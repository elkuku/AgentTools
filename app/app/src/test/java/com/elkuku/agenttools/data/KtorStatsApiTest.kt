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

        assertEquals(AgentStatsResult.Failure(detail), KtorStatsApi("http://server", engine).list("t"))
    }

    @Test
    fun listMapsNetworkError() = runTest {
        val engine = MockEngine { throw IOException("timeout") }

        assertEquals(AgentStatsResult.Failure("Network error: timeout"), KtorStatsApi("http://server", engine).list("t"))
    }

    private val orion = customMedal("anomaly", "orion")!!

    private val voyager = customMedal("annual", "voyager")!!

    @Test
    fun customMedalsLoadsTheUsersMedals() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Get, req.method)
            assertEquals("http://server/api/me/custom-medals", req.url.toString())
            assertEquals("Bearer t", req.headers[HttpHeaders.Authorization])
            respond(
                """{"@context": "/api/contexts/CustomMedals", "@id": "/api/me/custom-medals", "@type": "CustomMedals",
                    "anomaly": {"orion": "platinum"}, "annual": {"voyager": 5}, "event": []}""",
                HttpStatusCode.OK,
                jsonHeaders,
            )
        }

        val result = KtorStatsApi("http://server", engine).customMedals("t")

        assertEquals(
            CustomMedalsResult.Success(listOf(CustomMedalTier(orion, "platinum"), CustomMedalTier(voyager, "black"))),
            result,
        )
    }

    @Test
    fun saveCustomMedalsPutsThemAs4eStoresThem() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Put, req.method)
            assertEquals("http://server/api/me/custom-medals", req.url.toString())
            assertEquals("application/ld+json", req.body.contentType?.toString())
            assertEquals(
                Json.parseToJsonElement("""{"anomaly": {"orion": "gold"}, "annual": {"voyager": 2}, "event": {}}"""),
                Json.parseToJsonElement(String(req.body.toByteArray())),
            )
            respond(String(req.body.toByteArray()), HttpStatusCode.OK, jsonHeaders)
        }
        val medals = listOf(CustomMedalTier(orion, "gold"), CustomMedalTier(voyager, "silver"))

        assertEquals(CustomMedalsResult.Success(medals), KtorStatsApi("http://server", engine).saveCustomMedals("t", medals))
    }

    @Test
    fun saveCustomMedalsMapsViolations() = runTest {
        val body = """{"status": 422, "detail": "annual[voyager]: The level must be 1 to 5.",
            "violations": [{"propertyPath": "annual[voyager]", "message": "The level must be 1 to 5."}]}"""
        val engine = MockEngine { respond(body, HttpStatusCode.UnprocessableEntity, problemHeaders) }

        assertEquals(
            CustomMedalsResult.Failure("annual[voyager]: The level must be 1 to 5."),
            KtorStatsApi("http://server", engine).saveCustomMedals("t", emptyList()),
        )
    }

    @Test
    fun customMedalsMapsUnauthorizedAndNetworkErrors() = runTest {
        val unauthorized = MockEngine { respond(problem(401, "Invalid ID token."), HttpStatusCode.Unauthorized, problemHeaders) }
        val offline = MockEngine { throw IOException("timeout") }

        assertEquals(
            CustomMedalsResult.Unauthorized("Invalid ID token."),
            KtorStatsApi("http://server", unauthorized).customMedals("t"),
        )
        assertEquals(CustomMedalsResult.Failure("Network error: timeout"), KtorStatsApi("http://server", offline).customMedals("t"))
    }
}
