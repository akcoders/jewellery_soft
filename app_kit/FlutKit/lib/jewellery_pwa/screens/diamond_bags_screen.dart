import 'package:flutkit/jewellery_mobile/screens/diamond_bag_create_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_status_badge.dart';

class DiamondBagsScreen extends StatelessWidget {
  const DiamondBagsScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  Widget build(BuildContext context) {
    return _DiamondBagRegister(api: api);
  }
}

class _DiamondBagRegister extends StatefulWidget {
  const _DiamondBagRegister({required this.api});

  final MobileApiService api;

  @override
  State<_DiamondBagRegister> createState() => _DiamondBagRegisterState();
}

class _DiamondBagRegisterState extends State<_DiamondBagRegister> {
  bool _loading = true;
  String _error = '';
  List<dynamic> _bags = [];

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
      final bags = await widget.api.fetchDiamondBags();
      if (mounted) setState(() => _bags = bags);
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _createBag() async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => DiamondBagCreateScreen(api: widget.api),
      ),
    );
    if (changed == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _bags.isEmpty) {
      return const FullScreenLoader(message: 'Loading diamond bags...');
    }
    if (_error.isNotEmpty && _bags.isEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: _bags.isEmpty
          ? ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                FilledButton.icon(
                  onPressed: _createBag,
                  icon: const Icon(LucideIcons.plus),
                  label: const Text('Create Diamond Bag'),
                ),
                const SizedBox(height: 80),
                const AppEmptyState(
                  title: 'No diamond bags',
                  message:
                      'Create a size-wise bag for a Diamond or Jadau order.',
                ),
              ],
            )
          : ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
              itemCount: _bags.length + 1,
              itemBuilder: (context, index) {
                if (index == 0) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                    child: FilledButton.icon(
                      onPressed: _createBag,
                      icon: const Icon(LucideIcons.plus),
                      label: const Text('Create Diamond Bag'),
                    ),
                  );
                }
                final bag = _map(_bags[index - 1]);
                final status = (bag['status'] ?? 'ready').toString();
                return Card(
                  margin: const EdgeInsets.only(bottom: AppSpacing.md),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(AppRadius.lg),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => DiamondBagDetailScreen(
                          api: widget.api,
                          bagId: _int(bag['id']),
                        ),
                      ),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 44,
                                height: 44,
                                decoration: BoxDecoration(
                                  color: AppColors.diamond.withValues(
                                    alpha: .16,
                                  ),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(
                                  Icons.inventory_2_outlined,
                                  color: AppColors.brandRed,
                                ),
                              ),
                              const SizedBox(width: AppSpacing.md),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      (bag['bag_no'] ?? '-').toString(),
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                        fontSize: 16,
                                      ),
                                    ),
                                    Text(
                                      'Prepared ${AppFormatters.date(bag['prepared_date'] ?? bag['created_at'])}',
                                      style: const TextStyle(
                                        color: AppColors.textSecondary,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const Icon(
                                LucideIcons.chevron_right,
                                size: 19,
                                color: AppColors.textSecondary,
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),
                          _bagStatus(status),
                          const Divider(height: 28),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              _metaChip(
                                LucideIcons.grid_2x2,
                                '${_int(bag['item_count'])} size rows',
                              ),
                              _metaChip(
                                LucideIcons.gem,
                                '${AppFormatters.quantity(bag['pcs_balance'])} pcs · ${AppFormatters.quantity(bag['cts_balance'])} cts',
                              ),
                              _metaChip(
                                LucideIcons.clipboard_list,
                                '${_int(bag['order_count'])} orders',
                              ),
                            ],
                          ),
                          if ((bag['requirement_no'] ?? '')
                              .toString()
                              .isNotEmpty) ...[
                            const SizedBox(height: 10),
                            Text(
                              '${bag['requirement_no']} · Order ${bag['order_no'] ?? '-'}',
                              style: const TextStyle(
                                color: AppColors.textSecondary,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
    );
  }
}

class DiamondBagDetailScreen extends StatefulWidget {
  const DiamondBagDetailScreen({
    super.key,
    required this.api,
    required this.bagId,
  });

  final MobileApiService api;
  final int bagId;

  @override
  State<DiamondBagDetailScreen> createState() => _DiamondBagDetailScreenState();
}

class _DiamondBagDetailScreenState extends State<DiamondBagDetailScreen> {
  bool _loading = true;
  String _error = '';
  Map<String, dynamic> _bag = {};
  List<dynamic> _items = [];
  List<dynamic> _movements = [];

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
      final data = await widget.api.fetchDiamondBag(widget.bagId);
      if (!mounted) return;
      setState(() {
        _bag = _map(data['bag']);
        _items = _list(data['items']);
        _movements = _list(data['movements']);
      });
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text((_bag['bag_no'] ?? 'Diamond Bag').toString())),
      body: _loading && _bag.isEmpty
          ? const FullScreenLoader(message: 'Loading bag details...')
          : _error.isNotEmpty && _bag.isEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  _headerCard(),
                  const SizedBox(height: AppSpacing.lg),
                  _sectionTitle('Calibrated contents'),
                  const SizedBox(height: AppSpacing.sm),
                  if (_items.isEmpty)
                    const AppEmptyState(
                      title: 'No bag rows',
                      message: 'This bag does not contain any diamond rows.',
                    )
                  else
                    ..._items.map(_itemCard),
                  const SizedBox(height: AppSpacing.lg),
                  _sectionTitle('Movement history'),
                  const SizedBox(height: AppSpacing.sm),
                  if (_movements.isEmpty)
                    const AppEmptyState(
                      title: 'No movement yet',
                      message: 'Bag issue and studding movements appear here.',
                    )
                  else
                    ..._movements.map(_movementCard),
                ],
              ),
            ),
    );
  }

  Widget _headerCard() {
    final imageUrl = (_bag['audit_image_url'] ?? '').toString();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(LucideIcons.gem, color: AppColors.brandGold, size: 28),
            const SizedBox(height: 14),
            Text(
              (_bag['bag_no'] ?? '-').toString(),
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 12),
            _bagStatus((_bag['status'] ?? 'ready').toString()),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _metaChip(
                  LucideIcons.calendar_days,
                  AppFormatters.date(_bag['prepared_date']),
                ),
                _metaChip(
                  LucideIcons.gem,
                  '${AppFormatters.quantity(_bag['pcs_balance'])} pcs · ${AppFormatters.quantity(_bag['cts_balance'])} cts available',
                ),
                if ((_bag['prepared_by_name'] ?? '').toString().isNotEmpty)
                  _metaChip(
                    LucideIcons.user_round,
                    _bag['prepared_by_name'].toString(),
                  ),
              ],
            ),
            if ((_bag['requirement_no'] ?? '').toString().isNotEmpty) ...[
              const Divider(height: 24),
              Text(
                'Request ${_bag['requirement_no']} · Order ${_bag['order_no'] ?? '-'}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
            if ((_bag['notes'] ?? '').toString().isNotEmpty) ...[
              const Divider(height: 24),
              Text(_bag['notes'].toString()),
            ],
            if (imageUrl.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              ClipRRect(
                borderRadius: BorderRadius.circular(AppRadius.md),
                child: Image.network(
                  imageUrl,
                  height: 190,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => const SizedBox.shrink(),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _itemCard(dynamic raw) {
    final row = _map(raw);
    final totalPcs = _double(row['pcs_total']);
    final availablePcs = _double(row['pcs_available']);
    final totalCts = _double(row['weight_cts_total']);
    final availableCts = _double(row['weight_cts_available']);
    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              (row['diamond_type'] ?? '-').toString(),
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            Text(
              '${row['color'] ?? '-'} / ${row['clarity'] ?? '-'} · ${row['shape_name'] ?? '-'} · ${row['size_label'] ?? row['chalni_group_name'] ?? row['size'] ?? '-'}',
              style: const TextStyle(color: AppColors.textSecondary),
            ),
            const Divider(height: 20),
            Wrap(
              spacing: 20,
              runSpacing: 14,
              children: [
                _balanceMetric(
                  'Available',
                  availablePcs,
                  availableCts,
                  color: AppColors.plum,
                ),
                _balanceMetric('Total', totalPcs, totalCts),
                _balanceMetric(
                  'Consumed',
                  totalPcs - availablePcs,
                  totalCts - availableCts,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _balanceMetric(
    String label,
    double pcs,
    double cts, {
    Color color = AppColors.textSecondary,
  }) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
      ),
      const SizedBox(height: 4),
      Text(
        '${AppFormatters.quantity(pcs)} pcs',
        style: TextStyle(fontWeight: FontWeight.w700, color: color),
      ),
      Text(
        '${AppFormatters.quantity(cts)} cts',
        style: TextStyle(fontSize: 12, color: color),
      ),
    ],
  );

  Widget _movementCard(dynamic raw) {
    final row = _map(raw);
    final type = (row['movement_type'] ?? '-').toString().replaceAll('_', ' ');
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.paleGold,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    LucideIcons.arrow_right_left,
                    color: AppColors.plum,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        type,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        AppFormatters.date(row['movement_date']),
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              '${row['diamond_type'] ?? '-'} · ${row['size_label'] ?? row['chalni_group_name'] ?? '-'}',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 8),
            Text(
              '${AppFormatters.quantity(row['pcs'])} pcs · ${AppFormatters.quantity(row['carat'])} cts',
              style: const TextStyle(
                color: AppColors.plum,
                fontWeight: FontWeight.w700,
              ),
            ),
            const Divider(height: 26),
            Text(
              '${row['voucher_no'] ?? '-'} · Order ${row['order_no'] ?? 'Unallocated'}',
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              '${row['karigar_name'] ?? '-'}',
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

Widget _sectionTitle(String label) => Text(
  label,
  style: const TextStyle(
    fontWeight: FontWeight.w800,
    color: AppColors.textSecondary,
    letterSpacing: .5,
  ),
);

Widget _bagStatus(String status) {
  final color = switch (status) {
    'ready' => AppColors.success,
    'partly_issued' => AppColors.warning,
    'consumed' => AppColors.textSecondary,
    _ => AppColors.diamond,
  };
  return AppStatusBadge(label: status.replaceAll('_', ' '), color: color);
}

Widget _metaChip(IconData icon, String label) => Container(
  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
  decoration: BoxDecoration(
    color: AppColors.background,
    borderRadius: BorderRadius.circular(10),
    border: Border.all(color: AppColors.border),
  ),
  child: Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: AppColors.textSecondary),
      const SizedBox(width: 5),
      Flexible(
        child: Text(
          label,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ),
    ],
  ),
);

Map<String, dynamic> _map(dynamic value) {
  if (value is Map) return value.cast<String, dynamic>();
  return <String, dynamic>{};
}

List<dynamic> _list(dynamic value) => value is List ? value : <dynamic>[];

int _int(dynamic value) {
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

double _double(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}
