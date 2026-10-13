package com.aabhushan.erp

import android.content.ContentValues
import android.os.Build
import android.media.MediaPlayer
import android.os.Environment
import android.provider.MediaStore
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.embedding.android.FlutterActivity
import io.flutter.plugin.common.MethodChannel
import java.io.File

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
            DOWNLOADS_CHANNEL,
        ).setMethodCallHandler { call, result ->
            if (call.method != "savePdf") {
                result.notImplemented()
                return@setMethodCallHandler
            }
            try {
                val bytes = call.argument<ByteArray>("bytes")
                    ?: throw IllegalArgumentException("PDF content is missing.")
                val fileName = call.argument<String>("fileName")
                    ?.let(::safeFileName)
                    ?: throw IllegalArgumentException("PDF file name is missing.")
                val folderName = call.argument<String>("folderName")
                    ?.let(::safeFolderName)
                    ?.takeIf { it.isNotEmpty() }
                result.success(savePdfToDownloads(bytes, fileName, folderName))
            } catch (error: Exception) {
                result.error("PDF_SAVE_FAILED", error.message ?: "Unable to save PDF.", null)
            }
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

    private fun savePdfToDownloads(bytes: ByteArray, fileName: String, folderName: String?): String {
        val relativePath = listOfNotNull(
            Environment.DIRECTORY_DOWNLOADS,
            "Aabhushan",
            folderName,
        ).joinToString(File.separator)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            val values = ContentValues().apply {
                put(MediaStore.MediaColumns.DISPLAY_NAME, fileName)
                put(MediaStore.MediaColumns.MIME_TYPE, "application/pdf")
                put(MediaStore.MediaColumns.RELATIVE_PATH, relativePath)
                put(MediaStore.MediaColumns.IS_PENDING, 1)
            }
            val uri = contentResolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values)
                ?: throw IllegalStateException("Android could not create the Downloads file.")
            try {
                contentResolver.openOutputStream(uri)?.use { output ->
                    output.write(bytes)
                } ?: throw IllegalStateException("Android could not open the Downloads file.")
                val published = ContentValues().apply {
                    put(MediaStore.MediaColumns.IS_PENDING, 0)
                }
                contentResolver.update(uri, published, null, null)
                return "$relativePath/$fileName"
            } catch (error: Exception) {
                contentResolver.delete(uri, null, null)
                throw error
            }
        }

        val directory = File(
            getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS),
            listOfNotNull("Aabhushan", folderName).joinToString(File.separator),
        )
        if (!directory.exists() && !directory.mkdirs()) {
            throw IllegalStateException("Android could not create the app Downloads folder.")
        }
        File(directory, fileName).writeBytes(bytes)
        return File(directory, fileName).absolutePath
    }

    private fun safeFileName(value: String): String {
        val name = value.substringAfterLast('/').substringAfterLast('\\')
            .replace(Regex("[^A-Za-z0-9._-]"), "_")
        if (name.isBlank() || name == "." || name == "..") {
            throw IllegalArgumentException("PDF file name is invalid.")
        }
        return name
    }

    private fun safeFolderName(value: String): String =
        value.replace(Regex("[^A-Za-z0-9 _-]"), "").trim().take(60)

    companion object {
        private const val APP_INFO_CHANNEL = "com.aabhushan.erp/app_info"
        private const val DOWNLOADS_CHANNEL = "com.aabhushan.erp/downloads"
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
