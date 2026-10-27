package com.elkuku.agenttools.auth

import android.content.Context
import com.elkuku.agenttools.R
import com.elkuku.agenttools.UiText
import com.elkuku.agenttools.uiText
import androidx.credentials.ClearCredentialStateRequest
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialException
import androidx.credentials.exceptions.NoCredentialException
import com.google.android.libraries.identity.googleid.GetGoogleIdOption
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import com.google.android.libraries.identity.googleid.GoogleIdTokenParsingException

/** A signed-in Google account and its current ID token. */
data class GoogleSession(
    val idToken: String,
    val email: String,
    val displayName: String?,
)

/** [text] is shown to the user. */
class AuthException(val text: UiText, val cancelled: Boolean = false) : Exception(text.toString())

/**
 * Google sign-in. Every call takes the current Activity context because
 * Credential Manager may need to show UI.
 */
interface GoogleAuth {
    /** Shows the "Sign in with Google" account picker. */
    suspend fun signIn(context: Context): GoogleSession

    /**
     * Gets a fresh ID token for an account that already authorized the app,
     * without the account picker. Returns null if no such account is available.
     */
    suspend fun signInSilently(context: Context): GoogleSession?

    suspend fun signOut(context: Context)
}

class CredentialManagerGoogleAuth(
    /** The *Web* OAuth client ID; it becomes the token's audience checked by the server. */
    private val serverClientId: String,
) : GoogleAuth {

    override suspend fun signIn(context: Context): GoogleSession {
        val option = GetSignInWithGoogleOption.Builder(requireClientId()).build()

        return try {
            request(context, GetCredentialRequest.Builder().addCredentialOption(option).build())
        } catch (_: GetCredentialCancellationException) {
            throw AuthException(uiText(R.string.sign_in_cancelled), cancelled = true)
        } catch (_: NoCredentialException) {
            throw AuthException(uiText(R.string.sign_in_no_account))
        } catch (e: GetCredentialException) {
            throw AuthException(uiText(R.string.sign_in_failed, e.message ?: e.type))
        }
    }

    override suspend fun signInSilently(context: Context): GoogleSession? {
        val option = GetGoogleIdOption.Builder()
            .setServerClientId(requireClientId())
            .setFilterByAuthorizedAccounts(true)
            .setAutoSelectEnabled(true)
            .build()

        return try {
            request(context, GetCredentialRequest.Builder().addCredentialOption(option).build())
        } catch (_: GetCredentialException) {
            null
        }
    }

    override suspend fun signOut(context: Context) {
        // Also stops automatic sign-in until the user picks an account again
        CredentialManager.create(context).clearCredentialState(ClearCredentialStateRequest())
    }

    private suspend fun request(context: Context, request: GetCredentialRequest): GoogleSession {
        val credential = CredentialManager.create(context).getCredential(context, request).credential

        if (credential !is CustomCredential ||
            credential.type != GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL
        ) {
            throw AuthException(UiText.Plain("Unexpected credential type: ${credential.type}"))
        }

        val google = try {
            GoogleIdTokenCredential.createFrom(credential.data)
        } catch (e: GoogleIdTokenParsingException) {
            throw AuthException(uiText(R.string.sign_in_invalid_token, e.message.orEmpty()))
        }

        return GoogleSession(
            idToken = google.idToken,
            email = google.email ?: throw AuthException(uiText(R.string.sign_in_no_email)),
            displayName = google.displayName,
        )
    }

    private fun requireClientId(): String {
        if (serverClientId.isBlank()) {
            throw AuthException(UiText.Plain("google.serverClientId is not set in local.properties."))
        }
        return serverClientId
    }
}
