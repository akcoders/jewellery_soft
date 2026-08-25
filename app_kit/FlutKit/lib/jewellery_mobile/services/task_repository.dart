import 'package:flutkit/jewellery_mobile/services/local_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';

int taskReminderNotificationId(int taskId) {
  const namespaceStart = 1000000000;
  const namespaceSize = 1000000000;
  return namespaceStart + taskId.abs() % namespaceSize;
}

abstract interface class TaskReminderScheduler {
  Future<bool> schedule({
    required int id,
    required String title,
    required String body,
    required DateTime scheduledAt,
  });

  Future<void> cancel(int id);
}

class DeviceTaskReminderScheduler implements TaskReminderScheduler {
  DeviceTaskReminderScheduler({LocalNotificationService? notifications})
    : _notifications = notifications ?? LocalNotificationService.instance;

  final LocalNotificationService _notifications;

  @override
  Future<bool> schedule({
    required int id,
    required String title,
    required String body,
    required DateTime scheduledAt,
  }) async {
    if (!_notifications.isSupported) return false;
    return _notifications.scheduleNotification(
      id: id,
      title: title,
      body: body,
      scheduledAt: scheduledAt,
    );
  }

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
    required this.notificationQueued,
    this.localNotificationScheduled = false,
    this.notificationMessage = '',
  });

  final int id;
  final String title;
  final String note;
  final DateTime scheduledAt;
  final DateTime createdAt;
  final bool isDone;
  final String status;
  final bool notificationQueued;
  final bool localNotificationScheduled;
  final String notificationMessage;

  bool get hasScheduledReminder =>
      notificationQueued || localNotificationScheduled;

  String get saveConfirmation {
    if (notificationQueued) return 'Task saved. Push reminder scheduled.';
    if (localNotificationScheduled) {
      return 'Task saved. Device reminder scheduled.';
    }
    if (notificationMessage.isNotEmpty) {
      return 'Task saved. $notificationMessage';
    }
    return 'Task saved.';
  }

  factory TaskItem.fromApi(
    Map<String, dynamic> json, {
    bool? notificationQueued,
    bool localNotificationScheduled = false,
    String notificationMessage = '',
  }) {
    return TaskItem(
      id: _safeInt(json['id']),
      title: (json['title'] ?? '').toString(),
      note: (json['note'] ?? '').toString(),
      scheduledAt: _safeDateTime(json['scheduled_at']) ?? DateTime.now(),
      createdAt: _safeDateTime(json['created_at']) ?? DateTime.now(),
      isDone: _safeBool(json['is_done']),
      status: (json['status'] ?? 'pending').toString(),
      notificationQueued:
          notificationQueued ?? _safeBool(json['notification_queued']),
      localNotificationScheduled: localNotificationScheduled,
      notificationMessage: notificationMessage,
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
      final taskData = task.cast<String, dynamic>();
      final notification = result['notification'];
      final notificationData = notification is Map
          ? notification.cast<String, dynamic>()
          : null;
      final remoteResultReported =
          notificationData?.containsKey('queued') ?? false;
      final remoteQueued = notificationData?['queued'] == true;
      var localScheduled = false;
      var notificationMessage = (notificationData?['message'] ?? '')
          .toString()
          .trim();

      if (remoteResultReported && !remoteQueued) {
        final notificationId = TaskItem._safeInt(
          notificationData?['notification_id'],
        );
        try {
          final taskId = TaskItem._safeInt(taskData['id']);
          if (taskId > 0) {
            localScheduled = await _reminderScheduler.schedule(
              id: taskReminderNotificationId(taskId),
              title: 'Task Reminder',
              body: note.trim().isNotEmpty ? note.trim() : title.trim(),
              scheduledAt: scheduledAt,
            );
          }
          if (localScheduled) {
            notificationMessage =
                'Push was unavailable, so a device reminder was scheduled.';
          } else if (notificationMessage.isEmpty) {
            notificationMessage = 'The task was saved without a reminder.';
          }
        } catch (error) {
          notificationMessage =
              'The task was saved, but its device reminder failed: $error';
        }

        if (notificationId > 0) {
          try {
            await _api.confirmNotificationLocalFallback(
              notificationId,
              scheduled: localScheduled,
            );
          } catch (_) {
            final confirmationMessage = localScheduled
                ? ' Server confirmation is pending.'
                : ' The server will keep the remote retry available.';
            notificationMessage = '$notificationMessage$confirmationMessage'
                .trim();
          }
        }
      }

      return TaskItem.fromApi(
        taskData,
        notificationQueued: remoteQueued,
        localNotificationScheduled: localScheduled,
        notificationMessage: notificationMessage,
      );
    }
    return null;
  }

  Future<void> delete(int id) async {
    await _api.deleteTask(id);
    await _reminderScheduler.cancel(taskReminderNotificationId(id));
  }
}
