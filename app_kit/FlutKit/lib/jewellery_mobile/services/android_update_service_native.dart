import 'dart:io';

import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

class AndroidAppInfo {
  const AndroidAppInfo({required this.version, required this.buildNumber});

  final String version;
  final int buildNumber;
}

class AndroidUpdateService {
  AndroidUpdateService._();

  static const MethodChannel _channel = MethodChannel(
    'com.aabhushan.erp/app_info',
  );

  static bool get supported => Platform.isAndroid;

  static Future<AndroidAppInfo> appInfo() async {
    final data = await _channel.invokeMapMethod<String, dynamic>('getAppInfo');
    return AndroidAppInfo(
      version: (data?['version'] ?? '').toString(),
      buildNumber: _asInt(data?['build_number']),
    );
  }

  static Future<bool> openDownload(String url) async {
    final uri = Uri.tryParse(url.trim());
    if (uri == null || uri.scheme != 'https') return false;
    return launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }
}
