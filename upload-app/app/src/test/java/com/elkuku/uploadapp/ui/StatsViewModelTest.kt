package com.elkuku.uploadapp.ui

import android.content.Context
import android.content.ContextWrapper
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.auth.GoogleSession
import com.elkuku.uploadapp.data.AgentStatEntry
import com.elkuku.uploadapp.data.AgentStatsResult
import com.elkuku.uploadapp.data.UploadApi
import com.elkuku.uploadapp.data.UploadRequest
import com.elkuku.uploadapp.data.UploadResult
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.JsonObject
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class StatsViewModelTest {
    private val context: Context = ContextWrapper(null)

    private val newest = AgentStatEntry(2, "2026-10-19 15:35:56", "TestAgent", "Enlightened", mapOf("ap" to 2L))
    private val oldest = AgentStatEntry(1, "2026-10-01 10:00:00", "TestAgent", "Enlightened", mapOf("ap" to 1L))

    private var session: GoogleSession? = GoogleSession("token-1", "jane@example.com", "Jane")
    private var result: AgentStatsResult = AgentStatsResult.Success(listOf(newest, oldest))
    private val tokens = mutableListOf<String>()

    private val auth = object : GoogleAuth {
        override suspend fun signIn(context: Context) = error("not used")
        override suspend fun signInSilently(context: Context) = session
        override suspend fun signOut(context: Context) = Unit
    }

    private val api = object : UploadApi {
        override suspend fun upload(idToken: String, request: UploadRequest): UploadResult = error("not used")
        override suspend fun uploadAgentStats(idToken: String, stats: JsonObject): UploadResult = error("not used")
        override suspend fun agentStats(idToken: String): AgentStatsResult {
            tokens += idToken
            return result
        }
    }

    @Before
    fun setUp() = Dispatchers.setMain(UnconfinedTestDispatcher())

    @After
    fun tearDown() = Dispatchers.resetMain()

    @Test
    fun loadsStatsWithFreshToken() {
        val viewModel = StatsViewModel(auth, api)

        viewModel.load(context)

        val state = viewModel.state.value
        assertEquals(listOf("token-1"), tokens)
        assertEquals(newest, state.latest)
        assertEquals(oldest, state.first)
        assertFalse(state.loading)
        assertNull(state.error)
    }

    @Test
    fun notSignedInShowsError() {
        session = null
        val viewModel = StatsViewModel(auth, api)

        viewModel.load(context)

        assertEquals("Please sign in first.", viewModel.state.value.error)
        assertEquals(emptyList<String>(), tokens)
    }

    @Test
    fun failureKeepsPreviousStats() {
        val viewModel = StatsViewModel(auth, api)
        viewModel.load(context)

        result = AgentStatsResult.Failure("Network error: timeout")
        viewModel.load(context)

        assertEquals(newest, viewModel.state.value.latest)
        assertEquals("Network error: timeout", viewModel.state.value.error)
    }
}
