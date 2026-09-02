import 'package:flutter/foundation.dart';

class PwaInstallService {
  PwaInstallService._();

  static final ValueNotifier<bool> available = ValueNotifier<bool>(false);

  static Future<void> init() async {}

  static Future<bool> promptInstall() async => false;
}
