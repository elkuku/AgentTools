package com.elkuku.uploadapp.ui

import android.content.Context
import android.content.ContextWrapper
import com.elkuku.uploadapp.auth.AuthException
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.auth.GoogleSession
import com.elkuku.uploadapp.data.AgentStatEntry
import com.elkuku.uploadapp.data.AgentStatsResult
import com.elkuku.uploadapp.data.SaveResult
import com.elkuku.uploadapp.data.StatsApi
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class MainViewModelTest {
    private val context: Context = ContextWrapper(null)

    private val session = GoogleSession("token-1", "jane@example.com", "Jane")

    private val entry = AgentStatEntry(1, "2026-10-01 10:00:00", "TestAgent", "Enlightened", mapOf("ap" to 1L))

    private val auth = FakeGoogleAuth()

    private val api = FakeStatsApi()

    @Before
    fun setUp() = Dispatchers.setMain(UnconfinedTestDispatcher())

    @After
    fun tearDown() = Dispatchers.resetMain()

    @Test
    fun restoresSessionSilentlyOnceAndLoadsStats() {
        auth.silentSessions += session
        api.lists += AgentStatsResult.Success(listOf(entry))
        val viewModel = MainViewModel(auth, api)

        viewModel.restoreSession(context)
        viewModel.restoreSession(context)

        assertEquals(session, viewModel.state.value.session)
        assertEquals(entry, viewModel.state.value.latest)
        assertEquals(1, auth.silentCalls)
        assertEquals(listOf("token-1"), api.listTokens)
    }

    @Test
    fun withoutAuthorizationNothingIsLoaded() {
        val viewModel = MainViewModel(auth, api)

        viewModel.restoreSession(context)

        assertNull(viewModel.state.value.session)
        assertNull(viewModel.state.value.entries)
        assertTrue(api.listTokens.isEmpty())
    }

    @Test
    fun signInLoadsStats() {
        val viewModel = signedIn()

        assertEquals(session, viewModel.state.value.session)
        assertEquals(listOf(entry), viewModel.state.value.entries)
    }

    @Test
    fun signInFailureShowsError() {
        auth.signInError = AuthException("No Google account available on this device.")
        val viewModel = MainViewModel(auth, api)

        viewModel.signIn(context)

        assertNull(viewModel.state.value.session)
        assertEquals(Status("No Google account available on this device.", true), viewModel.state.value.status)
    }

    @Test
    fun cancelledSignInShowsNothing() {
        auth.signInError = AuthException("Sign-in cancelled.", cancelled = true)
        val viewModel = MainViewModel(auth, api)

        viewModel.signIn(context)

        assertNull(viewModel.state.value.status)
    }

    @Test
    fun signOutClearsSessionAndStats() {
        val viewModel = signedIn()

        viewModel.signOut(context)

        assertTrue(auth.signedOut)
        assertNull(viewModel.state.value.session)
        assertNull(viewModel.state.value.entries)
    }

    @Test
    fun sharedIngressStatsWaitForSave() {
        val viewModel = signedIn()

        viewModel.onShared(ingressStats())

        val pending = viewModel.state.value.pending!!
        assertEquals("TestAgent", pending.entry.nickname)
        assertEquals(243517130L, pending.entry["ap"])
        assertTrue(viewModel.state.value.canSave)
        assertTrue(api.saved.isEmpty())
    }

    @Test
    fun otherSharedTextShowsHint() {
        val viewModel = signedIn()

        viewModel.onShared("Buy milk")

        assertNull(viewModel.state.value.pending)
        assertEquals(Status(MainViewModel.NOT_STATS_MESSAGE, true), viewModel.state.value.status)
    }

    @Test
    fun shareHintSurvivesSignIn() {
        val viewModel = MainViewModel(auth, api)
        viewModel.onShared("Buy milk")
        auth.interactiveSession = session
        api.lists += AgentStatsResult.Success(listOf(entry))

        viewModel.signIn(context)

        assertEquals(Status(MainViewModel.NOT_STATS_MESSAGE, true), viewModel.state.value.status)
    }

    @Test
    fun savingRequiresSignIn() {
        val viewModel = MainViewModel(auth, api)
        viewModel.onShared(ingressStats())

        assertFalse(viewModel.state.value.canSave)
        viewModel.savePending(context)

        assertTrue(api.saved.isEmpty())
    }

    @Test
    fun saveSendsStatsAndReloads() {
        val viewModel = signedIn()
        viewModel.onShared(ingressStats())
        val newest = entry.copy(id = 2, datetime = "2026-10-19 15:35:56")
        api.saves += SaveResult.Success(2, "now")
        api.lists += AgentStatsResult.Success(listOf(newest, entry))

        viewModel.savePending(context)

        val (token, stats) = api.saved.single()
        assertEquals("token-1", token)
        assertEquals(JsonPrimitive("TestAgent"), stats["nickname"])
        assertEquals(JsonPrimitive("2026-10-19 15:35:56"), stats["datetime"])
        assertNull(viewModel.state.value.pending)
        assertEquals(Status("Agent stats saved.", false), viewModel.state.value.status)
        assertEquals(newest, viewModel.state.value.latest)
        assertEquals(entry, viewModel.state.value.first)
        assertFalse(viewModel.state.value.busy)
    }

    @Test
    fun duplicateKeepsPendingAndShowsServerMessage() {
        val viewModel = signedIn()
        viewModel.onShared(ingressStats())
        api.saves += SaveResult.Failure("Stat entry already added!")

        viewModel.savePending(context)

        assertEquals(Status("Stat entry already added!", true), viewModel.state.value.status)
        assertTrue(viewModel.state.value.pending != null)
    }

    @Test
    fun discardDropsPendingStats() {
        val viewModel = signedIn()
        viewModel.onShared(ingressStats())

        viewModel.discardPending()

        assertNull(viewModel.state.value.pending)
    }

    @Test
    fun expiredTokenIsRefreshedOnce() {
        val viewModel = signedIn()
        viewModel.onShared(ingressStats())
        val refreshed = session.copy(idToken = "token-2")
        auth.silentSessions += refreshed
        api.saves += SaveResult.Unauthorized("Invalid ID token.")
        api.saves += SaveResult.Success(2, "now")
        api.lists += AgentStatsResult.Success(listOf(entry))

        viewModel.savePending(context)

        assertEquals(listOf("token-1", "token-2"), api.saved.map { it.first })
        assertEquals(refreshed, viewModel.state.value.session)
        assertEquals(Status("Agent stats saved.", false), viewModel.state.value.status)
    }

    @Test
    fun unauthorizedWithoutRefreshSignsOut() {
        val viewModel = signedIn()
        api.lists += AgentStatsResult.Unauthorized("Unknown user.")

        viewModel.refresh(context)

        assertNull(viewModel.state.value.session)
        assertEquals(
            Status("Not authorized: Unknown user. Please sign in again.", true),
            viewModel.state.value.status,
        )
    }

    @Test
    fun loadFailureKeepsStats() {
        val viewModel = signedIn()
        api.lists += AgentStatsResult.Failure("Network error: timeout")

        viewModel.refresh(context)

        assertEquals(entry, viewModel.state.value.latest)
        assertEquals(Status("Network error: timeout", true), viewModel.state.value.status)
    }

    private fun ingressStats(): String =
        checkNotNull(javaClass.classLoader?.getResource("ingress-stats-all-time.tsv")).readText()

    private fun signedIn(): MainViewModel {
        auth.interactiveSession = session
        api.lists += AgentStatsResult.Success(listOf(entry))
        return MainViewModel(auth, api).also { it.signIn(context) }
    }

    private class FakeGoogleAuth : GoogleAuth {
        var interactiveSession: GoogleSession? = null
        var signInError: AuthException? = null
        val silentSessions = ArrayDeque<GoogleSession>()
        var silentCalls = 0
        var signedOut = false

        override suspend fun signIn(context: Context): GoogleSession {
            signInError?.let { throw it }
            return interactiveSession!!
        }

        override suspend fun signInSilently(context: Context): GoogleSession? {
            silentCalls++
            return silentSessions.removeFirstOrNull()
        }

        override suspend fun signOut(context: Context) {
            signedOut = true
        }
    }

    private class FakeStatsApi : StatsApi {
        val saves = ArrayDeque<SaveResult>()
        val lists = ArrayDeque<AgentStatsResult>()
        val saved = mutableListOf<Pair<String, JsonObject>>()
        val listTokens = mutableListOf<String>()

        override suspend fun save(idToken: String, stats: JsonObject): SaveResult {
            saved += idToken to stats
            return saves.removeFirst()
        }

        override suspend fun list(idToken: String): AgentStatsResult {
            listTokens += idToken
            return lists.removeFirst()
        }
    }
}
