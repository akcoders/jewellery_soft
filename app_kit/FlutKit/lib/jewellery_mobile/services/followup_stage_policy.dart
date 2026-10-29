class FollowupStagePolicy {
  FollowupStagePolicy._();

  static const defaultStages = ['Confirmed', 'In Production', 'QC', 'Ready'];
  static const _closedStages = {
    'complete',
    'completed',
    'packed',
    'dispatched',
    'delivered',
    'cancelled',
  };

  /// Old API responses may still include order statuses after production.
  static List<String> selectableStages(Iterable<String> stages) {
    final production = <String>{};
    for (final raw in stages) {
      final stage = raw.trim();
      final normalized = stage.toLowerCase();
      if (stage.isEmpty || _closedStages.contains(normalized)) continue;
      if (normalized == 'ready') continue;
      production.add(
        defaultStages.firstWhere(
          (value) => value.toLowerCase() == normalized,
          orElse: () => stage,
        ),
      );
    }
    if (production.isEmpty && stages.isEmpty) {
      return List.of(defaultStages);
    }
    return [...production, 'Ready'];
  }

  static bool requiresNextFollowup(String? stage) =>
      stage != null && stage.trim().toLowerCase() != 'ready';

  static String? validateNextFollowup(
    String value, {
    required bool required,
    DateTime? now,
  }) {
    if (!required) return null;
    if (value.trim().isEmpty) {
      return 'Next follow-up date is required until Ready';
    }
    final raw = value.trim();
    final parts = RegExp(
      r'^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$',
    ).firstMatch(raw);
    final date = DateTime.tryParse(raw);
    if (parts == null ||
        date == null ||
        date.year != int.parse(parts[1]!) ||
        date.month != int.parse(parts[2]!) ||
        date.day != int.parse(parts[3]!) ||
        date.hour != int.parse(parts[4]!) ||
        date.minute != int.parse(parts[5]!) ||
        date.second != int.parse(parts[6] ?? '0')) {
      return 'Enter a valid date and time (YYYY-MM-DD HH:MM)';
    }
    if (!date.isAfter(now ?? DateTime.now())) {
      return 'Next follow-up time must be in the future';
    }
    return null;
  }
}
