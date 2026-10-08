import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class MobileApprovalsScreen extends StatefulWidget {
  const MobileApprovalsScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<MobileApprovalsScreen> createState() => _MobileApprovalsScreenState();
}

class _MobileApprovalsScreenState extends State<MobileApprovalsScreen> {
  bool _loading = true;
  bool _canReview = false;
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
      final data = await widget.api.fetchApprovalRequests();
      if (!mounted) return;
      setState(() {
        _items = data['items'] is List ? data['items'] as List : [];
        _canReview = data['can_review'] == true;
      });
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _newRequest(String type) async {
    final created = await showDialog<bool>(
      context: context,
      builder: (_) => _MasterRequestDialog(api: widget.api, type: type),
    );
    if (created == true) await _load();
  }

  Future<void> _openStaffControls() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => _ApprovalStaffSettingsScreen(api: widget.api),
      ),
    );
  }

  Future<void> _review(Map<String, dynamic> item, String decision) async {
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: Icon(
          decision == 'approve'
              ? LucideIcons.badge_check
              : LucideIcons.circle_x,
          color: decision == 'approve' ? Colors.green : Colors.red,
          size: 36,
        ),
        title: Text(
          decision == 'approve' ? 'Approve request?' : 'Reject request?',
        ),
        content: TextField(
          controller: note,
          maxLength: 500,
          maxLines: 3,
          decoration: const InputDecoration(
            labelText: 'Admin note (optional)',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: decision == 'approve'
                  ? Colors.green.shade700
                  : Colors.red.shade700,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: Text(decision == 'approve' ? 'Approve & Process' : 'Reject'),
          ),
        ],
      ),
    );
    final noteValue = note.text;
    note.dispose();
    if (confirmed != true || !mounted) return;
    try {
      await widget.api.reviewApprovalRequest(
        _int(item['id']),
        decision: decision,
        note: noteValue,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            decision == 'approve'
                ? 'Request approved.'
                : item['request_type'] == 'followup'
                ? 'Followup disapproved.'
                : 'Request disapproved.',
          ),
        ),
      );
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _items.isEmpty) {
      return const FullScreenLoader(message: 'Loading approval requests...');
    }
    if (_error.isNotEmpty && _items.isEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    final pending = _items
        .where((raw) => _map(raw)['status'] == 'pending')
        .length;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
        children: [
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [
                  Color(0xFF32142D),
                  Color(0xFF76224F),
                  Color(0xFFC19636),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(22),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x332E1028),
                  blurRadius: 26,
                  offset: Offset(0, 12),
                ),
              ],
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _canReview ? 'ADMIN APPROVAL DESK' : 'MASTER REQUESTS',
                        style: const TextStyle(
                          color: Color(0xFFF5D98A),
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.3,
                        ),
                      ),
                      const SizedBox(height: 7),
                      Text(
                        _canReview
                            ? '$pending pending approval${pending == 1 ? '' : 's'}'
                            : 'Create masters with admin approval',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(
                  LucideIcons.shield_check,
                  color: Color(0xFFF5D98A),
                  size: 42,
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (_canReview) ...[
            OutlinedButton.icon(
              onPressed: _openStaffControls,
              icon: const Icon(LucideIcons.user_round_cog),
              label: const Text('Employee Approval & Camera Controls'),
            ),
            const SizedBox(height: 10),
          ],
          LayoutBuilder(
            builder: (context, constraints) {
              final buttonWidth = constraints.maxWidth < 430
                  ? constraints.maxWidth
                  : (constraints.maxWidth - 10) / 2;
              return Wrap(
                spacing: 10,
                runSpacing: 8,
                children: [
                  SizedBox(
                    width: buttonWidth,
                    child: FilledButton.icon(
                      onPressed: () => _newRequest('customer'),
                      icon: const Icon(LucideIcons.user_round_plus),
                      label: const Text('New Customer'),
                    ),
                  ),
                  SizedBox(
                    width: buttonWidth,
                    child: OutlinedButton.icon(
                      onPressed: () => _newRequest('karigar'),
                      icon: const Icon(LucideIcons.hammer),
                      label: const Text('New Karigar'),
                    ),
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 18),
          if (_items.isEmpty)
            const AppEmptyState(
              title: 'No requests yet',
              message: 'Customer and karigar requests will appear here.',
            )
          else
            ..._items.map((raw) => _requestCard(_map(raw))),
        ],
      ),
    );
  }

  Widget _requestCard(Map<String, dynamic> item) {
    final type = (item['request_type'] ?? '').toString();
    final status = (item['status'] ?? 'pending').toString();
    final payload = _map(item['payload']);
    final isIssue = type == 'issuement';
    final isFollowup = type == 'followup';
    final isChallan = type == 'delivery_challan';
    final statusLabel = status == 'rejected'
        ? 'DISAPPROVED'
        : status.toUpperCase();
    final statusColor = switch (status) {
      'approved' => Colors.green,
      'rejected' => Colors.red,
      _ => Colors.orange,
    };
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: const Color(0xFFF6EBCB),
                  foregroundColor: AppColors.plum,
                  child: Icon(
                    isIssue
                        ? LucideIcons.send
                        : isFollowup
                        ? LucideIcons.clipboard_check
                        : isChallan
                        ? LucideIcons.scroll_text
                        : type == 'customer_create'
                        ? LucideIcons.users
                        : LucideIcons.hammer,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _typeLabel(type),
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                        ),
                      ),
                      Text(
                        '#${item['id']} · ${item['requested_by_name'] ?? '-'}',
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(30),
                  ),
                  child: Text(
                    statusLabel,
                    style: TextStyle(
                      color: statusColor.shade700,
                      fontSize: 10,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
            const Divider(height: 26),
            Text(
              (item['summary'] ?? '').toString(),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            if (isIssue) ...[
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _chip(LucideIcons.hammer, payload['karigar_name'] ?? '-'),
                  _chip(LucideIcons.warehouse, payload['location_name'] ?? '-'),
                  _chip(LucideIcons.calendar, payload['issue_date'] ?? '-'),
                ],
              ),
              const SizedBox(height: 12),
              for (final material in ['gold', 'diamond', 'stone'])
                if (payload['${material}_lines'] is List &&
                    (payload['${material}_lines'] as List).isNotEmpty)
                  _materialLines(
                    material,
                    payload['${material}_lines'] as List,
                  ),
              if ((item['attachment_url'] ?? '').toString().isNotEmpty) ...[
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    item['attachment_url'].toString(),
                    width: double.infinity,
                    height: 230,
                    fit: BoxFit.contain,
                    errorBuilder: (_, _, _) => const SizedBox(
                      height: 70,
                      child: Center(
                        child: Text('Attachment preview unavailable'),
                      ),
                    ),
                  ),
                ),
              ],
            ] else if (isFollowup) ...[
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _chip(LucideIcons.shopping_bag, payload['order_no'] ?? '-'),
                  _chip(LucideIcons.activity, payload['stage'] ?? '-'),
                  if ((payload['next_followup_date'] ?? '')
                      .toString()
                      .isNotEmpty)
                    _chip(
                      LucideIcons.calendar_clock,
                      payload['next_followup_date'],
                    ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                (payload['description'] ?? '').toString(),
                style: const TextStyle(height: 1.5),
              ),
              if ((item['attachment_url'] ?? '').toString().isNotEmpty) ...[
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    item['attachment_url'].toString(),
                    width: double.infinity,
                    height: 230,
                    fit: BoxFit.contain,
                  ),
                ),
              ],
            ] else if (isChallan) ...[
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _chip(LucideIcons.users, payload['customer_name'] ?? '-'),
                  _chip(LucideIcons.map_pin, payload['dispatch_from'] ?? '-'),
                  _chip(LucideIcons.calendar, payload['challan_date'] ?? '-'),
                  _chip(
                    LucideIcons.package,
                    '${(payload['items'] as List?)?.length ?? 0} item(s)',
                  ),
                ],
              ),
              const SizedBox(height: 10),
              for (
                var i = 0;
                i < ((payload['items'] as List?)?.length ?? 0);
                i++
              )
                Builder(
                  builder: (context) {
                    final line = _map((payload['items'] as List)[i]);
                    return Container(
                      width: double.infinity,
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(11),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFAF7F9),
                        border: Border.all(color: AppColors.border),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        '${i + 1}. ${line['description'] ?? '-'} · ${line['item_type'] ?? '-'} · ${line['purity'] ?? '-'} · ${line['pcs'] ?? 0} PCS · Rs. ${line['value'] ?? 0}',
                        style: const TextStyle(
                          fontSize: 12,
                          height: 1.45,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    );
                  },
                ),
            ] else ...[
              const SizedBox(height: 8),
              Text(
                _masterDetails(payload),
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  height: 1.5,
                ),
              ),
            ],
            if ((item['review_note'] ?? '').toString().isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                'Admin note: ${item['review_note']}',
                style: const TextStyle(fontStyle: FontStyle.italic),
              ),
            ],
            if (_canReview && status == 'pending') ...[
              const SizedBox(height: 15),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _review(item, 'reject'),
                      icon: const Icon(LucideIcons.x),
                      label: const Text('Reject'),
                    ),
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () => _review(item, 'approve'),
                      icon: const Icon(LucideIcons.check),
                      label: const Text('Approve'),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _materialLines(String material, List lines) {
    final unitKey = material == 'gold'
        ? 'weight_gm'
        : material == 'diamond'
        ? 'carat'
        : 'qty';
    final unit = material == 'gold'
        ? 'gm'
        : material == 'diamond'
        ? 'ct'
        : 'qty';
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFAF7F9),
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${material[0].toUpperCase()}${material.substring(1)} · ${lines.length} line(s)',
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              color: AppColors.plum,
            ),
          ),
          for (var i = 0; i < lines.length; i++)
            Text(
              'Line ${i + 1} · ${_map(lines[i])['display_name'] ?? 'Item ${_map(lines[i])['item_id'] ?? '-'}'} · ${_map(lines[i])[unitKey] ?? 0} $unit${_map(lines[i])['pcs'] == null ? '' : ' · ${_map(lines[i])['pcs']} pcs'}',
              style: const TextStyle(fontSize: 12, height: 1.55),
            ),
        ],
      ),
    );
  }

  Widget _chip(IconData icon, dynamic value) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
    decoration: BoxDecoration(
      color: const Color(0xFFF8F3E7),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14),
        const SizedBox(width: 6),
        Text(
          '$value',
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ],
    ),
  );

  String _masterDetails(Map<String, dynamic> payload) =>
      [
            payload['phone'],
            payload['email'],
            payload['department'],
            payload['city'],
            payload['state'],
            payload['gstin'],
          ]
          .map((e) => (e ?? '').toString().trim())
          .where((e) => e.isNotEmpty)
          .join(' · ');

  String _typeLabel(String type) => switch (type) {
    'customer_create' => 'Customer Creation',
    'karigar_create' => 'Karigar Creation',
    'followup' => 'Order Follow-up',
    'delivery_challan' => 'Delivery Challan',
    _ => 'Material Issuement',
  };
}

class _ApprovalStaffSettingsScreen extends StatefulWidget {
  const _ApprovalStaffSettingsScreen({required this.api});

  final MobileApiService api;

  @override
  State<_ApprovalStaffSettingsScreen> createState() =>
      _ApprovalStaffSettingsScreenState();
}

class _ApprovalStaffSettingsScreenState
    extends State<_ApprovalStaffSettingsScreen> {
  bool _loading = true;
  String _error = '';
  List<Map<String, dynamic>> _rows = [];
  final Set<int> _saving = {};

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
      final rows = await widget.api.fetchApprovalStaffSettings();
      if (!mounted) return;
      setState(() {
        _rows = rows
            .whereType<Map>()
            .map((row) => row.cast<String, dynamic>())
            .toList();
      });
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save(Map<String, dynamic> row) async {
    final id = _int(row['id']);
    if (id <= 0 || _saving.contains(id)) return;
    setState(() => _saving.add(id));
    try {
      await widget.api.updateApprovalStaffSettings(id, {
        'followup_requires_approval': _enabled(
          row['followup_requires_approval'],
        ),
        'issuement_requires_approval': _enabled(
          row['issuement_requires_approval'],
        ),
        'delivery_challan_requires_approval': _enabled(
          row['delivery_challan_requires_approval'],
        ),
        'followup_gallery_enabled': _enabled(row['followup_gallery_enabled']),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${row['name']} settings saved.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) setState(() => _saving.remove(id));
    }
  }

  void _toggle(Map<String, dynamic> row, String field, bool value) {
    setState(() => row[field] = value ? 1 : 0);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Employee PWA Controls')),
    body: _loading
        ? const FullScreenLoader(message: 'Loading employee controls...')
        : _error.isNotEmpty
        ? AppErrorState(message: _error, onRetry: _load)
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.paleGold.withValues(alpha: .45),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Text(
                    'Approval is ON by default for follow-ups, issuements and delivery challans. Gallery is OFF by default, so follow-up photos open the camera only.',
                    style: TextStyle(height: 1.5, fontWeight: FontWeight.w600),
                  ),
                ),
                const SizedBox(height: 16),
                if (_rows.isEmpty)
                  const AppEmptyState(
                    title: 'No staff accounts',
                    message:
                        'Active non-admin employee logins will appear here.',
                  ),
                ..._rows.map(_card),
              ],
            ),
          ),
  );

  Widget _card(Map<String, dynamic> row) {
    final id = _int(row['id']);
    final busy = _saving.contains(id);
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              (row['name'] ?? 'Employee').toString(),
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
            ),
            Text(
              '${row['email'] ?? ''} · ${row['role_label'] ?? 'Staff'}',
              style: const TextStyle(color: AppColors.textSecondary),
            ),
            const Divider(height: 24),
            _switch(
              row,
              'followup_requires_approval',
              'Follow-up approval required',
            ),
            _switch(
              row,
              'issuement_requires_approval',
              'Issuement approval required',
            ),
            _switch(
              row,
              'delivery_challan_requires_approval',
              'Delivery challan approval required',
            ),
            _switch(
              row,
              'followup_gallery_enabled',
              'Allow gallery for follow-up image',
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: busy ? null : () => _save(row),
                icon: busy
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(LucideIcons.save),
                label: Text(busy ? 'Saving...' : 'Save Controls'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _switch(Map<String, dynamic> row, String field, String label) =>
      SwitchListTile.adaptive(
        contentPadding: EdgeInsets.zero,
        title: Text(label),
        value: _enabled(row[field]),
        onChanged: (value) => _toggle(row, field, value),
      );

  bool _enabled(dynamic value) =>
      value == true || value == 1 || value.toString() == '1';
}

class _MasterRequestDialog extends StatefulWidget {
  const _MasterRequestDialog({required this.api, required this.type});
  final MobileApiService api;
  final String type;

  @override
  State<_MasterRequestDialog> createState() => _MasterRequestDialogState();
}

class _MasterRequestDialogState extends State<_MasterRequestDialog> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, TextEditingController> _fields = {};
  bool _saving = false;

  TextEditingController _c(String key) =>
      _fields.putIfAbsent(key, TextEditingController.new);

  @override
  void dispose() {
    for (final controller in _fields.values) controller.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    final payload = {
      for (final entry in _fields.entries) entry.key: entry.value.text.trim(),
    };
    try {
      if (widget.type == 'customer') {
        await widget.api.createCustomerApprovalRequest(payload);
      } else {
        await widget.api.createKarigarApprovalRequest(payload);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final customer = widget.type == 'customer';
    return AlertDialog(
      title: Text(customer ? 'Request New Customer' : 'Request New Karigar'),
      content: SizedBox(
        width: 580,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              children: [
                _field(
                  'name',
                  customer ? 'Customer name *' : 'Karigar name *',
                  required: true,
                ),
                _field('phone', 'Phone'),
                _field('email', 'Email', type: TextInputType.emailAddress),
                if (customer) ...[
                  _field('gstin', 'GSTIN'),
                  _field('line1', 'Address line 1'),
                  _field('line2', 'Address line 2'),
                ] else ...[
                  _field('department', 'Department'),
                  _field('skills_text', 'Skills'),
                  _field('address', 'Address'),
                ],
                _field('city', 'City'),
                _field('state', 'State'),
                _field('pincode', 'Pincode'),
                if (!customer) ...[
                  _field(
                    'rate_per_gm',
                    'Rate per gram',
                    type: const TextInputType.numberWithOptions(decimal: true),
                  ),
                  _field(
                    'wastage_percentage',
                    'Wastage %',
                    type: const TextInputType.numberWithOptions(decimal: true),
                  ),
                ],
                _field('notes', 'Notes', lines: 2),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton.icon(
          onPressed: _saving ? null : _submit,
          icon: _saving
              ? const SizedBox.square(
                  dimension: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(LucideIcons.send, size: 18),
          label: const Text('Send for Approval'),
        ),
      ],
    );
  }

  Widget _field(
    String key,
    String label, {
    bool required = false,
    TextInputType? type,
    int lines = 1,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 11),
    child: TextFormField(
      controller: _c(key),
      keyboardType: type,
      maxLines: lines,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
      ),
      validator: required
          ? (value) => (value ?? '').trim().length < 2 ? 'Required' : null
          : null,
    ),
  );
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? value.cast<String, dynamic>() : <String, dynamic>{};
int _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
