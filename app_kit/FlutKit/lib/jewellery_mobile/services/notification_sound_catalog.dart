class AppNotificationSound {
  const AppNotificationSound({
    required this.key,
    required this.label,
    required this.description,
    required this.resourceName,
    required this.channelId,
  });

  final String key;
  final String label;
  final String description;
  final String resourceName;
  final String channelId;
}

class NotificationSoundCatalog {
  NotificationSoundCatalog._();

  static const String defaultKey = 'signature';

  static const List<AppNotificationSound> sounds = [
    AppNotificationSound(
      key: 'signature',
      label: 'Aabhushan Signature',
      description: 'The familiar Aabhushan notification sound.',
      resourceName: 'aabhushan_alert',
      channelId: 'aabhushan_alerts_v3',
    ),
    AppNotificationSound(
      key: 'crystal',
      label: 'Crystal Chime',
      description: 'A clean, sparkling two-note chime.',
      resourceName: 'aabhushan_crystal',
      channelId: 'aabhushan_sound_crystal_v1',
    ),
    AppNotificationSound(
      key: 'royal',
      label: 'Royal Rise',
      description: 'A warm three-note rising tone.',
      resourceName: 'aabhushan_royal',
      channelId: 'aabhushan_sound_royal_v1',
    ),
    AppNotificationSound(
      key: 'sparkle',
      label: 'Diamond Sparkle',
      description: 'A bright, quick sparkle for new activity.',
      resourceName: 'aabhushan_sparkle',
      channelId: 'aabhushan_sound_sparkle_v1',
    ),
    AppNotificationSound(
      key: 'classic',
      label: 'Classic Bell',
      description: 'A balanced double bell that is easy to notice.',
      resourceName: 'aabhushan_classic',
      channelId: 'aabhushan_sound_classic_v1',
    ),
    AppNotificationSound(
      key: 'gentle',
      label: 'Gentle Glow',
      description: 'A soft tone for quieter environments.',
      resourceName: 'aabhushan_gentle',
      channelId: 'aabhushan_sound_gentle_v1',
    ),
    AppNotificationSound(
      key: 'bright',
      label: 'Bright Ping',
      description: 'A crisp high-frequency notification ping.',
      resourceName: 'aabhushan_bright',
      channelId: 'aabhushan_sound_bright_v1',
    ),
    AppNotificationSound(
      key: 'urgent',
      label: 'Urgent Pulse',
      description: 'A repeating pulse for time-sensitive work.',
      resourceName: 'aabhushan_urgent',
      channelId: 'aabhushan_sound_urgent_v1',
    ),
    AppNotificationSound(
      key: 'minimal',
      label: 'Minimal Tap',
      description: 'A very short, discreet tap.',
      resourceName: 'aabhushan_minimal',
      channelId: 'aabhushan_sound_minimal_v1',
    ),
    AppNotificationSound(
      key: 'reminder',
      label: 'Reminder Steps',
      description: 'A clear stepped melody for reminders.',
      resourceName: 'aabhushan_reminder',
      channelId: 'aabhushan_sound_reminder_v1',
    ),
  ];

  static AppNotificationSound resolve(String? key) {
    final normalized = key?.trim().toLowerCase();
    return sounds.firstWhere(
      (sound) => sound.key == normalized,
      orElse: () => sounds.first,
    );
  }
}
