package com.elkuku.uploadapp.ui

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.elkuku.uploadapp.auth.AuthException
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.auth.GoogleSession
import com.elkuku.uploadapp.data.AgentStatEntry
import com.elkuku.uploadapp.data.AgentStatsResult
import com.elkuku.uploadapp.data.SaveResult
import com.elkuku.uploadapp.data.StatsApi
import com.elkuku.uploadapp.data.parseIngressStats
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject

data class Status(val text: String, val isError: Boolean)

/** Stats shared from Ingress, waiting for the user to save them. */
data class PendingStats(val json: JsonObject) {
    val entry: AgentStatEntry = AgentStatEntry.fromJson(json)
}

data class UiState(
    val session: GoogleSession? = null,
    val busy: Boolean = false,
    /** The user's stats, newest first; null until loaded. */
    val entries: List<AgentStatEntry>? = null,
    val pending: PendingStats? = null,
    val status: Status? = null,
) {
    val latest: AgentStatEntry? get() = entries?.firstOrNull()

    val first: AgentStatEntry? get() = entries?.lastOrNull()

    val canSave: Boolean get() = session != null && pending != null && !busy
}

class MainViewModel(
    private val auth: GoogleAuth,
    private val api: StatsApi,
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
            loadStats(context)
        }
    }

    fun signIn(context: Context) = launchBusy {
        try {
            val session = auth.signIn(context)
            // No "signed in" message: the account is shown, and a share hint must stay visible
            _state.update { it.copy(session = session) }
            loadStats(context)
        } catch (e: AuthException) {
            _state.update { it.copy(status = if (e.cancelled) null else Status(e.message.orEmpty(), true)) }
        }
    }

    fun signOut(context: Context) = launchBusy {
        auth.signOut(context)
        _state.update { it.copy(session = null, entries = null, status = Status("Signed out.", false)) }
    }

    fun refresh(context: Context) {
        if (_state.value.session == null) return
        launchBusy { loadStats(context) }
    }

    /** Text shared from another app: Ingress all-time stats wait for the user to save them. */
    fun onShared(text: String) = _state.update {
        val stats = parseIngressStats(text)
        if (stats == null) {
            it.copy(status = Status(NOT_STATS_MESSAGE, true))
        } else {
            it.copy(pending = PendingStats(stats), status = null)
        }
    }

    fun discardPending() = _state.update { it.copy(pending = null, status = null) }

    fun savePending(context: Context) {
        val pending = _state.value.pending ?: return
        if (!_state.value.canSave) return

        launchBusy {
            val result = withToken(context, { it is SaveResult.Unauthorized }) { api.save(it, pending.json) }

            when (result) {
                is SaveResult.Success -> {
                    _state.update { it.copy(pending = null, status = Status("Agent stats saved.", false)) }
                    loadStats(context)
                }
                is SaveResult.Invalid -> _state.update { it.copy(status = Status(result.messages.joinToString("\n"), true)) }
                is SaveResult.Failure -> _state.update { it.copy(status = Status(result.message, true)) }
                is SaveResult.Unauthorized -> signedOutBy(result.message)
                null -> Unit
            }
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

    private fun signedOutBy(message: String) = _state.update {
        it.copy(session = null, entries = null, status = Status("Not authorized: $message Please sign in again.", true))
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
        const val NOT_STATS_MESSAGE =
            "That is not Ingress all-time stats. In Ingress, open your agent profile, select ALL TIME and share the stats."
    }
}
