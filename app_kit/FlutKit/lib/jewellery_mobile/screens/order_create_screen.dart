import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

class OrderCreateScreen extends StatefulWidget {
  const OrderCreateScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<OrderCreateScreen> createState() => _OrderCreateScreenState();
}

class _OrderCreateScreenState extends State<OrderCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _orderName = TextEditingController();
  final _newCategory = TextEditingController();
  final _orderFrom = TextEditingController();
  final _contact = TextEditingController();
  final _priorityLevel = TextEditingController(text: '0');
  final _whatsappNumber = TextEditingController();
  final _diamondSpec = TextEditingController();
  final _stoneSpec = TextEditingController();
  final _notes = TextEditingController();
  final _repairOrnament = TextEditingController();
  final _repairWork = TextEditingController();
  final _repairWeight = TextEditingController();
  final _goldRate = TextEditingController();
  final _approximatePrice = TextEditingController();
  final _advanceAmount = TextEditingController(text: '0');
  final _additionalDetails = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  String _error = '';
  DateTime _receivedDate = DateTime.now();
  DateTime? _dueDate;
  DateTime _repairReceivedDate = DateTime.now();
  String _orderType = 'Sales';
  String _designType = 'Fresh';
  String _material = 'Gold';
  String _priority = '';
  String _status = '';
  String _certificate = '';
  String _goldRateStatus = 'Not Fixed';
  String _fileType = 'reference';
  bool _notifyWhatsapp = true;
  int? _categoryId;
  int? _customerId;
  int? _salesPersonId;

  List<dynamic> _customers = [];
  List<dynamic> _salesPeople = [];
  List<dynamic> _designs = [];
  List<dynamic> _purities = [];
  List<dynamic> _categories = [];
  List<String> _priorities = [];
  List<String> _statuses = [];
  List<String> _materials = const ['Gold', 'Diamond', 'Jadau', 'Silver'];
  List<String> _certificates = const [
    '',
    'IGI',
    'Kalasha',
    'IGI / Kalasha',
    'Other',
  ];
  final List<_OrderItemForm> _items = [_OrderItemForm()];
  List<PlatformFile> _files = [];

  @override
  void initState() {
    super.initState();
    _approximatePrice.addListener(_refreshCommercialSummary);
    _advanceAmount.addListener(_refreshCommercialSummary);
    _load();
  }

  @override
  void dispose() {
    _approximatePrice.removeListener(_refreshCommercialSummary);
    _advanceAmount.removeListener(_refreshCommercialSummary);
    for (final controller in [
      _orderName,
      _newCategory,
      _orderFrom,
      _contact,
      _priorityLevel,
      _whatsappNumber,
      _diamondSpec,
      _stoneSpec,
      _notes,
      _repairOrnament,
      _repairWork,
      _repairWeight,
      _goldRate,
      _approximatePrice,
      _advanceAmount,
      _additionalDetails,
    ]) {
      controller.dispose();
    }
    for (final item in _items) {
      item.dispose();
    }
    super.dispose();
  }

  void _refreshCommercialSummary() {
    if (mounted) setState(() {});
  }

  Future<void> _load() async {
    try {
      final data = await widget.api.fetchOrderFormOptions();
      if (!mounted) return;
      setState(() {
        _customers = (data['customers'] as List?) ?? [];
        _salesPeople = (data['sales_people'] as List?) ?? [];
        _designs = (data['designs'] as List?) ?? [];
        _purities = (data['gold_purities'] as List?) ?? [];
        _categories = (data['order_categories'] as List?) ?? [];
        _priorities = _strings(data['priorities']);
        _statuses = _strings(data['statuses']);
        _materials = _strings(data['material_categories']).isEmpty
            ? _materials
            : _strings(data['material_categories']);
        _certificates = _strings(data['certificate_requirements']).isEmpty
            ? _certificates
            : _strings(data['certificate_requirements']);
        _categoryId = _categories.isEmpty ? null : _id(_categories.first);
        _priority = _priorities.contains('Medium')
            ? 'Medium'
            : (_priorities.isEmpty ? '' : _priorities.first);
        _status = _statuses.contains('Confirmed')
            ? 'Confirmed'
            : (_statuses.isEmpty ? '' : _statuses.first);
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

  Future<void> _pickFiles() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        allowMultiple: true,
        withData: true,
        type: FileType.custom,
        allowedExtensions: ['pdf', 'dwg', 'dxf'],
      );
      if (!mounted || result == null) return;
      final files = result.files.where((file) => file.bytes != null).toList();
      if (files.isEmpty) {
        _show('The selected file could not be read. Please choose it again.');
        return;
      }
      setState(() => _files = [..._files, ...files]);
    } catch (e) {
      if (mounted) _show(_message(e));
    }
  }

  Future<void> _pickPhotos() async {
    try {
      final images = await ImagePicker().pickMultiImage(
        imageQuality: 85,
        maxWidth: 1800,
        requestFullMetadata: false,
      );
      if (images.isEmpty) return;

      final photos = <PlatformFile>[];
      for (final image in images) {
        final bytes = await image.readAsBytes();
        if (bytes.length > 10 * 1024 * 1024) {
          if (mounted) _show('${image.name} is larger than 10 MB.');
          continue;
        }
        final extension = image.name.split('.').last.toLowerCase();
        if (!const ['jpg', 'jpeg', 'png', 'webp'].contains(extension)) {
          if (mounted) {
            _show('${image.name} must be a JPG, PNG, or WebP image.');
          }
          continue;
        }
        photos.add(
          PlatformFile(name: image.name, size: bytes.length, bytes: bytes),
        );
      }
      if (!mounted || photos.isEmpty) return;
      setState(() {
        _files = [..._files, ...photos];
        _fileType = 'photo';
      });
    } catch (e) {
      if (mounted) _show(_message(e));
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false) || _saving) return;
    if (_categoryId == null) {
      _show('Select an order category.');
      return;
    }
    if (_categoryId == 0 && _newCategory.text.trim().isEmpty) {
      _show('Enter the new jewellery category.');
      return;
    }
    if (_orderType != 'Repair' && _items.isEmpty) {
      _show('Add at least one order item.');
      return;
    }
    if (_designType == 'Repeat' &&
        _items
            .where((item) => _orderType != 'Repair' || item.hasContent)
            .any((item) => item.designId == null)) {
      _show('Select a design code for every repeat-order item.');
      return;
    }
    if (_goldRateStatus == 'Fixed' && (_decimal(_goldRate.text) ?? 0) <= 0) {
      _show('Enter the fixed gold rate per gram.');
      return;
    }
    final approximate = _decimal(_approximatePrice.text);
    final advance = _decimal(_advanceAmount.text) ?? 0;
    if (approximate != null && advance > approximate) {
      _show('Advance amount cannot exceed approximate price.');
      return;
    }

    setState(() => _saving = true);
    try {
      final attachments = <Map<String, dynamic>>[];
      for (final file in _files) {
        attachments.add({
          'name': file.name,
          'extension': file.extension ?? '',
          'file_type': _fileType,
          'base64': base64Encode(file.bytes!),
        });
      }
      final payload = <String, dynamic>{
        'order_name': _orderName.text.trim(),
        'order_received_date': _date(_receivedDate),
        'order_category_id': _categoryId,
        'new_order_category': _categoryId == 0 ? _newCategory.text.trim() : '',
        'order_type': _orderType,
        'order_design_type': _designType,
        'order_from': _orderFrom.text.trim(),
        'customer_id': _customerId ?? 0,
        'contact_number': _contact.text.trim(),
        'material_category': _material,
        'sales_person_user_id': _salesPersonId ?? 0,
        'priority': _priority,
        'status': _status,
        'priority_level': int.tryParse(_priorityLevel.text.trim()) ?? 0,
        'whatsapp_notification_number': _whatsappNumber.text.trim(),
        'whatsapp_notify_order_created': _notifyWhatsapp,
        'expected_diamond_spec': _diamondSpec.text.trim(),
        'expected_stone_spec': _stoneSpec.text.trim(),
        'order_notes': _notes.text.trim(),
        'certificate_requirement': _certificate,
        'gold_rate_block_status': _goldRateStatus,
        'gold_rate_per_gm': _goldRateStatus == 'Fixed'
            ? _decimal(_goldRate.text)
            : null,
        'approximate_price': approximate,
        'advance_amount': advance,
        'due_date': _dueDate == null ? '' : _date(_dueDate!),
        'additional_details': _additionalDetails.text.trim(),
        'items': _orderType == 'Repair'
            ? _items
                  .where((item) => item.hasContent)
                  .map((item) => item.payload(_designType))
                  .toList()
            : _items.map((item) => item.payload(_designType)).toList(),
        'attachments': attachments,
      };
      if (_orderType == 'Repair') {
        payload.addAll({
          'repair_ornament_details': _repairOrnament.text.trim(),
          'repair_work_details': _repairWork.text.trim(),
          'repair_receive_weight_gm': _decimal(_repairWeight.text),
          'repair_received_at': _date(_repairReceivedDate),
        });
      }
      final result = await widget.api.createOrder(payload);
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
      appBar: AppBar(title: const Text('Create Order')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error.isNotEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  _section('Order & Customer Details', [
                    _requiredText(_orderName, 'Order Name'),
                    _dateField(
                      'Order Received Date',
                      _receivedDate,
                      (value) => setState(() => _receivedDate = value),
                    ),
                    _dropdown<int>(
                      'Jewellery / Sub Category',
                      _categoryId,
                      [
                        ..._categories.map(
                          (row) => DropdownMenuItem(
                            value: _id(row),
                            child: Text('${row['name']} (${row['code']})'),
                          ),
                        ),
                        const DropdownMenuItem(
                          value: 0,
                          child: Text('+ Add New Category'),
                        ),
                      ],
                      (value) => setState(() => _categoryId = value),
                    ),
                    if (_categoryId == 0)
                      _requiredText(_newCategory, 'New Jewellery Category'),
                    _dropdown<String>(
                      'Order Type',
                      _orderType,
                      const [
                        DropdownMenuItem(value: 'Sales', child: Text('Sales')),
                        DropdownMenuItem(
                          value: 'Manufacturing',
                          child: Text('Manufacturing'),
                        ),
                        DropdownMenuItem(
                          value: 'Repair',
                          child: Text('Repair'),
                        ),
                      ],
                      (value) => setState(() => _orderType = value ?? 'Sales'),
                    ),
                    _dropdown<String>(
                      'Fresh / Repeat',
                      _designType,
                      const [
                        DropdownMenuItem(
                          value: 'Fresh',
                          child: Text('Fresh Order'),
                        ),
                        DropdownMenuItem(
                          value: 'Repeat',
                          child: Text('Repeat Existing Design'),
                        ),
                      ],
                      (value) => setState(() => _designType = value ?? 'Fresh'),
                    ),
                    _text(_orderFrom, 'Order From'),
                    _dropdown<int?>(
                      'Customer',
                      _customerId,
                      [
                        const DropdownMenuItem<int?>(
                          value: null,
                          child: Text('Select customer (optional)'),
                        ),
                        ..._customers.map(
                          (row) => DropdownMenuItem<int?>(
                            value: _id(row),
                            child: Text('${row['name']}'),
                          ),
                        ),
                      ],
                      _customerChanged,
                      required: false,
                    ),
                    _text(
                      _contact,
                      'Contact Number',
                      keyboard: TextInputType.phone,
                    ),
                    _dropdown<String>(
                      'Material Category',
                      _material,
                      _materials
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(value),
                            ),
                          )
                          .toList(),
                      (value) => setState(() => _material = value ?? 'Gold'),
                    ),
                    _dropdown<int?>(
                      'Sales Person',
                      _salesPersonId,
                      [
                        const DropdownMenuItem<int?>(
                          value: null,
                          child: Text('Select sales person (optional)'),
                        ),
                        ..._filteredSalesPeople.map(
                          (row) => DropdownMenuItem<int?>(
                            value: _id(row),
                            child: Text(
                              '${row['name']} · ${row['mobile'] ?? ''}',
                            ),
                          ),
                        ),
                      ],
                      (value) => setState(() => _salesPersonId = value),
                      required: false,
                    ),
                    _dropdown<String>(
                      'Priority',
                      _priority,
                      _priorities
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(value),
                            ),
                          )
                          .toList(),
                      (value) => setState(() => _priority = value ?? ''),
                    ),
                    _dropdown<String>(
                      'Current Status',
                      _status,
                      _statuses
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(value),
                            ),
                          )
                          .toList(),
                      (value) => setState(() => _status = value ?? ''),
                    ),
                    _text(
                      _priorityLevel,
                      'Priority Level (0–10)',
                      keyboard: TextInputType.number,
                      validator: (value) {
                        final level = int.tryParse(value ?? '');
                        return level == null || level < 0 || level > 10
                            ? 'Enter 0 to 10'
                            : null;
                      },
                    ),
                    _text(
                      _whatsappNumber,
                      'WhatsApp Notification No',
                      keyboard: TextInputType.phone,
                    ),
                    SwitchListTile.adaptive(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Queue WhatsApp on save'),
                      value: _notifyWhatsapp,
                      onChanged: (value) =>
                          setState(() => _notifyWhatsapp = value),
                    ),
                    _text(_diamondSpec, 'Expected Diamond Details', lines: 2),
                    _text(
                      _stoneSpec,
                      'Expected Stone / Other Details',
                      lines: 2,
                    ),
                    _text(_notes, 'General Order Notes', lines: 2),
                  ]),
                  if (_orderType == 'Repair')
                    _section('Repair Intake Details', [
                      _requiredText(
                        _repairOrnament,
                        'Ornament Received Details',
                        lines: 2,
                      ),
                      _requiredText(
                        _repairWork,
                        'Repair Work Details',
                        lines: 2,
                      ),
                      _requiredNumber(_repairWeight, 'Receive Weight (gm)'),
                      _dateField(
                        'Received Date',
                        _repairReceivedDate,
                        (value) => setState(() => _repairReceivedDate = value),
                      ),
                    ]),
                  _itemSection(),
                  _section('Certificate, Pricing & Delivery', [
                    _dropdown<String>(
                      'Certificate Requirement',
                      _certificate,
                      _certificates
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(
                                value.isEmpty
                                    ? 'No certificate required'
                                    : value,
                              ),
                            ),
                          )
                          .toList(),
                      (value) => setState(() => _certificate = value ?? ''),
                    ),
                    _dropdown<String>(
                      'Gold Rate Block',
                      _goldRateStatus,
                      const [
                        DropdownMenuItem(
                          value: 'Not Fixed',
                          child: Text('Not Fixed'),
                        ),
                        DropdownMenuItem(value: 'Fixed', child: Text('Fixed')),
                      ],
                      (value) => setState(
                        () => _goldRateStatus = value ?? 'Not Fixed',
                      ),
                    ),
                    if (_goldRateStatus == 'Fixed')
                      _requiredNumber(_goldRate, 'Fixed Gold Rate / gm'),
                    _number(_approximatePrice, 'Approximate Price'),
                    _number(_advanceAmount, 'Advance Amount'),
                    InputDecorator(
                      decoration: const InputDecoration(
                        labelText: 'Approximate Balance',
                        border: OutlineInputBorder(),
                        filled: true,
                      ),
                      child: Text(
                        '₹${((_decimal(_approximatePrice.text) ?? 0) - (_decimal(_advanceAmount.text) ?? 0)).clamp(0, double.infinity).toStringAsFixed(2)}',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                    _optionalDateField(
                      'Client Delivery Date',
                      _dueDate,
                      (value) => setState(() => _dueDate = value),
                    ),
                    _text(
                      _additionalDetails,
                      'Additional Details / Finish Instructions',
                      lines: 3,
                    ),
                  ]),
                  _section('Reference Images & Files', [
                    OutlinedButton.icon(
                      onPressed: _pickPhotos,
                      icon: const Icon(Icons.photo_library_outlined),
                      label: const Text('Choose photos'),
                    ),
                    OutlinedButton.icon(
                      onPressed: _pickFiles,
                      icon: const Icon(Icons.attach_file),
                      label: const Text('Choose PDF / CAD file'),
                    ),
                    if (_files.isNotEmpty)
                      Text('${_files.length} file(s) selected'),
                    if (_files.isNotEmpty)
                      ..._files.map(
                        (file) => ListTile(
                          dense: true,
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.insert_drive_file_outlined),
                          title: Text(file.name),
                          trailing: IconButton(
                            icon: const Icon(Icons.close),
                            onPressed: () =>
                                setState(() => _files.remove(file)),
                          ),
                        ),
                      ),
                    _dropdown<String>(
                      'Attachment Type',
                      _fileType,
                      const [
                        DropdownMenuItem(
                          value: 'reference',
                          child: Text('Reference'),
                        ),
                        DropdownMenuItem(value: 'cad', child: Text('CAD')),
                        DropdownMenuItem(value: 'photo', child: Text('Photo')),
                        DropdownMenuItem(
                          value: 'approval',
                          child: Text('Approval'),
                        ),
                      ],
                      (value) =>
                          setState(() => _fileType = value ?? 'reference'),
                    ),
                  ]),
                  FilledButton.icon(
                    onPressed: _saving ? null : _submit,
                    icon: _saving
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.check_circle_outline),
                    label: const Text('Save Order'),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                ],
              ),
            ),
    );
  }

  Widget _itemSection() {
    return _section('Jewellery Specifications', [
      ..._items.asMap().entries.map((entry) {
        final index = entry.key;
        final item = entry.value;
        return Card(
          margin: const EdgeInsets.only(bottom: AppSpacing.md),
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Item ${index + 1}',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                    IconButton(
                      onPressed: _items.length == 1
                          ? null
                          : () => setState(() {
                              _items.removeAt(index).dispose();
                            }),
                      icon: const Icon(Icons.delete_outline),
                    ),
                  ],
                ),
                if (_designType == 'Repeat')
                  _dropdown<int?>(
                    'Unique Design Code',
                    item.designId,
                    [
                      const DropdownMenuItem<int?>(
                        value: null,
                        child: Text('Select design'),
                      ),
                      ..._designs.map(
                        (row) => DropdownMenuItem<int?>(
                          value: _id(row),
                          child: Text('${row['design_code']} - ${row['name']}'),
                        ),
                      ),
                    ],
                    (value) => setState(() => item.designId = value),
                    required: false,
                  ),
                _dropdown<int?>(
                  'Gold Purity',
                  item.purityId,
                  [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('Select purity (optional)'),
                    ),
                    ..._purities.map(
                      (row) => DropdownMenuItem<int?>(
                        value: _id(row),
                        child: Text(
                          '${row['purity_code']} (${row['purity_percent']}%) ${row['color_name'] ?? ''}',
                        ),
                      ),
                    ),
                  ],
                  (value) => setState(() => item.purityId = value),
                  required: false,
                ),
                _text(
                  item.description,
                  'Description',
                  validator: (value) =>
                      _orderType != 'Repair' &&
                          _designType == 'Fresh' &&
                          (value ?? '').trim().isEmpty
                      ? 'Description is required for a fresh order'
                      : null,
                ),
                _text(item.size, 'Size / Length'),
                _text(
                  item.qty,
                  'Qty',
                  keyboard: TextInputType.number,
                  validator: (value) => (int.tryParse(value ?? '') ?? 0) <= 0
                      ? 'Enter a quantity greater than 0'
                      : null,
                ),
                _number(item.gold, 'Gold Required (gm)'),
                _number(item.diamond, 'Diamond Required (cts)'),
              ],
            ),
          ),
        );
      }),
      OutlinedButton.icon(
        onPressed: () => setState(() => _items.add(_OrderItemForm())),
        icon: const Icon(Icons.add),
        label: const Text('Add Item Row'),
      ),
    ]);
  }

  Widget _section(String title, List<Widget> children) => Container(
    margin: const EdgeInsets.only(bottom: AppSpacing.lg),
    padding: const EdgeInsets.all(AppSpacing.lg),
    decoration: BoxDecoration(
      color: AppColors.card,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      border: Border.all(color: AppColors.border),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: AppSpacing.md),
        ...children.map(
          (child) => Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.md),
            child: child,
          ),
        ),
      ],
    ),
  );

  Widget _text(
    TextEditingController controller,
    String label, {
    int lines = 1,
    TextInputType? keyboard,
    String? Function(String?)? validator,
  }) => TextFormField(
    controller: controller,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    maxLines: lines,
    keyboardType: keyboard,
    validator: validator,
  );

  Widget _requiredText(
    TextEditingController controller,
    String label, {
    int lines = 1,
  }) => _text(
    controller,
    label,
    lines: lines,
    validator: (value) =>
        (value ?? '').trim().isEmpty ? '$label is required' : null,
  );
  Widget _number(TextEditingController controller, String label) => _text(
    controller,
    label,
    keyboard: const TextInputType.numberWithOptions(decimal: true),
    validator: (value) {
      if ((value ?? '').trim().isEmpty) return null;
      final number = _decimal(value!);
      return number == null || number < 0
          ? 'Enter a valid non-negative number'
          : null;
    },
  );
  Widget _requiredNumber(TextEditingController controller, String label) =>
      _text(
        controller,
        label,
        keyboard: const TextInputType.numberWithOptions(decimal: true),
        validator: (value) => (_decimal(value ?? '') ?? 0) <= 0
            ? 'Enter a number greater than 0'
            : null,
      );

  Widget _dropdown<T>(
    String label,
    T? value,
    List<DropdownMenuItem<T>> items,
    ValueChanged<T?> changed, {
    bool required = true,
  }) => DropdownButtonFormField<T>(
    initialValue: value,
    isExpanded: true,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    items: items,
    onChanged: changed,
    validator: (selected) =>
        required && selected == null ? '$label is required' : null,
  );

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
    ValueChanged<DateTime?> changed,
  ) => ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 12),
    shape: RoundedRectangleBorder(
      side: const BorderSide(color: AppColors.border),
      borderRadius: BorderRadius.circular(4),
    ),
    title: Text(label),
    subtitle: Text(value == null ? 'Not selected' : _date(value)),
    trailing: value == null
        ? const Icon(Icons.calendar_today_outlined)
        : IconButton(
            icon: const Icon(Icons.close),
            onPressed: () => changed(null),
          ),
    onTap: () async {
      final selected = await showDatePicker(
        context: context,
        initialDate: value ?? DateTime.now(),
        firstDate: DateTime(2000),
        lastDate: DateTime(2100),
      );
      if (selected != null) changed(selected);
    },
  );

  void _customerChanged(int? value) {
    final row = _customers
        .cast<Map>()
        .where((item) => _id(item) == value)
        .firstOrNull;
    setState(() {
      _customerId = value;
      _salesPersonId = null;
      if (_contact.text.trim().isEmpty && row != null)
        _contact.text = (row['phone'] ?? '').toString();
    });
  }

  List<dynamic> get _filteredSalesPeople => _customerId == null
      ? []
      : _salesPeople
            .where(
              (row) =>
                  _id(row) == _salesPersonId ||
                  _asInt((row as Map)['customer_id']) == _customerId,
            )
            .toList();
  List<String> _strings(dynamic value) =>
      (value as List? ?? []).map((item) => item.toString()).toList();
  int _id(dynamic row) => _asInt((row as Map)['id']) ?? 0;
  int? _asInt(dynamic value) =>
      value is num ? value.toInt() : int.tryParse(value?.toString() ?? '');
  double? _decimal(String value) =>
      value.trim().isEmpty ? null : double.tryParse(value.trim());
  String _date(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
  String _message(Object error) =>
      error.toString().replaceFirst('Exception: ', '');
  void _show(String message) => ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(message)));
}

class _OrderItemForm {
  int? designId;
  int? purityId;
  final description = TextEditingController();
  final size = TextEditingController();
  final qty = TextEditingController(text: '1');
  final gold = TextEditingController(text: '0');
  final diamond = TextEditingController(text: '0');

  bool get hasContent =>
      description.text.trim().isNotEmpty ||
      designId != null ||
      purityId != null;

  Map<String, dynamic> payload(String designType) => {
    'design_id': designType == 'Repeat' ? designId : null,
    'gold_purity_id': purityId,
    'item_description': description.text.trim(),
    'size_label': size.text.trim(),
    'qty': int.tryParse(qty.text.trim()) ?? 1,
    'gold_required_gm': double.tryParse(gold.text.trim()) ?? 0,
    'diamond_required_cts': double.tryParse(diamond.text.trim()) ?? 0,
  };

  void dispose() {
    description.dispose();
    size.dispose();
    qty.dispose();
    gold.dispose();
    diamond.dispose();
  }
}
