package com.elkuku.agenttools.ui

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.elkuku.agenttools.R
import com.elkuku.agenttools.UiText
import com.elkuku.agenttools.data.TimelineEvent
import com.elkuku.agenttools.data.TimelineEventResult
import com.elkuku.agenttools.data.TimelineEventsResult
import com.elkuku.agenttools.uiText
import com.elkuku.agenttools.auth.AuthException
import com.elkuku.agenttools.auth.BannergressConnector
import com.elkuku.agenttools.auth.BannergressLogin
import com.elkuku.agenttools.data.BannergressResult
import com.elkuku.agenttools.data.BannergressSaveResult
import com.elkuku.agenttools.data.BannergressSync
import com.elkuku.agenttools.data.DoneBanner
import com.elkuku.agenttools.data.DoneBannerResult
import com.elkuku.agenttools.data.DoneBannersResult
import com.elkuku.agenttools.auth.GoogleAuth
import com.elkuku.agenttools.auth.GoogleSession
import com.elkuku.agenttools.data.AgentProfile
import com.elkuku.agenttools.data.AgentProfileResult
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.AgentStatsResult
import com.elkuku.agenttools.data.AgentSummary
import com.elkuku.agenttools.data.AgentsResult
import com.elkuku.agenttools.data.CustomMedalsResult
import com.elkuku.agenttools.data.CustomMedalTier
import com.elkuku.agenttools.data.ImportResult
import com.elkuku.agenttools.data.IngressEventCache
import com.elkuku.agenttools.data.IngressEventList
import com.elkuku.agenttools.data.IngressEventListResult
import com.elkuku.agenttools.data.LinkListCache
import com.elkuku.agenttools.data.LinkListResult
import com.elkuku.agenttools.data.LinkSection
import com.elkuku.agenttools.data.MeResult
import com.elkuku.agenttools.data.ProfilePart
import com.elkuku.agenttools.data.SaveResult
import com.elkuku.agenttools.data.StatsApi
import com.elkuku.agenttools.data.UserPrivacy
import com.elkuku.agenttools.data.UserRole
import com.elkuku.agenttools.data.UsersResult
import com.elkuku.agenttools.data.VisibilityResult
import com.elkuku.agenttools.data.importResultFor
import com.elkuku.agenttools.data.mergeCustomMedals
import com.elkuku.agenttools.data.parseIngressStats
import com.elkuku.agenttools.scan.ArgbImage
import com.elkuku.agenttools.scan.RecognizedMedal
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonPrimitive
import java.io.IOException

/** A message for the user; [untilSignIn] ones are about being signed out and go away on sign-in. */
data class Status(val text: UiText, val isError: Boolean, val untilSignIn: Boolean = false)

data class UiState(
    val session: GoogleSession? = null,
    /** The signed-in user's role on the server; null until loaded or for an unknown role. */
    val role: UserRole? = null,
    /** The agent name stats must be for; null until loaded, or until the user or the first saved stats set it. */
    val agentName: String? = null,
    /** The Google profile picture as a `data:` URI (from web); null until loaded or without one. */
    val avatar: String? = null,
    /** The parts of the profile other users see; empty until loaded or chosen. */
    val visibleParts: Set<ProfilePart> = emptySet(),
    /** The result of saving the visible parts, shown on the settings screen. */
    val visibilityStatus: Status? = null,
    /** The settings screen is shown instead of the home page. */
    val settingsOpen: Boolean = false,
    /** The user's stats ("My stats") are shown instead of the home page. */
    val statsOpen: Boolean = false,
    /** The timeline is shown over "My stats". */
    val timelineOpen: Boolean = false,
    /** The list of agents (or [agent]) is shown instead of the home page. */
    val agentsOpen: Boolean = false,
    /** The agents who show their profile; null until loaded, and for admins. */
    val agents: List<AgentSummary>? = null,
    /** Admin agents ([users]) are shown instead of the agents. */
    val adminAgents: Boolean = false,
    /** Admin agents: every user with the privacy settings; null until loaded. */
    val users: List<UserPrivacy>? = null,
    /** The agent profile shown over the list; null for the list. */
    val agent: AgentProfile? = null,
    /** Why the agents or an agent could not be loaded. */
    val agentsStatus: Status? = null,
    val busy: Boolean = false,
    /** What takes a while (saving, scanning), shown with a loading animation; null for nothing. */
    val progress: UiText? = null,
    /** The user's stats, newest first; null until loaded. */
    val entries: List<AgentStatEntry>? = null,
    /** The user's custom timeline events, newest first; null until loaded. */
    val timelineEvents: List<TimelineEvent>? = null,
    /** Stats shared from Ingress before the user signed in; saved after sign-in. */
    val pending: JsonObject? = null,
    /** What changed with the last saved stats, as on 4e-website after an import. */
    val importResult: ImportResult? = null,
    /** The user's custom medals; null until loaded. */
    val customMedals: List<CustomMedalTier>? = null,
    /** Custom medals recognized in shared screenshots, for the user to review before saving. */
    val medalScan: List<RecognizedMedal>? = null,
    /** How many recognized custom medals were left out of [medalScan] because they are saved already. */
    val medalScanKnown: Int = 0,
    /** Reviewed custom medals not saved yet: before sign-in, or after a failed save. */
    val pendingMedals: List<CustomMedalTier>? = null,
    val status: Status? = null,
    /** False until the user chose what other users see (in the settings or on web's profile page); true until loaded. */
    val visibilityChosen: Boolean = true,
    /** The user opened the settings from the prompt or chose "Later": not asked again until the next sign-in. */
    val visibilityDismissed: Boolean = false,
    /** What the app synced from Bannergress; null until loaded or synced. */
    val bannergress: BannergressSync? = null,
    val bannergressSignedIn: Boolean = false,
    /** The Bannergress sign-in shown; null for none. */
    val bannergressLogin: BannergressLogin? = null,
    /** The user's Bannergress done list with the days; null until loaded. */
    val doneBanners: List<DoneBanner>? = null,
    /** "My banners" is shown, over "My stats" when opened from there. */
    val bannergressOpen: Boolean = false,
    /** The Bannergress screen of the [agent] shown is open. */
    val agentBannergressOpen: Boolean = false,
    /** The timeline screen of the [agent] shown is open. */
    val agentTimelineOpen: Boolean = false,
    /** The Ingress link list is shown, over any other screen. */
    val linksOpen: Boolean = false,
    /** The link list: the saved one until loaded; null without either. */
    val links: List<LinkSection>? = null,
    /** The link list is loading (it does not need a sign-in, so not [busy]). */
    val linksLoading: Boolean = false,
    /** Why the link list could not be loaded. */
    val linksStatus: Status? = null,
    /** The calendar of Ingress events is shown, over any other screen. */
    val eventsOpen: Boolean = false,
    /** The events: the saved ones until loaded; null without either. */
    val events: IngressEventList? = null,
    /** The events are loading (they do not need a sign-in, so not [busy]). */
    val eventsLoading: Boolean = false,
    /** Why the events could not be loaded. */
    val eventsStatus: Status? = null,
) {
    /** Ask the user to choose what other users see of the profile. */
    val askVisibility: Boolean get() = session != null && !visibilityChosen && !visibilityDismissed

    val latest: AgentStatEntry? get() = entries?.firstOrNull()

    val first: AgentStatEntry? get() = entries?.lastOrNull()
}

class MainViewModel(
    private val auth: GoogleAuth,
    private val api: StatsApi,
    private val scanMedals: suspend (List<ArgbImage>) -> List<RecognizedMedal>,
    private val bannergress: BannergressConnector? = null,
    private val linkCache: LinkListCache? = null,
    private val eventCache: IngressEventCache? = null,
) : ViewModel() {
    private val _state = MutableStateFlow(UiState())

    private val tokenRefresh = Mutex()
    val state: StateFlow<UiState> = _state.asStateFlow()

    init {
        bannergress?.let { account ->
            viewModelScope.launch { account.signedIn.collect { signedIn -> _state.update { it.copy(bannergressSignedIn = signedIn) } } }
        }
    }

    fun signIn(context: Context) = launchBusy {
        try {
            val session = withProgress(uiText(R.string.signing_in)) { auth.signIn(context) }
            signedIn(session)
            withProgress(uiText(R.string.loading_your_stats)) { savePendingOrLoad(context) }
        } catch (e: AuthException) {
            _state.update { it.copy(status = if (e.cancelled) null else Status(e.text, true)) }
        }
    }

    fun signOut(context: Context) = launchBusy {
        auth.signOut(context)
        // The next Google account must not sync this Bannergress account
        bannergress?.signOut()
        _state.update {
            it.copy(
                session = null,
                role = null,
                agentName = null,
                visibleParts = emptySet(),
                avatar = null,
                visibilityStatus = null,
                entries = null,
                timelineEvents = null,
                importResult = null,
                customMedals = null,
                bannergress = null,
                doneBanners = null,
                bannergressOpen = false,
                visibilityChosen = true,
                statsOpen = false,
                timelineOpen = false,
                agentsOpen = false,
                adminAgents = false,
                agents = null,
                users = null,
                agent = null,
                agentsStatus = null,
                status = Status(uiText(R.string.signed_out), false, untilSignIn = true),
            )
        }
    }

    fun refresh(context: Context) {
        if (_state.value.session == null) return
        // An earlier message (e.g. a failed load) is outdated now
        _state.update { it.copy(status = null) }
        launchBusy {
            // E.g. the agent name or the custom medals changed on another device
            loadRoleAndStats(context)
            savePendingMedals(context)
        }
    }

    /**
     * Text shared from another app: Ingress all-time stats are saved right away, or after sign-in
     * when the user is not signed in yet.
     */
    fun onShared(context: Context, text: String) {
        // The result of a share shows on the stats screen
        _state.update { it.copy(settingsOpen = false, agentsOpen = false, linksOpen = false, eventsOpen = false, statsOpen = true, timelineOpen = false) }
        val stats = parseIngressStats(text)
        if (stats == null) {
            _state.update { it.copy(status = Status(NOT_STATS_MESSAGE, true)) }
            return
        }

        _state.update { it.copy(pending = stats, importResult = null, status = null) }
        if (_state.value.session == null) {
            _state.update { it.copy(status = Status(uiText(R.string.sign_in_to_save_stats), false)) }
        } else {
            launchBusy { savePendingOrLoad(context) }
        }
    }

    fun openSettings() = _state.update { it.copy(settingsOpen = true) }

    fun openStats() = _state.update { it.copy(statsOpen = true) }

    fun closeStats() = _state.update { it.copy(statsOpen = false, timelineOpen = false, bannergressOpen = false) }

    fun openTimeline() = _state.update { it.copy(timelineOpen = true) }

    /** "My timeline" from the account menu: over "My stats", where back goes, as from there. */
    fun openMyTimeline() = _state.update { it.copy(statsOpen = true, timelineOpen = true) }

    fun closeTimeline() = _state.update { it.copy(timelineOpen = false) }

    /** "My banners": from the stats, the home page or the menu; back goes where it came from. */
    fun openBannergress() = _state.update { it.copy(bannergressOpen = true, settingsOpen = false, agentsOpen = false) }

    fun closeBannergress() = _state.update { it.copy(bannergressOpen = false) }

    fun openAgentBannergress() = _state.update { it.copy(agentBannergressOpen = true) }

    fun closeAgentBannergress() = _state.update { it.copy(agentBannergressOpen = false) }

    fun openAgentTimeline() = _state.update { it.copy(agentTimelineOpen = true) }

    fun closeAgentTimeline() = _state.update { it.copy(agentTimelineOpen = false) }

    /** The agents who show their profile, or with [admin] every user (admins only), loaded each time. */
    fun openAgents(context: Context, admin: Boolean = false) {
        _state.update {
            it.copy(agentsOpen = true, adminAgents = admin, agents = null, users = null, agent = null, agentBannergressOpen = false, agentTimelineOpen = false, agentsStatus = null)
        }
        launchBusy { loadAgents(context) }
    }

    fun closeAgents() = _state.update { it.copy(agentsOpen = false, adminAgents = false, agents = null, users = null, agent = null, agentsStatus = null) }

    fun openAgent(context: Context, agentName: String) = launchBusy {
        _state.update { it.copy(agentsStatus = null) }
        when (val result = withToken(context, { it is AgentProfileResult.Unauthorized }) { api.agent(it, agentName) }) {
            is AgentProfileResult.Success -> _state.update { it.copy(agent = result.profile) }
            is AgentProfileResult.Failure -> _state.update { it.copy(agentsStatus = Status(result.message, true)) }
            is AgentProfileResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    /** Back from an agent to the list. */
    fun closeAgent() = _state.update { it.copy(agent = null, agentBannergressOpen = false, agentTimelineOpen = false, agentsStatus = null) }

    /** Reloads the agent shown, or the list. */
    fun refreshAgents(context: Context) {
        val agent = _state.value.agent
        if (agent != null) openAgent(context, agent.summary.agentName) else launchBusy { loadAgents(context) }
    }

    private suspend fun loadUsers(context: Context) {
        when (val result = withToken(context, { it is UsersResult.Unauthorized }) { api.users(it) }) {
            is UsersResult.Success -> _state.update { it.copy(users = result.users, agentsStatus = null) }
            is UsersResult.Failure -> _state.update { it.copy(agentsStatus = Status(result.message, true)) }
            is UsersResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    private suspend fun loadAgents(context: Context) {
        if (_state.value.adminAgents) {
            loadUsers(context)
            return
        }

        when (val result = withToken(context, { it is AgentsResult.Unauthorized }) { api.agents(it) }) {
            is AgentsResult.Success -> _state.update { it.copy(agents = result.agents, agentsStatus = null) }
            is AgentsResult.Failure -> _state.update { it.copy(agentsStatus = Status(result.message, true)) }
            is AgentsResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    /** The link list: the saved one right away, then loaded again (signed in or not). */
    fun openLinks() {
        _state.update { it.copy(linksOpen = true, links = it.links ?: linkCache?.read(), linksStatus = null) }
        refreshLinks()
    }

    fun closeLinks() = _state.update { it.copy(linksOpen = false, linksStatus = null) }

    fun refreshLinks() {
        if (_state.value.linksLoading) return
        _state.update { it.copy(linksLoading = true) }
        viewModelScope.launch {
            try {
                when (val result = api.links()) {
                    is LinkListResult.Success -> {
                        linkCache?.write(result.sections)
                        _state.update { it.copy(links = result.sections, linksStatus = null) }
                    }

                    // Offline the saved list stays, with why it is not the latest
                    is LinkListResult.Failure -> _state.update {
                        it.copy(
                            linksStatus = if (it.links != null) {
                                Status(uiText(R.string.links_saved_list, result.message), false)
                            } else {
                                Status(result.message, true)
                            },
                        )
                    }
                }
            } finally {
                _state.update { it.copy(linksLoading = false) }
            }
        }
    }

    /** The events: the saved ones right away, then loaded again (signed in or not). */
    fun openEvents() {
        _state.update { it.copy(eventsOpen = true, events = it.events ?: eventCache?.read(), eventsStatus = null) }
        refreshEvents()
    }

    fun closeEvents() = _state.update { it.copy(eventsOpen = false, eventsStatus = null) }

    fun refreshEvents() {
        if (_state.value.eventsLoading) return
        _state.update { it.copy(eventsLoading = true) }
        viewModelScope.launch {
            try {
                when (val result = api.events()) {
                    is IngressEventListResult.Success -> {
                        eventCache?.write(result.list)
                        _state.update { it.copy(events = result.list, eventsStatus = null) }
                    }

                    // Offline the saved list stays, with why it is not the latest
                    is IngressEventListResult.Failure -> _state.update {
                        it.copy(
                            eventsStatus = if (it.events != null) {
                                Status(uiText(R.string.links_saved_list, result.message), false)
                            } else {
                                Status(result.message, true)
                            },
                        )
                    }
                }
            } finally {
                _state.update { it.copy(eventsLoading = false) }
            }
        }
    }

    fun closeSettings() = _state.update { it.copy(settingsOpen = false, visibilityStatus = null) }

    /** Saves the parts of the profile other users see; without [ProfilePart.AGENT] the server keeps none. */
    fun saveVisibleParts(context: Context, parts: Set<ProfilePart>) = launchBusy {
        val result = withToken(context, { it is VisibilityResult.Unauthorized }) { api.saveVisibleParts(it, parts) }
        when (result) {
            is VisibilityResult.Success -> _state.update {
                it.copy(visibleParts = result.visibleParts, visibilityChosen = true, visibilityStatus = Status(uiText(R.string.saved), false))
            }
            is VisibilityResult.Failure -> _state.update { it.copy(visibilityStatus = Status(result.message, true)) }
            is VisibilityResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    /** The data shown came from the old server: forget it and load it from the new one. */
    fun serverChanged(context: Context) {
        if (_state.value.session == null) return
        _state.update { it.copy(role = null, agentName = null, entries = null, timelineEvents = null, importResult = null, customMedals = null, visibleParts = emptySet(), avatar = null, visibilityChosen = true, bannergress = null, doneBanners = null) }
        refresh(context)
    }

    fun dismissImportResult() = _state.update { it.copy(importResult = null) }

    fun dismissVisibility() = _state.update { it.copy(visibilityDismissed = true) }

    /** From the prompt: the visibility is chosen in the settings. */
    fun chooseVisibility() = _state.update { it.copy(visibilityDismissed = true, settingsOpen = true) }

    /**
     * Screenshots of the Ingress medal grid, from [read]: the recognized custom medals are shown for
     * review, except those already saved (or waiting to be saved) with the same or a higher tier.
     * Ingress shows only the highest tier, so a lower one is a wrong match.
     */
    fun onSharedScreenshots(read: suspend () -> List<ArgbImage>) = launchBusy {
        _state.update { it.copy(settingsOpen = false, agentsOpen = false, linksOpen = false, eventsOpen = false, statsOpen = true, timelineOpen = false, medalScan = null, medalScanKnown = 0, status = null) }
        val recognized = withProgress(uiText(R.string.looking_for_custom_medals)) { scanMedals(read()) }
        val savedTiers = _state.value.let { it.customMedals.orEmpty() + it.pendingMedals.orEmpty() }
            .groupBy({ it.medal }, { it.tierIndex })
            .mapValues { (_, tiers) -> tiers.max() }
        val new = recognized.filter { found -> found.medal.tierIndex > (savedTiers[found.medal.medal] ?: -1) }
        _state.update {
            when {
                recognized.isEmpty() -> it.copy(status = Status(NO_MEDALS_MESSAGE, true))
                new.isEmpty() -> it.copy(status = Status(allKnownMessage(recognized.size), false))
                else -> it.copy(medalScan = new, medalScanKnown = recognized.size - new.size)
            }
        }
    }

    /**
     * Saves the custom medals the user kept from the review, or after sign-in when the user is not
     * signed in yet. A medal already saved gets the new tier.
     */
    fun saveMedalScan(context: Context, medals: List<CustomMedalTier>) {
        _state.update { it.copy(medalScan = null, medalScanKnown = 0, pendingMedals = mergeCustomMedals(it.pendingMedals.orEmpty(), medals)) }
        if (_state.value.session == null) {
            _state.update { it.copy(status = Status(uiText(R.string.sign_in_to_save_custom_medals), false)) }
        } else {
            // The saved medals may have changed meanwhile, e.g. from another device
            launchBusy {
                loadRole(context, showFailure = true)
                savePendingMedals(context)
            }
        }
    }

    fun dismissMedalScan() = _state.update { it.copy(medalScan = null, medalScanKnown = 0) }

    /** Loads the account, then saves stats and custom medals shared before sign-in, or just loads the stats. */
    private suspend fun savePendingOrLoad(context: Context) {
        if (_state.value.pending == null) {
            loadRoleAndStats(context)
        } else {
            // Saving the stats checks them against the agent name of the account
            loadRole(context)
            savePendingStatsOrLoad(context)
        }
        savePendingMedals(context)
    }

    /** The account and the stats at the same time: each is a request of its own. */
    private suspend fun loadRoleAndStats(context: Context) = coroutineScope {
        launch { loadRole(context) }
        launch { loadStats(context) }
    }

    /**
     * Saves stats shared before sign-in (which reloads the stats), or just loads the stats. Without
     * an agent name the server makes the stats' nickname the agent name.
     */
    private suspend fun savePendingStatsOrLoad(context: Context) {
        val stats = _state.value.pending
        if (stats == null) {
            loadStats(context)
            return
        }

        val state = _state.value
        val nickname = stats["nickname"]?.jsonPrimitive?.contentOrNull.orEmpty()
        if (state.agentName != null && !nickname.equals(state.agentName, ignoreCase = true)) {
            _state.update {
                it.copy(pending = null, status = Status(uiText(R.string.stats_of_other_agent, nickname, state.agentName), true))
            }
            loadStats(context)
            return
        }

        // Taken before saving, so a sign-in and a share at the same time save it only once
        _state.update { it.copy(pending = null) }
        val result = withProgress(uiText(R.string.saving_agent_stats)) {
            withToken(context, { it is SaveResult.Unauthorized }) { api.save(it, stats) }
        }

        when (result) {
            is SaveResult.Success -> {
                _state.update { it.copy(agentName = it.agentName ?: nickname.trim(), status = Status(uiText(R.string.agent_stats_saved), false)) }
                loadStats(context)
                _state.update { state ->
                    val entries = state.entries.orEmpty()
                    state.copy(importResult = entries.find { it.id == result.id }?.let { importResultFor(it, entries) })
                }
            }
            is SaveResult.Invalid -> _state.update { it.copy(status = Status(UiText.Lines(result.messages), true)) }
            is SaveResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is SaveResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    /**
     * Saves custom medals reviewed before sign-in (or after a failed save), merged with the saved
     * ones that [loadRole] loaded with the account: call it after [loadRole].
     */
    private suspend fun savePendingMedals(context: Context) {
        val state = _state.value
        val medals = state.pendingMedals ?: return
        // Not loaded (e.g. offline): kept for the next try
        val saved = state.customMedals ?: return

        // Taken before saving, so a sign-in and a save at the same time save them only once
        _state.update { it.copy(pendingMedals = null) }
        val result = withProgress(UiText.Plural(R.plurals.saving_custom_medals, medals.size)) {
            withToken(context, { it is CustomMedalsResult.Unauthorized }) { api.saveCustomMedals(it, mergeCustomMedals(saved, medals)) }
        }

        if (result !is CustomMedalsResult.Success) {
            _state.update { it.copy(pendingMedals = mergeCustomMedals(medals, it.pendingMedals.orEmpty())) }
        }
        when (result) {
            is CustomMedalsResult.Success -> _state.update {
                it.copy(customMedals = result.medals, status = Status(UiText.Plural(R.plurals.custom_medals_saved, medals.size), false))
            }
            is CustomMedalsResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is CustomMedalsResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    /**
     * Loads the account: role, agent name and custom medals. A failure is only shown with
     * [showFailure], else loading the stats shows what went wrong.
     */
    private suspend fun loadRole(context: Context, showFailure: Boolean = false) {
        when (val result = withToken(context, { it is MeResult.Unauthorized }) { api.me(it) }) {
            is MeResult.Success -> _state.update {
                it.copy(
                    role = result.role,
                    agentName = result.agentName,
                    customMedals = result.customMedals,
                    visibleParts = result.visibleParts,
                    visibilityChosen = result.visibilityChosen,
                    avatar = result.avatar,
                    bannergress = result.bannergress,
                )
            }
            is MeResult.Unauthorized -> signedOutBy(result.message)
            is MeResult.Failure -> if (showFailure) _state.update { it.copy(status = Status(result.message, true)) }
            null -> Unit
        }
    }

    /** The stats, the timeline events and the done banners, loaded at the same time. */
    private suspend fun loadStats(context: Context) = coroutineScope {
        val stats = async { withToken(context, { it is AgentStatsResult.Unauthorized }) { api.list(it) } }
        val events = async { withToken(context, { it is TimelineEventsResult.Unauthorized }) { api.timelineEvents(it) } }
        val banners = async { withToken(context, { it is DoneBannersResult.Unauthorized }) { api.doneBanners(it) } }
        showStats(stats.await(), events.await(), banners.await())
    }

    private fun showStats(stats: AgentStatsResult?, events: TimelineEventsResult?, banners: DoneBannersResult?) {
        when (val result = stats) {
            is AgentStatsResult.Success -> _state.update { it.copy(entries = result.entries) }
            is AgentStatsResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is AgentStatsResult.Unauthorized -> {
                signedOutBy(result.message)
                return
            }
            null -> return
        }

        // With the stats: they make the timeline together
        when (val result = events) {
            is TimelineEventsResult.Success -> _state.update { it.copy(timelineEvents = result.events) }
            is TimelineEventsResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is TimelineEventsResult.Unauthorized -> {
                signedOutBy(result.message)
                return
            }
            null -> return
        }

        showDoneBanners(banners)
    }

    private suspend fun loadDoneBanners(context: Context) =
        showDoneBanners(withToken(context, { it is DoneBannersResult.Unauthorized }) { api.doneBanners(it) })

    /** The Bannergress done list: the banners with a day are on the timeline. */
    private fun showDoneBanners(result: DoneBannersResult?) {
        when (result) {
            is DoneBannersResult.Success -> _state.update { it.copy(doneBanners = result.banners) }
            is DoneBannersResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is DoneBannersResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    /** Sets the day a banner was done ([doneAt] `yyyy-MM-dd`), or removes it (null). */
    fun saveDoneAt(context: Context, banner: DoneBanner, doneAt: String?) {
        val id = banner.id ?: return
        launchBusy {
            when (val result = withToken(context, { it is DoneBannerResult.Unauthorized }) { api.saveDoneAt(it, id, doneAt) }) {
                is DoneBannerResult.Success -> _state.update { state ->
                    state.copy(
                        doneBanners = state.doneBanners?.map { if (it.id == id) result.banner else it },
                        status = Status(uiText(if (doneAt == null) R.string.bannergress_date_removed else R.string.bannergress_date_saved), false),
                    )
                }
                is DoneBannerResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
                is DoneBannerResult.Unauthorized -> signedOutBy(result.message)
                null -> Unit
            }
        }
    }

    /** Adds a custom event to the user's timeline ([TimelineEvent.id] null), or changes it. */
    fun saveTimelineEvent(context: Context, event: TimelineEvent) = launchBusy {
        when (val result = withToken(context, { it is TimelineEventResult.Unauthorized }) { api.saveTimelineEvent(it, event) }) {
            is TimelineEventResult.Success -> _state.update { state ->
                val saved = result.event ?: event
                val others = state.timelineEvents.orEmpty().filter { it.id != saved.id }
                state.copy(timelineEvents = newestFirst(others + saved), status = Status(uiText(R.string.event_saved), false))
            }
            is TimelineEventResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is TimelineEventResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    fun deleteTimelineEvent(context: Context, event: TimelineEvent) {
        val id = event.id ?: return
        launchBusy {
            when (val result = withToken(context, { it is TimelineEventResult.Unauthorized }) { api.deleteTimelineEvent(it, id) }) {
                is TimelineEventResult.Success -> _state.update { state ->
                    state.copy(timelineEvents = state.timelineEvents?.filter { it.id != id }, status = Status(uiText(R.string.event_deleted), false))
                }
                is TimelineEventResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
                is TimelineEventResult.Unauthorized -> signedOutBy(result.message)
                null -> Unit
            }
        }
    }

    /** Shows the Bannergress sign-in; its redirect goes to [finishBannergressLogin]. */
    fun startBannergressLogin() {
        val account = bannergress ?: return
        _state.update { it.copy(bannergressLogin = account.start()) }
    }

    fun cancelBannergressLogin() = _state.update { it.copy(bannergressLogin = null) }

    /** The sign-in's authorization [code]: gets the tokens, then syncs. */
    fun finishBannergressLogin(context: Context, code: String) {
        val account = bannergress ?: return
        val login = _state.value.bannergressLogin ?: return
        _state.update { it.copy(bannergressLogin = null) }
        launchBusy {
            val signedIn = try {
                account.finish(login, code)
            } catch (e: IOException) {
                _state.update { it.copy(status = Status(uiText(R.string.error_network, e.message.orEmpty()), true)) }
                return@launchBusy
            }
            if (signedIn) {
                syncBannergressNow(context, account)
            } else {
                _state.update { it.copy(status = Status(uiText(R.string.bannergress_sign_in_failed), true)) }
            }
        }
    }

    fun syncBannergress(context: Context) {
        val account = bannergress ?: return
        launchBusy { syncBannergressNow(context, account) }
    }

    fun signOutBannergress() {
        bannergress?.signOut()
    }

    /** Reads the counts and lists from Bannergress and saves them on the server. */
    private suspend fun syncBannergressNow(context: Context, account: BannergressConnector) {
        val agentName = _state.value.agentName
        if (agentName == null) {
            _state.update { it.copy(status = Status(uiText(R.string.bannergress_needs_agent_name), true)) }
            return
        }

        val result = withProgress(uiText(R.string.bannergress_syncing)) {
            when (val read = account.sync(agentName)) {
                is BannergressResult.Success -> withToken(context, { it is BannergressSaveResult.Unauthorized }) { api.saveBannergress(it, read.sync) }
                BannergressResult.SignedOut -> BannergressSaveResult.Failure(uiText(R.string.bannergress_session_ended))
                is BannergressResult.Failure -> BannergressSaveResult.Failure(read.message)
            }
        }
        when (result) {
            is BannergressSaveResult.Success -> {
                _state.update { it.copy(bannergress = result.sync, status = Status(uiText(R.string.bannergress_synced), false)) }
                // New banners on the done list, with the days kept
                loadDoneBanners(context)
            }
            is BannergressSaveResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is BannergressSaveResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    /** As the server lists them: by date, then the newest added first. */
    private fun newestFirst(events: List<TimelineEvent>) =
        events.sortedWith(compareByDescending<TimelineEvent> { it.date }.thenByDescending { it.id ?: Int.MAX_VALUE })

    /**
     * Calls [call] with the session's ID token. ID tokens expire after about an hour: when the
     * result [isUnauthorized], gets a fresh token silently and retries once. Null without a session.
     */
    private suspend fun <T> withToken(
        context: Context,
        isUnauthorized: (T) -> Boolean,
        call: suspend (String) -> T,
    ): T? {
        val session = _state.value.session ?: return null
        val result = call(session.idToken)
        if (!isUnauthorized(result)) return result

        // Calls at the same time get one fresh token
        val refreshed = tokenRefresh.withLock {
            val current = _state.value.session ?: return result
            if (current.idToken != session.idToken) return@withLock current
            auth.signInSilently(context)?.also { fresh -> _state.update { it.copy(session = fresh) } }
        } ?: return result

        return call(refreshed.idToken)
    }

    /**
     * No "signed in" message: the account is shown. A share hint must stay visible, a message
     * about being signed out is outdated.
     */
    private fun signedIn(session: GoogleSession) = _state.update {
        it.copy(session = session, status = it.status?.takeUnless(Status::untilSignIn), visibilityDismissed = false)
    }

    private val CustomMedalTier.tierIndex: Int get() = medal.tierOrNone.indexOf(tier)

    private fun signedOutBy(message: UiText) = _state.update {
        it.copy(
            session = null,
            role = null,
            agentName = null,
            visibleParts = emptySet(),
            avatar = null,
            visibilityStatus = null,
            settingsOpen = false,
            entries = null,
            timelineEvents = null,
            customMedals = null,
            bannergress = null,
            doneBanners = null,
            bannergressOpen = false,
            visibilityChosen = true,
            statsOpen = false,
            timelineOpen = false,
            agentsOpen = false,
            adminAgents = false,
            agents = null,
            users = null,
            agent = null,
            agentsStatus = null,
            status = Status(uiText(R.string.not_authorized, message), true, untilSignIn = true),
        )
    }

    /** Runs [block] with the loading animation and [progress] shown, then shows the earlier progress again. */
    private suspend fun <T> withProgress(progress: UiText, block: suspend () -> T): T {
        val earlier = _state.value.progress
        _state.update { it.copy(progress = progress) }
        try {
            return block()
        } finally {
            _state.update { it.copy(progress = earlier) }
        }
    }

    private fun launchBusy(block: suspend () -> Unit) {
        _state.update { it.copy(busy = true) }
        viewModelScope.launch {
            try {
                block()
            } finally {
                _state.update { it.copy(busy = false) }
            }
        }
    }

    companion object {
        fun allKnownMessage(count: Int): UiText = UiText.Plural(R.plurals.custom_medals_all_known, count)

        val NOT_STATS_MESSAGE = uiText(R.string.not_stats)

        val NO_MEDALS_MESSAGE = uiText(R.string.no_custom_medals_found)
    }
}
