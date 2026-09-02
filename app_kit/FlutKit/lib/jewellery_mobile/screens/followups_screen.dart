import 'package:flutkit/jewellery_mobile/services/followup_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

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
      final data = await widget.api.fetchOrders(page: 1, limit: 200);
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

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          const AppSectionTitle('Today Followups'),
          const SizedBox(height: AppSpacing.md),
          _listSection(_today),
          const SizedBox(height: AppSpacing.lg),
          const AppSectionTitle('Upcoming / Overdue'),
          const SizedBox(height: AppSpacing.md),
          _listSection(_other),
        ],
      ),
    );
  }

  Widget _listSection(List<Map<String, dynamic>> rows) {
    if (rows.isEmpty) {
      return const AppEmptyState(
        title: 'No followups',
        message: 'Nothing scheduled for this section.',
      );
    }

    return Column(
      children: rows.map((row) {
        final status = _followupStatus(row['next_followup_date']);
        return Container(
          margin: const EdgeInsets.only(bottom: AppSpacing.md),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(color: AppColors.border),
            boxShadow: AppShadows.soft,
          ),
          child: ListTile(
            onTap: () {
              final id =
                  int.tryParse(
                    (row['id'] ?? row['order_id'] ?? '').toString(),
                  ) ??
                  0;
              if (id <= 0) return;
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => OrderDetailScreen(
                    api: widget.api,
                    orderId: id,
                    initialOrder: row,
                  ),
                ),
              );
            },
            leading: const Icon(
              Icons.event_note_outlined,
              color: AppColors.brandRed,
            ),
            title: Text(
              (row['order_no'] ?? '-').toString(),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            subtitle: Text(
              'Customer: ${row['customer_name'] ?? '-'}\nFollower: ${row['follower_name'] ?? 'Not Assigned'} · Stage: ${row['last_followup_stage'] ?? '-'}',
            ),
            trailing: Text(
              status.$1,
              style: TextStyle(fontWeight: FontWeight.w700, color: status.$2),
            ),
          ),
        );
      }).toList(),
    );
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
}
