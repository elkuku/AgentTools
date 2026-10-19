package com.elkuku.uploadapp.auth

import android.content.ContextWrapper
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class FakeGoogleAuthTest {
    private val context = ContextWrapper(null)

    @Test
    fun producesServerFakeTokenFormat() = runTest {
        val session = FakeGoogleAuth("dev@example.com").signIn(context)

        assertEquals("fake|fake-dev@example.com|dev@example.com|verified", session.idToken)
        assertEquals("dev@example.com", session.email)
    }

    @Test
    fun silentSignInOnlyAfterSignInAndUntilSignOut() = runTest {
        val auth = FakeGoogleAuth("dev@example.com")
        assertNull(auth.signInSilently(context))

        auth.signIn(context)
        assertEquals("dev@example.com", auth.signInSilently(context)?.email)

        auth.signOut(context)
        assertNull(auth.signInSilently(context))
    }
}
