import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class DeliveryChallansScreen extends StatefulWidget {
  const DeliveryChallansScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<DeliveryChallansScreen> createState() => _DeliveryChallansScreenState();
}

class _DeliveryChallansScreenState extends State<DeliveryChallansScreen> {
  bool _loading = true;
  String _error = '';
  List<dynamic> _rows = [];

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
      final rows = await widget.api.fetchDeliveryChallans();
      if (mounted) setState(() => _rows = rows);
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _download(Map<String, dynamic> row) async {
    try {
      await widget.api.downloadPdf(
        path: '/api/mobile/delivery-challans/${row['id']}/pdf',
        fileName: 'delivery_challan_${row['challan_no'] ?? row['id']}.pdf',
      );
      if (mounted) _message('Delivery challan PDF downloaded.');
    } catch (e) {
      if (mounted) _message(e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> openCreate() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => DeliveryChallanCreateScreen(api: widget.api),
      ),
    );
    if (created == true) await _load();
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
          Container(
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
                    LucideIcons.scroll_text,
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
                        'DELIVERY REGISTER',
                        style: TextStyle(
                          color: AppColors.brandGold,
                          fontSize: 11,
                          letterSpacing: 1.6,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      SizedBox(height: 5),
                      Text(
                        'Delivery Challans',
                        style: TextStyle(
                          color: Colors.white,
                          fontFamily: 'CormorantGaramond',
                          fontSize: 25,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  '${_rows.length}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          if (_rows.isEmpty)
            const AppEmptyState(
              title: 'No delivery challans',
              message: 'Create the first ornament or loose material challan.',
            )
          else
            ..._rows.map((raw) {
              final row = _map(raw);
              return Card(
                margin: const EdgeInsets.only(bottom: 14),
                child: Padding(
                  padding: const EdgeInsets.all(17),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: AppColors.paleGold,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: const Icon(
                              LucideIcons.file_text,
                              color: AppColors.plum,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${row['challan_no'] ?? '-'}',
                                  style: Theme.of(
                                    context,
                                  ).textTheme.titleMedium,
                                ),
                                Text(
                                  '${row['display_customer'] ?? 'Customer'} · ${row['dispatch_from'] ?? 'Company'}',
                                  style: const TextStyle(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton.filledTonal(
                            tooltip: 'Download PDF',
                            onPressed: () => _download(row),
                            icon: const Icon(LucideIcons.file_down),
                          ),
                        ],
                      ),
                      const Divider(height: 26),
                      Row(
                        children: [
                          Expanded(
                            child: _metric(
                              'CONTENTS',
                              '${row['material_types'] ?? 'Ornament'}',
                            ),
                          ),
                          Expanded(
                            child: _metric(
                              'DATE',
                              AppFormatters.date(row['challan_date']),
                            ),
                          ),
                          Expanded(
                            child: _metric(
                              'GROSS TOTAL',
                              '₹${AppFormatters.amount(row['total_amount'])}',
                              gold: true,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            }),
          const SizedBox(height: 90),
        ],
      ),
    );
  }

  Widget _metric(String label, String value, {bool gold = false}) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: const TextStyle(
          color: AppColors.textSecondary,
          fontSize: 9,
          fontWeight: FontWeight.w800,
          letterSpacing: .7,
        ),
      ),
      const SizedBox(height: 4),
      Text(
        value,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: gold ? AppColors.gold : AppColors.textPrimary,
          fontWeight: FontWeight.w800,
          fontSize: 13,
        ),
      ),
    ],
  );

  void _message(String text) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }
}

class DeliveryChallanCreateScreen extends StatefulWidget {
  const DeliveryChallanCreateScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<DeliveryChallanCreateScreen> createState() =>
      _DeliveryChallanCreateScreenState();
}

class _DeliveryChallanCreateScreenState
    extends State<DeliveryChallanCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _notes = TextEditingController();
  final List<_ChallanLine> _lines = [_ChallanLine()];
  bool _loading = true;
  bool _saving = false;
  String _error = '';
  String _nextNumber = '';
  String? _branch;
  int? _customerId;
  double _gst = 3;
  DateTime _date = DateTime.now();
  List<dynamic> _branches = [];
  List<dynamic> _customers = [];
  List<dynamic> _gstRates = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _notes.dispose();
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await widget.api.fetchDeliveryChallanForm();
      if (!mounted) return;
      setState(() {
        _nextNumber = '${data['next_challan_no'] ?? ''}';
        _branches = _list(data['branches']);
        _customers = _list(data['customers']);
        _gstRates = _list(data['gst_rates']);
        _loading = false;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = e.toString().replaceFirst('Exception: ', '');
        });
      }
    }
  }

  double get _taxable => _lines.fold(
    0,
    (sum, line) => sum + (double.tryParse(line.value.text) ?? 0),
  );

  Future<void> _submit() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) return;
    if (_branch == null || _customerId == null) {
      _message('Select dispatch branch and customer.');
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.api.createDeliveryChallan({
        'challan_date': _dateText(_date),
        'dispatch_from': _branch,
        'customer_id': _customerId,
        'tax_percent': _gst,
        'notes': _notes.text.trim(),
        'items': _lines.map((line) => line.payload).toList(),
      });
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) _message(e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Create Delivery Challan')),
      body: _loading
          ? const FullScreenLoader()
          : _error.isNotEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [AppColors.plum, Color(0xFF7B2947)],
                      ),
                      borderRadius: BorderRadius.circular(AppRadius.xl),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          LucideIcons.crown,
                          color: AppColors.brandGold,
                          size: 30,
                        ),
                        const SizedBox(width: 14),
                        const Expanded(
                          child: Text(
                            'DELIVERY CHALLAN',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 1.2,
                            ),
                          ),
                        ),
                        Text(
                          _nextNumber,
                          style: const TextStyle(
                            color: AppColors.brandGold,
                            fontWeight: FontWeight.w900,
                            fontSize: 20,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  _section(
                    'Dispatch & Customer',
                    LucideIcons.map_pinned,
                    Column(
                      children: [
                        DropdownButtonFormField<String>(
                          initialValue: _branch,
                          isExpanded: true,
                          decoration: const InputDecoration(
                            labelText: 'Dispatch From *',
                            border: OutlineInputBorder(),
                          ),
                          items: _branches.map((raw) {
                            final row = _map(raw);
                            return DropdownMenuItem<String>(
                              value: '${row['key']}',
                              child: Text(
                                '${row['name']} · ${row['address']}',
                                overflow: TextOverflow.ellipsis,
                              ),
                            );
                          }).toList(),
                          validator: (value) => value == null
                              ? 'Select Mumbai or Hyderabad'
                              : null,
                          onChanged: (value) => setState(() => _branch = value),
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          initialValue: _customerId,
                          isExpanded: true,
                          decoration: const InputDecoration(
                            labelText: 'Customer *',
                            border: OutlineInputBorder(),
                          ),
                          items: _customers.map((raw) {
                            final row = _map(raw);
                            return DropdownMenuItem<int>(
                              value: _int(row['id']),
                              child: Text(
                                '${row['name']} · ${row['address'] ?? ''}',
                                overflow: TextOverflow.ellipsis,
                              ),
                            );
                          }).toList(),
                          validator: (value) =>
                              value == null ? 'Select customer' : null,
                          onChanged: (value) =>
                              setState(() => _customerId = value),
                        ),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: () async {
                            final value = await showDatePicker(
                              context: context,
                              initialDate: _date,
                              firstDate: DateTime.now().subtract(
                                const Duration(days: 90),
                              ),
                              lastDate: DateTime.now().add(
                                const Duration(days: 90),
                              ),
                            );
                            if (value != null) setState(() => _date = value);
                          },
                          icon: const Icon(LucideIcons.calendar_days),
                          label: Text('Challan Date · ${_dateText(_date)}'),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  _section(
                    'Challan Contents',
                    LucideIcons.gem,
                    Column(
                      children: [
                        ...List.generate(_lines.length, _lineCard),
                        OutlinedButton.icon(
                          onPressed: () =>
                              setState(() => _lines.add(_ChallanLine())),
                          icon: const Icon(LucideIcons.plus),
                          label: const Text('Add Another Item'),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  _section(
                    'Value & GST',
                    LucideIcons.indian_rupee,
                    Column(
                      children: [
                        DropdownButtonFormField<double>(
                          initialValue: _gst,
                          decoration: const InputDecoration(
                            labelText: 'GST Rate',
                            border: OutlineInputBorder(),
                          ),
                          items: _gstRates.map((raw) {
                            final rate = _double(raw);
                            return DropdownMenuItem<double>(
                              value: rate,
                              child: Text('${rate.toStringAsFixed(0)}%'),
                            );
                          }).toList(),
                          onChanged: (value) =>
                              setState(() => _gst = value ?? 0),
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _notes,
                          maxLines: 3,
                          decoration: const InputDecoration(
                            labelText: 'Notes / Purpose',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        const SizedBox(height: 14),
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: AppColors.plum,
                            borderRadius: BorderRadius.circular(AppRadius.lg),
                          ),
                          child: Column(
                            children: [
                              _totalRow('Item Value', _taxable),
                              _totalRow('GST', _taxable * _gst / 100),
                              const Divider(color: Colors.white24),
                              _totalRow(
                                'GROSS TOTAL',
                                _taxable * (1 + _gst / 100),
                                grand: true,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  FilledButton.icon(
                    onPressed: _saving ? null : _submit,
                    icon: _saving
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(LucideIcons.file_check_2),
                    label: const Text('Create Delivery Challan'),
                  ),
                  const SizedBox(height: 32),
                ],
              ),
            ),
    );
  }

  Widget _lineCard(int index) {
    final line = _lines[index];
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      color: const Color(0xFFFFFCF6),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Item ${index + 1}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                if (_lines.length > 1)
                  IconButton(
                    onPressed: () => setState(() {
                      _lines.removeAt(index).dispose();
                    }),
                    icon: const Icon(LucideIcons.trash_2),
                  ),
              ],
            ),
            DropdownButtonFormField<String>(
              initialValue: line.type,
              decoration: const InputDecoration(
                labelText: 'Item Type *',
                border: OutlineInputBorder(),
              ),
              items: const [
                DropdownMenuItem(value: 'ornament', child: Text('Ornament')),
                DropdownMenuItem(
                  value: 'loose_diamond',
                  child: Text('Loose Diamond'),
                ),
                DropdownMenuItem(
                  value: 'loose_gold',
                  child: Text('Loose Gold / Metal'),
                ),
              ],
              onChanged: (value) => setState(() => line.type = value!),
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: line.description,
              decoration: const InputDecoration(
                labelText: 'Description',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 10),
            if (line.type != 'loose_diamond') ...[
              DropdownButtonFormField<String>(
                initialValue: line.purity,
                decoration: const InputDecoration(
                  labelText: 'Purity *',
                  border: OutlineInputBorder(),
                ),
                items: const [
                  DropdownMenuItem(value: '14 KT', child: Text('14 KT')),
                  DropdownMenuItem(value: '18 KT', child: Text('18 KT')),
                  DropdownMenuItem(value: '22 KT', child: Text('22 KT')),
                ],
                onChanged: (value) => line.purity = value!,
              ),
              const SizedBox(height: 10),
            ],
            _number(line.pcs, 'PCS *', whole: true),
            if (line.type == 'ornament') ...[
              _number(line.gross, 'Gross Weight (gm) *'),
              _number(line.net, 'Net Weight (gm) *'),
              _number(line.diamond, 'Diamond Weight (ct)'),
              _number(line.stone, 'Stone Weight (ct)'),
              _number(line.other, 'Other Weight (gm)'),
            ] else if (line.type == 'loose_diamond')
              _number(line.diamond, 'Diamond Weight (ct) *')
            else
              _number(line.net, 'Gold Weight (gm) *'),
            _number(line.value, 'Total Value (₹) *', recalculate: true),
          ],
        ),
      ),
    );
  }

  Widget _number(
    TextEditingController controller,
    String label, {
    bool whole = false,
    bool recalculate = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextFormField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
      ),
      validator: (value) {
        final number = double.tryParse(value?.trim() ?? '') ?? 0;
        if (label.endsWith('*') && number <= 0) return 'Enter positive value';
        if (whole && number != number.roundToDouble()) return 'Enter whole PCS';
        return null;
      },
      onChanged: recalculate ? (_) => setState(() {}) : null,
    ),
  );

  Widget _section(String title, IconData icon, Widget child) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      border: Border.all(color: AppColors.border),
      borderRadius: BorderRadius.circular(AppRadius.xl),
      boxShadow: AppShadows.soft,
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, color: AppColors.gold, size: 20),
            const SizedBox(width: 9),
            Text(title, style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
        const SizedBox(height: 16),
        child,
      ],
    ),
  );

  Widget _totalRow(String label, double amount, {bool grand = false}) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Expanded(
              child: Text(label, style: const TextStyle(color: Colors.white)),
            ),
            Text(
              '₹${AppFormatters.amount(amount)}',
              style: TextStyle(
                color: grand ? AppColors.brandGold : Colors.white,
                fontWeight: FontWeight.w900,
                fontSize: grand ? 20 : 14,
              ),
            ),
          ],
        ),
      );

  void _message(String value) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }
}

class _ChallanLine {
  String type = 'ornament';
  String purity = '18 KT';
  final description = TextEditingController();
  final pcs = TextEditingController(text: '1');
  final gross = TextEditingController();
  final net = TextEditingController();
  final diamond = TextEditingController();
  final stone = TextEditingController();
  final other = TextEditingController();
  final value = TextEditingController();

  Map<String, dynamic> get payload => {
    'item_type': type,
    'description': description.text.trim(),
    'purity': purity,
    'pcs': int.tryParse(pcs.text) ?? 0,
    'gross_weight_gm': double.tryParse(gross.text) ?? 0,
    'net_weight_gm': double.tryParse(net.text) ?? 0,
    'diamond_weight_cts': double.tryParse(diamond.text) ?? 0,
    'stone_weight_cts': double.tryParse(stone.text) ?? 0,
    'other_weight_gm': double.tryParse(other.text) ?? 0,
    'value': double.tryParse(value.text) ?? 0,
  };

  void dispose() {
    for (final controller in [
      description,
      pcs,
      gross,
      net,
      diamond,
      stone,
      other,
      value,
    ]) {
      controller.dispose();
    }
  }
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? value.cast<String, dynamic>() : <String, dynamic>{};
List<dynamic> _list(dynamic value) => value is List ? value : <dynamic>[];
int _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
double _double(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
String _dateText(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
