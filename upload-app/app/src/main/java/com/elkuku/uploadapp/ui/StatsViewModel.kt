package com.elkuku.uploadapp.ui

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.data.AgentStatEntry
import com.elkuku.uploadapp.data.AgentStatsResult
import com.elkuku.uploadapp.data.UploadApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class StatsUiState(
    val loading: Boolean = false,
    /** Newest first; null until loaded. */
    val entries: List<AgentStatEntry>? = null,
    val error: String? = null,
) {
    val latest: AgentStatEntry? get() = entries?.firstOrNull()

    val first: AgentStatEntry? get() = entries?.lastOrNull()
}

class StatsViewModel(
    private val auth: GoogleAuth,
    private val api: UploadApi,
) : ViewModel() {
    private val _state = MutableStateFlow(StatsUiState())
    val state: StateFlow<StatsUiState> = _state.asStateFlow()

    /** Loads the user's agent stats; signs in silently to get a fresh ID token. */
    fun load(context: Context) {
        if (_state.value.loading) return
        _state.update { it.copy(loading = true, error = null) }

        viewModelScope.launch {
            val session = auth.signInSilently(context)
            val result = if (session == null) {
                AgentStatsResult.Unauthorized("Please sign in first.")
            } else {
                api.agentStats(session.idToken)
            }

            _state.update {
                when (result) {
                    is AgentStatsResult.Success -> StatsUiState(entries = result.entries)
                    is AgentStatsResult.Unauthorized -> it.copy(loading = false, error = result.message)
                    is AgentStatsResult.Failure -> it.copy(loading = false, error = result.message)
                }
            }
        }
    }
}
