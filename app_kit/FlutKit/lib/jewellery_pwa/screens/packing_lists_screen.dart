import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class PackingListsScreen extends StatefulWidget {
  const PackingListsScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<PackingListsScreen> createState() => _PackingListsScreenState();
}

class _PackingListsScreenState extends State<PackingListsScreen> {
  final TextEditingController _search = TextEditingController();
  final Set<int> _selected = <int>{};
  List<Map<String, dynamic>> _orders = <Map<String, dynamic>>[];
  bool _loading = true;
  bool _generating = false;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _search.addListener(() => setState(() {}));
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      final rows = await widget.api.fetchPackingListOrders();
      if (!mounted) return;
      setState(() {
        _orders = rows
            .whereType<Map>()
            .map((row) => row.cast<String, dynamic>())
            .toList();
        _selected.removeWhere(
          (id) => !_orders.any((row) => _id(row['id']) == id),
        );
      });
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _visible {
    final query = _search.text.trim().toLowerCase();
    if (query.isEmpty) return _orders;
    return _orders.where((row) {
      return [
        row['order_no'],
        row['order_name'],
        row['customer_name'],
        row['karigar_name'],
      ].join(' ').toLowerCase().contains(query);
    }).toList();
  }

  Future<void> _generate() async {
    if (_selected.isEmpty || _generating) return;
    setState(() => _generating = true);
    try {
      await widget.api.downloadBatchPackingList(_selected.toList());
      if (mounted) _message('Packing list PDF downloaded.');
    } catch (e) {
      if (mounted) _message(e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _generating = false);
    }
  }

  void _selectVisible() {
    final visibleIds = _visible
        .map((row) => _id(row['id']))
        .where((id) => id > 0);
    final allSelected =
        visibleIds.isNotEmpty && visibleIds.every(_selected.contains);
    setState(() {
      if (allSelected) {
        _selected.removeAll(visibleIds);
      } else {
        _selected.addAll(visibleIds);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const FullScreenLoader();
    if (_error.isNotEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    final rows = _visible;
    final wide = MediaQuery.sizeOf(context).width >= 760;
    return Stack(
      children: [
        RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.lg,
              AppSpacing.lg,
              112,
            ),
            children: [
              _hero(),
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _search,
                      decoration: const InputDecoration(
                        hintText: 'Search order, customer or karigar',
                        prefixIcon: Icon(LucideIcons.search),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  OutlinedButton.icon(
                    onPressed: rows.isEmpty ? null : _selectVisible,
                    icon: const Icon(LucideIcons.list_checks, size: 18),
                    label: Text(wide ? 'Select visible' : 'Select'),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (rows.isEmpty)
                const AppEmptyState(
                  title: 'No ready orders',
                  message:
                      'Only completed orders with an actual receiving entry appear here.',
                )
              else
                ...rows.map(_orderCard),
            ],
          ),
        ),
        Positioned(left: 18, right: 18, bottom: 16, child: _actionBar()),
      ],
    );
  }

  Widget _hero() => Container(
    padding: const EdgeInsets.all(24),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [AppColors.plum, Color(0xFF762D49)],
      ),
      borderRadius: BorderRadius.circular(AppRadius.xl),
      boxShadow: AppShadows.soft,
    ),
    child: Row(
      children: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.brandGold.withValues(alpha: .16),
            borderRadius: BorderRadius.circular(18),
          ),
          child: const Icon(
            LucideIcons.package_check,
            color: AppColors.brandGold,
            size: 30,
          ),
        ),
        const SizedBox(width: 16),
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'READY ORDER DISPATCH',
                style: TextStyle(
                  color: AppColors.brandGold,
                  fontSize: 10,
                  letterSpacing: 1.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
              SizedBox(height: 5),
              Text(
                'Packing List Generator',
                style: TextStyle(
                  color: Colors.white,
                  fontFamily: 'CormorantGaramond',
                  fontSize: 25,
                  fontWeight: FontWeight.w700,
                ),
              ),
              SizedBox(height: 3),
              Text(
                'Select completed & received orders for one A4 landscape PDF.',
                style: TextStyle(color: Colors.white70, fontSize: 12),
              ),
            ],
          ),
        ),
        Text(
          '${_orders.length}',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 27,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );

  Widget _orderCard(Map<String, dynamic> row) {
    final id = _id(row['id']);
    final selected = _selected.contains(id);
    return Card(
      color: selected ? AppColors.paleGold : null,
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        onTap: () => setState(() {
          selected ? _selected.remove(id) : _selected.add(id);
        }),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Checkbox(
                value: selected,
                onChanged: (_) => setState(() {
                  selected ? _selected.remove(id) : _selected.add(id);
                }),
              ),
              _photo(row),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${row['order_no'] ?? '-'}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${row['customer_name'] ?? 'Customer'} · ${row['karigar_name'] ?? 'Karigar'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: AppColors.textSecondary),
                    ),
                    const SizedBox(height: 7),
                    Wrap(
                      spacing: 7,
                      runSpacing: 5,
                      children: [
                        _chip('${row['purity_code'] ?? '-'}'),
                        _chip(
                          '${AppFormatters.amount(row['gross_weight_gm'])} gm gross',
                        ),
                        _chip(
                          '₹${AppFormatters.amount(row['total_valuation'])}',
                          gold: true,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  const Text(
                    'RECEIVED',
                    style: TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 8,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    AppFormatters.date(
                      row['received_at'] ?? row['receipt_created_at'],
                    ),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _photo(Map<String, dynamic> row) {
    final url = (row['photo_url'] ?? '').toString();
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: 60,
        height: 60,
        color: const Color(0xFFF4EFF1),
        child: url.isEmpty
            ? const Icon(LucideIcons.image, color: AppColors.textSecondary)
            : Image.network(
                url,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => const Icon(
                  LucideIcons.image,
                  color: AppColors.textSecondary,
                ),
              ),
      ),
    );
  }

  Widget _chip(String text, {bool gold = false}) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: gold ? AppColors.brandGold.withValues(alpha: .14) : Colors.white,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: AppColors.border),
    ),
    child: Text(
      text,
      style: TextStyle(
        color: gold ? AppColors.plum : AppColors.textSecondary,
        fontSize: 10,
        fontWeight: FontWeight.w700,
      ),
    ),
  );

  Widget _actionBar() => Material(
    elevation: 12,
    borderRadius: BorderRadius.circular(18),
    color: AppColors.plum,
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  '${_selected.length} selected',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const Text(
                  'A4 landscape PDF',
                  style: TextStyle(color: Colors.white60, fontSize: 10),
                ),
              ],
            ),
          ),
          FilledButton.icon(
            onPressed: _selected.isEmpty || _generating ? null : _generate,
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.brandGold,
              foregroundColor: AppColors.plum,
            ),
            icon: _generating
                ? const SizedBox.square(
                    dimension: 17,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(LucideIcons.file_down, size: 18),
            label: Text(_generating ? 'Generating…' : 'Download PDF'),
          ),
        ],
      ),
    ),
  );

  int _id(dynamic value) => int.tryParse(value?.toString() ?? '') ?? 0;

  void _message(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}
