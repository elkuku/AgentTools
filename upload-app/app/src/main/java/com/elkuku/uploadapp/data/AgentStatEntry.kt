package com.elkuku.uploadapp.data

import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.longOrNull

/** One agent_stat row as returned by `GET /api/agent-stats`. */
data class AgentStatEntry(
    val id: Int,
    val datetime: String,
    val nickname: String,
    val faction: String,
    /** The numeric columns by agent_stat column name (`ap`, `level`, `mind_controller`, ...). */
    val stats: Map<String, Long?>,
) {
    operator fun get(column: String): Long? = stats[column]

    companion object {
        private val TEXT_KEYS = setOf("id", "datetime", "nickname", "faction")

        fun fromJson(json: JsonObject): AgentStatEntry {
            fun text(key: String) = (json[key] as? JsonPrimitive)?.contentOrNull.orEmpty()

            return AgentStatEntry(
                id = (json["id"] as? JsonPrimitive)?.longOrNull?.toInt() ?: 0,
                datetime = text("datetime"),
                nickname = text("nickname"),
                faction = text("faction"),
                stats = json.filterKeys { it !in TEXT_KEYS }.mapValues { (_, v) -> (v as? JsonPrimitive)?.longOrNull },
            )
        }
    }
}

sealed interface AgentStatsResult {
    /** Newest first. */
    data class Success(val entries: List<AgentStatEntry>) : AgentStatsResult

    data class Unauthorized(val message: String) : AgentStatsResult

    data class Failure(val message: String) : AgentStatsResult
}
