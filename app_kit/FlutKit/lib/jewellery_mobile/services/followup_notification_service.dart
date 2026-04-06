class FollowupNotificationService {
  FollowupNotificationService._();

  static const Set<String> inactiveStatuses = {
    'Ready',
    'Complete',
    'Completed',
    'Packed',
    'Delivered',
    'Dispatched',
    'Cancelled',
  };

  static Future<void> syncFromOrders(List<dynamic> orders) async {
    return;
  }

  static DateTime? normalizedFollowupTime(dynamic value) {
    return _normalizedFollowupTime(value);
  }

  static DateTime? _normalizedFollowupTime(dynamic value) {
    final raw = (value ?? '').toString().trim();
    if (raw.isEmpty) {
      return null;
    }

    final parsed = DateTime.tryParse(raw);
    if (parsed == null) {
      return null;
    }

    if (parsed.hour == 0 && parsed.minute == 0 && parsed.second == 0) {
      return DateTime(parsed.year, parsed.month, parsed.day, 9, 0);
    }

    return parsed;
  }
}
