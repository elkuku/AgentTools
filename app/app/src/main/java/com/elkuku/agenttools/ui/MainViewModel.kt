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
import com.elkuku.agenttools.data.SaveResult
import com.elkuku.agenttools.data.customMedalCount
import com.elkuku.agenttools.data.StatsApi
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

data class Status(val text: String, val isError: Boolean)

data class UiState(
    val session: GoogleSession? = null,
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
) {
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

    private var restoreAttempted = false

    /** Signs in without UI if the user already authorized the app, then loads the stats. Runs once. */
    fun restoreSession(context: Context) {
        if (restoreAttempted) return
        restoreAttempted = true

        launchBusy {
            val session = auth.signInSilently(context) ?: return@launchBusy
            _state.update { it.copy(session = session) }
            savePendingOrLoad(context)
        }
    }

    fun signIn(context: Context) = launchBusy {
        try {
            val session = auth.signIn(context)
            // No "signed in" message: the account is shown, and a share hint must stay visible
            _state.update { it.copy(session = session) }
            savePendingOrLoad(context)
        } catch (e: AuthException) {
            _state.update { it.copy(status = if (e.cancelled) null else Status(e.message.orEmpty(), true)) }
        }
    }

    fun signOut(context: Context) = launchBusy {
        auth.signOut(context)
        _state.update {
            it.copy(session = null, entries = null, importResult = null, customMedals = null, status = Status("Signed out.", false))
        }
    }

    fun refresh(context: Context) {
        if (_state.value.session == null) return
        // An earlier message (e.g. a failed load) is outdated now
        _state.update { it.copy(status = null) }
        launchBusy {
            loadStats(context)
            savePendingMedalsOrLoad(context)
        }
    }

    /**
     * Text shared from another app: Ingress all-time stats are saved right away, or after sign-in
     * when the user is not signed in yet.
     */
    fun onShared(context: Context, text: String) {
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

    fun dismissImportResult() = _state.update { it.copy(importResult = null) }

    /**
     * Screenshots of the Ingress medal grid, from [read]: the recognized custom medals are shown for
     * review, except those already saved (or waiting to be saved) with the same or a higher tier.
     * Ingress shows only the highest tier, so a lower one is a wrong match.
     */
    fun onSharedScreenshots(read: suspend () -> List<ArgbImage>) = launchBusy {
        _state.update { it.copy(medalScan = null, medalScanKnown = 0, status = null) }
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
            launchBusy { savePendingMedalsOrLoad(context) }
        }
    }

    fun dismissMedalScan() = _state.update { it.copy(medalScan = null, medalScanKnown = 0) }

    /** Saves stats and custom medals shared before sign-in, or just loads them. */
    private suspend fun savePendingOrLoad(context: Context) {
        savePendingStatsOrLoad(context)
        savePendingMedalsOrLoad(context)
    }

    /** Saves stats shared before sign-in (which reloads the stats), or just loads the stats. */
    private suspend fun savePendingStatsOrLoad(context: Context) {
        val stats = _state.value.pending
        if (stats == null) {
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
                _state.update { it.copy(status = Status("Agent stats saved.", false)) }
                loadStats(context)
                _state.update { state ->
                    val entries = state.entries.orEmpty()
                    state.copy(importResult = entries.find { it.id == result.id }?.let { importResultFor(it, entries) })
                }
            }
            is SaveResult.Invalid -> _state.update { it.copy(status = Status(result.messages.joinToString("\n"), true)) }
            is SaveResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is SaveResult.Unauthorized -> signedOutBy(result.message)
            is SaveResult.Forbidden -> waitingForAgentRole()
            null -> Unit
        }
    }

    /**
     * Loads the custom medals and adds the pending ones: the server keeps all custom medals, so
     * they are merged with the saved ones first. Failed medals stay pending (Refresh retries).
     */
    private suspend fun savePendingMedalsOrLoad(context: Context) {
        val medals = _state.value.pendingMedals
        // Taken before saving, so a sign-in and a save at the same time save them only once
        _state.update { it.copy(pendingMedals = null) }

        var result = withToken(context, { it is CustomMedalsResult.Unauthorized }) { api.customMedals(it) }
        if (medals != null && result is CustomMedalsResult.Success) {
            val merged = mergeCustomMedals(result.medals, medals)
            result = withProgress("Saving ${customMedalCount(medals.size)}…") {
                withToken(context, { it is CustomMedalsResult.Unauthorized }) { api.saveCustomMedals(it, merged) }
            }
            if (result is CustomMedalsResult.Success) {
                _state.update { it.copy(status = Status("${customMedalCount(medals.size)} saved.", false)) }
            }
        }

        if (medals != null && result !is CustomMedalsResult.Success) {
            _state.update { it.copy(pendingMedals = mergeCustomMedals(medals, it.pendingMedals.orEmpty())) }
        }
        when (result) {
            is CustomMedalsResult.Success -> _state.update { it.copy(customMedals = result.medals) }
            is CustomMedalsResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is CustomMedalsResult.Unauthorized -> signedOutBy(result.message)
            is CustomMedalsResult.Forbidden -> waitingForAgentRole()
            null -> Unit
        }
    }

    private suspend fun loadStats(context: Context) {
        when (val result = withToken(context, { it is AgentStatsResult.Unauthorized }) { api.list(it) }) {
            is AgentStatsResult.Success -> _state.update { it.copy(entries = result.entries) }
            is AgentStatsResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
            is AgentStatsResult.Unauthorized -> signedOutBy(result.message)
            is AgentStatsResult.Forbidden -> waitingForAgentRole()
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

    private val CustomMedalTier.tierIndex: Int get() = medal.tierOrNone.indexOf(tier)

    /**
     * Signed in, but not an agent yet (e.g. just registered): stats and custom medals both answer
     * 403, so one message for the account instead of the last request's.
     */
    private fun waitingForAgentRole() = _state.update {
        it.copy(entries = null, customMedals = null, status = Status(AGENT_ROLE_MESSAGE, false))
    }

    private fun signedOutBy(message: String) = _state.update {
        it.copy(
            session = null,
            entries = null,
            customMedals = null,
            status = Status("Not authorized: $message Please sign in again.", true),
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
        const val AGENT_ROLE_MESSAGE =
            "Your account is waiting for an administrator to give it the Agent role. Then you can see and save your agent stats."

        fun allKnownMessage(count: Int) =
            if (count == 1) "The custom medal found is already saved." else "All $count custom medals found are already saved."

        const val NOT_STATS_MESSAGE =
            "That is not Ingress all-time stats. In Ingress, open your agent profile, select ALL TIME and share the stats."

        const val NO_MEDALS_MESSAGE =
            "No custom medals found. Share screenshots of the medals in your Ingress agent profile."
    }
}
