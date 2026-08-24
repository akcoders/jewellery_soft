import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';

class TransactionCreateScreen extends StatefulWidget {
  const TransactionCreateScreen({
    super.key,
    required this.api,
    required this.title,
    required this.material,
    required this.action,
    required this.accentColor,
  });

  final MobileApiService api;
  final String title;
  final String material; // diamond | gold | stone
  final String action; // purchase | issue | return
  final Color accentColor;

  @override
  State<TransactionCreateScreen> createState() =>
      _TransactionCreateScreenState();
}

class _TransactionCreateScreenState extends State<TransactionCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _loading = true;
  bool _saving = false;
  String _error = '';

  DateTime _txnDate = DateTime.now();
  String _purpose = 'Jobwork';
  String _notes = '';
  String _invoiceNo = '';
  String _supplierName = '';
  String _taxPercent = '';
  String _dueDate = '';

  int? _karigarId;
  int? _locationId;
  int? _vendorId;
  int? _issueId;

  List<dynamic> _karigars = [];
  List<dynamic> _locations = [];
  List<dynamic> _vendors = [];
  List<dynamic> _items = [];
  List<dynamic> _issueRefs = [];

  XFile? _attachment;

  final List<_LineForm> _lines = [];

  @override
  void initState() {
    super.initState();
    _lines.add(
      _LineForm(
        material: widget.material,
        includePcs: widget.action == 'issue',
      ),
    );
    _loadLookups();
  }

  @override
  void dispose() {
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> _loadLookups() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      if (widget.material == 'diamond') {
        _items = await widget.api.fetchDiamondItems();
      } else if (widget.material == 'gold') {
        _items = await widget.api.fetchGoldItems();
      } else {
        _items = await widget.api.fetchStoneItems();
      }

      if (widget.action == 'purchase') {
        _vendors = await widget.api.fetchVendors();
      } else {
        _karigars = await widget.api.fetchKarigars();
        _locations = await widget.api.fetchLocations();
        await _loadIssueRefs();
      }
    } catch (e) {
      _error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _loadIssueRefs() async {
    if (widget.action != 'return') return;
    if (widget.material == 'diamond') {
      _issueRefs = await widget.api.fetchDiamondIssueRefs();
    } else if (widget.material == 'gold') {
      _issueRefs = await widget.api.fetchGoldIssueRefs();
    } else {
      _issueRefs = await widget.api.fetchStoneIssueRefs();
    }
  }

  Future<void> _pickAttachment() async {
    final picker = ImagePicker();
    final file = await picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (!mounted) return;
    setState(() => _attachment = file);
  }

  Future<String> _attachmentBase64() async {
    if (_attachment == null) return '';
    final bytes = await File(_attachment!.path).readAsBytes();
    return base64Encode(bytes);
  }

  Future<void> _submit() async {
    final valid = _formKey.currentState?.validate() ?? false;
    if (!valid || _saving) return;

    if (widget.action != 'purchase') {
      if (_locationId == null ||
          (widget.action == 'issue' && _karigarId == null)) {
        _showError(
          widget.action == 'issue'
              ? 'Karigar and location are required.'
              : 'Location is required.',
        );
        return;
      }
      if (widget.action == 'return' && _issueId == null) {
        _showError('Issue reference is required for return.');
        return;
      }
    }

    if (_lines.isEmpty) {
      _showError('Add at least one line item.');
      return;
    }
    if (widget.action != 'purchase' && _attachment == null) {
      _showError('Attachment is required.');
      return;
    }

    setState(() => _saving = true);
    try {
      final linesPayload = _lines.map((line) => line.toPayload()).toList();
      final base64Attachment = await _attachmentBase64();
      final payload = <String, dynamic>{'lines': linesPayload};

      if (widget.action == 'purchase') {
        payload['purchase_date'] = _formatDate(_txnDate);
        payload['vendor_id'] = _vendorId ?? 0;
        payload['supplier_name'] = _supplierName.trim();
        payload['invoice_no'] = _invoiceNo.trim();
        payload['notes'] = _notes.trim();
        if (widget.material == 'diamond') {
          payload['tax_percentage'] = double.tryParse(_taxPercent) ?? 0;
          payload['due_date'] = _dueDate.trim();
        }
      } else if (widget.action == 'issue') {
        payload['issue_date'] = _formatDate(_txnDate);
        payload['karigar_id'] = _karigarId;
        payload['location_id'] = _locationId;
        payload['purpose'] = _purpose.trim();
        payload['notes'] = _notes.trim();
        payload['attachment_base64'] = base64Attachment;
      } else {
        payload['return_date'] = _formatDate(_txnDate);
        payload['issue_id'] = _issueId;
        payload['karigar_id'] = _karigarId ?? 0;
        payload['location_id'] = _locationId;
        payload['purpose'] = _purpose.trim();
        payload['notes'] = _notes.trim();
        payload['attachment_base64'] = base64Attachment;
      }

      if (widget.material == 'diamond') {
        if (widget.action == 'purchase') {
          await widget.api.createDiamondPurchase(payload);
        } else if (widget.action == 'issue') {
          await widget.api.createDiamondIssue(payload);
        } else {
          await widget.api.createDiamondReturn(payload);
        }
      } else if (widget.material == 'gold') {
        if (widget.action == 'purchase') {
          await widget.api.createGoldPurchase(payload);
        } else if (widget.action == 'issue') {
          await widget.api.createGoldIssue(payload);
        } else {
          await widget.api.createGoldReturn(payload);
        }
      } else {
        if (widget.action == 'purchase') {
          await widget.api.createStonePurchase(payload);
        } else if (widget.action == 'issue') {
          await widget.api.createStoneIssue(payload);
        } else {
          await widget.api.createStoneReturn(payload);
        }
      }

      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      _showError(e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  String _formatDate(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  int? _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error.isNotEmpty
          ? AppErrorState(message: _error, onRetry: _loadLookups)
          : Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Form(
                key: _formKey,
                child: ListView(
                  children: [
                    AppSectionTitle(widget.title),
                    const SizedBox(height: AppSpacing.md),
                    _buildBasicSection(),
                    const SizedBox(height: AppSpacing.lg),
                    _buildLinesSection(),
                    const SizedBox(height: AppSpacing.lg),
                    _buildAttachmentSection(),
                    const SizedBox(height: AppSpacing.lg),
                    SafeArea(
                      top: false,
                      child: SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          style: FilledButton.styleFrom(
                            backgroundColor: widget.accentColor,
                          ),
                          onPressed: _saving ? null : _submit,
                          icon: _saving
                              ? const SizedBox(
                                  width: 16,
                                  height: 16,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Icon(Icons.save),
                          label: Text(_saving ? 'Saving...' : 'Save'),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildBasicSection() {
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
          const AppSectionTitle('Details'),
          const SizedBox(height: AppSpacing.md),
          OutlinedButton.icon(
            onPressed: () async {
              final picked = await showDatePicker(
                context: context,
                firstDate: DateTime(DateTime.now().year - 1),
                lastDate: DateTime(DateTime.now().year + 3),
                initialDate: _txnDate,
              );
              if (picked != null && mounted) {
                setState(() => _txnDate = picked);
              }
            },
            icon: const Icon(Icons.event),
            label: Text('Date: ${_formatDate(_txnDate)}'),
          ),
          const SizedBox(height: AppSpacing.md),
          if (widget.action == 'purchase') ...[
            DropdownButtonFormField<int>(
              initialValue: _vendorId,
              decoration: const InputDecoration(labelText: 'Vendor (optional)'),
              items: _vendors
                  .map(
                    (row) => DropdownMenuItem<int>(
                      value: _asInt(row['id']),
                      child: Text(row['name']?.toString() ?? '-'),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _vendorId = value),
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              initialValue: _supplierName,
              decoration: const InputDecoration(labelText: 'Supplier Name'),
              onChanged: (v) => _supplierName = v,
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              initialValue: _invoiceNo,
              decoration: const InputDecoration(labelText: 'Invoice No'),
              onChanged: (v) => _invoiceNo = v,
            ),
            if (widget.material == 'diamond') ...[
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                initialValue: _taxPercent,
                decoration: const InputDecoration(labelText: 'Tax %'),
                keyboardType: TextInputType.number,
                onChanged: (v) => _taxPercent = v,
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                initialValue: _dueDate,
                decoration: const InputDecoration(
                  labelText: 'Due Date (YYYY-MM-DD)',
                ),
                onChanged: (v) => _dueDate = v,
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              initialValue: _notes,
              decoration: const InputDecoration(labelText: 'Notes'),
              maxLines: 3,
              onChanged: (v) => _notes = v,
            ),
          ] else ...[
            if (widget.action == 'return') ...[
              DropdownButtonFormField<int>(
                initialValue: _issueId,
                decoration: const InputDecoration(labelText: 'Issue Reference'),
                items: _issueRefs
                    .map(
                      (row) => DropdownMenuItem<int>(
                        value: _asInt(row['id']),
                        child: Text(row['voucher_no']?.toString() ?? '-'),
                      ),
                    )
                    .toList(),
                onChanged: (value) {
                  final selected = _issueRefs.firstWhere(
                    (row) => _asInt(row['id']) == value,
                    orElse: () => null,
                  );
                  setState(() {
                    _issueId = value;
                    _karigarId = selected == null
                        ? null
                        : _asInt(selected['karigar_id']);
                  });
                },
                validator: (v) =>
                    v == null ? 'Issue reference is required' : null,
              ),
              const SizedBox(height: AppSpacing.md),
            ],
            if (widget.action == 'issue') ...[
              DropdownButtonFormField<int>(
                initialValue: _karigarId,
                decoration: const InputDecoration(labelText: 'Karigar'),
                items: _karigars
                    .map(
                      (row) => DropdownMenuItem<int>(
                        value: _asInt(row['id']),
                        child: Text(row['name']?.toString() ?? '-'),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _karigarId = value),
                validator: (v) => v == null ? 'Karigar is required' : null,
              ),
              const SizedBox(height: AppSpacing.md),
            ],
            DropdownButtonFormField<int>(
              initialValue: _locationId,
              decoration: const InputDecoration(labelText: 'Warehouse'),
              items: _locations
                  .map(
                    (row) => DropdownMenuItem<int>(
                      value: _asInt(row['id']),
                      child: Text(row['name']?.toString() ?? '-'),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _locationId = value),
              validator: (v) => v == null ? 'Warehouse is required' : null,
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              initialValue: _purpose,
              decoration: const InputDecoration(labelText: 'Purpose'),
              onChanged: (v) => _purpose = v,
              validator: (v) =>
                  v == null || v.trim().isEmpty ? 'Purpose is required' : null,
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              initialValue: _notes,
              decoration: const InputDecoration(labelText: 'Notes'),
              maxLines: 3,
              onChanged: (v) => _notes = v,
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildLinesSection() {
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
          const AppSectionTitle('Line Items'),
          const SizedBox(height: AppSpacing.md),
          ..._lines.asMap().entries.map((entry) {
            final index = entry.key;
            final line = entry.value;
            return _LineCard(
              index: index + 1,
              line: line,
              items: _items,
              material: widget.material,
              includePcs: widget.action == 'issue',
              onChanged: () => setState(() {}),
              onRemove: _lines.length == 1
                  ? null
                  : () {
                      setState(() {
                        line.dispose();
                        _lines.removeAt(index);
                      });
                    },
            );
          }).toList(),
          const SizedBox(height: AppSpacing.md),
          Align(
            alignment: Alignment.centerLeft,
            child: OutlinedButton.icon(
              onPressed: () {
                setState(() {
                  _lines.add(
                    _LineForm(
                      material: widget.material,
                      includePcs: widget.action == 'issue',
                    ),
                  );
                });
              },
              icon: const Icon(Icons.add),
              label: const Text('Add Line'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAttachmentSection() {
    if (widget.action == 'purchase') {
      return const SizedBox.shrink();
    }
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
          const AppSectionTitle('Attachment'),
          const SizedBox(height: AppSpacing.md),
          OutlinedButton.icon(
            onPressed: _pickAttachment,
            icon: const Icon(Icons.attach_file),
            label: Text(
              _attachment == null ? 'Select attachment' : 'Attachment selected',
            ),
          ),
          if (_attachment == null)
            const Padding(
              padding: EdgeInsets.only(top: AppSpacing.sm),
              child: Text(
                'Attachment is required for issue/return.',
                style: TextStyle(color: AppColors.textSecondary),
              ),
            ),
        ],
      ),
    );
  }
}

class _LineForm {
  _LineForm({required this.material, required this.includePcs});

  final String material;
  final bool includePcs;

  int? itemId;
  bool custom = false;

  final TextEditingController pcsCtrl = TextEditingController();
  final TextEditingController caratCtrl = TextEditingController();
  final TextEditingController rateCtrl = TextEditingController();
  final TextEditingController weightCtrl = TextEditingController();
  final TextEditingController qtyCtrl = TextEditingController();

  final TextEditingController diamondTypeCtrl = TextEditingController();
  final TextEditingController shapeCtrl = TextEditingController();
  final TextEditingController chalniFromCtrl = TextEditingController();
  final TextEditingController chalniToCtrl = TextEditingController();
  final TextEditingController colorCtrl = TextEditingController();
  final TextEditingController clarityCtrl = TextEditingController();
  final TextEditingController cutCtrl = TextEditingController();

  void dispose() {
    pcsCtrl.dispose();
    caratCtrl.dispose();
    rateCtrl.dispose();
    weightCtrl.dispose();
    qtyCtrl.dispose();
    diamondTypeCtrl.dispose();
    shapeCtrl.dispose();
    chalniFromCtrl.dispose();
    chalniToCtrl.dispose();
    colorCtrl.dispose();
    clarityCtrl.dispose();
    cutCtrl.dispose();
  }

  Map<String, dynamic> toPayload() {
    if (material == 'diamond') {
      final payload = <String, dynamic>{
        'item_id': itemId ?? 0,
        'pcs': double.tryParse(pcsCtrl.text) ?? 0,
        'carat': double.tryParse(caratCtrl.text) ?? 0,
        'rate_per_carat': double.tryParse(rateCtrl.text),
      };
      if (custom || (itemId ?? 0) == 0) {
        payload.addAll({
          'diamond_type': diamondTypeCtrl.text.trim(),
          'shape': shapeCtrl.text.trim(),
          'chalni_from': chalniFromCtrl.text.trim(),
          'chalni_to': chalniToCtrl.text.trim(),
          'color': colorCtrl.text.trim(),
          'clarity': clarityCtrl.text.trim(),
          'cut': cutCtrl.text.trim(),
        });
      }
      return payload;
    }

    if (material == 'gold') {
      return {
        'item_id': itemId ?? 0,
        'weight_gm': double.tryParse(weightCtrl.text) ?? 0,
        'rate_per_gm': double.tryParse(rateCtrl.text),
      };
    }

    return {
      'item_id': itemId ?? 0,
      'qty': double.tryParse(qtyCtrl.text) ?? 0,
      'pcs': includePcs ? (double.tryParse(pcsCtrl.text) ?? 0) : null,
      'rate': double.tryParse(rateCtrl.text),
    };
  }
}

class _LineCard extends StatelessWidget {
  const _LineCard({
    required this.index,
    required this.line,
    required this.items,
    required this.material,
    required this.includePcs,
    this.onChanged,
    this.onRemove,
  });

  final int index;
  final _LineForm line;
  final List<dynamic> items;
  final String material;
  final bool includePcs;
  final VoidCallback? onChanged;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
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
          Row(
            children: [
              Expanded(
                child: Text(
                  'Line $index',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
              if (onRemove != null)
                IconButton(
                  onPressed: onRemove,
                  icon: const Icon(Icons.delete_outline),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          DropdownButtonFormField<int>(
            initialValue: line.itemId,
            decoration: const InputDecoration(labelText: 'Item'),
            items: [
              if (material == 'diamond')
                const DropdownMenuItem<int>(
                  value: 0,
                  child: Text('Custom / New Item'),
                ),
              ...items.map(
                (row) => DropdownMenuItem<int>(
                  value: _safeInt(row['id']),
                  child: Text(_itemLabel(row)),
                ),
              ),
            ],
            onChanged: (value) {
              line.itemId = value;
              line.custom = material == 'diamond' && (value ?? 0) == 0;
              onChanged?.call();
            },
            validator: (v) => v == null ? 'Item is required' : null,
          ),
          if (material == 'diamond' && line.custom) ...[
            const SizedBox(height: AppSpacing.sm),
            TextFormField(
              controller: line.diamondTypeCtrl,
              decoration: const InputDecoration(labelText: 'Diamond Type'),
            ),
            const SizedBox(height: AppSpacing.sm),
            TextFormField(
              controller: line.shapeCtrl,
              decoration: const InputDecoration(labelText: 'Shape'),
            ),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: line.chalniFromCtrl,
                    decoration: const InputDecoration(labelText: 'Chalni From'),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: TextFormField(
                    controller: line.chalniToCtrl,
                    decoration: const InputDecoration(labelText: 'Chalni To'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: line.colorCtrl,
                    decoration: const InputDecoration(labelText: 'Color'),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: TextFormField(
                    controller: line.clarityCtrl,
                    decoration: const InputDecoration(labelText: 'Clarity'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            TextFormField(
              controller: line.cutCtrl,
              decoration: const InputDecoration(labelText: 'Cut'),
            ),
          ],
          const SizedBox(height: AppSpacing.sm),
          if (material == 'diamond') ...[
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: line.pcsCtrl,
                    decoration: const InputDecoration(labelText: 'PCS'),
                    keyboardType: TextInputType.number,
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: TextFormField(
                    controller: line.caratCtrl,
                    decoration: const InputDecoration(labelText: 'Carat'),
                    keyboardType: TextInputType.number,
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            TextFormField(
              controller: line.rateCtrl,
              decoration: const InputDecoration(labelText: 'Rate / Carat'),
              keyboardType: TextInputType.number,
            ),
          ] else if (material == 'gold') ...[
            TextFormField(
              controller: line.weightCtrl,
              decoration: const InputDecoration(labelText: 'Weight (gm)'),
              keyboardType: TextInputType.number,
            ),
            const SizedBox(height: AppSpacing.sm),
            TextFormField(
              controller: line.rateCtrl,
              decoration: const InputDecoration(labelText: 'Rate / gm'),
              keyboardType: TextInputType.number,
            ),
          ] else ...[
            TextFormField(
              controller: line.qtyCtrl,
              decoration: const InputDecoration(labelText: 'Weight / Qty'),
              keyboardType: TextInputType.number,
            ),
            if (includePcs) ...[
              const SizedBox(height: AppSpacing.sm),
              TextFormField(
                controller: line.pcsCtrl,
                decoration: const InputDecoration(labelText: 'PCS'),
                keyboardType: TextInputType.number,
              ),
            ],
            const SizedBox(height: AppSpacing.sm),
            TextFormField(
              controller: line.rateCtrl,
              decoration: const InputDecoration(labelText: 'Rate'),
              keyboardType: TextInputType.number,
            ),
          ],
        ],
      ),
    );
  }

  String _itemLabel(dynamic row) {
    if (material == 'diamond') {
      final type = row['diamond_type']?.toString() ?? '';
      final shape = row['shape']?.toString() ?? '';
      final chalniFrom = row['chalni_from']?.toString() ?? '';
      final chalniTo = row['chalni_to']?.toString() ?? '';
      final chalni = chalniFrom.isNotEmpty ? '$chalniFrom-$chalniTo' : '';
      return [
        type,
        shape,
        chalni,
      ].where((e) => e.toString().isNotEmpty).join(' ');
    }
    if (material == 'gold') {
      final purity = row['purity_code']?.toString() ?? '';
      final color = row['color_name']?.toString() ?? '';
      final form = row['form_type']?.toString() ?? '';
      return [
        purity,
        color,
        form,
      ].where((e) => e.toString().isNotEmpty).join(' ');
    }
    return row['product_name']?.toString() ?? '-';
  }

  int? _safeInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }
}
