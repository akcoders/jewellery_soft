import 'package:flutkit/jewellery_mobile/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

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
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          Text(
            'Awaiting approval (${pending.length})',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: AppSpacing.md),
          if (pending.isEmpty)
            const AppEmptyState(
              title: 'No pending requests',
              message: 'New follower requests will appear here.',
            ),
          ...pending.map(_card),
          const SizedBox(height: AppSpacing.xl),
          Text('Reviewed', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: AppSpacing.md),
          ...reviewed.map(_card),
        ],
      ),
    );
  }

  Widget _card(Map row) {
    final type = switch ('${row['request_type']}') {
      'order_delay' => 'Order delay',
      'gold_requirement' => 'Gold requirement',
      'diamond_requirement' => 'Diamond requirement',
      'follower_change' => 'Follower change',
      _ => '${row['request_type']}',
    };
    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      child: ListTile(
        contentPadding: const EdgeInsets.all(AppSpacing.lg),
        title: Text(
          '${row['order_no']} · $type',
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: AppSpacing.sm),
          child: Text(
            '${row['details']}\nBy ${row['requester_name'] ?? 'Staff'} · ${row['status']}',
          ),
        ),
        isThreeLine: true,
        trailing: const Icon(Icons.chevron_right),
        onTap: () async {
          final id = int.tryParse('${row['order_id']}') ?? 0;
          if (id <= 0) return;
          await Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => OrderDetailScreen(api: widget.api, orderId: id),
            ),
          );
          _load();
        },
      ),
    );
  }
}
