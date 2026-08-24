import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_status_badge.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_mobile/screens/order_followup_form_screen.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({
    super.key,
    required this.api,
    required this.orderId,
    this.initialOrder,
  });

  final MobileApiService api;
  final int orderId;
  final Map<String, dynamic>? initialOrder;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  bool _loading = true;
  String _error = '';
  Map<String, dynamic> _order = {};
  List<dynamic> _items = [];
  List<dynamic> _followups = [];
  List<String> _allowedStages = const [];

  @override
  void initState() {
    super.initState();
    if (widget.initialOrder != null && widget.initialOrder!.isNotEmpty) {
      _order = Map<String, dynamic>.from(widget.initialOrder!);
    }
    _load();
  }

  @override
  void didUpdateWidget(covariant OrderDetailScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.orderId != widget.orderId) {
      _load();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    if (widget.orderId <= 0) {
      setState(() {
        _loading = false;
        _error = 'Invalid order id.';
      });
      return;
    }
    try {
      final data = await widget.api.fetchOrderDetail(widget.orderId);
      if (!mounted) return;
      final orderMap = (data['order'] as Map?)?.cast<String, dynamic>();
      setState(() {
        _order = orderMap ?? data.cast<String, dynamic>();
        _items = (data['items'] as List?) ?? <dynamic>[];
        _followups = (data['followups'] as List?) ?? <dynamic>[];
        _allowedStages = ((data['allowed_stages'] as List?) ?? <dynamic>[])
            .map((e) => e.toString())
            .toList(growable: false);
      });
      if (_order.isEmpty) {
        setState(() => _error = 'Order data not found.');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _takeFollowup() async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => OrderFollowupFormScreen(
          api: widget.api,
          orderId: widget.orderId,
          stages: _allowedStages,
        ),
      ),
    );

    if (result == true) {
      _load();
    }
  }

  Future<void> _openUrl(String url) async {
    if (url.trim().isEmpty) return;
    final ok = await launchUrl(
      Uri.parse(url),
      mode: LaunchMode.inAppBrowserView,
      browserConfiguration: const BrowserConfiguration(showTitle: true),
    );
    if (!ok && mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Unable to open document.')));
    }
  }

  bool _canTakeFollowup(String status) {
    return !{'Cancelled', 'Completed'}.contains(status);
  }

  String _primaryImageUrl() {
    return (_order['primary_image_url'] ??
            _order['finish_photo_url'] ??
            _order['order_photo_url'] ??
            '')
        .toString();
  }

  bool _hasReadyDocuments() {
    final packingUrl = (_order['packing_list_url'] ?? '').toString();
    final challanUrl = (_order['delivery_challan_url'] ?? '').toString();
    return packingUrl.isNotEmpty || challanUrl.isNotEmpty;
  }

  @override
  Widget build(BuildContext context) {
    final title = (_order['order_no'] ?? 'Order Detail').toString();
    final status = (_order['status'] ?? '-').toString();
    final canTakeFollowup = _canTakeFollowup(status);
    final imageUrl = _primaryImageUrl();
    final packingListUrl = (_order['packing_list_url'] ?? '').toString();
    final deliveryChallanUrl = (_order['delivery_challan_url'] ?? '')
        .toString();
    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: _loading
          ? null
          : !canTakeFollowup
          ? null
          : FloatingActionButton.extended(
              onPressed: _takeFollowup,
              icon: const Icon(Icons.add_task),
              label: const Text('Take Followup'),
            ),
      body: _loading && _order.isEmpty
          ? const FullScreenLoader()
          : _error.isNotEmpty && _order.isEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : _order.isEmpty
          ? const AppEmptyState(
              title: 'Order not found',
              message: 'Please go back and try again.',
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  if (_loading)
                    const LinearProgressIndicator(
                      minHeight: 2,
                      color: AppColors.brandRed,
                      backgroundColor: AppColors.border,
                    ),
                  if (_loading) const SizedBox(height: AppSpacing.lg),
                  if (_error.isNotEmpty) ...[
                    Container(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      decoration: BoxDecoration(
                        color: AppColors.danger.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        border: Border.all(
                          color: AppColors.danger.withValues(alpha: 0.3),
                        ),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.error_outline,
                            color: AppColors.danger,
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Text(
                              _error,
                              style: const TextStyle(
                                color: AppColors.danger,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.lg),
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
                          children: [
                            Expanded(
                              child: Text(
                                (_order['order_no'] ?? '-').toString(),
                                style: const TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            AppStatusBadge(
                              label: status,
                              color: _statusColor(status),
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Wrap(
                          spacing: AppSpacing.md,
                          runSpacing: AppSpacing.sm,
                          children: [
                            _metaChip('Type', _order['order_type'] ?? '-'),
                            _metaChip('Priority', _order['priority'] ?? '-'),
                            _metaChip('Due', _order['due_date'] ?? '-'),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Row(
                    children: [
                      Expanded(
                        child: _infoCard(
                          title: 'Customer',
                          value: _order['customer_name'] ?? '-',
                          subtitle: _order['customer_phone'] ?? '',
                          icon: Icons.person_outline,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: _infoCard(
                          title: 'Karigar',
                          value: _order['karigar_name'] ?? 'Not Assigned',
                          subtitle: _order['karigar_phone'] ?? '',
                          icon: Icons.badge_outlined,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  if (imageUrl.isNotEmpty) ...[
                    const AppSectionTitle('Order Image'),
                    const SizedBox(height: AppSpacing.md),
                    GestureDetector(
                      onTap: () {
                        showDialog(
                          context: context,
                          builder: (_) => Dialog(
                            child: InteractiveViewer(
                              child: Image.network(
                                imageUrl,
                                fit: BoxFit.contain,
                                errorBuilder: (_, __, ___) => const Padding(
                                  padding: EdgeInsets.all(AppSpacing.xl),
                                  child: Text('Unable to load image.'),
                                ),
                              ),
                            ),
                          ),
                        );
                      },
                      child: Container(
                        decoration: BoxDecoration(
                          color: AppColors.card,
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                          border: Border.all(color: AppColors.border),
                          boxShadow: AppShadows.soft,
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: AspectRatio(
                          aspectRatio: 4 / 3,
                          child: Image.network(
                            imageUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => const Center(
                              child: Text('Unable to load image.'),
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  if ((_order['order_notes'] ?? '').toString().isNotEmpty) ...[
                    const AppSectionTitle('Notes'),
                    const SizedBox(height: AppSpacing.md),
                    Container(
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      decoration: BoxDecoration(
                        color: AppColors.card,
                        borderRadius: BorderRadius.circular(AppRadius.lg),
                        border: Border.all(color: AppColors.border),
                        boxShadow: AppShadows.soft,
                      ),
                      child: Text((_order['order_notes'] ?? '').toString()),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  if (_hasReadyDocuments()) ...[
                    const AppSectionTitle('Ready Documents'),
                    const SizedBox(height: AppSpacing.md),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(AppSpacing.lg),
                        child: Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            if (packingListUrl.isNotEmpty)
                              FilledButton.icon(
                                onPressed: () => _openUrl(packingListUrl),
                                icon: const Icon(Icons.inventory_2_outlined),
                                label: const Text('Download Packing List'),
                              ),
                            if (deliveryChallanUrl.isNotEmpty)
                              FilledButton.icon(
                                onPressed: () => _openUrl(deliveryChallanUrl),
                                icon: const Icon(Icons.local_shipping_outlined),
                                label: const Text('Download Delivery Challan'),
                              ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  const AppSectionTitle('Order Items'),
                  const SizedBox(height: AppSpacing.md),
                  if (_items.isEmpty)
                    const AppEmptyState(
                      title: 'No items',
                      message: 'No order items added yet.',
                    )
                  else
                    ..._items.map((itemRaw) {
                      final item = (itemRaw as Map).cast<String, dynamic>();
                      return Container(
                        margin: const EdgeInsets.only(bottom: AppSpacing.md),
                        padding: const EdgeInsets.all(AppSpacing.lg),
                        decoration: BoxDecoration(
                          color: AppColors.card,
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                          border: Border.all(color: AppColors.border),
                          boxShadow: AppShadows.soft,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${item['design_name'] ?? '-'} (${item['design_code'] ?? '-'})',
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            Text(
                              'Qty: ${item['qty'] ?? 0} | Size: ${item['size_label'] ?? '-'}',
                            ),
                            Text(
                              'Gold: ${(item['gold_required_gm'] ?? 0)} gm | Diamond: ${(item['diamond_required_cts'] ?? 0)} cts',
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            AppStatusBadge(
                              label: (item['item_status'] ?? '-').toString(),
                              color: _statusColor(
                                (item['item_status'] ?? '').toString(),
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                  const SizedBox(height: AppSpacing.lg),
                  const AppSectionTitle('Followups'),
                  const SizedBox(height: AppSpacing.md),
                  if (_followups.isEmpty)
                    const AppEmptyState(
                      title: 'No followups yet',
                      message: 'Tap "Take Followup" to add the first update.',
                    )
                  else
                    ..._followups.map((rowRaw) {
                      final row = (rowRaw as Map).cast<String, dynamic>();
                      final imageUrl = (row['image_url'] ?? '').toString();
                      return Container(
                        margin: const EdgeInsets.only(bottom: AppSpacing.md),
                        padding: const EdgeInsets.all(AppSpacing.lg),
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
                              children: [
                                const Icon(
                                  Icons.task_alt,
                                  color: AppColors.brandRed,
                                ),
                                const SizedBox(width: AppSpacing.sm),
                                Text(
                                  (row['stage'] ?? '-').toString(),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            Text((row['description'] ?? '').toString()),
                            const SizedBox(height: AppSpacing.sm),
                            Text(
                              'Next: ${(row['next_followup_date'] ?? '-')} | By: ${(row['followup_taken_by_name'] ?? '-')}',
                              style: const TextStyle(
                                color: AppColors.textSecondary,
                              ),
                            ),
                            if (imageUrl.isNotEmpty) ...[
                              const SizedBox(height: AppSpacing.sm),
                              OutlinedButton.icon(
                                onPressed: () {
                                  showDialog(
                                    context: context,
                                    builder: (_) => Dialog(
                                      child: InteractiveViewer(
                                        child: Image.network(
                                          imageUrl,
                                          fit: BoxFit.contain,
                                        ),
                                      ),
                                    ),
                                  );
                                },
                                icon: const Icon(Icons.image_outlined),
                                label: const Text('View Image'),
                              ),
                            ],
                          ],
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'Completed':
        return AppColors.success;
      case 'Cancelled':
        return AppColors.danger;
      case 'In Production':
        return AppColors.warning;
      case 'QC':
        return AppColors.brandGold;
      case 'Ready':
        return AppColors.stone;
      default:
        return AppColors.brandRed;
    }
  }

  Widget _metaChip(String label, Object value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.background,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: Text(
        '$label: $value',
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AppColors.textSecondary,
        ),
      ),
    );
  }

  Widget _infoCard({
    required String title,
    required Object value,
    required String subtitle,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.soft,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AppColors.brandRed),
          const SizedBox(height: AppSpacing.sm),
          Text(
            title,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            value.toString(),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          if (subtitle.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.xs),
            Text(
              subtitle,
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
