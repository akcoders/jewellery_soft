// ignore_for_file: deprecated_member_use

import 'dart:async';
import 'dart:convert';
import 'dart:html' as html;

import 'package:flutkit/jewellery_mobile/services/push_platform_snapshot.dart';

class PlatformPushClient {
  static const _channel = 'aabhushan_pwa_bridge';
  final Map<int, Completer<Map<String, dynamic>>> _pending = {};
  StreamSubscription<html.MessageEvent>? _messages;
  int _requestId = 0;
  void Function(PushPlatformSnapshot snapshot)? _onStatus;
  void Function(Map<String, dynamic> payload)? _onNotificationOpened;
  void Function()? _onNotificationForeground;

  Future<PushPlatformSnapshot> init({
    required String appId,
    required void Function(PushPlatformSnapshot snapshot) onStatus,
    required void Function(Map<String, dynamic> payload) onNotificationOpened,
    required void Function() onNotificationForeground,
  }) async {
    _onStatus = onStatus;
    _onNotificationOpened = onNotificationOpened;
    _onNotificationForeground = onNotificationForeground;
    _messages ??= html.window.onMessage.listen(_handleMessage);
    return _snapshotFrom(await _command('init', {'appId': appId}));
  }

  Future<PushPlatformSnapshot> login({
    required String externalId,
    required String email,
    required String name,
  }) async => _snapshotFrom(
    await _command('login', {
      'externalId': externalId,
      'email': email,
      'name': name,
    }),
  );

  Future<PushPlatformSnapshot> requestPermission({
    bool fallbackToSettings = true,
  }) async => _snapshotFrom(await _command('requestPermission'));

  Future<PushPlatformSnapshot> snapshot() async =>
      _snapshotFrom(await _command('status'));

  Future<PushPlatformSnapshot> logout() async =>
      _snapshotFrom(await _command('logout'));

  Future<Map<String, dynamic>> _command(
    String command, [
    Map<String, dynamic> payload = const {},
  ]) async {
    final id = ++_requestId;
    final completer = Completer<Map<String, dynamic>>();
    _pending[id] = completer;
    html.window.postMessage(
      jsonEncode({
        'channel': _channel,
        'sender': 'dart',
        'kind': 'command',
        'id': id,
        'command': command,
        'payload': payload,
      }),
      html.window.location.origin,
    );

    try {
      return await completer.future.timeout(const Duration(seconds: 15));
    } on TimeoutException {
      _pending.remove(id);
      throw Exception(
        'Web push service did not respond. Check the OneSignal web setup.',
      );
    }
  }

  void _handleMessage(html.MessageEvent event) {
    if (event.origin != html.window.location.origin || event.data is! String) {
      return;
    }
    dynamic decoded;
    try {
      decoded = jsonDecode(event.data as String);
    } catch (_) {
      return;
    }
    if (decoded is! Map ||
        decoded['channel'] != _channel ||
        decoded['sender'] != 'javascript') {
      return;
    }
    final message = decoded.map<String, dynamic>(
      (key, value) => MapEntry(key.toString(), value),
    );
    if (message['kind'] == 'response') {
      final id = _asInt(message['id']);
      final completer = _pending.remove(id);
      if (completer == null) return;
      if (message['ok'] == true) {
        completer.complete(_asMap(message['data']));
      } else {
        completer.completeError(
          Exception(
            (message['error'] ?? 'Unable to use web push notifications.')
                .toString(),
          ),
        );
      }
      return;
    }

    if (message['kind'] != 'event') return;
    final data = _asMap(message['data']);
    switch (message['event']) {
      case 'status':
        _onStatus?.call(_snapshotFrom(data));
      case 'notificationOpened':
        _onNotificationOpened?.call(data);
      case 'notificationForeground':
        _onNotificationForeground?.call();
    }
  }

  PushPlatformSnapshot _snapshotFrom(Map<String, dynamic> value) {
    return PushPlatformSnapshot(
      initialized: value['initialized'] == true,
      permissionGranted: value['permissionGranted'] == true,
      canRequestPermission: value['canRequestPermission'] == true,
      optedIn: value['optedIn'] == true,
      subscriptionId: _nullableString(value['subscriptionId']),
      pushToken: _nullableString(value['pushToken']),
      externalUserId: _nullableString(value['externalUserId']),
      error: _nullableString(value['error']),
    );
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is! Map) return <String, dynamic>{};
    return value.map<String, dynamic>(
      (key, item) => MapEntry(key.toString(), item),
    );
  }

  int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  String? _nullableString(dynamic value) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? null : text;
  }
}
