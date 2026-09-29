import 'package:flutkit/jewellery_mobile/screens/diamond_requirements_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

class DiamondBagsScreen extends StatelessWidget {
  const DiamondBagsScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          const Material(
            color: AppColors.background,
            child: TabBar(
              tabs: [
                Tab(icon: Icon(Icons.inventory_2_outlined), text: 'Bags'),
                Tab(
                  icon: Icon(Icons.fact_check_outlined),
                  text: 'Creation Requests',
                ),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              children: [
                _DiamondBagRegister(api: api),
                DiamondRequirementsScreen(api: api),
              ],
            ),
          ),
        ],
      ),
    );
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
              children: const [
                SizedBox(height: 120),
                AppEmptyState(
                  title: 'No diamond bags',
                  message: 'Prepared diamond bags will appear here.',
                ),
              ],
            )
          : ListView.builder(
              padding: const EdgeInsets.all(AppSpacing.lg),
              itemCount: _bags.length,
              itemBuilder: (context, index) {
                final bag = _map(_bags[index]);
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
                              _bagStatus(status),
                            ],
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              _metaChip(
                                Icons.grid_view_outlined,
                                '${_int(bag['item_count'])} size rows',
                              ),
                              _metaChip(
                                Icons.diamond_outlined,
                                '${AppFormatters.quantity(bag['pcs_balance'])} pcs · ${AppFormatters.quantity(bag['cts_balance'])} cts',
                              ),
                              _metaChip(
                                Icons.assignment_outlined,
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
                  _sectionTitle('CALIBRATED CONTENTS'),
                  const SizedBox(height: AppSpacing.sm),
                  if (_items.isEmpty)
                    const AppEmptyState(
                      title: 'No bag rows',
                      message: 'This bag does not contain any diamond rows.',
                    )
                  else
                    ..._items.map(_itemCard),
                  const SizedBox(height: AppSpacing.lg),
                  _sectionTitle('ISSUE → ORDER → STUDDING TRAIL'),
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
            Row(
              children: [
                Expanded(
                  child: Text(
                    (_bag['bag_no'] ?? '-').toString(),
                    style: const TextStyle(
                      fontSize: 19,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _bagStatus((_bag['status'] ?? 'ready').toString()),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _metaChip(
                  Icons.calendar_today_outlined,
                  AppFormatters.date(_bag['prepared_date']),
                ),
                _metaChip(
                  Icons.diamond_outlined,
                  '${AppFormatters.quantity(_bag['pcs_balance'])} pcs · ${AppFormatters.quantity(_bag['cts_balance'])} cts available',
                ),
                if ((_bag['prepared_by_name'] ?? '').toString().isNotEmpty)
                  _metaChip(
                    Icons.person_outline,
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
              '${row['color'] ?? '-'} / ${row['clarity'] ?? '-'} · ${row['shape_name'] ?? '-'} · ${row['size_label'] ?? row['size'] ?? '-'}',
              style: const TextStyle(color: AppColors.textSecondary),
            ),
            const Divider(height: 20),
            Text(
              'Total ${AppFormatters.quantity(totalPcs)} pcs / ${AppFormatters.quantity(totalCts)} cts  ·  Available ${AppFormatters.quantity(availablePcs)} pcs / ${AppFormatters.quantity(availableCts)} cts',
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            ),
            Text(
              'Consumed ${AppFormatters.quantity(totalPcs - availablePcs)} pcs / ${AppFormatters.quantity(totalCts - availableCts)} cts',
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 11,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _movementCard(dynamic raw) {
    final row = _map(raw);
    final type = (row['movement_type'] ?? '-').toString().toUpperCase();
    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: AppColors.diamond.withValues(alpha: .16),
          child: const Icon(Icons.swap_horiz, color: AppColors.brandRed),
        ),
        title: Text(
          '$type · ${row['diamond_type'] ?? '-'} / ${row['size_label'] ?? '-'}',
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        subtitle: Text(
          '${AppFormatters.date(row['movement_date'])} · ${row['voucher_no'] ?? '-'} · Order ${row['order_no'] ?? 'Unallocated'}\n${row['karigar_name'] ?? '-'} · ${AppFormatters.quantity(row['pcs'])} pcs / ${AppFormatters.quantity(row['carat'])} cts',
        ),
        isThreeLine: true,
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
  return Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .13),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      status.replaceAll('_', ' ').toUpperCase(),
      style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w800),
    ),
  );
}

Widget _metaChip(IconData icon, String label) => Container(
  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
  decoration: BoxDecoration(
    color: AppColors.background,
    borderRadius: BorderRadius.circular(99),
    border: Border.all(color: AppColors.border),
  ),
  child: Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: AppColors.textSecondary),
      const SizedBox(width: 5),
      Text(
        label,
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
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
