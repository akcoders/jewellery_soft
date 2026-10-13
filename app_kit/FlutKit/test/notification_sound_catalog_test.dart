import 'package:flutkit/jewellery_mobile/services/notification_sound_catalog.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('notification sound catalog contains ten unique bundled choices', () {
    expect(NotificationSoundCatalog.sounds, hasLength(10));
    expect(
      NotificationSoundCatalog.sounds.map((sound) => sound.key).toSet(),
      hasLength(10),
    );
    expect(
      NotificationSoundCatalog.sounds.map((sound) => sound.channelId).toSet(),
      hasLength(10),
    );
    expect(
      NotificationSoundCatalog.sounds
          .map((sound) => sound.resourceName)
          .toSet(),
      hasLength(10),
    );
  });

  test('unknown sound keys safely resolve to the signature sound', () {
    final sound = NotificationSoundCatalog.resolve('user-controlled-value');

    expect(sound.key, NotificationSoundCatalog.defaultKey);
    expect(sound.channelId, 'aabhushan_alerts_v3');
    expect(sound.resourceName, 'aabhushan_alert');
  });
}
