import 'dart:convert';

import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/services/app_image_picker.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_photo_field.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:image_picker/image_picker.dart';

class OrderReceivingScreen extends StatefulWidget {
  const OrderReceivingScreen({
    super.key,
    required this.api,
    required this.orderId,
  });

  final MobileApiService api;
  final int orderId;

  @override
  State<OrderReceivingScreen> createState() => _OrderReceivingScreenState();
}

class _OrderReceivingScreenState extends State<OrderReceivingScreen> {
  final _form = GlobalKey<FormState>();
  final _gross = TextEditingController();
  final _goldRate = TextEditingController();
  final _labourRate = TextEditingController(text: '0');
  final _wastage = TextEditingController(text: '0');
  final _notes = TextEditingController();
  final List<_ComponentLine> _diamonds = [];
  final List<_ComponentLine> _stones = [];
  final List<_ComponentLine> _others = [];
  Map<String, dynamic> _order = {};
  List<Map<String, dynamic>> _locations = [];
  List<Map<String, dynamic>> _purities = [];
  int? _locationId;
  int? _purityId;
  bool _loading = true;
  bool _saving = false;
  String _error = '';
  XFile? _photo;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final controller in [
      _gross,
      _goldRate,
      _labourRate,
      _wastage,
      _notes,
    ]) {
      controller.dispose();
    }
    for (final row in [..._diamonds, ..._stones, ..._others]) {
      row.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await widget.api.fetchOrderReceiving(widget.orderId);
      if (!mounted) return;
      final received =
          data['already_received'] == true || data['already_received'] == 1;
      if (received) {
        setState(() {
          _loading = false;
          _error = 'This order has already been received.';
        });
        return;
      }
      setState(() {
        _order = _map(data['order']);
        _locations = _maps(data['locations']);
        _purities = _maps(data['gold_purities']);
        _locationId = _locations.isEmpty ? null : _int(_locations.first['id']);
        _purityId = _purities.isEmpty ? null : _int(_purities.first['id']);
        final rate = _number(_order['gold_rate_per_gm']);
        if (rate > 0) _goldRate.text = rate.toStringAsFixed(2);
        _loading = false;
      });
    } catch (e) {
      if (mounted)
        setState(() {
          _loading = false;
          _error = _message(e);
        });
    }
  }

  Future<void> _pickPhoto() async {
    final file = await AppImagePicker().pickImage(
      context,
      title: 'Finished jewellery photo',
      allowGallery: true,
    );
    if (mounted && file != null) setState(() => _photo = file);
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false) || _saving) return;
    setState(() => _saving = true);
    try {
      final photo = _photo == null
          ? ''
          : base64Encode(await _photo!.readAsBytes());
      await widget.api.receiveOrder(widget.orderId, {
        'location_id': _locationId,
        'gold_purity_id': _purityId,
        'gross_weight_gm': _decimal(_gross),
        'gold_rate_per_gm': _decimal(_goldRate),
        'labour_rate_per_gm': _decimal(_labourRate),
        'wastage_percent': _decimal(_wastage),
        'notes': _notes.text.trim(),
        if (photo.isNotEmpty) 'finish_photo_base64': photo,
        'diamonds': _diamonds.map((row) => row.json(false)).toList(),
        'stones': _stones.map((row) => row.json(false)).toList(),
        'others': _others.map((row) => row.json(true)).toList(),
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Finished jewellery received successfully.'),
        ),
      );
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(_message(e))));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Receive ${_order['order_no'] ?? 'Order'}')),
      body: _loading
          ? const FullScreenLoader(message: 'Preparing receiving...')
          : _error.isNotEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : Form(
              key: _form,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
                children: [
                  _hero(),
                  const SizedBox(height: 24),
                  const AppSectionTitle('Actual finished weight'),
                  const SizedBox(height: 12),
                  _fields(),
                  const SizedBox(height: 24),
                  _componentSection(
                    'Diamonds',
                    _diamonds,
                    LucideIcons.diamond,
                    false,
                  ),
                  const SizedBox(height: 20),
                  _componentSection(
                    'Colour stones',
                    _stones,
                    LucideIcons.sparkles,
                    false,
                  ),
                  const SizedBox(height: 20),
                  _componentSection(
                    'Other materials',
                    _others,
                    LucideIcons.box,
                    true,
                  ),
                  const SizedBox(height: 24),
                  AppPhotoField(
                    file: _photo,
                    title: 'Finished jewellery photo',
                    subtitle:
                        'Optional proof for receiving and finished inventory',
                    busy: false,
                    enabled: !_saving,
                    onPick: _pickPhoto,
                    onRemove: () => setState(() => _photo = null),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _notes,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Receiving notes',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 24),
                  FilledButton.icon(
                    onPressed: _saving ? null : _submit,
                    icon: _saving
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(LucideIcons.package_check),
                    label: Text(
                      _saving
                          ? 'Posting receiving...'
                          : 'Receive & create inventory',
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _hero() => Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [AppColors.plum, Color(0xFF71455B)],
      ),
      borderRadius: BorderRadius.circular(AppRadius.xl),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'INDEPENDENT RECEIVING',
          style: TextStyle(
            color: AppColors.brandGold,
            fontSize: 11,
            fontWeight: FontWeight.w800,
            letterSpacing: 1.4,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          '${_order['order_no'] ?? ''}',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 24,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          '${_order['customer_name'] ?? 'Customer'} · ${_order['karigar_name'] ?? 'Karigar'}',
          style: const TextStyle(color: Colors.white70),
        ),
        const SizedBox(height: 12),
        const Text(
          'Completing a follow-up does not receive stock. Post actual weights here.',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
        ),
      ],
    ),
  );

  Widget _fields() => LayoutBuilder(
    builder: (context, box) {
      final fields = <Widget>[
        DropdownButtonFormField<int>(
          initialValue: _locationId,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Receiving location *'),
          items: _locations
              .map(
                (row) => DropdownMenuItem(
                  value: _int(row['id']),
                  child: Text('${row['name']}'),
                ),
              )
              .toList(),
          onChanged: (value) => setState(() => _locationId = value),
          validator: (value) => value == null ? 'Select location' : null,
        ),
        DropdownButtonFormField<int>(
          initialValue: _purityId,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Actual purity *'),
          items: _purities
              .map(
                (row) => DropdownMenuItem(
                  value: _int(row['id']),
                  child: Text(
                    '${row['purity_code']} · ${row['purity_percent']}%',
                  ),
                ),
              )
              .toList(),
          onChanged: (value) => setState(() => _purityId = value),
          validator: (value) => value == null ? 'Select purity' : null,
        ),
        _numberField(_gross, 'Gross weight (gm) *', positive: true),
        _numberField(_goldRate, 'Gold rate / gm *', positive: true),
        _numberField(_labourRate, 'Labour rate / gm'),
        _numberField(_wastage, 'Wastage %', max: 100),
      ];
      if (box.maxWidth < 680)
        return Column(
          children: fields
              .map(
                (field) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: field,
                ),
              )
              .toList(),
        );
      return Wrap(
        spacing: 12,
        runSpacing: 12,
        children: fields
            .map(
              (field) => SizedBox(width: (box.maxWidth - 12) / 2, child: field),
            )
            .toList(),
      );
    },
  );

  Widget _numberField(
    TextEditingController controller,
    String label, {
    bool positive = false,
    double? max,
  }) => TextFormField(
    controller: controller,
    keyboardType: const TextInputType.numberWithOptions(decimal: true),
    decoration: InputDecoration(labelText: label),
    validator: (value) {
      final amount = double.tryParse(value ?? '') ?? 0;
      if (positive && amount <= 0) return 'Enter a positive value';
      if (amount < 0 || (max != null && amount > max))
        return 'Enter a valid value';
      return null;
    },
  );

  Widget _componentSection(
    String title,
    List<_ComponentLine> rows,
    IconData icon,
    bool grams,
  ) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(icon, color: AppColors.brandGold),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              TextButton.icon(
                onPressed: () => setState(() => rows.add(_ComponentLine())),
                icon: const Icon(Icons.add),
                label: const Text('Add'),
              ),
            ],
          ),
          if (rows.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text(
                'No component added',
                style: TextStyle(color: AppColors.textSecondary),
              ),
            ),
          ...rows.asMap().entries.map(
            (entry) => _componentRow(rows, entry.key, entry.value, grams),
          ),
        ],
      ),
    ),
  );

  Widget _componentRow(
    List<_ComponentLine> rows,
    int index,
    _ComponentLine row,
    bool grams,
  ) => Container(
    margin: const EdgeInsets.only(top: 10),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: AppColors.background,
      borderRadius: BorderRadius.circular(AppRadius.md),
      border: Border.all(color: AppColors.border),
    ),
    child: Column(
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Line ${index + 1}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
            IconButton(
              onPressed: () {
                row.dispose();
                setState(() => rows.removeAt(index));
              },
              icon: const Icon(Icons.close, color: AppColors.danger),
            ),
          ],
        ),
        TextFormField(
          controller: row.name,
          decoration: const InputDecoration(labelText: 'Description'),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(child: _numberField(row.pcs, 'PCS')),
            const SizedBox(width: 10),
            Expanded(
              child: _numberField(
                grams ? row.weightGm : row.weightCts,
                grams ? 'Weight gm *' : 'Weight cts *',
                positive: true,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _numberField(row.rate, grams ? 'Rate / gm' : 'Rate / ct'),
            ),
          ],
        ),
      ],
    ),
  );

  double _decimal(TextEditingController controller) =>
      double.tryParse(controller.text.trim()) ?? 0;
}

class _ComponentLine {
  final name = TextEditingController();
  final pcs = TextEditingController(text: '0');
  final weightCts = TextEditingController(text: '0');
  final weightGm = TextEditingController(text: '0');
  final rate = TextEditingController(text: '0');
  Map<String, dynamic> json(bool grams) => {
    'name': name.text.trim(),
    'pcs': double.tryParse(pcs.text) ?? 0,
    'weight_cts': grams ? 0 : double.tryParse(weightCts.text) ?? 0,
    'weight_gm': grams ? double.tryParse(weightGm.text) ?? 0 : 0,
    'rate': double.tryParse(rate.text) ?? 0,
  };
  void dispose() {
    name.dispose();
    pcs.dispose();
    weightCts.dispose();
    weightGm.dispose();
    rate.dispose();
  }
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? value.cast<String, dynamic>() : <String, dynamic>{};
List<Map<String, dynamic>> _maps(dynamic value) => (value as List? ?? const [])
    .whereType<Map>()
    .map((row) => row.cast<String, dynamic>())
    .toList();
int _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
double _number(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
String _message(Object error) =>
    error.toString().replaceFirst('Exception: ', '');
