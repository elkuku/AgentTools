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
        versionCode = 2
        versionName = "0.2.0"

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
        // Dev key (dev.* properties, set by the GitHub Pages workflow); without it assembleDev is unsigned
        if (localProperty("dev.storeFile").isNotBlank()) {
            create("dev") {
                storeFile = file(localProperty("dev.storeFile"))
                storePassword = localProperty("dev.storePassword")
                keyAlias = localProperty("dev.keyAlias")
                keyPassword = localProperty("dev.keyPassword")
            }
        }
    }

    buildTypes {
        debug {
            buildConfigField("String", "SERVER_BASE_URL", "\"${localProperty("server.baseUrl.debug", "http://10.0.2.2:8000")}\"")
            // The server can be changed on the settings screen
            buildConfigField("boolean", "SERVER_SWITCH", "true")
            // Fake sign-in without Google (needs APP_FAKE_GOOGLE_TOKENS=1 on the dev server)
            buildConfigField("String", "FAKE_SIGN_IN_EMAIL", "\"${localProperty("debug.fakeSignInEmail")}\"")
        }
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
            buildConfigField("String", "SERVER_BASE_URL", "\"${localProperty("server.baseUrl.release")}\"")
            buildConfigField("boolean", "SERVER_SWITCH", "false")
            signingConfig = signingConfigs.findByName("release")
        }
        // Release build installed next to the release app ("AgentTools Dev"), with the server switch
        create("dev") {
            initWith(getByName("release"))
            applicationIdSuffix = ".dev"
            versionNameSuffix = "-dev" + localProperty("dev.build").let { if (it.isBlank()) "" else "+$it" }
            buildConfigField("boolean", "SERVER_SWITCH", "true")
            signingConfig = signingConfigs.findByName("dev")
            matchingFallbacks += "release"
        }
    }

    // Signs in like release
    sourceSets["dev"].kotlin.directories += "src/release/java"

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    // The app's languages for Android 13+'s per-app language setting (res/resources.properties: English is the default)
    androidResources {
        generateLocaleConfig = true
    }

    // An app bundle keeps every language: the app switches them itself (AppLanguage)
    bundle {
        language {
            enableSplit = false
        }
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

    // Banner pictures from Bannergress
    implementation(libs.coil.compose)
    implementation(libs.coil.network.okhttp)
    // The banners' world map, OpenStreetMap tiles without an API key
    implementation(libs.osmdroid.android)

    testImplementation(libs.junit)
    testImplementation(libs.kotlinx.coroutines.test)
    testImplementation(libs.ktor.client.mock)
}
