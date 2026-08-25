import 'dart:async';

import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutter/foundation.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

@immutable
class PushNotificationStatus {
  const PushNotificationStatus({
    this.initialized = false,
    this.permissionGranted = false,
    this.canRequestPermission = false,
    this.optedIn = false,
    this.subscriptionId,
    this.pushToken,
    this.externalUserId,
    this.error,
  });

  final bool initialized;
  final bool permissionGranted;
  final bool canRequestPermission;
  final bool optedIn;
  final String? subscriptionId;
  final String? pushToken;
  final String? externalUserId;
  final String? error;

  bool get isReady =>
      initialized &&
      permissionGranted &&
      optedIn &&
      (subscriptionId?.isNotEmpty ?? false) &&
      (pushToken?.isNotEmpty ?? false) &&
      (externalUserId?.isNotEmpty ?? false);

  PushNotificationStatus copyWith({
    bool? initialized,
    bool? permissionGranted,
    bool? canRequestPermission,
    bool? optedIn,
    String? subscriptionId,
    String? pushToken,
    String? externalUserId,
    String? error,
    bool clearSubscriptionId = false,
    bool clearPushToken = false,
    bool clearExternalUserId = false,
    bool clearError = false,
  }) {
    return PushNotificationStatus(
      initialized: initialized ?? this.initialized,
      permissionGranted: permissionGranted ?? this.permissionGranted,
      canRequestPermission: canRequestPermission ?? this.canRequestPermission,
      optedIn: optedIn ?? this.optedIn,
      subscriptionId: clearSubscriptionId
          ? null
          : subscriptionId ?? this.subscriptionId,
      pushToken: clearPushToken ? null : pushToken ?? this.pushToken,
      externalUserId: clearExternalUserId
          ? null
          : externalUserId ?? this.externalUserId,
      error: clearError ? null : error ?? this.error,
    );
  }
}

class OneSignalService {
  OneSignalService._();

  static const String appId = '47e56c4c-5cec-4de4-a247-d1c62c1154ae';
  static bool _initialized = false;
  static bool _observersRegistered = false;
  static Future<PushNotificationStatus>? _initializing;

  static final ValueNotifier<PushNotificationStatus> status =
      ValueNotifier<PushNotificationStatus>(const PushNotificationStatus());

  static final ValueNotifier<Map<String, dynamic>?> openedNotification =
      ValueNotifier<Map<String, dynamic>?>(null);

  static Future<PushNotificationStatus> init() async {
    if (_initialized) return refreshStatus();
    final pending = _initializing;
    if (pending != null) return pending;

    final future = _initialize();
    _initializing = future;
    try {
      return await future;
    } finally {
      _initializing = null;
    }
  }

  static Future<PushNotificationStatus> syncUser({
    required String userEmail,
    required String userName,
  }) async {
    final initializedStatus = await init();
    if (!initializedStatus.initialized) return initializedStatus;

    final externalId = externalIdForUser(userEmail, userName);
    if (externalId == null) {
      status.value = status.value.copyWith(
        error: 'A valid user identity is required for push notifications.',
        clearExternalUserId: true,
      );
      return status.value;
    }

    try {
      await OneSignal.login(externalId);
      await OneSignal.User.addTags({
        'email': userEmail.trim(),
        'name': userName.trim(),
      });
      status.value = status.value.copyWith(
        externalUserId: externalId,
        clearError: true,
      );
      return await _ensurePermissionAndOptIn();
    } catch (error) {
      status.value = status.value.copyWith(error: error.toString());
      return status.value;
    }
  }

  static Future<PushNotificationStatus> requestPermission({
    bool fallbackToSettings = true,
  }) async {
    final initializedStatus = await init();
    if (!initializedStatus.initialized) return initializedStatus;

    try {
      final granted = await OneSignal.Notifications.requestPermission(
        fallbackToSettings,
      );
      if (granted) {
        await OneSignal.User.pushSubscription.optIn();
      }
      return await refreshStatus(clearError: true);
    } catch (error) {
      status.value = status.value.copyWith(error: error.toString());
      return status.value;
    }
  }

  static Future<PushNotificationStatus> refreshStatus({
    bool clearError = false,
  }) async {
    if (!_initialized) return status.value;

    try {
      final canRequest = await OneSignal.Notifications.canRequest();
      final subscription = OneSignal.User.pushSubscription;
      status.value = status.value.copyWith(
        initialized: true,
        permissionGranted: OneSignal.Notifications.permission,
        canRequestPermission: canRequest,
        optedIn: subscription.optedIn ?? false,
        subscriptionId: subscription.id,
        pushToken: subscription.token,
        clearSubscriptionId: subscription.id == null,
        clearPushToken: subscription.token == null,
        clearError: clearError,
      );
    } catch (error) {
      status.value = status.value.copyWith(error: error.toString());
    }
    return status.value;
  }

  static Future<void> clearUser() async {
    if (!_initialized) return;
    try {
      await OneSignal.logout();
      openedNotification.value = null;
      status.value = status.value.copyWith(
        clearExternalUserId: true,
        clearError: true,
      );
      await refreshStatus(clearError: true);
    } catch (error) {
      status.value = status.value.copyWith(error: error.toString());
    }
  }

  static Map<String, dynamic>? consumeOpenedNotification() {
    final payload = openedNotification.value;
    openedNotification.value = null;
    return payload;
  }

  @visibleForTesting
  static String? externalIdForUser(String userEmail, String userName) {
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

  static Future<PushNotificationStatus> _initialize() async {
    try {
      await OneSignal.initialize(appId);
      _initialized = true;
      _registerObservers();
      return await refreshStatus(clearError: true);
    } catch (error) {
      _initialized = false;
      status.value = status.value.copyWith(
        initialized: false,
        error: error.toString(),
      );
      return status.value;
    }
  }

  static Future<PushNotificationStatus> _ensurePermissionAndOptIn() async {
    try {
      var granted = OneSignal.Notifications.permission;
      if (!granted && await OneSignal.Notifications.canRequest()) {
        granted = await OneSignal.Notifications.requestPermission(false);
      }
      if (granted) {
        await OneSignal.User.pushSubscription.optIn();
      }
      return await refreshStatus(clearError: true);
    } catch (error) {
      status.value = status.value.copyWith(error: error.toString());
      return status.value;
    }
  }

  static void _registerObservers() {
    if (_observersRegistered) return;
    _observersRegistered = true;

    OneSignal.Notifications.addPermissionObserver((granted) {
      status.value = status.value.copyWith(
        permissionGranted: granted,
        clearError: true,
      );
      unawaited(refreshStatus(clearError: true));
    });
    OneSignal.User.pushSubscription.addObserver((changes) {
      status.value = status.value.copyWith(
        optedIn: changes.current.optedIn,
        subscriptionId: changes.current.id,
        pushToken: changes.current.token,
        clearSubscriptionId: changes.current.id == null,
        clearPushToken: changes.current.token == null,
        clearError: true,
      );
    });
    OneSignal.Notifications.addForegroundWillDisplayListener((event) {
      TaskRefreshBus.notify();
    });
    OneSignal.Notifications.addClickListener((event) {
      final data = event.notification.additionalData;
      openedNotification.value = data == null
          ? <String, dynamic>{}
          : Map<String, dynamic>.unmodifiable(data);
      TaskRefreshBus.notify();
    });
  }
}
