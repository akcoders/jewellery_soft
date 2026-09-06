import 'package:flutkit/jewellery_mobile/services/local_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/onesignal_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/services/task_repository.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

class NotificationCenterScreen extends StatefulWidget {
  const NotificationCenterScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<NotificationCenterScreen> createState() =>
      _NotificationCenterScreenState();
}

class _NotificationCenterScreenState extends State<NotificationCenterScreen> {
  bool _loading = true;
  String _error = '';
  String _diagnosticError = '';
  List<_NotificationItem> _items = [];
  Map<String, dynamic> _serverStatus = {};
  PushNotificationStatus _deviceStatus = OneSignalService.status.value;
  bool _requestingPermission = false;
  final Set<int> _markingIds = <int>{};

  @override
  void initState() {
    super.initState();
    OneSignalService.status.addListener(_onDeviceStatusChanged);
    _load();
  }

  @override
  void dispose() {
    OneSignalService.status.removeListener(_onDeviceStatusChanged);
    super.dispose();
  }

  void _onDeviceStatusChanged() {
    if (!mounted) return;
    setState(() => _deviceStatus = OneSignalService.status.value);
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });

    try {
      final rows = await widget.api.fetchNotifications();
      Map<String, dynamic> serverStatus = {};
      var diagnosticError = '';
      try {
        serverStatus = await widget.api.fetchNotificationStatus();
      } catch (error) {
        diagnosticError = error.toString().replaceFirst('Exception: ', '');
      }
      final deviceStatus = await OneSignalService.refreshStatus();
      final notifications =
          rows
              .whereType<Map>()
              .map(
                (row) => _NotificationItem.fromApi(row.cast<String, dynamic>()),
              )
              .toList()
            ..sort((a, b) => a.scheduledAt.compareTo(b.scheduledAt));

      if (!mounted) {
        return;
      }
      setState(() {
        _items = notifications;
        _serverStatus = serverStatus;
        _diagnosticError = diagnosticError;
        _deviceStatus = deviceStatus;
      });
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

  Future<void> _enableNotifications() async {
    if (_requestingPermission) return;
    setState(() => _requestingPermission = true);
    final updated = await OneSignalService.requestPermission(
      fallbackToSettings: true,
    );
    if (!mounted) return;
    setState(() {
      _deviceStatus = updated;
      _requestingPermission = false;
    });
  }

  Future<void> _markDone(_NotificationItem item) async {
    if (_markingIds.contains(item.id)) {
      return;
    }

    setState(() => _markingIds.add(item.id));
    try {
      await widget.api.markNotificationDone(item.id);
      if (item.referenceTable == 'mobile_tasks' && item.referenceId > 0) {
        await LocalNotificationService.instance.cancel(
          taskReminderNotificationId(item.referenceId),
        );
      }
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
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  _notificationHealthCard(),
                  const SizedBox(height: AppSpacing.lg),
                  if (_items.isEmpty)
                    const AppEmptyState(
                      title: 'No notifications',
                      message:
                          'There are no pending task or followup alerts right now.',
                    )
                  else
                    ..._items.map(
                      (item) => Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.md),
                        child: _notificationCard(item),
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _notificationHealthCard() {
    final provider =
        (_serverStatus['provider'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
    final queue =
        (_serverStatus['queue'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
    final scheduler =
        (_serverStatus['scheduler'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
    final serverChecked = provider.isNotEmpty;
    final providerReady = _safeBool(provider['configured']);
    final schedulerReady = _safeBool(scheduler['healthy']);
    final queueFailures = _safeInt(queue['failed']);
    final healthy = _deviceStatus.isReady && providerReady && schedulerReady;
    final accent = healthy ? AppColors.success : AppColors.warning;
    final deviceError = (_deviceStatus.error ?? '').trim();
    final missingWebSetup = deviceError.toLowerCase().contains(
      'not configured for web push',
    );
    final permissionLabel = switch (_deviceStatus.permissionState) {
      'granted' => 'Allowed',
      'default' => 'Not requested',
      'denied' => 'Blocked',
      'unsupported' => 'Unsupported',
      _ => missingWebSetup ? 'Waiting for setup' : 'Unavailable',
    };

    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: accent.withValues(alpha: 0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: 0.14),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  healthy
                      ? Icons.notifications_active_outlined
                      : Icons.notifications_off_outlined,
                  color: accent,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Push Notification Health',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    Text(
                      healthy
                          ? 'Device and server are ready.'
                          : 'Action is required for reliable alerts.',
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              _statusBadge(healthy ? 'Ready' : 'Check setup', accent),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              _healthMetric(
                'Permission',
                permissionLabel,
                _deviceStatus.permissionGranted,
              ),
              _healthMetric(
                'Device',
                _deviceStatus.optedIn &&
                        (_deviceStatus.subscriptionId?.isNotEmpty ?? false)
                    ? 'Subscribed'
                    : 'Not subscribed',
                _deviceStatus.optedIn &&
                    (_deviceStatus.subscriptionId?.isNotEmpty ?? false),
              ),
              _healthMetric(
                'Server',
                serverChecked
                    ? providerReady
                          ? 'Configured'
                          : 'Not configured'
                    : 'Unavailable',
                providerReady,
              ),
              _healthMetric(
                'Scheduler',
                scheduler.isEmpty
                    ? 'Unavailable'
                    : schedulerReady
                    ? 'Running'
                    : 'Stopped',
                schedulerReady,
              ),
              if (queueFailures > 0)
                _healthMetric('Failed queue', '$queueFailures', false),
            ],
          ),
          if (_diagnosticError.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              'Server diagnostic unavailable: $_diagnosticError',
              style: const TextStyle(color: AppColors.warning, fontSize: 12),
            ),
          ],
          if (deviceError.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              missingWebSetup
                  ? 'OneSignal Web Push platform is not configured for this website. Add the Web platform for aabhushan.webignitors.in in OneSignal, then tap retry.'
                  : 'Device setup: $deviceError',
              style: const TextStyle(color: AppColors.warning, fontSize: 12),
            ),
          ],
          if (!_deviceStatus.permissionGranted || !_deviceStatus.optedIn) ...[
            const SizedBox(height: AppSpacing.md),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _requestingPermission ? null : _enableNotifications,
                icon: _requestingPermission
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.notifications_active_outlined),
                label: Text(
                  _requestingPermission
                      ? 'Checking permission...'
                      : missingWebSetup
                      ? 'Retry Web Push Setup'
                      : _deviceStatus.permissionState == 'denied'
                      ? 'Retry Notification Permission'
                      : 'Enable Notifications',
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _healthMetric(String label, String value, bool ok) {
    final color = ok ? AppColors.success : AppColors.warning;
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            ok ? Icons.check_circle_outline : Icons.error_outline,
            size: 16,
            color: color,
          ),
          const SizedBox(width: AppSpacing.xs),
          Text('$label: $value', style: const TextStyle(fontSize: 12)),
        ],
      ),
    );
  }

  Widget _statusBadge(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _notificationCard(_NotificationItem item) {
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
  }

  static bool _safeBool(dynamic value) {
    if (value is bool) return value;
    if (value is num) return value.toInt() == 1;
    return const {
      '1',
      'true',
      'yes',
    }.contains((value ?? '').toString().trim().toLowerCase());
  }

  static int _safeInt(dynamic value) {
    if (value is num) return value.toInt();
    return int.tryParse((value ?? '').toString()) ?? 0;
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
    required this.referenceTable,
    required this.referenceId,
  });

  final int id;
  final String title;
  final String subtitle;
  final DateTime scheduledAt;
  final Color color;
  final IconData icon;
  final String type;
  final String referenceTable;
  final int referenceId;

  factory _NotificationItem.fromApi(Map<String, dynamic> json) {
    final type = (json['type'] ?? 'general').toString().trim().toLowerCase();
    final scheduledAt =
        DateTime.tryParse((json['scheduled_at'] ?? '').toString()) ??
        DateTime.now();
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
      type: type.isEmpty
          ? 'Notification'
          : '${type[0].toUpperCase()}${type.substring(1)}',
      referenceTable: (json['reference_table'] ?? '').toString(),
      referenceId: _safeInt(json['reference_id']),
    );
  }

  static int _safeInt(dynamic value) {
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse((value ?? '').toString()) ?? 0;
  }
}
