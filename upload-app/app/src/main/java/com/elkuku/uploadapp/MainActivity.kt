package com.elkuku.uploadapp

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.elkuku.uploadapp.ui.MainScreen
import com.elkuku.uploadapp.ui.MainViewModel
import com.elkuku.uploadapp.ui.StatsScreen
import com.elkuku.uploadapp.ui.StatsViewModel
import com.elkuku.uploadapp.ui.theme.UploadAppTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : ComponentActivity() {
    private val viewModel: MainViewModel by viewModels {
        val app = application as UploadApplication
        viewModelFactory { initializer { MainViewModel(app.googleAuth, app.uploadApi) } }
    }

    private val statsViewModel: StatsViewModel by viewModels {
        val app = application as UploadApplication
        viewModelFactory { initializer { StatsViewModel(app.googleAuth, app.uploadApi) } }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        // Only on first creation: after a rotation the ViewModel already holds the (maybe edited) data
        if (savedInstanceState == null) handleShare(intent)

        setContent {
            UploadAppTheme {
                val state by viewModel.state.collectAsStateWithLifecycle()
                val activity = this@MainActivity
                var showStats by rememberSaveable { mutableStateOf(false) }

                LaunchedEffect(Unit) { viewModel.restoreSession(activity) }

                if (showStats) {
                    val statsState by statsViewModel.state.collectAsStateWithLifecycle()
                    LaunchedEffect(Unit) { statsViewModel.load(activity) }
                    BackHandler { showStats = false }

                    StatsScreen(
                        state = statsState,
                        onBack = { showStats = false },
                        onRefresh = { statsViewModel.load(activity) },
                    )
                    return@UploadAppTheme
                }

                MainScreen(
                    state = state,
                    onSignIn = { viewModel.signIn(activity) },
                    onSignOut = { viewModel.signOut(activity) },
                    onTitleChange = viewModel::onTitleChange,
                    onJsonChange = viewModel::onJsonChange,
                    onUploadAsPlainData = viewModel::onUploadAsPlainData,
                    onUpload = { viewModel.upload(activity) },
                    onShowStats = { showStats = true },
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
            val shared = withContext(Dispatchers.IO) { readSharedData(intent, contentResolver) }
            if (shared != null) viewModel.onShared(shared)
        }
    }
}
