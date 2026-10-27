package com.elkuku.agenttools.data

import com.elkuku.agenttools.UiText
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject

/** An agent in the list of agents who show their profile (`GET /api/agents`). */
data class AgentSummary(
    val agentName: String,
    /** From the latest stats; null without stats. */
    val faction: String? = null,
    val level: Long? = null,
    /** The Google profile picture (a `data:` URI), when the agent shows it. */
    val avatar: String? = null,
)

/** An agent's profile (`GET /api/agents/{agentName}`): null for the parts the agent keeps private. */
data class AgentProfile(
    val summary: AgentSummary,
    val latest: AgentStatEntry? = null,
    /** Newest first. */
    val history: List<AgentStatEntry>? = null,
    val customMedals: List<CustomMedalTier>? = null,
    /** The custom timeline events, with the history; newest first. */
    val events: List<TimelineEvent>? = null,
    /** What the agent synced from Bannergress. */
    val bannergress: BannergressSync? = null,
    /** The done list with the days, with [bannergress]. */
    val doneBanners: List<DoneBanner>? = null,
)

/** A user with the privacy settings, for admins (`GET /api/users`). */
data class UserPrivacy(
    val identifier: String,
    val role: UserRole?,
    val agentName: String?,
    val faction: String? = null,
    val level: Long? = null,
    /** Also when the user keeps it private: admins see it. */
    val avatar: String? = null,
    val visibleParts: Set<ProfilePart> = emptySet(),
    /** False until the user chose [visibleParts]: until then everything is private. */
    val visibilityChosen: Boolean = false,
) {
    /** The agent page can be opened: an agent name and the agent part shown. */
    val showsAgent: Boolean get() = agentName != null && ProfilePart.AGENT in visibleParts
}

sealed interface UsersResult {
    data class Success(val users: List<UserPrivacy>) : UsersResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : UsersResult

    /** E.g. 403 for a user who is no admin, or a network error. */
    data class Failure(val message: UiText) : UsersResult
}

sealed interface AgentsResult {
    data class Success(val agents: List<AgentSummary>) : AgentsResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : AgentsResult

    data class Failure(val message: UiText) : AgentsResult
}

sealed interface AgentProfileResult {
    data class Success(val profile: AgentProfile) : AgentProfileResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : AgentProfileResult

    /** E.g. 404: the agent no longer shows the profile, or a network error. */
    data class Failure(val message: UiText) : AgentProfileResult
}

@Serializable
internal data class AgentResponse(
    val agentName: String,
    val faction: String? = null,
    val level: Long? = null,
    val avatar: String? = null,
    val latestStats: JsonObject? = null,
    val history: List<JsonObject>? = null,
    val customMedals: JsonObject? = null,
    val events: List<TimelineEvent>? = null,
    val bannergress: BannergressSync? = null,
    val doneBanners: List<DoneBanner>? = null,
) {
    val summary: AgentSummary get() = AgentSummary(agentName, faction, level, avatar)

    val profile: AgentProfile
        get() = AgentProfile(
            summary,
            latestStats?.let(AgentStatEntry::fromJson),
            history?.map(AgentStatEntry::fromJson),
            customMedals?.let(::customMedalsFromJson),
            events,
            bannergress,
            doneBanners,
        )
}

@Serializable
internal data class AgentsPage(val member: List<AgentResponse> = emptyList())

@Serializable
internal data class UserPrivacyResponse(
    val identifier: String,
    val role: String,
    val agentName: String? = null,
    val faction: String? = null,
    val level: Long? = null,
    val avatar: String? = null,
    val visibleParts: List<String> = emptyList(),
    val visibilityChosen: Boolean = false,
) {
    val user: UserPrivacy
        get() = UserPrivacy(
            identifier,
            UserRole.fromServer(role),
            agentName,
            faction,
            level,
            avatar,
            visibleParts.mapNotNull(ProfilePart::fromServer).toSet(),
            visibilityChosen,
        )
}

@Serializable
internal data class UsersPage(val member: List<UserPrivacyResponse> = emptyList())
