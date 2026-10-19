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
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Test
import java.io.IOException

class KtorUploadApiTest {
    private val request = UploadRequest("Hello", JsonObject(mapOf("a" to JsonPrimitive(1))))

    private val jsonHeaders = headersOf(HttpHeaders.ContentType, "application/json")

    @Test
    fun sendsBearerTokenAndJsonBody() = runTest {
        val engine = MockEngine { req ->
            assertEquals(HttpMethod.Post, req.method)
            assertEquals("http://server/api/uploads", req.url.toString())
            assertEquals("Bearer id-token", req.headers[HttpHeaders.Authorization])

            val body = Json.parseToJsonElement(String(req.body.toByteArray())).jsonObject
            assertEquals("Hello", body["title"]?.jsonPrimitive?.content)
            assertEquals(JsonObject(mapOf("a" to JsonPrimitive(1))), body["data"])

            respond("""{"id": 7, "createdAt": "2026-10-19T12:00:00+00:00"}""", HttpStatusCode.Created, jsonHeaders)
        }

        val result = KtorUploadApi("http://server/", engine).upload("id-token", request)

        assertEquals(UploadResult.Success(7, "2026-10-19T12:00:00+00:00"), result)
    }

    @Test
    fun mapsUnauthorized() = runTest {
        val engine = MockEngine { respond("""{"error": "Unknown user."}""", HttpStatusCode.Unauthorized, jsonHeaders) }

        val result = KtorUploadApi("http://server", engine).upload("id-token", request)

        assertEquals(UploadResult.Unauthorized("Unknown user."), result)
    }

    @Test
    fun mapsValidationViolations() = runTest {
        val body = """
            {"title": "Validation Failed", "status": 422, "violations": [
                {"propertyPath": "title", "title": "This value should not be blank."},
                {"propertyPath": "data", "title": "The data must be a JSON object, not a list."}
            ]}
        """.trimIndent()
        val engine = MockEngine { respond(body, HttpStatusCode.UnprocessableEntity, jsonHeaders) }

        val result = KtorUploadApi("http://server", engine).upload("id-token", request)

        assertEquals(
            UploadResult.Invalid(
                listOf(
                    "title: This value should not be blank.",
                    "data: The data must be a JSON object, not a list.",
                )
            ),
            result,
        )
    }

    @Test
    fun mapsServerError() = runTest {
        val engine = MockEngine { respond("oops", HttpStatusCode.InternalServerError) }

        val result = KtorUploadApi("http://server", engine).upload("id-token", request)

        assertEquals(UploadResult.Failure("Server error: HTTP 500"), result)
    }

    @Test
    fun mapsNetworkError() = runTest {
        val engine = MockEngine { throw IOException("connection refused") }

        val result = KtorUploadApi("http://server", engine).upload("id-token", request)

        assertEquals(UploadResult.Failure("Network error: connection refused"), result)
    }

    @Test
    fun postsAgentStatsAsTheBody() = runTest {
        val stats = JsonObject(mapOf("nickname" to JsonPrimitive("TestAgent"), "ap" to JsonPrimitive(1)))
        val engine = MockEngine { req ->
            assertEquals("http://server/api/agent-stats", req.url.toString())
            assertEquals("Bearer id-token", req.headers[HttpHeaders.Authorization])
            assertEquals(stats, Json.parseToJsonElement(String(req.body.toByteArray())))

            respond("""{"id": 9, "createdAt": "2026-10-19T12:00:00+00:00"}""", HttpStatusCode.Created, jsonHeaders)
        }

        val result = KtorUploadApi("http://server", engine).uploadAgentStats("id-token", stats)

        assertEquals(UploadResult.Success(9, "2026-10-19T12:00:00+00:00"), result)
    }

    @Test
    fun mapsConflictToServerMessage() = runTest {
        val engine = MockEngine {
            respond("""{"error": "Stat entry already added!"}""", HttpStatusCode.Conflict, jsonHeaders)
        }

        val result = KtorUploadApi("http://server", engine).uploadAgentStats("id-token", JsonObject(emptyMap()))

        assertEquals(UploadResult.Failure("Stat entry already added!"), result)
    }

    @Test
    fun loadsAgentStats() = runTest {
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

        val result = KtorUploadApi("http://server", engine).agentStats("id-token")

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
    fun agentStatsMapsUnauthorized() = runTest {
        val engine = MockEngine { respond("""{"error": "Invalid ID token."}""", HttpStatusCode.Unauthorized, jsonHeaders) }

        assertEquals(
            AgentStatsResult.Unauthorized("Invalid ID token."),
            KtorUploadApi("http://server", engine).agentStats("id-token"),
        )
    }
}
