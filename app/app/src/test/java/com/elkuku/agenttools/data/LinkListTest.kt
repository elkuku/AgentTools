package com.elkuku.agenttools.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.io.File
import java.nio.file.Files

class LinkListTest {
    private val sections = listOf(
        LinkSection("Official", listOf(Link("Ingress", "https://ingress.com", "The official site."), Link("Intel Map", "https://intel.ingress.com"))),
    )

    private fun file() = File(Files.createTempDirectory("links").toFile(), "links.json")

    @Test
    fun theCacheKeepsTheList() {
        val file = file()

        FileLinkListCache(file).write(sections)

        assertEquals(sections, FileLinkListCache(file).read())
    }

    @Test
    fun theCacheIsEmptyWithoutAFileOrWithABrokenOne() {
        val file = file()

        assertNull(FileLinkListCache(file).read())

        file.writeText("{not json")

        assertNull(FileLinkListCache(file).read())
    }
}
