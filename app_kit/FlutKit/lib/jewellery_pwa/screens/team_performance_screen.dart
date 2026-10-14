import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/screens/performance_screen.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_pwa/widgets/workspace_components.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

/// Administrator view of the whole team: who is on track for the monthly
/// target, who earned reward points, and where points are won or lost.
class TeamPerformanceScreen extends StatefulWidget {
  const TeamPerformanceScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<TeamPerformanceScreen> createState() => _TeamPerformanceScreenState();
}

enum _TeamFilter { all, achieved, onTrack, attention }

class _TeamPerformanceScreenState extends State<TeamPerformanceScreen> {
  DateTime _period = DateTime(DateTime.now().year, DateTime.now().month);
  Map<String, dynamic> _totals = {};
  Map<String, dynamic> _rules = {};
  List<Map<String, dynamic>> _rows = [];
  bool _loading = true;
  String _error = '';
  String _query = '';
  _TeamFilter _filter = _TeamFilter.all;

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
      final data = await widget.api.fetchTeamPerformance(
        year: _period.year,
        month: _period.month,
      );
      if (!mounted) return;
      setState(() {
        _totals = (data['totals'] as Map?)?.cast<String, dynamic>() ?? {};
        _rules = (data['score_rules'] as Map?)?.cast<String, dynamic>() ?? {};
        _rows = ((data['rows'] as List?) ?? const [])
            .whereType<Map>()
            .map((row) => row.cast<String, dynamic>())
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

  double get _target =>
      _number(_totals['monthly_target'] ?? _rules['monthly_target'] ?? 200);

  bool _achieved(Map<String, dynamic> row) => _number(row['score']) >= _target;

  bool _onTrack(Map<String, dynamic> row) {
    final score = _number(row['score']);
    return score < _target && score >= _target * 0.75;
  }

  List<Map<String, dynamic>> _visible(List<Map<String, dynamic>> ranked) {
    final term = _query.trim().toLowerCase();
    return ranked.where((row) {
      if (term.isNotEmpty &&
          !'${row['name'] ?? ''} ${row['role_label'] ?? ''}'
              .toLowerCase()
              .contains(term)) {
        return false;
      }
      return switch (_filter) {
        _TeamFilter.all => true,
        _TeamFilter.achieved => _achieved(row),
        _TeamFilter.onTrack => _onTrack(row),
        _TeamFilter.attention => row['needs_attention'] == true,
      };
    }).toList();
  }

  void _openMember(Map<String, dynamic> row) {
    final id = int.tryParse('${row['id']}');
    if (id == null) return;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => Scaffold(
          appBar: AppBar(title: Text('${row['name'] ?? 'Staff'}')),
          body: PerformanceScreen(
            api: widget.api,
            memberId: id,
            memberName: '${row['name'] ?? 'Staff'}',
            initialPeriod: _period,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const FullScreenLoader(message: 'Calculating team performance...');
    }
    if (_error.isNotEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    final ranked = [..._rows]
      ..sort((a, b) => _number(b['score']).compareTo(_number(a['score'])));
    final podium = ranked
        .where((row) => row['has_scored_activity'] == true)
        .take(3)
        .toList();
    final visible = _visible(ranked);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          const WorkspacePageHeading(
            title: 'Team performance',
            description:
                'See who is on track for the monthly target, who earned rewards and where points are won or lost.',
            icon: LucideIcons.users_round,
          ),
          const SizedBox(height: 24),
          _monthSelector(),
          const SizedBox(height: 16),
          _heroCard(),
          const SizedBox(height: 16),
          WorkspaceCardLayout(
            minimumWidth: 150,
            children: [
              WorkspaceMetricCard(
                label: 'On-time rate',
                value:
                    '${_number(_totals['on_time_rate']).toStringAsFixed(0)}%',
                color: AppColors.diamond,
                icon: LucideIcons.timer,
              ),
              WorkspaceMetricCard(
                label: 'Overdue actions',
                value: '${_integer(_totals['overdue_actions'])}',
                color: AppColors.warning,
                icon: LucideIcons.clock_alert,
              ),
              WorkspaceMetricCard(
                label: 'Approved follow-ups',
                value: '${_integer(_totals['approved_followups'])}',
                color: AppColors.success,
                icon: LucideIcons.calendar_check,
              ),
              WorkspaceMetricCard(
                label: 'Need attention',
                value: '${_integer(_totals['action_required_count'])}',
                color: AppColors.danger,
                icon: LucideIcons.triangle_alert,
                onTap: () => setState(() => _filter = _TeamFilter.attention),
              ),
            ],
          ),
          if (podium.isNotEmpty) ...[
            const SizedBox(height: 28),
            const AppSectionTitle('Top performers'),
            const SizedBox(height: 14),
            _podium(podium),
          ],
          const SizedBox(height: 28),
          AppSectionTitle(
            'Leaderboard',
            trailing: WorkspaceBadge('${visible.length} of ${_rows.length}'),
          ),
          const SizedBox(height: 12),
          _filters(),
          const SizedBox(height: 14),
          if (_rows.isEmpty)
            const AppEmptyState(
              title: 'No staff found',
              message: 'Active non-admin staff will appear here.',
            )
          else if (visible.isEmpty)
            const AppEmptyState(
              title: 'Nobody matches',
              message: 'Try a different filter or search.',
            )
          else
            ...visible.map((row) => _memberCard(row, ranked.indexOf(row) + 1)),
          const SizedBox(height: 28),
          LayoutBuilder(
            builder: (context, constraints) {
              final earned = _breakdownCard(
                'Where points come from',
                _totals['point_earned_breakdown'],
                AppColors.success,
                LucideIcons.trending_up,
              );
              final lost = _breakdownCard(
                'Where points are lost',
                _totals['point_loss_breakdown'],
                AppColors.danger,
                LucideIcons.trending_down,
              );
              if (constraints.maxWidth < 760) {
                return Column(
                  children: [earned, const SizedBox(height: 16), lost],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: earned),
                  const SizedBox(width: 16),
                  Expanded(child: lost),
                ],
              );
            },
          ),
          const SizedBox(height: 16),
          _rulesNote(),
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

  Widget _heroStat(String label, String value, IconData icon) => Expanded(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: AppColors.paleGold),
        const SizedBox(height: 8),
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 24,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: const TextStyle(color: Color(0xFFD6C4CE), fontSize: 11),
        ),
      ],
    ),
  );

  Widget _heroCard() {
    final achievers = _integer(_totals['target_achievers']);
    final teamSize = _integer(_totals['total_staff_count']);
    final progress = (_number(_totals['average_target_progress']) / 100).clamp(
      0.0,
      1.0,
    );

    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.plum, Color(0xFF6B3A52), Color(0xFF8A5A2B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(AppRadius.xl),
        boxShadow: [
          BoxShadow(
            color: AppColors.plum.withValues(alpha: 0.14),
            blurRadius: 22,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'TEAM THIS MONTH',
            style: TextStyle(
              color: AppColors.paleGold,
              fontSize: 10,
              letterSpacing: 1.6,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            '$achievers of $teamSize reached the ${_target.toStringAsFixed(0)}-point target',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 20,
              height: 1.3,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: progress),
              duration: const Duration(milliseconds: 900),
              curve: Curves.easeOutCubic,
              builder: (context, value, _) => LinearProgressIndicator(
                value: value,
                minHeight: 10,
                backgroundColor: Colors.white.withValues(alpha: 0.15),
                valueColor: const AlwaysStoppedAnimation(Color(0xFFF3D98B)),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Active staff average ${(progress * 100).toStringAsFixed(0)}% of target',
            style: const TextStyle(color: Color(0xFFD6C4CE), fontSize: 11),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              _heroStat(
                'Average score',
                _number(_totals['average_score']).toStringAsFixed(1),
                LucideIcons.chart_no_axes_combined,
              ),
              _heroStat(
                'Reward points',
                AppFormatters.points(_totals['reward_points_total']),
                LucideIcons.gift,
              ),
              _heroStat(
                'Active staff',
                '${_integer(_totals['staff_count'])}/$teamSize',
                LucideIcons.users_round,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _podium(List<Map<String, dynamic>> podium) {
    const medals = [Color(0xFFD4AF37), Color(0xFFA8B0BA), Color(0xFFCD7F32)];
    return LayoutBuilder(
      builder: (context, constraints) {
        final cards = [
          for (var i = 0; i < podium.length; i++)
            _podiumCard(podium[i], i + 1, medals[i]),
        ];
        if (constraints.maxWidth < 560) {
          return Column(
            children: [
              for (final card in cards) ...[card, const SizedBox(height: 10)],
            ],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (var i = 0; i < cards.length; i++) ...[
              if (i > 0) const SizedBox(width: 12),
              Expanded(child: cards[i]),
            ],
          ],
        );
      },
    );
  }

  Widget _podiumCard(Map<String, dynamic> row, int rank, Color medal) {
    final reward = _number(row['reward_points']);
    return InkWell(
      onTap: () => _openMember(row),
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          border: Border.all(color: medal.withValues(alpha: 0.6), width: 1.4),
          gradient: LinearGradient(
            colors: [medal.withValues(alpha: 0.14), Colors.white],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: Row(
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                _avatar('${row['name'] ?? ''}', medal, 46),
                Positioned(
                  right: -4,
                  bottom: -4,
                  child: Container(
                    width: 22,
                    height: 22,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: medal,
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                    child: Text(
                      '$rank',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${row['name'] ?? '-'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppColors.plum,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${_number(row['score']).toStringAsFixed(1)} pts'
                    '${reward > 0 ? ' · +${AppFormatters.points(reward)} reward' : ''}',
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _chip(String label, _TeamFilter value, int count) {
    final selected = _filter == value;
    return ChoiceChip(
      label: Text('$label · $count'),
      selected: selected,
      onSelected: (_) => setState(() => _filter = value),
      selectedColor: AppColors.paleGold,
      showCheckmark: false,
      labelStyle: TextStyle(
        fontWeight: FontWeight.w700,
        fontSize: 12,
        color: selected ? AppColors.plum : AppColors.textSecondary,
      ),
      side: BorderSide(
        color: selected ? AppColors.brandGold : AppColors.border,
      ),
    );
  }

  Widget _filters() {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(AppRadius.lg),
      borderSide: const BorderSide(color: AppColors.border),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          onChanged: (value) => setState(() => _query = value),
          decoration: InputDecoration(
            hintText: 'Search staff by name or role',
            prefixIcon: const Icon(LucideIcons.search, size: 18),
            filled: true,
            fillColor: Colors.white,
            isDense: true,
            border: border,
            enabledBorder: border,
          ),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            _chip('All', _TeamFilter.all, _rows.length),
            _chip(
              'Target reached',
              _TeamFilter.achieved,
              _rows.where(_achieved).length,
            ),
            _chip(
              'On track',
              _TeamFilter.onTrack,
              _rows.where(_onTrack).length,
            ),
            _chip(
              'Needs attention',
              _TeamFilter.attention,
              _rows.where((row) => row['needs_attention'] == true).length,
            ),
          ],
        ),
      ],
    );
  }

  Widget _memberCard(Map<String, dynamic> row, int rank) {
    final score = _number(row['score']);
    final target = _target <= 0 ? 200.0 : _target;
    final progress = (score / target).clamp(0.0, 1.0);
    final achieved = score >= target;
    final reward = _number(row['reward_points']);
    final needsAttention = row['needs_attention'] == true;
    final hasActivity = row['has_scored_activity'] == true;
    final barColor = achieved
        ? AppColors.success
        : progress >= 0.75
        ? AppColors.brandGold
        : progress >= 0.5
        ? AppColors.diamond
        : AppColors.warning;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(
          color: needsAttention
              ? AppColors.danger.withValues(alpha: 0.35)
              : AppColors.border,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        onTap: () => _openMember(row),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  SizedBox(
                    width: 32,
                    child: Text(
                      '#$rank',
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.textSecondary,
                        fontSize: 13,
                      ),
                    ),
                  ),
                  _avatar('${row['name'] ?? ''}', barColor, 40),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${row['name'] ?? '-'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                            color: AppColors.plum,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${row['role_label'] ?? 'Staff'} · ${row['performance_band'] ?? '-'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  ConstrainedBox(
                    // Keeps large scores and enlarged text inside narrow phones.
                    constraints: const BoxConstraints(maxWidth: 96),
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerRight,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            score.toStringAsFixed(1),
                            style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                              color: AppColors.plum,
                              letterSpacing: -0.5,
                            ),
                          ),
                          Text(
                            '/ ${target.toStringAsFixed(0)} pts',
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(99),
                child: LinearProgressIndicator(
                  value: progress,
                  minHeight: 8,
                  backgroundColor: AppColors.border,
                  valueColor: AlwaysStoppedAnimation(barColor),
                ),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (achieved)
                    WorkspaceBadge(
                      reward > 0
                          ? '+${AppFormatters.points(reward)} reward pts'
                          : 'Target reached',
                      color: AppColors.success,
                      icon: LucideIcons.gift,
                    )
                  else
                    WorkspaceBadge(
                      '${AppFormatters.points(row['points_to_target'] ?? (target - score))} to target',
                      color: barColor,
                      icon: LucideIcons.target,
                    ),
                  WorkspaceBadge(
                    '${_number(row['on_time_rate']).toStringAsFixed(0)}% on time',
                    color: AppColors.diamond,
                    icon: LucideIcons.timer,
                  ),
                  WorkspaceBadge(
                    '${_integer(row['approved_followups'])} follow-ups',
                    color: AppColors.stone,
                    icon: LucideIcons.calendar_check,
                  ),
                  if (_integer(row['overdue_actions']) > 0)
                    WorkspaceBadge(
                      '${_integer(row['overdue_actions'])} overdue',
                      color: AppColors.warning,
                      icon: LucideIcons.clock_alert,
                    ),
                  if (_integer(row['disapproved_followups']) > 0)
                    WorkspaceBadge(
                      '${_integer(row['disapproved_followups'])} disapproved',
                      color: AppColors.danger,
                      icon: LucideIcons.circle_x,
                    ),
                  if (!hasActivity)
                    const WorkspaceBadge(
                      'No activity yet',
                      color: AppColors.textSecondary,
                    ),
                ],
              ),
              if (needsAttention &&
                  '${row['analysis_summary'] ?? ''}'.trim().isNotEmpty) ...[
                const SizedBox(height: 10),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      LucideIcons.triangle_alert,
                      size: 15,
                      color: AppColors.danger,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        '${row['analysis_summary']}',
                        style: const TextStyle(
                          fontSize: 12,
                          height: 1.45,
                          color: AppColors.danger,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _breakdownCard(String title, dynamic raw, Color color, IconData icon) {
    final entries = raw is Map
        ? raw.entries
              .map((e) => MapEntry(e.key.toString(), _number(e.value)))
              .where((e) => e.value > 0)
              .toList()
        : <MapEntry<String, double>>[];
    entries.sort((a, b) => b.value.compareTo(a.value));
    final max = entries.isEmpty ? 1.0 : entries.first.value;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              WorkspaceIconTile(icon: icon, color: color, size: 34),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.plum,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          if (entries.isEmpty)
            const Text(
              'Nothing recorded this month.',
              style: TextStyle(color: AppColors.textSecondary, fontSize: 12),
            )
          else
            for (final entry in entries.take(6))
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            entry.key,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                        Text(
                          AppFormatters.points(entry.value),
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            color: color,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(99),
                      child: LinearProgressIndicator(
                        value: (entry.value / max).clamp(0.0, 1.0),
                        minHeight: 6,
                        backgroundColor: color.withValues(alpha: 0.1),
                        valueColor: AlwaysStoppedAnimation(color),
                      ),
                    ),
                  ],
                ),
              ),
        ],
      ),
    );
  }

  Widget _rulesNote() => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.paleGold,
      borderRadius: BorderRadius.circular(AppRadius.lg),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(LucideIcons.info, size: 18, color: AppColors.plum),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            'Everyone starts at ${_number(_rules['base_score'] ?? 100).toStringAsFixed(0)} points each month. '
            'The target is ${_target.toStringAsFixed(0)} points; every point above it earns '
            '${AppFormatters.points(_rules['reward_per_extra_point'] ?? 1)} reward point. '
            'Tap a person to see every point they earned or lost.',
            style: const TextStyle(
              fontSize: 12,
              height: 1.5,
              color: AppColors.plum,
            ),
          ),
        ),
      ],
    ),
  );

  Widget _avatar(String name, Color color, double size) => Container(
    width: size,
    height: size,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.18),
      shape: BoxShape.circle,
    ),
    child: Text(
      name.trim().isEmpty ? '?' : name.trim().characters.first.toUpperCase(),
      style: TextStyle(
        fontSize: size * 0.4,
        fontWeight: FontWeight.w800,
        color: AppColors.plum,
      ),
    ),
  );

  double _number(dynamic value) =>
      double.tryParse((value ?? 0).toString()) ?? 0;
  int _integer(dynamic value) =>
      int.tryParse((value ?? 0).toString()) ??
      (double.tryParse((value ?? 0).toString()) ?? 0).round();
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
