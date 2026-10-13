package com.aabhushan.erp

import android.os.Build
import android.media.MediaPlayer
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.embedding.android.FlutterActivity
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private var previewPlayer: MediaPlayer? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            APP_INFO_CHANNEL,
        ).setMethodCallHandler { call, result ->
            if (call.method != "getAppInfo") {
                result.notImplemented()
                return@setMethodCallHandler
            }

            val packageInfo = packageManager.getPackageInfo(packageName, 0)
            val buildNumber = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                packageInfo.longVersionCode
            } else {
                @Suppress("DEPRECATION")
                packageInfo.versionCode.toLong()
            }
            result.success(
                mapOf(
                    "version" to (packageInfo.versionName ?: ""),
                    "build_number" to buildNumber,
                ),
            )
        }

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            NOTIFICATION_SOUND_CHANNEL,
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "previewSound" -> {
                    val resourceName = call.argument<String>("resource")?.trim().orEmpty()
                    if (!ALLOWED_SOUND_RESOURCES.contains(resourceName)) {
                        result.error("INVALID_SOUND", "Unknown bundled sound.", null)
                        return@setMethodCallHandler
                    }

                    val resourceId = resources.getIdentifier(resourceName, "raw", packageName)
                    if (resourceId == 0) {
                        result.error("MISSING_SOUND", "Bundled sound is unavailable.", null)
                        return@setMethodCallHandler
                    }

                    releasePreviewPlayer()
                    previewPlayer = MediaPlayer.create(this, resourceId)?.apply {
                        setOnCompletionListener { releasePreviewPlayer() }
                        start()
                    }
                    result.success(previewPlayer != null)
                }
                "stopPreview" -> {
                    releasePreviewPlayer()
                    result.success(true)
                }
                else -> result.notImplemented()
            }
        }
    }

    override fun onDestroy() {
        releasePreviewPlayer()
        super.onDestroy()
    }

    private fun releasePreviewPlayer() {
        previewPlayer?.let { player ->
            if (player.isPlaying) player.stop()
            player.reset()
            player.release()
        }
        previewPlayer = null
    }

    companion object {
        private const val APP_INFO_CHANNEL = "com.aabhushan.erp/app_info"
        private const val NOTIFICATION_SOUND_CHANNEL = "com.aabhushan.erp/notification_sound"
        private val ALLOWED_SOUND_RESOURCES = setOf(
            "aabhushan_alert",
            "aabhushan_crystal",
            "aabhushan_royal",
            "aabhushan_sparkle",
            "aabhushan_classic",
            "aabhushan_gentle",
            "aabhushan_bright",
            "aabhushan_urgent",
            "aabhushan_minimal",
            "aabhushan_reminder",
        )
    }
}
