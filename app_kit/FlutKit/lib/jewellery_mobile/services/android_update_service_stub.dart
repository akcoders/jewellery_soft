class AndroidAppInfo {
  const AndroidAppInfo({required this.version, required this.buildNumber});

  final String version;
  final int buildNumber;
}

class AndroidUpdateService {
  AndroidUpdateService._();

  static bool get supported => false;

  static Future<AndroidAppInfo> appInfo() async =>
      const AndroidAppInfo(version: '', buildNumber: 0);

  static Future<bool> openDownload(String url) async => false;
}
