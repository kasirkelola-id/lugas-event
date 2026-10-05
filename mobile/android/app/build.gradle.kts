import java.util.Properties

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
    id("com.google.gms.google-services")
}

// Secrets and signing identity belong outside the checkout. Never use debug
// signing for a release, including implicit tasks such as `assemble`.
val releaseProperties = Properties()
System.getenv("KARTAR_SIGNING_PROPERTIES")?.takeIf { it.isNotBlank() }?.let { path ->
    val propertiesFile = file(path).canonicalFile
    require(!propertiesFile.toPath().startsWith(rootProject.projectDir.parentFile.canonicalFile.toPath())) {
        "Release signing properties must be outside the repository"
    }
    propertiesFile.inputStream().use { releaseProperties.load(it) }
}
fun releaseValue(name: String, environment: String): String? =
    System.getenv(environment)?.takeIf { it.isNotBlank() }
        ?: releaseProperties.getProperty(name)?.takeIf { it.isNotBlank() }
val releaseStorePath = releaseValue("storeFile", "KARTAR_RELEASE_STORE_FILE")
val releaseStorePassword = releaseValue("storePassword", "KARTAR_RELEASE_STORE_PASSWORD")
val releaseKeyAlias = releaseValue("keyAlias", "KARTAR_RELEASE_KEY_ALIAS")
val releaseKeyPassword = releaseValue("keyPassword", "KARTAR_RELEASE_KEY_PASSWORD")
val releaseSigningReady = listOf(releaseStorePath, releaseStorePassword, releaseKeyAlias, releaseKeyPassword).all { it != null }
if (gradle.startParameter.taskNames.any { it.contains("Release", ignoreCase = true) } && !releaseSigningReady) {
    throw GradleException("Release signing configuration is required; debug fallback is forbidden")
}
val releaseStore = releaseStorePath?.let { file(it).canonicalFile }
if (releaseSigningReady) {
    require(releaseStore!!.isFile && !releaseStore.toPath().startsWith(rootProject.projectDir.parentFile.canonicalFile.toPath())) {
        "Release keystore must exist outside the repository"
    }
    require(releaseKeyAlias != "androiddebugkey") { "Debug identity is forbidden for release" }
}
gradle.taskGraph.whenReady {
    if (allTasks.any { it.project == project && it.name.contains("Release", ignoreCase = true) } && !releaseSigningReady) {
        throw GradleException("Release signing configuration is required; debug fallback is forbidden")
    }
}

android {
    namespace = "com.kartar.app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.kartar.app"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName

        // Restrict to ARM64 for release to reduce APK size without affecting x86_64 debug emulators
        if (gradle.startParameter.taskNames.any { it.contains("Release") }) {
            ndk {
                abiFilters.clear()
                abiFilters.add("arm64-v8a")
            }
        }
    }

    packagingOptions {
        if (gradle.startParameter.taskNames.any { it.contains("Release") }) {
            exclude("lib/armeabi-v7a/**")
            exclude("lib/x86_64/**")
            exclude("lib/x86/**")
        }
    }

    signingConfigs {
        if (releaseSigningReady) {
            create("release") {
                storeFile = releaseStore
                storePassword = releaseStorePassword
                keyAlias = releaseKeyAlias
                keyPassword = releaseKeyPassword
            }
        }
    }
    buildTypes {
        release {
            signingConfig = if (releaseSigningReady) signingConfigs.getByName("release") else null
        }
    }
}

flutter {
    source = "../.."
}
