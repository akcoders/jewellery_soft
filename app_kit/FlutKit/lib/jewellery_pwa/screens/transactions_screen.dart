import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/workspace_components.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_search_bar.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_pwa/screens/transaction_detail_screen.dart';
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
  int _requestId = 0;
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
    final requestId = ++_requestId;
    setState(() {
      _loading = true;
      _error = '';
      _rows = [];
      _filtered = [];
    });
    try {
      final rows = await widget.loader();
      if (!mounted || requestId != _requestId) return;
      setState(() {
        _rows = rows;
        _filtered = _matchingRows(rows, _searchCtrl.text);
      });
    } catch (e) {
      if (!mounted || requestId != _requestId) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted && requestId == _requestId) {
        setState(() => _loading = false);
      }
    }
  }

  void _applySearch(String query) {
    setState(() => _filtered = _matchingRows(_rows, query));
  }

  List<dynamic> _matchingRows(List<dynamic> rows, String query) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return rows;
    return rows.where((raw) {
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
        row['payment_terms_days'],
        row['due_date'],
      ].map((e) => (e ?? '').toString().toLowerCase()).join(' ');
      return haystack.contains(q);
    }).toList();
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
    if (_loading) return const FullScreenLoader();
    if (_error.isNotEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          WorkspacePageHeading(
            title: widget.title,
            description: 'Every movement, reference and party in one place.',
            icon: _materialIcon,
          ),
          const SizedBox(height: 24),
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(AppRadius.lg),
              border: Border.all(color: AppColors.border),
            ),
            child: Row(
              children: [
                WorkspaceIconTile(
                  icon: LucideIcons.receipt_text,
                  color: widget.accentColor,
                  size: 44,
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${_rows.length}',
                        style: const TextStyle(
                          fontSize: 27,
                          color: AppColors.plum,
                          fontWeight: FontWeight.w700,
                          letterSpacing: -.7,
                        ),
                      ),
                      const Text(
                        'Transaction records',
                        style: TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Refresh transactions',
                  onPressed: _load,
                  icon: const Icon(LucideIcons.refresh_cw, size: 19),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          AppSearchBar(
            controller: _searchCtrl,
            hintText: 'Search party, voucher or reference',
            onChanged: _applySearch,
          ),
          const SizedBox(height: 24),
          AppSectionTitle(
            _searchCtrl.text.trim().isEmpty ? 'All records' : 'Search results',
            trailing: WorkspaceBadge('${_filtered.length} records'),
          ),
          const SizedBox(height: 12),
          if (_filtered.isEmpty)
            const AppEmptyState(
              title: 'No transactions found',
              message: 'Try changing your search or pull to refresh.',
            )
          else
            WorkspaceCardLayout(
              minimumWidth: 330,
              maxColumns: 2,
              children: _filtered
                  .map(
                    (raw) =>
                        _transactionCard((raw as Map).cast<String, dynamic>()),
                  )
                  .toList(),
            ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  IconData get _materialIcon {
    if (widget.transactionKey.startsWith('diamond')) return LucideIcons.diamond;
    if (widget.transactionKey.startsWith('gold')) return LucideIcons.gem;
    if (widget.transactionKey.startsWith('stone')) return LucideIcons.sparkles;
    return widget.icon;
  }

  Widget _transactionCard(Map<String, dynamic> row) {
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
    final order = (row['order_no'] ?? '').toString();
    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        side: const BorderSide(color: AppColors.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _openDetails(row),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  WorkspaceIconTile(
                    icon: _materialIcon,
                    color: widget.accentColor,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          voucher.isNotEmpty
                              ? voucher
                              : '${widget.title} #${row['id'] ?? '-'}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 15,
                            color: AppColors.plum,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          party.toString(),
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Icon(
                    LucideIcons.arrow_up_right,
                    size: 17,
                    color: AppColors.textSecondary,
                  ),
                ],
              ),
              const SizedBox(height: 18),
              WorkspaceMetadata(
                icon: LucideIcons.notebook_pen,
                text: 'Reference · $purpose',
              ),
              if (order.isNotEmpty) ...[
                const SizedBox(height: 8),
                WorkspaceMetadata(
                  icon: LucideIcons.clipboard_list,
                  text: 'Order · $order',
                ),
              ],
              if (widget.transactionKey == 'diamond_purchase') ...[
                const SizedBox(height: 8),
                WorkspaceMetadata(
                  icon: LucideIcons.calendar_clock,
                  text:
                      'Terms · ${row['payment_terms_days'] == null ? '—' : '${row['payment_terms_days']} days'}'
                      '  /  Due ${AppFormatters.date(row['due_date'])}',
                ),
              ],
              const SizedBox(height: 16),
              const Divider(height: 1),
              const SizedBox(height: 12),
              WorkspaceMetadata(
                icon: LucideIcons.calendar_days,
                text: AppFormatters.date(date),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
