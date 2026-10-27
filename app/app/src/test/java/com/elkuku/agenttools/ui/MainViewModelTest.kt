package com.elkuku.agenttools.ui

import android.content.Context
import android.content.ContextWrapper
import com.elkuku.agenttools.R
import com.elkuku.agenttools.UiText
import com.elkuku.agenttools.auth.AuthException
import com.elkuku.agenttools.auth.GoogleAuth
import com.elkuku.agenttools.auth.GoogleSession
import com.elkuku.agenttools.data.AgentProfile
import com.elkuku.agenttools.data.AgentProfileResult
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.AgentStatsResult
import com.elkuku.agenttools.data.AgentSummary
import com.elkuku.agenttools.data.AgentsResult
import com.elkuku.agenttools.data.CUSTOM_MEDALS
import com.elkuku.agenttools.data.CustomMedalsResult
import com.elkuku.agenttools.data.CustomMedalTier
import com.elkuku.agenttools.data.MeResult
import com.elkuku.agenttools.data.ProfilePart
import com.elkuku.agenttools.data.SaveResult
import com.elkuku.agenttools.data.StatsApi
import com.elkuku.agenttools.data.TimelineEvent
import com.elkuku.agenttools.data.TimelineEventResult
import com.elkuku.agenttools.data.TimelineEventsResult
import com.elkuku.agenttools.data.UserPrivacy
import com.elkuku.agenttools.data.UserRole
import com.elkuku.agenttools.data.UsersResult
import com.elkuku.agenttools.data.VisibilityResult
import com.elkuku.agenttools.scan.ArgbImage
import com.elkuku.agenttools.scan.RecognizedMedal
import com.elkuku.agenttools.uiText
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
import org.junit.Assert.assertNotNull
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

    private val orion = CUSTOM_MEDALS.single { it.group == "anomaly" && it.key == "orion" }

    private val echo = CUSTOM_MEDALS.single { it.group == "anomaly" && it.key == "echo" }

    /** What the fake scanner recognizes; the screenshots it got. */
    private var recognized = emptyList<RecognizedMedal>()

    private val scanned = mutableListOf<List<ArgbImage>>()

    private fun viewModel() = MainViewModel(auth, api) { scanned += it; recognized }

    @Before
    fun setUp() = Dispatchers.setMain(UnconfinedTestDispatcher())

    @After
    fun tearDown() = Dispatchers.resetMain()

    @Test
    fun signInLoadsStats() {
        val viewModel = signedIn()

        assertEquals(session, viewModel.state.value.session)
        assertEquals(listOf(entry), viewModel.state.value.entries)
    }

    @Test
    fun signInFailureShowsError() {
        auth.signInError = AuthException(uiText(R.string.sign_in_no_account))
        val viewModel = viewModel()

        viewModel.signIn(context)

        assertNull(viewModel.state.value.session)
        assertEquals(Status(uiText(R.string.sign_in_no_account), true), viewModel.state.value.status)
    }

    @Test
    fun cancelledSignInShowsNothing() {
        auth.signInError = AuthException(uiText(R.string.sign_in_cancelled), cancelled = true)
        val viewModel = viewModel()

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
    fun signInLoadsTheRoleAndSignOutForgetsIt() {
        val viewModel = signedIn()

        assertEquals(UserRole.USER, viewModel.state.value.role)

        viewModel.signOut(context)

        assertNull(viewModel.state.value.role)
    }

    @Test
    fun refreshReloadsTheRole() {
        api.me = MeResult.Success(UserRole.USER)
        val viewModel = signedIn()
        assertEquals(UserRole.USER, viewModel.state.value.role)

        api.me = MeResult.Success(UserRole.ADMIN, "TestAgent")
        api.lists += AgentStatsResult.Success(listOf(entry))
        viewModel.refresh(context)

        assertEquals(UserRole.ADMIN, viewModel.state.value.role)
    }

    @Test
    fun aFailedRoleLoadShowsNoMessage() {
        api.me = MeResult.Failure(uiText(R.string.error_network, "timeout"))
        val viewModel = signedIn()

        assertNull(viewModel.state.value.role)
        assertNull(viewModel.state.value.status)
        assertEquals(listOf(entry), viewModel.state.value.entries)
    }

    @Test
    fun serverChangeReloadsTheData() {
        val viewModel = signedIn()
        api.me = MeResult.Success(UserRole.USER)
        api.lists += AgentStatsResult.Success(emptyList())

        viewModel.serverChanged(context)

        assertEquals(UserRole.USER, viewModel.state.value.role)
        assertEquals(emptyList<AgentStatEntry>(), viewModel.state.value.entries)
        assertEquals(listOf("token-1", "token-1"), api.listTokens)
    }

    @Test
    fun serverChangeWhenSignedOutLoadsNothing() {
        val viewModel = viewModel()

        viewModel.serverChanged(context)

        assertTrue(api.listTokens.isEmpty())
    }

    @Test
    fun aShareClosesTheSettings() {
        val viewModel = viewModel()
        viewModel.openSettings()
        assertTrue(viewModel.state.value.settingsOpen)

        viewModel.onShared(context, ingressStats())

        assertFalse(viewModel.state.value.settingsOpen)
    }

    @Test
    fun signInShowsTheHomePageAndTheStatsOpenFromIt() {
        val viewModel = signedIn()
        assertFalse(viewModel.state.value.statsOpen)

        viewModel.openStats()
        assertTrue(viewModel.state.value.statsOpen)

        viewModel.closeStats()
        assertFalse(viewModel.state.value.statsOpen)
    }

    @Test
    fun aShareOpensTheStatsInsteadOfTheAgents() {
        val viewModel = signedIn()
        viewModel.openAgents(context)

        viewModel.onShared(context, "not stats")

        assertTrue(viewModel.state.value.statsOpen)
        assertFalse(viewModel.state.value.agentsOpen)
    }

    @Test
    fun sharedScreenshotsOpenTheStats() {
        val viewModel = signedIn()

        viewModel.onSharedScreenshots { emptyList() }

        assertTrue(viewModel.state.value.statsOpen)
    }

    @Test
    fun signOutClosesTheStats() {
        val viewModel = signedIn()
        viewModel.openStats()

        viewModel.signOut(context)

        assertFalse(viewModel.state.value.statsOpen)
    }

    @Test
    fun aUserWithoutAgentNameStaysOnTheStats() {
        api.me = MeResult.Success(UserRole.USER, null)
        val viewModel = signedIn()

        assertFalse(viewModel.state.value.settingsOpen)
    }

    @Test
    fun theFirstSharedStatsSetTheAgentName() {
        api.me = MeResult.Success(UserRole.USER, null)
        val viewModel = signedIn()
        api.saves += SaveResult.Success(2, "now")
        api.lists += AgentStatsResult.Success(listOf(entry))

        viewModel.onShared(context, ingressStats())

        assertEquals(1, api.saved.size)
        assertEquals("TestAgent", viewModel.state.value.agentName)
        assertNull(viewModel.state.value.pending)
        assertFalse(viewModel.state.value.settingsOpen)
        assertEquals(Status(uiText(R.string.agent_stats_saved), false), viewModel.state.value.status)
    }

    @Test
    fun aTakenAgentNameRejectsTheFirstStats() {
        api.me = MeResult.Success(UserRole.USER, null)
        val viewModel = signedIn()
        api.saves += SaveResult.Invalid(listOf(UiText.Plain("This agent name is already taken.")))

        viewModel.onShared(context, ingressStats())

        assertNull(viewModel.state.value.agentName)
        assertEquals(Status(UiText.Lines(listOf(UiText.Plain("This agent name is already taken."))), true), viewModel.state.value.status)
    }

    @Test
    fun statsOfAnotherAgentAreRejected() {
        api.me = MeResult.Success(UserRole.USER, "OtherAgent")
        val viewModel = signedIn()
        api.lists += AgentStatsResult.Success(listOf(entry))

        viewModel.onShared(context, ingressStats())

        assertTrue(api.saved.isEmpty())
        assertNull(viewModel.state.value.pending)
        assertEquals(
            Status(uiText(R.string.stats_of_other_agent, "TestAgent", "OtherAgent"), true),
            viewModel.state.value.status,
        )
    }

    @Test
    fun theAgentNameIsComparedIgnoringCase() {
        api.me = MeResult.Success(UserRole.USER, "testagent")
        val viewModel = signedIn()
        api.saves += SaveResult.Success(2, "now")
        api.lists += AgentStatsResult.Success(listOf(entry))

        viewModel.onShared(context, ingressStats())

        assertEquals(1, api.saved.size)
    }

    @Test
    fun agentsAreListedAndOpened() {
        val viewModel = signedIn()
        val other = AgentSummary("OtherAgent", "Resistance", 12)
        api.agents = AgentsResult.Success(listOf(other))
        api.agentProfiles["OtherAgent"] = AgentProfileResult.Success(AgentProfile(other, latest = entry))

        viewModel.openAgents(context)
        assertTrue(viewModel.state.value.agentsOpen)
        assertEquals(listOf(other), viewModel.state.value.agents)

        viewModel.openAgent(context, "OtherAgent")
        assertEquals(entry, viewModel.state.value.agent?.latest)

        // Back to the list, then to the own stats
        viewModel.closeAgent()
        assertNull(viewModel.state.value.agent)
        assertTrue(viewModel.state.value.agentsOpen)
        viewModel.closeAgents()
        assertFalse(viewModel.state.value.agentsOpen)
    }

    @Test
    fun adminsSeeEveryUser() {
        api.me = MeResult.Success(UserRole.ADMIN, "AdminAgent")
        val viewModel = signedIn()
        val private = UserPrivacy("someone@example.com", UserRole.USER, "PrivateAgent", visibilityChosen = true)
        api.users = UsersResult.Success(listOf(private))

        viewModel.openAgents(context, admin = true)

        assertEquals(listOf(private), viewModel.state.value.users)
        assertNull(viewModel.state.value.agents)
        assertFalse(private.showsAgent)

        // The agents list is the same for admins
        viewModel.closeAgents()
        viewModel.openAgents(context)
        assertNull(viewModel.state.value.users)
        assertEquals(emptyList<AgentSummary>(), viewModel.state.value.agents)
    }

    @Test
    fun usersSeeOnlyTheAgents() {
        val viewModel = signedIn()

        viewModel.openAgents(context)

        assertEquals(0, api.usersRequests)
        assertNull(viewModel.state.value.users)
        assertEquals(emptyList<AgentSummary>(), viewModel.state.value.agents)
    }

    @Test
    fun anAgentWhoNoLongerShowsTheProfileShowsWhy() {
        val viewModel = signedIn()
        viewModel.openAgents(context)

        viewModel.openAgent(context, "GoneAgent")

        assertNull(viewModel.state.value.agent)
        assertEquals(Status(uiText(R.string.error_agent_private, "GoneAgent"), true), viewModel.state.value.agentsStatus)
    }

    @Test
    fun signOutClosesTheAgents() {
        val viewModel = signedIn()
        viewModel.openAgents(context)

        viewModel.signOut(context)

        assertFalse(viewModel.state.value.agentsOpen)
        assertNull(viewModel.state.value.agents)
    }

    @Test
    fun signInLoadsTheAvatarAndSignOutForgetsIt() {
        api.me = MeResult.Success(UserRole.USER, "TestAgent", avatar = "data:image/jpeg;base64,abc")
        val viewModel = signedIn()
        assertEquals("data:image/jpeg;base64,abc", viewModel.state.value.avatar)

        viewModel.signOut(context)

        assertNull(viewModel.state.value.avatar)
    }

    @Test
    fun signInLoadsTheVisibleParts() {
        api.me = MeResult.Success(UserRole.USER, "TestAgent", visibleParts = setOf(ProfilePart.AGENT))

        assertEquals(setOf(ProfilePart.AGENT), signedIn().state.value.visibleParts)
    }

    @Test
    fun savingTheVisiblePartsStaysInTheSettings() {
        api.me = MeResult.Success(UserRole.USER, "TestAgent", visibilityChosen = false)
        val viewModel = signedIn()
        viewModel.chooseVisibility()
        assertTrue(viewModel.state.value.settingsOpen)
        assertFalse(viewModel.state.value.askVisibility)

        viewModel.saveVisibleParts(context, setOf(ProfilePart.AGENT, ProfilePart.STATS_HISTORY))

        assertEquals(listOf(setOf(ProfilePart.AGENT, ProfilePart.STATS_HISTORY)), api.savedVisibleParts)
        assertEquals(setOf(ProfilePart.AGENT, ProfilePart.STATS_HISTORY), viewModel.state.value.visibleParts)
        assertTrue(viewModel.state.value.visibilityChosen)
        assertTrue(viewModel.state.value.settingsOpen)
        assertEquals(Status(uiText(R.string.saved), false), viewModel.state.value.visibilityStatus)

        viewModel.closeSettings()
        assertNull(viewModel.state.value.visibilityStatus)
    }

    @Test
    fun aRejectedVisibilityShowsInTheSettings() {
        api.me = MeResult.Success(UserRole.USER, "TestAgent", visibilityChosen = false)
        val viewModel = signedIn()
        viewModel.openSettings()
        api.visibilityResult = VisibilityResult.Failure(uiText(R.string.error_network, "timeout"))

        viewModel.saveVisibleParts(context, setOf(ProfilePart.AGENT))

        assertFalse(viewModel.state.value.visibilityChosen)
        assertEquals(emptySet<ProfilePart>(), viewModel.state.value.visibleParts)
        assertEquals(Status(uiText(R.string.error_network, "timeout"), true), viewModel.state.value.visibilityStatus)
    }

    @Test
    fun signingInAgainHidesTheSignedOutMessage() {
        val viewModel = signedIn()
        viewModel.signOut(context)
        assertEquals(uiText(R.string.signed_out), viewModel.state.value.status?.text)

        api.lists += AgentStatsResult.Success(listOf(entry))
        viewModel.signIn(context)

        assertNull(viewModel.state.value.status)
    }

    @Test
    fun sharedStatsAreSavedRightAwayAndReloaded() {
        val viewModel = signedIn()
        val newest = entry.copy(id = 2, datetime = "2026-10-19 15:35:56")
        api.saves += SaveResult.Success(2, "now")
        api.lists += AgentStatsResult.Success(listOf(newest, entry))

        viewModel.onShared(context, ingressStats())

        val (token, stats) = api.saved.single()
        assertEquals("token-1", token)
        assertEquals(JsonPrimitive("TestAgent"), stats["nickname"])
        assertEquals(JsonPrimitive("2026-10-19 15:35:56"), stats["datetime"])
        assertEquals(JsonPrimitive(243517130L), stats["ap"])
        assertNull(viewModel.state.value.pending)
        assertEquals(Status(uiText(R.string.agent_stats_saved), false), viewModel.state.value.status)
        assertEquals(newest, viewModel.state.value.latest)
        assertEquals(entry, viewModel.state.value.first)
        assertFalse(viewModel.state.value.busy)
    }

    @Test
    fun savedStatsShowWhatChanged() {
        val viewModel = signedIn()
        val newest = entry.copy(id = 2, datetime = "2026-10-19 15:35:56", stats = mapOf("ap" to 5L))
        api.saves += SaveResult.Success(2, "now")
        api.lists += AgentStatsResult.Success(listOf(newest, entry))

        viewModel.onShared(context, ingressStats())

        val result = viewModel.state.value.importResult!!
        assertEquals(newest, result.entry)
        assertEquals(entry, result.previous)
        assertEquals(mapOf("ap" to 4L), result.diff)

        viewModel.dismissImportResult()
        assertNull(viewModel.state.value.importResult)
    }

    @Test
    fun otherSharedTextShowsHint() {
        val viewModel = signedIn()

        viewModel.onShared(context, "Buy milk")

        assertTrue(api.saved.isEmpty())
        assertEquals(Status(MainViewModel.NOT_STATS_MESSAGE, true), viewModel.state.value.status)
    }

    @Test
    fun shareHintSurvivesSignIn() {
        val viewModel = viewModel()
        viewModel.onShared(context, "Buy milk")
        auth.interactiveSession = session
        api.lists += AgentStatsResult.Success(listOf(entry))

        viewModel.signIn(context)

        assertEquals(Status(MainViewModel.NOT_STATS_MESSAGE, true), viewModel.state.value.status)
    }

    @Test
    fun statsSharedBeforeSignInAreSavedAfterSignIn() {
        val viewModel = viewModel()

        viewModel.onShared(context, ingressStats())

        assertTrue(api.saved.isEmpty())
        assertEquals(Status(uiText(R.string.sign_in_to_save_stats), false), viewModel.state.value.status)

        auth.interactiveSession = session
        api.saves += SaveResult.Success(2, "now")
        api.lists += AgentStatsResult.Success(listOf(entry))
        viewModel.signIn(context)

        assertEquals(1, api.saved.size)
        assertNull(viewModel.state.value.pending)
        assertEquals(Status(uiText(R.string.agent_stats_saved), false), viewModel.state.value.status)
    }

    @Test
    fun duplicateShowsServerMessage() {
        val viewModel = signedIn()
        api.saves += SaveResult.Failure(uiText(R.string.error_stats_already_saved))

        viewModel.onShared(context, ingressStats())

        assertEquals(Status(uiText(R.string.error_stats_already_saved), true), viewModel.state.value.status)
        assertNull(viewModel.state.value.pending)
    }

    @Test
    fun expiredTokenIsRefreshedOnce() {
        val viewModel = signedIn()
        val refreshed = session.copy(idToken = "token-2")
        auth.silentSessions += refreshed
        api.saves += SaveResult.Unauthorized(UiText.Plain("Invalid ID token."))
        api.saves += SaveResult.Success(2, "now")
        api.lists += AgentStatsResult.Success(listOf(entry))

        viewModel.onShared(context, ingressStats())

        assertEquals(listOf("token-1", "token-2"), api.saved.map { it.first })
        assertEquals(refreshed, viewModel.state.value.session)
        assertEquals(Status(uiText(R.string.agent_stats_saved), false), viewModel.state.value.status)
    }

    @Test
    fun unauthorizedWithoutRefreshSignsOut() {
        val viewModel = signedIn()
        api.lists += AgentStatsResult.Unauthorized(UiText.Plain("Unknown user."))

        viewModel.refresh(context)

        assertNull(viewModel.state.value.session)
        assertEquals(
            Status(uiText(R.string.not_authorized, UiText.Plain("Unknown user.")), true, untilSignIn = true),
            viewModel.state.value.status,
        )
    }

    @Test
    fun loadFailureKeepsStats() {
        val viewModel = signedIn()
        api.lists += AgentStatsResult.Failure(uiText(R.string.error_network, "timeout"))

        viewModel.refresh(context)

        assertEquals(entry, viewModel.state.value.latest)
        assertEquals(Status(uiText(R.string.error_network, "timeout"), true), viewModel.state.value.status)
    }

    private fun ingressStats(): String =
        checkNotNull(javaClass.classLoader?.getResource("ingress-stats-all-time.tsv")).readText()

    @Test
    fun refreshClearsAnOutdatedMessage() {
        auth.interactiveSession = session
        api.me = MeResult.Success(UserRole.USER)
        api.lists += AgentStatsResult.Failure(uiText(R.string.error_network, "timeout"))
        val viewModel = viewModel().also { it.signIn(context) }

        // Back online
        api.lists += AgentStatsResult.Success(listOf(entry))
        api.me = null
        viewModel.refresh(context)

        assertNull(viewModel.state.value.status)
        assertEquals(entry, viewModel.state.value.latest)
        assertEquals(emptyList<CustomMedalTier>(), viewModel.state.value.customMedals)
    }

    @Test
    fun signInLoadsTheTimelineEvents() {
        api.serverEvents += TimelineEvent(1, "2026-10-20", "Anomaly")

        val viewModel = signedIn()

        assertEquals(listOf(TimelineEvent(1, "2026-10-20", "Anomaly")), viewModel.state.value.timelineEvents)

        viewModel.signOut(context)
        assertNull(viewModel.state.value.timelineEvents)
    }

    @Test
    fun addsChangesAndDeletesTimelineEvents() {
        val viewModel = signedIn()

        viewModel.saveTimelineEvent(context, TimelineEvent(date = "2026-10-20", text = "Older"))
        viewModel.saveTimelineEvent(context, TimelineEvent(date = "2026-10-25", text = "Newer"))
        assertEquals(Status(uiText(R.string.event_saved), false), viewModel.state.value.status)
        // Newest first, with the ids the server gave them
        assertEquals(
            listOf(TimelineEvent(2, "2026-10-25", "Newer"), TimelineEvent(1, "2026-10-20", "Older")),
            viewModel.state.value.timelineEvents,
        )

        viewModel.saveTimelineEvent(context, TimelineEvent(1, "2026-10-30", "Moved"))
        assertEquals(listOf("Moved", "Newer"), viewModel.state.value.timelineEvents?.map { it.text })

        viewModel.deleteTimelineEvent(context, TimelineEvent(2, "2026-10-25", "Newer"))
        assertEquals(listOf(TimelineEvent(1, "2026-10-30", "Moved")), viewModel.state.value.timelineEvents)
        assertEquals(Status(uiText(R.string.event_deleted), false), viewModel.state.value.status)
    }

    @Test
    fun aFailedEventSaveKeepsTheEvents() {
        api.serverEvents += TimelineEvent(1, "2026-10-20", "Anomaly")
        val viewModel = signedIn()
        api.eventFailure = TimelineEventResult.Failure(uiText(R.string.error_network, "timeout"))

        viewModel.saveTimelineEvent(context, TimelineEvent(1, "2026-10-20", "Changed"))

        assertEquals(Status(uiText(R.string.error_network, "timeout"), true), viewModel.state.value.status)
        assertEquals(listOf(TimelineEvent(1, "2026-10-20", "Anomaly")), viewModel.state.value.timelineEvents)
    }

    private fun signedIn(): MainViewModel {
        auth.interactiveSession = session
        api.lists += AgentStatsResult.Success(listOf(entry))
        return viewModel().also { it.signIn(context) }
    }

    @Test
    fun usersAreAskedToChooseTheVisibilityUntilTheyDismiss() {
        api.me = MeResult.Success(UserRole.USER, "TestAgent", visibilityChosen = false)
        val viewModel = signedIn()
        assertTrue(viewModel.state.value.askVisibility)

        viewModel.dismissVisibility()
        assertFalse(viewModel.state.value.askVisibility)

        // Asked again on the next sign-in, unless chosen meanwhile
        viewModel.signOut(context)
        assertFalse(viewModel.state.value.askVisibility)
        api.lists += AgentStatsResult.Success(listOf(entry))
        viewModel.signIn(context)
        assertTrue(viewModel.state.value.askVisibility)

        api.me = MeResult.Success(UserRole.USER, "TestAgent", visibilityChosen = true)
        api.lists += AgentStatsResult.Success(listOf(entry))
        viewModel.refresh(context)
        assertFalse(viewModel.state.value.askVisibility)
    }

    @Test
    fun usersWhoChoseAreNotAsked() {
        assertFalse(signedIn().state.value.askVisibility)
    }

    @Test
    fun signInLoadsCustomMedals() {
        api.serverMedals = listOf(CustomMedalTier(echo, ""))

        assertEquals(api.serverMedals, signedIn().state.value.customMedals)
    }

    @Test
    fun sharedScreenshotsShowRecognizedMedalsForReview() {
        val screenshot = ArgbImage(1, 1, IntArray(1))
        recognized = listOf(RecognizedMedal(CustomMedalTier(orion, "gold"), 0.8, unsure = false))
        val viewModel = viewModel()

        viewModel.onSharedScreenshots { listOf(screenshot) }

        assertEquals(listOf(listOf(screenshot)), scanned)
        assertEquals(recognized, viewModel.state.value.medalScan)
        assertEquals(0, viewModel.state.value.medalScanKnown)
        assertFalse(viewModel.state.value.busy)
        assertNull(viewModel.state.value.progress)
        assertTrue(api.savedMedals.isEmpty())
    }

    @Test
    fun alreadySavedMedalsAreNotShownAgain() {
        api.serverMedals = listOf(CustomMedalTier(orion, "silver"), CustomMedalTier(echo, ""))
        recognized = listOf(
            // Same tier as saved: left out
            RecognizedMedal(CustomMedalTier(echo, ""), 0.8, unsure = false),
            // A new tier: shown
            RecognizedMedal(CustomMedalTier(orion, "gold"), 0.8, unsure = false),
        )
        val viewModel = signedIn()

        viewModel.onSharedScreenshots { emptyList() }

        assertEquals(recognized.drop(1), viewModel.state.value.medalScan)
        assertEquals(1, viewModel.state.value.medalScanKnown)
    }

    @Test
    fun lowerTiersThanSavedAreNotShown() {
        val persistence = CUSTOM_MEDALS.single { it.group == "annual" && it.key == "persistence" }
        api.serverMedals = listOf(CustomMedalTier(persistence, "black"), CustomMedalTier(orion, "silver"))
        recognized = listOf(
            RecognizedMedal(CustomMedalTier(persistence, "silver"), 0.45, unsure = true),
            RecognizedMedal(CustomMedalTier(orion, "bronze"), 0.8, unsure = false),
        )
        val viewModel = signedIn()

        viewModel.onSharedScreenshots { emptyList() }

        assertNull(viewModel.state.value.medalScan)
        assertEquals(Status(MainViewModel.allKnownMessage(2), false), viewModel.state.value.status)
    }

    @Test
    fun pendingMedalsAreNotShownAgain() {
        recognized = listOf(RecognizedMedal(CustomMedalTier(orion, "gold"), 0.8, unsure = false))
        val viewModel = viewModel()
        viewModel.saveMedalScan(context, listOf(CustomMedalTier(orion, "gold")))

        viewModel.onSharedScreenshots { emptyList() }

        assertNull(viewModel.state.value.medalScan)
        assertEquals(Status(MainViewModel.allKnownMessage(1), false), viewModel.state.value.status)
    }

    @Test
    fun scanningShowsProgress() {
        val viewModel = viewModel()
        var progress: UiText? = null
        recognized = emptyList()

        viewModel.onSharedScreenshots { progress = viewModel.state.value.progress; emptyList() }

        assertEquals(uiText(R.string.looking_for_custom_medals), progress)
        assertNull(viewModel.state.value.progress)
    }

    @Test
    fun screenshotsWithoutCustomMedalsShowError() {
        val viewModel = viewModel()

        viewModel.onSharedScreenshots { listOf(ArgbImage(1, 1, IntArray(1))) }

        assertNull(viewModel.state.value.medalScan)
        assertEquals(Status(MainViewModel.NO_MEDALS_MESSAGE, true), viewModel.state.value.status)
    }

    @Test
    fun savingTheReviewMergesWithTheServersMedals() {
        val viewModel = signedIn()
        // Saved meanwhile, e.g. from another device: the save must keep it
        api.serverMedals = listOf(CustomMedalTier(orion, "bronze"), CustomMedalTier(echo, ""))

        viewModel.saveMedalScan(context, listOf(CustomMedalTier(orion, "gold")))

        val expected = listOf(CustomMedalTier(orion, "gold"), CustomMedalTier(echo, ""))
        assertEquals(listOf(expected), api.savedMedals)
        assertEquals(expected, viewModel.state.value.customMedals)
        assertNull(viewModel.state.value.medalScan)
        assertNull(viewModel.state.value.pendingMedals)
        assertEquals(Status(UiText.Plural(R.plurals.custom_medals_saved, 1), false), viewModel.state.value.status)
    }

    @Test
    fun reviewBeforeSignInIsSavedAfterSignIn() {
        val viewModel = viewModel()

        viewModel.saveMedalScan(context, listOf(CustomMedalTier(orion, "gold")))

        assertEquals(Status(uiText(R.string.sign_in_to_save_custom_medals), false), viewModel.state.value.status)
        assertTrue(api.savedMedals.isEmpty())

        auth.interactiveSession = session
        api.lists += AgentStatsResult.Success(listOf(entry))
        viewModel.signIn(context)

        assertEquals(listOf(listOf(CustomMedalTier(orion, "gold"))), api.savedMedals)
        assertEquals(listOf(CustomMedalTier(orion, "gold")), viewModel.state.value.customMedals)
        assertNull(viewModel.state.value.pendingMedals)
    }

    @Test
    fun failedSaveKeepsTheMedalsForRefresh() {
        val viewModel = signedIn()
        api.medalFailure = CustomMedalsResult.Failure(uiText(R.string.error_network, "timeout"))

        viewModel.saveMedalScan(context, listOf(CustomMedalTier(orion, "gold")))

        assertEquals(Status(uiText(R.string.error_network, "timeout"), true), viewModel.state.value.status)
        assertEquals(listOf(CustomMedalTier(orion, "gold")), viewModel.state.value.pendingMedals)

        api.medalFailure = null
        api.lists += AgentStatsResult.Success(listOf(entry))
        viewModel.refresh(context)

        assertEquals(listOf(listOf(CustomMedalTier(orion, "gold"))), api.savedMedals)
        assertNull(viewModel.state.value.pendingMedals)
    }

    @Test
    fun dismissingTheReviewSavesNothing() {
        recognized = listOf(RecognizedMedal(CustomMedalTier(orion, "gold"), 0.45, unsure = true))
        val viewModel = signedIn()
        viewModel.onSharedScreenshots { emptyList() }

        viewModel.dismissMedalScan()

        assertNull(viewModel.state.value.medalScan)
        assertTrue(api.savedMedals.isEmpty())
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

        /** What `GET /api/me` answers; null for an agent "TestAgent" with the [serverMedals]. */
        var me: MeResult? = null

        override suspend fun me(idToken: String): MeResult = me ?: MeResult.Success(UserRole.USER, "TestAgent", serverMedals)

        /** What `PATCH /api/me` with the visible parts answers; null for success. */
        var visibilityResult: VisibilityResult? = null
        val savedVisibleParts = mutableListOf<Set<ProfilePart>>()

        override suspend fun saveVisibleParts(idToken: String, parts: Set<ProfilePart>): VisibilityResult {
            savedVisibleParts += parts
            return visibilityResult ?: VisibilityResult.Success(parts)
        }

        override suspend fun save(idToken: String, stats: JsonObject): SaveResult {
            saved += idToken to stats
            return saves.removeFirst()
        }

        override suspend fun list(idToken: String): AgentStatsResult {
            listTokens += idToken
            return lists.removeFirst()
        }

        /** The custom medals on the "server"; [medalFailure] fails saving them instead. */
        var serverMedals = emptyList<CustomMedalTier>()
        var medalFailure: CustomMedalsResult? = null
        val savedMedals = mutableListOf<List<CustomMedalTier>>()

        override suspend fun saveCustomMedals(idToken: String, medals: List<CustomMedalTier>): CustomMedalsResult {
            medalFailure?.let { return it }
            savedMedals += medals
            serverMedals = medals
            return CustomMedalsResult.Success(medals)
        }

        /** What `GET /api/agents` answers. */
        var agents: AgentsResult = AgentsResult.Success(emptyList())

        /** What `GET /api/agents/{agentName}` answers, by agent name. */
        val agentProfiles = mutableMapOf<String, AgentProfileResult>()

        override suspend fun agents(idToken: String): AgentsResult = agents

        override suspend fun agent(idToken: String, agentName: String): AgentProfileResult =
            agentProfiles[agentName] ?: AgentProfileResult.Failure(uiText(R.string.error_agent_private, agentName))

        /** What `GET /api/users` answers (admins). */
        var users: UsersResult = UsersResult.Success(emptyList())
        var usersRequests = 0

        override suspend fun users(idToken: String): UsersResult {
            usersRequests++
            return users
        }

        /** The custom timeline events on the "server"; [eventFailure] fails saving and deleting instead. */
        val serverEvents = mutableListOf<TimelineEvent>()
        var eventFailure: TimelineEventResult? = null

        override suspend fun timelineEvents(idToken: String): TimelineEventsResult = TimelineEventsResult.Success(serverEvents.toList())

        override suspend fun saveTimelineEvent(idToken: String, event: TimelineEvent): TimelineEventResult {
            eventFailure?.let { return it }
            val saved = event.copy(id = event.id ?: ((serverEvents.mapNotNull { it.id }.maxOrNull() ?: 0) + 1))
            serverEvents.removeAll { it.id == saved.id }
            serverEvents += saved
            return TimelineEventResult.Success(saved)
        }

        override suspend fun deleteTimelineEvent(idToken: String, id: Int): TimelineEventResult {
            eventFailure?.let { return it }
            serverEvents.removeAll { it.id == id }
            return TimelineEventResult.Success(null)
        }
    }
}
