package com.elkuku.uploadapp.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.uploadapp.auth.GoogleSession
import com.elkuku.uploadapp.ui.theme.UploadAppTheme

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainScreen(
    state: UiState,
    onSignIn: () -> Unit,
    onSignOut: () -> Unit,
    onTitleChange: (String) -> Unit,
    onJsonChange: (String) -> Unit,
    onUpload: () -> Unit,
) {
    Scaffold(topBar = { TopAppBar(title = { Text("Upload App") }) }) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            if (state.busy) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
            }

            val session = state.session
            if (session == null) {
                Text("Sign in with your Google account to upload data.")
                Button(onClick = onSignIn, enabled = !state.busy) { Text("Sign in with Google") }
            } else {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = session.displayName?.let { "$it (${session.email})" } ?: session.email,
                        modifier = Modifier.weight(1f),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    OutlinedButton(onClick = onSignOut, enabled = !state.busy) { Text("Sign out") }
                }

                OutlinedTextField(
                    value = state.title,
                    onValueChange = onTitleChange,
                    label = { Text("Title") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )

                OutlinedTextField(
                    value = state.json,
                    onValueChange = onJsonChange,
                    label = { Text("Data (JSON object)") },
                    isError = state.jsonError != null,
                    supportingText = state.jsonError?.let { { Text(it) } },
                    textStyle = MaterialTheme.typography.bodyMedium.copy(fontFamily = FontFamily.Monospace),
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 200.dp),
                )

                Button(onClick = onUpload, enabled = state.canSend, modifier = Modifier.fillMaxWidth()) {
                    Text("Upload")
                }
            }

            state.status?.let { status ->
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = if (status.isError) {
                            MaterialTheme.colorScheme.errorContainer
                        } else {
                            MaterialTheme.colorScheme.secondaryContainer
                        },
                    ),
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text(status.text, modifier = Modifier.padding(12.dp))
                }
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
private fun MainScreenPreview() {
    UploadAppTheme {
        MainScreen(
            state = UiState(
                session = GoogleSession("token", "jane@example.com", "Jane"),
                title = "Hello",
                status = Status("Uploaded (id 1).", false),
            ),
            onSignIn = {},
            onSignOut = {},
            onTitleChange = {},
            onJsonChange = {},
            onUpload = {},
        )
    }
}
