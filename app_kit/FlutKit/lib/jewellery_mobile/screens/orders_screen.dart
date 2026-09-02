import 'package:flutkit/jewellery_mobile/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_search_bar.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_status_badge.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({
    super.key,
    required this.api,
    this.initialStatus = 'All',
  });

  final MobileApiService api;
  final String initialStatus;

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  static const List<String> _statuses = [
    'All',
    'Pending',
    'Confirmed',
    'In Production',
    'QC',
    'Completed Bucket',
    'Ready',
    'Packed',
    'Dispatched',
    'Completed',
    'Cancelled',
  ];

  final TextEditingController _searchCtrl = TextEditingController();
  String _status = 'All';
  bool _loading = true;
  String _error = '';
  List<dynamic> _orders = [];

  @override
  void initState() {
    super.initState();
    _status = _statuses.contains(widget.initialStatus)
        ? widget.initialStatus
        : 'All';
    _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      final data = await widget.api.fetchOrders(
        status: _apiStatusFilter(),
        query: _searchCtrl.text.trim(),
        page: 1,
        limit: 200,
      );
      if (!mounted) return;
      final rows = (data['items'] as List?) ?? <dynamic>[];
      setState(() {
        _orders = _applyBucketFilter(rows);
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

  String _apiStatusFilter() {
    if (_status == 'All' ||
        _status == 'Pending' ||
        _status == 'Completed Bucket') {
      return '';
    }
    return _status;
  }

  List<dynamic> _applyBucketFilter(List<dynamic> rows) {
    if (_status == 'Pending') {
      const pendingStatuses = {'Confirmed', 'In Production', 'QC'};
      return rows.where((raw) {
        final row = (raw as Map).cast<String, dynamic>();
        return pendingStatuses.contains((row['status'] ?? '').toString());
      }).toList();
    }

    if (_status == 'Completed Bucket') {
      const completedStatuses = {'Ready', 'Packed', 'Dispatched', 'Completed'};
      return rows.where((raw) {
        final row = (raw as Map).cast<String, dynamic>();
        return completedStatuses.contains((row['status'] ?? '').toString());
      }).toList();
    }

    return rows;
  }

  Color _statusColor(BuildContext context, String status) {
    switch (status) {
      case 'Completed':
        return Colors.green;
      case 'Cancelled':
        return Colors.red;
      case 'In Production':
        return Colors.orange;
      case 'QC':
        return Colors.purple;
      default:
        return Theme.of(context).colorScheme.primary;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: AppSearchBar(
            controller: _searchCtrl,
            hintText: 'Search orders, customer, karigar...',
            onChanged: (_) => _load(),
          ),
        ),
        SizedBox(
          height: 42,
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            scrollDirection: Axis.horizontal,
            itemCount: _statuses.length,
            separatorBuilder: (_, __) => const SizedBox(width: 8),
            itemBuilder: (context, index) {
              final status = _statuses[index];
              final selected = _status == status;
              return ChoiceChip(
                label: Text(status),
                selected: selected,
                onSelected: (_) {
                  setState(() => _status = status);
                  _load();
                },
              );
            },
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _load,
            child: _loading
                ? const FullScreenLoader()
                : _error.isNotEmpty
                ? AppErrorState(message: _error, onRetry: _load)
                : _orders.isEmpty
                ? const AppEmptyState(
                    title: 'No orders found',
                    message: 'Try changing the search or filters.',
                  )
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
                    itemCount: _orders.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final row = (_orders[index] as Map)
                          .cast<String, dynamic>();
                      final status = (row['status'] ?? '').toString();
                      final id =
                          int.tryParse(
                            (row['id'] ?? row['order_id'] ?? '').toString(),
                          ) ??
                          0;
                      return Container(
                        decoration: BoxDecoration(
                          color: AppColors.card,
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                          border: Border.all(color: AppColors.border),
                          boxShadow: AppShadows.soft,
                        ),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                          onTap: () async {
                            await Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => OrderDetailScreen(
                                  api: widget.api,
                                  orderId: id,
                                  initialOrder: row,
                                ),
                              ),
                            );
                            _load();
                          },
                          child: Padding(
                            padding: const EdgeInsets.all(AppSpacing.lg),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        (row['order_no'] ?? '-').toString(),
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                          fontSize: 16,
                                        ),
                                      ),
                                    ),
                                    AppStatusBadge(
                                      label: status,
                                      color: _statusColor(context, status),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: AppSpacing.sm),
                                Text(
                                  'Customer: ${row['customer_name'] ?? '-'}',
                                  style: const TextStyle(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                Text(
                                  'Karigar: ${row['karigar_name'] ?? 'Not Assigned'}',
                                  style: const TextStyle(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                Text(
                                  'Follower: ${row['follower_name'] ?? 'Not Assigned'}',
                                  style: const TextStyle(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                const SizedBox(height: AppSpacing.sm),
                                Wrap(
                                  spacing: AppSpacing.sm,
                                  runSpacing: AppSpacing.sm,
                                  children: [
                                    _infoChip(
                                      Icons.event_note_outlined,
                                      'Followup: ${row['last_followup_stage'] ?? '-'}',
                                    ),
                                    _infoChip(
                                      Icons.calendar_today_outlined,
                                      'Next: ${row['next_followup_date'] ?? '-'}',
                                    ),
                                    _infoChip(
                                      Icons.event_outlined,
                                      'Due: ${row['due_date'] ?? '-'}',
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ),
      ],
    );
  }

  Widget _infoChip(IconData icon, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.background,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: AppColors.textSecondary),
          const SizedBox(width: 6),
          Text(
            label,
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }
}
