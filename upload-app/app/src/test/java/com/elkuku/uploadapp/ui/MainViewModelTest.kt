package com.elkuku.uploadapp.ui

import android.content.Context
import android.content.ContextWrapper
import com.elkuku.uploadapp.auth.AuthException
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.auth.GoogleSession
import com.elkuku.uploadapp.data.AgentStatsResult
import com.elkuku.uploadapp.data.SharedData
import com.elkuku.uploadapp.data.UploadApi
import com.elkuku.uploadapp.data.UploadRequest
import com.elkuku.uploadapp.data.UploadResult
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
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

    private val auth = FakeGoogleAuth()

    private val api = FakeUploadApi()

    @Before
    fun setUp() = Dispatchers.setMain(UnconfinedTestDispatcher())

    @After
    fun tearDown() = Dispatchers.resetMain()

    @Test
    fun restoresSessionSilentlyOnce() {
        auth.silentSessions += session
        val viewModel = MainViewModel(auth, api)

        viewModel.restoreSession(context)
        viewModel.restoreSession(context)

        assertEquals(session, viewModel.state.value.session)
        assertEquals(1, auth.silentCalls)
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
    fun invalidJsonDisablesUpload() {
        val viewModel = signedIn()
        viewModel.onTitleChange("Title")

        viewModel.onJsonChange("[1, 2]")

        assertFalse(viewModel.state.value.canSend)
        assertTrue(viewModel.state.value.jsonError!!.startsWith("The data must be a JSON object"))
    }

    @Test
    fun sharedDataFillsTheFormAndUploads() {
        val viewModel = signedIn()
        viewModel.onJsonChange("[")

        viewModel.onShared(SharedData("Buy milk", subject = "Shopping"))

        val state = viewModel.state.value
        assertEquals("Shopping", state.title)
        assertNull(state.jsonError)
        assertTrue(state.canSend)

        api.results += UploadResult.Success(7, "now")
        viewModel.upload(context)

        assertEquals(buildJsonObject { put("text", "Buy milk") }, api.calls.single().second.data)
    }

    @Test
    fun sharedIngressStatsAreSavedAsAgentStats() {
        val viewModel = signedIn()

        viewModel.onShared(SharedData(ingressStats()))

        assertEquals(UploadTarget.AGENT_STATS, viewModel.state.value.target)
        viewModel.onTitleChange("")
        assertTrue(viewModel.state.value.canSend)

        api.results += UploadResult.Success(3, "now")
        viewModel.upload(context)

        assertTrue(api.calls.isEmpty())
        val stats = api.statsCalls.single().second
        assertEquals(JsonPrimitive("TestAgent"), stats["nickname"])
        assertEquals(JsonPrimitive(243517130L), stats["ap"])
        assertEquals(Status("Agent stats saved (id 3).", false), viewModel.state.value.status)
    }

    @Test
    fun ingressStatsCanBeSentAsPlainUpload() {
        val viewModel = signedIn()
        viewModel.onShared(SharedData(ingressStats()))

        viewModel.onUploadAsPlainData()

        api.results += UploadResult.Success(4, "now")
        viewModel.upload(context)

        assertTrue(api.statsCalls.isEmpty())
        assertEquals("Ingress stats: TestAgent, 2026-10-19 15:35:56", api.calls.single().second.title)
    }

    @Test
    fun duplicateAgentStatsShowServerMessage() {
        val viewModel = signedIn()
        viewModel.onShared(SharedData(ingressStats()))

        api.results += UploadResult.Failure("Stat entry already added!")
        viewModel.upload(context)

        assertEquals(Status("Stat entry already added!", true), viewModel.state.value.status)
    }

    @Test
    fun otherSharedDataStaysAnUpload() {
        val viewModel = signedIn()

        viewModel.onShared(SharedData("Buy milk"))

        assertEquals(UploadTarget.UPLOAD, viewModel.state.value.target)
    }

    @Test
    fun uploadSendsTokenAndShowsSuccess() {
        api.results += UploadResult.Success(5, "now")
        val viewModel = signedIn()
        viewModel.onTitleChange("  Title  ")
        viewModel.onJsonChange("""{"a": 1}""")

        viewModel.upload(context)

        val (token, request) = api.calls.single()
        assertEquals("token-1", token)
        assertEquals("Title", request.title)
        assertEquals(Status("Uploaded (id 5).", false), viewModel.state.value.status)
        assertFalse(viewModel.state.value.busy)
    }

    @Test
    fun expiredTokenIsRefreshedAndRetriedOnce() {
        api.results += UploadResult.Unauthorized("Invalid ID token.")
        api.results += UploadResult.Success(6, "now")
        auth.silentSessions += session.copy(idToken = "token-2")
        val viewModel = signedIn()
        viewModel.onTitleChange("Title")

        viewModel.upload(context)

        assertEquals(listOf("token-1", "token-2"), api.calls.map { it.first })
        assertEquals("token-2", viewModel.state.value.session?.idToken)
        assertEquals(Status("Uploaded (id 6).", false), viewModel.state.value.status)
    }

    @Test
    fun unauthorizedAfterRefreshSignsOut() {
        api.results += UploadResult.Unauthorized("Unknown user.")
        api.results += UploadResult.Unauthorized("Unknown user.")
        auth.silentSessions += session.copy(idToken = "token-2")
        val viewModel = signedIn()
        viewModel.onTitleChange("Title")

        viewModel.upload(context)

        assertEquals(2, api.calls.size)
        assertNull(viewModel.state.value.session)
        assertTrue(viewModel.state.value.status!!.isError)
    }

    @Test
    fun validationErrorsAreShown() {
        api.results += UploadResult.Invalid(listOf("title: too long", "data: too large"))
        val viewModel = signedIn()
        viewModel.onTitleChange("Title")

        viewModel.upload(context)

        assertEquals(Status("title: too long\ndata: too large", true), viewModel.state.value.status)
    }

    @Test
    fun signOutClearsSession() {
        val viewModel = signedIn()

        viewModel.signOut(context)

        assertNull(viewModel.state.value.session)
        assertTrue(auth.signedOut)
    }

    private fun ingressStats(): String =
        checkNotNull(javaClass.classLoader?.getResource("ingress-stats-all-time.tsv")).readText()

    private fun signedIn(): MainViewModel {
        auth.interactiveSession = session
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

    private class FakeUploadApi : UploadApi {
        val results = ArrayDeque<UploadResult>()
        val calls = mutableListOf<Pair<String, UploadRequest>>()
        val statsCalls = mutableListOf<Pair<String, JsonObject>>()

        override suspend fun upload(idToken: String, request: UploadRequest): UploadResult {
            calls += idToken to request
            return results.removeFirst()
        }

        override suspend fun uploadAgentStats(idToken: String, stats: JsonObject): UploadResult {
            statsCalls += idToken to stats
            return results.removeFirst()
        }

        override suspend fun agentStats(idToken: String): AgentStatsResult = error("not used")
    }
}
