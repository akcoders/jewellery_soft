import 'package:onesignal_flutter/onesignal_flutter.dart';

class OneSignalService {
  OneSignalService._();

  static const String appId = '47e56c4c-5cec-4de4-a247-d1c62c1154ae';
  static bool _initialized = false;

  static Future<void> init() async {
    if (_initialized) return;

    OneSignal.initialize(appId);
    await OneSignal.Notifications.requestPermission(true);
    await OneSignal.User.pushSubscription.optIn();
    _initialized = true;
  }

  static Future<void> syncUser({
    required String userEmail,
    required String userName,
  }) async {
    await init();

    final externalId = _normalizedExternalId(userEmail, userName);
    if (externalId == null) {
      return;
    }

    OneSignal.login(externalId);
    OneSignal.User.addTags({
      'email': userEmail.trim(),
      'name': userName.trim(),
    });
  }

  static Future<void> clearUser() async {
    if (!_initialized) return;
    await OneSignal.logout();
  }

  static String? _normalizedExternalId(String userEmail, String userName) {
    final email = userEmail.trim().toLowerCase();
    if (email.isNotEmpty) {
      return email;
    }

    final name = userName.trim().toLowerCase().replaceAll(RegExp(r'\s+'), '_');
    if (name.isEmpty) {
      return null;
    }

    final restricted = <String>{
      'na',
      'null',
      '0',
      '1',
      '-1',
      'all',
      'nan',
      '-',
      'none',
      'ok',
      '123abc',
      'unknown',
      'invalid_user',
      'undefined',
      'not_set',
      'unqualified',
      '00000000-0000-0000-0000-000000000000',
    };
    if (restricted.contains(name)) {
      return null;
    }

    return name;
  }
}
