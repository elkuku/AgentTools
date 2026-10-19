package com.elkuku.uploadapp

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.elkuku.uploadapp.ui.MainScreen
import com.elkuku.uploadapp.ui.MainViewModel
import com.elkuku.uploadapp.ui.theme.UploadAppTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val app = application as UploadApplication
        val factory = viewModelFactory {
            initializer { MainViewModel(app.googleAuth, app.uploadApi) }
        }

        setContent {
            UploadAppTheme {
                val viewModel: MainViewModel = viewModel(factory = factory)
                val state by viewModel.state.collectAsStateWithLifecycle()
                val activity = this@MainActivity

                LaunchedEffect(Unit) { viewModel.restoreSession(activity) }

                MainScreen(
                    state = state,
                    onSignIn = { viewModel.signIn(activity) },
                    onSignOut = { viewModel.signOut(activity) },
                    onTitleChange = viewModel::onTitleChange,
                    onJsonChange = viewModel::onJsonChange,
                    onUpload = { viewModel.upload(activity) },
                )
            }
        }
    }
}
