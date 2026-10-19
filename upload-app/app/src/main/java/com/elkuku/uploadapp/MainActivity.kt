package com.elkuku.uploadapp

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
import com.elkuku.uploadapp.ui.MainScreen
import com.elkuku.uploadapp.ui.MainViewModel
import com.elkuku.uploadapp.ui.theme.UploadAppTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : ComponentActivity() {
    private val viewModel: MainViewModel by viewModels {
        val app = application as UploadApplication
        viewModelFactory { initializer { MainViewModel(app.googleAuth, app.statsApi) } }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        // Only on first creation: after a rotation the ViewModel already holds the shared stats
        if (savedInstanceState == null) handleShare(intent)

        setContent {
            UploadAppTheme {
                val state by viewModel.state.collectAsStateWithLifecycle()
                val activity = this@MainActivity

                LaunchedEffect(Unit) { viewModel.restoreSession(activity) }

                MainScreen(
                    state = state,
                    onSignIn = { viewModel.signIn(activity) },
                    onSignOut = { viewModel.signOut(activity) },
                    onRefresh = { viewModel.refresh(activity) },
                    onSave = { viewModel.savePending(activity) },
                    onDiscard = viewModel::discardPending,
                )
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        handleShare(intent)
    }

    private fun handleShare(intent: Intent) {
        if (intent.action != Intent.ACTION_SEND) return

        lifecycleScope.launch {
            val text = withContext(Dispatchers.IO) { readSharedText(intent, contentResolver) }
            if (text != null) viewModel.onShared(text)
        }
    }
}
