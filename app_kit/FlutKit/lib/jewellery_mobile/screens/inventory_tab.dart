import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_search_bar.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

class InventoryTab extends StatefulWidget {
  const InventoryTab({super.key, required this.api});

  final MobileApiService api;

  @override
  State<InventoryTab> createState() => _InventoryTabState();
}

class _InventoryTabState extends State<InventoryTab>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final _searchCtrl = TextEditingController();
  bool _loading = true;
  String _error = '';
  Map<String, dynamic> _summary = {};
  List<dynamic> _diamonds = [];
  List<dynamic> _gold = [];
  List<dynamic> _stones = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _loadAll();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadAll() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    final q = _searchCtrl.text.trim();
    try {
      final results = await Future.wait<dynamic>([
        widget.api.fetchInventorySummary(),
        widget.api.fetchDiamondStock(query: q),
        widget.api.fetchGoldStock(query: q),
        widget.api.fetchStoneStock(query: q),
      ]);
      if (!mounted) return;
      setState(() {
        _summary = (results[0] as Map).cast<String, dynamic>();
        _diamonds = (results[1] as List?) ?? <dynamic>[];
        _gold = (results[2] as List?) ?? <dynamic>[];
        _stones = (results[3] as List?) ?? <dynamic>[];
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

  @override
  Widget build(BuildContext context) {
    final d = (_summary['diamond'] as Map?)?.cast<String, dynamic>() ?? {};
    final g = (_summary['gold'] as Map?)?.cast<String, dynamic>() ?? {};
    final s = (_summary['stone'] as Map?)?.cast<String, dynamic>() ?? {};

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
          child: AppSearchBar(
            controller: _searchCtrl,
            hintText: 'Search by type, purity, stone, color...',
            onChanged: (_) => _loadAll(),
          ),
        ),
        SizedBox(
          height: 124,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            children: [
              _summaryCard(
                width: 216,
                icon: Icons.diamond_outlined,
                title: 'Diamond',
                stats: [
                  _SummaryStat('PCS', AppFormatters.quantity(d['total_pcs'])),
                  _SummaryStat('CTS', AppFormatters.quantity(d['total_carat'])),
                  _SummaryStat('Value', 'Rs ${AppFormatters.amount(d['total_value'])}'),
                ],
                color: AppColors.diamond,
              ),
              const SizedBox(width: 12),
              _summaryCard(
                width: 216,
                icon: Icons.workspace_premium_outlined,
                title: 'Gold',
                stats: [
                  _SummaryStat('Weight', '${AppFormatters.quantity(g['total_weight_gm'])} gm'),
                  _SummaryStat('Fine', '${AppFormatters.quantity(g['total_fine_gm'])} gm'),
                  _SummaryStat('Value', 'Rs ${AppFormatters.amount(g['total_value'])}'),
                ],
                color: AppColors.gold,
              ),
              const SizedBox(width: 12),
              _summaryCard(
                width: 216,
                icon: Icons.scatter_plot_outlined,
                title: 'Stone',
                stats: [
                  _SummaryStat('Qty', AppFormatters.quantity(s['total_qty'])),
                  _SummaryStat('Value', 'Rs ${AppFormatters.amount(s['total_value'])}'),
                ],
                color: AppColors.stone,
              ),
            ],
          ),
        ),
        Container(
          margin: const EdgeInsets.fromLTRB(16, 8, 16, 8),
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(color: AppColors.border),
          ),
          child: TabBar(
            controller: _tabController,
            isScrollable: false,
            indicatorSize: TabBarIndicatorSize.tab,
            labelStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
            unselectedLabelStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
            labelPadding: EdgeInsets.zero,
            labelColor: Colors.white,
            unselectedLabelColor: AppColors.textSecondary,
            indicator: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.md),
              gradient: const LinearGradient(
                colors: [AppColors.brandRed, AppColors.brandGold],
              ),
            ),
            dividerColor: Colors.transparent,
            tabs: const [
              Tab(text: 'Diamond'),
              Tab(text: 'Gold'),
              Tab(text: 'Stone'),
            ],
          ),
        ),
        Expanded(
          child: _loading
              ? const FullScreenLoader()
              : _error.isNotEmpty
              ? AppErrorState(message: _error, onRetry: _loadAll)
              : TabBarView(
                  controller: _tabController,
                  children: [
                    _diamondList(),
                    _goldList(),
                    _stoneList(),
                  ],
                ),
        ),
      ],
    );
  }

  Widget _summaryCard({
    required double width,
    required IconData icon,
    required String title,
    required List<_SummaryStat> stats,
    required Color color,
  }) {
    return Container(
      width: width,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [color.withValues(alpha: 0.16), Colors.white],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(AppRadius.xl),
        border: Border.all(color: color.withValues(alpha: 0.22)),
        boxShadow: AppShadows.soft,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(icon, color: color),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  '$title Stock',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: stats.map((stat) => _statPill(stat.label, stat.value, color)).toList(),
          ),
        ],
      ),
    );
  }

  Widget _statPill(String label, String value, Color color) {
    return Container(
      constraints: const BoxConstraints(minWidth: 84),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
        ],
      ),
    );
  }

  Widget _diamondList() {
    if (_diamonds.isEmpty) {
      return const AppEmptyState(
        title: 'No diamond stock found',
        message: 'Try changing the search.',
      );
    }
    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 20),
        itemCount: _diamonds.length,
        itemBuilder: (context, index) {
          final row = (_diamonds[index] as Map).cast<String, dynamic>();
          final from = (row['chalni_from'] ?? '').toString().trim();
          final to = (row['chalni_to'] ?? '').toString().trim();
          final chalni = from.isEmpty && to.isEmpty ? 'NA' : '$from - $to';
          return _stockCard(
            accent: AppColors.diamond,
            title: '${row['diamond_type'] ?? '-'} | ${row['shape'] ?? '-'}',
            subtitle: 'Chalni $chalni | ${row['color'] ?? '-'} | ${row['clarity'] ?? '-'}',
            metrics: [
              _Metric('PCS', AppFormatters.quantity(row['pcs_balance'])),
              _Metric('CTS', AppFormatters.quantity(row['carat_balance'])),
              _Metric('Value', 'Rs ${AppFormatters.amount(row['stock_value'])}'),
              _Metric('Avg', 'Rs ${AppFormatters.amount(row['avg_cost_per_carat'])}'),
            ],
          );
        },
      ),
    );
  }

  Widget _goldList() {
    if (_gold.isEmpty) {
      return const AppEmptyState(
        title: 'No gold stock found',
        message: 'Try changing the search.',
      );
    }
    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 20),
        itemCount: _gold.length,
        itemBuilder: (context, index) {
          final row = (_gold[index] as Map).cast<String, dynamic>();
          return _stockCard(
            accent: AppColors.gold,
            title: '${row['purity_code'] ?? '-'} ${row['color_name'] ?? ''}'.trim(),
            subtitle: 'Form ${row['form_type'] ?? '-'}',
            metrics: [
              _Metric('Weight', '${AppFormatters.quantity(row['weight_balance_gm'])} gm'),
              _Metric('Fine', '${AppFormatters.quantity(row['fine_balance_gm'])} gm'),
              _Metric('Value', 'Rs ${AppFormatters.amount(row['stock_value'])}'),
              _Metric('Avg', 'Rs ${AppFormatters.amount(row['avg_cost_per_gm'])}/gm'),
            ],
          );
        },
      ),
    );
  }

  Widget _stoneList() {
    if (_stones.isEmpty) {
      return const AppEmptyState(
        title: 'No stone stock found',
        message: 'Try changing the search.',
      );
    }
    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 20),
        itemCount: _stones.length,
        itemBuilder: (context, index) {
          final row = (_stones[index] as Map).cast<String, dynamic>();
          return _stockCard(
            accent: AppColors.stone,
            title: '${row['product_name'] ?? '-'}',
            subtitle: '${row['stone_type'] ?? '-'}',
            metrics: [
              _Metric('Qty', AppFormatters.quantity(row['qty_balance'])),
              _Metric('Value', 'Rs ${AppFormatters.amount(row['stock_value'])}'),
              _Metric('Avg', 'Rs ${AppFormatters.amount(row['avg_rate'])}'),
              _Metric('Default', 'Rs ${AppFormatters.amount(row['default_rate'])}'),
            ],
          );
        },
      ),
    );
  }

  Widget _stockCard({
    required Color accent,
    required String title,
    required String subtitle,
    required List<_Metric> metrics,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(AppRadius.xl),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.soft,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: accent,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 14),
          LayoutBuilder(
            builder: (context, constraints) {
              final metricWidth = (constraints.maxWidth - 10) / 2;
              return Wrap(
            spacing: 10,
            runSpacing: 10,
            children: metrics.map((metric) {
              return Container(
                width: metricWidth,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      metric.label,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      metric.value,
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        color: accent,
                      ),
                    ),
                  ],
                ),
              );
            }).toList(),
              );
            },
          ),
        ],
      ),
    );
  }
}

class _SummaryStat {
  const _SummaryStat(this.label, this.value);

  final String label;
  final String value;
}

class _Metric {
  const _Metric(this.label, this.value);

  final String label;
  final String value;
}
