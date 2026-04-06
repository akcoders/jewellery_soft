import 'package:flutter/material.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_search_bar.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_mobile/screens/transaction_detail_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';

class TransactionsScreen extends StatefulWidget {
  const TransactionsScreen({
    super.key,
    required this.title,
    required this.loader,
    required this.icon,
    required this.accentColor,
    required this.transactionKey,
    required this.api,
  });

  final String title;
  final Future<List<dynamic>> Function() loader;
  final IconData icon;
  final Color accentColor;
  final String transactionKey;
  final MobileApiService api;

  @override
  State<TransactionsScreen> createState() => _TransactionsScreenState();
}

class _TransactionsScreenState extends State<TransactionsScreen> {
  bool _loading = true;
  String _error = '';
  List<dynamic> _rows = [];
  List<dynamic> _filtered = [];
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(covariant TransactionsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.title != widget.title || oldWidget.loader != widget.loader) {
      _searchCtrl.clear();
      _load();
    }
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
      _rows = [];
      _filtered = [];
    });
    try {
      final rows = await widget.loader();
      if (!mounted) return;
      setState(() {
        _rows = rows;
        _filtered = rows;
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

  void _applySearch(String query) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) {
      setState(() => _filtered = _rows);
      return;
    }
    final filtered = _rows.where((raw) {
      final row = (raw as Map).cast<String, dynamic>();
      final haystack = [
        row['voucher_no'],
        row['order_no'],
        row['issue_to'],
        row['return_from'],
        row['supplier_name'],
        row['karigar_name'],
        row['invoice_no'],
        row['purpose'],
        row['notes'],
        row['issue_date'],
        row['return_date'],
        row['purchase_date'],
      ].map((e) => (e ?? '').toString().toLowerCase()).join(' ');
      return haystack.contains(q);
    }).toList();
    setState(() => _filtered = filtered);
  }

  Future<void> _openDetails(Map<String, dynamic> row) async {
    final title = widget.title
        .replaceAll('Issues', 'Issue')
        .replaceAll('Returns', 'Return');
    final id = int.tryParse(row['id']?.toString() ?? '') ?? 0;
    if (id <= 0) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TransactionDetailScreen(
          api: widget.api,
          transactionKey: widget.transactionKey,
          id: id,
          title: title,
          accentColor: widget.accentColor,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const FullScreenLoader();
    }
    if (_error.isNotEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          AppSectionTitle(widget.title),
          const SizedBox(height: AppSpacing.md),
          AppSearchBar(
            controller: _searchCtrl,
            hintText: 'Search by party, voucher, purpose...',
            onChanged: _applySearch,
          ),
          const SizedBox(height: AppSpacing.lg),
          if (_filtered.isEmpty)
            const AppEmptyState(
              title: 'No transactions found',
              message: 'Try changing the search or pull to refresh.',
            )
          else
            ..._filtered.map((raw) {
              final row = (raw as Map).cast<String, dynamic>();
              final date =
                  row['issue_date'] ??
                  row['return_date'] ??
                  row['purchase_date'] ??
                  row['created_at'] ??
                  '-';
              final party =
                  row['issue_to'] ??
                  row['return_from'] ??
                  row['supplier_name'] ??
                  row['karigar_name'] ??
                  '-';
              final purpose = row['purpose'] ?? row['invoice_no'] ?? '-';
              final voucher = (row['voucher_no'] ?? '').toString();

              return Container(
                margin: const EdgeInsets.only(bottom: AppSpacing.md),
                decoration: BoxDecoration(
                  color: AppColors.card,
                  borderRadius: BorderRadius.circular(AppRadius.lg),
                  border: Border.all(color: AppColors.border),
                  boxShadow: AppShadows.soft,
                ),
                child: ListTile(
                  onTap: () => _openDetails(row),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.lg,
                    vertical: AppSpacing.sm,
                  ),
                  leading: Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: widget.accentColor.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(AppRadius.md),
                    ),
                    child: Icon(widget.icon, color: widget.accentColor),
                  ),
                  title: Text(
                    voucher.isNotEmpty
                        ? voucher
                        : '${widget.title} #${row['id'] ?? '-'}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Party: $party'),
                        Text('Ref: $purpose'),
                        Text('Date: $date'),
                      ],
                    ),
                  ),
                  trailing: const Icon(Icons.chevron_right),
                ),
              );
            }),
        ],
      ),
    );
  }
}
