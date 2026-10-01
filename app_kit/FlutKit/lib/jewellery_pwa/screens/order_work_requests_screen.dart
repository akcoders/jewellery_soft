import 'package:flutkit/jewellery_pwa/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_status_badge.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';

class OrderWorkRequestsScreen extends StatefulWidget {
  const OrderWorkRequestsScreen({super.key, required this.api});
  final MobileApiService api;

  @override
  State<OrderWorkRequestsScreen> createState() =>
      _OrderWorkRequestsScreenState();
}

class _OrderWorkRequestsScreenState extends State<OrderWorkRequestsScreen> {
  List<dynamic> _requests = [];
  bool _loading = true;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final rows = await widget.api.fetchOrderWorkRequests();
      if (mounted)
        setState(() {
          _requests = rows;
          _loading = false;
          _error = '';
        });
    } catch (e) {
      if (mounted)
        setState(() {
          _loading = false;
          _error = e.toString().replaceFirst('Exception: ', '');
        });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const FullScreenLoader();
    if (_error.isNotEmpty)
      return AppErrorState(message: _error, onRetry: _load);
    final pending = _requests
        .whereType<Map>()
        .where((row) => row['status'] == 'pending')
        .toList();
    final reviewed = _requests
        .whereType<Map>()
        .where((row) => row['status'] != 'pending')
        .toList();
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppColors.plum,
              borderRadius: BorderRadius.circular(AppRadius.xl),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  LucideIcons.badge_check,
                  color: AppColors.paleGold,
                  size: 27,
                ),
                const SizedBox(height: 14),
                Text(
                  '${pending.length} awaiting approval',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Review order changes and keep work moving.',
                  style: TextStyle(color: Color(0xFFE9D7DE), height: 1.5),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          const AppSectionTitle('Needs your attention'),
          const SizedBox(height: AppSpacing.md),
          if (pending.isEmpty)
            const AppEmptyState(
              title: 'No pending requests',
              message: 'New follower requests will appear here.',
            ),
          ...pending.map(_card),
          const SizedBox(height: AppSpacing.xl),
          const AppSectionTitle('Reviewed requests'),
          const SizedBox(height: AppSpacing.md),
          ...reviewed.map(_card),
        ],
      ),
    );
  }

  Widget _card(Map row) {
    final (type, icon) = switch ('${row['request_type']}') {
      'order_delay' => ('Order delay', LucideIcons.calendar_clock),
      'gold_requirement' => ('Gold requirement', LucideIcons.gem),
      'follower_change' => ('Follower change', LucideIcons.users_round),
      _ => ('${row['request_type']}', LucideIcons.clipboard_list),
    };
    final status = '${row['status']}';
    final color = switch (status) {
      'approved' || 'fulfilled' => AppColors.success,
      'rejected' => AppColors.danger,
      _ => AppColors.brandGold,
    };
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () async {
          final id = int.tryParse('${row['order_id']}') ?? 0;
          if (id <= 0) return;
          await Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => OrderDetailScreen(api: widget.api, orderId: id),
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
                    child: Icon(icon, color: AppColors.plum, size: 22),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          type,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${row['order_no'] ?? '-'}',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Icon(
                    LucideIcons.chevron_right,
                    color: AppColors.textSecondary,
                    size: 19,
                  ),
                ],
              ),
              const SizedBox(height: 14),
              AppStatusBadge(label: status.replaceAll('_', ' '), color: color),
              const SizedBox(height: 14),
              Text(
                '${row['details'] ?? ''}',
                style: const TextStyle(height: 1.6),
              ),
              const Divider(height: 28),
              Row(
                children: [
                  const Icon(
                    LucideIcons.user_round,
                    size: 16,
                    color: AppColors.textSecondary,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Requested by ${row['requester_name'] ?? 'Staff'}',
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
