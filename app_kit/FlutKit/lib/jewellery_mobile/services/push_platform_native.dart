import 'dart:async';

import 'package:flutkit/jewellery_mobile/services/push_platform_snapshot.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

class PlatformPushClient {
  bool _initialized = false;
  bool _observersRegistered = false;
  String? _externalUserId;
  late void Function(PushPlatformSnapshot snapshot) _onStatus;
  late void Function(Map<String, dynamic> payload) _onNotificationOpened;
  late void Function() _onNotificationForeground;

  Future<PushPlatformSnapshot> init({
    required String appId,
    required void Function(PushPlatformSnapshot snapshot) onStatus,
    required void Function(Map<String, dynamic> payload) onNotificationOpened,
    required void Function() onNotificationForeground,
  }) async {
    _onStatus = onStatus;
    _onNotificationOpened = onNotificationOpened;
    _onNotificationForeground = onNotificationForeground;
    if (!_initialized) {
      await OneSignal.initialize(appId);
      _initialized = true;
      _registerObservers();
    }
    return snapshot();
  }

  Future<PushPlatformSnapshot> login({
    required String externalId,
    required String email,
    required String name,
  }) async {
    await OneSignal.login(externalId);
    await OneSignal.User.addTags({'email': email.trim(), 'name': name.trim()});
    _externalUserId = externalId;

    var granted = OneSignal.Notifications.permission;
    if (!granted && await OneSignal.Notifications.canRequest()) {
      granted = await OneSignal.Notifications.requestPermission(false);
    }
    if (granted) {
      await OneSignal.User.pushSubscription.optIn();
    }
    return snapshot();
  }

  Future<PushPlatformSnapshot> requestPermission({
    bool fallbackToSettings = true,
  }) async {
    final granted = await OneSignal.Notifications.requestPermission(
      fallbackToSettings,
    );
    if (granted) {
      await OneSignal.User.pushSubscription.optIn();
    }
    return snapshot();
  }

  Future<PushPlatformSnapshot> snapshot() async {
    if (!_initialized) return const PushPlatformSnapshot();
    final subscription = OneSignal.User.pushSubscription;
    return PushPlatformSnapshot(
      initialized: true,
      permissionGranted: OneSignal.Notifications.permission,
      canRequestPermission: await OneSignal.Notifications.canRequest(),
      optedIn: subscription.optedIn ?? false,
      subscriptionId: subscription.id,
      pushToken: subscription.token,
      externalUserId: _externalUserId,
    );
  }

  Future<PushPlatformSnapshot> logout() async {
    if (!_initialized) return const PushPlatformSnapshot();
    await OneSignal.logout();
    _externalUserId = null;
    return snapshot();
  }

  void _registerObservers() {
    if (_observersRegistered) return;
    _observersRegistered = true;

    OneSignal.Notifications.addPermissionObserver((_) {
      unawaited(_emitStatus());
    });
    OneSignal.User.pushSubscription.addObserver((_) {
      unawaited(_emitStatus());
    });
    OneSignal.Notifications.addForegroundWillDisplayListener((_) {
      _onNotificationForeground();
    });
    OneSignal.Notifications.addClickListener((event) {
      final data = event.notification.additionalData;
      _onNotificationOpened(
        data == null ? <String, dynamic>{} : Map<String, dynamic>.from(data),
      );
    });
  }

  Future<void> _emitStatus() async {
    try {
      _onStatus(await snapshot());
    } catch (_) {}
  }
}
