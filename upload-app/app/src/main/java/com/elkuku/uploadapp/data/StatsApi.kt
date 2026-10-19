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
import io.ktor.client.request.setBody
import io.ktor.client.statement.HttpResponse
import io.ktor.http.ContentType
import io.ktor.http.HttpStatusCode
import io.ktor.http.contentType
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import java.io.IOException

@Serializable
private data class CreatedResponse(val id: Int, val createdAt: String)

@Serializable
private data class ErrorResponse(val error: String = "")

@Serializable
private data class Violation(val propertyPath: String = "", val title: String = "")

@Serializable
private data class ValidationResponse(val violations: List<Violation> = emptyList())

sealed interface SaveResult {
    data class Success(val id: Int, val createdAt: String) : SaveResult

    /** The ID token was rejected (expired, invalid, or the user is unknown to the server). */
    data class Unauthorized(val message: String) : SaveResult

    /** The server rejected the stats; one message per violation. */
    data class Invalid(val messages: List<String>) : SaveResult

    /** E.g. a network error, or 409 "Stat entry already added!". */
    data class Failure(val message: String) : SaveResult
}

interface StatsApi {
    /** `POST /api/agent-stats`: Ingress all-time stats as an agent_stat row (see [parseIngressStats]). */
    suspend fun save(idToken: String, stats: JsonObject): SaveResult

    /** `GET /api/agent-stats`: the user's agent stats, newest first. */
    suspend fun list(idToken: String): AgentStatsResult
}

class KtorStatsApi(
    baseUrl: String,
    engine: HttpClientEngine = OkHttp.create(),
) : StatsApi {
    private val url = "${baseUrl.trimEnd('/')}/api/agent-stats"

    private val json = Json { ignoreUnknownKeys = true }

    private val client = HttpClient(engine) {
        expectSuccess = false
        install(ContentNegotiation) { json(json) }
    }

    override suspend fun save(idToken: String, stats: JsonObject): SaveResult {
        val response = try {
            client.post(url) {
                bearerAuth(idToken)
                contentType(ContentType.Application.Json)
                accept(ContentType.Application.Json)
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
                    ?.map { if (it.propertyPath.isBlank()) it.title else "${it.propertyPath}: ${it.title}" }
                    ?.ifEmpty { null }
                    ?: listOf("The server rejected the stats.")
            )

            // e.g. 409 "Stat entry already added!"
            else -> SaveResult.Failure(response.errorMessage() ?: "Server error: HTTP ${response.status.value}")
        }
    }

    override suspend fun list(idToken: String): AgentStatsResult {
        val response = try {
            client.get(url) {
                bearerAuth(idToken)
                accept(ContentType.Application.Json)
            }
        } catch (e: IOException) {
            return AgentStatsResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<List<JsonObject>>()
                ?.let { list -> AgentStatsResult.Success(list.map(AgentStatEntry::fromJson)) }
                ?: AgentStatsResult.Failure("Unexpected response from the server.")

            HttpStatusCode.Unauthorized -> AgentStatsResult.Unauthorized(response.errorMessage() ?: "Unauthorized.")

            else -> AgentStatsResult.Failure("Server error: HTTP ${response.status.value}")
        }
    }

    fun close() = client.close()

    private fun networkError(e: IOException) = "Network error: ${e.message ?: e::class.simpleName}"

    private suspend fun HttpResponse.errorMessage(): String? = decodeOrNull<ErrorResponse>()?.error?.ifBlank { null }

    private suspend inline fun <reified T> HttpResponse.decodeOrNull(): T? =
        try {
            body<T>()
        } catch (_: Exception) {
            null
        }
}
