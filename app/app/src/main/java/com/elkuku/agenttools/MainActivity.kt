package com.elkuku.agenttools

import android.content.Context
import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.compose.ui.res.stringResource
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.elkuku.agenttools.ui.AgentsScreen
import com.elkuku.agenttools.ui.AppBuild
import com.elkuku.agenttools.ui.MainScreen
import com.elkuku.agenttools.ui.MainViewModel
import com.elkuku.agenttools.ui.BannergressActions
import com.elkuku.agenttools.ui.BannergressScreen
import com.elkuku.agenttools.ui.ProgressAndStatus
import com.elkuku.agenttools.ui.ProfileSetting
import com.elkuku.agenttools.ui.SettingsScreen
import com.elkuku.agenttools.ui.StatsScreen
import com.elkuku.agenttools.ui.theme.AgentToolsTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : ComponentActivity() {
    private val viewModel: MainViewModel by viewModels {
        val app = application as AgentToolsApplication
        viewModelFactory {
            initializer {
                MainViewModel(
                    app.googleAuth,
                    app.statsApi,
                    scanMedals = { screenshots -> withContext(Dispatchers.Default) { app.medalScanner.scan(screenshots) } },
                    bannergress = app.bannergress,
                )
            }
        }
    }

    private val serverSettings get() = (application as AgentToolsApplication).serverSettings

    // The chosen language before Android 13 (AppLanguage)
    override fun attachBaseContext(newBase: Context) = super.attachBaseContext(AppLanguage.wrap(newBase))

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
                        build = AppBuild(BuildConfig.VERSION_NAME, BuildConfig.VERSION_CODE, BuildConfig.BUILD_TYPE, BuildConfig.SERVER_SWITCH),
                        serverUrl = serverUrl,
                        defaultServerUrl = serverSettings.defaultUrl,
                        liveServerUrl = if (BuildConfig.SERVER_SWITCH) BuildConfig.LIVE_SERVER_BASE_URL else "",
                        onSaveServerUrl = { url ->
                            val before = serverSettings.url.value
                            serverSettings.set(url).also { error ->
                                if (error == null && serverSettings.url.value != before) viewModel.serverChanged(activity)
                            }
                        },
                        onBack = viewModel::closeSettings,
                    )
                } else if (state.bannergressOpen && state.session != null) {
                    BackHandler(onBack = viewModel::closeBannergress)
                    BannergressScreen(
                        title = stringResource(R.string.my_banners),
                        sync = state.bannergress,
                        doneBanners = state.doneBanners,
                        onBack = viewModel::closeBannergress,
                        actions = BannergressActions(
                            signIn = viewModel::startBannergressLogin,
                            code = { viewModel.finishBannergressLogin(activity, it) },
                            cancel = viewModel::cancelBannergressLogin,
                            sync = { viewModel.syncBannergress(activity) },
                            signOut = viewModel::signOutBannergress,
                        ),
                        signedIn = state.bannergressSignedIn,
                        login = state.bannergressLogin,
                        onSetDoneAt = { banner, doneAt -> viewModel.saveDoneAt(activity, banner, doneAt) },
                        header = { ProgressAndStatus(state) },
                    )
                } else if (state.statsOpen && state.session != null) {
                    BackHandler(onBack = viewModel::closeStats)
                    StatsScreen(
                        state = state,
                        onRefresh = { viewModel.refresh(activity) },
                        onDismissImportResult = viewModel::dismissImportResult,
                        onSaveMedalScan = { viewModel.saveMedalScan(activity, it) },
                        onDismissMedalScan = viewModel::dismissMedalScan,
                        onSaveTimelineEvent = { viewModel.saveTimelineEvent(activity, it) },
                        onDeleteTimelineEvent = { viewModel.deleteTimelineEvent(activity, it) },
                        onOpenBannergress = viewModel::openBannergress,
                        onBack = viewModel::closeStats,
                    )
                } else if (state.agentsOpen && state.agentBannergressOpen && state.agent != null) {
                    val agent = state.agent!!
                    BackHandler(onBack = viewModel::closeAgentBannergress)
                    BannergressScreen(
                        title = "${agent.summary.agentName} · ${stringResource(R.string.banners)}",
                        sync = agent.bannergress,
                        doneBanners = agent.doneBanners,
                        onBack = viewModel::closeAgentBannergress,
                    )
                } else if (state.agentsOpen) {
                    // Back from an agent to the list, from the list to the home page
                    BackHandler(onBack = { if (state.agent != null) viewModel.closeAgent() else viewModel.closeAgents() })
                    AgentsScreen(
                        agents = state.agents,
                        users = state.users,
                        agent = state.agent,
                        status = state.agentsStatus,
                        busy = state.busy,
                        onOpenAgent = { viewModel.openAgent(activity, it) },
                        onOpenBannergress = viewModel::openAgentBannergress,
                        onRefresh = { viewModel.refreshAgents(activity) },
                        onBack = { if (state.agent != null) viewModel.closeAgent() else viewModel.closeAgents() },
                    )
                } else {
                    MainScreen(
                        state = state,
                        serverUrl = serverUrl,
                        onSignIn = { viewModel.signIn(activity) },
                        onSignOut = { viewModel.signOut(activity) },
                        onRefresh = { viewModel.refresh(activity) },
                        onOpenSettings = viewModel::openSettings,
                        onOpenStats = viewModel::openStats,
                        onOpenAgents = { viewModel.openAgents(activity) },
                        onOpenBanners = viewModel::openBannergress,
                        onOpenAdminAgents = { viewModel.openAgents(activity, admin = true) },
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
