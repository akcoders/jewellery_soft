package com.aabhushan.erp

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationChannelGroup
import android.app.NotificationManager
import android.content.Context
import android.media.AudioAttributes
import android.net.Uri
import android.os.Build

class AabhushanApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        createNotificationChannels()
    }

    private fun createNotificationChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val audioAttributes = AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build()
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.createNotificationChannelGroup(
            NotificationChannelGroup(SOUND_CHANNEL_GROUP_ID, "Notification sound choices"),
        )

        SOUND_CHANNELS.forEach { sound ->
            val soundUri = Uri.parse("android.resource://$packageName/${sound.resourceId}")
            val channel = NotificationChannel(
                sound.channelId,
                sound.label,
                NotificationManager.IMPORTANCE_HIGH,
            ).apply {
                description = "Task and follow-up reminders using ${sound.label}"
                group = SOUND_CHANNEL_GROUP_ID
                enableVibration(true)
                setShowBadge(true)
                setSound(soundUri, audioAttributes)
            }
            manager.createNotificationChannel(channel)
        }
    }

    companion object {
        private const val SOUND_CHANNEL_GROUP_ID = "aabhushan_sound_choices"

        private data class SoundChannel(
            val channelId: String,
            val label: String,
            val resourceId: Int,
        )

        private val SOUND_CHANNELS = listOf(
            SoundChannel("aabhushan_alerts_v3", "Aabhushan Signature", R.raw.aabhushan_alert),
            SoundChannel("aabhushan_sound_crystal_v1", "Crystal Chime", R.raw.aabhushan_crystal),
            SoundChannel("aabhushan_sound_royal_v1", "Royal Rise", R.raw.aabhushan_royal),
            SoundChannel("aabhushan_sound_sparkle_v1", "Diamond Sparkle", R.raw.aabhushan_sparkle),
            SoundChannel("aabhushan_sound_classic_v1", "Classic Bell", R.raw.aabhushan_classic),
            SoundChannel("aabhushan_sound_gentle_v1", "Gentle Glow", R.raw.aabhushan_gentle),
            SoundChannel("aabhushan_sound_bright_v1", "Bright Ping", R.raw.aabhushan_bright),
            SoundChannel("aabhushan_sound_urgent_v1", "Urgent Pulse", R.raw.aabhushan_urgent),
            SoundChannel("aabhushan_sound_minimal_v1", "Minimal Tap", R.raw.aabhushan_minimal),
            SoundChannel("aabhushan_sound_reminder_v1", "Reminder Steps", R.raw.aabhushan_reminder),
        )
    }
}
