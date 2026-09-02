import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

class PerformanceScreen extends StatefulWidget {
  const PerformanceScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<PerformanceScreen> createState() => _PerformanceScreenState();
}

class _PerformanceScreenState extends State<PerformanceScreen> {
  DateTime _period = DateTime(DateTime.now().year, DateTime.now().month);
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
      final data = await widget.api.fetchPerformance(
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
                  Color(0xFF28103E),
                  Color(0xFF8E173A),
                  Color(0xFFC48922),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(AppRadius.xl),
              boxShadow: AppShadows.soft,
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    IconButton(
                      onPressed: () => _changeMonth(-1),
                      icon: const Icon(Icons.chevron_left, color: Colors.white),
                    ),
                    Text(
                      '${_monthName(_period.month)} ${_period.year}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    IconButton(
                      onPressed: () => _changeMonth(1),
                      icon: const Icon(
                        Icons.chevron_right,
                        color: Colors.white,
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
                    color: Colors.white.withValues(alpha: .13),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: .45),
                      width: 4,
                    ),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        score.toStringAsFixed(1),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 31,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const Text(
                        'SCORE',
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: 10,
                          letterSpacing: 1.4,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  score >= 100
                      ? 'Strong performance'
                      : (score >= 90
                            ? 'Attention needed'
                            : 'Improvement required'),
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
                Text(
                  'Monthly score starts at ${_number(_rules['base_score']).toStringAsFixed(0)}',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: AppSpacing.md,
            mainAxisSpacing: AppSpacing.md,
            childAspectRatio: 1.55,
            children: [
              _metric(
                'Points earned',
                '+${_number(_summary['points_earned']).toStringAsFixed(0)}',
                AppColors.success,
                Icons.trending_up,
              ),
              _metric(
                'Points lost',
                '-${_number(_summary['points_lost']).toStringAsFixed(0)}',
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
            ],
          ),
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
                  'Tasks and assigned order follow-ups for this month will appear here.',
            ),
          ..._events.map((event) => _eventCard(event, scoreColor)),
        ],
      ),
    );
  }

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
          'Tasks late / overdue',
          _integer(_summary['task_late']) + _integer(_summary['task_overdue']),
          '−2 each',
          AppColors.danger,
        ),
        _breakdownRow(
          'Follow-ups on time',
          _integer(_summary['followup_on_time']),
          '+1 each',
          AppColors.success,
        ),
        _breakdownRow(
          'Follow-ups late / overdue',
          _integer(_summary['followup_late']) +
              _integer(_summary['followup_overdue']),
          '−1 each',
          AppColors.danger,
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
            '${delta > 0 ? '+' : ''}${delta.toStringAsFixed(0)}',
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
