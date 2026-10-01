import 'dart:async';
import 'package:flutkit/jewellery_pwa/screens/dashboard_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/inventory_tab.dart';
import 'package:flutkit/jewellery_pwa/screens/performance_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/transactions_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  for (final size in [const Size(320, 740), const Size(1280, 900)]) {
    testWidgets('workspace pages fit ${size.width}px and enlarged text', (
      tester,
    ) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = size;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = _WorkspaceApi();
      final pages = <Widget>[
        DashboardScreen(api: api),
        InventoryTab(api: api),
        PerformanceScreen(api: api),
        TransactionsScreen(
          title: 'Diamond Purchases',
          loader: () async => _transactions,
          icon: Icons.diamond_outlined,
          accentColor: Colors.blue,
          transactionKey: 'diamond_purchase',
          api: api,
        ),
      ];
      for (final page in pages) {
        await tester.pumpWidget(
          MaterialApp(
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context).copyWith(
                textScaler: TextScaler.linear(size.width < 400 ? 1.5 : 1),
              ),
              child: child!,
            ),
            home: Scaffold(body: page),
          ),
        );
        await tester.pumpAndSettle();
        expect(
          tester.takeException(),
          isNull,
          reason: '${page.runtimeType} header',
        );
        for (var i = 0; i < 10; i++) {
          await tester.drag(find.byType(ListView).first, const Offset(0, -550));
          await tester.pumpAndSettle();
          expect(
            tester.takeException(),
            isNull,
            reason: '${page.runtimeType} content',
          );
        }
        await tester.pumpWidget(const SizedBox.shrink());
      }
    });
  }
  testWidgets('inventory waits for typing and ignores an older search result', (
    tester,
  ) async {
    final api = _SearchApi();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: InventoryTab(api: api)),
      ),
    );
    await tester.pumpAndSettle();
    final field = find.byType(TextField);
    await tester.enterText(field, 'a');
    await tester.pump(const Duration(milliseconds: 200));
    expect(api.queries, ['']);
    await tester.pump(const Duration(milliseconds: 150));
    expect(api.queries, ['', 'a']);
    await tester.enterText(field, 'ab');
    api.pending['a']!.complete([
      {'diamond_type': 'Old result', 'shape': 'Round'},
    ]);
    await tester.pump();
    expect(find.textContaining('Old result'), findsNothing);
    await tester.pump(const Duration(milliseconds: 350));
    api.pending['ab']!.complete([
      {'diamond_type': 'New result', 'shape': 'Round'},
    ]);
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('New result · Round'),
      300,
      scrollable: find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    expect(find.text('New result · Round'), findsOneWidget);
    expect(find.textContaining('Old result'), findsNothing);
  });
}

const _transactions = [
  {
    'id': 1,
    'voucher_no': 'PUR-2026-00001',
    'supplier_name': 'A supplier with a longer business name',
    'purpose': 'Stock replenishment with a longer reference',
    'purchase_date': '2026-10-01',
    'payment_terms_days': 30,
    'due_date': '2026-10-31',
    'order_no': 'ORD-2026-001',
  },
];

class _WorkspaceApi extends MobileApiService {
  _WorkspaceApi() : super(baseUrl: 'https://example.test');
  @override
  Future<Map<String, dynamic>> fetchOrders({
    String status = '',
    String query = '',
    int page = 1,
    int limit = 20,
    bool followupsOnly = false,
  }) async => {
    'items': [
      {
        'id': 1,
        'order_no': 'ORD-2026-00001',
        'customer_name': 'A customer with a longer display name',
        'status': 'In Production',
        'next_followup_date': DateTime.now().toIso8601String(),
      },
    ],
  };
  @override
  Future<Map<String, dynamic>> fetchInventorySummary() async => {
    'diamond': {
      'total_pcs': 3500,
      'total_carat': 1234.25,
      'total_value': 12345678.50,
    },
    'gold': {
      'total_weight_gm': 2000.25,
      'total_fine_gm': 1500,
      'total_value': 1234567,
    },
    'stone': {'total_qty': 2456, 'total_value': 123456},
  };
  @override
  Future<List<dynamic>> fetchDiamondStock({String query = ''}) async => [
    {
      'diamond_type': 'Natural diamonds',
      'shape': 'Emerald cut',
      'color': 'Colourless',
      'clarity': 'VVS1',
      'chalni_from': '10.0',
      'chalni_to': '15.0',
      'pcs_balance': 3500,
      'carat_balance': 1234.25,
      'stock_value': 12345678.50,
      'avg_cost_per_carat': 456789.50,
    },
  ];
  @override
  Future<List<dynamic>> fetchGoldStock({String query = ''}) async => [];
  @override
  Future<List<dynamic>> fetchStoneStock({String query = ''}) async => [];
  @override
  Future<Map<String, dynamic>> fetchPerformance({
    int? year,
    int? month,
  }) async => {
    'summary': {
      'score': 106,
      'points_earned': 10,
      'points_lost': 4,
      'on_time_rate': 84,
      'overdue_actions': 2,
      'task_on_time': 5,
      'task_late': 2,
      'followup_on_time': 2,
      'followup_late': 1,
      'followup_overdue': 1,
    },
    'score_rules': {
      'base_score': 100,
      'task_on_time': 2,
      'task_late_or_overdue': -2,
    },
    'events': [
      {
        'type': 'Task',
        'title': 'Complete the gold issuance for a customer order',
        'score_delta': 2,
        'due_at': '2026-10-01 10:00:00',
        'status': 'completed_on_time',
      },
    ],
  };
}

class _SearchApi extends _WorkspaceApi {
  final queries = <String>[];
  final pending = <String, Completer<List<dynamic>>>{};
  @override
  Future<List<dynamic>> fetchDiamondStock({String query = ''}) {
    queries.add(query);
    if (query.isEmpty) return super.fetchDiamondStock();
    final completer = Completer<List<dynamic>>();
    pending[query] = completer;
    return completer.future;
  }
}
