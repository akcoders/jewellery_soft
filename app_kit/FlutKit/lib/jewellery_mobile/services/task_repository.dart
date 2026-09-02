import 'package:flutkit/jewellery_mobile/services/local_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';

int taskReminderNotificationId(int taskId) {
  const namespaceStart = 1000000000;
  const namespaceSize = 1000000000;
  return namespaceStart + taskId.abs() % namespaceSize;
}

abstract interface class TaskReminderScheduler {
  Future<void> cancel(int id);
}

class DeviceTaskReminderScheduler implements TaskReminderScheduler {
  DeviceTaskReminderScheduler({LocalNotificationService? notifications})
    : _notifications = notifications ?? LocalNotificationService.instance;

  final LocalNotificationService _notifications;

  @override
  Future<void> cancel(int id) => _notifications.cancel(id);
}

class TaskItem {
  TaskItem({
    required this.id,
    required this.title,
    required this.note,
    required this.scheduledAt,
    required this.createdAt,
    required this.isDone,
    required this.status,
    required this.priority,
    required this.assignedByName,
    required this.isOverdue,
    required this.scoreDelta,
  });

  final int id;
  final String title;
  final String note;
  final DateTime scheduledAt;
  final DateTime createdAt;
  final bool isDone;
  final String status;
  final String priority;
  final String assignedByName;
  final bool isOverdue;
  final double scoreDelta;
  factory TaskItem.fromApi(Map<String, dynamic> json) {
    return TaskItem(
      id: _safeInt(json['id']),
      title: (json['title'] ?? '').toString(),
      note: (json['note'] ?? '').toString(),
      scheduledAt: _safeDateTime(json['scheduled_at']) ?? DateTime.now(),
      createdAt: _safeDateTime(json['created_at']) ?? DateTime.now(),
      isDone: _safeBool(json['is_done']),
      status: (json['status'] ?? 'pending').toString(),
      priority: (json['priority'] ?? 'normal').toString(),
      assignedByName: (json['assigned_by_name'] ?? 'Admin').toString(),
      isOverdue: _safeBool(json['is_overdue']),
      scoreDelta: double.tryParse((json['score_delta'] ?? 0).toString()) ?? 0,
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
  TaskRepository({
    required MobileApiService api,
    TaskReminderScheduler? reminderScheduler,
  }) : _api = api,
       _reminderScheduler = reminderScheduler ?? DeviceTaskReminderScheduler();

  final MobileApiService _api;
  final TaskReminderScheduler _reminderScheduler;

  Future<List<TaskItem>> load({String query = ''}) async {
    final rows = await _api.fetchTasks(query: query);
    return rows
        .whereType<Map>()
        .map((row) => TaskItem.fromApi(row.cast<String, dynamic>()))
        .toList();
  }

  Future<void> complete({
    required int id,
    required String proofBase64,
    required String proofNote,
  }) async {
    await _api.completeTask(
      id: id,
      proofBase64: proofBase64,
      proofNote: proofNote,
    );
    await _reminderScheduler.cancel(taskReminderNotificationId(id));
  }
}
