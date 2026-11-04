package com.elkuku.agenttools.ui

import android.content.Context
import android.content.ContextWrapper
import com.elkuku.agenttools.R
import com.elkuku.agenttools.UiText
import com.elkuku.agenttools.auth.AuthException
import com.elkuku.agenttools.auth.BannergressConnector
import com.elkuku.agenttools.auth.BannergressLogin
import com.elkuku.agenttools.data.BannergressResult
import com.elkuku.agenttools.data.BannergressSaveResult
import com.elkuku.agenttools.data.BannergressSync
import com.elkuku.agenttools.data.BannergressBanner
import com.elkuku.agenttools.data.DoneBanner
import com.elkuku.agenttools.data.DoneBannerResult
import com.elkuku.agenttools.data.DoneBannersResult
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
import com.elkuku.agenttools.data.Link
import com.elkuku.agenttools.data.HistoryAttendance
import com.elkuku.agenttools.data.HistoryCache
import com.elkuku.agenttools.data.HistoryDocument
import com.elkuku.agenttools.data.HistoryResult
import com.elkuku.agenttools.data.IngressEvent
import com.elkuku.agenttools.data.IngressEventCache
import com.elkuku.agenttools.data.IngressEventList
import com.elkuku.agenttools.data.IngressEventListResult
import com.elkuku.agenttools.data.LinkListCache
import com.elkuku.agenttools.data.LinkListResult
import com.elkuku.agenttools.data.LinkSection
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
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
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

    private fun viewModel() = MainViewModel(auth, api, scanMedals = { scanned += it; recognized })

    @Before
    fun setUp() = Dispatchers.setMain(UnconfinedTestDispatcher())

    @After
    fun tearDown() = Dispatchers.resetMain()

    private val official = listOf(LinkSection("Official", listOf(Link("Ingress", "https://ingress.com", "The official site."))))

    private val tools = listOf(LinkSection("Tools", listOf(Link("IITC-CE", "https://iitc.app"))))

    private class FakeHistoryCache(val documents: MutableMap<String, HistoryDocument> = mutableMapOf()) : HistoryCache {
        override fun read(slug: String) = documents[slug]

        override fun write(document: HistoryDocument) {
            documents[document.slug] = document
        }
    }

    private val prague = HistoryAttendance("anomalies:2026-05-16:Prague", "2026-05-16", "Orion Anomaly: Prague")

    @Test
    fun attendingAddsTheEventToTheTimelineAndAgainRemovesIt() {
        val viewModel = signedIn()

        viewModel.toggleAttendance(context, prague)

        assertEquals(prague.key, viewModel.state.value.timelineEvents?.singleOrNull()?.historyKey)
        assertEquals(prague.text, api.serverEvents.single().text)
        assertNull(viewModel.state.value.status)

        viewModel.toggleAttendance(context, prague)

        assertEquals(emptyList<TimelineEvent>(), viewModel.state.value.timelineEvents)
        assertEquals(0, api.serverEvents.size)
    }

    @Test
    fun aFailedAttendanceShowsOnTheHistory() {
        val viewModel = signedIn()
        api.eventFailure = TimelineEventResult.Failure(UiText.Plain("Offline"))

        viewModel.toggleAttendance(context, prague)

        assertEquals(true, viewModel.state.value.historyStatus["anomalies"]?.isError)
        assertEquals(emptyList<TimelineEvent>(), viewModel.state.value.timelineEvents)
    }

    @Test
    fun historyLoadsWithoutASignInAndIsSaved() {
        val cache = FakeHistoryCache()
        val document = HistoryDocument("anomalies", "Ingress XM Anomalies")
        api.historyResults["anomalies"] = HistoryResult.Success(document)
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, historyCache = cache)

        viewModel.openHistory()

        assertTrue(viewModel.state.value.historyOpen)
        assertEquals("anomalies", viewModel.state.value.historySlug)
        assertEquals(document, viewModel.state.value.history["anomalies"])
        assertTrue(viewModel.state.value.historyLoading.isEmpty())
        assertEquals(document, cache.documents["anomalies"])

        viewModel.closeHistory()

        assertFalse(viewModel.state.value.historyOpen)
    }

    @Test
    fun historyTabsLoadTheirDocument() {
        val document = HistoryDocument("mission-days", "Ingress Mission Days")
        api.historyResults["mission-days"] = HistoryResult.Success(document)
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() })

        viewModel.openHistory()
        viewModel.selectHistory("mission-days")

        assertEquals("mission-days", viewModel.state.value.historySlug)
        assertEquals(document, viewModel.state.value.history["mission-days"])
    }

    @Test
    fun historyKeepsTheSavedDocumentWhenOffline() {
        val saved = HistoryDocument("anomalies", "Saved")
        api.historyResults["anomalies"] = HistoryResult.Failure(UiText.Plain("Offline"))
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, historyCache = FakeHistoryCache(mutableMapOf("anomalies" to saved)))

        viewModel.openHistory()

        assertEquals(saved, viewModel.state.value.history["anomalies"])
        assertFalse(viewModel.state.value.historyStatus.getValue("anomalies").isError)
    }

    @Test
    fun historyWithoutASavedDocumentShowsTheError() {
        api.historyResults["anomalies"] = HistoryResult.Failure(UiText.Plain("Offline"))
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() })

        viewModel.openHistory()

        assertTrue(viewModel.state.value.historyStatus.getValue("anomalies").isError)
    }

    private val zhuhai = IngressEventList(listOf(IngressEvent("mission_day", "Mission Day", "2026-11-07", "2026-11-07", location = "Zhuhai, China")))

    private class FakeEventCache(var list: IngressEventList? = null) : IngressEventCache {
        override fun read() = list

        override fun write(list: IngressEventList) {
            this.list = list
        }
    }

    @Test
    fun eventsLoadWithoutASignInAndAreSaved() {
        val cache = FakeEventCache()
        api.events = IngressEventListResult.Success(zhuhai)
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, eventCache = cache)

        viewModel.openEvents()

        assertTrue(viewModel.state.value.eventsOpen)
        assertEquals(zhuhai, viewModel.state.value.events)
        assertNull(viewModel.state.value.eventsStatus)
        assertFalse(viewModel.state.value.eventsLoading)
        assertEquals(zhuhai, cache.list)

        viewModel.closeEvents()

        assertFalse(viewModel.state.value.eventsOpen)
    }

    @Test
    fun eventsKeepTheSavedListWhenOffline() {
        api.events = IngressEventListResult.Failure(UiText.Plain("Offline"))
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, eventCache = FakeEventCache(zhuhai))

        viewModel.openEvents()

        assertEquals(zhuhai, viewModel.state.value.events)
        assertFalse(viewModel.state.value.eventsStatus!!.isError)
    }

    @Test
    fun eventsWithoutASavedListShowTheError() {
        api.events = IngressEventListResult.Failure(UiText.Plain("Offline"))
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() })

        viewModel.openEvents()

        assertNull(viewModel.state.value.events)
        assertTrue(viewModel.state.value.eventsStatus!!.isError)
    }

    @Test
    fun linksLoadWithoutASignInAndAreSaved() {
        val cache = FakeLinkListCache()
        api.links = LinkListResult.Success(official)
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, linkCache = cache)

        viewModel.openLinks()

        assertTrue(viewModel.state.value.linksOpen)
        assertEquals(official, viewModel.state.value.links)
        assertNull(viewModel.state.value.linksStatus)
        assertFalse(viewModel.state.value.linksLoading)
        assertEquals(official, cache.sections)
    }

    @Test
    fun linksShowTheSavedListWhileLoading() {
        api.links = LinkListResult.Success(tools)
        val gate = CompletableDeferred<Unit>()
        api.loadGate = gate
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, linkCache = FakeLinkListCache(official))

        viewModel.openLinks()

        assertEquals(official, viewModel.state.value.links)
        assertTrue(viewModel.state.value.linksLoading)

        gate.complete(Unit)

        assertEquals(tools, viewModel.state.value.links)
        assertFalse(viewModel.state.value.linksLoading)
    }

    @Test
    fun linksOfflineKeepTheSavedListAndSayWhy() {
        val offline = uiText(R.string.error_network, "timeout")
        api.links = LinkListResult.Failure(offline)
        val cache = FakeLinkListCache(official)
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, linkCache = cache)

        viewModel.openLinks()

        assertEquals(official, viewModel.state.value.links)
        assertEquals(Status(uiText(R.string.links_saved_list, offline), false), viewModel.state.value.linksStatus)
        assertEquals(official, cache.sections)
    }

    @Test
    fun linksOfflineWithoutASavedListShowTheError() {
        val offline = uiText(R.string.error_network, "timeout")
        api.links = LinkListResult.Failure(offline)
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, linkCache = FakeLinkListCache())

        viewModel.openLinks()

        assertNull(viewModel.state.value.links)
        assertEquals(Status(offline, true), viewModel.state.value.linksStatus)
    }

    @Test
    fun linksCloseAndASharedStatsUploadClosesThem() {
        val viewModel = viewModel()
        viewModel.openLinks()
        viewModel.closeLinks()

        assertFalse(viewModel.state.value.linksOpen)

        viewModel.openLinks()
        viewModel.onShared(context, "not stats")

        assertFalse(viewModel.state.value.linksOpen)
        assertTrue(viewModel.state.value.statsOpen)
    }

    @Test
    fun signInLoadsStats() {
        val viewModel = signedIn()

        assertEquals(session, viewModel.state.value.session)
        assertEquals(listOf(entry), viewModel.state.value.entries)
    }

    @Test
    fun signInShowsThatTheStatsAreLoading() {
        auth.interactiveSession = session
        api.lists += AgentStatsResult.Success(listOf(entry))
        val gate = CompletableDeferred<Unit>()
        api.loadGate = gate
        val viewModel = viewModel()

        viewModel.signIn(context)

        assertEquals(uiText(R.string.loading_your_stats), viewModel.state.value.progress)
        assertNull(viewModel.state.value.entries)

        gate.complete(Unit)

        assertNull(viewModel.state.value.progress)
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
    fun theTimelineOpensFromTheStatsAndBackGoesToThem() {
        val viewModel = signedIn()
        viewModel.openStats()

        viewModel.openTimeline()
        assertTrue(viewModel.state.value.timelineOpen)

        viewModel.closeTimeline()
        assertFalse(viewModel.state.value.timelineOpen)
        assertTrue(viewModel.state.value.statsOpen)

        // Closing the stats closes it too, and a share shows its result on the stats
        viewModel.openTimeline()
        viewModel.closeStats()
        assertFalse(viewModel.state.value.timelineOpen)
        viewModel.openStats()
        viewModel.openTimeline()
        viewModel.onShared(context, "not stats")
        assertFalse(viewModel.state.value.timelineOpen)
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
    fun loadsAtTheSameTimeRefreshTheTokenOnce() {
        val viewModel = signedIn()
        val refreshed = session.copy(idToken = "token-2")
        auth.silentSessions += refreshed
        api.expiredToken = "token-1"
        api.lists += AgentStatsResult.Success(listOf(entry))
        val gate = CompletableDeferred<Unit>()
        api.loadGate = gate

        viewModel.refresh(context)
        gate.complete(Unit)

        assertEquals(1, auth.silentCalls)
        assertEquals(listOf("token-1", "token-1", "token-2"), api.listTokens)
        assertEquals(refreshed, viewModel.state.value.session)
        assertNull(viewModel.state.value.status)
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

    @Test
    fun theBannergressSignInSyncsAndSavesOnTheServer() {
        val bannergress = FakeBannergress()
        auth.interactiveSession = session
        api.lists += AgentStatsResult.Success(listOf(entry))
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, bannergress = bannergress).also { it.signIn(context) }

        viewModel.startBannergressLogin()
        assertNotNull(viewModel.state.value.bannergressLogin)
        viewModel.finishBannergressLogin(context, "good")

        val state = viewModel.state.value
        assertNull(state.bannergressLogin)
        assertTrue(state.bannergressSignedIn)
        assertEquals(listOf("TestAgent"), bannergress.syncedAgents)
        assertEquals(listOf(1), api.savedBannergress.map { it.done })
        assertEquals("2026-10-27T18:00:00+00:00", state.bannergress?.syncedAt)
        assertEquals(uiText(R.string.bannergress_synced), state.status?.text)

        // The done list is reloaded with the sync
        assertEquals(listOf(DoneBanner(1, "b-1", "Banner", 6)), state.doneBanners)

        // Another Google account must not sync this Bannergress account
        viewModel.signOut(context)
        assertFalse(viewModel.state.value.bannergressSignedIn)
        assertNull(viewModel.state.value.bannergress)
    }

    @Test
    fun theDayOfADoneBannerIsSavedAndPutsItOnTheTimeline() {
        api.serverDoneBanners += DoneBanner(1, "b-1", "Banner", 6)
        val viewModel = signedIn()
        assertEquals(listOf(DoneBanner(1, "b-1", "Banner", 6)), viewModel.state.value.doneBanners)

        viewModel.saveDoneAt(context, DoneBanner(1, "b-1", "Banner", 6), "2026-10-18")

        assertEquals("2026-10-18", viewModel.state.value.doneBanners?.single()?.doneAt)
        assertEquals(Status(uiText(R.string.bannergress_date_saved), false), viewModel.state.value.status)

        viewModel.saveDoneAt(context, viewModel.state.value.doneBanners!!.single(), null)
        assertNull(viewModel.state.value.doneBanners?.single()?.doneAt)
    }

    @Test
    fun anEndedBannergressSessionIsReported() {
        val bannergress = FakeBannergress().apply { result = BannergressResult.SignedOut }
        auth.interactiveSession = session
        api.lists += AgentStatsResult.Success(listOf(entry))
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, bannergress = bannergress).also { it.signIn(context) }

        viewModel.syncBannergress(context)

        assertEquals(Status(uiText(R.string.bannergress_session_ended), true), viewModel.state.value.status)
        assertTrue(api.savedBannergress.isEmpty())
    }

    @Test
    fun aRefusedBannergressSignInIsReported() {
        val bannergress = FakeBannergress()
        val viewModel = MainViewModel(auth, api, scanMedals = { emptyList() }, bannergress = bannergress)

        viewModel.startBannergressLogin()
        viewModel.finishBannergressLogin(context, "bad")

        assertEquals(Status(uiText(R.string.bannergress_sign_in_failed), true), viewModel.state.value.status)
        assertTrue(bannergress.syncedAgents.isEmpty())
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

    private class FakeBannergress : BannergressConnector {
        override val signedIn = MutableStateFlow(false)

        /** What a sync reads from "Bannergress". */
        var result: BannergressResult = BannergressResult.Success(
            BannergressSync(published = 1, todo = 2, done = 1, doneBanners = listOf(BannergressBanner("b-1", "Banner", 6))),
        )
        val syncedAgents = mutableListOf<String>()

        override fun start() = BannergressLogin("https://login.bannergress.com/auth", "verifier", "state")

        override suspend fun finish(login: BannergressLogin, code: String): Boolean {
            signedIn.value = code == "good"
            return signedIn.value
        }

        override suspend fun sync(agentName: String): BannergressResult {
            syncedAgents += agentName
            return result
        }

        override fun signOut() {
            signedIn.value = false
        }
    }

    private class FakeStatsApi : StatsApi {
        val saves = ArrayDeque<SaveResult>()
        val lists = ArrayDeque<AgentStatsResult>()
        val saved = mutableListOf<Pair<String, JsonObject>>()
        val listTokens = mutableListOf<String>()

        /** What `GET /api/me` answers; null for an agent "TestAgent" with the [serverMedals]. */
        var me: MeResult? = null

        /** Loads wait for it, to see what shows meanwhile and that they run at the same time. */
        var loadGate: CompletableDeferred<Unit>? = null

        /** Loads with this token answer "unauthorized". */
        var expiredToken: String? = null

        /** Waits for the [loadGate]; false with the [expiredToken]. */
        private suspend fun authorized(idToken: String): Boolean {
            loadGate?.await()
            return idToken != expiredToken
        }

        override suspend fun me(idToken: String): MeResult {
            if (!authorized(idToken)) return MeResult.Unauthorized(UiText.Plain("Expired."))
            return me ?: MeResult.Success(UserRole.USER, "TestAgent", serverMedals)
        }

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
            if (!authorized(idToken)) return AgentStatsResult.Unauthorized(UiText.Plain("Expired."))
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

        override suspend fun timelineEvents(idToken: String): TimelineEventsResult {
            if (!authorized(idToken)) return TimelineEventsResult.Unauthorized(UiText.Plain("Expired."))
            return TimelineEventsResult.Success(serverEvents.toList())
        }

        override suspend fun saveTimelineEvent(idToken: String, event: TimelineEvent): TimelineEventResult {
            eventFailure?.let { return it }
            val saved = event.copy(id = event.id ?: ((serverEvents.mapNotNull { it.id }.maxOrNull() ?: 0) + 1))
            serverEvents.removeAll { it.id == saved.id }
            serverEvents += saved
            return TimelineEventResult.Success(saved)
        }

        val savedBannergress = mutableListOf<BannergressSync>()

        /** The done list on the "server". */
        val serverDoneBanners = mutableListOf<DoneBanner>()

        override suspend fun doneBanners(idToken: String): DoneBannersResult {
            if (!authorized(idToken)) return DoneBannersResult.Unauthorized(UiText.Plain("Expired."))
            return DoneBannersResult.Success(serverDoneBanners.toList())
        }

        override suspend fun saveDoneAt(idToken: String, id: Int, doneAt: String?): DoneBannerResult {
            val saved = serverDoneBanners.single { it.id == id }.copy(doneAt = doneAt)
            serverDoneBanners.replaceAll { if (it.id == id) saved else it }
            return DoneBannerResult.Success(saved)
        }

        override suspend fun saveBannergress(idToken: String, sync: BannergressSync): BannergressSaveResult {
            savedBannergress += sync
            serverDoneBanners.clear()
            serverDoneBanners += sync.doneBanners.mapIndexed { index, banner -> DoneBanner(index + 1, banner.id, banner.title, banner.missions, banner.picture) }
            return BannergressSaveResult.Success(sync.copy(syncedAt = "2026-10-27T18:00:00+00:00"))
        }

        override suspend fun deleteTimelineEvent(idToken: String, id: Int): TimelineEventResult {
            eventFailure?.let { return it }
            serverEvents.removeAll { it.id == id }
            return TimelineEventResult.Success(null)
        }

        /** What `GET /api/links` answers. */
        val historyResults = mutableMapOf<String, HistoryResult>()

        override suspend fun history(slug: String): HistoryResult = historyResults[slug] ?: HistoryResult.Success(HistoryDocument(slug))

        var events: IngressEventListResult = IngressEventListResult.Success(IngressEventList())

        override suspend fun events(): IngressEventListResult = events

        var links: LinkListResult = LinkListResult.Success(emptyList())
        var linksRequests = 0

        override suspend fun links(): LinkListResult {
            linksRequests++
            loadGate?.await()
            return links
        }
    }

    private class FakeLinkListCache(var sections: List<LinkSection>? = null) : LinkListCache {
        override fun read() = sections

        override fun write(sections: List<LinkSection>) {
            this.sections = sections
        }
    }
}
