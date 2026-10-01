import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/widgets/workspace_components.dart';

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
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
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
    if (_loading) {
      return const FullScreenLoader(message: 'Calculating performance...');
    }
    if (_error.isNotEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    final score = _number(_summary['score']);
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          const WorkspacePageHeading(
            title: 'Your performance',
            description: 'A clear record of your work and the points it earns.',
            icon: LucideIcons.chart_no_axes_combined,
          ),
          const SizedBox(height: 24),
          _monthSelector(),
          const SizedBox(height: 16),
          _scoreCard(score),
          const SizedBox(height: 16),
          WorkspaceCardLayout(
            minimumWidth: 132,
            children: [
              WorkspaceMetricCard(
                label: 'Points earned',
                value:
                    '+${_number(_summary['points_earned']).toStringAsFixed(0)}',
                color: AppColors.success,
                icon: LucideIcons.trending_up,
              ),
              WorkspaceMetricCard(
                label: 'Points lost',
                value:
                    '−${_number(_summary['points_lost']).toStringAsFixed(0)}',
                color: AppColors.danger,
                icon: LucideIcons.trending_down,
              ),
              WorkspaceMetricCard(
                label: 'On-time rate',
                value:
                    '${_number(_summary['on_time_rate']).toStringAsFixed(0)}%',
                color: AppColors.diamond,
                icon: LucideIcons.timer,
              ),
              WorkspaceMetricCard(
                label: 'Pending overdue',
                value: '${_integer(_summary['overdue_actions'])}',
                color: AppColors.warning,
                icon: LucideIcons.clock_alert,
              ),
            ],
          ),
          const SizedBox(height: 28),
          LayoutBuilder(
            builder: (context, constraints) {
              final breakdown = Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const AppSectionTitle('Work breakdown'),
                  const SizedBox(height: 12),
                  _breakdownCard(),
                ],
              );
              final audit = Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AppSectionTitle(
                    'Point history',
                    trailing: WorkspaceBadge('${_events.length} events'),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'The details behind every earned or lost point.',
                    style: TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (_events.isEmpty)
                    const AppEmptyState(
                      title: 'No scored activity',
                      message:
                          'Scored task activity for this month will appear here.',
                    )
                  else
                    ..._events.map(_eventCard),
                ],
              );
              if (constraints.maxWidth < 850) {
                return Column(
                  children: [breakdown, const SizedBox(height: 28), audit],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(flex: 4, child: breakdown),
                  const SizedBox(width: 24),
                  Expanded(flex: 5, child: audit),
                ],
              );
            },
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _monthSelector() => Container(
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      border: Border.all(color: AppColors.border),
    ),
    child: Row(
      children: [
        IconButton(
          tooltip: 'Previous month',
          onPressed: () => _changeMonth(-1),
          icon: const Icon(LucideIcons.chevron_left, size: 20),
        ),
        Expanded(
          child: Text(
            '${_monthName(_period.month)} ${_period.year}',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: AppColors.plum,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        IconButton(
          tooltip: 'Next month',
          onPressed: () => _changeMonth(1),
          icon: const Icon(LucideIcons.chevron_right, size: 20),
        ),
      ],
    ),
  );

  Widget _scoreCard(double score) => Container(
    padding: const EdgeInsets.all(24),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [AppColors.plum, Color(0xFF674050)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(AppRadius.xl),
    ),
    child: LayoutBuilder(
      builder: (context, constraints) {
        final scoreValue = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'MONTHLY SCORE',
              style: TextStyle(
                color: AppColors.paleGold,
                letterSpacing: 1.6,
                fontWeight: FontWeight.w600,
                fontSize: 10,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              score.toStringAsFixed(1),
              style: const TextStyle(
                color: Colors.white,
                fontSize: 52,
                height: 1.1,
                letterSpacing: -2,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        );
        final description = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(
                  LucideIcons.award,
                  color: AppColors.paleGold,
                  size: 22,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    score >= 100
                        ? 'Strong performance'
                        : score >= 90
                        ? 'Attention needed'
                        : 'Improvement required',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              'Starts at ${_number(_rules['base_score']).toStringAsFixed(0)} points each month.',
              style: const TextStyle(color: Color(0xFFD6C4CE), fontSize: 12),
            ),
            const SizedBox(height: 6),
            const Text(
              'Points come from task completion and timing.',
              style: TextStyle(
                color: Color(0xFFD6C4CE),
                fontSize: 12,
                height: 1.5,
              ),
            ),
          ],
        );
        if (constraints.maxWidth < 520) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [scoreValue, const SizedBox(height: 24), description],
          );
        }
        return Row(
          children: [
            Expanded(flex: 3, child: scoreValue),
            const SizedBox(width: 24),
            Expanded(flex: 5, child: description),
          ],
        );
      },
    ),
  );

  Widget _breakdownCard() => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      border: Border.all(color: AppColors.border),
    ),
    child: Column(
      children: [
        _breakdownRow(
          'Tasks on time',
          _integer(_summary['task_on_time']),
          _ruleLabel('task_on_time'),
          AppColors.success,
          LucideIcons.circle_check,
        ),
        _breakdownRow(
          'Tasks completed late',
          _integer(_summary['task_late']),
          _ruleLabel('task_late_or_overdue'),
          AppColors.danger,
          LucideIcons.clock_3,
        ),
        _breakdownRow(
          'Followups on time',
          _integer(_summary['followup_on_time']),
          'Task scored',
          AppColors.success,
          LucideIcons.calendar_check,
        ),
        _breakdownRow(
          'Followups late / overdue',
          _integer(_summary['followup_late']) +
              _integer(_summary['followup_overdue']),
          'Task scored',
          AppColors.danger,
          LucideIcons.calendar_clock,
          divider: false,
        ),
      ],
    ),
  );

  String _ruleLabel(String key) {
    if (!_rules.containsKey(key)) return 'Task scored';
    final points = _number(_rules[key]);
    return '${points > 0 ? '+' : ''}${points.toStringAsFixed(0)} each';
  }

  Widget _breakdownRow(
    String label,
    int count,
    String points,
    Color color,
    IconData icon, {
    bool divider = true,
  }) => Column(
    children: [
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(
          children: [
            WorkspaceIconTile(icon: icon, color: color, size: 34),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    points,
                    style: TextStyle(
                      color: color,
                      fontSize: 11,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Text(
              '$count',
              style: const TextStyle(
                fontSize: 22,
                color: AppColors.plum,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
      if (divider) const Divider(height: 1),
    ],
  );

  Widget _eventCard(Map<String, dynamic> event) {
    final delta = _number(event['score_delta']);
    final color = delta > 0
        ? AppColors.success
        : delta < 0
        ? AppColors.danger
        : AppColors.textSecondary;
    final status = (event['status'] ?? '').toString().replaceAll('_', ' ');
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              WorkspaceIconTile(
                icon: event['type'] == 'Task'
                    ? LucideIcons.list_checks
                    : LucideIcons.calendar_check,
                color: color,
                size: 36,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  (event['title'] ?? '-').toString(),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              WorkspaceBadge(
                '${delta > 0 ? '+' : ''}${delta.toStringAsFixed(0)} pts',
                color: color,
              ),
            ],
          ),
          const SizedBox(height: 14),
          WorkspaceMetadata(
            icon: LucideIcons.calendar_days,
            text: 'Due ${AppFormatters.dateTime(event['due_at'])}',
          ),
          if (status.isNotEmpty) ...[
            const SizedBox(height: 7),
            WorkspaceMetadata(
              icon: LucideIcons.circle_check,
              text: status,
              color: color,
            ),
          ],
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
