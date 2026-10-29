import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_pwa/widgets/performance_target_card.dart';
import 'package:flutter/material.dart';

class PerformanceScreen extends StatefulWidget {
  const PerformanceScreen({
    super.key,
    required this.api,
    this.memberId,
    this.memberName,
    this.initialPeriod,
  });

  final MobileApiService api;

  /// When set, an administrator is viewing this staff member's performance.
  final int? memberId;
  final String? memberName;
  final DateTime? initialPeriod;

  bool get isTeamView => memberId != null;

  @override
  State<PerformanceScreen> createState() => _PerformanceScreenState();
}

class _PerformanceScreenState extends State<PerformanceScreen> {
  late DateTime _period =
      widget.initialPeriod ??
      DateTime(DateTime.now().year, DateTime.now().month);
  Map<String, dynamic> _summary = {};
  Map<String, dynamic> _rules = {};
  List<Map<String, dynamic>> _events = [];
  bool _loading = true;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      final data = widget.isTeamView
          ? await widget.api.fetchTeamMemberPerformance(
              widget.memberId!,
              year: _period.year,
              month: _period.month,
            )
          : await widget.api.fetchPerformance(
              year: _period.year,
              month: _period.month,
            );
      if (!mounted) return;
      setState(() {
        _summary = (data['summary'] as Map?)?.cast<String, dynamic>() ?? {};
        _rules = (data['score_rules'] as Map?)?.cast<String, dynamic>() ?? {};
        _events = ((data['events'] as List?) ?? const [])
            .whereType<Map>()
            .map((e) => e.cast<String, dynamic>())
            .toList();
      });
    } catch (e) {
      if (mounted)
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _changeMonth(int amount) {
    setState(() => _period = DateTime(_period.year, _period.month + amount));
    _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading)
      return const FullScreenLoader(message: 'Calculating performance...');
    if (_error.isNotEmpty)
      return AppErrorState(message: _error, onRetry: _load);
    final score = _number(_summary['score']);
    final scoreColor = score >= 100
        ? AppColors.success
        : (score >= 90 ? AppColors.warning : AppColors.danger);
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          Container(
            padding: const EdgeInsets.all(AppSpacing.xl),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [
                  AppColors.card,
                  AppColors.background,
                  AppColors.paleGold,
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(AppRadius.xl),
              border: Border.all(color: AppColors.border),
              boxShadow: AppShadows.soft,
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    IconButton(
                      onPressed: () => _changeMonth(-1),
                      tooltip: 'Previous month',
                      icon: const Icon(
                        Icons.chevron_left,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    Expanded(
                      child: Text(
                        '${_monthName(_period.month)} ${_period.year}',
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: 'Next month',
                      onPressed: () => _changeMonth(1),
                      icon: const Icon(
                        Icons.chevron_right,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
                Container(
                  width: 118,
                  height: 118,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.card,
                    border: Border.all(
                      color: AppColors.brandGold.withValues(alpha: .24),
                      width: 4,
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            score.toStringAsFixed(1),
                            style: const TextStyle(
                              color: AppColors.textPrimary,
                              fontSize: 31,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const Text(
                            'SCORE',
                            style: TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 10,
                              letterSpacing: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  _scoreHeadline(score),
                  style: const TextStyle(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
                Text(
                  'Monthly score starts at ${_number(_rules['base_score']).toStringAsFixed(0)}',
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          PerformanceTargetCard(
            score: score,
            target: _number(
              _summary['monthly_target'] ?? _rules['monthly_target'] ?? 200,
            ),
            rewardPoints: _number(_summary['reward_points']),
            rewardPerPoint: _number(_rules['reward_per_extra_point'] ?? 1),
          ),
          const SizedBox(height: AppSpacing.lg),
          _metricGrid([
            _metric(
              'Points earned',
              '+${AppFormatters.points(_summary['points_earned'])}',
              AppColors.success,
              Icons.trending_up,
            ),
            _metric(
              'Points lost',
              '-${AppFormatters.points(_summary['points_lost'])}',
              AppColors.danger,
              Icons.trending_down,
            ),
            _metric(
              'On-time rate',
              '${_number(_summary['on_time_rate']).toStringAsFixed(0)}%',
              AppColors.diamond,
              Icons.timer_outlined,
            ),
            _metric(
              'Pending overdue',
              '${_integer(_summary['overdue_actions'])}',
              AppColors.warning,
              Icons.warning_amber_rounded,
            ),
          ]),
          const SizedBox(height: AppSpacing.xl),
          const Text(
            'Performance Breakdown',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: AppSpacing.md),
          _breakdownCard(),
          const SizedBox(height: AppSpacing.xl),
          const Text(
            'Point Audit',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          const Text(
            'Every earned or lost point is listed below.',
            style: TextStyle(color: AppColors.textSecondary, fontSize: 12),
          ),
          const SizedBox(height: AppSpacing.md),
          if (_events.isEmpty)
            const AppEmptyState(
              title: 'No scored activity',
              message:
                  'Completed tasks and order follow-ups for this month will appear here.',
            ),
          ..._events.map((event) => _eventCard(event, scoreColor)),
        ],
      ),
    );
  }

  /// Two tiles per row that grow with their content (large system text
  /// would overflow a fixed-ratio grid).
  Widget _metricGrid(List<Widget> tiles) => LayoutBuilder(
    builder: (context, constraints) {
      final width = (constraints.maxWidth - AppSpacing.md) / 2;
      return Wrap(
        spacing: AppSpacing.md,
        runSpacing: AppSpacing.md,
        children: [
          for (final tile in tiles) SizedBox(width: width, child: tile),
        ],
      );
    },
  );

  Widget _metric(String label, String value, Color color, IconData icon) =>
      Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(AppRadius.lg),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 21),
            const SizedBox(height: 6),
            Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 23),
            ),
            Text(
              label,
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 11,
              ),
            ),
          ],
        ),
      );

  Widget _breakdownCard() => Container(
    padding: const EdgeInsets.all(AppSpacing.lg),
    decoration: BoxDecoration(
      color: AppColors.card,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      border: Border.all(color: AppColors.border),
    ),
    child: Column(
      children: [
        _breakdownRow(
          'Tasks on time',
          _integer(_summary['task_on_time']),
          '+2 each',
          AppColors.success,
        ),
        _breakdownRow(
          'Task late day units',
          _integer(_summary['task_delay_days']),
          '−2 / day',
          AppColors.danger,
        ),
        _breakdownRow(
          'Approved follow-ups',
          _integer(_summary['approved_followups']),
          '+2 each',
          AppColors.success,
        ),
        _breakdownRow(
          'Approved without points',
          _integer(_summary['no_point_followups']),
          '0 each',
          AppColors.textSecondary,
        ),
        _breakdownRow(
          'Disapproved follow-ups',
          _integer(_summary['disapproved_followups']),
          '−2 each',
          AppColors.danger,
        ),
        _breakdownRow(
          'Follow-ups on time',
          _integer(_summary['followup_on_time']),
          'no penalty',
          AppColors.success,
        ),
        _breakdownRow(
          'Follow-ups late / overdue',
          _integer(_summary['followup_late']) +
              _integer(_summary['followup_overdue']),
          'tracked',
          AppColors.danger,
        ),
        _breakdownRow(
          'Follow-up late day units',
          _integer(_summary['followup_delay_days']),
          '−2 / day',
          AppColors.danger,
        ),
        _breakdownRow(
          'Order entries',
          _integer(_summary['order_entries']),
          '+0.25 each',
          AppColors.diamond,
        ),
        _breakdownRow(
          'Issuements',
          _integer(_summary['issuements']),
          '+0.25 each',
          AppColors.gold,
        ),
        _breakdownRow(
          'Returns',
          _integer(_summary['returns']),
          '+0.25 each',
          AppColors.diamond,
        ),
        _breakdownRow(
          'Receivings',
          _integer(_summary['receivings']),
          '+0.25 each',
          AppColors.success,
        ),
        _breakdownRow(
          'Purchases',
          _integer(_summary['purchases']),
          '+0.25 each',
          AppColors.stone,
        ),
        _breakdownRow(
          'Completion ratings',
          _integer(_summary['rating_count']),
          '${_number(_summary['rating_average']).toStringAsFixed(1)}/5 · ±${_number(_rules['rating_point_step'] ?? 5).toStringAsFixed(0)}/star',
          AppColors.gold,
          divider: false,
        ),
      ],
    ),
  );

  Widget _breakdownRow(
    String label,
    int count,
    String points,
    Color color, {
    bool divider = true,
  }) => Column(
    children: [
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
            ),
            Text('$count', style: const TextStyle(fontWeight: FontWeight.w900)),
            const SizedBox(width: 12),
            SizedBox(
              width: 55,
              child: Text(
                points,
                textAlign: TextAlign.right,
                style: TextStyle(
                  color: color,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
      if (divider) const Divider(height: 1),
    ],
  );

  Widget _eventCard(Map<String, dynamic> event, Color fallbackColor) {
    final delta = _number(event['score_delta']);
    final color = delta > 0
        ? AppColors.success
        : (delta < 0 ? AppColors.danger : fallbackColor);
    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: color.withValues(alpha: .12),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(
              event['type'] == 'Task'
                  ? Icons.task_alt
                  : Icons.event_note_outlined,
              color: color,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  (event['title'] ?? '-').toString(),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                Text(
                  'Due ${AppFormatters.dateTime(event['due_at'])} · ${(event['status'] ?? '').toString().replaceAll('_', ' ')}',
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
              ],
            ),
          ),
          Text(
            '${delta > 0 ? '+' : ''}${AppFormatters.points(delta)}',
            style: TextStyle(
              color: color,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  String _scoreHeadline(double score) {
    final target = _number(_rules['monthly_target'] ?? 200);
    if (score >= target) return 'Target reached';
    if (score >= target * 0.75) return 'On track for target';
    if (score >= 100) return 'Building towards target';
    if (score >= 90) return 'Attention needed';
    return 'Improvement required';
  }

  double _number(dynamic value) =>
      double.tryParse((value ?? 0).toString()) ?? 0;
  int _integer(dynamic value) => int.tryParse((value ?? 0).toString()) ?? 0;
  String _monthName(int month) => const [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ][month - 1];
}
