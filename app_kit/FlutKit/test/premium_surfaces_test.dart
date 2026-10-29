import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutkit/jewellery_mobile/screens/brand_splash_screen.dart'
    as native_splash;
import 'package:flutkit/jewellery_mobile/screens/dashboard_screen.dart'
    as native_dashboard;
import 'package:flutkit/jewellery_mobile/screens/login_screen.dart'
    as native_login;
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/widgets/premium_surface.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart' as native_theme;
import 'package:flutkit/jewellery_pwa/screens/dashboard_screen.dart'
    as pwa_dashboard;
import 'package:flutkit/jewellery_pwa/screens/login_screen.dart' as pwa_login;
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart' as pwa_theme;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  setUpAll(() async {
    for (final entry in {
      'Manrope': 'assets/fonts/manrope/Manrope.ttf',
      'CormorantGaramond': 'assets/fonts/cormorant/CormorantGaramond.ttf',
      'MaterialIcons': 'fonts/MaterialIcons-Regular.otf',
      'packages/flutter_lucide/lucide':
          'packages/flutter_lucide/lib/fonts/lucide.ttf',
    }.entries) {
      await (FontLoader(
        entry.key,
      )..addFont(rootBundle.load(entry.value))).load();
    }
  });

  for (final target in [
    ('native-320', const Size(320, 740), false, 1.5),
    ('native-390', const Size(390, 844), false, 1.0),
    ('pwa-390', const Size(390, 844), true, 1.0),
    ('pwa-1280', const Size(1280, 900), true, 1.0),
  ]) {
    testWidgets('premium surfaces fit ${target.$1}', (tester) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = target.$2;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = _PremiumApi();
      final pages = <String, Widget>{
        'login': target.$3
            ? pwa_login.LoginScreen(onLoginSuccess: _signedIn)
            : native_login.LoginScreen(onLoginSuccess: _signedIn),
        'dashboard': Scaffold(
          appBar: AppBar(title: const Text('Dashboard')),
          body: target.$3
              ? pwa_dashboard.DashboardScreen(
                  api: api,
                  onOpenOrdersByStatus: (_) {},
                )
              : native_dashboard.DashboardScreen(
                  api: api,
                  onOpenOrdersByStatus: (_) {},
                ),
        ),
      };
      for (final page in pages.entries) {
        final key = GlobalKey();
        await tester.pumpWidget(
          RepaintBoundary(
            key: key,
            child: MaterialApp(
              debugShowCheckedModeBanner: false,
              theme: target.$3
                  ? pwa_theme.AppTheme.light()
                  : native_theme.AppTheme.light(),
              builder: (context, child) => MediaQuery(
                data: MediaQuery.of(
                  context,
                ).copyWith(textScaler: TextScaler.linear(target.$4)),
                child: PremiumBackdrop(child: child!),
              ),
              home: page.value,
            ),
          ),
        );
        await tester.runAsync(
          () => precacheImage(
            const AssetImage('assets/images/brand/aabhushan_mark.png'),
            key.currentContext!,
          ),
        );
        await tester.pumpAndSettle();
        expect(
          tester.takeException(),
          isNull,
          reason: '${target.$1} ${page.key} header',
        );
        await _capture(tester, key, '${target.$1}-${page.key}');
        if (page.key == 'login') {
          await tester.ensureVisible(find.text('Sign In'));
          await tester.pumpAndSettle();
          await tester.tap(find.text('Sign In'));
          await tester.pumpAndSettle();
          expect(find.text('Email is required'), findsOneWidget);
          expect(find.text('Password is required'), findsOneWidget);
          expect(
            tester.takeException(),
            isNull,
            reason: '${target.$1} form validation',
          );
        } else {
          for (var i = 0; i < 8; i++) {
            await tester.drag(
              find.byType(ListView).first,
              const Offset(0, -450),
            );
            await tester.pumpAndSettle();
            expect(
              tester.takeException(),
              isNull,
              reason: '${target.$1} dashboard content',
            );
          }
        }
        await tester.pumpWidget(const SizedBox.shrink());
      }
    });
  }

  testWidgets('splash settles without motion and finishes promptly', (
    tester,
  ) async {
    var completed = false;
    await tester.pumpWidget(
      MaterialApp(
        theme: native_theme.AppTheme.light(),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(disableAnimations: true),
          child: child!,
        ),
        home: native_splash.BrandSplashScreen(
          onFinished: () => completed = true,
        ),
      ),
    );
    expect(find.text('JEWELLERY WORKSPACE'), findsOneWidget);
    expect(
      tester
          .widget<LinearProgressIndicator>(find.byType(LinearProgressIndicator))
          .value,
      1,
    );
    await tester.pump(const Duration(milliseconds: 250));
    expect(completed, isTrue);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _capture(WidgetTester tester, GlobalKey key, String name) async {
  final directory = Platform.environment['PREMIUM_UI_CAPTURE_DIR'];
  if (directory == null) return;
  await tester.runAsync(() async {
    final boundary =
        key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 2);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    final file = File('$directory/$name.png');
    await file.create(recursive: true);
    await file.writeAsBytes(bytes!.buffer.asUint8List());
    image.dispose();
  });
}

Future<void> _signedIn({
  required String baseUrl,
  required String token,
  required String userName,
  required String userEmail,
  required List<String> roleCodes,
}) async {}

class _PremiumApi extends MobileApiService {
  _PremiumApi() : super(baseUrl: 'https://example.test');

  @override
  Future<Map<String, dynamic>> fetchOrders({
    String status = '',
    String query = '',
    int page = 1,
    int limit = 20,
    bool followupsOnly = false,
  }) async => {
    'items': [
      for (final entry in [
        (1, 'In Production'),
        (2, 'Ready'),
        (3, 'Confirmed'),
        (4, 'QC'),
      ])
        {
          'id': entry.$1,
          'order_no': 'ORD-2026-0012${entry.$1}',
          'customer_name': 'Meera Shah · Aabhushan Jewellers',
          'status': entry.$2,
          'last_followup_stage': 'Diamond setting and final quality inspection',
          'next_followup_date': DateTime.now()
              .add(Duration(days: entry.$1 - 2))
              .toIso8601String(),
        },
    ],
  };
}
