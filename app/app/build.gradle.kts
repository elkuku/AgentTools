import java.util.Properties

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.kotlin.serialization)
}

val localProperties = Properties().apply {
    rootProject.file("local.properties").takeIf { it.exists() }?.inputStream()?.use { load(it) }
}

fun localProperty(name: String, default: String = ""): String =
    localProperties.getProperty(name) ?: providers.gradleProperty(name).orNull ?: default

android {
    namespace = "com.elkuku.agenttools"
    compileSdk = 37

    defaultConfig {
        applicationId = "com.elkuku.agenttools"
        minSdk = 26
        targetSdk = 36
        versionCode = 1
        versionName = "0.1.0"

        // Debug builds offer it on the settings screen next to their default server
        buildConfigField("String", "LIVE_SERVER_BASE_URL", "\"${localProperty("server.baseUrl.release")}\"")
        buildConfigField("String", "GOOGLE_SERVER_CLIENT_ID", "\"${localProperty("google.serverClientId")}\"")
    }

    signingConfigs {
        // Release key from local.properties; without it assembleRelease builds an unsigned APK
        if (localProperty("release.storeFile").isNotBlank()) {
            create("release") {
                storeFile = file(localProperty("release.storeFile"))
                storePassword = localProperty("release.storePassword")
                keyAlias = localProperty("release.keyAlias")
                keyPassword = localProperty("release.keyPassword")
            }
        }
    }

    buildTypes {
        debug {
            buildConfigField("String", "SERVER_BASE_URL", "\"${localProperty("server.baseUrl.debug", "http://10.0.2.2:8000")}\"")
            // Fake sign-in without Google (needs APP_FAKE_GOOGLE_TOKENS=1 on the dev server)
            buildConfigField("String", "FAKE_SIGN_IN_EMAIL", "\"${localProperty("debug.fakeSignInEmail")}\"")
        }
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
            buildConfigField("String", "SERVER_BASE_URL", "\"${localProperty("server.baseUrl.release")}\"")
            signingConfig = signingConfigs.findByName("release")
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    testOptions {
        unitTests.isReturnDefaultValues = true
    }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.lifecycle.runtime.compose)

    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.compose.material3)
    debugImplementation(libs.androidx.compose.ui.tooling)

    implementation(libs.androidx.credentials)
    implementation(libs.androidx.credentials.play.services.auth)
    implementation(libs.googleid)

    implementation(libs.ktor.client.core)
    implementation(libs.ktor.client.okhttp)
    implementation(libs.ktor.client.content.negotiation)
    implementation(libs.ktor.serialization.kotlinx.json)
    implementation(libs.kotlinx.serialization.json)

    testImplementation(libs.junit)
    testImplementation(libs.kotlinx.coroutines.test)
    testImplementation(libs.ktor.client.mock)
}
