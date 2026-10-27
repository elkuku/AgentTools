package com.elkuku.agenttools.data

import androidx.annotation.StringRes
import com.elkuku.agenttools.R
import com.elkuku.agenttools.UiText
import com.elkuku.agenttools.uiText
import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.engine.HttpClientEngine
import io.ktor.client.engine.okhttp.OkHttp
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.accept
import io.ktor.client.request.bearerAuth
import io.ktor.client.request.delete
import io.ktor.client.request.get
import io.ktor.client.request.patch
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.client.statement.HttpResponse
import io.ktor.http.ContentType
import io.ktor.http.HttpStatusCode
import io.ktor.http.URLBuilder
import io.ktor.http.takeFrom
import io.ktor.http.contentType
import io.ktor.http.encodeURLPathPart
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import java.io.IOException

/** API Platform speaks JSON-LD; its errors are RFC 7807 problems. */
private val LdJson = ContentType("application", "ld+json")
private val ProblemJson = ContentType("application", "problem+json")
private val MergePatchJson = ContentType("application", "merge-patch+json")

@Serializable
private data class CreatedResponse(val id: Int, val createdAt: String)

@Serializable
private data class Problem(val detail: String = "")

@Serializable
private data class Violation(val propertyPath: String = "", val message: String = "")

@Serializable
private data class ValidationResponse(val violations: List<Violation> = emptyList())

@Serializable
private data class MeResponse(
    val role: String,
    val agentName: String? = null,
    val customMedals: JsonObject? = null,
    val visibleParts: List<String>? = null,
    // A server without it does not ask
    val visibilityChosen: Boolean = true,
    val avatar: String? = null,
    val bannergress: BannergressSync? = null,
) {
    val parts: Set<ProfilePart> get() = visibleParts.orEmpty().mapNotNull(ProfilePart::fromServer).toSet()
}

@Serializable
private data class VisiblePartsBody(val visibleParts: List<String>)

@Serializable
private data class BannergressBody(val bannergress: BannergressSync)

@Serializable
private data class TimelineEventBody(val date: String, val endDate: String?, val text: String)

@Serializable
private data class TimelineEventsPage(val member: List<TimelineEvent> = emptyList())

@Serializable
private data class DoneBannersPage(val member: List<DoneBanner> = emptyList())

@Serializable
private data class CollectionView(val next: String? = null)

/** One page of a Hydra collection. */
@Serializable
private data class CollectionPage(val member: List<JsonObject> = emptyList(), val view: CollectionView? = null)

sealed interface SaveResult {
    data class Success(val id: Int, val createdAt: String) : SaveResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : SaveResult

    /** The server rejected the stats; one message per violation. */
    data class Invalid(val messages: List<UiText>) : SaveResult

    /** E.g. a network error, or 409 for stats saved before. */
    data class Failure(val message: UiText) : SaveResult
}

sealed interface CustomMedalsResult {
    data class Success(val medals: List<CustomMedalTier>) : CustomMedalsResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : CustomMedalsResult

    /** E.g. a network error, or 422 for medals the server does not accept. */
    data class Failure(val message: UiText) : CustomMedalsResult
}

/** The user's role on the server, as `UserRole` in web. */
enum class UserRole(@param:StringRes val label: Int) {
    USER(R.string.role_user),
    ADMIN(R.string.role_admin);

    companion object {
        /** `ROLE_ADMIN` → [ADMIN]; null for a role the app does not know. */
        fun fromServer(role: String): UserRole? = entries.find { "ROLE_$it" == role }
    }
}

sealed interface MeResult {
    /**
     * [role] is null for a role the app does not know, [agentName] until the user or the first
     * stats upload sets it, [customMedals] when the server sends none. [visibleParts] are the parts
     * of the profile other users see; [visibilityChosen] is false until the user chose them.
     * [avatar] is the Google profile picture as web stores it: a JPEG `data:` URI.
     */
    data class Success(
        val role: UserRole?,
        val agentName: String? = null,
        val customMedals: List<CustomMedalTier>? = null,
        val visibleParts: Set<ProfilePart> = emptySet(),
        val visibilityChosen: Boolean = true,
        val avatar: String? = null,
        /** What the app synced from Bannergress; null until it did. */
        val bannergress: BannergressSync? = null,
    ) : MeResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : MeResult

    data class Failure(val message: UiText) : MeResult
}

sealed interface BannergressSaveResult {
    /** As stored, with the time the server set. */
    data class Success(val sync: BannergressSync) : BannergressSaveResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : BannergressSaveResult

    data class Failure(val message: UiText) : BannergressSaveResult
}

sealed interface VisibilityResult {
    /** The parts stored: none without [ProfilePart.AGENT]. */
    data class Success(val visibleParts: Set<ProfilePart>) : VisibilityResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : VisibilityResult

    /** E.g. a part the server does not know (422), or a network error. */
    data class Failure(val message: UiText) : VisibilityResult
}

interface StatsApi {
    /** `GET /api/me`: the signed-in user, with the agent name and custom medals. */
    suspend fun me(idToken: String): MeResult

    /** `PATCH /api/me`: the parts of the profile other users see; all private until chosen. */
    suspend fun saveVisibleParts(idToken: String, parts: Set<ProfilePart>): VisibilityResult

    /**
     * `POST /api/agent-stats`: Ingress all-time stats as an agent_stat row (see [parseIngressStats]).
     * Without an agent name, the stats' nickname becomes it (unless another user has it).
     */
    suspend fun save(idToken: String, stats: JsonObject): SaveResult

    /** `GET /api/agent-stats`: the user's agent stats, newest first (all pages). */
    suspend fun list(idToken: String): AgentStatsResult

    /** `PATCH /api/me`: replaces all of the user's custom medals. */
    suspend fun saveCustomMedals(idToken: String, medals: List<CustomMedalTier>): CustomMedalsResult

    /** `PATCH /api/me`: what the app synced from Bannergress, replacing the last sync. */
    suspend fun saveBannergress(idToken: String, sync: BannergressSync): BannergressSaveResult

    /** `GET /api/done-banners`: the user's done list, newest first, with the days. */
    suspend fun doneBanners(idToken: String): DoneBannersResult

    /** `PATCH /api/done-banners/{id}`: the day the banner was done, null to remove it. */
    suspend fun saveDoneAt(idToken: String, id: Int, doneAt: String?): DoneBannerResult

    /** `GET /api/agents`: the agents who show their profile to other users. */
    suspend fun agents(idToken: String): AgentsResult

    /** `GET /api/agents/{agentName}`: an agent's profile, with the parts the agent shows. */
    suspend fun agent(idToken: String, agentName: String): AgentProfileResult

    /** `GET /api/users`: every user with the privacy settings, for admins. */
    suspend fun users(idToken: String): UsersResult

    /** `GET /api/timeline-events`: the user's custom timeline events, newest first. */
    suspend fun timelineEvents(idToken: String): TimelineEventsResult

    /** `POST /api/timeline-events` for a new event (no id), else `PATCH /api/timeline-events/{id}`. */
    suspend fun saveTimelineEvent(idToken: String, event: TimelineEvent): TimelineEventResult

    /** `DELETE /api/timeline-events/{id}`. */
    suspend fun deleteTimelineEvent(idToken: String, id: Int): TimelineEventResult
}

/** [baseUrl] is read on every request: debug and dev builds can change the server on the settings screen. */
class KtorStatsApi(
    private val baseUrl: () -> String,
    engine: HttpClientEngine = OkHttp.create(),
) : StatsApi {
    constructor(baseUrl: String, engine: HttpClientEngine = OkHttp.create()) : this({ baseUrl }, engine)

    private val url get() = "${baseUrl().trimEnd('/')}/api/agent-stats"

    private val meUrl get() = "${baseUrl().trimEnd('/')}/api/me"

    private val agentsUrl get() = "${baseUrl().trimEnd('/')}/api/agents"

    private val usersUrl get() = "${baseUrl().trimEnd('/')}/api/users"

    private val timelineEventsUrl get() = "${baseUrl().trimEnd('/')}/api/timeline-events"

    private val doneBannersUrl get() = "${baseUrl().trimEnd('/')}/api/done-banners"


    private val json = Json { ignoreUnknownKeys = true }

    private val client = HttpClient(engine) {
        expectSuccess = false
        install(ContentNegotiation) {
            json(json, LdJson)
            json(json, ProblemJson)
            json(json, MergePatchJson)
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
                ?.let { MeResult.Success(UserRole.fromServer(it.role), it.agentName, it.customMedals?.let(::customMedalsFromJson), it.parts, it.visibilityChosen, it.avatar, it.bannergress) }
                ?: MeResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> MeResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            else -> MeResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
        }
    }

    override suspend fun saveVisibleParts(idToken: String, parts: Set<ProfilePart>): VisibilityResult {
        val response = try {
            client.patch(meUrl) {
                bearerAuth(idToken)
                contentType(MergePatchJson)
                accept(LdJson)
                setBody(VisiblePartsBody(parts.map { it.serverValue }))
            }
        } catch (e: IOException) {
            return VisibilityResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<MeResponse>()
                ?.let { VisibilityResult.Success(it.parts) }
                ?: VisibilityResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> VisibilityResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            HttpStatusCode.UnprocessableEntity -> VisibilityResult.Failure(
                response.decodeOrNull<ValidationResponse>()?.violations?.firstOrNull()?.message?.let(UiText::Plain)
                    ?: uiText(R.string.error_rejected_visible_parts),
            )

            else -> VisibilityResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
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

            HttpStatusCode.Unauthorized -> SaveResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            HttpStatusCode.UnprocessableEntity -> SaveResult.Invalid(
                response.decodeOrNull<ValidationResponse>()?.violations
                    ?.map { UiText.Plain(if (it.propertyPath.isBlank()) it.message else "${it.propertyPath}: ${it.message}") }
                    ?.ifEmpty { null }
                    // No violations: e.g. stats of another agent
                    ?: listOf(response.errorMessage() ?: uiText(R.string.error_rejected_stats))
            )

            HttpStatusCode.Conflict -> SaveResult.Failure(uiText(R.string.error_stats_already_saved))

            else -> SaveResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
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
                        ?: return AgentStatsResult.Failure(uiText(R.string.error_unexpected_response))
                    entries += page.member.map(AgentStatEntry::fromJson)
                    // view.next is an IRI like "/api/agent-stats?page=2"
                    pageUrl = page.view?.next?.let { URLBuilder(currentUrl).takeFrom(it).buildString() }
                }

                HttpStatusCode.Unauthorized ->
                    return AgentStatsResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

                else -> return AgentStatsResult.Failure(
                    response.errorMessage() ?: uiText(R.string.error_server, response.status.value),
                )
            }
        }

        return AgentStatsResult.Success(entries)
    }

    override suspend fun saveCustomMedals(idToken: String, medals: List<CustomMedalTier>): CustomMedalsResult {
        val response = try {
            client.patch(meUrl) {
                bearerAuth(idToken)
                contentType(MergePatchJson)
                accept(LdJson)
                // All groups: a merge patch keeps the groups it does not send
                setBody(buildJsonObject { put("customMedals", customMedalsToJson(medals)) })
            }
        } catch (e: IOException) {
            return CustomMedalsResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<MeResponse>()?.customMedals
                ?.let { CustomMedalsResult.Success(customMedalsFromJson(it)) }
                ?: CustomMedalsResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> CustomMedalsResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            HttpStatusCode.UnprocessableEntity -> CustomMedalsResult.Failure(
                response.decodeOrNull<ValidationResponse>()?.violations?.firstOrNull()
                    ?.let { UiText.Plain("${it.propertyPath}: ${it.message}") }
                    ?: uiText(R.string.error_rejected_custom_medals),
            )

            else -> CustomMedalsResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
        }
    }

    override suspend fun saveBannergress(idToken: String, sync: BannergressSync): BannergressSaveResult {
        val response = try {
            client.patch(meUrl) {
                bearerAuth(idToken)
                contentType(MergePatchJson)
                accept(LdJson)
                setBody(BannergressBody(sync))
            }
        } catch (e: IOException) {
            return BannergressSaveResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<MeResponse>()?.bannergress
                ?.let { BannergressSaveResult.Success(it) }
                ?: BannergressSaveResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> BannergressSaveResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            HttpStatusCode.UnprocessableEntity -> BannergressSaveResult.Failure(
                response.decodeOrNull<ValidationResponse>()?.violations?.firstOrNull()
                    ?.let { UiText.Plain("${it.propertyPath}: ${it.message}") }
                    ?: uiText(R.string.error_server, response.status.value),
            )

            else -> BannergressSaveResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
        }
    }

    override suspend fun doneBanners(idToken: String): DoneBannersResult {
        val response = try {
            client.get(doneBannersUrl) {
                bearerAuth(idToken)
                accept(LdJson)
            }
        } catch (e: IOException) {
            return DoneBannersResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<DoneBannersPage>()
                ?.let { DoneBannersResult.Success(it.member) }
                ?: DoneBannersResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> DoneBannersResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            else -> DoneBannersResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
        }
    }

    override suspend fun saveDoneAt(idToken: String, id: Int, doneAt: String?): DoneBannerResult {
        val response = try {
            client.patch("$doneBannersUrl/$id") {
                bearerAuth(idToken)
                contentType(MergePatchJson)
                accept(LdJson)
                // Explicitly null to remove the day
                setBody(buildJsonObject { put("doneAt", doneAt) })
            }
        } catch (e: IOException) {
            return DoneBannerResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<DoneBanner>()
                ?.let { DoneBannerResult.Success(it) }
                ?: DoneBannerResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> DoneBannerResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            else -> DoneBannerResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
        }
    }

    override suspend fun agents(idToken: String): AgentsResult {
        val response = try {
            client.get(agentsUrl) {
                bearerAuth(idToken)
                accept(LdJson)
            }
        } catch (e: IOException) {
            return AgentsResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<AgentsPage>()
                ?.let { page -> AgentsResult.Success(page.member.map { it.summary }) }
                ?: AgentsResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> AgentsResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            else -> AgentsResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
        }
    }

    override suspend fun agent(idToken: String, agentName: String): AgentProfileResult {
        val response = try {
            client.get("$agentsUrl/${agentName.encodeURLPathPart()}") {
                bearerAuth(idToken)
                accept(LdJson)
            }
        } catch (e: IOException) {
            return AgentProfileResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<AgentResponse>()
                ?.let { AgentProfileResult.Success(it.profile) }
                ?: AgentProfileResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> AgentProfileResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            HttpStatusCode.NotFound -> AgentProfileResult.Failure(uiText(R.string.error_agent_private, agentName))

            else -> AgentProfileResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
        }
    }

    override suspend fun users(idToken: String): UsersResult {
        val response = try {
            client.get(usersUrl) {
                bearerAuth(idToken)
                accept(LdJson)
            }
        } catch (e: IOException) {
            return UsersResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<UsersPage>()
                ?.let { page -> UsersResult.Success(page.member.map { it.user }) }
                ?: UsersResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> UsersResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            else -> UsersResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
        }
    }

    override suspend fun timelineEvents(idToken: String): TimelineEventsResult {
        val response = try {
            client.get(timelineEventsUrl) {
                bearerAuth(idToken)
                accept(LdJson)
            }
        } catch (e: IOException) {
            return TimelineEventsResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK -> response.decodeOrNull<TimelineEventsPage>()
                ?.let { TimelineEventsResult.Success(it.member) }
                ?: TimelineEventsResult.Failure(uiText(R.string.error_unexpected_response))

            HttpStatusCode.Unauthorized -> TimelineEventsResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

            else -> TimelineEventsResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
        }
    }

    override suspend fun saveTimelineEvent(idToken: String, event: TimelineEvent): TimelineEventResult {
        // A null end date clears it (merge patch)
        val body = TimelineEventBody(event.date, event.endDate, event.text)
        val response = try {
            if (event.id == null) {
                client.post(timelineEventsUrl) {
                    bearerAuth(idToken)
                    contentType(LdJson)
                    accept(LdJson)
                    setBody(body)
                }
            } else {
                client.patch("$timelineEventsUrl/${event.id}") {
                    bearerAuth(idToken)
                    contentType(MergePatchJson)
                    accept(LdJson)
                    setBody(body)
                }
            }
        } catch (e: IOException) {
            return TimelineEventResult.Failure(networkError(e))
        }

        return when (response.status) {
            HttpStatusCode.OK, HttpStatusCode.Created -> response.decodeOrNull<TimelineEvent>()
                ?.let { TimelineEventResult.Success(it) }
                ?: TimelineEventResult.Failure(uiText(R.string.error_unexpected_response))

            else -> timelineEventError(response)
        }
    }

    override suspend fun deleteTimelineEvent(idToken: String, id: Int): TimelineEventResult {
        val response = try {
            client.delete("$timelineEventsUrl/$id") {
                bearerAuth(idToken)
                accept(LdJson)
            }
        } catch (e: IOException) {
            return TimelineEventResult.Failure(networkError(e))
        }

        return if (response.status == HttpStatusCode.NoContent) TimelineEventResult.Success(null) else timelineEventError(response)
    }

    private suspend fun timelineEventError(response: HttpResponse): TimelineEventResult = when (response.status) {
        HttpStatusCode.Unauthorized -> TimelineEventResult.Unauthorized(response.errorMessage() ?: uiText(R.string.error_unauthorized))

        HttpStatusCode.UnprocessableEntity -> TimelineEventResult.Failure(
            response.decodeOrNull<ValidationResponse>()?.violations?.firstOrNull()
                ?.let { UiText.Plain("${it.propertyPath}: ${it.message}") }
                ?: uiText(R.string.error_rejected_timeline_event),
        )

        else -> TimelineEventResult.Failure(response.errorMessage() ?: uiText(R.string.error_server, response.status.value))
    }

    fun close() = client.close()

    private fun networkError(e: IOException) = uiText(R.string.error_network, e.message ?: e::class.simpleName.orEmpty())

    private suspend fun HttpResponse.errorMessage(): UiText? = decodeOrNull<Problem>()?.detail?.ifBlank { null }?.let(UiText::Plain)

    private suspend inline fun <reified T> HttpResponse.decodeOrNull(): T? =
        try {
            body<T>()
        } catch (_: Exception) {
            null
        }
}
