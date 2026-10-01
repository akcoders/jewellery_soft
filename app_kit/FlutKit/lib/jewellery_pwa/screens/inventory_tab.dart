import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_search_bar.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_pwa/widgets/workspace_components.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class InventoryTab extends StatefulWidget {
  const InventoryTab({super.key, required this.api});
  final MobileApiService api;

  @override
  State<InventoryTab> createState() => _InventoryTabState();
}

class _InventoryTabState extends State<InventoryTab> {
  final _searchCtrl = TextEditingController();
  int _requestId = 0;
  int _material = 0;
  bool _loading = true;
  String _error = '';
  Map<String, dynamic> _summary = {};
  List<dynamic> _diamonds = [];
  List<dynamic> _gold = [];
  List<dynamic> _stones = [];

  static const _names = ['Diamond', 'Gold', 'Stone'];
  static const _icons = [
    LucideIcons.diamond,
    LucideIcons.gem,
    LucideIcons.sparkles,
  ];
  static const _colors = [
    AppColors.diamond,
    AppColors.brandGold,
    AppColors.stone,
  ];

  @override
  void initState() {
    super.initState();
    _loadAll();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadAll() async {
    final requestId = ++_requestId;
    setState(() {
      _loading = true;
      _error = '';
    });
    final query = _searchCtrl.text.trim();
    try {
      final results = await Future.wait<dynamic>([
        widget.api.fetchInventorySummary(),
        widget.api.fetchDiamondStock(query: query),
        widget.api.fetchGoldStock(query: query),
        widget.api.fetchStoneStock(query: query),
      ]);
      if (!mounted ||
          requestId != _requestId ||
          query != _searchCtrl.text.trim())
        return;
      setState(() {
        _summary = (results[0] as Map).cast<String, dynamic>();
        _diamonds = (results[1] as List?) ?? [];
        _gold = (results[2] as List?) ?? [];
        _stones = (results[3] as List?) ?? [];
      });
    } catch (e) {
      if (!mounted ||
          requestId != _requestId ||
          query != _searchCtrl.text.trim())
        return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted &&
          requestId == _requestId &&
          query == _searchCtrl.text.trim()) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _summary.isEmpty && _searchCtrl.text.isEmpty) {
      return const FullScreenLoader(message: 'Preparing your inventory...');
    }
    final rows = [_diamonds, _gold, _stones][_material];
    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          const WorkspacePageHeading(
            title: 'Material inventory',
            description: 'A clear view of your stock, weight and value.',
            icon: LucideIcons.boxes,
          ),
          const SizedBox(height: 24),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (var index = 0; index < _names.length; index++)
                ChoiceChip(
                  selected: _material == index,
                  showCheckmark: false,
                  avatar: Icon(
                    _icons[index],
                    size: 16,
                    color: _material == index ? Colors.white : AppColors.plum,
                  ),
                  label: Text(_names[index]),
                  onSelected: (_) => setState(() => _material = index),
                ),
            ],
          ),
          const SizedBox(height: 16),
          _summaryCard(),
          const SizedBox(height: 24),
          AppSearchBar(
            controller: _searchCtrl,
            hintText: 'Search type, purity, stone or colour',
            onChanged: (_) => _loadAll(),
          ),
          const SizedBox(height: 22),
          AppSectionTitle(
            '${_names[_material]} stock',
            trailing: WorkspaceBadge('${rows.length} entries'),
          ),
          const SizedBox(height: 12),
          if (_loading) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: const LinearProgressIndicator(minHeight: 3),
            ),
            const SizedBox(height: 12),
          ],
          if (_error.isNotEmpty)
            AppErrorState(message: _error, onRetry: _loadAll)
          else if (rows.isEmpty && !_loading)
            AppEmptyState(
              title: 'No ${_names[_material].toLowerCase()} stock found',
              message: _searchCtrl.text.trim().isEmpty
                  ? 'Stock entries will appear here when material is added.'
                  : 'Try a different material, type or search term.',
            )
          else
            WorkspaceCardLayout(
              minimumWidth: 330,
              maxColumns: 2,
              children: rows
                  .map(
                    (raw) => _stockCard((raw as Map).cast<String, dynamic>()),
                  )
                  .toList(),
            ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _summaryCard() {
    final data =
        (_summary[['diamond', 'gold', 'stone'][_material]] as Map?)
            ?.cast<String, dynamic>() ??
        {};
    final metrics = switch (_material) {
      0 => [
        _Metric('Pieces', AppFormatters.quantity(data['total_pcs'])),
        _Metric('Carats', '${AppFormatters.quantity(data['total_carat'])} ct'),
      ],
      1 => [
        _Metric(
          'Weight',
          '${AppFormatters.quantity(data['total_weight_gm'])} gm',
        ),
        _Metric(
          'Fine weight',
          '${AppFormatters.quantity(data['total_fine_gm'])} gm',
        ),
      ],
      _ => [_Metric('Quantity', AppFormatters.quantity(data['total_qty']))],
    };
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.xl),
        gradient: const LinearGradient(
          colors: [AppColors.plum, Color(0xFF654152)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'TOTAL STOCK VALUE',
                  style: TextStyle(
                    fontSize: 10,
                    letterSpacing: 1.5,
                    color: AppColors.paleGold,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Icon(_icons[_material], size: 24, color: AppColors.paleGold),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            '₹ ${AppFormatters.amount(data['total_value'])}',
            style: const TextStyle(
              fontSize: 30,
              fontWeight: FontWeight.w600,
              color: Colors.white,
              letterSpacing: -.9,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Across all ${_names[_material].toLowerCase()} stock',
            style: const TextStyle(fontSize: 12, color: Color(0xFFD6C4CE)),
          ),
          const SizedBox(height: 22),
          Container(height: 1, color: Colors.white.withValues(alpha: .15)),
          const SizedBox(height: 16),
          Wrap(
            spacing: 36,
            runSpacing: 14,
            children: metrics
                .map(
                  (metric) => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        metric.label,
                        style: const TextStyle(
                          fontSize: 11,
                          color: Color(0xFFD6C4CE),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        metric.value,
                        style: const TextStyle(
                          fontSize: 18,
                          color: Colors.white,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }

  Widget _stockCard(Map<String, dynamic> row) {
    late final String title;
    late final String subtitle;
    late final List<_Metric> metrics;
    if (_material == 0) {
      final from = (row['chalni_from'] ?? '').toString().trim();
      final to = (row['chalni_to'] ?? '').toString().trim();
      final chalni = from.isEmpty && to.isEmpty
          ? 'Not specified'
          : '$from – $to';
      title = '${row['diamond_type'] ?? '-'} · ${row['shape'] ?? '-'}';
      subtitle =
          '${row['color'] ?? '-'} · ${row['clarity'] ?? '-'} · Chalni $chalni';
      metrics = [
        _Metric('Pieces', AppFormatters.quantity(row['pcs_balance'])),
        _Metric('Carats', '${AppFormatters.quantity(row['carat_balance'])} ct'),
        _Metric('Stock value', '₹ ${AppFormatters.amount(row['stock_value'])}'),
        _Metric(
          'Average / ct',
          '₹ ${AppFormatters.amount(row['avg_cost_per_carat'])}',
        ),
      ];
    } else if (_material == 1) {
      title = '${row['purity_code'] ?? '-'} ${row['color_name'] ?? ''}'.trim();
      subtitle = 'Form · ${row['form_type'] ?? '-'}';
      metrics = [
        _Metric(
          'Weight',
          '${AppFormatters.quantity(row['weight_balance_gm'])} gm',
        ),
        _Metric(
          'Fine weight',
          '${AppFormatters.quantity(row['fine_balance_gm'])} gm',
        ),
        _Metric('Stock value', '₹ ${AppFormatters.amount(row['stock_value'])}'),
        _Metric(
          'Average / gm',
          '₹ ${AppFormatters.amount(row['avg_cost_per_gm'])}',
        ),
      ];
    } else {
      title = (row['product_name'] ?? '-').toString();
      subtitle = (row['stone_type'] ?? '-').toString();
      metrics = [
        _Metric('Quantity', AppFormatters.quantity(row['qty_balance'])),
        _Metric('Stock value', '₹ ${AppFormatters.amount(row['stock_value'])}'),
        _Metric('Average rate', '₹ ${AppFormatters.amount(row['avg_rate'])}'),
        _Metric(
          'Default rate',
          '₹ ${AppFormatters.amount(row['default_rate'])}',
        ),
      ];
    }
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              WorkspaceIconTile(
                icon: _icons[_material],
                color: _colors[_material],
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: AppColors.plum,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 11,
                        height: 1.5,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          const Divider(height: 1),
          const SizedBox(height: 16),
          WorkspaceCardLayout(
            minimumWidth: 105,
            maxColumns: 2,
            spacing: 16,
            children: metrics
                .map(
                  (metric) => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        metric.label,
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppColors.textSecondary,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        metric.value,
                        style: const TextStyle(
                          fontSize: 15,
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }
}

class _Metric {
  const _Metric(this.label, this.value);
  final String label;
  final String value;
}
