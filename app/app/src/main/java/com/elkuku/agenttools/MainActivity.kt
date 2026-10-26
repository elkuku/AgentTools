package com.elkuku.agenttools

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.elkuku.agenttools.ui.AppBuild
import com.elkuku.agenttools.ui.MainScreen
import com.elkuku.agenttools.ui.MainViewModel
import com.elkuku.agenttools.ui.ProfileSetting
import com.elkuku.agenttools.ui.SettingsScreen
import com.elkuku.agenttools.ui.theme.AgentToolsTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : ComponentActivity() {
    private val viewModel: MainViewModel by viewModels {
        val app = application as AgentToolsApplication
        viewModelFactory {
            initializer {
                MainViewModel(app.googleAuth, app.statsApi) { screenshots ->
                    withContext(Dispatchers.Default) { app.medalScanner.scan(screenshots) }
                }
            }
        }
    }

    private val serverSettings get() = (application as AgentToolsApplication).serverSettings

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        // Only on first creation: after a rotation the shared stats were already handled
        if (savedInstanceState == null) handleShare(intent)

        setContent {
            AgentToolsTheme {
                val state by viewModel.state.collectAsStateWithLifecycle()
                val serverUrl by serverSettings.url.collectAsStateWithLifecycle()
                val activity = this@MainActivity

                if (state.settingsOpen) {
                    BackHandler(onBack = viewModel::closeSettings)
                    SettingsScreen(
                        profile = ProfileSetting(
                            agentName = state.agentName,
                            visibleParts = state.visibleParts,
                            chosen = state.visibilityChosen,
                            status = state.visibilityStatus,
                            busy = state.busy,
                        ),
                        onSaveVisibleParts = { viewModel.saveVisibleParts(activity, it) },
                        build = AppBuild(BuildConfig.VERSION_NAME, BuildConfig.VERSION_CODE, BuildConfig.DEBUG),
                        serverUrl = serverUrl,
                        defaultServerUrl = serverSettings.defaultUrl,
                        liveServerUrl = if (BuildConfig.DEBUG) BuildConfig.LIVE_SERVER_BASE_URL else "",
                        onSaveServerUrl = { url ->
                            val before = serverSettings.url.value
                            serverSettings.set(url).also { error ->
                                if (error == null && serverSettings.url.value != before) viewModel.serverChanged(activity)
                            }
                        },
                        onBack = viewModel::closeSettings,
                    )
                } else {
                    MainScreen(
                        state = state,
                        serverUrl = serverUrl,
                        onSignIn = { viewModel.signIn(activity) },
                        onSignOut = { viewModel.signOut(activity) },
                        onRefresh = { viewModel.refresh(activity) },
                        onOpenSettings = viewModel::openSettings,
                        onDismissImportResult = viewModel::dismissImportResult,
                        onChooseVisibility = viewModel::chooseVisibility,
                        onDismissVisibility = viewModel::dismissVisibility,
                        onSaveMedalScan = { viewModel.saveMedalScan(activity, it) },
                        onDismissMedalScan = viewModel::dismissMedalScan,
                    )
                }
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        handleShare(intent)
    }

    private fun handleShare(intent: Intent) {
        if (isSharedImages(intent)) {
            viewModel.onSharedScreenshots { withContext(Dispatchers.IO) { readSharedImages(intent, contentResolver) } }
            return
        }
        if (intent.action != Intent.ACTION_SEND) return

        lifecycleScope.launch {
            val text = withContext(Dispatchers.IO) { readSharedText(intent, contentResolver) }
            if (text != null) viewModel.onShared(this@MainActivity, text)
        }
    }
}
