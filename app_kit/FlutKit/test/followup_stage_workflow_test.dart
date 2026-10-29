import 'package:flutkit/jewellery_mobile/screens/order_followup_form_screen.dart'
    as native;
import 'package:flutkit/jewellery_mobile/services/followup_stage_policy.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/screens/order_followup_form_screen.dart'
    as pwa;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _FollowupApi extends MobileApiService {
  _FollowupApi() : super(baseUrl: 'https://example.test');

  final List<Map<String, dynamic>> saved = [];

  @override
  Future<Map<String, dynamic>> addFollowup({
    required int orderId,
    required String stage,
    required String description,
    String nextFollowupDate = '',
    String imageBase64 = '',
    String imageSource = 'camera',
    bool sameForSubOrders = true,
    List<Map<String, dynamic>> childFollowups = const [],
  }) async {
    saved.add({
      'stage': stage,
      'description': description,
      'next_followup_date': nextFollowupDate,
      'same_for_sub_orders': sameForSubOrders,
      'child_followups': childFollowups,
    });
    return {
      'notification': {'queued': true},
    };
  }
}

const _family = [
  {'id': 1, 'order_no': 'ORD-1', 'order_name': 'Parent necklace'},
  {'id': 2, 'order_no': 'ORD-2', 'order_name': 'Matching earrings'},
];

Finder _input(String label) => find.byWidgetPredicate(
  (widget) => widget is TextField && widget.decoration?.labelText == label,
);

Future<void> _save(WidgetTester tester) async {
  final save = find.text('Save Followup');
  await tester.ensureVisible(save);
  await tester.tap(save);
  await tester.pumpAndSettle();
}

Future<void> _selectStage(WidgetTester tester, String stage) async {
  final dropdown = find.byType(DropdownButtonFormField<String>);
  await tester.ensureVisible(dropdown);
  await tester.tap(dropdown);
  await tester.pumpAndSettle();
  await tester.tap(find.text(stage).last);
  await tester.pumpAndSettle();
}

Future<void> _futureDate(WidgetTester tester) async {
  final date = find.text('Select next followup date *');
  await tester.ensureVisible(date);
  await tester.tap(date);
  await tester.pumpAndSettle();
  await tester.tap(find.byTooltip('Next month'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('15').last);
  await tester.tap(find.text('OK'));
  await tester.pumpAndSettle();
}

void main() {
  test('empty API fallback contains production stages ending at Ready', () {
    expect(FollowupStagePolicy.selectableStages([]), [
      'Confirmed',
      'In Production',
      'QC',
      'Ready',
    ]);
    expect(
      FollowupStagePolicy.selectableStages([
        'Completed',
        ' confirmed ',
        'QC',
        'READY',
        'Complete',
        'Packed',
        'Dispatched',
        'Delivered',
        'Cancelled',
      ]),
      ['Confirmed', 'QC', 'Ready'],
    );
  });

  test(
    'active followups need a valid future reminder and Ready needs none',
    () {
      final now = DateTime(2026, 10, 29, 10);
      expect(
        FollowupStagePolicy.validateNextFollowup('', required: true, now: now),
        isNotNull,
      );
      expect(
        FollowupStagePolicy.validateNextFollowup(
          'invalid',
          required: true,
          now: now,
        ),
        isNotNull,
      );
      expect(
        FollowupStagePolicy.validateNextFollowup(
          '2026-10-29 09:00',
          required: true,
          now: now,
        ),
        isNotNull,
      );
      expect(
        FollowupStagePolicy.validateNextFollowup(
          '2026-13-40 09:00',
          required: true,
          now: now,
        ),
        isNotNull,
      );
      expect(
        FollowupStagePolicy.validateNextFollowup(
          '2026-10-30 09:00',
          required: true,
          now: now,
        ),
        isNull,
      );
      expect(
        FollowupStagePolicy.validateNextFollowup('', required: false, now: now),
        isNull,
      );
    },
  );

  for (final app in ['native', 'pwa']) {
    Future<void> mount(
      WidgetTester tester,
      _FollowupApi api, {
      List<String> stages = const [
        'Completed',
        'Confirmed',
        'In Production',
        'QC',
        'Ready',
        'Complete',
        'Packed',
      ],
      bool family = false,
    }) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                child: const Text('Open followup'),
                onPressed: () => Navigator.push<void>(
                  context,
                  MaterialPageRoute(
                    builder: (_) => app == 'native'
                        ? native.OrderFollowupFormScreen(
                            api: api,
                            orderId: 1,
                            stages: stages,
                            familyOrders: family ? _family : const [],
                          )
                        : pwa.OrderFollowupFormScreen(
                            api: api,
                            orderId: 1,
                            stages: stages,
                            familyOrders: family ? _family : const [],
                          ),
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Open followup'));
      await tester.pumpAndSettle();
    }

    testWidgets(
      '$app hides stale closed stages and submits Ready without a reminder',
      (tester) async {
        final api = _FollowupApi();
        await mount(tester, api);
        await tester.tap(find.byType(DropdownButtonFormField<String>));
        await tester.pumpAndSettle();
        for (final stage in ['Completed', 'Complete', 'Packed']) {
          expect(find.text(stage), findsNothing);
        }
        for (final stage in ['Confirmed', 'In Production', 'QC', 'Ready']) {
          expect(find.text(stage), findsWidgets);
        }
        await tester.tap(find.text('Ready').last);
        await tester.pumpAndSettle();
        await tester.enterText(
          _input('Description'),
          'Jewellery is ready for receiving.',
        );
        expect(find.text('Select next followup date *'), findsNothing);
        await _save(tester);
        expect(api.saved.single['stage'], 'Ready');
        expect(api.saved.single['next_followup_date'], isEmpty);
        expect(tester.takeException(), isNull);
      },
    );

    for (final stage in ['Confirmed', 'In Production', 'QC']) {
      testWidgets('$app requires next followup before Ready: $stage', (
        tester,
      ) async {
        final api = _FollowupApi();
        await mount(tester, api, stages: [stage, 'Ready']);
        await tester.enterText(
          _input('Description'),
          'Production is progressing.',
        );
        await _save(tester);
        expect(api.saved, isEmpty);
        expect(
          find.text('Next follow-up date is required until Ready'),
          findsOneWidget,
        );
        await _futureDate(tester);
        expect(
          find.text('Next follow-up date is required until Ready'),
          findsNothing,
        );
        await _save(tester);
        expect(api.saved.single['stage'], stage);
        expect(
          DateTime.parse(
            api.saved.single['next_followup_date'] as String,
          ).isAfter(DateTime.now()),
          isTrue,
        );
        expect(tester.takeException(), isNull);
      });
    }

    testWidgets('$app separate family reminders remain required until Ready', (
      tester,
    ) async {
      final api = _FollowupApi();
      await mount(tester, api, stages: ['QC', 'Ready'], family: true);
      await tester.enterText(_input('Description'), 'Parent quality checked.');
      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();
      await tester.enterText(
        _input('Sub order follow-up'),
        'Earrings quality checked.',
      );
      await _futureDate(tester);
      await _save(tester);
      expect(api.saved, isEmpty);
      expect(
        find.text('Next follow-up date is required until Ready'),
        findsOneWidget,
      );

      await _selectStage(tester, 'Ready');
      expect(_input('Next follow-up (YYYY-MM-DD HH:MM)'), findsNothing);
      await _save(tester);
      final saved = api.saved.single;
      expect(saved['stage'], 'Ready');
      expect(saved['next_followup_date'], isEmpty);
      expect(saved['same_for_sub_orders'], isFalse);
      expect(saved['child_followups'], [
        {
          'order_id': 2,
          'stage': 'Ready',
          'description': 'Earrings quality checked.',
          'next_followup_date': '',
        },
      ]);
      expect(tester.takeException(), isNull);
    });

    testWidgets('$app shared family mode uses one future production reminder', (
      tester,
    ) async {
      final api = _FollowupApi();
      await mount(
        tester,
        api,
        stages: ['In Production', 'Ready'],
        family: true,
      );
      await tester.enterText(
        _input('Description'),
        'Family remains in production.',
      );
      await _futureDate(tester);
      await _save(tester);
      expect(api.saved.single['stage'], 'In Production');
      expect(api.saved.single['same_for_sub_orders'], isTrue);
      expect(
        DateTime.parse(
          api.saved.single['next_followup_date'] as String,
        ).isAfter(DateTime.now()),
        isTrue,
      );
      expect(tester.takeException(), isNull);
    });
  }
}
