import 'package:flutkit/jewellery_pwa/screens/admin_tasks_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/diamond_bags_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/followups_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/issuements_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/order_work_requests_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/orders_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _WorkflowApi extends MobileApiService {
  _WorkflowApi() : super(baseUrl: 'https://example.test');
  final order = <String, dynamic>{
    'id': 1,
    'order_no': 'ORD-2026-000001',
    'status': 'In Production',
    'customer_name': 'A very long customer and jewellery business name',
    'karigar_name': 'A long karigar name for small displays',
    'follower_name': 'A long assigned follower name',
    'due_date': '2026-10-12',
    'next_followup_date': DateTime.now().toIso8601String(),
    'last_followup_stage': 'Diamond setting and quality verification',
    'order_type': 'Custom jewellery',
    'priority': 'High',
    'followup_due_at': '2026-10-12 11:00:00',
  };
  final request = <String, dynamic>{
    'id': 1,
    'order_id': 1,
    'order_no': 'ORD-2026-000001',
    'request_type': 'gold_requirement',
    'status': 'pending',
    'details': 'Additional gold needed to complete the customer design.',
    'requester_name': 'A long assigned follower name',
    'gold_quantity_gm': 12.345,
  };
  @override
  Future<Map<String, dynamic>> fetchOrders({
    String status = '',
    String query = '',
    int page = 1,
    int limit = 20,
    bool followupsOnly = false,
  }) async => {
    'items': [order],
  };
  @override
  Future<Map<String, dynamic>> fetchOrderDetail(int orderId) async => {
    'order': order,
    'can_assign_karigar': true,
    'can_change_follower': true,
    'can_add_followup': true,
    'can_raise_diamond_requirement': true,
    'items': [
      {
        'design_name': 'Detailed diamond necklace with a long design name',
        'design_code': 'DES-1234',
        'qty': 1,
        'size_label': 'Custom fit',
        'gold_required_gm': 125.345,
        'diamond_required_cts': 12.5,
        'item_status': 'In Production',
      },
    ],
    'followups': [
      {
        'stage': 'Diamond setting and quality verification',
        'description': 'The karigar has completed setting the central stones.',
        'next_followup_date': '2026-10-12 11:00:00',
        'followup_taken_by_name': 'A long assigned follower name',
      },
    ],
  };
  @override
  Future<List<dynamic>> fetchOrderWorkRequests({int? orderId}) async => [
    request,
  ];
  @override
  Future<Map<String, dynamic>> fetchAdminTasks() async => {
    'staff': [],
    'tasks': [
      {
        'id': 1,
        'title': 'Follow up on a detailed custom jewellery order',
        'status': 'pending',
        'priority': 'high',
        'assignee_name': 'A long assigned staff member name',
        'scheduled_at': '2026-10-12 11:00:00',
        'note':
            'Contact the karigar and update the order with the delivery date.',
      },
    ],
  };
  @override
  Future<List<dynamic>> fetchIssuements() async => [
    {
      'voucher_no': 'ISS-2026-000001',
      'materials': ['Gold', 'Diamond', 'Stone'],
      'karigar_name': 'A long karigar name for small displays',
      'issue_date': '2026-10-12',
      'total_lines': 4,
      'total_value': 1234567.8,
    },
  ];
  @override
  Future<Map<String, dynamic>> fetchIssuementDetail(String voucherNo) async => {
    'header': {
      'karigar_name': 'A long karigar name for small displays',
      'issue_date': '2026-10-12',
      'warehouse_name': 'Main jewellery warehouse',
      'purpose': 'Custom jewellery production',
      'notes': 'Handle with care',
    },
    'lines': {
      'gold': [
        {
          'purity_code': '22K',
          'weight_gm': 125.5,
          'rate_per_gm': 10000,
          'line_value': 1255000,
        },
      ],
    },
  };
  @override
  Future<List<dynamic>> fetchDiamondBags() async => [
    {
      'id': 1,
      'bag_no': 'BAG-2026-000001',
      'status': 'partly_issued',
      'pcs_balance': 1200,
      'cts_balance': 125.678,
      'item_count': 4,
      'order_count': 2,
      'created_at': '2026-10-12',
    },
  ];
  @override
  Future<List<dynamic>> fetchDiamondRequirements() async => [];
  @override
  Future<Map<String, dynamic>> fetchDiamondBag(int id) async => {
    'bag': (await fetchDiamondBags()).first,
    'items': [
      {
        'diamond_type': 'Natural diamonds',
        'color': 'D',
        'clarity': 'VVS1',
        'shape_name': 'Round brilliant',
        'size_label': 'Long calibrated size description',
        'pcs_total': 1500,
        'pcs_available': 1200,
        'weight_cts_total': 150.78,
        'weight_cts_available': 125.678,
      },
    ],
    'movements': [
      {
        'movement_type': 'partly_issued',
        'diamond_type': 'Natural diamonds',
        'size_label': 'Long calibrated size description',
        'movement_date': '2026-10-12',
        'voucher_no': 'ISS-2026-000001',
        'order_no': 'ORD-2026-000001',
        'karigar_name': 'A long karigar name for small displays',
        'pcs': 300,
        'carat': 25.102,
      },
    ],
  };
}

void main() {
  final pages = <String, Widget Function(_WorkflowApi)>{
    'orders': (api) => OrdersScreen(api: api),
    'order details': (api) => OrderDetailScreen(api: api, orderId: 1),
    'follow-ups': (api) => FollowupsScreen(api: api),
    'staff tasks': (api) => AdminTasksScreen(api: api),
    'order requests': (api) => OrderWorkRequestsScreen(api: api),
    'issuements': (api) => IssuementsScreen(api: api),
    'issuement details': (api) =>
        IssuementDetailScreen(api: api, voucherNo: 'ISS-2026-000001'),
    'diamond bags': (api) => DiamondBagsScreen(api: api),
    'diamond bag details': (api) => DiamondBagDetailScreen(api: api, bagId: 1),
  };
  for (final width in [320.0, 1100.0]) {
    for (final page in pages.entries) {
      testWidgets('${page.key} wraps long content at ${width.toInt()}px', (
        tester,
      ) async {
        tester.view.physicalSize = Size(width, 900);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.light(),
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: const TextScaler.linear(1.2)),
              child: child!,
            ),
            home: Scaffold(body: page.value(_WorkflowApi())),
          ),
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        final lists = find.byType(ListView);
        for (var step = 0; step < 6 && lists.evaluate().isNotEmpty; step++) {
          await tester.drag(lists.first, const Offset(0, -550));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        }
      });
    }
  }
}
