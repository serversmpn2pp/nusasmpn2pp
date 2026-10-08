import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Firebase baru diaktifkan setelah konfigurasi resmi proyek sekolah tersedia.
// Dengan ini build pengembangan lama tetap dapat berjalan tanpa google-services.json.
if (file("google-services.json").exists()) {
    apply(plugin = "com.google.gms.google-services")
}

// Secret lokal, tidak disimpan dalam Git. Override opsional untuk CI/pengujian.
val keystorePropertiesFile = rootProject.file(
    providers.gradleProperty("nusaSigningProperties").orElse("key.properties").get(),
)
val keystoreProperties = Properties()
if (keystorePropertiesFile.isFile) {
    try {
        keystorePropertiesFile.inputStream().use { keystoreProperties.load(it) }
    } catch (_: Exception) {
        throw GradleException("Konfigurasi signing NUSA tidak dapat dibaca. Periksa key.properties secara lokal.")
    }
}
val uploadKeystoreFile = keystoreProperties.getProperty("storeFile")
    ?.takeIf { it.isNotBlank() }
    ?.let { rootProject.file(it) }

val validateNusaReleaseSigning = tasks.register("validateNusaReleaseSigning") {
    group = "verification"
    description = "Memastikan release NUSA memakai upload key, tanpa fallback ke debug key."
    doLast {
        if (!keystorePropertiesFile.isFile) {
            throw GradleException("Release NUSA memerlukan android/key.properties dan upload key sekolah. Debug tetap dapat dibangun tanpa file tersebut.")
        }
        val requiredProperties = listOf("storeFile", "storePassword", "keyAlias", "keyPassword")
        if (requiredProperties.any { keystoreProperties.getProperty(it).isNullOrBlank() }) {
            throw GradleException("Konfigurasi signing NUSA belum lengkap. Isi storeFile, storePassword, keyAlias, dan keyPassword secara lokal; jangan membagikan nilainya.")
        }
        if (uploadKeystoreFile?.isFile != true) {
            throw GradleException("Upload keystore NUSA tidak ditemukan. Periksa storeFile dalam key.properties secara lokal.")
        }
    }
}

android {
    namespace = "id.sch.smpn2padangpanjang.nusa"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "id.sch.smpn2padangpanjang.nusa"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        // image_picker stabil terbaru mendukung Android 7.0 (API 24) ke atas.
        minSdk = 24
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        create("release") {
            keyAlias = keystoreProperties.getProperty("keyAlias")
            keyPassword = keystoreProperties.getProperty("keyPassword")
            storeFile = uploadKeystoreFile
            storePassword = keystoreProperties.getProperty("storePassword")
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("release")
        }
    }
}

tasks.configureEach {
    if (name == "preReleaseBuild" || name == "validateSigningRelease") {
        dependsOn(validateNusaReleaseSigning)
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
