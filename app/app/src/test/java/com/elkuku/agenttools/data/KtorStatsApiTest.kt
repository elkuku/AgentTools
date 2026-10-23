package com.elkuku.agenttools.data

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
                    "id": 3, "identifier": "jane@example.com", "role": "ROLE_AGENT"}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(MeResult.Success(UserRole.AGENT, null), KtorStatsApi("http://server", engine).me("t"))
    }

    @Test
    fun meLoadsTheAgentName() = runTest {
        val engine = MockEngine { respond("""{"role": "ROLE_AGENT", "agentName": "TestAgent"}""", HttpStatusCode.OK, jsonHeaders) }

        assertEquals(MeResult.Success(UserRole.AGENT, "TestAgent"), KtorStatsApi("http://server", engine).me("t"))
    }

    @Test
    fun saveAgentNamePatchesMe() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Patch, req.method)
            assertEquals("http://server/api/me", req.url.toString())
            assertEquals("application/merge-patch+json", req.body.contentType.toString())
            assertEquals("Bearer t", req.headers[HttpHeaders.Authorization])
            assertEquals("""{"agentName":"TestAgent"}""", String(req.body.toByteArray()))
            respond("""{"@id": "/api/me", "role": "ROLE_AGENT", "agentName": "TestAgent"}""", HttpStatusCode.OK, jsonHeaders)
        }

        assertEquals(AgentNameResult.Success("TestAgent"), KtorStatsApi("http://server", engine).saveAgentName("t", "TestAgent"))
    }

    @Test
    fun saveAgentNameShowsWhyTheServerRejectedIt() = runTest {
        val engine = MockEngine {
            respond(
                """{"status": 422, "violations": [{"propertyPath": "agentName", "message": "This agent name is already taken."}]}""",
                HttpStatusCode.UnprocessableEntity,
                problemHeaders,
            )
        }

        assertEquals(
            AgentNameResult.Failure("This agent name is already taken."),
            KtorStatsApi("http://server", engine).saveAgentName("t", "TestAgent"),
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
            SaveResult.Invalid(listOf("These stats are for agent SomeoneElse, your agent name is TestAgent.")),
            KtorStatsApi("http://server", engine).save("t", JsonObject(emptyMap())),
        )
    }

    @Test
    fun meMapsUnknownRolesUnauthorizedAndNetworkErrors() = runTest {
        val unknown = MockEngine { respond("""{"role": "ROLE_SUPER"}""", HttpStatusCode.OK, jsonHeaders) }
        val unauthorized = MockEngine { respond(problem(401, "Invalid ID token."), HttpStatusCode.Unauthorized, problemHeaders) }
        val offline = MockEngine { throw IOException("timeout") }

        assertEquals(MeResult.Success(null), KtorStatsApi("http://server", unknown).me("t"))
        assertEquals(MeResult.Unauthorized("Invalid ID token."), KtorStatsApi("http://server", unauthorized).me("t"))
        assertEquals(MeResult.Failure("Network error: timeout"), KtorStatsApi("http://server", offline).me("t"))
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

        assertEquals(SaveResult.Unauthorized("Unknown user."), KtorStatsApi("http://server", engine).save("t", stats))
    }

    @Test
    fun saveMapsForbidden() = runTest {
        val detail = "Agent stats need the Agent role. Ask an administrator."
        val engine = MockEngine { respond(problem(403, detail), HttpStatusCode.Forbidden, problemHeaders) }

        assertEquals(SaveResult.Forbidden(detail), KtorStatsApi("http://server", engine).save("t", stats))
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
            SaveResult.Invalid(listOf("ap: This value should not be null.", "Something general.")),
            KtorStatsApi("http://server", engine).save("t", stats),
        )
    }

    @Test
    fun saveMapsConflictToServerMessage() = runTest {
        val engine = MockEngine {
            respond(problem(409, "Stat entry already added!"), HttpStatusCode.Conflict, problemHeaders)
        }

        assertEquals(SaveResult.Failure("Stat entry already added!"), KtorStatsApi("http://server", engine).save("t", stats))
    }

    @Test
    fun saveMapsServerError() = runTest {
        val engine = MockEngine { respond("oops", HttpStatusCode.InternalServerError) }

        assertEquals(SaveResult.Failure("Server error: HTTP 500"), KtorStatsApi("http://server", engine).save("t", stats))
    }

    @Test
    fun saveMapsNetworkError() = runTest {
        val engine = MockEngine { throw IOException("connection refused") }

        assertEquals(
            SaveResult.Failure("Network error: connection refused"),
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

        assertEquals(AgentStatsResult.Unauthorized("Invalid ID token."), KtorStatsApi("http://server", engine).list("t"))
    }

    @Test
    fun listShowsWhyItIsForbidden() = runTest {
        val detail = "Agent stats need the Agent role. Ask an administrator."
        val engine = MockEngine { respond(problem(403, detail), HttpStatusCode.Forbidden, problemHeaders) }

        assertEquals(AgentStatsResult.Forbidden(detail), KtorStatsApi("http://server", engine).list("t"))
    }

    @Test
    fun listMapsNetworkError() = runTest {
        val engine = MockEngine { throw IOException("timeout") }

        assertEquals(AgentStatsResult.Failure("Network error: timeout"), KtorStatsApi("http://server", engine).list("t"))
    }

    private val orion = customMedal("anomaly", "orion")!!

    private val voyager = customMedal("annual", "voyager")!!

    @Test
    fun meLoadsTheCustomMedals() = runTest {
        val engine = MockEngine {
            respond(
                """{"@context": "/api/contexts/Me", "@id": "/api/me", "@type": "Me", "role": "ROLE_AGENT", "agentName": "TestAgent",
                    "customMedals": {"anomaly": {"orion": "platinum"}, "annual": {"voyager": 5}, "event": []}}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        assertEquals(
            MeResult.Success(UserRole.AGENT, "TestAgent", listOf(CustomMedalTier(orion, "platinum"), CustomMedalTier(voyager, "black"))),
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
                """{"@id": "/api/me", "role": "ROLE_AGENT", "agentName": null,
                    "customMedals": {"anomaly": {"orion": "gold"}, "annual": {"voyager": 2}, "event": []}}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }
        val medals = listOf(CustomMedalTier(orion, "gold"), CustomMedalTier(voyager, "silver"))

        assertEquals(CustomMedalsResult.Success(medals), KtorStatsApi("http://server", engine).saveCustomMedals("t", medals))
    }

    @Test
    fun saveCustomMedalsMapsForbidden() = runTest {
        val detail = "An agent name and custom medals need the Agent role. Ask an administrator."
        val engine = MockEngine { respond(problem(403, detail), HttpStatusCode.Forbidden, problemHeaders) }

        assertEquals(CustomMedalsResult.Forbidden(detail), KtorStatsApi("http://server", engine).saveCustomMedals("t", emptyList()))
    }

    @Test
    fun saveCustomMedalsMapsViolations() = runTest {
        val body = """{"status": 422, "detail": "customMedals.annual[voyager]: The level must be 1 to 5.",
            "violations": [{"propertyPath": "customMedals.annual[voyager]", "message": "The level must be 1 to 5."}]}"""
        val engine = MockEngine { respond(body, HttpStatusCode.UnprocessableEntity, problemHeaders) }

        assertEquals(
            CustomMedalsResult.Failure("customMedals.annual[voyager]: The level must be 1 to 5."),
            KtorStatsApi("http://server", engine).saveCustomMedals("t", emptyList()),
        )
    }

    @Test
    fun saveCustomMedalsMapsUnauthorizedAndNetworkErrors() = runTest {
        val unauthorized = MockEngine { respond(problem(401, "Invalid ID token."), HttpStatusCode.Unauthorized, problemHeaders) }
        val offline = MockEngine { throw IOException("timeout") }

        assertEquals(
            CustomMedalsResult.Unauthorized("Invalid ID token."),
            KtorStatsApi("http://server", unauthorized).saveCustomMedals("t", emptyList()),
        )
        assertEquals(
            CustomMedalsResult.Failure("Network error: timeout"),
            KtorStatsApi("http://server", offline).saveCustomMedals("t", emptyList()),
        )
    }
}
