package com.elkuku.uploadapp.data

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

    private val jsonHeaders = headersOf(HttpHeaders.ContentType, "application/json")

    @Test
    fun savePostsStatsWithBearerToken() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Post, req.method)
            assertEquals("http://server/api/agent-stats", req.url.toString())
            assertEquals("Bearer id-token", req.headers[HttpHeaders.Authorization])
            assertEquals(stats, Json.parseToJsonElement(String(req.body.toByteArray())))

            respond("""{"id": 9, "createdAt": "2026-10-19T12:00:00+00:00"}""", HttpStatusCode.Created, jsonHeaders)
        }

        val result = KtorStatsApi("http://server/", engine).save("id-token", stats)

        assertEquals(SaveResult.Success(9, "2026-10-19T12:00:00+00:00"), result)
    }

    @Test
    fun saveMapsUnauthorized() = runTest {
        val engine = MockEngine { respond("""{"error": "Unknown user."}""", HttpStatusCode.Unauthorized, jsonHeaders) }

        assertEquals(SaveResult.Unauthorized("Unknown user."), KtorStatsApi("http://server", engine).save("t", stats))
    }

    @Test
    fun saveMapsValidationViolations() = runTest {
        val body = """
            {"title": "Validation Failed", "status": 422, "violations": [
                {"propertyPath": "ap", "title": "This value should not be null."},
                {"propertyPath": "", "title": "Something general."}
            ]}
        """.trimIndent()
        val engine = MockEngine { respond(body, HttpStatusCode.UnprocessableEntity, jsonHeaders) }

        assertEquals(
            SaveResult.Invalid(listOf("ap: This value should not be null.", "Something general.")),
            KtorStatsApi("http://server", engine).save("t", stats),
        )
    }

    @Test
    fun saveMapsConflictToServerMessage() = runTest {
        val engine = MockEngine {
            respond("""{"error": "Stat entry already added!"}""", HttpStatusCode.Conflict, jsonHeaders)
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
                """[{"id": 2, "datetime": "2026-10-19 15:35:56", "nickname": "TestAgent",
                    "faction": "Enlightened", "ap": 243517130, "level": 16, "recon": null}]""",
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
    fun listMapsUnauthorized() = runTest {
        val engine = MockEngine { respond("""{"error": "Invalid ID token."}""", HttpStatusCode.Unauthorized, jsonHeaders) }

        assertEquals(AgentStatsResult.Unauthorized("Invalid ID token."), KtorStatsApi("http://server", engine).list("t"))
    }

    @Test
    fun listMapsNetworkError() = runTest {
        val engine = MockEngine { throw IOException("timeout") }

        assertEquals(AgentStatsResult.Failure("Network error: timeout"), KtorStatsApi("http://server", engine).list("t"))
    }
}
