import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_search_bar.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';

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
    if (_loading) return const FullScreenLoader();
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
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          AppSearchBar(
            controller: _search,
            hintText: 'Search voucher, karigar or material',
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 24),
          AppSectionTitle(
            'Material issuements',
            trailing: Text(
              '${rows.length}',
              style: const TextStyle(color: AppColors.textSecondary),
            ),
          ),
          const SizedBox(height: 16),
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
                margin: const EdgeInsets.only(bottom: 14),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => IssuementDetailScreen(
                        api: widget.api,
                        voucherNo: '${row['voucher_no'] ?? ''}',
                      ),
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(11),
                              decoration: BoxDecoration(
                                color: AppColors.paleGold,
                                borderRadius: BorderRadius.circular(14),
                              ),
                              child: const Icon(
                                LucideIcons.package_open,
                                size: 22,
                                color: AppColors.plum,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '${row['voucher_no'] ?? '-'}',
                                    style: Theme.of(
                                      context,
                                    ).textTheme.titleMedium,
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    AppFormatters.date(row['issue_date']),
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
                        const SizedBox(height: 16),
                        Text(
                          materials.isEmpty
                              ? '${row['material_type'] ?? '-'}'
                              : materials,
                          style: const TextStyle(
                            color: AppColors.plum,
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(height: 10),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(
                              LucideIcons.user_round,
                              size: 16,
                              color: AppColors.textSecondary,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                '${row['karigar_name'] ?? row['issue_to'] ?? '-'}',
                              ),
                            ),
                          ],
                        ),
                        const Divider(height: 28),
                        Wrap(
                          spacing: 20,
                          runSpacing: 12,
                          children: [
                            _metric(
                              'Total value',
                              '₹${AppFormatters.amount(row['total_value'])}',
                            ),
                            _metric(
                              'Material lines',
                              '${row['total_lines'] ?? 0}',
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }

  Widget _metric(String label, String value) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: const TextStyle(color: AppColors.textSecondary, fontSize: 11),
      ),
      const SizedBox(height: 4),
      Text(
        value,
        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
      ),
    ],
  );
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
          ? const FullScreenLoader()
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
        leading: CircleAvatar(
          backgroundColor: AppColors.paleGold,
          foregroundColor: AppColors.plum,
          child: Text(
            '${entry.key + 1}',
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        title: Text(
          description,
          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
        ),
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
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const Divider(height: 28),
          ...children,
        ],
      ),
    ),
  );

  Widget _detail(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 84,
          child: Text(
            label,
            style: const TextStyle(color: AppColors.textSecondary),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}
