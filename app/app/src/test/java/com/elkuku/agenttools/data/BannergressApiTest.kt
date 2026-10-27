package com.elkuku.agenttools.data

import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class BannergressApiTest {
    private val jsonHeaders = headersOf(HttpHeaders.ContentType, "application/json")

    private fun banners(count: Int, from: Int = 0) =
        (from until from + count).joinToString(",", "[", "]") { """{"id": "b-$it", "title": "Banner $it", "numberOfMissions": 6, "picture": "/bnrs/pictures/p$it"}""" }

    @Test
    fun countsPageByPageAndReadsTheNewestBanners() = runTest {
        val requests = mutableListOf<String>()
        val engine = MockEngine { req ->
            requests += req.url.toString()
            assertEquals("Bearer t", req.headers[HttpHeaders.Authorization])
            val parameters = req.url.parameters
            val offset = parameters["offset"]!!.toInt()
            val body = when {
                parameters["author"] == "TestAgent" -> banners(2)
                // 137 done banners: a full page and the rest
                parameters["listTypes"] == "done" -> banners(if (offset == 0) 100 else 37, offset)
                parameters["orderBy"] == "listAdded" -> banners(3)
                else -> banners(0)
            }
            respond(body, HttpStatusCode.OK, jsonHeaders)
        }

        val result = BannergressApi(engine).sync("t", "TestAgent")

        val sync = (result as BannergressResult.Success).sync
        assertEquals(2, sync.published)
        assertEquals(listOf("b-0", "b-1"), sync.publishedBanners.map { it.id })
        assertEquals(0, sync.todo)
        assertEquals(137, sync.done)
        // The whole done list, newest first
        assertEquals(137, sync.doneBanners.size)
        assertEquals(BannergressBanner("b-0", "Banner 0", 6, "/bnrs/pictures/p0"), sync.doneBanners.first())
        assertEquals("b-136", sync.doneBanners.last().id)
        assertEquals(3, sync.todoBanners.size)
        assertEquals("https://bannergress.com/banner/b-0", sync.doneBanners.first().url)
        assertEquals("https://api.bannergress.com/bnrs/pictures/p0", sync.doneBanners.first().pictureUrl)
        // Counting asks for the ids only; the done list newest first with the pictures, page by page
        assertTrue(requests.any { "listTypes=todo" in it && "orderBy" !in it && "attributes=id&" in it.plus("&") && "attributes=title" !in it })
        assertTrue(requests.any { "listTypes=done" in it && "offset=100" in it && "orderBy=listAdded" in it && "attributes=picture" in it })
        assertTrue(requests.any { "author=TestAgent" in it && "attributes=picture" in it })
        assertTrue(requests.any { "listTypes=todo" in it && "orderBy=listAdded" in it && "orderDirection=DESC" in it && "limit=$MAX_BANNERS" in it })
    }

    @Test
    fun anEndedSessionSignsOut() = runTest {
        val engine = MockEngine { respond("", HttpStatusCode.Unauthorized) }

        assertEquals(BannergressResult.SignedOut, BannergressApi(engine).sync("t", "TestAgent"))
    }

    @Test
    fun serverErrorsAreFailures() = runTest {
        val engine = MockEngine { respond("", HttpStatusCode.InternalServerError) }

        assertTrue(BannergressApi(engine).sync("t", "TestAgent") is BannergressResult.Failure)
    }
}
