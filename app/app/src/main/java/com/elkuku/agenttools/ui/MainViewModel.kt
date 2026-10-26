package com.elkuku.agenttools.ui

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.elkuku.agenttools.auth.AuthException
import com.elkuku.agenttools.auth.GoogleAuth
import com.elkuku.agenttools.auth.GoogleSession
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.AgentStatsResult
import com.elkuku.agenttools.data.CustomMedalsResult
import com.elkuku.agenttools.data.CustomMedalTier
import com.elkuku.agenttools.data.ImportResult
import com.elkuku.agenttools.data.MeResult
import com.elkuku.agenttools.data.ProfilePart
import com.elkuku.agenttools.data.SaveResult
import com.elkuku.agenttools.data.customMedalCount
import com.elkuku.agenttools.data.StatsApi
import com.elkuku.agenttools.data.UserRole
import com.elkuku.agenttools.data.VisibilityResult
import com.elkuku.agenttools.data.importResultFor
import com.elkuku.agenttools.data.mergeCustomMedals
import com.elkuku.agenttools.data.parseIngressStats
import com.elkuku.agenttools.scan.ArgbImage
import com.elkuku.agenttools.scan.RecognizedMedal
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonPrimitive

/** A message for the user; [untilSignIn] ones are about being signed out and go away on sign-in. */
data class Status(val text: String, val isError: Boolean, val untilSignIn: Boolean = false)

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
    /** The settings screen is shown instead of the stats. */
    val settingsOpen: Boolean = false,
    val busy: Boolean = false,
    /** What takes a while (saving, scanning), shown with a loading animation; null for nothing. */
    val progress: String? = null,
    /** The user's stats, newest first; null until loaded. */
    val entries: List<AgentStatEntry>? = null,
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
) : ViewModel() {
    private val _state = MutableStateFlow(UiState())
    val state: StateFlow<UiState> = _state.asStateFlow()

    fun signIn(context: Context) = launchBusy {
        try {
            val session = auth.signIn(context)
            signedIn(session)
            savePendingOrLoad(context)
        } catch (e: AuthException) {
            _state.update { it.copy(status = if (e.cancelled) null else Status(e.message.orEmpty(), true)) }
        }
    }

    fun signOut(context: Context) = launchBusy {
        auth.signOut(context)
        _state.update {
            it.copy(
                session = null,
                role = null,
                agentName = null,
                visibleParts = emptySet(),
                avatar = null,
                visibilityStatus = null,
                entries = null,
                importResult = null,
                customMedals = null,
                visibilityChosen = true,
                status = Status("Signed out.", false, untilSignIn = true),
            )
        }
    }

    fun refresh(context: Context) {
        if (_state.value.session == null) return
        // An earlier message (e.g. a failed load) is outdated now
        _state.update { it.copy(status = null) }
        launchBusy {
            // E.g. the agent name or the custom medals changed on another device
            loadRole(context)
            loadStats(context)
            savePendingMedals(context)
        }
    }

    /**
     * Text shared from another app: Ingress all-time stats are saved right away, or after sign-in
     * when the user is not signed in yet.
     */
    fun onShared(context: Context, text: String) {
        // The result of a share shows on the stats screen
        _state.update { it.copy(settingsOpen = false) }
        val stats = parseIngressStats(text)
        if (stats == null) {
            _state.update { it.copy(status = Status(NOT_STATS_MESSAGE, true)) }
            return
        }

        _state.update { it.copy(pending = stats, importResult = null, status = null) }
        if (_state.value.session == null) {
            _state.update { it.copy(status = Status("Sign in to save the shared stats.", false)) }
        } else {
            launchBusy { savePendingOrLoad(context) }
        }
    }

    fun openSettings() = _state.update { it.copy(settingsOpen = true) }

    fun closeSettings() = _state.update { it.copy(settingsOpen = false, visibilityStatus = null) }

    /** Saves the parts of the profile other users see; without [ProfilePart.AGENT] the server keeps none. */
    fun saveVisibleParts(context: Context, parts: Set<ProfilePart>) = launchBusy {
        val result = withToken(context, { it is VisibilityResult.Unauthorized }) { api.saveVisibleParts(it, parts) }
        when (result) {
            is VisibilityResult.Success -> _state.update {
                it.copy(visibleParts = result.visibleParts, visibilityChosen = true, visibilityStatus = Status("Saved.", false))
            }
            is VisibilityResult.Failure -> _state.update { it.copy(visibilityStatus = Status(result.message, true)) }
            is VisibilityResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

    /** The data shown came from the old server: forget it and load it from the new one. */
    fun serverChanged(context: Context) {
        if (_state.value.session == null) return
        _state.update { it.copy(role = null, agentName = null, entries = null, importResult = null, customMedals = null, visibleParts = emptySet(), avatar = null, visibilityChosen = true) }
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
        _state.update { it.copy(settingsOpen = false, medalScan = null, medalScanKnown = 0, status = null) }
        val recognized = withProgress("Looking for custom medals…") { scanMedals(read()) }
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
            _state.update { it.copy(status = Status("Sign in to save the custom medals.", false)) }
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
        loadRole(context)
        savePendingStatsOrLoad(context)
        savePendingMedals(context)
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
                it.copy(pending = null, status = Status("These stats are for agent $nickname, your agent name is ${state.agentName}.", true))
            }
            loadStats(context)
            return
        }

        // Taken before saving, so a sign-in and a share at the same time save it only once
        _state.update { it.copy(pending = null) }
        val result = withProgress("Saving agent stats…") {
            withToken(context, { it is SaveResult.Unauthorized }) { api.save(it, stats) }
        }

        when (result) {
            is SaveResult.Success -> {
                _state.update { it.copy(agentName = it.agentName ?: nickname.trim(), status = Status("Agent stats saved.", false)) }
                loadStats(context)
                _state.update { state ->
                    val entries = state.entries.orEmpty()
                    state.copy(importResult = entries.find { it.id == result.id }?.let { importResultFor(it, entries) })
                }
            }
            is SaveResult.Invalid -> _state.update { it.copy(status = Status(result.messages.joinToString("\n"), true)) }
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
        val result = withProgress("Saving ${customMedalCount(medals.size)}…") {
            withToken(context, { it is CustomMedalsResult.Unauthorized }) { api.saveCustomMedals(it, mergeCustomMedals(saved, medals)) }
        }

        if (result !is CustomMedalsResult.Success) {
            _state.update { it.copy(pendingMedals = mergeCustomMedals(medals, it.pendingMedals.orEmpty())) }
        }
        when (result) {
            is CustomMedalsResult.Success -> _state.update {
                it.copy(customMedals = result.medals, status = Status("${customMedalCount(medals.size)} saved.", false))
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
                )
            }
            is MeResult.Unauthorized -> signedOutBy(result.message)
            is MeResult.Failure -> if (showFailure) _state.update { it.copy(status = Status(result.message, true)) }
            null -> Unit
        }
    }

    private suspend fun loadStats(context: Context) {
        when (val result = withToken(context, { it is AgentStatsResult.Unauthorized }) { api.list(it) }) {
            is AgentStatsResult.Success -> _state.update { it.copy(entries = result.entries) }
            is AgentStatsResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is AgentStatsResult.Unauthorized -> signedOutBy(result.message)
            null -> Unit
        }
    }

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

        val refreshed = auth.signInSilently(context) ?: return result
        _state.update { it.copy(session = refreshed) }

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

    private fun signedOutBy(message: String) = _state.update {
        it.copy(
            session = null,
            role = null,
            agentName = null,
            visibleParts = emptySet(),
            avatar = null,
            visibilityStatus = null,
            settingsOpen = false,
            entries = null,
            customMedals = null,
            visibilityChosen = true,
            status = Status("Not authorized: $message Please sign in again.", true, untilSignIn = true),
        )
    }

    /** Runs [block] with the loading animation and [progress] shown. */
    private suspend fun <T> withProgress(progress: String, block: suspend () -> T): T {
        _state.update { it.copy(progress = progress) }
        try {
            return block()
        } finally {
            _state.update { it.copy(progress = null) }
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
        fun allKnownMessage(count: Int) =
            if (count == 1) "The custom medal found is already saved." else "All $count custom medals found are already saved."

        const val NOT_STATS_MESSAGE =
            "That is not Ingress all-time stats. In Ingress, open your agent profile, select ALL TIME and share the stats."

        const val NO_MEDALS_MESSAGE =
            "No custom medals found. Share screenshots of the medals in your Ingress agent profile."
    }
}
