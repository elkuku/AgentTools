package com.elkuku.agenttools.data

import androidx.annotation.StringRes
import com.elkuku.agenttools.R

/**
 * A part of the agent profile that the user shows to other users (signed in on the website) or
 * keeps private, as `ProfilePart` in web. The other parts are only shown together with [AGENT].
 */
enum class ProfilePart(val serverValue: String, @param:StringRes val label: Int, @param:StringRes val help: Int) {
    AGENT("agent", R.string.part_agent, R.string.part_agent_help),
    AVATAR("avatar", R.string.part_avatar, R.string.part_avatar_help),
    LATEST_STATS("latest_stats", R.string.part_latest_stats, R.string.part_latest_stats_help),
    STATS_HISTORY("stats_history", R.string.part_stats_history, R.string.part_stats_history_help),
    CUSTOM_MEDALS("custom_medals", R.string.part_custom_medals, R.string.part_custom_medals_help);

    companion object {
        /** Null for a part the app does not know. */
        fun fromServer(value: String): ProfilePart? = entries.find { it.serverValue == value }
    }
}
