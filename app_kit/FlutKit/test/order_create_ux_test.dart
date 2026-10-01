import 'package:flutkit/jewellery_pwa/screens/order_create_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _OrderFormApi extends MobileApiService {
  _OrderFormApi() : super(baseUrl: 'https://example.test');

  int saves = 0;

  @override
  Future<Map<String, dynamic>> fetchOrderFormOptions() async => {
    'order_categories': [
      {'id': 1, 'name': 'Ring', 'code': 'RNG'},
    ],
    'priorities': ['Medium'],
    'statuses': ['Confirmed'],
    'material_categories': ['Gold'],
  };

  @override
  Future<Map<String, dynamic>> createOrder(Map<String, dynamic> payload) async {
    saves++;
    return {};
  }
}

void main() {
  testWidgets(
    'new order fits a phone and save reveals offscreen required fields',
    (tester) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = _OrderFormApi();
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light(),
          home: OrderCreateScreen(api: api),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);

      // The persistent save action must validate fields below the current view.
      final name = find.widgetWithText(TextFormField, 'Order Name');
      await tester.ensureVisible(name);
      await tester.enterText(name, 'Royal ring');
      await tester.tap(find.text('Save Order'));
      await tester.pumpAndSettle();

      expect(api.saves, 0);
      final error = find.text('Description is required for a fresh order');
      expect(error, findsOneWidget);
      expect(tester.getRect(error).top, greaterThan(56));
      expect(tester.getRect(error).bottom, lessThan(760));
      expect(find.text('Save Order').hitTestable(), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
