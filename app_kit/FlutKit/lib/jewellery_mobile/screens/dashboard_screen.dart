import 'package:flutkit/jewellery_mobile/widgets/premium_surface.dart';
import 'package:flutkit/jewellery_mobile/services/followup_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/screens/issuement_create_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/order_create_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/purchase_create_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/transaction_create_screen.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_summary_card.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_status_badge.dart';
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

  @override
  void initState() {
    super.initState();
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

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 1120),
        child: RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              _welcomeCard(),
              const SizedBox(height: AppSpacing.xl),
              const AppSectionTitle('Order overview'),
              const SizedBox(height: AppSpacing.xs),
              Text(
                '${_orders.length} recent orders · Tap a card to view orders',
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              _statsHeader(stats),
              const SizedBox(height: AppSpacing.xl),
              _quickActions(),
              if ((followupGroups['overdue'] ?? []).isNotEmpty) ...[
                const SizedBox(height: AppSpacing.xl),
                _followupHeading(
                  'Overdue followups',
                  followupGroups['overdue']!,
                ),
                const SizedBox(height: AppSpacing.md),
                _followupList(followupGroups['overdue']!),
              ],
              const SizedBox(height: AppSpacing.xl),
              _followupHeading(
                "Today's followups",
                followupGroups['today'] ?? [],
              ),
              const SizedBox(height: AppSpacing.md),
              _followupList(followupGroups['today'] ?? []),
              const SizedBox(height: AppSpacing.lg),
              _followupHeading(
                'Upcoming followups',
                followupGroups['upcoming'] ?? [],
              ),
              const SizedBox(height: AppSpacing.md),
              _followupList(followupGroups['upcoming'] ?? []),
              const SizedBox(height: AppSpacing.xl),
            ],
          ),
        ),
      ),
    );
  }

  Widget _followupHeading(String title, List<Map<String, dynamic>> rows) {
    return AppSectionTitle(
      title,
      trailing: Text(
        '${rows.length}',
        style: const TextStyle(
          color: AppColors.textSecondary,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _welcomeCard() {
    final hour = DateTime.now().hour;
    final greeting = hour < 12
        ? 'Good morning'
        : hour < 17
        ? 'Good afternoon'
        : 'Good evening';
    final groups = _followupGroups();
    final overdue = groups['overdue']?.length ?? 0;
    final compact = MediaQuery.sizeOf(context).width < 360;
    return PremiumEntrance(
      child: PremiumSurface(
        padding: const EdgeInsets.all(24),
        borderRadius: 28,
        accent: AppColors.ice,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    greeting.toUpperCase(),
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 9,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.7,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                const FacetedDiamond(size: 42, color: AppColors.brandGold),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              compact ? 'Your workspace.' : 'Your workspace, in focus.',
              style: TextStyle(
                fontFamily: 'CormorantGaramond',
                color: AppColors.textPrimary,
                fontSize: compact ? 28 : 32,
                fontWeight: FontWeight.w600,
                height: 1.12,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              compact
                  ? 'Orders, materials and follow-ups.'
                  : 'Orders, materials and follow-ups at a glance.',
              style: TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12,
                height: 1.65,
              ),
            ),
            const SizedBox(height: 22),
            const Divider(height: 1, color: AppColors.border),
            const SizedBox(height: 15),
            Wrap(
              spacing: 18,
              runSpacing: 10,
              children: [
                _heroDetail(
                  Icons.calendar_today_outlined,
                  AppFormatters.date(DateTime.now().toIso8601String()),
                ),
                _heroDetail(
                  Icons.event_available_outlined,
                  '${groups['today']?.length ?? 0} due today',
                ),
                if (overdue > 0)
                  _heroDetail(Icons.schedule_rounded, '$overdue overdue'),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _heroDetail(IconData icon, String text) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 15, color: AppColors.brandGold),
      const SizedBox(width: 7),
      Flexible(
        child: Text(
          text,
          style: const TextStyle(
            color: AppColors.textSecondary,
            fontSize: 11,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),
    ],
  );

  Widget _statsHeader(Map<String, int> stats) {
    final cards = [
      _clickableStatCard(
        title: 'Open orders',
        value: (stats['pending'] ?? 0).toString(),
        color: AppColors.brandRed,
        icon: Icons.pending_actions,
        targetStatus: 'Pending',
      ),
      _clickableStatCard(
        title: 'Ready & completed',
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

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 720 ? 4 : 2;
        final width = (constraints.maxWidth - (columns - 1) * 12) / columns;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: cards
              .map((card) => SizedBox(width: width, child: card))
              .toList(),
        );
      },
    );
  }

  Widget _clickableStatCard({
    required String title,
    required String value,
    required Color color,
    required IconData icon,
    required String targetStatus,
  }) {
    return AppSummaryCard(
      title: title,
      value: value,
      color: color,
      icon: icon,
      onTap: widget.onOpenOrdersByStatus == null
          ? null
          : () => widget.onOpenOrdersByStatus!(targetStatus),
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
        return Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.md),
          child: Material(
            color: AppColors.card,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.lg),
              side: const BorderSide(color: AppColors.border),
            ),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () => _openFollowupOrder(row),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: AppSpacing.md,
                      runSpacing: AppSpacing.sm,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          (row['order_no'] ?? '-').toString(),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        AppStatusBadge(label: status.$1, color: status.$2),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      (row['customer_name'] ?? 'Customer not set').toString(),
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      'Stage: ${row['last_followup_stage'] ?? row['status'] ?? '-'}',
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Wrap(
                      spacing: AppSpacing.lg,
                      runSpacing: AppSpacing.sm,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          AppFormatters.dateTime(row['next_followup_date']),
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 12,
                          ),
                        ),
                        const Text(
                          'View order →',
                          style: TextStyle(
                            color: AppColors.emerald,
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      }).toList(),
    );
  }

  Future<void> _openFollowupOrder(Map<String, dynamic> row) async {
    final id =
        int.tryParse((row['id'] ?? row['order_id'] ?? '').toString()) ?? 0;
    if (id <= 0) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            OrderDetailScreen(api: widget.api, orderId: id, initialOrder: row),
      ),
    );
    if (mounted) await _load();
  }

  Widget _quickActions() {
    final actions = [
      _actionCard(
        label: 'New order',
        description: 'Customer, design & photos',
        icon: Icons.add_box_outlined,
        color: AppColors.emerald,
        onTap: _openOrder,
      ),
      _actionCard(
        label: 'New issuement',
        description: 'Issue materials to a karigar',
        icon: Icons.outbox_outlined,
        color: AppColors.emerald,
        onTap: _openIssuement,
      ),
      for (final material in ['diamond', 'gold', 'stone'])
        _actionCard(
          label:
              '${material[0].toUpperCase()}${material.substring(1)} purchase',
          description: 'Record incoming stock',
          icon: material == 'diamond'
              ? Icons.diamond_outlined
              : material == 'gold'
              ? Icons.workspace_premium_outlined
              : Icons.scatter_plot_outlined,
          color: _accentFor(material),
          onTap: () => _openTransaction(material, 'purchase'),
        ),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const AppSectionTitle('Quick actions'),
        const SizedBox(height: AppSpacing.md),
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth >= 900
                ? 3
                : constraints.maxWidth >= 560
                ? 2
                : 1;
            final width = (constraints.maxWidth - (columns - 1) * 12) / columns;
            return Wrap(
              spacing: 12,
              runSpacing: 12,
              children: actions
                  .map((action) => SizedBox(width: width, child: action))
                  .toList(),
            );
          },
        ),
      ],
    );
  }

  Future<void> _openTransaction(String material, String action) async {
    final title =
        'Create ${material[0].toUpperCase()}${material.substring(1)} ${action[0].toUpperCase()}${action.substring(1)}';
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => action == 'purchase'
            ? PurchaseCreateScreen(api: widget.api, material: material)
            : TransactionCreateScreen(
                api: widget.api,
                title: title,
                material: material,
                action: action,
                accentColor: _accentFor(material),
              ),
      ),
    );
  }

  Future<void> _openOrder() async {
    final created = await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => OrderCreateScreen(api: widget.api)),
    );
    if (created != null) await _load();
  }

  Future<void> _openIssuement() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => IssuementCreateScreen(api: widget.api)),
    );
  }

  Widget _actionCard({
    required String label,
    required String description,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Material(
      color: AppColors.card,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        side: const BorderSide(color: AppColors.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.09),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(icon, color: color, size: 22),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      description,
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              const Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textSecondary,
                size: 20,
              ),
            ],
          ),
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
    final overdueRows = <Map<String, dynamic>>[];
    final upcomingRows = <Map<String, dynamic>>[];
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
      final parsed = FollowupNotificationService.normalizedFollowupTime(
        nextFollowup,
      );
      if (parsed == null) continue;
      final dateKey = DateTime(parsed.year, parsed.month, parsed.day);
      if (dateKey == todayKey) {
        todayRows.add(row);
      } else if (dateKey.isBefore(todayKey)) {
        overdueRows.add(row);
      } else {
        upcomingRows.add(row);
      }
    }

    int sortByFollowup(Map<String, dynamic> a, Map<String, dynamic> b) {
      final ad =
          FollowupNotificationService.normalizedFollowupTime(
            a['next_followup_date'],
          ) ??
          DateTime(2100);
      final bd =
          FollowupNotificationService.normalizedFollowupTime(
            b['next_followup_date'],
          ) ??
          DateTime(2100);
      return ad.compareTo(bd);
    }

    todayRows.sort(sortByFollowup);
    overdueRows.sort(sortByFollowup);
    upcomingRows.sort(sortByFollowup);

    return {
      'today': todayRows,
      'overdue': overdueRows,
      'upcoming': upcomingRows,
    };
  }

  (String, Color) _followupStatus(dynamic nextFollowupDate) {
    final parsed = FollowupNotificationService.normalizedFollowupTime(
      nextFollowupDate,
    );
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
