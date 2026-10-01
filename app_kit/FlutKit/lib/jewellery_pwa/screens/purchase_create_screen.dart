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

class PurchaseCreateScreen extends StatefulWidget {
  const PurchaseCreateScreen({
    super.key,
    required this.api,
    required this.material,
  });

  final MobileApiService api;
  final String material;

  @override
  State<PurchaseCreateScreen> createState() => _PurchaseCreateScreenState();
}

class _PurchaseCreateScreenState extends State<PurchaseCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _invoice = TextEditingController();
  final _supplierName = TextEditingController();
  final _supplierAddress = TextEditingController();
  final _supplierGstin = TextEditingController();
  final _supplierPhone = TextEditingController();
  final _supplierEmail = TextEditingController();
  final _terms = TextEditingController();
  final _placeOfSupply = TextEditingController();
  final _description = TextEditingController();
  final _notes = TextEditingController();
  final _roundOff = TextEditingController(text: '0');
  final _paidAmount = TextEditingController(text: '0');
  DateTime _purchaseDate = DateTime.now();
  DateTime? _dueDate;
  DateTime? _paymentDate;
  int? _vendorId;
  int? _locationId;
  int? _gstMasterId;
  String _paymentStatus = 'Pending';
  bool _loading = true;
  bool _saving = false;
  bool _pickingPhoto = false;
  String _error = '';
  List<dynamic> _vendors = [];
  List<dynamic> _locations = [];
  List<dynamic> _gstMasters = [];
  List<dynamic> _items = [];
  List<dynamic> _purities = [];
  List<dynamic> _shapes = [];
  List<dynamic> _chalniGroups = [];
  List<XFile> _files = [];
  final List<_PurchaseLine> _lines = [_PurchaseLine()];

  String get _material => widget.material.toLowerCase();
  String get _materialLabel =>
      '${_material[0].toUpperCase()}${_material.substring(1)}';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final controller in [
      _invoice,
      _supplierName,
      _supplierAddress,
      _supplierGstin,
      _supplierPhone,
      _supplierEmail,
      _terms,
      _placeOfSupply,
      _description,
      _notes,
      _roundOff,
      _paidAmount,
    ]) {
      controller.dispose();
    }
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final common = await Future.wait([
        widget.api.fetchVendors(),
        widget.api.fetchGstMasters(),
      ]);
      final specific = switch (_material) {
        'diamond' => await Future.wait([
          widget.api.fetchDiamondItems(),
          widget.api.fetchDiamondShapes(),
          widget.api.fetchDiamondChalniGroups(),
        ]),
        'gold' => await Future.wait([
          widget.api.fetchGoldItems(),
          widget.api.fetchGoldPurities(),
          widget.api.fetchLocations(),
        ]),
        _ => await Future.wait([widget.api.fetchStoneItems()]),
      };
      if (!mounted) return;
      setState(() {
        _vendors = common[0];
        _gstMasters = common[1];
        _items = specific[0];
        if (_material == 'diamond') {
          _shapes = specific[1];
          _chalniGroups = specific[2];
        } else if (_material == 'gold') {
          _purities = specific[1];
          _locations = specific[2];
        }
        _gstMasterId = _gstMasters.isEmpty ? null : _id(_gstMasters.first);
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

  void _selectVendor(int? value) {
    final vendor = _vendors
        .cast<Map>()
        .where((row) => _id(row) == value)
        .firstOrNull;
    setState(() {
      _vendorId = value;
      _supplierName.text = '${vendor?['name'] ?? ''}';
      _supplierAddress.text = '${vendor?['address'] ?? ''}';
      _supplierGstin.text = '${vendor?['gstin'] ?? ''}';
      _supplierPhone.text = '${vendor?['phone'] ?? ''}';
      _supplierEmail.text = '${vendor?['email'] ?? ''}';
    });
  }

  Future<void> _takePhoto() async {
    if (_pickingPhoto || _saving) return;
    setState(() => _pickingPhoto = true);
    try {
      final images = await AppImagePicker().pickImages(
        context,
        title: 'Purchase attachments',
      );
      if (mounted && images.isNotEmpty) {
        setState(() => _files = [..._files, ...images]);
      }
    } finally {
      if (mounted) setState(() => _pickingPhoto = false);
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false) ||
        _saving ||
        _pickingPhoto)
      return;
    if (_gstMasterId == null) {
      _show('GST master is required.');
      return;
    }
    if (_material != 'gold' && _vendorId == null) {
      _show('Vendor is required.');
      return;
    }
    if (_material == 'gold' && _locationId == null) {
      _show('Purchase location is required.');
      return;
    }
    if (_material == 'gold' &&
        _paymentStatus != 'Pending' &&
        (_number(_paidAmount.text) <= 0)) {
      _show('Paid amount is required for $_paymentStatus payment.');
      return;
    }

    setState(() => _saving = true);
    try {
      final attachments = <Map<String, dynamic>>[];
      for (final file in _files) {
        final bytes = await file.readAsBytes();
        attachments.add({
          'name': file.name,
          'extension': file.name.split('.').last.toLowerCase(),
          'mime_type': file.mimeType ?? 'image/jpeg',
          'base64': base64Encode(bytes),
        });
      }
      final payload = <String, dynamic>{
        'purchase_date': _date(_purchaseDate),
        'vendor_id': _vendorId ?? 0,
        'invoice_no': _invoice.text.trim(),
        'supplier_name': _supplierName.text.trim(),
        'supplier_address': _supplierAddress.text.trim(),
        'supplier_gstin': _supplierGstin.text.trim(),
        'supplier_phone': _supplierPhone.text.trim(),
        'supplier_email': _supplierEmail.text.trim(),
        'due_date': _dueDate == null ? '' : _date(_dueDate!),
        'gst_master_id': _gstMasterId,
        'round_off_amount': _number(_roundOff.text),
        'notes': _notes.text.trim(),
        'lines': _lines.map(_linePayload).toList(),
      };
      if (_material == 'diamond') {
        payload['terms'] = int.tryParse(_terms.text.trim());
        payload['attachments'] = attachments;
        await widget.api.createDiamondPurchase(payload);
      } else if (_material == 'gold') {
        payload.addAll({
          'location_id': _locationId,
          'place_of_supply': _placeOfSupply.text.trim(),
          'purchase_description': _description.text.trim(),
          'payment_status': _paymentStatus,
          'paid_amount': _number(_paidAmount.text),
          'payment_date': _paymentDate == null ? '' : _date(_paymentDate!),
        });
        await widget.api.createGoldPurchase(payload);
      } else {
        payload['attachments'] = attachments;
        await widget.api.createStonePurchase(payload);
      }
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      _show(_message(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Map<String, dynamic> _linePayload(_PurchaseLine line) => switch (_material) {
    'diamond' => {
      'item_id': line.itemId ?? 0,
      'diamond_type': line.type.text.trim(),
      'shape_master_id': line.shapeId,
      'chalni_group_id': line.chalniId,
      'color': line.color.text.trim(),
      'clarity': line.clarity.text.trim(),
      'pcs': _number(line.pcs.text),
      'carat': _number(line.quantity.text),
      'rate_per_carat': _number(line.rate.text),
    },
    'gold' => {
      'item_id': line.itemId ?? 0,
      'gold_purity_id': line.purityId,
      'color_name': line.color.text.trim(),
      'form_type': line.type.text.trim(),
      'description': line.description.text.trim(),
      'hsn_sac': line.hsn.text.trim(),
      'unit': line.unit.text.trim(),
      'weight_gm': _number(line.quantity.text),
      'rate_per_gm': _number(line.rate.text),
    },
    _ => {
      'item_id': line.itemId ?? 0,
      'product_name': line.description.text.trim(),
      'stone_type': line.type.text.trim(),
      'qty': _number(line.quantity.text),
      'rate': _number(line.rate.text),
    },
  };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Create $_materialLabel Purchase')),
      body: _loading
          ? const FullScreenLoader()
          : _error.isNotEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  _section('Purchase & Supplier Details', [
                    _dateField(
                      'Purchase Date',
                      _purchaseDate,
                      (value) => setState(() {
                        _purchaseDate = value;
                        if (_material == 'diamond') _recalculateDueDate();
                      }),
                    ),
                    _vendorDropdown(),
                    _text(_invoice, 'Invoice No'),
                    if (_material == 'diamond')
                      _text(
                        _terms,
                        'Terms (Days)',
                        keyboard: TextInputType.number,
                        validator: (value) {
                          if ((value ?? '').trim().isEmpty) return null;
                          final days = int.tryParse(value!);
                          return days == null || days < 0 || days > 36500
                              ? 'Enter valid terms days'
                              : null;
                        },
                        onChanged: (_) => _recalculateDueDate(),
                      ),
                    _optionalDateField(
                      'Due Date',
                      _dueDate,
                      _material == 'diamond'
                          ? null
                          : (value) => setState(() => _dueDate = value),
                    ),
                    if (_material == 'gold')
                      _simpleDropdown(
                        'Purchase Location',
                        _locationId,
                        _locations,
                        (row) => '${row['name']}',
                        (value) => setState(() => _locationId = value),
                        required: true,
                      ),
                    _text(
                      _supplierName,
                      'Supplier Name',
                      readOnly: _material != 'gold',
                    ),
                    _text(
                      _supplierAddress,
                      'Supplier Address',
                      readOnly: _material != 'gold',
                    ),
                    _text(
                      _supplierGstin,
                      'Supplier GSTIN',
                      readOnly: _material != 'gold',
                    ),
                    _text(
                      _supplierPhone,
                      'Supplier Phone',
                      readOnly: _material != 'gold',
                      keyboard: TextInputType.phone,
                    ),
                    _text(
                      _supplierEmail,
                      'Supplier Email',
                      readOnly: _material != 'gold',
                      keyboard: TextInputType.emailAddress,
                    ),
                    if (_material == 'gold')
                      _text(_placeOfSupply, 'Place of Supply'),
                    if (_material == 'gold')
                      _text(_description, 'Purchase Description'),
                    _text(_notes, 'Notes', lines: 2),
                  ]),
                  _lineSection(),
                  _taxSection(),
                  if (_material != 'gold') _attachmentSection(),
                  FilledButton.icon(
                    onPressed: _saving || _pickingPhoto ? null : _submit,
                    icon: _saving
                        ? const AppLoadingIndicator(size: 18, light: true)
                        : const Icon(Icons.check_circle_outline),
                    label: Text('Save $_materialLabel Purchase'),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                ],
              ),
            ),
    );
  }

  Widget _vendorDropdown() => DropdownButtonFormField<int?>(
    initialValue: _vendorId,
    isExpanded: true,
    decoration: InputDecoration(
      labelText: _material == 'gold' ? 'Vendor (optional)' : 'Vendor',
      border: const OutlineInputBorder(),
    ),
    items: [
      const DropdownMenuItem<int?>(value: null, child: Text('Select vendor')),
      ..._vendors.map(
        (row) => DropdownMenuItem<int?>(
          value: _id(row),
          child: Text('${row['name']}'),
        ),
      ),
    ],
    onChanged: _selectVendor,
    validator: (value) =>
        _material != 'gold' && value == null ? 'Vendor is required' : null,
  );

  Widget _lineSection() => _section('Purchase Lines', [
    ..._lines.asMap().entries.map(
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
                      'Line ${entry.key + 1}',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                  ),
                  IconButton(
                    onPressed: _lines.length == 1
                        ? null
                        : () => setState(() {
                            _lines.removeAt(entry.key).dispose();
                          }),
                    icon: const Icon(Icons.delete_outline),
                  ),
                ],
              ),
              _lineFields(entry.value),
              Align(
                alignment: Alignment.centerRight,
                child: Text(
                  'Line Value: ₹${_lineValue(entry.value).toStringAsFixed(2)}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
    OutlinedButton.icon(
      onPressed: () => setState(() => _lines.add(_PurchaseLine())),
      icon: const Icon(Icons.add),
      label: const Text('Add Line'),
    ),
  ]);

  Widget _lineFields(_PurchaseLine line) => switch (_material) {
    'diamond' => Column(
      children: [
        _optionalItem(
          'Existing Item',
          line.itemId,
          _items,
          (row) =>
              '${row['diamond_type']} / ${row['shape'] ?? ''} / ${row['color'] ?? ''} / ${row['clarity'] ?? ''}',
          (value) => setState(() {
            line.itemId = value;
            final row = _find(_items, value);
            if (row != null) {
              line.type.text = '${row['diamond_type'] ?? ''}';
              line.color.text = '${row['color'] ?? ''}';
              line.clarity.text = '${row['clarity'] ?? ''}';
              line.shapeId = _nullableId(row['default_shape_master_id']);
            }
          }),
        ),
        _text(
          line.type,
          'Diamond Type',
          validator: (value) =>
              line.itemId == null && (value ?? '').trim().isEmpty
              ? 'Diamond type is required'
              : null,
        ),
        _simpleDropdown(
          'Shape',
          line.shapeId,
          _shapes,
          (row) => '${row['name']}',
          (value) => setState(() => line.shapeId = value),
          required: true,
        ),
        _simpleDropdown(
          'Chalni Group',
          line.chalniId,
          _chalniGroups,
          (row) => '${row['name']} · ${row['range_label'] ?? ''}',
          (value) => setState(() => line.chalniId = value),
          required: true,
        ),
        _text(line.color, 'Color'),
        _text(line.clarity, 'Clarity'),
        _nonNegative(line.pcs, 'PCS'),
        _positive(line.quantity, 'Carat'),
        _nonNegative(
          line.rate,
          'Rate / cts',
          required: true,
          onChanged: (_) => setState(() {}),
        ),
      ],
    ),
    'gold' => Column(
      children: [
        _optionalItem(
          'Existing Item',
          line.itemId,
          _items,
          (row) =>
              '${row['purity_code']} / ${row['color_name'] ?? ''} / ${row['form_type'] ?? ''}',
          (value) => setState(() {
            line.itemId = value;
            final row = _find(_items, value);
            if (row != null) {
              line.purityId = _nullableId(row['gold_purity_id']);
              line.color.text = '${row['color_name'] ?? ''}';
              line.type.text = '${row['form_type'] ?? ''}';
            }
          }),
        ),
        _simpleDropdown(
          'Gold Purity',
          line.purityId,
          _purities,
          (row) => '${row['purity_code']} (${row['purity_percent']}%)',
          (value) => setState(() => line.purityId = value),
          required: line.itemId == null,
        ),
        _text(line.color, 'Color'),
        _text(line.type, 'Form'),
        _text(line.description, 'Description'),
        _text(line.hsn, 'HSN / SAC'),
        _text(line.unit, 'Unit'),
        _positive(
          line.quantity,
          'Weight (gm)',
          onChanged: (_) => setState(() {}),
        ),
        Align(
          alignment: Alignment.centerRight,
          child: Text(
            'Pure Weight: ${_fineWeight(line).toStringAsFixed(3)} gm',
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
        _nonNegative(
          line.rate,
          'Rate / gm',
          required: true,
          onChanged: (_) => setState(() {}),
        ),
      ],
    ),
    _ => Column(
      children: [
        _optionalItem(
          'Existing Item',
          line.itemId,
          _items,
          (row) => '${row['product_name']} / ${row['stone_type'] ?? ''}',
          (value) => setState(() {
            line.itemId = value;
            final row = _find(_items, value);
            if (row != null) {
              line.description.text = '${row['product_name'] ?? ''}';
              line.type.text = '${row['stone_type'] ?? ''}';
              line.rate.text = '${row['default_rate'] ?? ''}';
            }
          }),
        ),
        _text(
          line.description,
          'Product Name',
          validator: (value) =>
              line.itemId == null && (value ?? '').trim().isEmpty
              ? 'Product name is required'
              : null,
        ),
        _text(line.type, 'Stone Type'),
        _positive(line.quantity, 'Quantity', onChanged: (_) => setState(() {})),
        _nonNegative(
          line.rate,
          'Rate',
          required: true,
          onChanged: (_) => setState(() {}),
        ),
      ],
    ),
  };

  Widget _taxSection() => _section(
    _material == 'gold' ? 'Tax & Payment Information' : 'Tax Information',
    [
      _simpleDropdown(
        'GST Master',
        _gstMasterId,
        _gstMasters,
        (row) => '${row['name']} (${row['total_percentage']}%)',
        (value) => setState(() => _gstMasterId = value),
        required: true,
      ),
      _readout('Taxable Amount', _subtotal),
      if (_gstComponents.isEmpty)
        const Text('Select a GST master to see the tax breakup.')
      else
        ..._gstComponents.map((component) {
          final percentage = _number('${component['percentage'] ?? 0}');
          return _readout(
            '${component['name'] ?? 'Tax'} (${percentage.toStringAsFixed(3)}%)',
            _subtotal * percentage / 100,
          );
        }),
      _readout(
        'GST Amount (${_gstPercentage.toStringAsFixed(3)}%)',
        _gstAmount,
      ),
      _nonNegative(
        _roundOff,
        'Round Off',
        allowNegative: true,
        onChanged: (_) => setState(() {}),
      ),
      _readout('Invoice Total', _invoiceTotal),
      if (_material == 'gold') _paymentFields(),
    ],
  );

  Widget _paymentFields() => Column(
    children: [
      DropdownButtonFormField<String>(
        initialValue: _paymentStatus,
        decoration: const InputDecoration(
          labelText: 'Payment Status',
          border: OutlineInputBorder(),
        ),
        items: const ['Pending', 'Partial', 'Paid']
            .map((value) => DropdownMenuItem(value: value, child: Text(value)))
            .toList(),
        onChanged: (value) => setState(() {
          _paymentStatus = value ?? 'Pending';
          if (_paymentStatus == 'Pending') {
            _paidAmount.text = '0';
            _paymentDate = null;
          } else if (_paymentStatus == 'Paid') {
            _paidAmount.text = _invoiceTotal.toStringAsFixed(2);
          }
        }),
      ),
      const SizedBox(height: AppSpacing.md),
      _nonNegative(
        _paidAmount,
        'Paid Amount',
        required: _paymentStatus != 'Pending',
      ),
      const SizedBox(height: AppSpacing.md),
      _optionalDateField(
        'Payment Date',
        _paymentDate,
        _paymentStatus == 'Pending'
            ? null
            : (value) => setState(() => _paymentDate = value),
      ),
    ],
  );

  Widget _attachmentSection() => _section('Attachments', [
    AppPhotoAddButton(
      onPressed: _saving || _pickingPhoto ? null : _takePhoto,
      title: _files.isEmpty ? 'Add attachment photos' : 'Add more photos',
      busy: _pickingPhoto,
    ),
    if (_files.isNotEmpty)
      Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
        child: Text('${_files.length} photo(s) selected'),
      ),
    ..._files.map(
      (file) => Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
        child: AppPhotoPreview(
          key: ObjectKey(file),
          file: file,
          onRemove: _saving || _pickingPhoto
              ? null
              : () => setState(() => _files.remove(file)),
        ),
      ),
    ),
  ]);

  double get _subtotal => _lines.fold(0, (sum, line) => sum + _lineValue(line));
  double get _gstPercentage {
    final row = _find(_gstMasters, _gstMasterId);
    return _number('${row?['total_percentage'] ?? 0}');
  }

  List<Map> get _gstComponents {
    final row = _find(_gstMasters, _gstMasterId);
    return (row?['components'] as List? ?? []).whereType<Map>().toList();
  }

  double get _gstAmount => _subtotal * _gstPercentage / 100;
  double get _invoiceTotal => _subtotal + _gstAmount + _number(_roundOff.text);
  double _lineValue(_PurchaseLine line) =>
      _number(line.quantity.text) * _number(line.rate.text);
  double _fineWeight(_PurchaseLine line) {
    final item = _find(_items, line.itemId);
    final purity = _find(_purities, line.purityId);
    final percentage = _number(
      '${item?['purity_percent'] ?? purity?['purity_percent'] ?? 0}',
    );
    return _number(line.quantity.text) * percentage / 100;
  }

  void _recalculateDueDate() {
    final days = int.tryParse(_terms.text.trim());
    setState(
      () => _dueDate = days == null || days < 0
          ? null
          : DateTime(
              _purchaseDate.year,
              _purchaseDate.month,
              _purchaseDate.day + days,
            ),
    );
  }

  Widget _section(String title, List<Widget> children) =>
      AppFormSection(title: title, children: children);

  Widget _text(
    TextEditingController controller,
    String label, {
    int lines = 1,
    bool readOnly = false,
    TextInputType? keyboard,
    String? Function(String?)? validator,
    ValueChanged<String>? onChanged,
  }) => TextFormField(
    controller: controller,
    maxLines: lines,
    readOnly: readOnly,
    keyboardType: keyboard,
    validator: validator,
    onChanged: onChanged,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
      filled: readOnly,
    ),
  );
  Widget _positive(
    TextEditingController controller,
    String label, {
    ValueChanged<String>? onChanged,
  }) => _text(
    controller,
    label,
    keyboard: const TextInputType.numberWithOptions(decimal: true),
    onChanged: onChanged,
    validator: (value) =>
        _number(value ?? '') <= 0 ? 'Enter a number greater than 0' : null,
  );
  Widget _nonNegative(
    TextEditingController controller,
    String label, {
    bool required = false,
    bool allowNegative = false,
    ValueChanged<String>? onChanged,
  }) => _text(
    controller,
    label,
    keyboard: const TextInputType.numberWithOptions(
      decimal: true,
      signed: true,
    ),
    onChanged: onChanged,
    validator: (value) {
      if ((value ?? '').trim().isEmpty)
        return required ? '$label is required' : null;
      final parsed = double.tryParse(value!.trim());
      return parsed == null || (!allowNegative && parsed < 0)
          ? 'Enter a valid ${allowNegative ? '' : 'non-negative '}number'
          : null;
    },
  );
  Widget _readout(String label, double value) => InputDecorator(
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
      filled: true,
    ),
    child: Text(
      '₹${value.toStringAsFixed(2)}',
      style: const TextStyle(fontWeight: FontWeight.w700),
    ),
  );

  Widget _simpleDropdown(
    String label,
    int? value,
    List<dynamic> rows,
    String Function(dynamic) display,
    ValueChanged<int?> changed, {
    bool required = false,
  }) => DropdownButtonFormField<int?>(
    initialValue: value,
    isExpanded: true,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    items: [
      if (!required)
        const DropdownMenuItem<int?>(
          value: null,
          child: Text('Select (optional)'),
        ),
      ...rows.map(
        (row) => DropdownMenuItem<int?>(
          value: _id(row),
          child: Text(display(row), overflow: TextOverflow.ellipsis),
        ),
      ),
    ],
    onChanged: changed,
    validator: (selected) =>
        required && selected == null ? '$label is required' : null,
  );
  Widget _optionalItem(
    String label,
    int? value,
    List<dynamic> rows,
    String Function(dynamic) display,
    ValueChanged<int?> changed,
  ) => _simpleDropdown(label, value, rows, display, changed);

  Widget _dateField(
    String label,
    DateTime value,
    ValueChanged<DateTime> changed,
  ) => ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 12),
    shape: RoundedRectangleBorder(
      side: const BorderSide(color: AppColors.border),
      borderRadius: BorderRadius.circular(4),
    ),
    title: Text(label),
    subtitle: Text(_date(value)),
    trailing: const Icon(Icons.calendar_today_outlined),
    onTap: () async {
      final selected = await showDatePicker(
        context: context,
        initialDate: value,
        firstDate: DateTime(2000),
        lastDate: DateTime(2100),
      );
      if (selected != null) changed(selected);
    },
  );
  Widget _optionalDateField(
    String label,
    DateTime? value,
    ValueChanged<DateTime?>? changed,
  ) => ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 12),
    shape: RoundedRectangleBorder(
      side: const BorderSide(color: AppColors.border),
      borderRadius: BorderRadius.circular(4),
    ),
    title: Text(label),
    subtitle: Text(value == null ? 'Not selected' : _date(value)),
    trailing: changed == null
        ? null
        : value == null
        ? const Icon(Icons.calendar_today_outlined)
        : IconButton(
            icon: const Icon(Icons.close),
            onPressed: () => changed(null),
          ),
    onTap: changed == null
        ? null
        : () async {
            final selected = await showDatePicker(
              context: context,
              initialDate: value ?? DateTime.now(),
              firstDate: DateTime(2000),
              lastDate: DateTime(2100),
            );
            if (selected != null) changed(selected);
          },
  );

  Map? _find(List<dynamic> rows, int? id) =>
      rows.cast<Map>().where((row) => _id(row) == id).firstOrNull;
  int _id(dynamic row) => _nullableId(row['id']) ?? 0;
  int? _nullableId(dynamic value) =>
      value is num ? value.toInt() : int.tryParse('${value ?? ''}');
  double _number(String value) => double.tryParse(value.trim()) ?? 0;
  String _date(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
  String _message(Object error) =>
      error.toString().replaceFirst('Exception: ', '');
  void _show(String message) => ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(message)));
}

class _PurchaseLine {
  int? itemId;
  int? shapeId;
  int? chalniId;
  int? purityId;
  final type = TextEditingController();
  final color = TextEditingController();
  final clarity = TextEditingController();
  final pcs = TextEditingController(text: '0');
  final quantity = TextEditingController();
  final rate = TextEditingController();
  final description = TextEditingController();
  final hsn = TextEditingController();
  final unit = TextEditingController(text: 'GMS');

  void dispose() {
    for (final controller in [
      type,
      color,
      clarity,
      pcs,
      quantity,
      rate,
      description,
      hsn,
      unit,
    ]) {
      controller.dispose();
    }
  }
}
