package com.elkuku.agenttools

import android.content.res.Resources
import androidx.annotation.PluralsRes
import androidx.annotation.StringRes
import androidx.compose.runtime.Composable
import androidx.compose.ui.platform.LocalResources

/**
 * A text for the user, resolved in the app's language when it is shown: the view model and the API
 * client stay free of Android resources, and a language change also changes the texts already shown.
 */
sealed interface UiText {
    /** As it is, e.g. a message from the server. */
    data class Plain(val text: String) : UiText

    /** A string resource; [args] may be [UiText]s themselves. */
    data class Res(@param:StringRes val id: Int, val args: List<Any> = emptyList()) : UiText

    /** A plurals resource for [count]; [args] default to the count. */
    data class Plural(@param:PluralsRes val id: Int, val count: Int, val args: List<Any> = listOf(count)) : UiText

    /** One text per line. */
    data class Lines(val lines: List<UiText>) : UiText

    fun resolve(resources: Resources): String = when (this) {
        is Plain -> text
        is Res -> resources.getString(id, *resolveArgs(args, resources))
        is Plural -> resources.getQuantityString(id, count, *resolveArgs(args, resources))
        is Lines -> lines.joinToString("\n") { it.resolve(resources) }
    }

    private fun resolveArgs(args: List<Any>, resources: Resources): Array<Any> =
        args.map { if (it is UiText) it.resolve(resources) else it }.toTypedArray()
}

fun uiText(@StringRes id: Int, vararg args: Any): UiText = UiText.Res(id, args.toList())

@Composable
fun UiText.asString(): String = resolve(LocalResources.current)
