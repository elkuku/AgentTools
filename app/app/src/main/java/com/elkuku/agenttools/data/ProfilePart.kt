package com.elkuku.agenttools.data

/**
 * A part of the agent profile that the user shows to other users (signed in on the website) or
 * keeps private, as `ProfilePart` in web. The other parts are only shown together with [AGENT].
 */
enum class ProfilePart(val serverValue: String, val label: String, val help: String) {
    AGENT("agent", "Agent name, faction and level", "Your agent page and your entry in the list of agents. The other parts are only shown with it."),
    LATEST_STATS("latest_stats", "Latest stats", "The all-time stats of your newest upload."),
    STATS_HISTORY("stats_history", "Stats history", "Every upload with its date and AP."),
    CUSTOM_MEDALS("custom_medals", "Custom medals", "Your anomaly, annual and event medals.");

    companion object {
        /** Null for a part the app does not know. */
        fun fromServer(value: String): ProfilePart? = entries.find { it.serverValue == value }
    }
}
