import 'dart:async';
import 'package:flutkit/jewellery_pwa/screens/dashboard_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/inventory_tab.dart';
import 'package:flutkit/jewellery_pwa/screens/mobile_approvals_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/performance_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/team_performance_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/transactions_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets(
    'approval filters retain readable resolved typography when selected',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light(),
          home: Scaffold(body: MobileApprovalsScreen(api: _WorkspaceApi())),
        ),
      );
      await tester.pumpAndSettle();
      TextStyle labelStyle(Finder chip) {
        final label = find
            .descendant(of: chip, matching: find.byType(Text))
            .first;
        return tester.renderObject<RenderParagraph>(label).text.style!;
      }

      final selected = find.byWidgetPredicate(
        (widget) => widget is FilterChip && widget.selected,
      );
      final unselected = find.byWidgetPredicate(
        (widget) => widget is FilterChip && !widget.selected,
      );
      expect(labelStyle(selected).fontFamily, 'Manrope');
      expect(labelStyle(selected).color, Colors.white);
      expect(labelStyle(unselected).fontFamily, 'Manrope');
      expect(labelStyle(unselected).color, AppColors.textSecondary);
      await tester.tap(unselected);
      await tester.pumpAndSettle();
      expect(labelStyle(selected).color, Colors.white);
      expect(labelStyle(unselected).color, AppColors.textSecondary);
      expect(tester.takeException(), isNull);
    },
  );

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
        TeamPerformanceScreen(api: api),
        MobileApprovalsScreen(api: api),
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
  Future<Map<String, dynamic>> fetchTeamPerformance({
    int? year,
    int? month,
  }) async => {
    'score_rules': {'base_score': 100, 'monthly_target': 200},
    'totals': {
      'monthly_target': 200,
      'total_staff_count': 2,
      'staff_count': 2,
      'target_achievers': 1,
      'reward_points_total': 1234.5,
      'average_target_progress': 92,
      'average_score': 212.4,
      'on_time_rate': 88,
      'overdue_actions': 3,
      'approved_followups': 120,
      'action_required_count': 1,
      'point_earned_breakdown': {'Approved follow-up with a long reason': 240},
      'point_loss_breakdown': {'Follow-up delay': 12},
    },
    'rows': [
      {
        'id': 1,
        'name': 'A team member with a very long display name indeed',
        'role_label': 'Senior Sales Executive, Store Manager',
        'score': 1434.5,
        'reward_points': 1234.5,
        'on_time_rate': 100,
        'approved_followups': 110,
        'overdue_actions': 3,
        'disapproved_followups': 2,
        'needs_attention': true,
        'analysis_summary':
            'A long explanation of why this member needs attention.',
        'has_scored_activity': true,
        'performance_band': 'Excellent',
      },
      {
        'id': 2,
        'name': 'Short',
        'score': 90,
        'points_to_target': 110,
        'has_scored_activity': false,
      },
    ],
  };

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

  @override
  Future<Map<String, dynamic>> fetchApprovalRequests() async => {
    'can_review': true,
    'items': [
      {
        'id': 101,
        'request_type': 'followup',
        'status': 'pending',
        'requested_by_name': 'Production Coordinator',
        'created_at': '2026-10-12 10:00:00',
        'followup_order_id': 25,
        'followup_order_no': 'ORD-2026-00025',
        'followup_chain_count': 3,
        'followup_chain_position': 2,
        'followup_is_oldest_pending': true,
        'payload': {
          'order_id': 25,
          'order_no': 'ORD-2026-00025',
          'followup_id': 202,
          'stage': 'In Production',
          'description':
              'Casting completed and the piece moved to detailed finishing.',
          'next_followup_date': '2026-10-14 11:00:00',
        },
        'followup_chain': [
          {
            'id': 201,
            'stage': 'Assigned',
            'description': 'Design and material details shared with workshop.',
            'followup_taken_by_name': 'Production Coordinator',
            'followup_taken_on': '2026-10-10 09:00:00',
            'next_followup_date': '2026-10-12 10:00:00',
            'approval_status': 'approved',
          },
          {
            'id': 202,
            'stage': 'In Production',
            'description':
                'Casting completed and the piece moved to detailed finishing.',
            'followup_taken_by_name': 'Production Coordinator',
            'followup_taken_on': '2026-10-12 10:00:00',
            'next_followup_date': '2026-10-14 11:00:00',
            'approval_status': 'pending',
          },
          {
            'id': 203,
            'stage': 'QC',
            'description':
                'Finishing completed and the jewellery entered quality control.',
            'followup_taken_by_name': 'Production Coordinator',
            'followup_taken_on': '2026-10-14 09:30:00',
            'next_followup_date': '2026-10-15 11:00:00',
            'approval_status': 'pending',
          },
        ],
      },
      {
        'id': 102,
        'request_type': 'followup',
        'status': 'pending',
        'requested_by_name': 'Production Coordinator',
        'created_at': '2026-10-14 09:30:00',
        'followup_order_id': 25,
        'followup_order_no': 'ORD-2026-00025',
        'followup_chain_count': 3,
        'followup_chain_position': 3,
        'followup_is_oldest_pending': false,
        'payload': {
          'order_id': 25,
          'order_no': 'ORD-2026-00025',
          'followup_id': 203,
          'stage': 'QC',
          'description':
              'Finishing completed and the jewellery entered quality control.',
          'next_followup_date': '2026-10-15 11:00:00',
        },
        'followup_chain': [],
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
