// ignore_for_file: deprecated_member_use

import 'dart:html' as html;

class PwaUpdateService {
  PwaUpdateService._();

  static const _appliedVersionKey = 'aabhushan_pwa_applied_update';

  static bool get supported => true;

  static String get appliedVersion =>
      html.window.localStorage[_appliedVersionKey]?.trim() ?? '';

  static Future<void> clearCacheAndRelaunch(String version) async {
    final normalizedVersion = version.trim();
    if (normalizedVersion.isNotEmpty) {
      html.window.localStorage[_appliedVersionKey] = normalizedVersion;
    }
    final current = Uri.parse(html.window.location.href);
    final query = Map<String, String>.from(current.queryParameters)
      ..['clear-pwa-cache'] = '1';
    if (normalizedVersion.isNotEmpty) {
      query['app-update-version'] = normalizedVersion;
    }
    html.window.location.replace(
      current.replace(queryParameters: query).toString(),
    );
  }
}
