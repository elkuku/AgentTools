package com.elkuku.agenttools.auth

import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.BannergressApi
import com.elkuku.agenttools.data.BannergressResult
import com.elkuku.agenttools.data.SettingsStore
import com.elkuku.agenttools.uiText
import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.engine.HttpClientEngine
import io.ktor.client.engine.okhttp.OkHttp
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.forms.submitForm
import io.ktor.http.HttpStatusCode
import io.ktor.http.Parameters
import io.ktor.http.URLBuilder
import io.ktor.http.Url
import io.ktor.http.parameters
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import java.io.IOException
import java.security.MessageDigest
import java.security.SecureRandom
import java.util.Base64

/** A started Bannergress sign-in: open [url] in a WebView and wait for [REDIRECT_URI]. */
data class BannergressLogin(val url: String, val verifier: String, val state: String) {
    /** The authorization code of a redirect to [REDIRECT_URI] for this sign-in, else null. */
    fun code(redirect: String): String? {
        if (!redirect.startsWith(BannergressAuth.REDIRECT_URI)) return null
        val parameters = Url(redirect).parameters
        return parameters["code"]?.takeIf { parameters["state"] == state }
    }
}

@Serializable
private data class TokenResponse(val access_token: String? = null, val refresh_token: String? = null, val expires_in: Long = 0)

/**
 * The Bannergress sign-in as kBannerGuider does it: Keycloak's authorization code flow with PKCE
 * for the Bannergress website's client, whose only redirect is bannergress.com, so the app
 * catches it in a WebView. The refresh token is kept in the app's private settings; the access
 * token only in memory.
 */
class BannergressAuth(
    private val store: SettingsStore,
    engine: HttpClientEngine = OkHttp.create(),
    private val now: () -> Long = System::currentTimeMillis,
) {
    private val client = HttpClient(engine) {
        expectSuccess = false
        install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
    }

    private val _signedIn = MutableStateFlow(store.get(KEY_REFRESH_TOKEN) != null)
    val signedIn: StateFlow<Boolean> = _signedIn.asStateFlow()

    private var accessToken: String? = null
    private var expiresAt = 0L
    private val refreshing = Mutex()

    fun start(): BannergressLogin {
        val verifier = random(32)
        val state = random(16)
        val url = URLBuilder("$KEYCLOAK/auth").apply {
            parameters.append("client_id", CLIENT_ID)
            parameters.append("redirect_uri", REDIRECT_URI)
            parameters.append("response_type", "code")
            parameters.append("scope", "openid profile email")
            parameters.append("state", state)
            parameters.append("code_challenge", base64Url(MessageDigest.getInstance("SHA-256").digest(verifier.toByteArray())))
            parameters.append("code_challenge_method", "S256")
        }.buildString()

        return BannergressLogin(url, verifier, state)
    }

    /** Exchanges the code of [login]'s redirect for tokens. False when Bannergress refused it. */
    suspend fun finish(login: BannergressLogin, code: String): Boolean = token(
        parameters {
            append("grant_type", "authorization_code")
            append("client_id", CLIENT_ID)
            append("redirect_uri", REDIRECT_URI)
            append("code", code)
            append("code_verifier", login.verifier)
        },
    )

    /** A valid access token, refreshed when it (nearly) expired; null when signed out. Throws an IOException when offline. */
    suspend fun accessToken(): String? = refreshing.withLock {
        accessToken?.takeIf { now() < expiresAt - 60_000 }?.let { return it }
        val refreshToken = store.get(KEY_REFRESH_TOKEN) ?: return null
        val refreshed = token(
            parameters {
                append("grant_type", "refresh_token")
                append("client_id", CLIENT_ID)
                append("refresh_token", refreshToken)
            },
        )

        return if (refreshed) accessToken else null
    }

    fun signOut() {
        store.put(KEY_REFRESH_TOKEN, null)
        accessToken = null
        _signedIn.value = false
    }

    /**
     * Asks the token endpoint; signs out when it refuses (an expired session). Throws an
     * IOException when offline.
     */
    private suspend fun token(form: Parameters): Boolean {
        val response = client.submitForm("$KEYCLOAK/token", form)
        val tokens = if (response.status == HttpStatusCode.OK) response.body<TokenResponse>() else null
        if (tokens?.access_token == null) {
            if (response.status.value in 400..499) signOut()
            return false
        }

        accessToken = tokens.access_token
        expiresAt = now() + tokens.expires_in * 1000
        tokens.refresh_token?.let { store.put(KEY_REFRESH_TOKEN, it) }
        _signedIn.value = true
        return true
    }

    private fun random(bytes: Int) = base64Url(ByteArray(bytes).also(SecureRandom()::nextBytes))

    private fun base64Url(bytes: ByteArray): String = Base64.getUrlEncoder().withoutPadding().encodeToString(bytes)

    companion object {
        const val REDIRECT_URI = "https://bannergress.com/"
        private const val KEYCLOAK = "https://login.bannergress.com/auth/realms/bannergress/protocol/openid-connect"
        private const val CLIENT_ID = "bannergress-website"
        private const val KEY_REFRESH_TOKEN = "bannergress_refresh_token"
    }
}

/** The Bannergress account the app syncs from: sign-in and the sync, for the view model. */
interface BannergressConnector {
    val signedIn: StateFlow<Boolean>

    fun start(): BannergressLogin

    /** False when Bannergress refused the code. Throws an IOException when offline. */
    suspend fun finish(login: BannergressLogin, code: String): Boolean

    /** The counts and the newest banners for [agentName]; signs out when the session ended. */
    suspend fun sync(agentName: String): BannergressResult

    fun signOut()
}

class BannergressAccount(private val auth: BannergressAuth, private val api: BannergressApi) : BannergressConnector {
    override val signedIn: StateFlow<Boolean> get() = auth.signedIn

    override fun start() = auth.start()

    override suspend fun finish(login: BannergressLogin, code: String) = auth.finish(login, code)

    override suspend fun sync(agentName: String): BannergressResult {
        val token = try {
            auth.accessToken()
        } catch (e: IOException) {
            return BannergressResult.Failure(uiText(R.string.error_network, e.message ?: e::class.simpleName.orEmpty()))
        } ?: return BannergressResult.SignedOut

        return api.sync(token, agentName).also { if (it is BannergressResult.SignedOut) auth.signOut() }
    }

    override fun signOut() = auth.signOut()
}
