import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutkit/jewellery_mobile/screens/issuement_create_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/issuements_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutter_test/flutter_test.dart';

class _IssuementApi extends MobileApiService {
  _IssuementApi({this.received = false})
    : super(baseUrl: 'https://example.test');
  final bool received;
  int saves = 0;

  @override
  Future<List<dynamic>> fetchKarigars() async => [
    {'id': 1, 'name': 'A karigar with a long workshop name'},
  ];
  @override
  Future<List<dynamic>> fetchLocations() async => [
    {'id': 1, 'name': 'Main warehouse'},
  ];
  @override
  Future<List<dynamic>> fetchGoldItems() async => [];
  @override
  Future<List<dynamic>> fetchDiamondBagItems() async => [];
  @override
  Future<List<dynamic>> fetchStoneItems() async => [];
  @override
  Future<List<dynamic>> fetchDiamondOrderAllocations() async => [
    {
      'id': 1,
      'order_no': 'ORD-100',
      'order_name': 'Completed necklace',
      'status': 'Completed',
    },
  ];
  @override
  Future<Map<String, dynamic>> createIssuement(
    Map<String, dynamic> payload,
  ) async {
    saves++;
    return {};
  }

  @override
  Future<Map<String, dynamic>> fetchOrderDetail(int orderId) async => {
    'order': {
      'id': 1,
      'order_no': 'ORD-100-WITH-A-LONG-REFERENCE',
      'status': 'Completed',
      'customer_name': 'A customer with a long jewellery business name',
      'karigar_name': 'A karigar with a long workshop name',
      'follower_name': 'An assigned follower with a long name',
    },
    'is_received': received,
  };
  @override
  Future<List<dynamic>> fetchOrderWorkRequests({int? orderId}) async => [];
  @override
  Future<List<dynamic>> fetchIssuements() async => [
    {
      'voucher_no': 'ISS-2026-WITH-A-LONG-REFERENCE',
      'materials': ['Gold', 'Diamond', 'Stone'],
      'karigar_name': 'A karigar with a long workshop name',
      'issue_date': '2026-10-29',
      'total_lines': 3,
      'total_value': 12345678.9,
    },
  ];
}

void main() {
  setUpAll(() async {
    await (FontLoader(
      'Manrope',
    )..addFont(rootBundle.load('assets/fonts/manrope/Manrope.ttf'))).load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    await (FontLoader('packages/flutter_lucide/lucide')..addFont(
          rootBundle.load('packages/flutter_lucide/lib/fonts/lucide.ttf'),
        ))
        .load();
  });
  Future<void> mount(
    WidgetTester tester,
    Widget screen, {
    Size size = const Size(320, 740),
    double textScale = 1.5,
  }) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final captureKey = GlobalKey();
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: TextScaler.linear(textScale)),
          child: child!,
        ),
        home: RepaintBoundary(key: captureKey, child: screen),
      ),
    );
    await tester.pumpAndSettle();
    final captureDir = Platform.environment['FORM_UI_CAPTURE_DIR'];
    if (captureDir != null &&
        screen is IssuementCreateScreen &&
        size.width == 390) {
      await tester.ensureVisible(find.text('Issuement Details'));
      await tester.pumpAndSettle();
      await tester.runAsync(() async {
        final boundary =
            captureKey.currentContext!.findRenderObject()!
                as RenderRepaintBoundary;
        final picture = await boundary.toImage(pixelRatio: 2);
        final bytes = await picture.toByteData(format: ui.ImageByteFormat.png);
        final file = File('$captureDir/issuement-form.png');
        await file.create(recursive: true);
        await file.writeAsBytes(bytes!.buffer.asUint8List());
        picture.dispose();
      });
    }
  }

  if (Platform.environment['FORM_UI_CAPTURE_DIR'] != null) {
    testWidgets('capture native form at phone size', (tester) async {
      await mount(
        tester,
        IssuementCreateScreen(api: _IssuementApi()),
        size: const Size(390, 844),
        textScale: 1,
      );
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets(
    'persistent save reveals required fields and completed orders can be selected',
    (tester) async {
      final api = _IssuementApi();
      await mount(tester, IssuementCreateScreen(api: api));
      expect(tester.takeException(), isNull);
      expect(
        find.text('Save Combined Issuement').hitTestable(),
        findsOneWidget,
      );
      await tester.tap(find.text('Save Combined Issuement'));
      await tester.pumpAndSettle();
      expect(api.saves, 0);
      expect(find.text('Karigar is required').hitTestable(), findsOneWidget);
      final addStone = find.text('Add Stone Line');
      await tester.ensureVisible(addStone);
      await tester.tap(addStone);
      await tester.pumpAndSettle();
      expect(find.text('1 material line(s) added'), findsOneWidget);
      final order = find.widgetWithText(
        DropdownButtonFormField<int>,
        'Stone issued for order',
      );
      await tester.ensureVisible(order);
      await tester.tap(order);
      await tester.pumpAndSettle();
      expect(find.text('ORD-100 · Completed necklace'), findsWidgets);
      await tester.tap(find.text('ORD-100 · Completed necklace').last);
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(
        find.text('Save Combined Issuement').hitTestable(),
        findsOneWidget,
      );
    },
  );

  for (final received in [false, true]) {
    testWidgets(
      'completed order receiving notice is accurate: received=$received',
      (tester) async {
        await mount(
          tester,
          OrderDetailScreen(api: _IssuementApi(received: received), orderId: 1),
        );
        for (var index = 0; index < 4; index++) {
          expect(tester.takeException(), isNull);
          if (!received &&
              find
                  .text('Completed · receiving pending')
                  .evaluate()
                  .isNotEmpty) {
            break;
          }
          await tester.drag(find.byType(ListView).first, const Offset(0, -260));
          await tester.pumpAndSettle();
        }
        expect(
          find.text('Completed · receiving pending'),
          received ? findsNothing : findsOneWidget,
        );
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'issuement cards keep long references and values readable on small phones',
    (tester) async {
      await mount(
        tester,
        Scaffold(body: IssuementsScreen(api: _IssuementApi())),
      );
      await tester.drag(find.byType(ListView), const Offset(0, -300));
      await tester.pumpAndSettle();
      expect(find.text('ISS-2026-WITH-A-LONG-REFERENCE'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
