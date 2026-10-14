import 'package:flutkit/jewellery_mobile/screens/performance_screen.dart'
    as android;
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/screens/team_performance_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _TeamApi extends MobileApiService {
  _TeamApi() : super(baseUrl: 'https://example.test');

  int? requestedMember;

  @override
  Future<Map<String, dynamic>> fetchTeamPerformance({
    int? year,
    int? month,
  }) async => {
    'score_rules': {
      'base_score': 100,
      'monthly_target': 200,
      'reward_per_extra_point': 1,
    },
    'totals': {
      'monthly_target': 200,
      'total_staff_count': 3,
      'staff_count': 3,
      'target_achievers': 1,
      'reward_points_total': 24.5,
      'average_target_progress': 80,
      'average_score': 170.2,
      'on_time_rate': 91,
      'overdue_actions': 2,
      'approved_followups': 40,
      'action_required_count': 1,
      'point_earned_breakdown': {'Approved follow-up': 60, 'Order Entry': 8},
      'point_loss_breakdown': {'Follow-up delay': 6},
    },
    'rows': [
      {
        'id': 2,
        'name': 'Priya Sales',
        'role_label': 'Sales Executive',
        'score': 160,
        'points_to_target': 40,
        'reward_points': 0,
        'on_time_rate': 80,
        'approved_followups': 12,
        'overdue_actions': 2,
        'needs_attention': true,
        'analysis_summary': '2 assigned actions are overdue.',
        'has_scored_activity': true,
        'performance_band': 'Schedule risk',
      },
      {
        'id': 1,
        'name': 'Ravi Shah',
        'role_label': 'Sales Executive',
        'score': 224.5,
        'points_to_target': 0,
        'reward_points': 24.5,
        'on_time_rate': 100,
        'approved_followups': 20,
        'overdue_actions': 0,
        'needs_attention': false,
        'has_scored_activity': true,
        'performance_band': 'Excellent',
      },
      {
        'id': 3,
        'name': 'Nisha Store',
        'role_label': 'Store',
        'score': 126,
        'points_to_target': 74,
        'reward_points': 0,
        'on_time_rate': 95,
        'approved_followups': 8,
        'needs_attention': false,
        'has_scored_activity': true,
        'performance_band': 'Strong',
      },
    ],
  };

  @override
  Future<Map<String, dynamic>> fetchTeamMemberPerformance(
    int userId, {
    int? year,
    int? month,
  }) async {
    requestedMember = userId;
    return {
      'score_rules': {'base_score': 100, 'monthly_target': 200},
      'summary': {'score': 224.5, 'reward_points': 24.5, 'monthly_target': 200},
      'events': [],
    };
  }
}

void main() {
  testWidgets('android member view shows target and reward on a small phone', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(320, 900);
    addTearDown(tester.view.reset);
    final api = _TeamApi();

    await tester.pumpWidget(
      MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: const TextScaler.linear(1.5)),
          child: child!,
        ),
        home: Scaffold(
          body: android.PerformanceScreen(
            api: api,
            memberId: 1,
            memberName: 'Ravi Shah',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(api.requestedMember, 1);
    expect(find.text('Target reached'), findsOneWidget);
    await tester.drag(find.byType(ListView).first, const Offset(0, -300));
    await tester.pumpAndSettle();
    expect(find.text('Monthly target reached'), findsOneWidget);
    for (var i = 0; i < 8; i++) {
      expect(tester.takeException(), isNull);
      await tester.drag(find.byType(ListView).first, const Offset(0, -400));
      await tester.pumpAndSettle();
    }
    expect(tester.takeException(), isNull);
  });

  testWidgets('android team screen opens the android member screen', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(1280, 2400);
    addTearDown(tester.view.reset);
    final api = _TeamApi();

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: TeamPerformanceScreen(
            api: api,
            memberBuilder: (id, name, period) => android.PerformanceScreen(
              api: api,
              memberId: id,
              memberName: name,
              initialPeriod: period,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ravi Shah').last);
    await tester.pumpAndSettle();
    expect(find.byType(android.PerformanceScreen), findsOneWidget);
    expect(api.requestedMember, 1);
  });
}
