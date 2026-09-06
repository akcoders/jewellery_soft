import 'package:flutkit/jewellery_mobile/services/push_platform_snapshot.dart';

class PlatformPushClient {
  Future<PushPlatformSnapshot> init({
    required String appId,
    String? safariWebId,
    required void Function(PushPlatformSnapshot snapshot) onStatus,
    required void Function(Map<String, dynamic> payload) onNotificationOpened,
    required void Function() onNotificationForeground,
  }) async => const PushPlatformSnapshot(
    permissionState: 'unsupported',
    error: 'Push notifications are not supported on this platform.',
  );

  Future<PushPlatformSnapshot> login({
    required String externalId,
    required String email,
    required String name,
  }) async => const PushPlatformSnapshot();

  Future<PushPlatformSnapshot> requestPermission({
    bool fallbackToSettings = true,
  }) async => const PushPlatformSnapshot();

  Future<PushPlatformSnapshot> snapshot() async => const PushPlatformSnapshot();

  Future<PushPlatformSnapshot> logout() async => const PushPlatformSnapshot();
}
