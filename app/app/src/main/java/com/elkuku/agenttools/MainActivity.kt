package com.elkuku.agenttools

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.elkuku.agenttools.ui.MainScreen
import com.elkuku.agenttools.ui.MainViewModel
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

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        // Only on first creation: after a rotation the shared stats were already handled
        if (savedInstanceState == null) handleShare(intent)

        setContent {
            AgentToolsTheme {
                val state by viewModel.state.collectAsStateWithLifecycle()
                val activity = this@MainActivity

                LaunchedEffect(Unit) { viewModel.restoreSession(activity) }

                MainScreen(
                    state = state,
                    onSignIn = { viewModel.signIn(activity) },
                    onSignOut = { viewModel.signOut(activity) },
                    onRefresh = { viewModel.refresh(activity) },
                    onDismissImportResult = viewModel::dismissImportResult,
                    onSaveMedalScan = { viewModel.saveMedalScan(activity, it) },
                    onDismissMedalScan = viewModel::dismissMedalScan,
                )
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
