import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutter/material.dart';

/// Every order field the web admin shows, grouped into collapsible sections
/// (customer & sales, production, payment, notes, order-wise stone issues).
class OrderFullDetails extends StatelessWidget {
  const OrderFullDetails({
    super.key,
    required this.order,
    this.stoneIssues = const [],
    this.isReceived = false,
  });

  final Map<String, dynamic> order;
  final List<dynamic> stoneIssues;
  final bool isReceived;

  String _text(String key, [String fallback = '-']) {
    final value = '${order[key] ?? ''}'.trim();
    return value.isEmpty || value == 'null' ? fallback : value;
  }

  double _num(String key) {
    final value = order[key];
    return value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
  }

  String _date(String key) {
    final value = _text(key, '');
    return value.isEmpty ? '-' : AppFormatters.date(value);
  }

  String _money(String key, {bool blankWhenEmpty = false}) {
    final raw = '${order[key] ?? ''}'.trim();
    if (blankWhenEmpty && (raw.isEmpty || raw == 'null')) return '-';
    return '₹${AppFormatters.amount(_num(key))}';
  }

  @override
  Widget build(BuildContext context) {
    final approx = _num('approximate_price');
    final advance = _num('advance_amount');
    final balance = (approx - advance) < 0 ? 0 : approx - advance;
    final goldRate = _num('gold_rate_per_gm');
    final customerPhone = _text('customer_phone', '');
    final contact = _text('contact_number', '');
    final karigarPhone = _text('karigar_phone', '');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _Section(
          icon: Icons.receipt_long_outlined,
          title: 'Order information',
          summary: '${_text('order_no')} · ${_text('status')}',
          initiallyExpanded: true,
          rows: [
            _Row('Order no', _text('order_no')),
            _Row('Order name', _text('order_name')),
            _Row('Jewellery category', _text('order_category_name')),
            _Row('Material category', _text('material_category')),
            _Row('Order type', _text('order_type')),
            _Row('Design type', _text('order_design_type', 'Fresh')),
            _Row('Priority', _text('priority')),
            _Row('Status', _text('status')),
            _Row('Order from', _text('order_from')),
            _Row('Order received', _date('order_received_date')),
            _Row('Created on', _date('created_at')),
          ],
        ),
        _Section(
          icon: Icons.person_outline,
          title: 'Customer & sales',
          summary: [
            _text('customer_name', ''),
            _text('sales_person_name', ''),
          ].where((part) => part.isNotEmpty).join(' · '),
          rows: [
            _Row('Customer', _text('customer_name')),
            _Row('Customer phone', customerPhone.isEmpty ? '-' : customerPhone),
            _Row('Contact number', contact.isEmpty ? '-' : contact),
            _Row('Customer email', _text('customer_email')),
            _Row('Sales person', _text('sales_person_name')),
            _Row('Sales mobile', _text('sales_person_mobile')),
          ],
        ),
        _Section(
          icon: Icons.handyman_outlined,
          title: 'Production & follow-up',
          summary: 'Delivery ${_date('due_date')}',
          rows: [
            _Row(
              'Assigned karigar',
              _text('karigar_name', 'Not assigned') +
                  (karigarPhone.isEmpty ? '' : ' · $karigarPhone'),
            ),
            _Row('Order follower', _text('follower_name', 'Not assigned')),
            _Row('Next follow-up due', _date('followup_due_at')),
            _Row('Client delivery date', _date('due_date')),
            _Row(
              'Receiving status',
              isReceived ? 'Received' : 'Pending receiving',
            ),
          ],
        ),
        _Section(
          icon: Icons.payments_outlined,
          title: 'Payment & commercials',
          summary:
              'Advance ₹${AppFormatters.amount(advance)} · Balance ₹${AppFormatters.amount(balance)}',
          rows: [
            _Row(
              'Approximate price',
              _money('approximate_price', blankWhenEmpty: true),
            ),
            _Row('Advance amount', _money('advance_amount')),
            _Row(
              'Approx. balance',
              '₹${AppFormatters.amount(balance)}',
              emphasize: true,
            ),
            _Row(
              'Gold rate block',
              _text('gold_rate_block_status', 'Not Fixed') +
                  (goldRate > 0
                      ? ' · ₹${AppFormatters.amount(goldRate)}/gm'
                      : ''),
            ),
            _Row(
              'Certificate',
              _text('certificate_requirement', 'Not required'),
            ),
          ],
        ),
        _Section(
          icon: Icons.sticky_note_2_outlined,
          title: 'Notes & instructions',
          summary: _text('order_notes', '').isEmpty
              ? 'No notes'
              : 'Notes recorded',
          rows: [
            _Row(
              'Order notes',
              _text('order_notes', 'No additional notes recorded.'),
              block: true,
            ),
            _Row(
              'Additional details / finish',
              _text('additional_details'),
              block: true,
            ),
          ],
        ),
        _Section(
          icon: Icons.scatter_plot_outlined,
          title: 'Stone issued for this order',
          summary: stoneIssues.isEmpty
              ? 'No stone issued yet'
              : '${stoneIssues.length} issue${stoneIssues.length == 1 ? '' : 's'}',
          rows: stoneIssues.isEmpty
              ? const [
                  _Row(
                    'Stone',
                    'No stone has been issued against this order yet.',
                    block: true,
                  ),
                ]
              : stoneIssues.whereType<Map>().map((raw) {
                  final row = raw.cast<String, dynamic>();
                  final voucher = '${row['voucher_no'] ?? ''}'.trim();
                  return _Row(
                    voucher.isEmpty ? 'Issue #${row['id']}' : voucher,
                    '${AppFormatters.date(row['issue_date'])} · ${row['karigar_name'] ?? '-'}\n'
                    'PCS ${AppFormatters.quantity(row['total_pcs'])} · Qty ${AppFormatters.quantity(row['total_qty'])} · ₹${AppFormatters.amount(row['total_value'])}',
                    block: true,
                  );
                }).toList(),
        ),
      ],
    );
  }
}

class _Row {
  const _Row(
    this.label,
    this.value, {
    this.block = false,
    this.emphasize = false,
  });

  final String label;
  final String value;
  final bool block;
  final bool emphasize;
}

class _Section extends StatelessWidget {
  const _Section({
    required this.icon,
    required this.title,
    required this.summary,
    required this.rows,
    this.initiallyExpanded = false,
  });

  final IconData icon;
  final String title;
  final String summary;
  final List<_Row> rows;
  final bool initiallyExpanded;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        side: const BorderSide(color: AppColors.border),
      ),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          initiallyExpanded: initiallyExpanded,
          maintainState: true,
          tilePadding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: 2,
          ),
          childrenPadding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            0,
            AppSpacing.lg,
            AppSpacing.lg,
          ),
          iconColor: AppColors.plum,
          collapsedIconColor: AppColors.textSecondary,
          leading: Container(
            padding: const EdgeInsets.all(9),
            decoration: BoxDecoration(
              color: AppColors.paleGold,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: AppColors.plum, size: 19),
          ),
          title: Text(
            title,
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
            ),
          ),
          subtitle: summary.isEmpty
              ? null
              : Text(
                  summary,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
          children: [
            const Divider(height: 1, color: AppColors.border),
            const SizedBox(height: AppSpacing.sm),
            ...rows.map(_buildRow),
          ],
        ),
      ),
    );
  }

  Widget _buildRow(_Row row) {
    final label = Text(
      row.label.toUpperCase(),
      style: const TextStyle(
        fontSize: 10.5,
        letterSpacing: 0.6,
        fontWeight: FontWeight.w700,
        color: AppColors.textSecondary,
      ),
    );
    final value = Text(
      row.value,
      style: TextStyle(
        fontSize: 14,
        height: 1.45,
        fontWeight: row.emphasize ? FontWeight.w800 : FontWeight.w600,
        color: row.emphasize ? AppColors.plum : AppColors.textPrimary,
      ),
    );
    if (row.block) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [label, const SizedBox(height: 4), value],
        ),
      );
    }
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 130, child: label),
          const SizedBox(width: AppSpacing.md),
          Expanded(child: value),
        ],
      ),
    );
  }
}
