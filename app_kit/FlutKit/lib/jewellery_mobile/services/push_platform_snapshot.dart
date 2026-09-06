import 'package:flutter/foundation.dart';

@immutable
class PushPlatformSnapshot {
  const PushPlatformSnapshot({
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
}
