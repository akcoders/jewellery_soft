import 'dart:convert';

import 'package:flutkit/jewellery_pwa/services/app_image_picker.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_photo_field.dart';

import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_form_section.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

class IssuementCreateScreen extends StatefulWidget {
  const IssuementCreateScreen({
    super.key,
    required this.api,
    this.workRequestId,
    this.initialKarigarId,
  });

  final MobileApiService api;
  final int? workRequestId;
  final int? initialKarigarId;

  @override
  State<IssuementCreateScreen> createState() => _IssuementCreateScreenState();
}

class _IssuementCreateScreenState extends State<IssuementCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _voucher = TextEditingController();
  final _purpose = TextEditingController(text: 'Jobwork');
  final _notes = TextEditingController();
  DateTime _issueDate = DateTime.now();
  int? _karigarId;
  int? _locationId;
  bool _loading = true;
  bool _saving = false;
  bool _pickingPhoto = false;
  String _error = '';
  List<dynamic> _karigars = [];
  List<dynamic> _locations = [];
  List<dynamic> _goldItems = [];
  List<dynamic> _diamondItems = [];
  List<dynamic> _orders = [];
  List<dynamic> _stoneItems = [];
  final List<_IssueLine> _goldLines = [];
  final List<_IssueLine> _diamondLines = [];
  final List<_IssueLine> _stoneLines = [];
  XFile? _attachment;

  @override
  void initState() {
    super.initState();
    _karigarId = widget.initialKarigarId;
    _load();
  }

  @override
  void dispose() {
    _voucher.dispose();
    _purpose.dispose();
    _notes.dispose();
    for (final line in [..._goldLines, ..._diamondLines, ..._stoneLines]) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await Future.wait([
        widget.api.fetchKarigars(),
        widget.api.fetchLocations(),
        widget.api.fetchGoldItems(),
        widget.api.fetchDiamondBagItems(),
        widget.api.fetchDiamondOrderAllocations(),
        widget.api.fetchStoneItems(),
      ]);
      if (!mounted) return;
      setState(() {
        _karigars = data[0];
        _locations = data[1];
        _goldItems = data[2];
        _diamondItems = data[3];
        _orders = data[4];
        _stoneItems = data[5];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = _message(e);
        _loading = false;
      });
    }
  }

  Future<void> _pickAttachment() async {
    if (_pickingPhoto || _saving) return;
    setState(() => _pickingPhoto = true);
    try {
      final image = await AppImagePicker().pickImage(
        context,
        title: 'Issuement attachment',
      );
      if (mounted && image != null) setState(() => _attachment = image);
    } finally {
      if (mounted) setState(() => _pickingPhoto = false);
    }
  }

  Future<void> _addCompleteDiamondBag() async {
    final bags = <int, dynamic>{};
    for (final row in _diamondItems) {
      final bagId = _intValue(row['bag_id']);
      if (bagId > 0) bags.putIfAbsent(bagId, () => row);
    }
    if (bags.isEmpty) {
      _show('No complete diamond bag is available to issue.');
      return;
    }
    int? bagId;
    int? orderId;
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, update) {
          final selected = bagId == null ? null : bags[bagId];
          final linkedOrderId = selected == null
              ? 0
              : _intValue(
                  selected['bag_order_id'] ?? selected['requirement_order_id'],
                );
          if (linkedOrderId > 0) orderId = linkedOrderId;
          return AlertDialog(
            title: const Text('Issue Complete Diamond Bag'),
            content: SizedBox(
              width: 440,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    initialValue: bagId,
                    decoration: const InputDecoration(
                      labelText: 'Select Bag',
                      border: OutlineInputBorder(),
                    ),
                    items: bags.entries.map((entry) {
                      final rows = _diamondItems
                          .where((row) => _intValue(row['bag_id']) == entry.key)
                          .toList();
                      final chalni = rows
                          .map(
                            (row) =>
                                (row['size_label'] ??
                                        row['chalni_group_name'] ??
                                        row['size'] ??
                                        '')
                                    .toString()
                                    .trim(),
                          )
                          .where((value) => value.isNotEmpty)
                          .toSet()
                          .join(', ');
                      return DropdownMenuItem<int>(
                        value: entry.key,
                        child: Text(
                          '${entry.value['bag_no'] ?? 'Bag'} (#${entry.key}) · ${chalni.isEmpty ? '${rows.length} group(s)' : chalni}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      );
                    }).toList(),
                    onChanged: (value) => update(() {
                      bagId = value;
                      final row = value == null ? null : bags[value];
                      orderId = row == null
                          ? null
                          : _nullableId(
                              row['bag_order_id'] ??
                                  row['requirement_order_id'],
                            );
                    }),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  if (linkedOrderId > 0)
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        'Allocated to ${selected?['bag_order_no'] ?? selected?['requirement_order_no'] ?? 'linked order'}',
                      ),
                    )
                  else
                    _optionalItemDropdown(
                      'Allocate to Order',
                      orderId,
                      _orders,
                      (row) => '${row['order_no']} · ${row['order_name']}',
                      (value) => update(() => orderId = value),
                    ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: bagId == null
                    ? null
                    : () => Navigator.pop(dialogContext, true),
                child: const Text('Add Complete Bag'),
              ),
            ],
          );
        },
      ),
    );
    if (accepted != true || bagId == null) return;

    final rows = _diamondItems.where(
      (row) =>
          _intValue(row['bag_id']) == bagId &&
          _numberValue(row['pcs_available']) > 0 &&
          _numberValue(row['weight_cts_available']) > 0,
    );
    final existing = _diamondLines.map((line) => line.itemId).toSet();
    if (rows.any((row) => existing.contains(_intValue(row['id'])))) {
      _show(
        'This bag already has a line in the form. Remove it before adding the complete bag.',
      );
      return;
    }
    setState(() {
      for (final row in rows) {
        final line = _IssueLine()
          ..itemId = _intValue(row['id'])
          ..orderId = orderId;
        line.pcs.text = _wholeNumberText(row['pcs_available']);
        line.quantity.text = _decimalText(row['weight_cts_available']);
        _diamondLines.add(line);
      }
    });
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false) ||
        _saving ||
        _pickingPhoto)
      return;
    if (_karigarId == null || _locationId == null) {
      _show('Karigar and warehouse are required.');
      return;
    }
    if (_goldLines.isEmpty && _diamondLines.isEmpty && _stoneLines.isEmpty) {
      _show('Add at least one Gold, Diamond, or Stone line.');
      return;
    }
    if (_attachment == null) {
      _show('Attachment is required.');
      return;
    }

    setState(() => _saving = true);
    try {
      final result = await widget.api.createIssuement({
        if (widget.workRequestId != null)
          'work_request_id': widget.workRequestId,
        'voucher_no': _voucher.text.trim(),
        'issue_date': _date(_issueDate),
        'karigar_id': _karigarId,
        'location_id': _locationId,
        'purpose': _purpose.text.trim(),
        'notes': _notes.text.trim(),
        'attachment_base64': await _attachmentData(_attachment!),
        'gold_lines': _goldLines
            .map(
              (line) => {
                'item_id': line.itemId,
                'weight_gm': _number(line.quantity.text),
                'rate_per_gm': _optionalNumber(line.rate.text),
              },
            )
            .toList(),
        'diamond_lines': _diamondLines
            .map(
              (line) => {
                'bag_item_id': line.itemId,
                'allocation_order_id': line.orderId,
                'pcs': _number(line.pcs.text),
                'carat': _number(line.quantity.text),
                'rate_per_carat': _optionalNumber(line.rate.text),
              },
            )
            .toList(),
        'stone_lines': _stoneLines
            .map(
              (line) => {
                'item_id': line.itemId,
                'pcs': _optionalNumber(line.pcs.text) ?? 0,
                'qty': _number(line.quantity.text),
                'rate': _optionalNumber(line.rate.text),
              },
            )
            .toList(),
      });
      if (!mounted) return;
      Navigator.of(context).pop(result);
    } catch (e) {
      _show(_message(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Create Combined Issuement')),
      body: _loading
          ? const FullScreenLoader()
          : _error.isNotEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  if (widget.workRequestId != null) ...[
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(AppSpacing.lg),
                        child: Text(
                          'Gold request #${widget.workRequestId} · save a gold issuement to complete your assigned task.',
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  _section('Issuement Details', [
                    _text(_voucher, 'Voucher No (leave blank for automatic)'),
                    _dateField(),
                    _dropdown(
                      'Karigar',
                      _karigarId,
                      _karigars,
                      (row) => '${row['name']}',
                    ),
                    _dropdown(
                      'Warehouse',
                      _locationId,
                      _locations,
                      (row) => '${row['name']}',
                    ),
                    _requiredText(_purpose, 'Purpose'),
                    _text(_notes, 'Notes', lines: 3),
                    AppPhotoField(
                      file: _attachment,
                      title: 'Add attachment',
                      subtitle: 'Required · camera or gallery',
                      busy: _pickingPhoto,
                      enabled: !_saving,
                      onPick: _pickAttachment,
                      onRemove: () => setState(() => _attachment = null),
                    ),
                  ]),
                  _materialSection(
                    title: 'Gold',
                    color: AppColors.gold,
                    icon: Icons.workspace_premium_outlined,
                    lines: _goldLines,
                    add: () => setState(() => _goldLines.add(_IssueLine())),
                    builder: _goldLine,
                  ),
                  _materialSection(
                    title: 'Diamond',
                    color: AppColors.diamond,
                    icon: Icons.diamond_outlined,
                    lines: _diamondLines,
                    add: () => setState(() => _diamondLines.add(_IssueLine())),
                    addComplete: _addCompleteDiamondBag,
                    builder: _diamondLine,
                  ),
                  _materialSection(
                    title: 'Stone',
                    color: AppColors.stone,
                    icon: Icons.scatter_plot_outlined,
                    lines: _stoneLines,
                    add: () => setState(() => _stoneLines.add(_IssueLine())),
                    builder: _stoneLine,
                  ),
                  FilledButton.icon(
                    onPressed: _saving || _pickingPhoto ? null : _submit,
                    icon: _saving
                        ? const AppLoadingIndicator(size: 18, light: true)
                        : const Icon(Icons.check_circle_outline),
                    label: const Text('Save Combined Issuement'),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                ],
              ),
            ),
    );
  }

  Widget _goldLine(_IssueLine line) => Column(
    children: [
      _itemDropdown(
        'Gold Item',
        line.itemId,
        _goldItems,
        (row) =>
            '${row['purity_code']} · ${row['color_name'] ?? ''} · ${row['form_type'] ?? ''} · Bal ${row['weight_balance_gm'] ?? 0} gm',
        (value) => setState(() => line.itemId = value),
      ),
      _positive(line.quantity, 'Weight (gm)'),
      _optionalNumberField(line.rate, 'Rate / gm'),
    ],
  );

  Widget _diamondLine(_IssueLine line) => Column(
    children: [
      _itemDropdown(
        'Bag / Shape / Size',
        line.itemId,
        _diamondItems,
        (row) =>
            'Bag #${row['bag_id']} · ${_diamondSize(row)} · ${row['shape_name'] ?? row['item_shape'] ?? ''} · ${row['pcs_available'] ?? 0} PCS / ${row['weight_cts_available'] ?? 0} CTS · ${row['bag_no'] ?? ''}',
        (value) => setState(() {
          line.itemId = value;
          final selected = _diamondItems.cast<dynamic>().firstWhere(
            (row) => _intValue(row?['id']) == value,
            orElse: () => null,
          );
          final linkedOrderId = selected == null
              ? 0
              : _intValue(
                  selected['bag_order_id'] ?? selected['requirement_order_id'],
                );
          if (linkedOrderId > 0) line.orderId = linkedOrderId;
        }),
      ),
      _optionalItemDropdown(
        'Allocate to Order',
        line.orderId,
        _orders,
        (row) => '${row['order_no']} · ${row['order_name']}',
        (value) => setState(() => line.orderId = value),
        fieldKey: ValueKey(
          'diamond_order_${identityHashCode(line)}_${line.orderId ?? 0}',
        ),
      ),
      _positive(line.pcs, 'PCS', whole: true),
      _positive(line.quantity, 'Carat'),
      _optionalNumberField(line.rate, 'Rate / carat'),
    ],
  );

  Widget _stoneLine(_IssueLine line) => Column(
    children: [
      _itemDropdown(
        'Stone Item',
        line.itemId,
        _stoneItems,
        (row) =>
            '${row['product_name']} · ${row['stone_type'] ?? ''} · Bal ${row['qty_balance'] ?? 0}',
        (value) => setState(() => line.itemId = value),
      ),
      _optionalNumberField(line.pcs, 'PCS'),
      _positive(line.quantity, 'Quantity'),
      _optionalNumberField(line.rate, 'Rate'),
    ],
  );

  Widget _materialSection({
    required String title,
    required Color color,
    required IconData icon,
    required List<_IssueLine> lines,
    required VoidCallback add,
    VoidCallback? addComplete,
    required Widget Function(_IssueLine) builder,
  }) => Container(
    margin: const EdgeInsets.only(bottom: AppSpacing.lg),
    padding: const EdgeInsets.all(AppSpacing.lg),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(AppRadius.lg),
      border: Border.all(color: color.withValues(alpha: 0.35)),
    ),
    child: Column(
      children: [
        Row(
          children: [
            Icon(icon, color: color),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                '$title Issuement',
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            Text('${lines.length} line(s)'),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        if (lines.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
            child: Text(
              'No $title lines added.',
              style: const TextStyle(color: AppColors.textSecondary),
            ),
          ),
        ...lines.asMap().entries.map(
          (entry) => Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.md),
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          '$title line ${entry.key + 1}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.delete_outline),
                        onPressed: () => setState(() {
                          lines.removeAt(entry.key).dispose();
                        }),
                      ),
                    ],
                  ),
                  builder(entry.value),
                ],
              ),
            ),
          ),
        ),
        OutlinedButton.icon(
          onPressed: add,
          icon: const Icon(Icons.add),
          label: Text('Add $title Line'),
        ),
        if (addComplete != null) ...[
          const SizedBox(height: AppSpacing.sm),
          FilledButton.icon(
            onPressed: addComplete,
            icon: const Icon(Icons.inventory_2_outlined),
            label: const Text('Issue Complete Bag'),
          ),
        ],
      ],
    ),
  );

  Widget _section(String title, List<Widget> children) =>
      AppFormSection(title: title, children: children);

  Widget _text(
    TextEditingController controller,
    String label, {
    int lines = 1,
    String? Function(String?)? validator,
  }) => TextFormField(
    controller: controller,
    maxLines: lines,
    validator: validator,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
  );
  Widget _requiredText(TextEditingController controller, String label) => _text(
    controller,
    label,
    validator: (value) =>
        (value ?? '').trim().isEmpty ? '$label is required' : null,
  );
  Widget _positive(
    TextEditingController controller,
    String label, {
    bool whole = false,
  }) => TextFormField(
    controller: controller,
    keyboardType: TextInputType.numberWithOptions(decimal: !whole),
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    validator: (value) {
      final parsed = double.tryParse((value ?? '').trim());
      if (parsed == null || parsed <= 0) return 'Enter a number greater than 0';
      if (whole && parsed != parsed.truncateToDouble())
        return 'Enter whole-number PCS';
      return null;
    },
  );
  Widget _optionalNumberField(TextEditingController controller, String label) =>
      TextFormField(
        controller: controller,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
        ),
        validator: (value) {
          if ((value ?? '').trim().isEmpty) return null;
          final parsed = double.tryParse(value!.trim());
          return parsed == null || parsed < 0
              ? 'Enter a valid non-negative number'
              : null;
        },
      );

  Widget _dropdown(
    String label,
    int? value,
    List<dynamic> rows,
    String Function(dynamic) display,
  ) => DropdownButtonFormField<int>(
    initialValue: value,
    isExpanded: true,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    items: rows
        .map(
          (row) => DropdownMenuItem<int>(
            value: _id(row),
            child: Text(display(row), overflow: TextOverflow.ellipsis),
          ),
        )
        .toList(),
    onChanged: (selected) => setState(() {
      if (label == 'Karigar')
        _karigarId = selected;
      else
        _locationId = selected;
    }),
    validator: (selected) => selected == null ? '$label is required' : null,
  );

  Widget _itemDropdown(
    String label,
    int? value,
    List<dynamic> rows,
    String Function(dynamic) display,
    ValueChanged<int?> changed,
  ) => DropdownButtonFormField<int>(
    initialValue: value,
    isExpanded: true,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    items: rows
        .map(
          (row) => DropdownMenuItem<int>(
            value: _id(row),
            child: Text(display(row), overflow: TextOverflow.ellipsis),
          ),
        )
        .toList(),
    onChanged: changed,
    validator: (selected) => selected == null ? '$label is required' : null,
  );

  Widget _optionalItemDropdown(
    String label,
    int? value,
    List<dynamic> rows,
    String Function(dynamic) display,
    ValueChanged<int?> changed, {
    Key? fieldKey,
  }) => DropdownButtonFormField<int?>(
    key: fieldKey,
    initialValue: value,
    isExpanded: true,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    items: [
      const DropdownMenuItem<int?>(value: null, child: Text('Not allocated')),
      ...rows.map(
        (row) => DropdownMenuItem<int?>(
          value: _id(row),
          child: Text(display(row), overflow: TextOverflow.ellipsis),
        ),
      ),
    ],
    onChanged: changed,
  );

  Widget _dateField() => ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 12),
    shape: RoundedRectangleBorder(
      side: const BorderSide(color: AppColors.border),
      borderRadius: BorderRadius.circular(4),
    ),
    title: const Text('Issue Date'),
    subtitle: Text(_date(_issueDate)),
    trailing: const Icon(Icons.calendar_today_outlined),
    onTap: () async {
      final value = await showDatePicker(
        context: context,
        initialDate: _issueDate,
        firstDate: DateTime(2000),
        lastDate: DateTime(2100),
      );
      if (value != null) setState(() => _issueDate = value);
    },
  );

  int _id(dynamic row) => row['id'] is num
      ? (row['id'] as num).toInt()
      : int.tryParse('${row['id']}') ?? 0;
  int _intValue(dynamic value) =>
      value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
  int? _nullableId(dynamic value) {
    final id = _intValue(value);
    return id > 0 ? id : null;
  }

  double _numberValue(dynamic value) =>
      value is num ? value.toDouble() : double.tryParse('${value ?? ''}') ?? 0;
  String _wholeNumberText(dynamic value) =>
      _numberValue(value).round().toString();
  String _decimalText(dynamic value) => _numberValue(value).toStringAsFixed(3);
  String _chalniRange(dynamic row) {
    final from = '${row['chalni_from'] ?? ''}'.trim();
    final to = '${row['chalni_to'] ?? ''}'.trim();
    return from.isEmpty && to.isEmpty ? 'No chalni' : 'Chalni $from-$to';
  }

  String _diamondSize(dynamic row) {
    final size = '${row['size_label'] ?? ''}'.trim().isNotEmpty
        ? '${row['size_label']}'.trim()
        : '${row['size_code'] ?? ''}'.trim();
    if (size.isEmpty) return _chalniRange(row);
    return size.toLowerCase().contains('chalni') ? size : 'Chalni $size';
  }

  double _number(String value) => double.tryParse(value.trim()) ?? 0;
  double? _optionalNumber(String value) =>
      value.trim().isEmpty ? null : double.tryParse(value.trim());
  Future<String> _attachmentData(XFile file) async {
    final extension = file.name.split('.').last.toLowerCase();
    final mime = switch (extension) {
      'png' => 'image/png',
      'webp' => 'image/webp',
      _ => 'image/jpeg',
    };
    return 'data:$mime;base64,${base64Encode(await file.readAsBytes())}';
  }

  String _date(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
  String _message(Object error) =>
      error.toString().replaceFirst('Exception: ', '');
  void _show(String message) => ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(message)));
}

class _IssueLine {
  int? itemId;
  int? orderId;
  final pcs = TextEditingController();
  final quantity = TextEditingController();
  final rate = TextEditingController();

  void dispose() {
    pcs.dispose();
    quantity.dispose();
    rate.dispose();
  }
}
