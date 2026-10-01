import 'package:flutkit/jewellery_mobile/services/followup_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_status_badge.dart';

class FollowupsScreen extends StatefulWidget {
  const FollowupsScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<FollowupsScreen> createState() => _FollowupsScreenState();
}

class _FollowupsScreenState extends State<FollowupsScreen> {
  bool _loading = true;
  String _error = '';
  List<Map<String, dynamic>> _today = [];
  List<Map<String, dynamic>> _other = [];

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
      final data = await widget.api.fetchOrders(
        page: 1,
        limit: 200,
        followupsOnly: true,
      );
      final orders = (data['items'] as List?) ?? <dynamic>[];
      final groups = _groupFollowups(orders);
      await FollowupNotificationService.syncFromOrders(orders);
      if (!mounted) return;
      setState(() {
        _today = groups['today'] ?? [];
        _other = groups['other'] ?? [];
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

  Map<String, List<Map<String, dynamic>>> _groupFollowups(
    List<dynamic> orders,
  ) {
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

    for (final raw in orders) {
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

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const FullScreenLoader();
    }
    if (_error.isNotEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }

    final overdue = _other.where((row) {
      final date = FollowupNotificationService.normalizedFollowupTime(
        row['next_followup_date'],
      );
      final now = DateTime.now();
      return date != null &&
          date.isBefore(DateTime(now.year, now.month, now.day));
    }).length;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [AppColors.plum, Color(0xFF613047)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(AppRadius.xl),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  LucideIcons.calendar_check_2,
                  color: AppColors.paleGold,
                  size: 25,
                ),
                const SizedBox(height: 14),
                Text(
                  'Keep every order moving',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Your next conversations, all in one place.',
                  style: TextStyle(color: Color(0xFFE9D7DE), height: 1.5),
                ),
                const SizedBox(height: 18),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    _summary('${_today.length}', 'Today'),
                    _summary('$overdue', 'Overdue'),
                    _summary('${_other.length - overdue}', 'Upcoming'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          AppSectionTitle(
            'Today',
            trailing: Text(
              '${_today.length} follow-ups',
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12,
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          _listSection(_today),
          const SizedBox(height: AppSpacing.lg),
          const AppSectionTitle('Upcoming & overdue'),
          const SizedBox(height: AppSpacing.md),
          _listSection(_other),
        ],
      ),
    );
  }

  Widget _summary(String value, String label) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .09),
      border: Border.all(color: Colors.white.withValues(alpha: .14)),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(
      '$value  $label',
      style: const TextStyle(
        color: Colors.white,
        fontWeight: FontWeight.w600,
        fontSize: 13,
      ),
    ),
  );

  Widget _listSection(List<Map<String, dynamic>> rows) {
    if (rows.isEmpty) {
      return const AppEmptyState(
        title: 'All clear here',
        message: 'No follow-ups scheduled in this section.',
      );
    }

    return Column(
      children: rows.map((row) {
        final status = _followupStatus(row['next_followup_date']);
        return Card(
          margin: const EdgeInsets.only(bottom: 14),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () async {
              final id =
                  int.tryParse('${row['id'] ?? row['order_id'] ?? ''}') ?? 0;
              if (id <= 0) return;
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
            },
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(11),
                        decoration: BoxDecoration(
                          color: AppColors.paleGold,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(
                          LucideIcons.calendar_clock,
                          color: AppColors.plum,
                          size: 21,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${row['order_no'] ?? '-'}',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${row['customer_name'] ?? '-'}',
                              style: const TextStyle(
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Icon(
                        LucideIcons.chevron_right,
                        size: 18,
                        color: AppColors.textSecondary,
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  AppStatusBadge(label: status.$1, color: status.$2),
                  const Divider(height: 28),
                  _detail(
                    LucideIcons.clock_3,
                    AppFormatters.dateTime(row['next_followup_date']),
                  ),
                  const SizedBox(height: 10),
                  _detail(
                    LucideIcons.user_round,
                    '${row['follower_name'] ?? 'Not assigned'}',
                  ),
                  if ('${row['last_followup_stage'] ?? ''}'.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    _detail(
                      LucideIcons.list_checks,
                      '${row['last_followup_stage']}',
                    ),
                  ],
                ],
              ),
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _detail(IconData icon, String value) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 16, color: AppColors.textSecondary),
      const SizedBox(width: 9),
      Expanded(child: Text(value, style: const TextStyle(fontSize: 13))),
    ],
  );

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
}
