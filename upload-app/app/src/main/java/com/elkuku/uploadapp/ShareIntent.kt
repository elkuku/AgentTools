package com.elkuku.uploadapp

import android.content.ContentResolver
import android.content.Intent
import android.net.Uri
import androidx.core.content.IntentCompat

/** Ingress stats are a few KB; stop reading shared files well above that. */
private const val MAX_SHARED_BYTES = 256 * 1024

/**
 * Reads an ACTION_SEND intent: the text in EXTRA_TEXT, or else the file in EXTRA_STREAM.
 * Returns null for other intents or when nothing could be read. Blocking: call off the main thread.
 */
fun readSharedText(intent: Intent, resolver: ContentResolver): String? {
    if (intent.action != Intent.ACTION_SEND) return null

    val text = intent.getCharSequenceExtra(Intent.EXTRA_TEXT)?.toString()
        ?: IntentCompat.getParcelableExtra(intent, Intent.EXTRA_STREAM, Uri::class.java)?.let { readText(it, resolver) }

    return text?.takeIf { it.isNotBlank() }
}

private fun readText(uri: Uri, resolver: ContentResolver): String? = try {
    resolver.openInputStream(uri)?.use { input ->
        val bytes = input.readNBytesCompat(MAX_SHARED_BYTES + 1)
        if (bytes.size > MAX_SHARED_BYTES) null else bytes.toString(Charsets.UTF_8)
    }
} catch (_: Exception) {
    // SecurityException (no read grant), FileNotFoundException, IOException
    null
}

/** InputStream.readNBytes needs API 33. */
private fun java.io.InputStream.readNBytesCompat(limit: Int): ByteArray {
    val out = java.io.ByteArrayOutputStream()
    val buffer = ByteArray(8 * 1024)
    while (out.size() < limit) {
        val read = read(buffer, 0, minOf(buffer.size, limit - out.size()))
        if (read < 0) break
        out.write(buffer, 0, read)
    }
    return out.toByteArray()
}
