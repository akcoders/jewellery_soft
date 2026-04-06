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

  Future<void> init() async {
    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const settings = InitializationSettings(android: android);
    await _plugin.initialize(settings);
    final tzInfo = await FlutterTimezone.getLocalTimezone();
    final String timeZoneName = tzInfo.identifier;
    tz.initializeTimeZones();
    tz.setLocalLocation(tz.getLocation(timeZoneName));

    final androidPlugin = _plugin.resolvePlatformSpecificImplementation<
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
  }

  Future<void> requestPermission() async {
    final android = _plugin
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >();
    await android?.requestNotificationsPermission();
    await android?.requestExactAlarmsPermission();
  }

  Future<void> scheduleNotification({
    required int id,
    required String title,
    required String body,
    required DateTime scheduledAt,
  }) async {
    if (!_initialized) {
      await init();
    }
    await requestPermission();

    final now = DateTime.now();
    final safeDateTime = scheduledAt.isBefore(now.add(const Duration(seconds: 2)))
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
  }

  Future<void> cancel(int id) async {
    await _plugin.cancel(id);
  }
}
