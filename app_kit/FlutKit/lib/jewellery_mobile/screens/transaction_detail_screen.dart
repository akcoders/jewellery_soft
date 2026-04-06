import 'package:flutter/material.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_status_badge.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:url_launcher/url_launcher.dart';

class TransactionDetailScreen extends StatefulWidget {
  const TransactionDetailScreen({
    super.key,
    required this.api,
    required this.transactionKey,
    required this.id,
    required this.title,
    required this.accentColor,
  });

  final MobileApiService api;
  final String transactionKey;
  final int id;
  final String title;
  final Color accentColor;

  @override
  State<TransactionDetailScreen> createState() =>
      _TransactionDetailScreenState();
}

class _TransactionDetailScreenState extends State<TransactionDetailScreen> {
  bool _loading = true;
  bool _downloadingPdf = false;
  String _error = '';
  Map<String, dynamic> _detail = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
      _detail = {};
    });
    try {
      final data = await _fetchDetail();
      if (!mounted) return;
      setState(() => _detail = data);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<Map<String, dynamic>> _fetchDetail() {
    switch (widget.transactionKey) {
      case 'diamond_issue':
        return widget.api.fetchDiamondIssueDetail(widget.id);
      case 'diamond_return':
        return widget.api.fetchDiamondReturnDetail(widget.id);
      case 'diamond_purchase':
        return widget.api.fetchDiamondPurchaseDetail(widget.id);
      case 'gold_issue':
        return widget.api.fetchGoldIssueDetail(widget.id);
      case 'gold_return':
        return widget.api.fetchGoldReturnDetail(widget.id);
      case 'gold_purchase':
        return widget.api.fetchGoldPurchaseDetail(widget.id);
      case 'stone_issue':
        return widget.api.fetchStoneIssueDetail(widget.id);
      case 'stone_return':
        return widget.api.fetchStoneReturnDetail(widget.id);
      case 'stone_purchase':
        return widget.api.fetchStonePurchaseDetail(widget.id);
      default:
        return Future.value({});
    }
  }

  Future<void> _downloadPdf(BuildContext context, String path, String url) async {
    if (_downloadingPdf) {
      return;
    }

    setState(() => _downloadingPdf = true);
    try {
      final targetUrl = url.trim().isNotEmpty ? url.trim() : '${widget.api.baseUrl}${path.trim()}';
      final result = await launchUrl(
        Uri.parse(targetUrl),
        mode: LaunchMode.externalApplication,
      );
      if (!context.mounted) {
        return;
      }
      if (!result) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to open PDF download.')),
        );
      }
    } catch (e) {
      if (!context.mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    } finally {
      if (mounted) {
        setState(() => _downloadingPdf = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: Text(widget.title)),
        body: const FullScreenLoader(message: 'Loading details...'),
      );
    }
    if (_error.isNotEmpty) {
      return Scaffold(
        appBar: AppBar(title: Text(widget.title)),
        body: AppErrorState(message: _error, onRetry: _load),
      );
    }

    final header = _headerData();
    final lines = (_detail['lines'] as List?) ?? [];
    final issueLines = (_detail['issue_lines'] as List?) ?? [];
    final totals = (_detail['totals'] as Map?)?.cast<String, dynamic>() ?? {};
    final docPath = (_detail['document_path'] ?? '').toString();
    final docUrl = (_detail['document_url'] ?? '').toString();

    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          _buildHeaderCard(header),
          const SizedBox(height: AppSpacing.lg),
          if (totals.isNotEmpty) ...[
            _buildTotalsCard(totals),
            const SizedBox(height: AppSpacing.lg),
          ],
          const AppSectionTitle('Line Items'),
          const SizedBox(height: AppSpacing.md),
          if (lines.isEmpty)
            const AppEmptyState(
              title: 'No lines found',
              message: 'No line items are available for this transaction.',
            )
          else
            ...lines.map((row) => _lineCard(row)).toList(),
          if (issueLines.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.lg),
            const AppSectionTitle('Issued Lines (Reference)'),
            const SizedBox(height: AppSpacing.md),
            ...issueLines.map((row) => _lineCard(row)).toList(),
          ],
          const SizedBox(height: AppSpacing.lg),
          const AppSectionTitle('Document'),
          const SizedBox(height: AppSpacing.md),
          if (docUrl.isEmpty)
            const AppEmptyState(
              title: 'No document',
              message: 'No PDF document available for this entry.',
            )
          else
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: _downloadingPdf ? null : () => _downloadPdf(context, docPath, docUrl),
                icon: _downloadingPdf
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.download),
                label: Text(_downloadingPdf ? 'Downloading...' : 'Download PDF'),
              ),
            ),
        ],
      ),
    );
  }

  Map<String, dynamic> _headerData() {
    final issue = _detail['issue'];
    if (issue is Map) {
      return issue.cast<String, dynamic>();
    }
    final ret = _detail['return'];
    if (ret is Map) {
      return ret.cast<String, dynamic>();
    }
    final purchase = _detail['purchase'];
    if (purchase is Map) {
      return purchase.cast<String, dynamic>();
    }
    return {};
  }

  Widget _buildHeaderCard(Map<String, dynamic> header) {
    final voucher = header['voucher_no']?.toString() ?? '';
    final date =
        header['issue_date'] ??
        header['return_date'] ??
        header['purchase_date'] ??
        header['created_at'] ??
        '-';
    final party =
        header['issue_to'] ??
        header['return_from'] ??
        header['supplier_name'] ??
        header['karigar_name'] ??
        '-';
    final purpose = header['purpose'] ?? header['invoice_no'] ?? '-';

    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.soft,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  voucher.isNotEmpty
                      ? voucher
                      : '${widget.title} #${header['id'] ?? ''}',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              AppStatusBadge(
                label: widget.title.split(' ').last,
                color: widget.accentColor,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          _metaRow('Date', date.toString()),
          _metaRow('Party', party.toString()),
          _metaRow('Ref', purpose.toString()),
          if ((header['order_no'] ?? '').toString().isNotEmpty)
            _metaRow('Order', header['order_no'].toString()),
        ],
      ),
    );
  }

  Widget _buildTotalsCard(Map<String, dynamic> totals) {
    final cards = <Widget>[];
    if (totals.containsKey('total_pcs')) {
      cards.add(_statTile('PCS', AppFormatters.quantity(totals['total_pcs'])));
    }
    if (totals.containsKey('total_carat')) {
      cards.add(_statTile('CTS', AppFormatters.quantity(totals['total_carat'])));
    }
    if (totals.containsKey('total_weight')) {
      cards.add(_statTile('Weight', AppFormatters.quantity(totals['total_weight'])));
    }
    if (totals.containsKey('total_fine')) {
      cards.add(_statTile('Fine', AppFormatters.quantity(totals['total_fine'])));
    }
    if (totals.containsKey('total_qty')) {
      cards.add(_statTile('Qty', AppFormatters.quantity(totals['total_qty'])));
    }
    if (totals.containsKey('total_value')) {
      cards.add(_statTile('Value', AppFormatters.amount(totals['total_value'])));
    }

    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            widget.accentColor.withValues(alpha: 0.10),
            Colors.white,
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: widget.accentColor.withValues(alpha: 0.20)),
        boxShadow: AppShadows.soft,
      ),
      child: Wrap(
        spacing: AppSpacing.md,
        runSpacing: AppSpacing.md,
        children: cards,
      ),
    );
  }

  Widget _statTile(String label, String value) {
    return Container(
      width: 140,
      padding: const EdgeInsets.all(AppSpacing.md),
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
              fontSize: 12,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            value,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }

  Widget _lineCard(dynamic raw) {
    final row = (raw as Map).cast<String, dynamic>();
    final label = _lineTitle(row);

    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: AppSpacing.xs),
          Wrap(
            spacing: AppSpacing.md,
            runSpacing: AppSpacing.xs,
            children: _lineMeta(row)
                .map(
                  (entry) => Text(
                    '${entry.key}: ${entry.value}',
                    style: const TextStyle(color: AppColors.textSecondary),
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }

  String _lineTitle(Map<String, dynamic> row) {
    final diamond = row['diamond_type'] ?? '';
    if (diamond.toString().isNotEmpty) {
      final shape = row['shape'] ?? '';
      final chalniFrom = row['chalni_from'] ?? '';
      final chalniTo = row['chalni_to'] ?? '';
      final chalni = chalniFrom.toString().isNotEmpty
          ? '${chalniFrom}-${chalniTo}'
          : '';
      return [
        diamond,
        shape,
        chalni,
      ].where((e) => e.toString().isNotEmpty).join(' ');
    }
    final purity = row['purity_code'] ?? '';
    if (purity.toString().isNotEmpty) {
      final color = row['color_name'] ?? '';
      final form = row['form_type'] ?? '';
      return [
        purity,
        color,
        form,
      ].where((e) => e.toString().isNotEmpty).join(' ');
    }
    return row['product_name']?.toString() ?? 'Item';
  }

  List<MapEntry<String, String>> _lineMeta(Map<String, dynamic> row) {
    final List<MapEntry<String, String>> meta = [];
    if (row.containsKey('pcs')) {
      meta.add(MapEntry('PCS', AppFormatters.quantity(row['pcs'])));
    }
    if (row.containsKey('carat')) {
      meta.add(MapEntry('CTS', AppFormatters.quantity(row['carat'])));
    }
    if (row.containsKey('weight_gm')) {
      meta.add(MapEntry('Weight', AppFormatters.quantity(row['weight_gm'])));
    }
    if (row.containsKey('fine_weight_gm')) {
      meta.add(MapEntry('Fine', AppFormatters.quantity(row['fine_weight_gm'])));
    }
    if (row.containsKey('qty')) {
      meta.add(MapEntry('Qty', AppFormatters.quantity(row['qty'])));
    }
    if (row.containsKey('rate_per_carat')) {
      meta.add(MapEntry('Rate', AppFormatters.amount(row['rate_per_carat'])));
    }
    if (row.containsKey('rate_per_gm')) {
      meta.add(MapEntry('Rate', AppFormatters.amount(row['rate_per_gm'])));
    }
    if (row.containsKey('rate')) {
      meta.add(MapEntry('Rate', AppFormatters.amount(row['rate'])));
    }
    if (row.containsKey('line_value')) {
      meta.add(MapEntry('Value', AppFormatters.amount(row['line_value'])));
    }
    return meta;
  }

  Widget _metaRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.xs),
      child: Row(
        children: [
          SizedBox(
            width: 90,
            child: Text(
              label,
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }
}
