package com.elkuku.uploadapp.ui

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.elkuku.uploadapp.auth.AuthException
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.auth.GoogleSession
import com.elkuku.uploadapp.data.JsonInput
import com.elkuku.uploadapp.data.UploadApi
import com.elkuku.uploadapp.data.UploadRequest
import com.elkuku.uploadapp.data.UploadResult
import com.elkuku.uploadapp.data.parseJsonObject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class Status(val text: String, val isError: Boolean)

data class UiState(
    val session: GoogleSession? = null,
    val title: String = "",
    val json: String = DEFAULT_JSON,
    val jsonError: String? = null,
    val busy: Boolean = false,
    val status: Status? = null,
) {
    val canSend: Boolean
        get() = session != null && !busy && title.isNotBlank() && jsonError == null

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

    fun upload(context: Context) {
        val current = _state.value
        val session = current.session ?: return
        val data = (parseJsonObject(current.json) as? JsonInput.Valid)?.value ?: return
        val request = UploadRequest(current.title.trim(), data)

        launchBusy {
            var result = api.upload(session.idToken, request)

            // ID tokens expire after about an hour: get a fresh one and retry once
            if (result is UploadResult.Unauthorized) {
                val refreshed = auth.signInSilently(context)
                if (refreshed != null) {
                    _state.update { it.copy(session = refreshed) }
                    result = api.upload(refreshed.idToken, request)
                }
            }

            _state.update { it.withResult(result) }
        }
    }

    private fun UiState.withResult(result: UploadResult): UiState = when (result) {
        is UploadResult.Success -> copy(status = Status("Uploaded (id ${result.id}).", false))
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
