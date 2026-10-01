import 'package:flutkit/jewellery_mobile/services/followup_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_pwa/screens/issuement_create_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/order_create_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/purchase_create_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/transaction_create_screen.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/workspace_components.dart';
import 'package:flutkit/jewellery_pwa/screens/order_detail_screen.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
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
    if (_loading) return const FullScreenLoader();
    if (_error.isNotEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    final stats = _computeStats();
    final followupGroups = _followupGroups();
    final today = followupGroups['today'] ?? [];
    final other = followupGroups['other'] ?? [];

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          _welcomeCard(today.length, other),
          const SizedBox(height: 28),
          AppSectionTitle(
            'Order overview',
            trailing: WorkspaceBadge('${_orders.length} orders'),
          ),
          const SizedBox(height: AppSpacing.md),
          _statsHeader(stats),
          const SizedBox(height: 28),
          _quickActions(),
          const SizedBox(height: 28),
          LayoutBuilder(
            builder: (context, constraints) {
              final panels = [
                _followupSection(
                  'Today’s followups',
                  today,
                  'You’re all caught up for today.',
                ),
                _followupSection(
                  'Upcoming & overdue',
                  other,
                  'No other followups are scheduled.',
                ),
              ];
              if (constraints.maxWidth < 820) {
                return Column(
                  children: [
                    panels.first,
                    const SizedBox(height: 24),
                    panels.last,
                  ],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: panels.first),
                  const SizedBox(width: 20),
                  Expanded(child: panels.last),
                ],
              );
            },
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  Widget _welcomeCard(int todayCount, List<Map<String, dynamic>> other) {
    final now = DateTime.now();
    final greeting = now.hour < 12
        ? 'Good morning'
        : now.hour < 17
        ? 'Good afternoon'
        : 'Good evening';
    final overdue = other
        .where(
          (row) => _followupStatus(
            row['next_followup_date'],
          ).$1.startsWith('Overdue'),
        )
        .length;
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.xl),
        gradient: const LinearGradient(
          colors: [Color(0xFF321426), AppColors.plum, Color(0xFF623544)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      greeting.toUpperCase(),
                      style: const TextStyle(
                        color: AppColors.paleGold,
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 1.8,
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      'A little clarity.\nA finer working day.',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 26,
                        fontWeight: FontWeight.w600,
                        height: 1.2,
                        letterSpacing: -.7,
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      'Your orders, craft and followups, beautifully organised.',
                      style: TextStyle(
                        color: Color(0xFFD6C4CE),
                        fontSize: 12,
                        height: 1.6,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .08),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: AppColors.brandGold.withValues(alpha: .45),
                  ),
                ),
                child: const Icon(
                  LucideIcons.gem,
                  color: AppColors.paleGold,
                  size: 22,
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          Container(height: 1, color: Colors.white.withValues(alpha: .12)),
          const SizedBox(height: 16),
          Wrap(
            spacing: 20,
            runSpacing: 12,
            children: [
              _heroDetail(
                LucideIcons.calendar_days,
                AppFormatters.date(now.toIso8601String()),
              ),
              _heroDetail(LucideIcons.calendar_clock, '$todayCount due today'),
              if (overdue > 0)
                _heroDetail(LucideIcons.clock_alert, '$overdue overdue'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _heroDetail(IconData icon, String text) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 15, color: AppColors.paleGold),
      const SizedBox(width: 7),
      Flexible(
        child: Text(
          text,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 11,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),
    ],
  );

  Widget _statsHeader(Map<String, int> stats) => WorkspaceCardLayout(
    minimumWidth: 132,
    children: [
      _statCard(
        'Pending',
        stats['pending'] ?? 0,
        AppColors.brandRed,
        LucideIcons.clock_3,
        'Pending',
      ),
      _statCard(
        'Completed',
        stats['completed'] ?? 0,
        AppColors.success,
        LucideIcons.circle_check,
        'Completed Bucket',
      ),
      _statCard(
        'In production',
        stats['in_progress'] ?? 0,
        AppColors.brandGold,
        LucideIcons.hammer,
        'In Production',
      ),
      _statCard(
        'Confirmed',
        stats['confirmed'] ?? 0,
        AppColors.diamond,
        LucideIcons.badge_check,
        'Confirmed',
      ),
    ],
  );

  Widget _statCard(
    String title,
    int value,
    Color color,
    IconData icon,
    String status,
  ) => WorkspaceMetricCard(
    label: title,
    value: '$value',
    icon: icon,
    color: color,
    onTap: widget.onOpenOrdersByStatus == null
        ? null
        : () => widget.onOpenOrdersByStatus!(status),
  );

  Widget _followupSection(
    String title,
    List<Map<String, dynamic>> rows,
    String emptyMessage,
  ) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      AppSectionTitle(title, trailing: WorkspaceBadge('${rows.length}')),
      const SizedBox(height: 12),
      if (rows.isEmpty)
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              const WorkspaceIconTile(
                icon: LucideIcons.calendar_check,
                color: AppColors.success,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  emptyMessage,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
            ],
          ),
        )
      else
        ...rows.map(_followupCard),
    ],
  );

  Widget _followupCard(Map<String, dynamic> row) {
    final status = _followupStatus(row['next_followup_date']);
    final id = int.tryParse(row['id']?.toString() ?? '') ?? 0;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          side: const BorderSide(color: AppColors.border),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: id > 0
              ? () async {
                  await Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => OrderDetailScreen(
                        api: widget.api,
                        orderId: id,
                        initialOrder: row,
                      ),
                    ),
                  );
                  if (mounted) _load();
                }
              : null,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        (row['order_no'] ?? '-').toString(),
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Flexible(
                      child: WorkspaceBadge(status.$1, color: status.$2),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                WorkspaceMetadata(
                  icon: LucideIcons.user_round,
                  text: (row['customer_name'] ?? '-').toString(),
                ),
                const SizedBox(height: 6),
                WorkspaceMetadata(
                  icon: LucideIcons.layers,
                  text: (row['last_followup_stage'] ?? row['status'] ?? '-')
                      .toString(),
                ),
                const SizedBox(height: 14),
                const Divider(height: 1),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: WorkspaceMetadata(
                        icon: LucideIcons.calendar_days,
                        text: AppFormatters.date(row['next_followup_date']),
                      ),
                    ),
                    if (id > 0)
                      const Icon(
                        LucideIcons.arrow_up_right,
                        size: 17,
                        color: AppColors.plum,
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _quickActions() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const AppSectionTitle('Create something new'),
      const SizedBox(height: 12),
      WorkspaceCardLayout(
        minimumWidth: 132,
        maxColumns: 5,
        children: [
          _actionCard(
            'New order',
            'Start an order',
            LucideIcons.clipboard_plus,
            AppColors.plum,
            _openOrder,
          ),
          _actionCard(
            'Issuement',
            'Issue materials',
            LucideIcons.package_open,
            AppColors.plum,
            _openIssuement,
          ),
          _actionCard(
            'Diamond',
            'Record purchase',
            LucideIcons.diamond,
            AppColors.diamond,
            () => _openTransaction('diamond', 'purchase'),
          ),
          _actionCard(
            'Gold',
            'Record purchase',
            LucideIcons.gem,
            AppColors.brandGold,
            () => _openTransaction('gold', 'purchase'),
          ),
          _actionCard(
            'Stone',
            'Record purchase',
            LucideIcons.sparkles,
            AppColors.stone,
            () => _openTransaction('stone', 'purchase'),
          ),
        ],
      ),
    ],
  );

  Widget _actionCard(
    String label,
    String description,
    IconData icon,
    Color color,
    VoidCallback onTap,
  ) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.lg),
      side: const BorderSide(color: AppColors.border),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            WorkspaceIconTile(icon: icon, color: color),
            const SizedBox(height: 14),
            Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: AppColors.plum,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              description,
              style: const TextStyle(
                fontSize: 11,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    ),
  );

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
      final parsed = FollowupNotificationService.normalizedFollowupTime(
        nextFollowup,
      );
      if (parsed == null) continue;
      final dateKey = DateTime(parsed.year, parsed.month, parsed.day);
      if (dateKey == todayKey) {
        todayRows.add(row);
      } else {
        otherRows.add(row);
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
    otherRows.sort(sortByFollowup);

    return {'today': todayRows, 'other': otherRows};
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
