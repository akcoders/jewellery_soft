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

  static bool get supported => false;

  static Future<AndroidAppInfo> appInfo() async =>
      const AndroidAppInfo(version: '', buildNumber: 0);

  static Future<bool> openDownload(String url) async => false;

  static Future<AndroidInstallResult> downloadAndInstall({
    required String url,
    required String version,
    String expectedSha256 = '',
    void Function(double progress)? onProgress,
  }) async => const AndroidInstallResult(
    opened: false,
    message: 'Android updates are not supported on this device.',
  );
}
