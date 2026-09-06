import 'package:flutter/foundation.dart';

@immutable
class PwaInstallResult {
  const PwaInstallResult({
    this.installed = false,
    this.prompted = false,
    this.manual = false,
    this.platform = 'native',
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

  static final ValueNotifier<bool> available = ValueNotifier<bool>(false);

  static Future<void> init() async {}

  static Future<PwaInstallResult> promptInstall() async =>
      const PwaInstallResult();
}
