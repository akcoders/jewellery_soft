import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutter/material.dart';

class IssuementsScreen extends StatefulWidget {
  const IssuementsScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<IssuementsScreen> createState() => _IssuementsScreenState();
}

class _IssuementsScreenState extends State<IssuementsScreen> {
  final _search = TextEditingController();
  bool _loading = true;
  String _error = '';
  List<dynamic> _rows = [];

  @override
  void initState() {
    super.initState();
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
      final rows = await widget.api.fetchIssuements();
      if (!mounted) return;
      setState(() => _rows = rows);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error.isNotEmpty)
      return AppErrorState(message: _error, onRetry: _load);
    final query = _search.text.trim().toLowerCase();
    final rows = _rows.where((dynamic raw) {
      final row = raw as Map;
      if (query.isEmpty) return true;
      return [
        'voucher_no',
        'karigar_name',
        'issue_to',
        'purpose',
        'material_type',
      ].any((key) => '${row[key] ?? ''}'.toLowerCase().contains(query));
    }).toList();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          TextField(
            controller: _search,
            decoration: const InputDecoration(
              labelText: 'Search voucher, karigar or material',
              prefixIcon: Icon(Icons.search),
              border: OutlineInputBorder(),
            ),
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: AppSpacing.lg),
          if (rows.isEmpty)
            const AppEmptyState(
              title: 'No issuements found',
              message:
                  'Create Gold, Diamond and Stone issuements from one form.',
            )
          else
            ...rows.map((dynamic raw) {
              final row = raw as Map;
              final materials = (row['materials'] as List? ?? []).join(' + ');
              return Card(
                margin: const EdgeInsets.only(bottom: AppSpacing.md),
                child: ListTile(
                  contentPadding: const EdgeInsets.all(AppSpacing.md),
                  leading: CircleAvatar(
                    backgroundColor: AppColors.brandRed.withValues(alpha: 0.1),
                    child: const Icon(
                      Icons.outbox_outlined,
                      color: AppColors.brandRed,
                    ),
                  ),
                  title: Text(
                    '${row['voucher_no'] ?? '-'}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(
                      [
                        materials.isEmpty
                            ? '${row['material_type'] ?? '-'}'
                            : materials,
                        '${row['karigar_name'] ?? row['issue_to'] ?? '-'}',
                        AppFormatters.date(row['issue_date']),
                        '${row['total_lines'] ?? 0} line(s) · ₹${AppFormatters.amount(row['total_value'])}',
                      ].join('\n'),
                    ),
                  ),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => IssuementDetailScreen(
                        api: widget.api,
                        voucherNo: '${row['voucher_no'] ?? ''}',
                      ),
                    ),
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }
}

class IssuementDetailScreen extends StatefulWidget {
  const IssuementDetailScreen({
    super.key,
    required this.api,
    required this.voucherNo,
  });

  final MobileApiService api;
  final String voucherNo;

  @override
  State<IssuementDetailScreen> createState() => _IssuementDetailScreenState();
}

class _IssuementDetailScreenState extends State<IssuementDetailScreen> {
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
      final data = await widget.api.fetchIssuementDetail(widget.voucherNo);
      if (!mounted) return;
      setState(() => _data = data);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final header = (_data['header'] as Map?) ?? {};
    final lines = (_data['lines'] as Map?) ?? {};
    return Scaffold(
      appBar: AppBar(title: Text(widget.voucherNo)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error.isNotEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                _card('Issuement Details', [
                  _detail('Date', AppFormatters.date(header['issue_date'])),
                  _detail(
                    'Karigar',
                    '${header['karigar_name'] ?? header['issue_to'] ?? '-'}',
                  ),
                  _detail('Warehouse', '${header['warehouse_name'] ?? '-'}'),
                  _detail('Purpose', '${header['purpose'] ?? '-'}'),
                  _detail('Notes', '${header['notes'] ?? '-'}'),
                ]),
                for (final material in const ['gold', 'diamond', 'stone'])
                  if ((lines[material] as List? ?? []).isNotEmpty)
                    _materialCard(material, lines[material] as List),
              ],
            ),
    );
  }

  Widget _materialCard(String material, List rows) => _card(
    '${material[0].toUpperCase()}${material.substring(1)} Lines',
    rows.asMap().entries.map((entry) {
      final row = entry.value as Map;
      final description = switch (material) {
        'gold' =>
          '${row['purity_code'] ?? '-'} · ${row['weight_gm'] ?? 0} gm · ₹${AppFormatters.amount(row['rate_per_gm'])}/gm',
        'diamond' =>
          '${row['bag_no'] ?? row['diamond_type'] ?? '-'} · ${row['pcs'] ?? 0} PCS · ${row['carat'] ?? 0} CTS · ₹${AppFormatters.amount(row['rate_per_carat'])}/ct',
        _ =>
          '${row['product_name'] ?? '-'} · ${row['pcs'] ?? 0} PCS · ${row['qty'] ?? 0} qty · ₹${AppFormatters.amount(row['rate'])}',
      };
      return ListTile(
        contentPadding: EdgeInsets.zero,
        leading: CircleAvatar(child: Text('${entry.key + 1}')),
        title: Text(description),
        subtitle: Text('Value: ₹${AppFormatters.amount(row['line_value'])}'),
      );
    }).toList(),
  );

  Widget _card(String title, List<Widget> children) => Card(
    margin: const EdgeInsets.only(bottom: AppSpacing.lg),
    child: Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
          ),
          const Divider(),
          ...children,
        ],
      ),
    ),
  );

  Widget _detail(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 100,
          child: Text(
            label,
            style: const TextStyle(color: AppColors.textSecondary),
          ),
        ),
        Expanded(child: Text(value)),
      ],
    ),
  );
}
