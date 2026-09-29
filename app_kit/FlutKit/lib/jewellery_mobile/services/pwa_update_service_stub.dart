class PwaUpdateService {
  PwaUpdateService._();

  static bool get supported => false;

  static String get appliedVersion => '';

  static Future<void> clearCacheAndRelaunch(String version) async {}
}
