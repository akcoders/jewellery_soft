import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';

class DiamondRequirementsScreen extends StatefulWidget {
  const DiamondRequirementsScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<DiamondRequirementsScreen> createState() =>
      _DiamondRequirementsScreenState();
}

class _DiamondRequirementsScreenState extends State<DiamondRequirementsScreen> {
  bool _loading = true;
  String _error = '';
  List<dynamic> _items = [];

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
      final rows = await widget.api.fetchDiamondRequirements();
      if (mounted) setState(() => _items = rows);
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _items.isEmpty) return const FullScreenLoader();
    if (_error.isNotEmpty && _items.isEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: _items.isEmpty
          ? ListView(
              children: const [
                SizedBox(height: 120),
                AppEmptyState(
                  title: 'No diamond requirements',
                  message:
                      'Raised or assigned diamond bag requirements will appear here.',
                ),
              ],
            )
          : ListView.builder(
              padding: const EdgeInsets.all(AppSpacing.lg),
              itemCount: _items.length,
              itemBuilder: (context, index) {
                final row = (_items[index] as Map).cast<String, dynamic>();
                final status = (row['status'] ?? '').toString();
                final canPrepare =
                    row['can_prepare'] == true || row['can_prepare'] == 1;
                return Card(
                  margin: const EdgeInsets.only(bottom: AppSpacing.md),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(AppRadius.lg),
                    onTap: () async {
                      final changed = await Navigator.of(context).push<bool>(
                        MaterialPageRoute(
                          builder: (_) => DiamondRequirementDetailScreen(
                            api: widget.api,
                            requirementId: _int(row['id']),
                          ),
                        ),
                      );
                      if (changed == true) _load();
                    },
                    child: Padding(
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 42,
                                height: 42,
                                decoration: BoxDecoration(
                                  color: AppColors.diamond.withValues(
                                    alpha: .16,
                                  ),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(
                                  Icons.diamond_outlined,
                                  color: AppColors.brandRed,
                                ),
                              ),
                              const SizedBox(width: AppSpacing.md),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      (row['requirement_no'] ?? '-').toString(),
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    Text(
                                      '${row['order_no'] ?? '-'} · ${row['order_name'] ?? row['order_category_name'] ?? ''}',
                                      style: const TextStyle(
                                        color: AppColors.textSecondary,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              _statusBadge(status),
                            ],
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Text(
                            (row['requirement_note'] ?? 'No instruction')
                                .toString(),
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              _smallChip(
                                Icons.person_outline,
                                row['assignee_name'] ?? 'Awaiting assignment',
                              ),
                              if ((row['preparation_due_at'] ?? '')
                                  .toString()
                                  .isNotEmpty)
                                _smallChip(
                                  Icons.schedule_outlined,
                                  row['preparation_due_at'],
                                ),
                              if ((row['bag_no'] ?? '').toString().isNotEmpty)
                                _smallChip(
                                  Icons.inventory_2_outlined,
                                  '${row['bag_no']} · ${row['pcs_balance'] ?? 0} pcs / ${row['cts_balance'] ?? 0} cts',
                                ),
                            ],
                          ),
                          if (canPrepare) ...[
                            const SizedBox(height: AppSpacing.md),
                            const Row(
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                Text(
                                  'Open & prepare bag',
                                  style: TextStyle(
                                    color: AppColors.brandRed,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                SizedBox(width: 4),
                                Icon(
                                  Icons.arrow_forward,
                                  color: AppColors.brandRed,
                                  size: 18,
                                ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
    );
  }
}

class DiamondRequirementDetailScreen extends StatefulWidget {
  const DiamondRequirementDetailScreen({
    super.key,
    required this.api,
    required this.requirementId,
  });

  final MobileApiService api;
  final int requirementId;

  @override
  State<DiamondRequirementDetailScreen> createState() =>
      _DiamondRequirementDetailScreenState();
}

class _DiamondRequirementDetailScreenState
    extends State<DiamondRequirementDetailScreen> {
  final _formKey = GlobalKey<FormState>();
  final _notes = TextEditingController();
  bool _loading = true;
  bool _saving = false;
  String _error = '';
  Map<String, dynamic> _requirement = {};
  List<dynamic> _bagItems = [];
  List<dynamic> _inventoryItems = [];
  List<dynamic> _shapes = [];
  List<dynamic> _sizes = [];
  List<dynamic> _locations = [];
  final List<_BagLine> _lines = [_BagLine()];
  int? _locationId;
  DateTime _preparedDate = DateTime.now();

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
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      final data = await widget.api.fetchDiamondRequirement(
        widget.requirementId,
      );
      final requirement =
          (data['requirement'] as Map?)?.cast<String, dynamic>() ?? {};
      final lookups = (data['lookups'] as Map?)?.cast<String, dynamic>() ?? {};
      if (!mounted) return;
      setState(() {
        _requirement = requirement;
        _bagItems = (data['bag_items'] as List?) ?? [];
        _inventoryItems = (lookups['inventory_items'] as List?) ?? [];
        _shapes = (lookups['shapes'] as List?) ?? [];
        _sizes = (lookups['sizes'] as List?) ?? [];
        _locations = (lookups['locations'] as List?) ?? [];
        if (_locationId == null && _locations.isNotEmpty) {
          _locationId = _int((_locations.first as Map)['id']);
        }
      });
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_locationId == null || _locationId! <= 0) {
      _message('Select an inventory location.');
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.api.prepareDiamondBag(
        requirementId: widget.requirementId,
        locationId: _locationId!,
        preparedDate: _date(_preparedDate),
        notes: _notes.text,
        items: _lines
            .map(
              (line) => {
                'inventory_item_id': line.itemId,
                'shape_master_id': line.shapeId,
                'size_master_id': line.sizeId,
                'pcs': int.tryParse(line.pcs.text.trim()) ?? 0,
                'weight_cts': double.tryParse(line.cts.text.trim()) ?? 0,
              },
            )
            .toList(),
      );
      if (!mounted) return;
      _message('Diamond bag is ready and admin has been notified.');
      Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) _message(e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _message(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final status = (_requirement['status'] ?? '').toString();
    final canPrepare = status == 'assigned' && _inventoryItems.isNotEmpty;
    return Scaffold(
      appBar: AppBar(title: const Text('Diamond Bag Requirement')),
      body: _loading && _requirement.isEmpty
          ? const FullScreenLoader()
          : _error.isNotEmpty && _requirement.isEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                (_requirement['requirement_no'] ?? '-')
                                    .toString(),
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            _statusBadge(status),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Order ${_requirement['order_no'] ?? '-'} · ${_requirement['order_name'] ?? ''}',
                        ),
                        const Divider(height: 24),
                        Text(
                          (_requirement['requirement_note'] ?? 'No instruction')
                              .toString(),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Assigned to: ${_requirement['assignee_name'] ?? 'Not assigned'}',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                if (_bagItems.isNotEmpty) ...[
                  const Text(
                    'READY BAG DETAILS',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppColors.textSecondary,
                      letterSpacing: .5,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  ..._bagItems.map((raw) {
                    final row = (raw as Map).cast<String, dynamic>();
                    return ListTile(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 4),
                      leading: const Icon(Icons.diamond_outlined),
                      title: Text(
                        '${row['diamond_type'] ?? '-'} · ${row['shape_name'] ?? '-'}',
                      ),
                      subtitle: Text(
                        '${row['size_label'] ?? row['size_code'] ?? '-'} · ${row['pcs_total'] ?? 0} pcs · ${row['weight_cts_total'] ?? 0} cts',
                      ),
                    );
                  }),
                ],
                if (status == 'pending_approval')
                  const AppEmptyState(
                    title: 'Waiting for admin approval',
                    message:
                        'Admin will approve this requirement and assign bag preparation.',
                  ),
                if (status == 'assigned' && !canPrepare && !_loading)
                  const AppEmptyState(
                    title: 'Bag preparation unavailable',
                    message:
                        'This requirement is assigned to another staff member.',
                  ),
                if (canPrepare) _preparationForm(),
              ],
            ),
    );
  }

  Widget _preparationForm() {
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'PREPARE SIZE-WISE BAG',
            style: TextStyle(
              fontWeight: FontWeight.w800,
              color: AppColors.textSecondary,
              letterSpacing: .5,
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          DropdownButtonFormField<int>(
            key: ValueKey('location_${_locationId ?? 0}'),
            initialValue: _locationId,
            decoration: const InputDecoration(
              labelText: 'Inventory Location *',
            ),
            items: _locations
                .map(
                  (raw) => DropdownMenuItem<int>(
                    value: _int((raw as Map)['id']),
                    child: Text((raw['name'] ?? '-').toString()),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => _locationId = value),
          ),
          const SizedBox(height: AppSpacing.md),
          OutlinedButton.icon(
            onPressed: () async {
              final date = await showDatePicker(
                context: context,
                initialDate: _preparedDate,
                firstDate: DateTime.now().subtract(const Duration(days: 30)),
                lastDate: DateTime.now().add(const Duration(days: 30)),
              );
              if (date != null) setState(() => _preparedDate = date);
            },
            icon: const Icon(Icons.calendar_today_outlined),
            label: Text('Prepared on ${_date(_preparedDate)}'),
          ),
          const SizedBox(height: AppSpacing.md),
          ...List.generate(_lines.length, (index) => _lineCard(index)),
          OutlinedButton.icon(
            onPressed: () => setState(() => _lines.add(_BagLine())),
            icon: const Icon(Icons.add),
            label: const Text('Add Diamond Size'),
          ),
          const SizedBox(height: AppSpacing.md),
          TextFormField(
            controller: _notes,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Preparation Notes'),
          ),
          const SizedBox(height: AppSpacing.lg),
          FilledButton.icon(
            onPressed: _saving ? null : _submit,
            icon: _saving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.inventory_2_outlined),
            label: const Text('Complete & Mark Bag Ready'),
          ),
        ],
      ),
    );
  }

  Widget _lineCard(int index) {
    final line = _lines[index];
    final sizes = _sizes.where((raw) {
      final row = raw as Map;
      return _int(row['shape_id']) == line.shapeId;
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
                    'Diamond size ${index + 1}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                if (_lines.length > 1)
                  IconButton(
                    onPressed: () => setState(() {
                      _lines.removeAt(index).dispose();
                    }),
                    icon: const Icon(
                      Icons.delete_outline,
                      color: AppColors.danger,
                    ),
                  ),
              ],
            ),
            DropdownButtonFormField<int>(
              key: ValueKey('item_${index}_${line.itemId ?? 0}'),
              initialValue: line.itemId,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Diamond Product *'),
              items: _inventoryItems.map((raw) {
                final row = raw as Map;
                final available =
                    _double(row['carat_balance']) - _double(row['bagged_cts']);
                return DropdownMenuItem<int>(
                  value: _int(row['id']),
                  child: Text(
                    '${row['diamond_type'] ?? '-'} · ${row['color'] ?? ''} ${row['clarity'] ?? ''} · ${available.toStringAsFixed(3)} cts free',
                    overflow: TextOverflow.ellipsis,
                  ),
                );
              }).toList(),
              validator: (value) => value == null ? 'Select product' : null,
              onChanged: (value) => setState(() => line.itemId = value),
            ),
            const SizedBox(height: AppSpacing.sm),
            DropdownButtonFormField<int>(
              key: ValueKey('shape_${index}_${line.shapeId ?? 0}'),
              initialValue: line.shapeId,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Shape *'),
              items: _shapes
                  .map(
                    (raw) => DropdownMenuItem<int>(
                      value: _int((raw as Map)['id']),
                      child: Text((raw['name'] ?? '-').toString()),
                    ),
                  )
                  .toList(),
              validator: (value) => value == null ? 'Select shape' : null,
              onChanged: (value) => setState(() {
                line.shapeId = value;
                line.sizeId = null;
              }),
            ),
            const SizedBox(height: AppSpacing.sm),
            DropdownButtonFormField<int>(
              key: ValueKey(
                'size_${index}_${line.shapeId ?? 0}_${line.sizeId ?? 0}',
              ),
              initialValue:
                  sizes.any((raw) => _int((raw as Map)['id']) == line.sizeId)
                  ? line.sizeId
                  : null,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Size *'),
              items: sizes
                  .map(
                    (raw) => DropdownMenuItem<int>(
                      value: _int((raw as Map)['id']),
                      child: Text(
                        (raw['size_label'] ?? raw['size_code'] ?? '-')
                            .toString(),
                      ),
                    ),
                  )
                  .toList(),
              validator: (value) => value == null ? 'Select size' : null,
              onChanged: (value) => setState(() => line.sizeId = value),
            ),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: line.pcs,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'PCS *'),
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
                    decoration: const InputDecoration(labelText: 'Carats *'),
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
}

class _BagLine {
  int? itemId;
  int? shapeId;
  int? sizeId;
  final TextEditingController pcs = TextEditingController();
  final TextEditingController cts = TextEditingController();

  void dispose() {
    pcs.dispose();
    cts.dispose();
  }
}

Widget _statusBadge(String status) {
  final color = switch (status) {
    'pending_approval' => AppColors.warning,
    'assigned' => AppColors.diamond,
    'bag_ready' => AppColors.success,
    'issued' => AppColors.brandRed,
    'rejected' => AppColors.danger,
    _ => AppColors.textSecondary,
  };
  return Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .13),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      status.replaceAll('_', ' ').toUpperCase(),
      style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w800),
    ),
  );
}

Widget _smallChip(IconData icon, dynamic label) {
  return Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
    decoration: BoxDecoration(
      color: AppColors.background,
      borderRadius: BorderRadius.circular(99),
      border: Border.all(color: AppColors.border),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: AppColors.textSecondary),
        const SizedBox(width: 5),
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 240),
          child: Text(
            label?.toString() ?? '-',
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

int _int(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

double _double(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}

String _date(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
