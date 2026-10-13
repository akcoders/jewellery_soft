import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class NotificationSoundPreviewService {
  NotificationSoundPreviewService._();

  static const MethodChannel _channel = MethodChannel(
    'com.aabhushan.erp/notification_sound',
  );

  static bool get isSupported =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  static Future<void> play(String resourceName) async {
    if (!isSupported) return;
    await _channel.invokeMethod<bool>('previewSound', {
      'resource': resourceName,
    });
  }

  static Future<void> stop() async {
    if (!isSupported) return;
    await _channel.invokeMethod<bool>('stopPreview');
  }
}
