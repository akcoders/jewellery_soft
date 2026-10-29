import 'package:flutkit/jewellery_mobile/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
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
  int _loadVersion = 0;

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
    _loadVersion++;
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final version = ++_loadVersion;
    final status = _status;
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      final data = await widget.api.fetchOrders(
        status:
            status == 'All' ||
                status == 'Pending' ||
                status == 'Completed Bucket'
            ? ''
            : status,
        query: _searchCtrl.text.trim(),
        page: 1,
        limit: 200,
      );
      if (!mounted || version != _loadVersion) return;
      final rows = (data['items'] as List?) ?? <dynamic>[];
      setState(() => _orders = _applyBucketFilter(rows, status));
    } catch (e) {
      if (!mounted || version != _loadVersion) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted && version == _loadVersion) {
        setState(() => _loading = false);
      }
    }
  }

  List<dynamic> _applyBucketFilter(List<dynamic> rows, String status) {
    final Set<String>? statuses = status == 'Pending'
        ? {'Confirmed', 'In Production', 'QC'}
        : status == 'Completed Bucket'
        ? {'Ready', 'Packed', 'Dispatched', 'Completed'}
        : null;
    if (statuses == null) return rows;
    return rows.where((raw) {
      return statuses.contains((raw as Map)['status']?.toString());
    }).toList();
  }

  String _statusLabel(String status) => switch (status) {
    'Pending' => 'Open orders',
    'Completed Bucket' => 'Ready & completed',
    _ => status,
  };

  Color _statusColor(String status) => switch (status) {
    'Completed' || 'Ready' || 'Packed' || 'Dispatched' => AppColors.success,
    'Cancelled' => AppColors.danger,
    'In Production' => AppColors.warning,
    'QC' => AppColors.diamond,
    _ => AppColors.emerald,
  };

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 1120),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
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
                children: [
                  for (final status in _statuses)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(_statusLabel(status)),
                        selected: _status == status,
                        onSelected: (_) {
                          if (_status == status) return;
                          setState(() => _status = status);
                          _load();
                        },
                      ),
                    ),
                ],
              ),
            ),
            if (!_loading && _error.isEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${_orders.length} recent ${_orders.length == 1 ? 'order' : 'orders'} · ${_statusLabel(_status)}',
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: 'Refresh orders',
                      onPressed: _load,
                      icon: const Icon(Icons.refresh_rounded, size: 20),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: _load,
                child: _loading
                    ? const FullScreenLoader()
                    : _error.isNotEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: [
                          AppErrorState(message: _error, onRetry: _load),
                        ],
                      )
                    : _orders.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [
                          AppEmptyState(
                            title: 'No orders found',
                            message:
                                'Try another customer name or select All orders.',
                          ),
                        ],
                      )
                    : ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                        itemCount: _orders.length,
                        separatorBuilder: (context, index) =>
                            const SizedBox(height: 12),
                        itemBuilder: (context, index) => _orderCard(
                          (_orders[index] as Map).cast<String, dynamic>(),
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _orderCard(Map<String, dynamic> row) {
    final status = (row['status'] ?? '').toString();
    final received =
        (row['receiving_status'] ?? '').toString().toLowerCase() ==
            'received' ||
        (row['received_at'] ?? '').toString().trim().isNotEmpty;
    final showReceiving =
        received ||
        {'Ready', 'Packed', 'Dispatched', 'Completed'}.contains(status);
    final name = (row['order_name'] ?? '').toString().trim();
    return Material(
      color: AppColors.card,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        side: const BorderSide(color: AppColors.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _openOrder(row),
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
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                    ),
                  ),
                  AppStatusBadge(label: status, color: _statusColor(status)),
                  if (showReceiving)
                    AppStatusBadge(
                      label: received ? 'Received' : 'Receiving pending',
                      color: received ? AppColors.success : AppColors.warning,
                    ),
                ],
              ),
              if (name.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  name,
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
              ],
              const SizedBox(height: AppSpacing.md),
              _detailLine(
                Icons.person_outline_rounded,
                'Customer',
                row['customer_name'],
                emphasized: true,
              ),
              const SizedBox(height: AppSpacing.md),
              LayoutBuilder(
                builder: (context, constraints) {
                  final stacked = constraints.maxWidth < 560;
                  final width = stacked
                      ? constraints.maxWidth
                      : (constraints.maxWidth - 16) / 2;
                  return Wrap(
                    spacing: 16,
                    runSpacing: AppSpacing.md,
                    children: [
                      SizedBox(
                        width: width,
                        child: _detailLine(
                          Icons.handyman_outlined,
                          'Karigar',
                          row['karigar_name'],
                        ),
                      ),
                      SizedBox(
                        width: width,
                        child: _detailLine(
                          Icons.support_agent_outlined,
                          'Follower',
                          row['follower_name'],
                        ),
                      ),
                    ],
                  );
                },
              ),
              const SizedBox(height: AppSpacing.lg),
              const Divider(height: 1),
              const SizedBox(height: AppSpacing.md),
              _detailLine(
                Icons.event_outlined,
                'Delivery date',
                _date(row['due_date']),
              ),
              if ((row['last_followup_stage'] ?? '')
                  .toString()
                  .trim()
                  .isNotEmpty) ...[
                const SizedBox(height: AppSpacing.sm),
                _detailLine(
                  Icons.timeline_outlined,
                  'Latest stage',
                  row['last_followup_stage'],
                ),
              ],
              if ((row['next_followup_date'] ?? '')
                  .toString()
                  .trim()
                  .isNotEmpty) ...[
                const SizedBox(height: AppSpacing.sm),
                _detailLine(
                  Icons.calendar_today_outlined,
                  'Next followup',
                  AppFormatters.dateTime(row['next_followup_date']),
                ),
              ],
              const SizedBox(height: AppSpacing.md),
              const Row(
                children: [
                  Expanded(
                    child: Text(
                      'View order & actions',
                      style: TextStyle(
                        color: AppColors.emerald,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  Icon(
                    Icons.arrow_forward_rounded,
                    color: AppColors.emerald,
                    size: 18,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _date(dynamic value) => (value ?? '').toString().trim().isEmpty
      ? 'Not scheduled'
      : AppFormatters.date(value);

  Widget _detailLine(
    IconData icon,
    String label,
    dynamic value, {
    bool emphasized = false,
  }) {
    final text = (value ?? '').toString().trim();
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: AppColors.textSecondary, size: 18),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text.rich(
            TextSpan(
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
                height: 1.5,
              ),
              children: [
                TextSpan(text: '$label: '),
                TextSpan(
                  text: text.isEmpty ? 'Not assigned' : text,
                  style: TextStyle(
                    color: AppColors.textPrimary,
                    fontWeight: emphasized ? FontWeight.w700 : FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _openOrder(Map<String, dynamic> row) async {
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
}
