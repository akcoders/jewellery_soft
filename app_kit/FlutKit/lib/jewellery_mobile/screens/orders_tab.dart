import 'package:flutkit/jewellery_mobile/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutter/material.dart';

class OrdersTab extends StatefulWidget {
  const OrdersTab({super.key, required this.api});

  final MobileApiService api;

  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab> {
  static const List<String> _statuses = [
    'All',
    'Confirmed',
    'In Production',
    'QC',
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
        status: _status == 'All' ? '' : _status,
        query: _searchCtrl.text.trim(),
        page: 1,
        limit: 100,
      );
      if (!mounted) return;
      setState(() {
        _orders = (data['items'] as List?) ?? <dynamic>[];
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
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchCtrl,
                  decoration: InputDecoration(
                    hintText: 'Search order no / customer / karigar',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: IconButton(
                      onPressed: _load,
                      icon: const Icon(Icons.arrow_forward),
                    ),
                  ),
                  onSubmitted: (_) => _load(),
                ),
              ),
              const SizedBox(width: 8),
              DropdownButton<String>(
                value: _status,
                onChanged: (v) {
                  if (v == null) return;
                  setState(() => _status = v);
                  _load();
                },
                items: _statuses
                    .map((e) => DropdownMenuItem(value: e, child: Text(e)))
                    .toList(),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _load,
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error.isNotEmpty
                ? ListView(
                    children: [
                      const SizedBox(height: 120),
                      Center(child: Text(_error)),
                    ],
                  )
                : _orders.isEmpty
                ? ListView(
                    children: const [
                      SizedBox(height: 120),
                      Center(child: Text('No orders found')),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(12, 4, 12, 16),
                    itemCount: _orders.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (context, index) {
                      final row = (_orders[index] as Map)
                          .cast<String, dynamic>();
                      final status = (row['status'] ?? '').toString();
                      return Card(
                        child: ListTile(
                          onTap: () async {
                            await Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => OrderDetailScreen(
                                  api: widget.api,
                                  orderId: int.tryParse(
                                        (row['id'] ?? row['order_id'] ?? '')
                                            .toString(),
                                      ) ??
                                      0,
                                  initialOrder: row,
                                ),
                              ),
                            );
                            _load();
                          },
                          title: Text((row['order_no'] ?? '-').toString()),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const SizedBox(height: 4),
                              Text(
                                'Customer: ${(row['customer_name'] ?? '-')}',
                              ),
                              Text(
                                'Karigar: ${(row['karigar_name'] ?? 'Not Assigned')}',
                              ),
                              Text(
                                'Followup: ${(row['last_followup_stage'] ?? '-')}'
                                ' | Next: ${(row['next_followup_date'] ?? '-')}',
                              ),
                            ],
                          ),
                          trailing: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: _statusColor(
                                    context,
                                    status,
                                  ).withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(16),
                                ),
                                child: Text(
                                  status,
                                  style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                    color: _statusColor(context, status),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                (row['due_date'] ?? '-').toString(),
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ],
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
}
