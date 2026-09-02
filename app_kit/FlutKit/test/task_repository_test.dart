import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Assigned staff task repository', () {
    test('loads admin-assigned task scoring details', () async {
      final api = _FakeTaskApi();
      final repository = TaskRepository(
        api: api,
        reminderScheduler: _FakeReminderScheduler(),
      );

      final tasks = await repository.load();

      expect(tasks, hasLength(1));
      expect(tasks.single.id, 42);
      expect(tasks.single.title, 'Photograph packed order');
      expect(tasks.single.assignedByName, 'Shweta');
      expect(tasks.single.priority, 'urgent');
      expect(tasks.single.isOverdue, isTrue);
      expect(tasks.single.scoreDelta, -2);
    });

    test('submits compulsory proof and cancels the local reminder', () async {
      final api = _FakeTaskApi();
      final scheduler = _FakeReminderScheduler();
      final repository = TaskRepository(api: api, reminderScheduler: scheduler);

      await repository.complete(
        id: 42,
        proofBase64: 'proof-image-data',
        proofNote: 'Packing verified',
      );

      expect(api.completedTaskId, 42);
      expect(api.completedProof, 'proof-image-data');
      expect(api.completedNote, 'Packing verified');
      expect(scheduler.cancelled, [taskReminderNotificationId(42)]);
    });
  });
}

class _FakeTaskApi extends MobileApiService {
  _FakeTaskApi() : super(baseUrl: 'https://example.test');

  int? completedTaskId;
  String completedProof = '';
  String completedNote = '';

  @override
  Future<List<dynamic>> fetchTasks({String query = ''}) async => [
    {
      'id': 42,
      'title': 'Photograph packed order',
      'note': 'Attach the completed packing photo.',
      'scheduled_at': '2026-08-26 11:30:00',
      'created_at': '2026-08-25 10:00:00',
      'is_done': 0,
      'status': 'pending',
      'priority': 'urgent',
      'assigned_by_name': 'Shweta',
      'is_overdue': 1,
      'score_delta': -2,
    },
  ];

  @override
  Future<Map<String, dynamic>> completeTask({
    required int id,
    required String proofBase64,
    required String proofNote,
  }) async {
    completedTaskId = id;
    completedProof = proofBase64;
    completedNote = proofNote;
    return {'points': -2};
  }
}

class _FakeReminderScheduler implements TaskReminderScheduler {
  final List<int> cancelled = [];

  @override
  Future<void> cancel(int id) async {
    cancelled.add(id);
  }
}
