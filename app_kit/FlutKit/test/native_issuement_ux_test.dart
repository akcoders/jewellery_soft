import 'package:flutkit/jewellery_mobile/screens/issuement_create_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/issuements_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutter/material.dart';
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
  Future<void> mount(WidgetTester tester, Widget screen) async {
    tester.view.physicalSize = const Size(320, 740);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: TextScaler.linear(1.5)),
          child: child!,
        ),
        home: screen,
      ),
    );
    await tester.pumpAndSettle();
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
              find.text('Completed · receiving pending').evaluate().isNotEmpty) {
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
