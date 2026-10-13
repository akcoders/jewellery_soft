import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:flutter/services.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';

class AndroidAppInfo {
  const AndroidAppInfo({required this.version, required this.buildNumber});

  final String version;
  final int buildNumber;
}

class AndroidInstallResult {
  const AndroidInstallResult({required this.opened, required this.message});

  final bool opened;
  final String message;
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

  static Future<AndroidInstallResult> downloadAndInstall({
    required String url,
    required String version,
    String expectedSha256 = '',
    void Function(double progress)? onProgress,
  }) async {
    final uri = Uri.tryParse(url.trim());
    if (uri == null || uri.scheme != 'https') {
      return const AndroidInstallResult(
        opened: false,
        message: 'The update download link is invalid.',
      );
    }

    final client = HttpClient()
      ..connectionTimeout = const Duration(seconds: 30);
    final safeVersion = version.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');
    final directory = await getTemporaryDirectory();
    final file = File('${directory.path}/aabhushan-update-$safeVersion.apk');
    IOSink? sink;
    try {
      final request = await client.getUrl(uri);
      request.headers.set(
        HttpHeaders.acceptHeader,
        'application/vnd.android.package-archive',
      );
      final response = await request.close();
      if (response.statusCode != HttpStatus.ok) {
        return AndroidInstallResult(
          opened: false,
          message: 'Update download failed (HTTP ${response.statusCode}).',
        );
      }

      if (await file.exists()) await file.delete();
      sink = file.openWrite();
      final total = response.contentLength;
      var received = 0;
      await for (final bytes in response) {
        sink.add(bytes);
        received += bytes.length;
        if (total > 0) onProgress?.call(received / total);
      }
      await sink.flush();
      await sink.close();
      sink = null;
      if (!await file.exists() || await file.length() == 0) {
        return const AndroidInstallResult(
          opened: false,
          message: 'The downloaded update file is empty.',
        );
      }

      final expected = expectedSha256.trim().toLowerCase();
      if (expected.isNotEmpty) {
        final actual = (await sha256.bind(file.openRead()).first).toString();
        if (actual != expected) {
          await file.delete();
          return const AndroidInstallResult(
            opened: false,
            message: 'Update verification failed. Please retry the download.',
          );
        }
      }

      onProgress?.call(1);
      final opened = await OpenFilex.open(
        file.path,
        type: 'application/vnd.android.package-archive',
      );
      return AndroidInstallResult(
        opened: opened.type == ResultType.done,
        message: opened.type == ResultType.done
            ? 'Android installer opened. Confirm the update, then tap Open.'
            : opened.message,
      );
    } on SocketException {
      return const AndroidInstallResult(
        opened: false,
        message: 'No internet connection. Connect and try again.',
      );
    } catch (error) {
      return AndroidInstallResult(
        opened: false,
        message: 'Could not install the update: $error',
      );
    } finally {
      await sink?.close();
      client.close(force: true);
    }
  }

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }
}
