package id.sch.smpn2padangpanjang.nusa

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import java.util.concurrent.atomic.AtomicBoolean
import android.view.WindowManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import com.google.android.play.core.appupdate.AppUpdateManagerFactory
import com.google.android.play.core.install.model.UpdateAvailability

class MainActivity : FlutterActivity() {
    private val examSecurityChannel = "id.sch.smpn2padangpanjang.nusa/exam_security"
    private var examProtectionActive = false
    private var overlaysHidden = false

    private fun examSecurityStatus(): Map<String, Any> = mapOf(
        "protocol" to 1,
        "apiLevel" to Build.VERSION.SDK_INT,
        "active" to examProtectionActive,
        "secureScreen" to ((window.attributes.flags and WindowManager.LayoutParams.FLAG_SECURE) != 0),
        "overlaysHidden" to overlaysHidden,
        "multiWindow" to isInMultiWindowMode,
    )

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                "nusa_notifications",
                "Notifikasi NUSA",
                NotificationManager.IMPORTANCE_HIGH,
            ).apply {
                description = "Informasi akademik dan kegiatan sekolah dari NUSA"
            }
            val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            "id.sch.smpn2padangpanjang.nusa/app_updates",
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "checkForUpdate" -> {
                    if (examProtectionActive) {
                        result.success(null)
                    } else {
                        try {
                            @Suppress("DEPRECATION")
                            val installed = packageManager.getPackageInfo(packageName, 0).let {
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) it.longVersionCode.toInt() else it.versionCode
                            }
                            val task = AppUpdateManagerFactory.create(this).appUpdateInfo
                            val completed = AtomicBoolean(false)
                            val handler = Handler(Looper.getMainLooper())
                            val timeout = Runnable { if (completed.compareAndSet(false, true)) result.success(null) }
                            handler.postDelayed(timeout, 10_000)
                            task
                                .addOnSuccessListener { info ->
                                    if (completed.compareAndSet(false, true)) {
                                        handler.removeCallbacks(timeout)
                                        result.success(if (info.updateAvailability() == UpdateAvailability.UPDATE_AVAILABLE
                                            && info.availableVersionCode() > installed) info.availableVersionCode() else null)
                                    }
                                }
                                .addOnFailureListener {
                                    if (completed.compareAndSet(false, true)) {
                                        handler.removeCallbacks(timeout)
                                        result.success(null)
                                    }
                                }
                        } catch (_: Exception) {
                            result.success(null)
                        }
                    }
                }
                "openPlayStore" -> {
                    if (examProtectionActive) {
                        result.success(false)
                    } else {
                        try {
                            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("market://details?id=$packageName"))
                                .setPackage("com.android.vending"))
                            result.success(true)
                        } catch (_: Exception) {
                            try {
                                startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://play.google.com/store/apps/details?id=$packageName")))
                                result.success(true)
                            } catch (_: Exception) {
                                result.success(false)
                            }
                        }
                    }
                }
                else -> result.notImplemented()
            }
        }
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            "id.sch.smpn2padangpanjang.nusa/push_notifications",
        ).setMethodCallHandler { call, result ->
            if (call.method == "clearNotifications") {
                val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
                manager.cancelAll()
                result.success(null)
            } else {
                result.notImplemented()
            }
        }
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, examSecurityChannel)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "setSecureScreen" -> {
                        val enabled = call.argument<Boolean>("enabled") ?: false
                        val active = call.argument<Boolean>("active") ?: enabled
                        runOnUiThread {
                            try {
                                if (enabled) {
                                    window.addFlags(WindowManager.LayoutParams.FLAG_SECURE)
                                } else {
                                    window.clearFlags(WindowManager.LayoutParams.FLAG_SECURE)
                                }
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                                    window.setHideOverlayWindows(active)
                                    overlaysHidden = active
                                }
                                examProtectionActive = active
                                // Acknowledge only after the UI-thread operations have completed.
                                result.success(examSecurityStatus())
                            } catch (_: Exception) {
                                examProtectionActive = false
                                result.error("SCREEN_PROTECTION_FAILED", "Proteksi layar tidak dapat dipasang pada perangkat ini.", null)
                            }
                        }
                    }
                    "getSecurityStatus" -> runOnUiThread { result.success(examSecurityStatus()) }
                    "isMultiWindow" -> result.success(isInMultiWindowMode)
                    else -> result.notImplemented()
                }
            }
    }
}
