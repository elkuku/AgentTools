package com.elkuku.uploadapp.data

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.engine.HttpClientEngine
import io.ktor.client.engine.okhttp.OkHttp
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.accept
import io.ktor.client.request.bearerAuth
import io.ktor.client.request.get
import io.ktor.client.request.post
import io.ktor.client.request.put
import io.ktor.client.request.setBody
import io.ktor.client.statement.HttpResponse
import io.ktor.http.ContentType
import io.ktor.http.HttpStatusCode
import io.ktor.http.URLBuilder
import io.ktor.http.takeFrom
import io.ktor.http.contentType
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import java.io.IOException

/** API Platform speaks JSON-LD; its errors are RFC 7807 problems. */
private val LdJson = ContentType("application", "ld+json")
private val ProblemJson = ContentType("application", "problem+json")

@Serializable
private data class CreatedResponse(val id: Int, val createdAt: String)

@Serializable
private data class Problem(val detail: String = "")

@Serializable
private data class Violation(val propertyPath: String = "", val message: String = "")

@Serializable
private data class ValidationResponse(val violations: List<Violation> = emptyList())

@Serializable
private data class CollectionView(val next: String? = null)

/** One page of a Hydra collection. */
@Serializable
private data class CollectionPage(val member: List<JsonObject> = emptyList(), val view: CollectionView? = null)

sealed interface SaveResult {
    data class Success(val id: Int, val createdAt: String) : SaveResult

    /** The ID token was rejected (expired, invalid, or the user is unknown to the server). */
    data class Unauthorized(val message: String) : SaveResult

    /** The server rejected the stats; one message per violation. */
    data class Invalid(val messages: List<String>) : SaveResult

    /** E.g. a network error, or 409 "Stat entry already added!". */
    data class Failure(val message: String) : SaveResult
}

sealed interface CustomMedalsResult {
    data class Success(val medals: List<CustomMedalTier>) : CustomMedalsResult

    /** The ID token was rejected (expired, invalid, or the user is unknown to the server). */
    data class Unauthorized(val message: String) : CustomMedalsResult

    /** E.g. a network error, or 422 for medals the server does not accept. */
    data class Failure(val message: String) : CustomMedalsResult
}

interface StatsApi {
    /** `POST /api/agent-stats`: Ingress all-time stats as an agent_stat row (see [parseIngressStats]). */
    suspend fun save(idToken: String, stats: JsonObject): SaveResult

    /** `GET /api/agent-stats`: the user's agent stats, newest first (all pages). */
    suspend fun list(idToken: String): AgentStatsResult

    /** `GET /api/me/custom-medals`: the user's custom medals. */
    suspend fun customMedals(idToken: String): CustomMedalsResult

    /** `PUT /api/me/custom-medals`: replaces all of the user's custom medals. */
    suspend fun saveCustomMedals(idToken: String, medals: List<CustomMedalTier>): CustomMedalsResult
}

class KtorStatsApi(
    baseUrl: String,
    engine: HttpClientEngine = OkHttp.create(),
) : StatsApi {
    private val url = "${baseUrl.trimEnd('/')}/api/agent-stats"

    private val customMedalsUrl = "${baseUrl.trimEnd('/')}/api/me/custom-medals"

    private val json = Json { ignoreUnknownKeys = true }

    private val client = HttpClient(engine) {
        expectSuccess = false
        install(ContentNegotiation) {
            json(json, LdJson)
            json(json, ProblemJson)
        }
    }

    override suspend fun save(idToken: String, stats: JsonObject): SaveResult {
        val response = try {
            client.post(url) {
                bearerAuth(idToken)
                contentType(LdJson)
                accept(LdJson)
                setBody(stats)
            }
        } catch (e: IOException) {
            return SaveResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.Created -> response.body<CreatedResponse>().let { SaveResult.Success(it.id, it.createdAt) }

            HttpStatusCode.Unauthorized -> SaveResult.Unauthorized(response.errorMessage() ?: "Unauthorized.")

            HttpStatusCode.UnprocessableEntity -> SaveResult.Invalid(
                response.decodeOrNull<ValidationResponse>()?.violations
                    ?.map { if (it.propertyPath.isBlank()) it.message else "${it.propertyPath}: ${it.message}" }
                    ?.ifEmpty { null }
                    ?: listOf("The server rejected the stats.")
            )

            // e.g. 409 "Stat entry already added!"
            else -> SaveResult.Failure(response.errorMessage() ?: "Server error: HTTP ${response.status.value}")
        }
    }

    override suspend fun list(idToken: String): AgentStatsResult {
        val entries = mutableListOf<AgentStatEntry>()
        var pageUrl: String? = url

        while (pageUrl != null) {
            val currentUrl: String = pageUrl
            val response = try {
                client.get(currentUrl) {
                    bearerAuth(idToken)
                    accept(LdJson)
                }
            } catch (e: IOException) {
                return AgentStatsResult.Failure(networkError(e))
            }

            when (response.status) {
                HttpStatusCode.OK -> {
                    val page = response.decodeOrNull<CollectionPage>()
                        ?: return AgentStatsResult.Failure("Unexpected response from the server.")
                    entries += page.member.map(AgentStatEntry::fromJson)
                    // view.next is an IRI like "/api/agent-stats?page=2"
                    pageUrl = page.view?.next?.let { URLBuilder(currentUrl).takeFrom(it).buildString() }
                }

                HttpStatusCode.Unauthorized ->
                    return AgentStatsResult.Unauthorized(response.errorMessage() ?: "Unauthorized.")

                else -> return AgentStatsResult.Failure("Server error: HTTP ${response.status.value}")
            }
        }

        return AgentStatsResult.Success(entries)
    }

    override suspend fun customMedals(idToken: String): CustomMedalsResult = customMedalsResult {
        client.get(customMedalsUrl) {
            bearerAuth(idToken)
            accept(LdJson)
        }
    }

    override suspend fun saveCustomMedals(idToken: String, medals: List<CustomMedalTier>): CustomMedalsResult =
        customMedalsResult {
            client.put(customMedalsUrl) {
                bearerAuth(idToken)
                contentType(LdJson)
                accept(LdJson)
                setBody(customMedalsToJson(medals))
            }
        }

    private suspend fun customMedalsResult(request: suspend () -> HttpResponse): CustomMedalsResult {
        val response = try {
            request()
        } catch (e: IOException) {
            return CustomMedalsResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<JsonObject>()
                ?.let { CustomMedalsResult.Success(customMedalsFromJson(it)) }
                ?: CustomMedalsResult.Failure("Unexpected response from the server.")

            HttpStatusCode.Unauthorized -> CustomMedalsResult.Unauthorized(response.errorMessage() ?: "Unauthorized.")

            HttpStatusCode.UnprocessableEntity -> CustomMedalsResult.Failure(
                response.decodeOrNull<ValidationResponse>()?.violations?.firstOrNull()
                    ?.let { "${it.propertyPath}: ${it.message}" }
                    ?: "The server rejected the custom medals.",
            )

            else -> CustomMedalsResult.Failure(response.errorMessage() ?: "Server error: HTTP ${response.status.value}")
        }
    }

    fun close() = client.close()

    private fun networkError(e: IOException) = "Network error: ${e.message ?: e::class.simpleName}"

    private suspend fun HttpResponse.errorMessage(): String? = decodeOrNull<Problem>()?.detail?.ifBlank { null }

    private suspend inline fun <reified T> HttpResponse.decodeOrNull(): T? =
        try {
            body<T>()
        } catch (_: Exception) {
            null
        }
}
