package com.elkuku.agenttools.data

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
private data class MeResponse(val role: String, val agentName: String? = null)

@Serializable
private data class AgentNameBody(val agentName: String)

@Serializable
private data class CollectionView(val next: String? = null)

/** One page of a Hydra collection. */
@Serializable
private data class CollectionPage(val member: List<JsonObject> = emptyList(), val view: CollectionView? = null)

sealed interface SaveResult {
    data class Success(val id: Int, val createdAt: String) : SaveResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: String) : SaveResult

    /** 403: the user is not an agent (yet); [message] is the server's reason. */
    data class Forbidden(val message: String) : SaveResult

    /** The server rejected the stats; one message per violation. */
    data class Invalid(val messages: List<String>) : SaveResult

    /** E.g. a network error, or 409 "Stat entry already added!". */
    data class Failure(val message: String) : SaveResult
}

sealed interface CustomMedalsResult {
    data class Success(val medals: List<CustomMedalTier>) : CustomMedalsResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: String) : CustomMedalsResult

    /** 403: the user is not an agent (yet); [message] is the server's reason. */
    data class Forbidden(val message: String) : CustomMedalsResult

    /** E.g. a network error, or 422 for medals the server does not accept. */
    data class Failure(val message: String) : CustomMedalsResult
}

/** The user's role on the server, as `UserRole` in web. */
enum class UserRole(val label: String) {
    USER("User"),
    AGENT("Agent"),
    ADMIN("Admin");

    companion object {
        /** `ROLE_AGENT` → [AGENT]; null for a role the app does not know. */
        fun fromServer(role: String): UserRole? = entries.find { "ROLE_$it" == role }
    }

    /** May see and upload agent stats (ROLE_AGENT, and admins through the role hierarchy). */
    val isAgent: Boolean get() = this != USER
}

sealed interface MeResult {
    /** [role] is null for a role the app does not know, [agentName] until the agent sets it. */
    data class Success(val role: UserRole?, val agentName: String? = null) : MeResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: String) : MeResult

    data class Failure(val message: String) : MeResult
}

sealed interface AgentNameResult {
    data class Success(val agentName: String) : AgentNameResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: String) : AgentNameResult

    /** E.g. the name is taken or not an agent name (422), or a network error. */
    data class Failure(val message: String) : AgentNameResult
}

interface StatsApi {
    /** `GET /api/me`: the signed-in user. */
    suspend fun me(idToken: String): MeResult

    /** `PUT /api/me/agent-name`: the agent name; uploaded stats must be for this agent. */
    suspend fun saveAgentName(idToken: String, agentName: String): AgentNameResult

    /** `POST /api/agent-stats`: Ingress all-time stats as an agent_stat row (see [parseIngressStats]). */
    suspend fun save(idToken: String, stats: JsonObject): SaveResult

    /** `GET /api/agent-stats`: the user's agent stats, newest first (all pages). */
    suspend fun list(idToken: String): AgentStatsResult

    /** `GET /api/me/custom-medals`: the user's custom medals. */
    suspend fun customMedals(idToken: String): CustomMedalsResult

    /** `PUT /api/me/custom-medals`: replaces all of the user's custom medals. */
    suspend fun saveCustomMedals(idToken: String, medals: List<CustomMedalTier>): CustomMedalsResult
}

/** [baseUrl] is read on every request: debug builds can change the server on the settings screen. */
class KtorStatsApi(
    private val baseUrl: () -> String,
    engine: HttpClientEngine = OkHttp.create(),
) : StatsApi {
    constructor(baseUrl: String, engine: HttpClientEngine = OkHttp.create()) : this({ baseUrl }, engine)

    private val url get() = "${baseUrl().trimEnd('/')}/api/agent-stats"

    private val meUrl get() = "${baseUrl().trimEnd('/')}/api/me"

    private val agentNameUrl get() = "${baseUrl().trimEnd('/')}/api/me/agent-name"

    private val customMedalsUrl get() = "${baseUrl().trimEnd('/')}/api/me/custom-medals"

    private val json = Json { ignoreUnknownKeys = true }

    private val client = HttpClient(engine) {
        expectSuccess = false
        install(ContentNegotiation) {
            json(json, LdJson)
            json(json, ProblemJson)
        }
    }

    override suspend fun me(idToken: String): MeResult {
        val response = try {
            client.get(meUrl) {
                bearerAuth(idToken)
                accept(LdJson)
            }
        } catch (e: IOException) {
            return MeResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<MeResponse>()
                ?.let { MeResult.Success(UserRole.fromServer(it.role), it.agentName) }
                ?: MeResult.Failure("Unexpected response from the server.")

            HttpStatusCode.Unauthorized -> MeResult.Unauthorized(response.errorMessage() ?: "Unauthorized.")

            else -> MeResult.Failure(response.errorMessage() ?: "Server error: HTTP ${response.status.value}")
        }
    }

    override suspend fun saveAgentName(idToken: String, agentName: String): AgentNameResult {
        val response = try {
            client.put(agentNameUrl) {
                bearerAuth(idToken)
                contentType(LdJson)
                accept(LdJson)
                setBody(AgentNameBody(agentName))
            }
        } catch (e: IOException) {
            return AgentNameResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<AgentNameBody>()
                ?.let { AgentNameResult.Success(it.agentName) }
                ?: AgentNameResult.Failure("Unexpected response from the server.")

            HttpStatusCode.Unauthorized -> AgentNameResult.Unauthorized(response.errorMessage() ?: "Unauthorized.")

            // e.g. "This agent name is already taken."
            HttpStatusCode.UnprocessableEntity -> AgentNameResult.Failure(
                response.decodeOrNull<ValidationResponse>()?.violations?.firstOrNull()?.message
                    ?: "The server rejected the agent name.",
            )

            else -> AgentNameResult.Failure(response.errorMessage() ?: "Server error: HTTP ${response.status.value}")
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

            HttpStatusCode.Forbidden -> SaveResult.Forbidden(response.errorMessage() ?: "Forbidden.")

            HttpStatusCode.UnprocessableEntity -> SaveResult.Invalid(
                response.decodeOrNull<ValidationResponse>()?.violations
                    ?.map { if (it.propertyPath.isBlank()) it.message else "${it.propertyPath}: ${it.message}" }
                    ?.ifEmpty { null }
                    // No violations: e.g. stats of another agent
                    ?: listOf(response.errorMessage() ?: "The server rejected the stats.")
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

                HttpStatusCode.Forbidden ->
                    return AgentStatsResult.Forbidden(response.errorMessage() ?: "Forbidden.")

                else -> return AgentStatsResult.Failure(
                    response.errorMessage() ?: "Server error: HTTP ${response.status.value}",
                )
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

            HttpStatusCode.Forbidden -> CustomMedalsResult.Forbidden(response.errorMessage() ?: "Forbidden.")

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
