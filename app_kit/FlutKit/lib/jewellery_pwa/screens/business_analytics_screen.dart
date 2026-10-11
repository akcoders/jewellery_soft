import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class BusinessAnalyticsScreen extends StatefulWidget {
  const BusinessAnalyticsScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<BusinessAnalyticsScreen> createState() =>
      _BusinessAnalyticsScreenState();
}

class _BusinessAnalyticsScreenState extends State<BusinessAnalyticsScreen> {
  int _days = 90;
  bool _loading = true;
  String _error = '';
  Map<String, dynamic> _data = {};

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
      final data = await widget.api.fetchBusinessAnalytics(days: _days);
      if (mounted) setState(() => _data = data);
    } catch (e) {
      if (mounted)
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data.isEmpty)
      return const FullScreenLoader(
        message: 'Building business intelligence...',
      );
    if (_error.isNotEmpty && _data.isEmpty)
      return AppErrorState(message: _error, onRetry: _load);
    final overview = _map(_data['overview']);
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          _hero(overview),
          const SizedBox(height: 16),
          _periodSelector(),
          if (_loading)
            const Padding(
              padding: EdgeInsets.only(top: 10),
              child: LinearProgressIndicator(minHeight: 2),
            ),
          if (_error.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                _error,
                style: const TextStyle(color: AppColors.danger),
              ),
            ),
          const SizedBox(height: 20),
          _kpis(overview),
          const SizedBox(height: 28),
          LayoutBuilder(
            builder: (context, size) {
              final trend = _trendCard();
              final status = _statusCard();
              if (size.maxWidth < 820)
                return Column(
                  children: [trend, const SizedBox(height: 16), status],
                );
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(flex: 3, child: trend),
                  const SizedBox(width: 16),
                  Expanded(flex: 2, child: status),
                ],
              );
            },
          ),
          const SizedBox(height: 28),
          const AppSectionTitle('Inventory intelligence'),
          const SizedBox(height: 12),
          _inventory(),
          const SizedBox(height: 28),
          LayoutBuilder(
            builder: (context, size) {
              final customers = _rankCard(
                'Top customers',
                _maps(_map(_data['customers'])['top']),
                LucideIcons.users,
                customer: true,
              );
              final karigars = _rankCard(
                'Karigar throughput',
                _maps(_data['karigars']),
                LucideIcons.hammer,
              );
              if (size.maxWidth < 820)
                return Column(
                  children: [customers, const SizedBox(height: 16), karigars],
                );
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: customers),
                  const SizedBox(width: 16),
                  Expanded(child: karigars),
                ],
              );
            },
          ),
          const SizedBox(height: 28),
          const AppSectionTitle('Employee performance'),
          const SizedBox(height: 12),
          _employees(),
          const SizedBox(height: 28),
          const AppSectionTitle('Decision centre'),
          const SizedBox(height: 12),
          ..._maps(_data['insights']).map(_insight),
          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _hero(Map<String, dynamic> overview) => Container(
    padding: const EdgeInsets.all(24),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [AppColors.plum, Color(0xFF713C55), Color(0xFF9A6930)],
      ),
      borderRadius: BorderRadius.circular(AppRadius.xl),
      boxShadow: AppShadows.soft,
    ),
    child: LayoutBuilder(
      builder: (context, size) {
        final heading = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'EXECUTIVE INTELLIGENCE',
              style: TextStyle(
                color: Color(0xFFFFD77C),
                fontSize: 11,
                letterSpacing: 1.8,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Business command centre',
              style: TextStyle(
                color: Colors.white,
                fontSize: 27,
                fontWeight: FontWeight.w900,
                letterSpacing: -.7,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              '${_integer(overview['orders'])} orders · ${_number(overview['completion_rate']).toStringAsFixed(1)}% completion · ${_integer(overview['overdue'])} overdue',
              style: const TextStyle(color: Colors.white70),
            ),
          ],
        );
        final value = Column(
          crossAxisAlignment: size.maxWidth < 600
              ? CrossAxisAlignment.start
              : CrossAxisAlignment.end,
          children: [
            const Text(
              'ORDER PIPELINE VALUE',
              style: TextStyle(
                color: Color(0xFFEAD7C0),
                fontSize: 10,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              '₹${AppFormatters.amount(overview['order_value'])}',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 29,
                fontWeight: FontWeight.w900,
              ),
            ),
            Text(
              'Balance ₹${AppFormatters.amount(overview['balance'])}',
              style: const TextStyle(
                color: Color(0xFFFFD77C),
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        );
        if (size.maxWidth < 600)
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [heading, const SizedBox(height: 22), value],
          );
        return Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(child: heading),
            value,
          ],
        );
      },
    ),
  );

  Widget _periodSelector() => Align(
    alignment: Alignment.centerRight,
    child: SegmentedButton<int>(
      segments: const [
        ButtonSegment(value: 30, label: Text('30D')),
        ButtonSegment(value: 90, label: Text('90D')),
        ButtonSegment(value: 180, label: Text('6M')),
        ButtonSegment(value: 365, label: Text('1Y')),
      ],
      selected: {_days},
      onSelectionChanged: (value) {
        setState(() => _days = value.first);
        _load();
      },
    ),
  );

  Widget _kpis(Map<String, dynamic> overview) {
    final cards = [
      _Kpi(
        'Average order',
        '₹${AppFormatters.amount(overview['average_order_value'])}',
        LucideIcons.badge_indian_rupee,
        AppColors.gold,
      ),
      _Kpi(
        'Advance collected',
        '₹${AppFormatters.amount(overview['advance'])}',
        LucideIcons.wallet_cards,
        AppColors.success,
      ),
      _Kpi(
        'Average cycle',
        '${_number(overview['average_cycle_days']).toStringAsFixed(1)} days',
        LucideIcons.timer,
        AppColors.diamond,
      ),
      _Kpi(
        'Receiving pending',
        '${_integer(overview['receiving_pending'])}',
        LucideIcons.package_open,
        AppColors.danger,
      ),
    ];
    return LayoutBuilder(
      builder: (context, size) {
        final width = size.maxWidth < 620
            ? (size.maxWidth - 12) / 2
            : (size.maxWidth - 36) / 4;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: cards
              .map((card) => SizedBox(width: width, child: _kpi(card)))
              .toList(),
        );
      },
    );
  }

  Widget _kpi(_Kpi card) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      border: Border.all(color: AppColors.border),
      borderRadius: BorderRadius.circular(AppRadius.lg),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(9),
          decoration: BoxDecoration(
            color: card.color.withValues(alpha: .1),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(card.icon, color: card.color, size: 19),
        ),
        const SizedBox(height: 13),
        Text(
          card.value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
        ),
        Text(
          card.label,
          style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
        ),
      ],
    ),
  );

  Widget _trendCard() {
    final rows = _maps(_data['order_trend']);
    final max = rows.fold<double>(
      1,
      (value, row) =>
          _number(row['value']) > value ? _number(row['value']) : value,
    );
    return _panel(
      'Order value trend',
      LucideIcons.chart_no_axes_column_increasing,
      rows.isEmpty
          ? const SizedBox(
              height: 180,
              child: Center(child: Text('No orders in this period')),
            )
          : SizedBox(
              height: 220,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: rows.map((row) {
                  final height = 28 + (_number(row['value']) / max * 145);
                  return Expanded(
                    child: Tooltip(
                      message:
                          '${row['period']} · ₹${AppFormatters.amount(row['value'])} · ${row['orders']} orders',
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 2),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            Text(
                              '${row['orders']}',
                              style: const TextStyle(
                                fontSize: 9,
                                color: AppColors.textSecondary,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Container(
                              height: height,
                              decoration: BoxDecoration(
                                gradient: const LinearGradient(
                                  begin: Alignment.topCenter,
                                  end: Alignment.bottomCenter,
                                  colors: [AppColors.brandGold, AppColors.plum],
                                ),
                                borderRadius: const BorderRadius.vertical(
                                  top: Radius.circular(7),
                                ),
                              ),
                            ),
                            const SizedBox(height: 7),
                            Text(
                              '${row['period']}'.substring(
                                '${row['period']}'.length > 5 ? 5 : 0,
                              ),
                              maxLines: 1,
                              style: const TextStyle(fontSize: 8),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
    );
  }

  Widget _statusCard() {
    final rows = _maps(_data['order_status']);
    final total = rows.fold<int>(0, (sum, row) => sum + _integer(row['count']));
    return _panel(
      'Order pipeline',
      LucideIcons.workflow,
      Column(
        children: rows.map((row) {
          final count = _integer(row['count']);
          final ratio = total == 0 ? 0.0 : count / total;
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${row['status']}',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                    Text(
                      '$count',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                LinearProgressIndicator(
                  value: ratio,
                  minHeight: 7,
                  borderRadius: BorderRadius.circular(8),
                  color: _statusColor('${row['status']}'),
                  backgroundColor: AppColors.border,
                ),
              ],
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _inventory() {
    final stock = _map(_data['inventory']);
    final flow = _map(_data['material_flow']);
    final entries = [
      ['Gold', _map(stock['gold']), AppColors.gold, LucideIcons.gem],
      [
        'Diamond',
        _map(stock['diamond']),
        AppColors.diamond,
        LucideIcons.diamond,
      ],
      ['Stone', _map(stock['stone']), AppColors.stone, LucideIcons.sparkles],
    ];
    return Column(
      children: [
        LayoutBuilder(
          builder: (context, size) {
            final width = size.maxWidth < 700
                ? size.maxWidth
                : (size.maxWidth - 24) / 3;
            return Wrap(
              spacing: 12,
              runSpacing: 12,
              children: entries.map((entry) {
                final row = entry[1] as Map<String, dynamic>;
                final color = entry[2] as Color;
                return SizedBox(
                  width: width,
                  child: Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: .07),
                      border: Border.all(color: color.withValues(alpha: .25)),
                      borderRadius: BorderRadius.circular(AppRadius.lg),
                    ),
                    child: Row(
                      children: [
                        CircleAvatar(
                          backgroundColor: color,
                          foregroundColor: Colors.white,
                          child: Icon(entry[3] as IconData, size: 19),
                        ),
                        const SizedBox(width: 13),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${entry[0]} stock',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              Text(
                                '${_number(row['quantity']).toStringAsFixed(3)} ${row['unit']}',
                              ),
                              Text(
                                '₹${AppFormatters.amount(row['value'])} · ${row['negative_skus']} negative',
                                style: TextStyle(
                                  fontSize: 11,
                                  color: _integer(row['negative_skus']) > 0
                                      ? AppColors.danger
                                      : AppColors.textSecondary,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }).toList(),
            );
          },
        ),
        const SizedBox(height: 12),
        _panel(
          'Material movement',
          LucideIcons.arrow_left_right,
          Wrap(
            spacing: 28,
            runSpacing: 12,
            children: [
              _mini(
                'Inventory value',
                '₹${AppFormatters.amount(stock['total_value'])}',
              ),
              _mini(
                'Finished goods',
                '${_map(stock['finished_goods'])['available'] ?? 0} available',
              ),
              _mini(
                'Purchases',
                '${_map(flow['purchases'])['count'] ?? 0} · ₹${AppFormatters.amount(_map(flow['purchases'])['value'])}',
              ),
              _mini(
                'Issuements',
                '${_map(flow['issuements'])['count'] ?? 0} vouchers',
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _rankCard(
    String title,
    List<Map<String, dynamic>> rows,
    IconData icon, {
    bool customer = false,
  }) => _panel(
    title,
    icon,
    rows.isEmpty
        ? const Padding(
            padding: EdgeInsets.all(20),
            child: Text('No activity in this period'),
          )
        : Column(
            children: rows.asMap().entries.map((entry) {
              final row = entry.value;
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 17,
                      backgroundColor: AppColors.paleGold,
                      foregroundColor: AppColors.plum,
                      child: Text(
                        '${entry.key + 1}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${row['name']}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          Text(
                            '${row['orders']} orders · ${row['completed']} completed',
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (customer)
                      Text(
                        '₹${AppFormatters.amount(row['value'])}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                  ],
                ),
              );
            }).toList(),
          ),
  );

  Widget _employees() {
    final rows = _maps(_data['employees']);
    return _panel(
      'Current month leaderboard',
      LucideIcons.trophy,
      rows.isEmpty
          ? const Padding(
              padding: EdgeInsets.all(20),
              child: Text('No employee activity yet'),
            )
          : SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: DataTable(
                columns: const [
                  DataColumn(label: Text('#')),
                  DataColumn(label: Text('Employee')),
                  DataColumn(label: Text('Score')),
                  DataColumn(label: Text('Earned')),
                  DataColumn(label: Text('Orders')),
                  DataColumn(label: Text('Follow-ups')),
                  DataColumn(label: Text('Issuements')),
                  DataColumn(label: Text('Purchases')),
                ],
                rows: rows.asMap().entries.map((entry) {
                  final row = entry.value;
                  return DataRow(
                    cells: [
                      DataCell(Text('${entry.key + 1}')),
                      DataCell(
                        Text(
                          '${row['name']}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                      DataCell(Text('${row['score']}')),
                      DataCell(Text('+${row['points_earned']}')),
                      DataCell(Text('${row['order_entries'] ?? 0}')),
                      DataCell(Text('${row['proper_followups'] ?? 0}')),
                      DataCell(Text('${row['issuements'] ?? 0}')),
                      DataCell(Text('${row['purchases'] ?? 0}')),
                    ],
                  );
                }).toList(),
              ),
            ),
    );
  }

  Widget _insight(Map<String, dynamic> row) {
    final severity = '${row['severity']}';
    final color = severity == 'critical'
        ? AppColors.danger
        : severity == 'positive'
        ? AppColors.success
        : AppColors.warning;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .07),
        border: Border(
          left: BorderSide(color: color, width: 4),
          top: const BorderSide(color: AppColors.border),
          right: const BorderSide(color: AppColors.border),
          bottom: const BorderSide(color: AppColors.border),
        ),
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            severity == 'positive'
                ? LucideIcons.circle_check
                : LucideIcons.triangle_alert,
            color: color,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${row['title']}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  '${row['action']}',
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _panel(String title, IconData icon, Widget child) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: Colors.white,
      border: Border.all(color: AppColors.border),
      borderRadius: BorderRadius.circular(AppRadius.lg),
      boxShadow: AppShadows.soft,
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, color: AppColors.brandGold, size: 20),
            const SizedBox(width: 8),
            Text(
              title,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
            ),
          ],
        ),
        const SizedBox(height: 18),
        child,
      ],
    ),
  );
  Widget _mini(String label, String value) => SizedBox(
    width: 170,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w900),
        ),
        Text(
          label,
          style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
        ),
      ],
    ),
  );
  Color _statusColor(String status) => status == 'Completed'
      ? AppColors.success
      : status == 'Cancelled'
      ? AppColors.danger
      : status == 'Dispatched'
      ? AppColors.diamond
      : AppColors.brandGold;
}

class _Kpi {
  const _Kpi(this.label, this.value, this.icon, this.color);
  final String label;
  final String value;
  final IconData icon;
  final Color color;
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? value.cast<String, dynamic>() : <String, dynamic>{};
List<Map<String, dynamic>> _maps(dynamic value) => (value as List? ?? const [])
    .whereType<Map>()
    .map((row) => row.cast<String, dynamic>())
    .toList();
int _integer(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
double _number(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
