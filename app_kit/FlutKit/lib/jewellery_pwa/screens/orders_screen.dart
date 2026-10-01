import 'package:flutkit/jewellery_pwa/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_search_bar.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_status_badge.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';

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

  Color _statusColor(String status) => switch (status) {
    'Completed' || 'Ready' => AppColors.success,
    'Cancelled' => AppColors.danger,
    'In Production' => AppColors.warning,
    'QC' => AppColors.brandGold,
    _ => AppColors.plum,
  };

  Future<void> _openOrder(Map<String, dynamic> row) async {
    final id = int.tryParse('${row['id'] ?? row['order_id'] ?? ''}') ?? 0;
    if (id <= 0) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            OrderDetailScreen(api: widget.api, orderId: id, initialOrder: row),
      ),
    );
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
          child: AppSearchBar(
            controller: _searchCtrl,
            hintText: 'Search order, customer or karigar',
            onChanged: (_) => _load(),
          ),
        ),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            children: _statuses
                .map(
                  (status) => Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(status),
                      selected: _status == status,
                      onSelected: (_) {
                        setState(() => _status = status);
                        _load();
                      },
                    ),
                  ),
                )
                .toList(),
          ),
        ),
        if (!_loading && _error.isEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 4),
            child: Row(
              children: [
                const Icon(
                  LucideIcons.sliders_horizontal,
                  size: 15,
                  color: AppColors.textSecondary,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _status == 'All' ? 'All orders' : _status,
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
                Text(
                  '${_orders.length} results',
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _load,
            child: _loading
                ? const FullScreenLoader(message: 'Loading your orders...')
                : _error.isNotEmpty
                ? AppErrorState(message: _error, onRetry: _load)
                : _orders.isEmpty
                ? const AppEmptyState(
                    title: 'No orders found',
                    message: 'Try changing the search or filters.',
                  )
                : ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                    itemCount: _orders.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 14),
                    itemBuilder: (_, index) => _orderCard(
                      (_orders[index] as Map).cast<String, dynamic>(),
                    ),
                  ),
          ),
        ),
      ],
    );
  }

  Widget _orderCard(Map<String, dynamic> row) {
    final status = (row['status'] ?? '').toString();
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _openOrder(row),
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
                      LucideIcons.gem,
                      color: AppColors.plum,
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          (row['order_no'] ?? '-').toString(),
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          (row['customer_name'] ?? '-').toString(),
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Icon(
                    LucideIcons.chevron_right,
                    color: AppColors.textSecondary,
                    size: 19,
                  ),
                ],
              ),
              const SizedBox(height: 14),
              AppStatusBadge(label: status, color: _statusColor(status)),
              const Divider(height: 28),
              LayoutBuilder(
                builder: (context, constraints) {
                  final compact = constraints.maxWidth < 340;
                  final people = [
                    _assignment(
                      LucideIcons.hammer,
                      'Karigar',
                      row['karigar_name'],
                    ),
                    _assignment(
                      LucideIcons.user_round,
                      'Follower',
                      row['follower_name'],
                    ),
                  ];
                  return compact
                      ? Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            people[0],
                            const SizedBox(height: 12),
                            people[1],
                          ],
                        )
                      : Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(child: people[0]),
                            const SizedBox(width: 16),
                            Expanded(child: people[1]),
                          ],
                        );
                },
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _infoChip(
                    LucideIcons.calendar_days,
                    'Due ${AppFormatters.date(row['due_date'])}',
                  ),
                  if ('${row['last_followup_stage'] ?? ''}'.isNotEmpty)
                    _infoChip(
                      LucideIcons.list_checks,
                      '${row['last_followup_stage']}',
                    ),
                  if ('${row['next_followup_date'] ?? ''}'.isNotEmpty)
                    _infoChip(
                      LucideIcons.clock_3,
                      'Next ${AppFormatters.date(row['next_followup_date'])}',
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _infoChip(IconData icon, String label) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
    decoration: BoxDecoration(
      color: AppColors.background,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: AppColors.textSecondary),
        const SizedBox(width: 6),
        Flexible(
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
            ),
          ),
        ),
      ],
    ),
  );

  Widget _assignment(IconData icon, String role, dynamic name) {
    final value = (name ?? '').toString().trim();
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: AppColors.brandGold),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                role,
                style: const TextStyle(
                  fontSize: 11,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                value.isEmpty ? 'Unassigned' : value,
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
