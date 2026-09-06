import 'package:flutkit/jewellery_mobile/services/push_platform.dart';
import 'package:flutkit/jewellery_mobile/services/push_platform_snapshot.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutter/foundation.dart';

@immutable
class PushNotificationStatus {
  const PushNotificationStatus({
    this.initialized = false,
    this.permissionGranted = false,
    this.permissionState = 'unknown',
    this.canRequestPermission = false,
    this.optedIn = false,
    this.subscriptionId,
    this.pushToken,
    this.externalUserId,
    this.error,
  });

  final bool initialized;
  final bool permissionGranted;
  final String permissionState;
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
    String? permissionState,
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
      permissionState: permissionState ?? this.permissionState,
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
  static const String safariWebId =
      'web.onesignal.auto.0a17e090-f65a-43cf-871a-056959ed633a';
  static final PlatformPushClient _client = PlatformPushClient();
  static bool _initialized = false;
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
      return _setError(
        'A valid user identity is required for push notifications.',
      );
    }

    try {
      return _apply(
        await _client.login(
          externalId: externalId,
          email: userEmail.trim(),
          name: userName.trim(),
        ),
        externalUserId: externalId,
      );
    } catch (error) {
      return _setError(error.toString());
    }
  }

  static Future<PushNotificationStatus> requestPermission({
    bool fallbackToSettings = true,
  }) async {
    final initializedStatus = await init();
    if (!initializedStatus.initialized) return initializedStatus;
    try {
      return _apply(
        await _client.requestPermission(fallbackToSettings: fallbackToSettings),
      );
    } catch (error) {
      return _setError(error.toString());
    }
  }

  static Future<PushNotificationStatus> refreshStatus({
    bool clearError = false,
  }) async {
    if (!_initialized) return status.value;
    try {
      return _apply(await _client.snapshot(), clearError: clearError);
    } catch (error) {
      return _setError(error.toString());
    }
  }

  static Future<void> clearUser() async {
    if (!_initialized) return;
    try {
      openedNotification.value = null;
      _apply(
        await _client.logout(),
        clearExternalUserId: true,
        clearError: true,
      );
    } catch (error) {
      _setError(error.toString());
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
    if (email.isNotEmpty) return email;

    final name = userName.trim().toLowerCase().replaceAll(RegExp(r'\s+'), '_');
    if (name.isEmpty) return null;
    const restricted = <String>{
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
    return restricted.contains(name) ? null : name;
  }

  static Future<PushNotificationStatus> _initialize() async {
    try {
      final snapshot = await _client.init(
        appId: appId,
        safariWebId: safariWebId,
        onStatus: _apply,
        onNotificationOpened: (payload) {
          openedNotification.value = Map<String, dynamic>.unmodifiable(payload);
          TaskRefreshBus.notify();
        },
        onNotificationForeground: TaskRefreshBus.notify,
      );
      _initialized = snapshot.initialized;
      return _apply(snapshot, clearError: true);
    } catch (error) {
      _initialized = false;
      return _setError(error.toString(), initialized: false);
    }
  }

  static PushNotificationStatus _apply(
    PushPlatformSnapshot snapshot, {
    String? externalUserId,
    bool clearExternalUserId = false,
    bool clearError = false,
  }) {
    final current = status.value;
    final next = PushNotificationStatus(
      initialized: snapshot.initialized,
      permissionGranted: snapshot.permissionGranted,
      permissionState: snapshot.permissionState,
      canRequestPermission: snapshot.canRequestPermission,
      optedIn: snapshot.optedIn,
      subscriptionId: snapshot.subscriptionId,
      pushToken: snapshot.pushToken,
      externalUserId: clearExternalUserId
          ? null
          : externalUserId ?? snapshot.externalUserId ?? current.externalUserId,
      error: clearError ? null : snapshot.error,
    );
    status.value = next;
    return next;
  }

  static PushNotificationStatus _setError(String error, {bool? initialized}) {
    final current = status.value;
    final next = PushNotificationStatus(
      initialized: initialized ?? current.initialized,
      permissionGranted: current.permissionGranted,
      permissionState: current.permissionState,
      canRequestPermission: current.canRequestPermission,
      optedIn: current.optedIn,
      subscriptionId: current.subscriptionId,
      pushToken: current.pushToken,
      externalUserId: current.externalUserId,
      error: error.replaceFirst('Exception: ', ''),
    );
    status.value = next;
    return next;
  }
}
