import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final scheduledAt = DateTime(2026, 8, 26, 11, 30);

  group('TaskRepository notification fallback', () {
    test('does not schedule locally when remote push was queued', () async {
      final api = _FakeTaskApi(_taskResponse(queued: true));
      final scheduler = _FakeReminderScheduler();
      final repository = TaskRepository(api: api, reminderScheduler: scheduler);

      final task = await repository.create(
        title: 'Call customer',
        note: 'Discuss order',
        scheduledAt: scheduledAt,
      );

      expect(task?.notificationQueued, isTrue);
      expect(task?.localNotificationScheduled, isFalse);
      expect(scheduler.scheduled, isEmpty);
      expect(api.fallbackConfirmations, isEmpty);
    });

    test('schedules a device reminder when server reports failure', () async {
      final api = _FakeTaskApi(
        _taskResponse(queued: false, message: 'Push is unavailable.'),
      );
      final scheduler = _FakeReminderScheduler();
      final repository = TaskRepository(api: api, reminderScheduler: scheduler);

      final task = await repository.create(
        title: 'Call customer',
        note: 'Discuss order',
        scheduledAt: scheduledAt,
      );

      expect(task?.notificationQueued, isFalse);
      expect(task?.localNotificationScheduled, isTrue);
      expect(task?.hasScheduledReminder, isTrue);
      expect(scheduler.scheduled, hasLength(1));
      expect(scheduler.scheduled.single.id, taskReminderNotificationId(42));
      expect(scheduler.scheduled.single.title, 'Task Reminder');
      expect(scheduler.scheduled.single.body, 'Discuss order');
      expect(scheduler.scheduled.single.scheduledAt, scheduledAt);
      expect(api.fallbackConfirmations, [(99, true)]);
    });

    test('does not infer failure when server omits push result', () async {
      final api = _FakeTaskApi(_taskResponse());
      final scheduler = _FakeReminderScheduler();
      final repository = TaskRepository(api: api, reminderScheduler: scheduler);

      final task = await repository.create(
        title: 'Call customer',
        note: '',
        scheduledAt: scheduledAt,
      );

      expect(task?.hasScheduledReminder, isFalse);
      expect(scheduler.scheduled, isEmpty);
      expect(api.fallbackConfirmations, isEmpty);
    });

    test('keeps a saved task when device scheduling fails', () async {
      final api = _FakeTaskApi(_taskResponse(queued: false));
      final scheduler = _FakeReminderScheduler(throwOnSchedule: true);
      final repository = TaskRepository(api: api, reminderScheduler: scheduler);

      final task = await repository.create(
        title: 'Call customer',
        note: '',
        scheduledAt: scheduledAt,
      );

      expect(task, isNotNull);
      expect(task?.hasScheduledReminder, isFalse);
      expect(task?.notificationMessage, contains('device reminder failed'));
      expect(api.fallbackConfirmations, [(99, false)]);
    });

    test('cancels the namespaced local reminder after deletion', () async {
      final api = _FakeTaskApi(_taskResponse());
      final scheduler = _FakeReminderScheduler();
      final repository = TaskRepository(api: api, reminderScheduler: scheduler);

      await repository.delete(42);

      expect(api.deletedTaskId, 42);
      expect(scheduler.cancelled, [taskReminderNotificationId(42)]);
    });
  });
}

Map<String, dynamic> _taskResponse({bool? queued, String? message}) {
  return <String, dynamic>{
    'task': <String, dynamic>{
      'id': 42,
      'title': 'Call customer',
      'note': 'Discuss order',
      'scheduled_at': '2026-08-26 11:30:00',
      'created_at': '2026-08-25 10:00:00',
      'is_done': 0,
      'status': 'pending',
    },
    if (queued != null)
      'notification': <String, dynamic>{
        'queued': queued,
        'notification_id': 99,
        if (message != null) 'message': message,
      },
  };
}

class _FakeTaskApi extends MobileApiService {
  _FakeTaskApi(this.createResponse) : super(baseUrl: 'https://example.test');

  final Map<String, dynamic> createResponse;
  int? deletedTaskId;
  final List<(int, bool)> fallbackConfirmations = [];

  @override
  Future<Map<String, dynamic>> createTask({
    required String title,
    required String note,
    required String scheduledAt,
  }) async {
    return createResponse;
  }

  @override
  Future<void> deleteTask(int id) async {
    deletedTaskId = id;
  }

  @override
  Future<void> confirmNotificationLocalFallback(
    int id, {
    required bool scheduled,
  }) async {
    fallbackConfirmations.add((id, scheduled));
  }
}

class _ScheduledReminder {
  const _ScheduledReminder({
    required this.id,
    required this.title,
    required this.body,
    required this.scheduledAt,
  });

  final int id;
  final String title;
  final String body;
  final DateTime scheduledAt;
}

class _FakeReminderScheduler implements TaskReminderScheduler {
  _FakeReminderScheduler({this.throwOnSchedule = false});

  final bool throwOnSchedule;
  final List<_ScheduledReminder> scheduled = [];
  final List<int> cancelled = [];

  @override
  Future<bool> schedule({
    required int id,
    required String title,
    required String body,
    required DateTime scheduledAt,
  }) async {
    if (throwOnSchedule) throw StateError('scheduler unavailable');
    scheduled.add(
      _ScheduledReminder(
        id: id,
        title: title,
        body: body,
        scheduledAt: scheduledAt,
      ),
    );
    return true;
  }

  @override
  Future<void> cancel(int id) async {
    cancelled.add(id);
  }
}
