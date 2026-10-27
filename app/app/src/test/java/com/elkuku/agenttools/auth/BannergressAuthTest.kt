package com.elkuku.agenttools.auth

import com.elkuku.agenttools.data.SettingsStore
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.engine.mock.toByteArray
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.Url
import io.ktor.http.headersOf
import io.ktor.http.parseUrlEncodedParameters
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.security.MessageDigest
import java.util.Base64

class BannergressAuthTest {
    private class MemoryStore : SettingsStore {
        val values = mutableMapOf<String, String>()

        override fun get(key: String) = values[key]

        override fun put(key: String, value: String?) {
            if (value == null) values.remove(key) else values[key] = value
        }
    }

    private val store = MemoryStore()

    private val jsonHeaders = headersOf(HttpHeaders.ContentType, "application/json")

    private var time = 1_000_000L

    /** The forms sent to the token endpoint. */
    private val forms = mutableListOf<Map<String, String>>()

    private fun auth(vararg responses: Pair<HttpStatusCode, String>): BannergressAuth {
        val queue = ArrayDeque(responses.toList())
        val engine = MockEngine { req ->
            assertEquals("https://login.bannergress.com/auth/realms/bannergress/protocol/openid-connect/token", req.url.toString())
            forms += String(req.body.toByteArray()).parseUrlEncodedParameters().entries().associate { it.key to it.value.single() }
            val (status, body) = queue.removeFirst()
            respond(body, status, jsonHeaders)
        }
        return BannergressAuth(store, engine) { time }
    }

    @Test
    fun startsAPkceSignInAndTakesOnlyItsOwnCode() {
        val login = auth().start()

        val parameters = Url(login.url).parameters
        assertEquals("bannergress-website", parameters["client_id"])
        assertEquals("https://bannergress.com/", parameters["redirect_uri"])
        assertEquals("S256", parameters["code_challenge_method"])
        val challenge = Base64.getUrlEncoder().withoutPadding().encodeToString(MessageDigest.getInstance("SHA-256").digest(login.verifier.toByteArray()))
        assertEquals(challenge, parameters["code_challenge"])

        assertEquals("c1", login.code("https://bannergress.com/?state=${login.state}&code=c1"))
        assertNull(login.code("https://bannergress.com/?state=other&code=c1"))
        assertNull(login.code("https://bannergress.com/?state=${login.state}&error=access_denied"))
        assertNull(login.code("https://login.bannergress.com/?state=${login.state}&code=c1"))
    }

    @Test
    fun keepsTheRefreshTokenAndRefreshesTheAccessToken() = runTest {
        val auth = auth(
            HttpStatusCode.OK to """{"access_token": "a1", "refresh_token": "r1", "expires_in": 300}""",
            HttpStatusCode.OK to """{"access_token": "a2", "refresh_token": "r2", "expires_in": 300}""",
        )
        val login = auth.start()
        assertFalse(auth.signedIn.value)

        assertTrue(auth.finish(login, "c1"))

        assertTrue(auth.signedIn.value)
        assertEquals("authorization_code", forms[0]["grant_type"])
        assertEquals(login.verifier, forms[0]["code_verifier"])
        assertEquals("r1", store.values["bannergress_refresh_token"])
        assertEquals("a1", auth.accessToken())

        // A minute before it expires
        time += 241_000
        assertEquals("a2", auth.accessToken())
        assertEquals(mapOf("grant_type" to "refresh_token", "client_id" to "bannergress-website", "refresh_token" to "r1"), forms[1])
        assertEquals("r2", store.values["bannergress_refresh_token"])
    }

    @Test
    fun aRefusedRefreshSignsOut() = runTest {
        store.values["bannergress_refresh_token"] = "old"
        val auth = auth(HttpStatusCode.BadRequest to """{"error": "invalid_grant"}""")
        assertTrue(auth.signedIn.value)

        assertNull(auth.accessToken())

        assertFalse(auth.signedIn.value)
        assertTrue(store.values.isEmpty())
    }
}
