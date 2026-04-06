import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

class NotificationCenterScreen extends StatefulWidget {
  const NotificationCenterScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<NotificationCenterScreen> createState() => _NotificationCenterScreenState();
}

class _NotificationCenterScreenState extends State<NotificationCenterScreen> {
  bool _loading = true;
  String _error = '';
  List<_NotificationItem> _items = [];
  final Set<int> _markingIds = <int>{};

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
      final rows = await widget.api.fetchNotifications();
      final notifications = rows
          .whereType<Map>()
          .map((row) => _NotificationItem.fromApi(row.cast<String, dynamic>()))
          .toList()
        ..sort((a, b) => a.scheduledAt.compareTo(b.scheduledAt));

      if (!mounted) {
        return;
      }
      setState(() => _items = notifications);
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _markDone(_NotificationItem item) async {
    if (_markingIds.contains(item.id)) {
      return;
    }

    setState(() => _markingIds.add(item.id));
    try {
      await widget.api.markNotificationDone(item.id);
      if (!mounted) {
        return;
      }
      setState(() => _items.removeWhere((row) => row.id == item.id));
      TaskRefreshBus.notify();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Notification marked as done.')),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    } finally {
      if (mounted) {
        setState(() => _markingIds.remove(item.id));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      body: _loading
          ? const FullScreenLoader(message: 'Loading notifications...')
          : _error.isNotEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : _items.isEmpty
          ? const AppEmptyState(
              title: 'No notifications',
              message: 'There are no pending task or followup alerts right now.',
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView.separated(
                padding: const EdgeInsets.all(AppSpacing.lg),
                itemBuilder: (context, index) {
                  final item = _items[index];
                  final marking = _markingIds.contains(item.id);
                  return Container(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    decoration: BoxDecoration(
                      color: AppColors.card,
                      borderRadius: BorderRadius.circular(AppRadius.lg),
                      border: Border.all(color: AppColors.border),
                      boxShadow: AppShadows.soft,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 46,
                              height: 46,
                              decoration: BoxDecoration(
                                color: item.color.withValues(alpha: 0.14),
                                borderRadius: BorderRadius.circular(AppRadius.md),
                              ),
                              child: Icon(item.icon, color: item.color),
                            ),
                            const SizedBox(width: AppSpacing.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    item.title,
                                    style: const TextStyle(fontWeight: FontWeight.w700),
                                  ),
                                  const SizedBox(height: AppSpacing.xs),
                                  Text(
                                    item.subtitle,
                                    style: const TextStyle(color: AppColors.textSecondary),
                                  ),
                                  const SizedBox(height: AppSpacing.xs),
                                  Text(
                                    '${item.type} | ${AppFormatters.dateTime(item.scheduledAt)}',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: item.color,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Align(
                          alignment: Alignment.centerRight,
                          child: OutlinedButton.icon(
                            onPressed: marking ? null : () => _markDone(item),
                            icon: marking
                                ? const SizedBox(
                                    width: 16,
                                    height: 16,
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  )
                                : const Icon(Icons.task_alt_outlined),
                            label: Text(marking ? 'Updating...' : 'Mark as Done'),
                          ),
                        ),
                      ],
                    ),
                  );
                },
                separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.md),
                itemCount: _items.length,
              ),
            ),
    );
  }
}

class _NotificationItem {
  const _NotificationItem({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.scheduledAt,
    required this.color,
    required this.icon,
    required this.type,
  });

  final int id;
  final String title;
  final String subtitle;
  final DateTime scheduledAt;
  final Color color;
  final IconData icon;
  final String type;

  factory _NotificationItem.fromApi(Map<String, dynamic> json) {
    final type = (json['type'] ?? 'general').toString().trim().toLowerCase();
    final scheduledAt = DateTime.tryParse((json['scheduled_at'] ?? '').toString()) ?? DateTime.now();
    final title = (json['title'] ?? 'Notification').toString();
    final subtitle = (json['message'] ?? '').toString().trim();

    return _NotificationItem(
      id: _safeInt(json['id']),
      title: title,
      subtitle: subtitle.isNotEmpty ? subtitle : 'Scheduled reminder',
      scheduledAt: scheduledAt,
      color: switch (type) {
        'task' => AppColors.brandGold,
        'followup' => AppColors.brandRed,
        _ => AppColors.brandRed,
      },
      icon: switch (type) {
        'task' => Icons.alarm_outlined,
        'followup' => Icons.notifications_active_outlined,
        _ => Icons.notifications_outlined,
      },
      type: type.isEmpty ? 'Notification' : '${type[0].toUpperCase()}${type.substring(1)}',
    );
  }

  static int _safeInt(dynamic value) {
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse((value ?? '').toString()) ?? 0;
  }
}
