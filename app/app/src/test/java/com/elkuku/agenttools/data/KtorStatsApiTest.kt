package com.elkuku.agenttools.data

import com.elkuku.agenttools.R
import com.elkuku.agenttools.UiText
import com.elkuku.agenttools.uiText
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.engine.mock.toByteArray
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpMethod
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Test
import java.io.IOException

class KtorStatsApiTest {
    private val stats = JsonObject(mapOf("nickname" to JsonPrimitive("TestAgent"), "ap" to JsonPrimitive(1)))

    private val jsonHeaders = headersOf(HttpHeaders.ContentType, "application/ld+json")

    private val problemHeaders = headersOf(HttpHeaders.ContentType, "application/problem+json")

    private fun problem(status: Int, detail: String) =
        """{"type": "/errors/$status", "title": "An error occurred", "status": $status, "detail": "$detail"}"""

    @Test
    fun meLoadsTheRole() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Get, req.method)
            assertEquals("http://server/api/me", req.url.toString())
            assertEquals("Bearer t", req.headers[HttpHeaders.Authorization])
            respond(
                """{"@context": "/api/contexts/Me", "@id": "/api/me", "@type": "Me",
                    "id": 3, "identifier": "jane@example.com", "role": "ROLE_USER"}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(MeResult.Success(UserRole.USER, null), KtorStatsApi("http://server", engine).me("t"))
    }

    @Test
    fun meLoadsWhetherTheVisibilityWasChosen() = runTest {
        val engine = MockEngine { respond("""{"role": "ROLE_USER", "visibilityChosen": false}""", HttpStatusCode.OK, jsonHeaders) }

        assertEquals(MeResult.Success(UserRole.USER, visibilityChosen = false), KtorStatsApi("http://server", engine).me("t"))
    }

    @Test
    fun meLoadsTheAgentName() = runTest {
        val engine = MockEngine { respond("""{"role": "ROLE_USER", "agentName": "TestAgent"}""", HttpStatusCode.OK, jsonHeaders) }

        assertEquals(MeResult.Success(UserRole.USER, "TestAgent"), KtorStatsApi("http://server", engine).me("t"))
    }

    @Test
    fun agentsAreListed() = runTest {
        val engine = MockEngine { req ->
            assertEquals("http://server/api/agents", req.url.toString())
            respond(
                """{"@id": "/api/agents", "member": [{"@id": "/api/agents/TestAgent", "agentName": "TestAgent",
                    "faction": "Enlightened", "level": 16, "avatar": "data:image/jpeg;base64,abc"}]}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(
            AgentsResult.Success(listOf(AgentSummary("TestAgent", "Enlightened", 16, "data:image/jpeg;base64,abc"))),
            KtorStatsApi("http://server", engine).agents("t"),
        )
    }

    @Test
    fun anAgentHasTheVisibleParts() = runTest {
        val engine = MockEngine { req ->
            assertEquals("http://server/api/agents/TestAgent", req.url.toString())
            respond(
                """{"agentName": "TestAgent", "faction": "Enlightened", "level": 16,
                    "latestStats": {"datetime": "2026-10-20 15:35:56", "nickname": "TestAgent", "faction": "Enlightened", "ap": 5, "level": 16},
                    "customMedals": {"anomaly": {"orion": "platinum"}, "annual": [], "event": []}}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        val result = KtorStatsApi("http://server", engine).agent("t", "TestAgent")

        val profile = (result as AgentProfileResult.Success).profile
        assertEquals(5L, profile.latest?.get("ap"))
        assertEquals(null, profile.history)
        assertEquals(listOf("orion"), profile.customMedals?.map { it.medal.key })
    }

    @Test
    fun anAgentHasTheTimelineEventsWithTheHistory() = runTest {
        val engine = MockEngine {
            respond(
                """{"agentName": "TestAgent", "history": [{"datetime": "2026-10-20 15:35:56", "nickname": "TestAgent", "faction": "Enlightened", "ap": 5}],
                    "events": [{"date": "2026-10-25", "endDate": null, "text": "Mission day"}, {"date": "2026-09-20", "endDate": "2026-10-02", "text": "Travel"}]}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        val profile = (KtorStatsApi("http://server", engine).agent("t", "TestAgent") as AgentProfileResult.Success).profile

        assertEquals(1, profile.history?.size)
        assertEquals(
            listOf(TimelineEvent(null, "2026-10-25", "Mission day"), TimelineEvent(null, "2026-09-20", "Travel", endDate = "2026-10-02")),
            profile.events,
        )
    }

    @Test
    fun timelineEventsAreListed() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Get, req.method)
            assertEquals("http://server/api/timeline-events", req.url.toString())
            respond(
                """{"member": [{"@id": "/api/timeline-events/2", "id": 2, "date": "2026-10-25", "text": "Mission day"}]}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(
            TimelineEventsResult.Success(listOf(TimelineEvent(2, "2026-10-25", "Mission day"))),
            KtorStatsApi("http://server", engine).timelineEvents("t"),
        )
    }

    @Test
    fun aNewTimelineEventIsPostedAndAChangedOnePatched() = runTest {
        val requests = mutableListOf<Triple<HttpMethod, String, String>>()
        val engine = MockEngine { req ->
            requests += Triple(req.method, req.url.toString(), String(req.body.toByteArray()))
            val status = if (req.method == HttpMethod.Post) HttpStatusCode.Created else HttpStatusCode.OK
            respond("""{"id": 3, "date": "2026-10-25", "text": "Mission day"}""", status, jsonHeaders)
        }
        val api = KtorStatsApi("http://server", engine)

        val event = TimelineEvent(null, "2026-10-25", "Mission day")
        assertEquals(TimelineEventResult.Success(event.copy(id = 3)), api.saveTimelineEvent("t", event))
        assertEquals(TimelineEventResult.Success(event.copy(id = 3)), api.saveTimelineEvent("t", event.copy(id = 3)))

        assertEquals(HttpMethod.Post, requests[0].first)
        assertEquals("http://server/api/timeline-events", requests[0].second)
        assertEquals(mapOf("date" to "2026-10-25", "endDate" to null, "text" to "Mission day"), Json.decodeFromString<Map<String, String?>>(requests[0].third))
        assertEquals(HttpMethod.Patch, requests[1].first)
        assertEquals("http://server/api/timeline-events/3", requests[1].second)
    }

    @Test
    fun timelineEventErrorsAreMapped() = runTest {
        val invalid = MockEngine {
            respond(
                """{"violations": [{"propertyPath": "text", "message": "This value should not be blank."}]}""",
                HttpStatusCode.UnprocessableEntity,
                problemHeaders,
            )
        }
        val deleted = MockEngine { req ->
            assertEquals(HttpMethod.Delete, req.method)
            assertEquals("http://server/api/timeline-events/3", req.url.toString())
            respond("", HttpStatusCode.NoContent)
        }
        val forbidden = MockEngine { respond(problem(403, "Access Denied."), HttpStatusCode.Forbidden, problemHeaders) }

        assertEquals(
            TimelineEventResult.Failure(UiText.Plain("text: This value should not be blank.")),
            KtorStatsApi("http://server", invalid).saveTimelineEvent("t", TimelineEvent(null, "2026-10-25", " ")),
        )
        assertEquals(TimelineEventResult.Success(null), KtorStatsApi("http://server", deleted).deleteTimelineEvent("t", 3))
        assertEquals(
            TimelineEventResult.Failure(UiText.Plain("Access Denied.")),
            KtorStatsApi("http://server", forbidden).deleteTimelineEvent("t", 3),
        )
    }

    @Test
    fun usersHaveThePrivacySettings() = runTest {
        val engine = MockEngine { req ->
            assertEquals("http://server/api/users", req.url.toString())
            respond(
                """{"member": [{"@id": "/api/users/6", "id": 6, "identifier": "jane@example.com", "role": "ROLE_USER",
                    "agentName": "TestAgent", "faction": "Resistance", "level": 9, "avatar": null,
                    "visibleParts": ["agent", "custom_medals"], "visibilityChosen": true},
                    {"id": 7, "identifier": "new@example.com", "role": "ROLE_USER", "agentName": null,
                    "visibleParts": [], "visibilityChosen": false}]}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(
            UsersResult.Success(
                listOf(
                    UserPrivacy("jane@example.com", UserRole.USER, "TestAgent", "Resistance", 9, null, setOf(ProfilePart.AGENT, ProfilePart.CUSTOM_MEDALS), true),
                    UserPrivacy("new@example.com", UserRole.USER, null),
                ),
            ),
            KtorStatsApi("http://server", engine).users("t"),
        )
    }

    @Test
    fun aPrivateAgentIsNotFound() = runTest {
        val engine = MockEngine { respond(problem(404, "Not Found"), HttpStatusCode.NotFound, problemHeaders) }

        assertEquals(
            AgentProfileResult.Failure(uiText(R.string.error_agent_private, "PrivateAgent")),
            KtorStatsApi("http://server", engine).agent("t", "PrivateAgent"),
        )
    }

    @Test
    fun meLoadsTheAvatar() = runTest {
        val engine = MockEngine { respond("""{"role": "ROLE_USER", "avatar": "data:image/jpeg;base64,abc"}""", HttpStatusCode.OK, jsonHeaders) }

        assertEquals(
            MeResult.Success(UserRole.USER, avatar = "data:image/jpeg;base64,abc"),
            KtorStatsApi("http://server", engine).me("t"),
        )
    }

    @Test
    fun saveBannergressPatchesMe() = runTest {
        val sync = BannergressSync(published = 2, todo = 5, done = 137, doneBanners = listOf(BannergressBanner("b-1", "Banner", 6, "/bnrs/pictures/p1")))
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Patch, req.method)
            assertEquals("application/merge-patch+json", req.body.contentType.toString())
            val body = Json.parseToJsonElement(String(req.body.toByteArray())) as JsonObject
            assertEquals(Json.parseToJsonElement("""{"published": 2, "todo": 5, "done": 137, "doneBanners": [{"id": "b-1", "title": "Banner", "missions": 6, "picture": "/bnrs/pictures/p1"}]}"""), body["bannergress"])
            // The done list is not sent back (doneBannersLoadsTheList)
            respond(
                """{"role": "ROLE_USER", "bannergress": {"published": 2, "todo": 5, "done": 137, "todoBanners": [], "syncedAt": "2026-10-27T18:00:00+00:00"}}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(
            BannergressSaveResult.Success(sync.copy(doneBanners = emptyList(), syncedAt = "2026-10-27T18:00:00+00:00")),
            KtorStatsApi("http://server", engine).saveBannergress("t", sync),
        )
    }

    @Test
    fun doneBannersLoadsTheList() = runTest {
        val engine = MockEngine { req ->
            assertEquals("http://server/api/done-banners", req.url.toString())
            // Null days are left out
            respond(
                """{"member": [{"id": 7, "bannerId": "b-1", "title": "Banner", "missions": 6, "picture": "/bnrs/pictures/p1"},
                    {"id": 8, "bannerId": "b-2", "title": "Other", "missions": 12, "doneAt": "2026-10-18"}]}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(
            DoneBannersResult.Success(
                listOf(DoneBanner(7, "b-1", "Banner", 6, "/bnrs/pictures/p1"), DoneBanner(8, "b-2", "Other", 12, doneAt = "2026-10-18")),
            ),
            KtorStatsApi("http://server", engine).doneBanners("t"),
        )
    }

    @Test
    fun saveDoneAtPatchesTheBannerAndSendsNullToRemoveTheDay() = runTest {
        val bodies = mutableListOf<String>()
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Patch, req.method)
            assertEquals("http://server/api/done-banners/7", req.url.toString())
            bodies += String(req.body.toByteArray())
            respond("""{"id": 7, "bannerId": "b-1", "title": "Banner", "missions": 6}""", HttpStatusCode.OK, jsonHeaders)
        }
        val api = KtorStatsApi("http://server", engine)

        assertEquals(DoneBannerResult.Success(DoneBanner(7, "b-1", "Banner", 6)), api.saveDoneAt("t", 7, "2026-10-18"))
        api.saveDoneAt("t", 7, null)

        assertEquals(listOf("""{"doneAt":"2026-10-18"}""", """{"doneAt":null}"""), bodies)
    }

    @Test
    fun meLoadsTheVisibleParts() = runTest {
        val engine = MockEngine {
            respond("""{"role": "ROLE_USER", "visibleParts": ["agent", "custom_medals", "future_part"]}""", HttpStatusCode.OK, jsonHeaders)
        }

        assertEquals(
            MeResult.Success(UserRole.USER, visibleParts = setOf(ProfilePart.AGENT, ProfilePart.CUSTOM_MEDALS)),
            KtorStatsApi("http://server", engine).me("t"),
        )
    }

    @Test
    fun saveVisiblePartsPatchesMe() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Patch, req.method)
            assertEquals("http://server/api/me", req.url.toString())
            assertEquals("application/merge-patch+json", req.body.contentType.toString())
            assertEquals("Bearer t", req.headers[HttpHeaders.Authorization])
            assertEquals("""{"visibleParts":["agent","latest_stats"]}""", String(req.body.toByteArray()))
            respond(
                """{"@id": "/api/me", "role": "ROLE_USER", "visibleParts": ["agent", "latest_stats"], "visibilityChosen": true}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(
            VisibilityResult.Success(setOf(ProfilePart.AGENT, ProfilePart.LATEST_STATS)),
            KtorStatsApi("http://server", engine).saveVisibleParts("t", linkedSetOf(ProfilePart.AGENT, ProfilePart.LATEST_STATS)),
        )
    }

    @Test
    fun saveVisiblePartsShowsWhyTheServerRejectedThem() = runTest {
        val engine = MockEngine {
            respond(
                """{"status": 422, "violations": [{"propertyPath": "visibleParts", "message": "The value is not a valid choice."}]}""",
                HttpStatusCode.UnprocessableEntity,
                problemHeaders,
            )
        }

        assertEquals(
            VisibilityResult.Failure(UiText.Plain("The value is not a valid choice.")),
            KtorStatsApi("http://server", engine).saveVisibleParts("t", setOf(ProfilePart.AGENT)),
        )
    }

    @Test
    fun saveShowsTheProblemDetailWithoutViolations() = runTest {
        val engine = MockEngine {
            respond(
                problem(422, "These stats are for agent SomeoneElse, your agent name is TestAgent."),
                HttpStatusCode.UnprocessableEntity,
                problemHeaders,
            )
        }

        assertEquals(
            SaveResult.Invalid(listOf(UiText.Plain("These stats are for agent SomeoneElse, your agent name is TestAgent."))),
            KtorStatsApi("http://server", engine).save("t", JsonObject(emptyMap())),
        )
    }

    @Test
    fun meMapsUnknownRolesUnauthorizedAndNetworkErrors() = runTest {
        val unknown = MockEngine { respond("""{"role": "ROLE_SUPER"}""", HttpStatusCode.OK, jsonHeaders) }
        val unauthorized = MockEngine { respond(problem(401, "Invalid ID token."), HttpStatusCode.Unauthorized, problemHeaders) }
        val offline = MockEngine { throw IOException("timeout") }

        assertEquals(MeResult.Success(null), KtorStatsApi("http://server", unknown).me("t"))
        assertEquals(MeResult.Unauthorized(UiText.Plain("Invalid ID token.")), KtorStatsApi("http://server", unauthorized).me("t"))
        assertEquals(MeResult.Failure(uiText(R.string.error_network, "timeout")), KtorStatsApi("http://server", offline).me("t"))
    }

    @Test
    fun savePostsStatsWithBearerToken() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Post, req.method)
            assertEquals("http://server/api/agent-stats", req.url.toString())
            assertEquals("Bearer id-token", req.headers[HttpHeaders.Authorization])
            assertEquals("application/ld+json", req.headers[HttpHeaders.Accept])
            assertEquals("application/ld+json", req.body.contentType?.toString())
            assertEquals(stats, Json.parseToJsonElement(String(req.body.toByteArray())))

            respond(
                """{"@context": "/api/contexts/AgentStat", "@id": "/api/agent-stats/9", "@type": "AgentStat",
                    "id": 9, "createdAt": "2026-10-19T12:00:00+00:00", "ap": 1}""",
                HttpStatusCode.Created,
                jsonHeaders,
            )
        }

        val result = KtorStatsApi("http://server/", engine).save("id-token", stats)

        assertEquals(SaveResult.Success(9, "2026-10-19T12:00:00+00:00"), result)
    }

    @Test
    fun saveMapsUnauthorized() = runTest {
        val engine = MockEngine { respond(problem(401, "Unknown user."), HttpStatusCode.Unauthorized, problemHeaders) }

        assertEquals(SaveResult.Unauthorized(UiText.Plain("Unknown user.")), KtorStatsApi("http://server", engine).save("t", stats))
    }

    @Test
    fun saveShowsWhyItIsForbidden() = runTest {
        val detail = "Access Denied."
        val engine = MockEngine { respond(problem(403, detail), HttpStatusCode.Forbidden, problemHeaders) }

        assertEquals(SaveResult.Failure(UiText.Plain(detail)), KtorStatsApi("http://server", engine).save("t", stats))
    }

    @Test
    fun saveMapsValidationViolations() = runTest {
        val body = """
            {"@type": "ConstraintViolation", "status": 422, "detail": "ap: This value should not be null.", "violations": [
                {"propertyPath": "ap", "message": "This value should not be null.", "code": "c1"},
                {"propertyPath": "", "message": "Something general.", "code": "c2"}
            ]}
        """.trimIndent()
        val engine = MockEngine { respond(body, HttpStatusCode.UnprocessableEntity, problemHeaders) }

        assertEquals(
            SaveResult.Invalid(listOf(UiText.Plain("ap: This value should not be null."), UiText.Plain("Something general."))),
            KtorStatsApi("http://server", engine).save("t", stats),
        )
    }

    @Test
    fun saveMapsConflictToAlreadySaved() = runTest {
        val engine = MockEngine {
            respond(problem(409, "Stat entry already added!"), HttpStatusCode.Conflict, problemHeaders)
        }

        assertEquals(SaveResult.Failure(uiText(R.string.error_stats_already_saved)), KtorStatsApi("http://server", engine).save("t", stats))
    }

    @Test
    fun saveMapsServerError() = runTest {
        val engine = MockEngine { respond("oops", HttpStatusCode.InternalServerError) }

        assertEquals(SaveResult.Failure(uiText(R.string.error_server, 500)), KtorStatsApi("http://server", engine).save("t", stats))
    }

    @Test
    fun saveMapsNetworkError() = runTest {
        val engine = MockEngine { throw IOException("connection refused") }

        assertEquals(
            SaveResult.Failure(uiText(R.string.error_network, "connection refused")),
            KtorStatsApi("http://server", engine).save("t", stats),
        )
    }

    @Test
    fun listLoadsAgentStats() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Get, req.method)
            assertEquals("http://server/api/agent-stats", req.url.toString())
            assertEquals("Bearer id-token", req.headers[HttpHeaders.Authorization])

            respond(
                """{"@context": "/api/contexts/AgentStat", "@id": "/api/agent-stats", "@type": "Collection",
                    "totalItems": 1, "member": [{"@id": "/api/agent-stats/2", "@type": "AgentStat", "id": 2,
                    "datetime": "2026-10-19 15:35:56", "createdAt": "2026-10-19T15:40:00+00:00",
                    "nickname": "TestAgent", "faction": "Enlightened", "ap": 243517130, "level": 16, "recon": null}]}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        val result = KtorStatsApi("http://server", engine).list("id-token")

        assertEquals(
            AgentStatsResult.Success(
                listOf(
                    AgentStatEntry(
                        id = 2,
                        datetime = "2026-10-19 15:35:56",
                        nickname = "TestAgent",
                        faction = "Enlightened",
                        stats = mapOf("ap" to 243517130L, "level" to 16L, "recon" to null),
                    ),
                ),
            ),
            result,
        )
    }

    @Test
    fun listFollowsNextPages() = runTest {
        val requested = mutableListOf<String>()
        val engine = MockEngine { req ->
            requested += req.url.toString()
            val (ids, view) = when (req.url.parameters["page"]) {
                null -> listOf(3, 2) to """{"@id": "/sub/api/agent-stats?page=1", "next": "/sub/api/agent-stats?page=2"}"""
                else -> listOf(1) to """{"@id": "/sub/api/agent-stats?page=2", "previous": "/sub/api/agent-stats?page=1"}"""
            }
            val member = ids.joinToString { """{"id": $it, "datetime": "2026-10-0$it 10:00:00", "ap": $it}""" }

            respond("""{"totalItems": 3, "member": [$member], "view": $view}""", HttpStatusCode.OK, jsonHeaders)
        }

        val result = KtorStatsApi("https://server/sub/", engine).list("t")

        assertEquals(listOf("https://server/sub/api/agent-stats", "https://server/sub/api/agent-stats?page=2"), requested)
        assertEquals(listOf(3, 2, 1), (result as AgentStatsResult.Success).entries.map { it.id })
    }

    @Test
    fun listMapsUnauthorized() = runTest {
        val engine = MockEngine { respond(problem(401, "Invalid ID token."), HttpStatusCode.Unauthorized, problemHeaders) }

        assertEquals(AgentStatsResult.Unauthorized(UiText.Plain("Invalid ID token.")), KtorStatsApi("http://server", engine).list("t"))
    }

    @Test
    fun listShowsWhyItIsForbidden() = runTest {
        val detail = "Access Denied."
        val engine = MockEngine { respond(problem(403, detail), HttpStatusCode.Forbidden, problemHeaders) }

        assertEquals(AgentStatsResult.Failure(UiText.Plain(detail)), KtorStatsApi("http://server", engine).list("t"))
    }

    @Test
    fun listMapsNetworkError() = runTest {
        val engine = MockEngine { throw IOException("timeout") }

        assertEquals(AgentStatsResult.Failure(uiText(R.string.error_network, "timeout")), KtorStatsApi("http://server", engine).list("t"))
    }

    private val orion = customMedal("anomaly", "orion")!!

    private val voyager = customMedal("annual", "voyager")!!

    @Test
    fun meLoadsTheCustomMedals() = runTest {
        val engine = MockEngine {
            respond(
                """{"@context": "/api/contexts/Me", "@id": "/api/me", "@type": "Me", "role": "ROLE_USER", "agentName": "TestAgent",
                    "customMedals": {"anomaly": {"orion": "platinum"}, "annual": {"voyager": 5}, "event": []}}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(
            MeResult.Success(UserRole.USER, "TestAgent", listOf(CustomMedalTier(orion, "platinum"), CustomMedalTier(voyager, "black"))),
            KtorStatsApi("http://server", engine).me("t"),
        )
    }

    @Test
    fun saveCustomMedalsPatchesMeWithAllGroups() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Patch, req.method)
            assertEquals("http://server/api/me", req.url.toString())
            assertEquals("application/merge-patch+json", req.body.contentType?.toString())
            assertEquals(
                Json.parseToJsonElement("""{"customMedals": {"anomaly": {"orion": "gold"}, "annual": {"voyager": 2}, "event": {}}}"""),
                Json.parseToJsonElement(String(req.body.toByteArray())),
            )
            respond(
                """{"@id": "/api/me", "role": "ROLE_USER", "agentName": null,
                    "customMedals": {"anomaly": {"orion": "gold"}, "annual": {"voyager": 2}, "event": []}}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }
        val medals = listOf(CustomMedalTier(orion, "gold"), CustomMedalTier(voyager, "silver"))

        assertEquals(CustomMedalsResult.Success(medals), KtorStatsApi("http://server", engine).saveCustomMedals("t", medals))
    }

    @Test
    fun saveCustomMedalsShowsWhyItIsForbidden() = runTest {
        val detail = "Access Denied."
        val engine = MockEngine { respond(problem(403, detail), HttpStatusCode.Forbidden, problemHeaders) }

        assertEquals(CustomMedalsResult.Failure(UiText.Plain(detail)), KtorStatsApi("http://server", engine).saveCustomMedals("t", emptyList()))
    }

    @Test
    fun saveCustomMedalsMapsViolations() = runTest {
        val body = """{"status": 422, "detail": "customMedals.annual[voyager]: The level must be 1 to 5.",
            "violations": [{"propertyPath": "customMedals.annual[voyager]", "message": "The level must be 1 to 5."}]}"""
        val engine = MockEngine { respond(body, HttpStatusCode.UnprocessableEntity, problemHeaders) }

        assertEquals(
            CustomMedalsResult.Failure(UiText.Plain("customMedals.annual[voyager]: The level must be 1 to 5.")),
            KtorStatsApi("http://server", engine).saveCustomMedals("t", emptyList()),
        )
    }

    @Test
    fun saveCustomMedalsMapsUnauthorizedAndNetworkErrors() = runTest {
        val unauthorized = MockEngine { respond(problem(401, "Invalid ID token."), HttpStatusCode.Unauthorized, problemHeaders) }
        val offline = MockEngine { throw IOException("timeout") }

        assertEquals(
            CustomMedalsResult.Unauthorized(UiText.Plain("Invalid ID token.")),
            KtorStatsApi("http://server", unauthorized).saveCustomMedals("t", emptyList()),
        )
        assertEquals(
            CustomMedalsResult.Failure(uiText(R.string.error_network, "timeout")),
            KtorStatsApi("http://server", offline).saveCustomMedals("t", emptyList()),
        )
    }
}
