// ignore_for_file: deprecated_member_use

import 'dart:async';
import 'dart:convert';
import 'dart:html' as html;

import 'package:flutter/foundation.dart';

@immutable
class PwaInstallResult {
  const PwaInstallResult({
    this.installed = false,
    this.prompted = false,
    this.manual = false,
    this.platform = 'other',
    this.message = '',
  });

  final bool installed;
  final bool prompted;
  final bool manual;
  final String platform;
  final String message;
}

class PwaInstallService {
  PwaInstallService._();

  static const _channel = 'aabhushan_pwa_install';
  static final ValueNotifier<bool> available = ValueNotifier<bool>(false);
  static final Map<int, Completer<Map<String, dynamic>>> _pending = {};
  static StreamSubscription<html.MessageEvent>? _messages;
  static int _requestId = 0;

  static Future<void> init() async {
    _messages ??= html.window.onMessage.listen(_handleMessage);
    try {
      final result = await _command('status');
      available.value = result['available'] == true;
    } catch (_) {
      available.value = false;
    }
  }

  static Future<PwaInstallResult> promptInstall() async {
    try {
      final result = await _command('install');
      available.value = result['available'] == true;
      return PwaInstallResult(
        installed: result['installed'] == true,
        prompted: result['prompted'] == true,
        manual: result['manual'] == true,
        platform: result['platform']?.toString() ?? 'other',
        message: result['message']?.toString() ?? '',
      );
    } catch (_) {
      return const PwaInstallResult(
        manual: true,
        message: 'Browser menu se Install app ya Add to Home screen chunein.',
      );
    }
  }

  static Future<Map<String, dynamic>> _command(String command) async {
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
      }),
      html.window.location.origin,
    );
    try {
      return await completer.future.timeout(const Duration(seconds: 5));
    } on TimeoutException {
      _pending.remove(id);
      return <String, dynamic>{'available': false, 'installed': false};
    }
  }

  static void _handleMessage(html.MessageEvent event) {
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
    if (decoded['kind'] == 'availability') {
      available.value = decoded['available'] == true;
      return;
    }
    if (decoded['kind'] != 'response') return;
    final id = int.tryParse(decoded['id']?.toString() ?? '') ?? 0;
    final completer = _pending.remove(id);
    if (completer == null) return;
    final data = decoded['data'];
    completer.complete(
      data is Map
          ? data.map<String, dynamic>(
              (key, value) => MapEntry(key.toString(), value),
            )
          : <String, dynamic>{},
    );
  }
}
