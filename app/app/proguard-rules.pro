# Credential Manager / Google ID (see https://developer.android.com/identity/sign-in/credential-manager#proguard)
-if class androidx.credentials.CredentialManager
-keep class androidx.credentials.playservices.** {
  *;
}

# Ktor references JVM-only classes that are not available on Android
-dontwarn java.lang.management.**
-dontwarn org.slf4j.**
