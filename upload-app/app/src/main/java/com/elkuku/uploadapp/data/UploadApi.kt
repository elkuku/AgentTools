package com.elkuku.uploadapp.data

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.engine.HttpClientEngine
import io.ktor.client.engine.okhttp.OkHttp
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.accept
import io.ktor.client.request.bearerAuth
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.client.statement.HttpResponse
import io.ktor.http.ContentType
import io.ktor.http.HttpStatusCode
import io.ktor.http.contentType
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import java.io.IOException

@Serializable
data class UploadRequest(val title: String, val data: JsonObject)

@Serializable
private data class CreatedResponse(val id: Int, val createdAt: String)

@Serializable
private data class ErrorResponse(val error: String = "")

@Serializable
private data class Violation(val propertyPath: String = "", val title: String = "")

@Serializable
private data class ValidationResponse(val violations: List<Violation> = emptyList())

sealed interface UploadResult {
    data class Success(val id: Int, val createdAt: String) : UploadResult

    /** The ID token was rejected (expired, invalid, or the user is unknown to the server). */
    data class Unauthorized(val message: String) : UploadResult

    /** The server rejected the payload; one message per violation. */
    data class Invalid(val messages: List<String>) : UploadResult

    data class Failure(val message: String) : UploadResult
}

fun interface UploadApi {
    suspend fun upload(idToken: String, request: UploadRequest): UploadResult
}

class KtorUploadApi(
    private val baseUrl: String,
    engine: HttpClientEngine = OkHttp.create(),
) : UploadApi {
    private val json = Json { ignoreUnknownKeys = true }

    private val client = HttpClient(engine) {
        expectSuccess = false
        install(ContentNegotiation) { json(json) }
    }

    override suspend fun upload(idToken: String, request: UploadRequest): UploadResult {
        val response = try {
            client.post("${baseUrl.trimEnd('/')}/api/uploads") {
                bearerAuth(idToken)
                contentType(ContentType.Application.Json)
                accept(ContentType.Application.Json)
                setBody(request)
            }
        } catch (e: IOException) {
            return UploadResult.Failure("Network error: ${e.message ?: e::class.simpleName}")
        }

        return when (response.status) {
            HttpStatusCode.Created -> response.body<CreatedResponse>().let {
                UploadResult.Success(it.id, it.createdAt)
            }

            HttpStatusCode.Unauthorized -> UploadResult.Unauthorized(
                response.decodeOrNull<ErrorResponse>()?.error?.ifBlank { null } ?: "Unauthorized."
            )

            HttpStatusCode.UnprocessableEntity -> UploadResult.Invalid(
                response.decodeOrNull<ValidationResponse>()?.violations
                    ?.map { if (it.propertyPath.isBlank()) it.title else "${it.propertyPath}: ${it.title}" }
                    ?.ifEmpty { null }
                    ?: listOf("The server rejected the data.")
            )

            else -> UploadResult.Failure("Server error: HTTP ${response.status.value}")
        }
    }

    fun close() = client.close()

    private suspend inline fun <reified T> HttpResponse.decodeOrNull(): T? =
        try {
            body<T>()
        } catch (_: Exception) {
            null
        }
}
