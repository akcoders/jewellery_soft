import 'package:flutkit/jewellery_mobile/jewellery_mobile_app.dart';
import 'package:flutkit/jewellery_mobile/services/onesignal_service.dart';
import 'package:flutkit/jewellery_mobile/session/mobile_session_store.dart';
import 'package:flutter/material.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await OneSignalService.init();
  final session = await MobileSessionStore.load();
  runApp(MyApp(initialSession: session));
}

class MyApp extends StatelessWidget {
  const MyApp({super.key, required this.initialSession});

  final MobileSession? initialSession;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Aabhushan ERP',
      theme: AppTheme.light(),
      home: JewelleryMobileApp(initialSession: initialSession),
    );
  }
}
