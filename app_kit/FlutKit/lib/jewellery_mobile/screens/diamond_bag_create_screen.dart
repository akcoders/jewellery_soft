import 'dart:convert';

import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

class DiamondBagCreateScreen extends StatefulWidget {
  const DiamondBagCreateScreen({
    super.key,
    required this.api,
    this.orderId,
    this.workRequestId,
  });

  final MobileApiService api;
  final int? orderId;
  final int? workRequestId;

  @override
  State<DiamondBagCreateScreen> createState() => _DiamondBagCreateScreenState();
}

class _DiamondBagCreateScreenState extends State<DiamondBagCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _notes = TextEditingController();
  final List<_BagLine> _lines = [_BagLine()];
  bool _loading = true;
  bool _saving = false;
  bool _pickingPhoto = false;
  String _error = '';
  int? _orderId;
  int? _locationId;
  DateTime _preparedDate = DateTime.now();
  List<dynamic> _orders = [];
  List<dynamic> _inventoryItems = [];
  List<dynamic> _shapes = [];
  List<dynamic> _sizes = [];
  List<dynamic> _groups = [];
  List<dynamic> _locations = [];
  XFile? _photo;

  @override
  void initState() {
    super.initState();
    _orderId = widget.orderId;
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
      final data = await widget.api.fetchDiamondBagForm(
        orderId: widget.orderId,
        workRequestId: widget.workRequestId,
      );
      final lookups = _map(data['lookups']);
      if (!mounted) return;
      setState(() {
        _orders = _list(data['orders']);
        _inventoryItems = _list(lookups['inventory_items']);
        _shapes = _list(lookups['shapes']);
        _sizes = _list(lookups['sizes']);
        _groups = _list(lookups['chalni_groups']);
        _locations = _list(lookups['locations']);
        if (_locationId == null && _locations.isNotEmpty) {
          _locationId = _int(_map(_locations.first)['id']);
        }
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString().replaceFirst('Exception: ', '');
      });
    }
  }

  Future<void> _pickPhoto() async {
    if (_pickingPhoto || _saving) return;
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Take photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null || !mounted) return;
    setState(() => _pickingPhoto = true);
    try {
      final photo = await ImagePicker().pickImage(
        source: source,
        imageQuality: 82,
        maxWidth: 1800,
        requestFullMetadata: false,
      );
      if (photo == null || !mounted) return;
      if (await photo.length() > 4 * 1024 * 1024) {
        _message('Bag photo must be smaller than 4 MB.');
        return;
      }
      setState(() => _photo = photo);
    } finally {
      if (mounted) setState(() => _pickingPhoto = false);
    }
  }

  Future<void> _submit() async {
    if (_saving ||
        _pickingPhoto ||
        !(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    if ((_orderId ?? 0) <= 0 || (_locationId ?? 0) <= 0) {
      _message('Select an order and inventory location.');
      return;
    }
    setState(() => _saving = true);
    try {
      final result = await widget.api.createDiamondBag(
        orderId: _orderId!,
        locationId: _locationId!,
        preparedDate: _date(_preparedDate),
        workRequestId: widget.workRequestId,
        notes: _notes.text,
        imageBase64: _photo == null ? '' : await _imageData(_photo!),
        items: _lines
            .map(
              (line) => {
                'inventory_item_id': line.itemId,
                'shape_master_id': line.shapeId,
                'chalni_group_id': line.groupId,
                'size_master_id': line.sizeId,
                'pcs': int.tryParse(line.pcs.text.trim()) ?? 0,
                'weight_cts': double.tryParse(line.cts.text.trim()) ?? 0,
              },
            )
            .toList(),
      );
      if (!mounted) return;
      final bag = _map(result['bag']);
      _message('Diamond bag #${bag['id'] ?? '-'} created successfully.');
      Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) _message(e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Create Diamond Bag')),
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
                        padding: const EdgeInsets.all(AppSpacing.md),
                        child: Text(
                          'Diamond request #${widget.workRequestId} · creating this bag will complete the assigned task.',
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                  ],
                  DropdownButtonFormField<int>(
                    initialValue: _orderId,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Diamond / Jadau Order *',
                      border: OutlineInputBorder(),
                    ),
                    items: _orders.map((raw) {
                      final order = _map(raw);
                      return DropdownMenuItem<int>(
                        value: _int(order['id']),
                        child: Text(
                          '${order['order_no'] ?? '-'} · ${order['order_name'] ?? ''} · ${order['material_category'] ?? order['order_category_name'] ?? ''}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      );
                    }).toList(),
                    validator: (value) => value == null ? 'Select order' : null,
                    onChanged: widget.orderId == null
                        ? (value) => setState(() => _orderId = value)
                        : null,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  DropdownButtonFormField<int>(
                    initialValue: _locationId,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Inventory Location *',
                      border: OutlineInputBorder(),
                    ),
                    items: _locations.map((raw) {
                      final location = _map(raw);
                      return DropdownMenuItem<int>(
                        value: _int(location['id']),
                        child: Text('${location['name'] ?? '-'}'),
                      );
                    }).toList(),
                    validator: (value) =>
                        value == null ? 'Select location' : null,
                    onChanged: (value) => setState(() => _locationId = value),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  OutlinedButton.icon(
                    onPressed: () async {
                      final date = await showDatePicker(
                        context: context,
                        initialDate: _preparedDate,
                        firstDate: DateTime.now().subtract(
                          const Duration(days: 30),
                        ),
                        lastDate: DateTime.now().add(const Duration(days: 30)),
                      );
                      if (date != null) setState(() => _preparedDate = date);
                    },
                    icon: const Icon(Icons.calendar_today_outlined),
                    label: Text('Prepared on ${_date(_preparedDate)}'),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  OutlinedButton.icon(
                    onPressed: _saving || _pickingPhoto ? null : _pickPhoto,
                    icon: _pickingPhoto
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.add_a_photo_outlined),
                    label: Text(
                      _photo == null ? 'Add optional bag photo' : _photo!.name,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  const Text(
                    'CHALNI GROUP-WISE BAG ITEMS',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppColors.textSecondary,
                      letterSpacing: .5,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  ...List.generate(_lines.length, _lineCard),
                  OutlinedButton.icon(
                    onPressed: () => setState(() => _lines.add(_BagLine())),
                    icon: const Icon(Icons.add),
                    label: const Text('Add Bag Row'),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextFormField(
                    controller: _notes,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Bag Notes',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  FilledButton.icon(
                    onPressed: _saving || _pickingPhoto ? null : _submit,
                    icon: _saving
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.inventory_2_outlined),
                    label: const Text('Create Diamond Bag'),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                ],
              ),
            ),
    );
  }

  Widget _lineCard(int index) {
    final line = _lines[index];
    final sizes = _sizes.where((raw) {
      final size = _map(raw);
      return _int(size['shape_id']) == line.shapeId &&
          _int(size['group_id']) == line.groupId;
    }).toList();
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
                    'Diamond row ${index + 1}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                if (_lines.length > 1)
                  IconButton(
                    onPressed: () => setState(() {
                      _lines.removeAt(index).dispose();
                    }),
                    icon: const Icon(Icons.delete_outline),
                  ),
              ],
            ),
            DropdownButtonFormField<int>(
              key: ValueKey('bag_item_${index}_${line.itemId ?? 0}'),
              initialValue: line.itemId,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Diamond Product *',
                border: OutlineInputBorder(),
              ),
              items: _inventoryItems.map((raw) {
                final item = _map(raw);
                final free =
                    _double(item['carat_balance']) -
                    _double(item['bagged_cts']);
                return DropdownMenuItem<int>(
                  value: _int(item['id']),
                  child: Text(
                    '${item['diamond_type'] ?? '-'} · ${item['color'] ?? ''} ${item['clarity'] ?? ''} · ${free.toStringAsFixed(3)} cts free',
                    overflow: TextOverflow.ellipsis,
                  ),
                );
              }).toList(),
              validator: (value) => value == null ? 'Select product' : null,
              onChanged: (value) => setState(() => line.itemId = value),
            ),
            const SizedBox(height: AppSpacing.sm),
            DropdownButtonFormField<int>(
              key: ValueKey('bag_shape_${index}_${line.shapeId ?? 0}'),
              initialValue: line.shapeId,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Shape *',
                border: OutlineInputBorder(),
              ),
              items: _shapes.map((raw) {
                final shape = _map(raw);
                return DropdownMenuItem<int>(
                  value: _int(shape['id']),
                  child: Text('${shape['name'] ?? '-'}'),
                );
              }).toList(),
              validator: (value) => value == null ? 'Select shape' : null,
              onChanged: (value) => setState(() {
                line.shapeId = value;
                line.groupId = null;
                line.sizeId = null;
              }),
            ),
            const SizedBox(height: AppSpacing.sm),
            DropdownButtonFormField<int>(
              key: ValueKey(
                'bag_group_${index}_${line.shapeId ?? 0}_${line.groupId ?? 0}',
              ),
              initialValue: line.groupId,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Chalni Group *',
                border: OutlineInputBorder(),
              ),
              items: _groups.map((raw) {
                final group = _map(raw);
                final range = (group['range_label'] ?? '').toString().trim();
                return DropdownMenuItem<int>(
                  value: _int(group['id']),
                  child: Text(
                    '${group['name'] ?? '-'}${range.isEmpty ? '' : ' · $range'}',
                    overflow: TextOverflow.ellipsis,
                  ),
                );
              }).toList(),
              validator: (value) =>
                  value == null ? 'Select chalni group' : null,
              onChanged: line.shapeId == null
                  ? null
                  : (value) => setState(() {
                      line.groupId = value;
                      line.sizeId = null;
                    }),
            ),
            const SizedBox(height: AppSpacing.sm),
            DropdownButtonFormField<int>(
              key: ValueKey(
                'bag_size_${index}_${line.shapeId ?? 0}_${line.sizeId ?? 0}',
              ),
              initialValue:
                  sizes.any((raw) => _int(_map(raw)['id']) == line.sizeId)
                  ? line.sizeId
                  : null,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Exact Chalni Size (optional)',
                border: OutlineInputBorder(),
              ),
              items: sizes.map((raw) {
                final size = _map(raw);
                return DropdownMenuItem<int>(
                  value: _int(size['id']),
                  child: Text(
                    '${size['size_label'] ?? size['size_code'] ?? '-'}',
                    overflow: TextOverflow.ellipsis,
                  ),
                );
              }).toList(),
              hint: const Text('Use chalni group'),
              onChanged: (value) => setState(() => line.sizeId = value),
            ),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: line.shapeId == null || line.groupId == null
                    ? null
                    : () => _createExactSize(line),
                icon: const Icon(Icons.add_circle_outline, size: 19),
                label: const Text('Exact size not listed? Create it'),
              ),
            ),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: line.pcs,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'PCS *',
                      border: OutlineInputBorder(),
                    ),
                    validator: (value) {
                      final pcs = int.tryParse(value?.trim() ?? '');
                      return pcs == null || pcs <= 0
                          ? 'Whole PCS required'
                          : null;
                    },
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: TextFormField(
                    controller: line.cts,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: 'Carats *',
                      border: OutlineInputBorder(),
                    ),
                    validator: (value) {
                      final cts = double.tryParse(value?.trim() ?? '');
                      return cts == null || cts <= 0 ? 'CTS required' : null;
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<String> _imageData(XFile file) async {
    final extension = file.name.split('.').last.toLowerCase();
    final mime = switch (extension) {
      'png' => 'image/png',
      'webp' => 'image/webp',
      _ => 'image/jpeg',
    };
    return 'data:$mime;base64,${base64Encode(await file.readAsBytes())}';
  }

  Future<void> _createExactSize(_BagLine line) async {
    final controller = TextEditingController();
    final label = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Create Exact Diamond Size'),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLength: 80,
          decoration: const InputDecoration(
            labelText: 'Exact size / sieve label',
            hintText: 'Example: 1.20 mm',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              final value = controller.text.trim();
              if (value.isNotEmpty) Navigator.pop(context, value);
            },
            child: const Text('Create'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (label == null || !mounted) return;
    try {
      final data = await widget.api.createDiamondBagSize(
        shapeId: line.shapeId!,
        chalniGroupId: line.groupId!,
        sizeLabel: label,
      );
      final size = _map(data['size']);
      if (!mounted || size.isEmpty) return;
      setState(() {
        _sizes.add(size);
        line.sizeId = _int(size['id']);
      });
      _message('Exact size created.');
    } catch (e) {
      if (mounted) _message(e.toString().replaceFirst('Exception: ', ''));
    }
  }

  void _message(String message) => ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(message)));
}

class _BagLine {
  int? itemId;
  int? shapeId;
  int? groupId;
  int? sizeId;
  final pcs = TextEditingController();
  final cts = TextEditingController();

  void dispose() {
    pcs.dispose();
    cts.dispose();
  }
}

int _int(dynamic value) {
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

double _double(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}

Map<String, dynamic> _map(dynamic value) {
  if (value is Map) return value.cast<String, dynamic>();
  return <String, dynamic>{};
}

List<dynamic> _list(dynamic value) => value is List ? value : <dynamic>[];

String _date(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
