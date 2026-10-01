import 'dart:async';

import 'package:flutkit/jewellery_mobile/jewellery_mobile_app.dart';
import 'package:flutkit/jewellery_pwa/jewellery_pwa_app.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart' as pwa;
import 'package:flutkit/jewellery_mobile/services/local_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/onesignal_service.dart';
import 'package:flutkit/jewellery_mobile/services/pwa_install_service.dart';
import 'package:flutkit/jewellery_mobile/session/mobile_session_store.dart';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:http/http.dart' as http;

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await _loadPwaFonts();
  final session = await MobileSessionStore.load();
  runApp(MyApp(initialSession: session));
  unawaited(PwaInstallService.init());
  unawaited(OneSignalService.init());
  unawaited(LocalNotificationService.instance.init());
}

Future<void> _loadPwaFonts() async {
  if (!kIsWeb) return;
  try {
    final loaders = [
      FontLoader('Manrope')..addFont(_loadPwaFont('fonts/manrope/Manrope.ttf')),
      FontLoader('CormorantGaramond')
        ..addFont(_loadPwaFont('fonts/cormorant/CormorantGaramond.ttf')),
    ];
    await Future.wait(loaders.map((loader) => loader.load()));
    LicenseRegistry.addLicense(() async* {
      for (final entry in const {
        'Manrope': 'fonts/manrope/OFL.txt',
        'Cormorant Garamond': 'fonts/cormorant/OFL.txt',
      }.entries) {
        yield LicenseEntryWithLineBreaks([
          entry.key,
        ], await _loadPwaText(entry.value));
      }
    });
  } catch (_) {
    // The app stays usable with the platform fallback font if an asset is stale.
  }
}

Future<ByteData> _loadPwaFont(String path) async {
  final response = await http.get(Uri.base.resolve(path));
  if (response.statusCode != 200) {
    throw StateError('Unable to load PWA font: $path');
  }
  return ByteData.sublistView(response.bodyBytes);
}

Future<String> _loadPwaText(String path) async {
  final response = await http.get(Uri.base.resolve(path));
  if (response.statusCode != 200) {
    throw StateError('Unable to load PWA license: $path');
  }
  return response.body;
}

class MyApp extends StatelessWidget {
  const MyApp({super.key, required this.initialSession});

  final MobileSession? initialSession;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Aabhushan ERP',
      theme: kIsWeb ? pwa.AppTheme.light() : AppTheme.light(),
      home: kIsWeb
          ? JewelleryPwaApp(initialSession: initialSession)
          : JewelleryMobileApp(initialSession: initialSession),
    );
  }
}
