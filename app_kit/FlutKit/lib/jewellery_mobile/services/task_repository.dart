import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';

class TaskItem {
  TaskItem({
    required this.id,
    required this.title,
    required this.note,
    required this.scheduledAt,
    required this.createdAt,
    required this.isDone,
    required this.status,
    required this.notificationQueued,
  });

  final int id;
  final String title;
  final String note;
  final DateTime scheduledAt;
  final DateTime createdAt;
  final bool isDone;
  final String status;
  final bool notificationQueued;

  factory TaskItem.fromApi(Map<String, dynamic> json) {
    return TaskItem(
      id: _safeInt(json['id']),
      title: (json['title'] ?? '').toString(),
      note: (json['note'] ?? '').toString(),
      scheduledAt: _safeDateTime(json['scheduled_at']) ?? DateTime.now(),
      createdAt: _safeDateTime(json['created_at']) ?? DateTime.now(),
      isDone: _safeBool(json['is_done']),
      status: (json['status'] ?? 'pending').toString(),
      notificationQueued: _safeBool(json['notification_queued']),
    );
  }

  static int _safeInt(dynamic value) {
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse((value ?? '').toString()) ?? 0;
  }

  static bool _safeBool(dynamic value) {
    if (value is bool) {
      return value;
    }
    if (value is num) {
      return value.toInt() == 1;
    }
    final normalized = (value ?? '').toString().trim().toLowerCase();
    return normalized == '1' || normalized == 'true' || normalized == 'yes';
  }

  static DateTime? _safeDateTime(dynamic value) {
    final raw = (value ?? '').toString().trim();
    if (raw.isEmpty) {
      return null;
    }
    return DateTime.tryParse(raw);
  }
}

class TaskRepository {
  TaskRepository({required MobileApiService api}) : _api = api;

  final MobileApiService _api;

  Future<List<TaskItem>> load({String query = ''}) async {
    final rows = await _api.fetchTasks(query: query);
    return rows
        .whereType<Map>()
        .map((row) => TaskItem.fromApi(row.cast<String, dynamic>()))
        .toList();
  }

  Future<TaskItem?> create({
    required String title,
    required String note,
    required DateTime scheduledAt,
  }) async {
    final result = await _api.createTask(
      title: title,
      note: note,
      scheduledAt: scheduledAt.toIso8601String(),
    );
    final task = result['task'];
    if (task is Map) {
      return TaskItem.fromApi(task.cast<String, dynamic>());
    }
    return null;
  }

  Future<void> delete(int id) async {
    await _api.deleteTask(id);
  }
}
