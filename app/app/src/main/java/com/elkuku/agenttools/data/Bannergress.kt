package com.elkuku.agenttools.data

import com.elkuku.agenttools.R
import com.elkuku.agenttools.UiText
import com.elkuku.agenttools.uiText
import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.engine.HttpClientEngine
import io.ktor.client.engine.okhttp.OkHttp
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.HttpRequestBuilder
import io.ktor.client.request.bearerAuth
import io.ktor.client.request.get
import io.ktor.client.request.parameter
import io.ktor.http.HttpStatusCode
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import java.io.IOException

/**
 * A banner on Bannergress; [picture] is relative to [BANNERGRESS_API]. It starts at [startLatitude],
 * [startLongitude]. [eventStartDate] (`yyyy-MM-dd`) is the first day of an event banner, read for the
 * done list only: web makes it the day it was done unless the user set one.
 */
@Serializable
data class BannergressBanner(
    val id: String,
    val title: String,
    val missions: Int,
    val picture: String? = null,
    val startLatitude: Double? = null,
    val startLongitude: Double? = null,
    val eventStartDate: String? = null,
) {
    val url: String get() = "https://bannergress.com/banner/$id"

    val pictureUrl: String? get() = picture?.let { BANNERGRESS_API + it }
}

/**
 * A banner on the done list as web stores it (`/api/done-banners`, web's `DoneBanner`), with
 * [doneAt], the day the user did it (`yyyy-MM-dd`, null until set). [id] is null for other
 * agents' banners.
 */
@Serializable
data class DoneBanner(
    val id: Int? = null,
    val bannerId: String,
    val title: String,
    val missions: Int,
    val picture: String? = null,
    val startLatitude: Double? = null,
    val startLongitude: Double? = null,
    val doneAt: String? = null,
) {
    val banner: BannergressBanner get() = BannergressBanner(bannerId, title, missions, picture, startLatitude, startLongitude)
}

sealed interface DoneBannersResult {
    /** Newest on the list first. */
    data class Success(val banners: List<DoneBanner>) : DoneBannersResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : DoneBannersResult

    data class Failure(val message: UiText) : DoneBannersResult
}

sealed interface DoneBannerResult {
    data class Success(val banner: DoneBanner) : DoneBannerResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : DoneBannerResult

    data class Failure(val message: UiText) : DoneBannerResult
}

/**
 * What the app synced from Bannergress, as web's `Bannergress` (`/api/me` `bannergress`):
 * the number of banners [published] (with a mission the agent created), on the to-do and done
 * lists, every published banner and the newest banners of the to-do list. [doneBanners] is the whole done list, only
 * sent: web keeps it as done banners (`/api/done-banners`). [syncedAt] is set by the server.
 */
@Serializable
data class BannergressSync(
    val published: Int = 0,
    val todo: Int = 0,
    val done: Int = 0,
    val todoBanners: List<BannergressBanner> = emptyList(),
    val doneBanners: List<BannergressBanner> = emptyList(),
    /** Every banner with a mission the agent created, newest first. */
    val publishedBanners: List<BannergressBanner> = emptyList(),
    val syncedAt: String? = null,
)

sealed interface BannergressResult {
    data class Success(val sync: BannergressSync) : BannergressResult

    /** The Bannergress session ended (401): sign in again. */
    data object SignedOut : BannergressResult

    data class Failure(val message: UiText) : BannergressResult
}

const val BANNERGRESS_API = "https://api.bannergress.com"

/** As web's `Bannergress::MAX_BANNERS`: the newest to-do banners kept. */
const val MAX_BANNERS = 20

@Serializable
private data class BannerResponse(
    val id: String,
    val title: String = "",
    val numberOfMissions: Int = 0,
    val picture: String? = null,
    val startLatitude: Double? = null,
    val startLongitude: Double? = null,
    val eventStartDate: String? = null,
) {
    val banner: BannergressBanner get() = BannergressBanner(id, title, numberOfMissions, picture, startLatitude, startLongitude, eventStartDate)
}

private class SignedOutException : Exception()

/**
 * Bannergress's `GET /bnrs`, as kBannerGuider queries it: with the Bearer token of a Bannergress
 * sign-in ([com.elkuku.agenttools.auth.BannergressAuth]), also for the banners by author. There
 * is no count, so the lists are read page by page (with only the ids where only the count is
 * needed).
 */
class BannergressApi(engine: HttpClientEngine = OkHttp.create()) {
    private val client = HttpClient(engine) {
        expectSuccess = false
        install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
    }

    /** The counts, the published banners of [agentName], the newest to-do banners and the whole done list. */
    suspend fun sync(accessToken: String, agentName: String): BannergressResult = try {
        val done = all(accessToken, DONE_ATTRIBUTES) { newestFirst("done") }
        val published = all(accessToken, BANNER_ATTRIBUTES) { parameter("author", agentName) }
        BannergressResult.Success(
            BannergressSync(
                published = published.size,
                todo = count(accessToken, "listTypes" to "todo"),
                done = done.size,
                todoBanners = newest(accessToken, "todo"),
                doneBanners = done,
                publishedBanners = published,
            ),
        )
    } catch (_: SignedOutException) {
        BannergressResult.SignedOut
    } catch (e: IOException) {
        BannergressResult.Failure(uiText(R.string.error_network, e.message ?: e::class.simpleName.orEmpty()))
    } catch (e: ServerException) {
        BannergressResult.Failure(uiText(R.string.bannergress_error_server, e.status))
    }

    private suspend fun count(accessToken: String, filter: Pair<String, String>): Int {
        var total = 0
        while (true) {
            val page = page(accessToken, PAGE_SIZE, total, listOf("id")) { parameter(filter.first, filter.second) }
            total += page.size
            if (page.size < PAGE_SIZE) return total
        }
    }

    private suspend fun newest(accessToken: String, listType: String): List<BannergressBanner> =
        page(accessToken, MAX_BANNERS, 0, BANNER_ATTRIBUTES) { newestFirst(listType) }.map { it.banner }

    /** Every banner of the [filter] with the [attributes], page by page. */
    private suspend fun all(accessToken: String, attributes: List<String>, filter: HttpRequestBuilder.() -> Unit): List<BannergressBanner> {
        val banners = mutableListOf<BannergressBanner>()
        while (true) {
            val page = page(accessToken, PAGE_SIZE, banners.size, attributes, filter)
            banners += page.map { it.banner }
            if (page.size < PAGE_SIZE) return banners
        }
    }

    private fun HttpRequestBuilder.newestFirst(listType: String) {
        parameter("listTypes", listType)
        parameter("orderBy", "listAdded")
        parameter("orderDirection", "DESC")
    }

    private suspend fun page(
        accessToken: String,
        limit: Int,
        offset: Int,
        attributes: List<String>,
        filter: HttpRequestBuilder.() -> Unit,
    ): List<BannerResponse> {
        val response = client.get("$BANNERGRESS_API/bnrs") {
            bearerAuth(accessToken)
            filter()
            parameter("offset", offset)
            parameter("limit", limit)
            attributes.forEach { parameter("attributes", it) }
        }
        return when (response.status) {
            HttpStatusCode.OK -> response.body()
            HttpStatusCode.Unauthorized -> throw SignedOutException()
            else -> throw ServerException(response.status.value)
        }
    }

    fun close() = client.close()

    private class ServerException(val status: Int) : Exception()

    private companion object {
        /** The most Bannergress returns at once. */
        const val PAGE_SIZE = 100

        val BANNER_ATTRIBUTES = listOf("id", "title", "numberOfMissions", "picture", "startLatitude", "startLongitude")

        /** The done list's also with the first day of an event: web dates those banners. */
        val DONE_ATTRIBUTES = BANNER_ATTRIBUTES + "eventStartDate"
    }
}
