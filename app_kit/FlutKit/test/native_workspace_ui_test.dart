import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutkit/jewellery_mobile/screens/dashboard_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/orders_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  setUpAll(() async {
    final loader = FontLoader('Manrope')
      ..addFont(rootBundle.load('assets/fonts/manrope/Manrope.ttf'));
    await loader.load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    final lucide = FontLoader('packages/flutter_lucide/lucide')
      ..addFont(rootBundle.load('packages/flutter_lucide/lib/fonts/lucide.ttf'));
    await lucide.load();
  });

  for (final size in [
    const Size(320, 740),
    const Size(390, 844),
    const Size(1280, 900),
  ]) {
    testWidgets('native workspace fits ${size.width}px with readable text', (
      tester,
    ) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = size;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = _NativeWorkspaceApi();
      final pages = <String, Widget>{
        'dashboard': DashboardScreen(api: api, onOpenOrdersByStatus: (_) {}),
        'orders': OrdersScreen(api: api),
      };
      for (final entry in pages.entries) {
        final captureKey = GlobalKey();
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.light(),
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context).copyWith(
                textScaler: TextScaler.linear(size.width < 350 ? 1.5 : 1),
              ),
              child: child!,
            ),
            home: RepaintBoundary(
              key: captureKey,
              child: Scaffold(
                appBar: AppBar(
                  title: Text(
                    entry.key == 'dashboard' ? 'Dashboard' : 'Orders',
                  ),
                ),
                body: entry.value,
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull, reason: '${entry.key} header');
        if (size.width == 390 &&
            Platform.environment['NATIVE_UI_CAPTURE_DIR'] != null) {
          final path =
              '${Platform.environment['NATIVE_UI_CAPTURE_DIR']}/${entry.key}.png';
          await tester.runAsync(() async {
            final boundary =
                captureKey.currentContext!.findRenderObject()!
                    as RenderRepaintBoundary;
            final image = await boundary.toImage(pixelRatio: 2);
            final bytes = await image.toByteData(
              format: ui.ImageByteFormat.png,
            );
            await File(path).create(recursive: true);
            await File(path).writeAsBytes(bytes!.buffer.asUint8List());
            image.dispose();
          });
        }
        for (var i = 0; i < 9; i++) {
          await tester.drag(find.byType(ListView).first, const Offset(0, -440));
          await tester.pumpAndSettle();
          expect(
            tester.takeException(),
            isNull,
            reason: '${entry.key} content',
          );
        }
        await tester.pumpWidget(const SizedBox.shrink());
      }
    });
  }

  testWidgets('order search ignores a slower previous response', (
    tester,
  ) async {
    final api = _DelayedSearchApi();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: OrdersScreen(api: api)),
      ),
    );
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'old');
    await tester.pump(const Duration(milliseconds: 350));
    await tester.enterText(find.byType(TextField), 'latest');
    await tester.pump(const Duration(milliseconds: 350));
    api.pending['latest']!.complete({
      'items': [
        {..._sampleOrder, 'order_no': 'LATEST-ORDER'},
      ],
    });
    await tester.pumpAndSettle();
    expect(find.text('LATEST-ORDER'), findsOneWidget);
    api.pending['old']!.complete({
      'items': [
        {..._sampleOrder, 'order_no': 'OUTDATED-ORDER'},
      ],
    });
    await tester.pumpAndSettle();
    expect(find.text('LATEST-ORDER'), findsOneWidget);
    expect(find.text('OUTDATED-ORDER'), findsNothing);
  });
}

final _sampleOrder = <String, dynamic>{
  'id': 1,
  'order_no': 'ORD-2026-00124',
  'order_name': 'Emerald & diamond bridal necklace',
  'customer_name': 'Meera Shah — Aabhushan Jewellers',
  'karigar_name': 'Ramesh Kumar, detailed jewellery workshop',
  'follower_name': 'Priya Sharma, production coordinator',
  'status': 'In Production',
  'due_date': '2026-11-06',
  'last_followup_stage': 'Diamond setting and final quality verification',
  'next_followup_date': DateTime.now().toIso8601String(),
};

class _NativeWorkspaceApi extends MobileApiService {
  _NativeWorkspaceApi() : super(baseUrl: 'https://example.test');

  @override
  Future<Map<String, dynamic>> fetchOrders({
    String status = '',
    String query = '',
    int page = 1,
    int limit = 20,
    bool followupsOnly = false,
  }) async => {
    'items': [
      _sampleOrder,
      {
        ..._sampleOrder,
        'id': 2,
        'order_no': 'ORD-2026-00123',
        'status': 'Completed',
        'receiving_status': 'pending',
      },
      {
        ..._sampleOrder,
        'id': 3,
        'order_no': 'ORD-2026-00122',
        'status': 'Confirmed',
        'next_followup_date': DateTime.now()
            .subtract(const Duration(days: 2))
            .toIso8601String(),
      },
      {
        ..._sampleOrder,
        'id': 4,
        'order_no': 'ORD-2026-00121',
        'status': 'QC',
        'next_followup_date': DateTime.now()
            .add(const Duration(days: 3))
            .toIso8601String(),
      },
    ],
  };
}

class _DelayedSearchApi extends _NativeWorkspaceApi {
  final pending = <String, Completer<Map<String, dynamic>>>{};

  @override
  Future<Map<String, dynamic>> fetchOrders({
    String status = '',
    String query = '',
    int page = 1,
    int limit = 20,
    bool followupsOnly = false,
  }) {
    if (query.isEmpty) return super.fetchOrders();
    final completer = Completer<Map<String, dynamic>>();
    pending[query] = completer;
    return completer.future;
  }
}
