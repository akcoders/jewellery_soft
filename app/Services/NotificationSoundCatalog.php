<?php

namespace App\Services;

final class NotificationSoundCatalog
{
    public const DEFAULT_KEY = 'signature';

    /** @var array<string, array{label: string, description: string, android_channel_id: string, android_resource: string}> */
    private const SOUNDS = [
        'signature' => [
            'label' => 'Aabhushan Signature',
            'description' => 'The familiar Aabhushan notification sound.',
            'android_channel_id' => 'aabhushan_alerts_v3',
            'android_resource' => 'aabhushan_alert',
        ],
        'crystal' => [
            'label' => 'Crystal Chime',
            'description' => 'A clean, sparkling two-note chime.',
            'android_channel_id' => 'aabhushan_sound_crystal_v1',
            'android_resource' => 'aabhushan_crystal',
        ],
        'royal' => [
            'label' => 'Royal Rise',
            'description' => 'A warm three-note rising tone.',
            'android_channel_id' => 'aabhushan_sound_royal_v1',
            'android_resource' => 'aabhushan_royal',
        ],
        'sparkle' => [
            'label' => 'Diamond Sparkle',
            'description' => 'A bright, quick sparkle for new activity.',
            'android_channel_id' => 'aabhushan_sound_sparkle_v1',
            'android_resource' => 'aabhushan_sparkle',
        ],
        'classic' => [
            'label' => 'Classic Bell',
            'description' => 'A balanced double bell that is easy to notice.',
            'android_channel_id' => 'aabhushan_sound_classic_v1',
            'android_resource' => 'aabhushan_classic',
        ],
        'gentle' => [
            'label' => 'Gentle Glow',
            'description' => 'A soft tone for quieter environments.',
            'android_channel_id' => 'aabhushan_sound_gentle_v1',
            'android_resource' => 'aabhushan_gentle',
        ],
        'bright' => [
            'label' => 'Bright Ping',
            'description' => 'A crisp high-frequency notification ping.',
            'android_channel_id' => 'aabhushan_sound_bright_v1',
            'android_resource' => 'aabhushan_bright',
        ],
        'urgent' => [
            'label' => 'Urgent Pulse',
            'description' => 'A repeating pulse for time-sensitive work.',
            'android_channel_id' => 'aabhushan_sound_urgent_v1',
            'android_resource' => 'aabhushan_urgent',
        ],
        'minimal' => [
            'label' => 'Minimal Tap',
            'description' => 'A very short, discreet tap.',
            'android_channel_id' => 'aabhushan_sound_minimal_v1',
            'android_resource' => 'aabhushan_minimal',
        ],
        'reminder' => [
            'label' => 'Reminder Steps',
            'description' => 'A clear stepped melody for reminders.',
            'android_channel_id' => 'aabhushan_sound_reminder_v1',
            'android_resource' => 'aabhushan_reminder',
        ],
    ];

    public static function normalize(?string $key): string
    {
        $normalized = strtolower(trim((string) $key));
        return isset(self::SOUNDS[$normalized]) ? $normalized : self::DEFAULT_KEY;
    }

    public static function isAllowed(?string $key): bool
    {
        return isset(self::SOUNDS[strtolower(trim((string) $key))]);
    }

    public static function channelId(?string $key): string
    {
        return self::SOUNDS[self::normalize($key)]['android_channel_id'];
    }

    public static function resourceName(?string $key): string
    {
        return self::SOUNDS[self::normalize($key)]['android_resource'];
    }

    /** @return list<array{key: string, label: string, description: string, android_channel_id: string, android_resource: string}> */
    public static function options(): array
    {
        $options = [];
        foreach (self::SOUNDS as $key => $sound) {
            $options[] = ['key' => $key] + $sound;
        }

        return $options;
    }
}
