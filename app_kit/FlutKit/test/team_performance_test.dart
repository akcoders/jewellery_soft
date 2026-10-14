import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/screens/performance_screen.dart';
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
  testWidgets('team performance ranks staff, shows target and rewards', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1280, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final api = _TeamApi();

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: TeamPerformanceScreen(api: api)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('1 of 3 reached the 200-point target'), findsOneWidget);
    expect(find.text('+24.5 reward pts'), findsOneWidget);
    expect(find.text('40 to target'), findsOneWidget);
    expect(find.text('2 assigned actions are overdue.'), findsOneWidget);

    // Highest score is ranked first.
    final ravi = tester.getTopLeft(find.text('#1'));
    final priya = tester.getTopLeft(find.text('#2'));
    expect(ravi.dy, lessThan(priya.dy));

    // Filter to people who need attention.
    await tester.tap(find.text('Needs attention · 1'));
    await tester.pumpAndSettle();
    // The podium still shows everyone; only the leaderboard is filtered.
    expect(find.text('1 of 3'), findsOneWidget);
    expect(find.text('#3'), findsNothing);

    // Drill into a member.
    await tester.tap(find.text('Priya Sales').last);
    await tester.pumpAndSettle();
    expect(api.requestedMember, 2);
    expect(find.byType(PerformanceScreen), findsOneWidget);
    expect(find.text('Monthly target reached'), findsOneWidget);
  });
}
