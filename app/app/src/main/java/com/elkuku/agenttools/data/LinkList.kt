package com.elkuku.agenttools.data

import com.elkuku.agenttools.UiText
import kotlinx.serialization.Serializable
import kotlinx.serialization.builtins.ListSerializer
import kotlinx.serialization.json.Json
import java.io.File
import java.io.IOException

/** An entry of the Ingress link list (docs/links.md in the repository, `GET /api/links`). */
@Serializable
data class Link(val title: String, val url: String, val description: String? = null)

/** A section of the link list with its entries. */
@Serializable
data class LinkSection(val title: String, val links: List<Link> = emptyList())

sealed interface LinkListResult {
    data class Success(val sections: List<LinkSection>) : LinkListResult

    /** E.g. offline, or a server error. */
    data class Failure(val message: UiText) : LinkListResult
}

/** Where the last link list loaded is kept, so it shows offline and right away. */
interface LinkListCache {
    /** The saved list; null without one or when it cannot be read. */
    fun read(): List<LinkSection>?

    fun write(sections: List<LinkSection>)
}

/** [LinkListCache] in a JSON file (the app's files directory). */
class FileLinkListCache(private val file: File) : LinkListCache {
    private val json = Json { ignoreUnknownKeys = true }

    private val serializer = ListSerializer(LinkSection.serializer())

    override fun read(): List<LinkSection>? = try {
        if (file.exists()) json.decodeFromString(serializer, file.readText()) else null
    } catch (_: IOException) {
        null
    } catch (_: IllegalArgumentException) {
        // An older or broken file: loaded again
        null
    }

    override fun write(sections: List<LinkSection>) {
        try {
            file.writeText(json.encodeToString(serializer, sections))
        } catch (_: IOException) {
            // Only a cache: the list is loaded again next time
        }
    }
}

/** Where to suggest a link: the GitHub editor of docs/links.md (as web's Links page). */
const val SUGGEST_LINK_URL = "https://github.com/elkuku/AgentTools/edit/master/docs/links.md"
