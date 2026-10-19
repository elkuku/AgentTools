package com.elkuku.uploadapp.ui

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.elkuku.uploadapp.auth.AuthException
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.auth.GoogleSession
import com.elkuku.uploadapp.data.JsonInput
import com.elkuku.uploadapp.data.SharedData
import com.elkuku.uploadapp.data.UploadApi
import com.elkuku.uploadapp.data.UploadRequest
import com.elkuku.uploadapp.data.UploadResult
import com.elkuku.uploadapp.data.parseIngressStats
import com.elkuku.uploadapp.data.parseJsonObject
import com.elkuku.uploadapp.data.title
import com.elkuku.uploadapp.data.toJsonText
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class Status(val text: String, val isError: Boolean)

/** Where the data goes: a generic upload, or Ingress agent stats. */
enum class UploadTarget { UPLOAD, AGENT_STATS }

data class UiState(
    val session: GoogleSession? = null,
    val target: UploadTarget = UploadTarget.UPLOAD,
    val title: String = "",
    val json: String = DEFAULT_JSON,
    val jsonError: String? = null,
    val busy: Boolean = false,
    val status: Status? = null,
) {
    val canSend: Boolean
        get() = session != null && !busy && jsonError == null &&
            (target == UploadTarget.AGENT_STATS || title.isNotBlank())

    companion object {
        const val DEFAULT_JSON = "{\n  \"hello\": \"world\"\n}"
    }
}

class MainViewModel(
    private val auth: GoogleAuth,
    private val api: UploadApi,
) : ViewModel() {
    private val _state = MutableStateFlow(UiState())
    val state: StateFlow<UiState> = _state.asStateFlow()

    private var restoreAttempted = false

    /** Signs in without UI if the user already authorized the app. Runs once. */
    fun restoreSession(context: Context) {
        if (restoreAttempted) return
        restoreAttempted = true

        launchBusy {
            auth.signInSilently(context)?.let { session -> _state.update { it.copy(session = session) } }
        }
    }

    fun signIn(context: Context) = launchBusy {
        try {
            val session = auth.signIn(context)
            _state.update { it.copy(session = session, status = Status("Signed in as ${session.email}", false)) }
        } catch (e: AuthException) {
            _state.update { it.copy(status = if (e.cancelled) null else Status(e.message.orEmpty(), true)) }
        }
    }

    fun signOut(context: Context) = launchBusy {
        auth.signOut(context)
        _state.update { it.copy(session = null, status = Status("Signed out.", false)) }
    }

    fun onTitleChange(title: String) = _state.update { it.copy(title = title) }

    fun onJsonChange(json: String) = _state.update {
        it.copy(json = json, jsonError = (parseJsonObject(json) as? JsonInput.Invalid)?.message)
    }

    /**
     * Fills the form with data shared from another app; the user reviews it and uploads.
     * Ingress all-time stats go to the agent stats instead of the uploads.
     */
    fun onShared(shared: SharedData) = _state.update {
        val isAgentStats = parseIngressStats(shared.text) != null
        it.copy(
            target = if (isAgentStats) UploadTarget.AGENT_STATS else UploadTarget.UPLOAD,
            title = shared.title(),
            json = shared.toJsonText(),
            jsonError = null,
            status = Status(
                if (isAgentStats) {
                    "Ingress agent stats loaded. Review them and tap Save agent stats."
                } else {
                    "Shared data loaded. Review it and tap Upload."
                },
                false,
            ),
        )
    }

    /** Sends the data as a generic upload instead of agent stats. */
    fun onUploadAsPlainData() = _state.update { it.copy(target = UploadTarget.UPLOAD) }

    fun upload(context: Context) {
        val current = _state.value
        val session = current.session ?: return
        val data = (parseJsonObject(current.json) as? JsonInput.Valid)?.value ?: return
        val target = current.target
        val send: suspend (String) -> UploadResult = when (target) {
            UploadTarget.UPLOAD -> UploadRequest(current.title.trim(), data).let { request ->
                { idToken -> api.upload(idToken, request) }
            }
            UploadTarget.AGENT_STATS -> { idToken -> api.uploadAgentStats(idToken, data) }
        }

        launchBusy {
            var result = send(session.idToken)

            // ID tokens expire after about an hour: get a fresh one and retry once
            if (result is UploadResult.Unauthorized) {
                val refreshed = auth.signInSilently(context)
                if (refreshed != null) {
                    _state.update { it.copy(session = refreshed) }
                    result = send(refreshed.idToken)
                }
            }

            _state.update { it.withResult(result, target) }
        }
    }

    private fun UiState.withResult(result: UploadResult, target: UploadTarget): UiState = when (result) {
        is UploadResult.Success -> copy(
            status = Status(
                when (target) {
                    UploadTarget.UPLOAD -> "Uploaded (id ${result.id})."
                    UploadTarget.AGENT_STATS -> "Agent stats saved (id ${result.id})."
                },
                false,
            ),
        )
        is UploadResult.Invalid -> copy(status = Status(result.messages.joinToString("\n"), true))
        is UploadResult.Failure -> copy(status = Status(result.message, true))
        is UploadResult.Unauthorized -> copy(
            session = null,
            status = Status("Not authorized: ${result.message} Please sign in again.", true),
        )
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
}
