import 'package:flutkit/jewellery_mobile/services/followup_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_repository.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/screens/task_form_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/transaction_create_screen.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_summary_card.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    super.key,
    required this.api,
    this.onOpenOrdersByStatus,
  });

  final MobileApiService api;
  final void Function(String status)? onOpenOrdersByStatus;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  bool _loading = true;
  String _error = '';
  List<dynamic> _orders = [];
  late final TaskRepository _taskRepo;

  @override
  void initState() {
    super.initState();
    _taskRepo = TaskRepository(api: widget.api);
    TaskRefreshBus.tick.addListener(_load);
    _load();
  }

  @override
  void dispose() {
    TaskRefreshBus.tick.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      final data = await widget.api.fetchOrders(page: 1, limit: 200);
      final orders = (data['items'] as List?) ?? <dynamic>[];
      await FollowupNotificationService.syncFromOrders(orders);
      if (!mounted) return;
      setState(() {
        _orders = orders;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const FullScreenLoader();
    }
    if (_error.isNotEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }

    final stats = _computeStats();
    final followupGroups = _followupGroups();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          _statsHeader(stats),
          const SizedBox(height: AppSpacing.lg),
          _quickActions(),
          const SizedBox(height: AppSpacing.lg),
          const AppSectionTitle('Today Followups'),
          const SizedBox(height: AppSpacing.md),
          _followupList(followupGroups['today'] ?? []),
          const SizedBox(height: AppSpacing.lg),
          const AppSectionTitle('Upcoming / Overdue Followups'),
          const SizedBox(height: AppSpacing.md),
          _followupList(followupGroups['other'] ?? []),
        ],
      ),
    );
  }

  Widget _statsHeader(Map<String, int> stats) {
    final cards = [
      _clickableStatCard(
        title: 'Pending',
        value: (stats['pending'] ?? 0).toString(),
        color: AppColors.brandRed,
        icon: Icons.pending_actions,
        targetStatus: 'Pending',
      ),
      _clickableStatCard(
        title: 'Completed',
        value: (stats['completed'] ?? 0).toString(),
        color: AppColors.success,
        icon: Icons.check_circle_outline,
        targetStatus: 'Completed Bucket',
      ),
      _clickableStatCard(
        title: 'In Progress',
        value: (stats['in_progress'] ?? 0).toString(),
        color: AppColors.warning,
        icon: Icons.construction_outlined,
        targetStatus: 'In Production',
      ),
      _clickableStatCard(
        title: 'Confirmed',
        value: (stats['confirmed'] ?? 0).toString(),
        color: AppColors.diamond,
        icon: Icons.verified_outlined,
        targetStatus: 'Confirmed',
      ),
    ];

    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      childAspectRatio: 1.25,
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      children: cards,
    );
  }

  Widget _clickableStatCard({
    required String title,
    required String value,
    required Color color,
    required IconData icon,
    required String targetStatus,
  }) {
    return InkWell(
      onTap: widget.onOpenOrdersByStatus == null
          ? null
          : () => widget.onOpenOrdersByStatus!(targetStatus),
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: AppSummaryCard(
        title: title,
        value: value,
        color: color,
        icon: icon,
        subtitle: widget.onOpenOrdersByStatus == null ? null : 'Tap to open',
      ),
    );
  }

  Widget _followupList(List<Map<String, dynamic>> rows) {
    if (rows.isEmpty) {
      return const AppEmptyState(
        title: 'No followups',
        message: 'No followups scheduled for this section.',
      );
    }

    return Column(
      children: rows.map((row) {
        final status = _followupStatus(row['next_followup_date']);
        return Container(
          margin: const EdgeInsets.only(bottom: AppSpacing.md),
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(color: AppColors.border),
            boxShadow: AppShadows.soft,
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: status.$2.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(Icons.event_available_outlined, color: status.$2),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      (row['order_no'] ?? '-').toString(),
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      'Customer: ${row['customer_name'] ?? '-'}',
                      style: const TextStyle(color: AppColors.textSecondary),
                    ),
                    Text(
                      'Stage: ${row['last_followup_stage'] ?? row['status'] ?? '-'}',
                      style: const TextStyle(color: AppColors.textSecondary),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    status.$1,
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: status.$2,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    AppFormatters.date(row['next_followup_date']),
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      }).toList(),
    );
  }

  Widget _quickActions() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const AppSectionTitle('Quick Actions'),
        const SizedBox(height: AppSpacing.md),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            _actionChip(
              label: 'Add Order',
              icon: Icons.add_box_outlined,
              color: AppColors.brandRed,
              onTap: () => _showInfo('Order creation is not available yet.'),
            ),
            _actionChip(
              label: 'Add Task',
              icon: Icons.task_alt,
              color: AppColors.brandGold,
              onTap: _addQuickTask,
            ),
            _actionChip(
              label: 'Diamond Purchase',
              icon: Icons.diamond_outlined,
              color: AppColors.diamond,
              onTap: () => _openTransaction('diamond', 'purchase'),
            ),
            _actionChip(
              label: 'Gold Purchase',
              icon: Icons.workspace_premium_outlined,
              color: AppColors.gold,
              onTap: () => _openTransaction('gold', 'purchase'),
            ),
            _actionChip(
              label: 'Stone Purchase',
              icon: Icons.scatter_plot_outlined,
              color: AppColors.stone,
              onTap: () => _openTransaction('stone', 'purchase'),
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _openTransaction(String material, String action) async {
    final title = 'Create ${material[0].toUpperCase()}${material.substring(1)} ${action[0].toUpperCase()}${action.substring(1)}';
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TransactionCreateScreen(
          api: widget.api,
          title: title,
          material: material,
          action: action,
          accentColor: _accentFor(material),
        ),
      ),
    );
  }

  void _showInfo(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  Future<void> _addQuickTask() async {
    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(builder: (_) => const TaskFormScreen()),
    );
    if (result == null) return;

    try {
      await _taskRepo.create(
        title: (result['title'] ?? '').toString(),
        note: (result['note'] ?? '').toString(),
        scheduledAt: result['scheduledAt'] as DateTime,
      );
      await _load();
      TaskRefreshBus.notify();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Task scheduled')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  Widget _actionChip({
    required String label,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.sm,
        ),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(AppRadius.md),
          border: Border.all(color: color.withValues(alpha: 0.3)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 18),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(fontWeight: FontWeight.w600, color: color),
            ),
          ],
        ),
      ),
    );
  }

  Map<String, int> _computeStats() {
    const pendingStatuses = {'Confirmed', 'In Production', 'QC'};
    const completedStatuses = {'Ready', 'Packed', 'Dispatched', 'Completed'};

    int pending = 0;
    int completed = 0;
    int inProgress = 0;
    int confirmed = 0;

    for (final raw in _orders) {
      final row = (raw as Map).cast<String, dynamic>();
      final status = (row['status'] ?? '').toString();
      if (pendingStatuses.contains(status)) pending++;
      if (completedStatuses.contains(status)) completed++;
      if (status == 'In Production') inProgress++;
      if (status == 'Confirmed') confirmed++;
    }

    return {
      'pending': pending,
      'completed': completed,
      'in_progress': inProgress,
      'confirmed': confirmed,
    };
  }

  Map<String, List<Map<String, dynamic>>> _followupGroups() {
    final today = DateTime.now();
    final todayKey = DateTime(today.year, today.month, today.day);
    final todayRows = <Map<String, dynamic>>[];
    final otherRows = <Map<String, dynamic>>[];
    const blockedStatuses = {
      'Completed',
      'Complete',
      'Ready',
      'Packed',
      'Delivered',
      'Dispatched',
      'Cancelled',
    };

    for (final raw in _orders) {
      final row = (raw as Map).cast<String, dynamic>();
      final status = (row['status'] ?? '').toString();
      if (blockedStatuses.contains(status)) continue;
      final nextFollowup = (row['next_followup_date'] ?? '').toString();
      if (nextFollowup.isEmpty) continue;
      final parsed = FollowupNotificationService.normalizedFollowupTime(nextFollowup);
      if (parsed == null) continue;
      final dateKey = DateTime(parsed.year, parsed.month, parsed.day);
      if (dateKey == todayKey) {
        todayRows.add(row);
      } else {
        otherRows.add(row);
      }
    }

    int sortByFollowup(Map<String, dynamic> a, Map<String, dynamic> b) {
      final ad = FollowupNotificationService.normalizedFollowupTime(a['next_followup_date']) ?? DateTime(2100);
      final bd = FollowupNotificationService.normalizedFollowupTime(b['next_followup_date']) ?? DateTime(2100);
      return ad.compareTo(bd);
    }
    todayRows.sort(sortByFollowup);
    otherRows.sort(sortByFollowup);

    return {'today': todayRows, 'other': otherRows};
  }

  (String, Color) _followupStatus(dynamic nextFollowupDate) {
    final parsed = FollowupNotificationService.normalizedFollowupTime(nextFollowupDate);
    if (parsed == null) {
      return ('No date', AppColors.textSecondary);
    }

    final now = DateTime.now();
    final nextDate = DateTime(parsed.year, parsed.month, parsed.day);
    final today = DateTime(now.year, now.month, now.day);
    final days = nextDate.difference(today).inDays;
    if (days < 0) {
      return ('Overdue ${days.abs()}d', AppColors.danger);
    }
    if (days == 0) {
      return ('Today', AppColors.brandRed);
    }
    return ('In $days days', AppColors.success);
  }

  Color _accentFor(String material) {
    switch (material) {
      case 'diamond':
        return AppColors.diamond;
      case 'gold':
        return AppColors.gold;
      case 'stone':
        return AppColors.stone;
      default:
        return AppColors.brandRed;
    }
  }
}
