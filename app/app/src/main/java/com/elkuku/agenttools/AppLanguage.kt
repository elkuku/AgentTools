package com.elkuku.agenttools

import android.app.Activity
import android.app.LocaleManager
import android.content.Context
import android.content.res.Configuration
import android.os.Build
import android.os.LocaleList
import androidx.core.content.edit
import java.util.Locale

/**
 * The app's language, independent of the phone's: Android 13+ keeps it as the per-app language
 * (also in the system settings, `res/xml/locales_config.xml` from `generateLocaleConfig`); before,
 * it is stored here and applied to the activity ([wrap]).
 */
object AppLanguage {
    /** The languages of the app, as language tags; English is the default (`values/`). */
    val SUPPORTED = listOf("en", "de", "es")

    private const val PREFS = "settings"
    private const val KEY = "language"

    /** The chosen language tag, or null to follow the phone. */
    fun get(context: Context): String? =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            context.getSystemService(LocaleManager::class.java).applicationLocales.get(0)?.language
        } else {
            context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY, null)
        }?.takeIf { it in SUPPORTED }

    /** Switches to [tag] (null: the phone's language); the activity is recreated with it. */
    fun set(activity: Activity, tag: String?) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            // The system recreates the activity
            activity.getSystemService(LocaleManager::class.java).applicationLocales =
                if (tag == null) LocaleList.getEmptyLocaleList() else LocaleList.forLanguageTags(tag)
        } else {
            activity.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit { putString(KEY, tag) }
            activity.recreate()
        }
    }

    /** Before Android 13: [base] with the chosen language, for `attachBaseContext`. */
    fun wrap(base: Context): Context {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) return base
        val tag = get(base) ?: return base
        val config = Configuration(base.resources.configuration).apply { setLocale(Locale.forLanguageTag(tag)) }
        return base.createConfigurationContext(config)
    }
}
