import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:timezone/data/latest.dart' as tz;
import 'package:timezone/timezone.dart' as tz;

class LocalNotificationService {
  LocalNotificationService._();

  static const String channelId = 'aabhushan_alerts';
  static const String channelName = 'Aabhushan Alerts';
  static const String customSoundName = 'aabhushan_alert';

  static final LocalNotificationService instance = LocalNotificationService._();

  final FlutterLocalNotificationsPlugin _plugin =
      FlutterLocalNotificationsPlugin();
  bool _initialized = false;
  String? _lastError;

  bool get isSupported =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;
  bool get isInitialized => _initialized;
  String? get lastError => _lastError;

  Future<void> init() async {
    if (_initialized || !isSupported) return;

    try {
      const android = AndroidInitializationSettings(
        'ic_stat_onesignal_default',
      );
      const settings = InitializationSettings(android: android);
      await _plugin.initialize(
        settings,
        onDidReceiveNotificationResponse: (_) => TaskRefreshBus.notify(),
      );
      final tzInfo = await FlutterTimezone.getLocalTimezone();
      final String timeZoneName = tzInfo.identifier;
      tz.initializeTimeZones();
      tz.setLocalLocation(tz.getLocation(timeZoneName));

      final androidPlugin = _plugin
          .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin
          >();
      await androidPlugin?.createNotificationChannel(
        const AndroidNotificationChannel(
          channelId,
          channelName,
          description: 'Task and followup reminders',
          importance: Importance.high,
          playSound: true,
          sound: RawResourceAndroidNotificationSound(customSoundName),
        ),
      );
      _initialized = true;
      _lastError = null;
    } catch (error) {
      _initialized = false;
      _lastError = error.toString();
    }
  }

  Future<bool> requestPermission() async {
    if (!isSupported) return false;
    if (!_initialized) await init();
    if (!_initialized) return false;
    final android = _plugin
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >();
    final notificationsGranted = await android
        ?.requestNotificationsPermission();
    await android?.requestExactAlarmsPermission();
    return notificationsGranted ?? true;
  }

  Future<bool> scheduleNotification({
    required int id,
    required String title,
    required String body,
    required DateTime scheduledAt,
  }) async {
    if (!isSupported) return false;
    if (!_initialized) {
      await init();
    }
    if (!_initialized) return false;
    if (!await requestPermission()) return false;

    final now = DateTime.now();
    final safeDateTime =
        scheduledAt.isBefore(now.add(const Duration(seconds: 2)))
        ? now.add(const Duration(seconds: 5))
        : scheduledAt;

    final androidDetails = AndroidNotificationDetails(
      channelId,
      channelName,
      channelDescription: 'Task reminders for scheduled followups',
      importance: Importance.high,
      priority: Priority.high,
      sound: const RawResourceAndroidNotificationSound(customSoundName),
      playSound: true,
    );

    final tzDateTime = tz.TZDateTime.from(safeDateTime, tz.local);

    try {
      await _plugin.zonedSchedule(
        id,
        title,
        body,
        tzDateTime,
        NotificationDetails(android: androidDetails),
        androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
        uiLocalNotificationDateInterpretation:
            UILocalNotificationDateInterpretation.absoluteTime,
        matchDateTimeComponents: null,
      );
    } catch (_) {
      await _plugin.zonedSchedule(
        id,
        title,
        body,
        tzDateTime,
        NotificationDetails(android: androidDetails),
        androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
        uiLocalNotificationDateInterpretation:
            UILocalNotificationDateInterpretation.absoluteTime,
        matchDateTimeComponents: null,
      );
    }
    return true;
  }

  Future<void> cancel(int id) async {
    if (!isSupported) return;
    try {
      if (!_initialized) await init();
      if (!_initialized) return;
      await _plugin.cancel(id);
      _lastError = null;
    } catch (error) {
      // The server-side task/notification is already complete at this point;
      // a device cancellation failure must not make that completed API action
      // look unsuccessful to the user.
      _lastError = error.toString();
    }
  }
}
