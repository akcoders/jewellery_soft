import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_status_badge.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_mobile/screens/order_followup_form_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/diamond_requirements_screen.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({
    super.key,
    required this.api,
    required this.orderId,
    this.initialOrder,
  });

  final MobileApiService api;
  final int orderId;
  final Map<String, dynamic>? initialOrder;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  bool _loading = true;
  String _error = '';
  Map<String, dynamic> _order = {};
  List<dynamic> _items = [];
  List<dynamic> _followups = [];
  List<dynamic> _diamondRequirements = [];
  List<dynamic> _workRequests = [];
  List<dynamic> _karigars = [];
  List<dynamic> _assignmentCustomers = [];
  bool _canAssignKarigar = false;
  bool _canRaiseDiamondRequirement = false;
  bool _canTakeOrderFollowup = false;
  bool _canChangeFollower = false;
  List<dynamic> _staffFollowers = [];
  List<String> _allowedStages = const [];

  @override
  void initState() {
    super.initState();
    if (widget.initialOrder != null && widget.initialOrder!.isNotEmpty) {
      _order = Map<String, dynamic>.from(widget.initialOrder!);
    }
    _load();
  }

  @override
  void didUpdateWidget(covariant OrderDetailScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.orderId != widget.orderId) {
      _load();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    if (widget.orderId <= 0) {
      setState(() {
        _loading = false;
        _error = 'Invalid order id.';
      });
      return;
    }
    try {
      final data = await widget.api.fetchOrderDetail(widget.orderId);
      final requests = await widget.api.fetchOrderWorkRequests(
        orderId: widget.orderId,
      );
      if (!mounted) return;
      final orderMap = (data['order'] as Map?)?.cast<String, dynamic>();
      setState(() {
        _order = orderMap ?? data.cast<String, dynamic>();
        _items = (data['items'] as List?) ?? <dynamic>[];
        _followups = (data['followups'] as List?) ?? <dynamic>[];
        _diamondRequirements =
            (data['diamond_requirements'] as List?) ?? <dynamic>[];
        _workRequests = requests;
        _karigars = (data['karigars'] as List?) ?? <dynamic>[];
        _assignmentCustomers =
            (data['assignment_customers'] as List?) ?? <dynamic>[];
        _canAssignKarigar =
            data['can_assign_karigar'] == true ||
            data['can_assign_karigar'] == 1;
        _canRaiseDiamondRequirement =
            data['can_raise_diamond_requirement'] == true ||
            data['can_raise_diamond_requirement'] == 1;
        _canTakeOrderFollowup =
            data['can_add_followup'] == true || data['can_add_followup'] == 1;
        _canChangeFollower =
            data['can_change_follower'] == true ||
            data['can_change_follower'] == 1;
        _staffFollowers = (data['staff_followers'] as List?) ?? <dynamic>[];
        _allowedStages = ((data['allowed_stages'] as List?) ?? <dynamic>[])
            .map((e) => e.toString())
            .toList(growable: false);
      });
      if (_order.isEmpty) {
        setState(() => _error = 'Order data not found.');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _takeFollowup() async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => OrderFollowupFormScreen(
          api: widget.api,
          orderId: widget.orderId,
          stages: _allowedStages,
        ),
      ),
    );

    if (result == true) {
      _load();
    }
  }

  Future<void> _changeFollower() async {
    final followerRows = _staffFollowers
        .whereType<Map>()
        .map((row) => row.cast<String, dynamic>())
        .toList(growable: false);
    if (followerRows.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No active staff follower is available.')),
      );
      return;
    }

    final currentFollowerId = int.tryParse(
      (_order['followup_assigned_to'] ?? '').toString(),
    );
    final availableIds = followerRows
        .map((row) => int.tryParse((row['id'] ?? '').toString()))
        .whereType<int>()
        .toSet();
    int? selectedFollowerId = availableIds.contains(currentFollowerId)
        ? currentFollowerId
        : null;
    final status = (_order['status'] ?? '').toString();
    final closed = {
      'Ready',
      'Packed',
      'Dispatched',
      'Delivered',
      'Completed',
      'Complete',
      'Cancelled',
    }.contains(status);
    DateTime? dueAt = DateTime.tryParse(
      (_order['followup_due_at'] ?? '').toString().replaceFirst(' ', 'T'),
    );
    if (!closed && (dueAt == null || !dueAt.isAfter(DateTime.now()))) {
      final tomorrow = DateTime.now().add(const Duration(days: 1));
      dueAt = DateTime(tomorrow.year, tomorrow.month, tomorrow.day, 11);
    }
    var saving = false;
    var dialogError = '';

    final changed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Change Order Follower'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                DropdownButtonFormField<int>(
                  initialValue: selectedFollowerId,
                  decoration: const InputDecoration(
                    labelText: 'Order follower *',
                  ),
                  items: followerRows
                      .map((row) {
                        final id =
                            int.tryParse((row['id'] ?? '').toString()) ?? 0;
                        final name = (row['name'] ?? 'Staff').toString();
                        final role = (row['role_label'] ?? 'Staff').toString();
                        return DropdownMenuItem<int>(
                          value: id,
                          child: Text('$name · $role'),
                        );
                      })
                      .where((item) => (item.value ?? 0) > 0)
                      .toList(),
                  onChanged: saving
                      ? null
                      : (value) =>
                            setDialogState(() => selectedFollowerId = value),
                ),
                const SizedBox(height: AppSpacing.md),
                if (closed)
                  const Text(
                    'This order is closed. The follower will change without scheduling another follow-up.',
                  )
                else
                  OutlinedButton.icon(
                    onPressed: saving
                        ? null
                        : () async {
                            final selectedDate = await showDatePicker(
                              context: dialogContext,
                              initialDate: dueAt ?? DateTime.now(),
                              firstDate: DateTime.now(),
                              lastDate: DateTime.now().add(
                                const Duration(days: 730),
                              ),
                            );
                            if (selectedDate == null ||
                                !dialogContext.mounted) {
                              return;
                            }
                            final selectedTime = await showTimePicker(
                              context: dialogContext,
                              initialTime: dueAt == null
                                  ? const TimeOfDay(hour: 11, minute: 0)
                                  : TimeOfDay.fromDateTime(dueAt!),
                            );
                            if (selectedTime == null ||
                                !dialogContext.mounted) {
                              return;
                            }
                            setDialogState(() {
                              dueAt = DateTime(
                                selectedDate.year,
                                selectedDate.month,
                                selectedDate.day,
                                selectedTime.hour,
                                selectedTime.minute,
                              );
                            });
                          },
                    icon: const Icon(Icons.event_outlined),
                    label: Text(
                      dueAt == null
                          ? 'Select next follow-up date & time'
                          : AppFormatters.dateTime(dueAt),
                    ),
                  ),
                if (dialogError.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    dialogError,
                    style: const TextStyle(color: AppColors.danger),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: saving
                  ? null
                  : () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      if (selectedFollowerId == null) {
                        setDialogState(
                          () => dialogError = 'Select an order follower.',
                        );
                        return;
                      }
                      if (!closed &&
                          (dueAt == null || !dueAt!.isAfter(DateTime.now()))) {
                        setDialogState(
                          () => dialogError =
                              'Select a future follow-up date and time.',
                        );
                        return;
                      }
                      setDialogState(() {
                        saving = true;
                        dialogError = '';
                      });
                      try {
                        await widget.api.updateOrderFollower(
                          orderId: widget.orderId,
                          followerId: selectedFollowerId!,
                          followupDueAt: closed || dueAt == null
                              ? ''
                              : _dateTimeValue(dueAt!),
                        );
                        if (!dialogContext.mounted) return;
                        Navigator.pop(dialogContext, true);
                      } catch (e) {
                        if (!dialogContext.mounted) return;
                        setDialogState(() {
                          saving = false;
                          dialogError = e.toString().replaceFirst(
                            'Exception: ',
                            '',
                          );
                        });
                      }
                    },
              child: saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Update Follower'),
            ),
          ],
        ),
      ),
    );

    if (changed == true && mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Order follower updated.')));
      await _load();
    }
  }

  Future<void> _assignKarigar() async {
    int? karigarId;
    int? customerId;
    int? followerId;
    DateTime dueAt = DateTime.now().add(const Duration(days: 1));
    var saving = false;
    String error = '';
    final karigars = _karigars
        .whereType<Map>()
        .map((e) => e.cast<String, dynamic>())
        .toList();
    final customers = _assignmentCustomers
        .whereType<Map>()
        .map((e) => e.cast<String, dynamic>())
        .toList();
    final followers = _staffFollowers
        .whereType<Map>()
        .map((e) => e.cast<String, dynamic>())
        .toList();
    final saved = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const Text('Assign order'),
          content: SizedBox(
            width: 420,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (customers.isNotEmpty) ...[
                    DropdownButtonFormField<int>(
                      initialValue: customerId,
                      decoration: const InputDecoration(
                        labelText: 'Customer *',
                      ),
                      items: customers
                          .map(
                            (row) => DropdownMenuItem<int>(
                              value: int.tryParse('${row['id']}'),
                              child: Text('${row['name']}'),
                            ),
                          )
                          .toList(),
                      onChanged: saving
                          ? null
                          : (value) => update(() => customerId = value),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  DropdownButtonFormField<int>(
                    initialValue: karigarId,
                    decoration: const InputDecoration(labelText: 'Karigar *'),
                    items: karigars
                        .map(
                          (row) => DropdownMenuItem<int>(
                            value: int.tryParse('${row['id']}'),
                            child: Text('${row['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: saving
                        ? null
                        : (value) => update(() => karigarId = value),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  DropdownButtonFormField<int>(
                    initialValue: followerId,
                    decoration: const InputDecoration(
                      labelText: 'Order follower *',
                    ),
                    items: followers
                        .map(
                          (row) => DropdownMenuItem<int>(
                            value: int.tryParse('${row['id']}'),
                            child: Text('${row['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: saving
                        ? null
                        : (value) => update(() => followerId = value),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  OutlinedButton.icon(
                    onPressed: saving
                        ? null
                        : () async {
                            final day = await showDatePicker(
                              context: dialogContext,
                              initialDate: dueAt,
                              firstDate: DateTime.now(),
                              lastDate: DateTime.now().add(
                                const Duration(days: 730),
                              ),
                            );
                            if (day == null || !dialogContext.mounted) return;
                            final time = await showTimePicker(
                              context: dialogContext,
                              initialTime: TimeOfDay.fromDateTime(dueAt),
                            );
                            if (time == null) return;
                            update(
                              () => dueAt = DateTime(
                                day.year,
                                day.month,
                                day.day,
                                time.hour,
                                time.minute,
                              ),
                            );
                          },
                    icon: const Icon(Icons.event_outlined),
                    label: Text(
                      'First follow-up: ${AppFormatters.dateTime(dueAt)}',
                    ),
                  ),
                  if (error.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      error,
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  ],
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: saving
                  ? null
                  : () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      if ((customers.isNotEmpty && customerId == null) ||
                          karigarId == null ||
                          followerId == null ||
                          !dueAt.isAfter(DateTime.now())) {
                        update(
                          () => error =
                              'Select customer, karigar, follower and a future follow-up time.',
                        );
                        return;
                      }
                      update(() {
                        saving = true;
                        error = '';
                      });
                      try {
                        await widget.api.assignOrder(
                          orderId: widget.orderId,
                          customerId: customerId,
                          karigarId: karigarId!,
                          followerId: followerId!,
                          followupDueAt: _dateTimeValue(dueAt),
                        );
                        if (dialogContext.mounted)
                          Navigator.pop(dialogContext, true);
                      } catch (e) {
                        update(() {
                          saving = false;
                          error = e.toString().replaceFirst('Exception: ', '');
                        });
                      }
                    },
              child: const Text('Assign'),
            ),
          ],
        ),
      ),
    );
    if (saved == true) await _load();
  }

  Future<void> _raiseWorkRequest() async {
    String type = 'order_delay';
    DateTime? dueAt;
    final details = TextEditingController();
    final grams = TextEditingController();
    String error = '';
    var saving = false;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const Text('Request admin action'),
          content: SizedBox(
            width: 420,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: type,
                    decoration: const InputDecoration(
                      labelText: 'Request type',
                    ),
                    items: const [
                      DropdownMenuItem(
                        value: 'order_delay',
                        child: Text('Order delay'),
                      ),
                      DropdownMenuItem(
                        value: 'gold_requirement',
                        child: Text('Gold requirement'),
                      ),
                      DropdownMenuItem(
                        value: 'follower_change',
                        child: Text('Follower change'),
                      ),
                    ],
                    onChanged: saving
                        ? null
                        : (value) => update(() => type = value ?? type),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  TextField(
                    controller: details,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Reason and details *',
                      alignLabelWithHint: true,
                    ),
                  ),
                  if (type == 'order_delay') ...[
                    const SizedBox(height: AppSpacing.lg),
                    OutlinedButton.icon(
                      onPressed: () async {
                        final day = await showDatePicker(
                          context: dialogContext,
                          initialDate: DateTime.now().add(
                            const Duration(days: 1),
                          ),
                          firstDate: DateTime.now().add(
                            const Duration(days: 1),
                          ),
                          lastDate: DateTime.now().add(
                            const Duration(days: 730),
                          ),
                        );
                        if (day != null) update(() => dueAt = day);
                      },
                      icon: const Icon(Icons.calendar_today_outlined),
                      label: Text(
                        dueAt == null
                            ? 'Requested delivery date *'
                            : _dateValue(dueAt!),
                      ),
                    ),
                  ],
                  if (type == 'gold_requirement') ...[
                    const SizedBox(height: AppSpacing.lg),
                    TextField(
                      controller: grams,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: const InputDecoration(
                        labelText: 'Gold required (gm) *',
                      ),
                    ),
                  ],
                  if (error.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      error,
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  ],
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: saving ? null : () => Navigator.pop(dialogContext),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      if (details.text.trim().isEmpty ||
                          (type == 'order_delay' && dueAt == null) ||
                          (type == 'gold_requirement' &&
                              (double.tryParse(grams.text) ?? 0) <= 0)) {
                        update(() => error = 'Complete all required fields.');
                        return;
                      }
                      update(() {
                        saving = true;
                        error = '';
                      });
                      try {
                        await widget.api
                            .createOrderWorkRequest(widget.orderId, {
                              'request_type': type,
                              'details': details.text.trim(),
                              if (dueAt != null && type == 'order_delay')
                                'requested_due_at': _dateValue(dueAt!),
                              if (type == 'gold_requirement')
                                'gold_quantity_gm': double.parse(grams.text),
                            });
                        if (dialogContext.mounted) Navigator.pop(dialogContext);
                        await _load();
                      } catch (e) {
                        update(() {
                          saving = false;
                          error = e.toString().replaceFirst('Exception: ', '');
                        });
                      }
                    },
              child: const Text('Send request'),
            ),
          ],
        ),
      ),
    );
    details.dispose();
    grams.dispose();
  }

  Future<void> _reviewWorkRequest(Map<String, dynamic> row) async {
    final type = '${row['request_type']}';
    int? assignee;
    DateTime dueAt = DateTime.now().add(const Duration(days: 1));
    final note = TextEditingController();
    final staff = _staffFollowers
        .whereType<Map>()
        .map((e) => e.cast<String, dynamic>())
        .toList();
    String error = '';
    var saving = false;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: Text('Review ${_requestLabel(type)}'),
          content: SizedBox(
            width: 420,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('${row['details']}'),
                  if (type == 'gold_requirement' ||
                      type == 'follower_change') ...[
                    const SizedBox(height: AppSpacing.lg),
                    DropdownButtonFormField<int>(
                      initialValue: assignee,
                      decoration: InputDecoration(
                        labelText: type == 'follower_change'
                            ? 'New follower *'
                            : 'Issue gold by *',
                      ),
                      items: staff
                          .map(
                            (user) => DropdownMenuItem<int>(
                              value: int.tryParse('${user['id']}'),
                              child: Text('${user['name']}'),
                            ),
                          )
                          .toList(),
                      onChanged: saving
                          ? null
                          : (value) => update(() => assignee = value),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    OutlinedButton.icon(
                      onPressed: saving
                          ? null
                          : () async {
                              final day = await showDatePicker(
                                context: dialogContext,
                                initialDate: dueAt,
                                firstDate: DateTime.now(),
                                lastDate: DateTime.now().add(
                                  const Duration(days: 730),
                                ),
                              );
                              if (day == null || !dialogContext.mounted) return;
                              final time = await showTimePicker(
                                context: dialogContext,
                                initialTime: TimeOfDay.fromDateTime(dueAt),
                              );
                              if (time != null)
                                update(
                                  () => dueAt = DateTime(
                                    day.year,
                                    day.month,
                                    day.day,
                                    time.hour,
                                    time.minute,
                                  ),
                                );
                            },
                      icon: const Icon(Icons.event_outlined),
                      label: Text(
                        '${type == 'follower_change' ? 'Next follow-up' : 'Task due'}: ${AppFormatters.dateTime(dueAt)}',
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.lg),
                  TextField(
                    controller: note,
                    maxLines: 2,
                    decoration: const InputDecoration(
                      labelText: 'Decision note (required to reject)',
                    ),
                  ),
                  if (error.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      error,
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  ],
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: saving ? null : () => Navigator.pop(dialogContext),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: saving
                  ? null
                  : () async {
                      if (note.text.trim().isEmpty) {
                        update(() => error = 'Enter a rejection reason.');
                        return;
                      }
                      update(() => saving = true);
                      try {
                        await widget.api.reviewOrderWorkRequest(
                          int.parse('${row['id']}'),
                          {
                            'decision': 'reject',
                            'review_note': note.text.trim(),
                          },
                        );
                        if (dialogContext.mounted) Navigator.pop(dialogContext);
                        await _load();
                      } catch (e) {
                        update(() {
                          saving = false;
                          error = e.toString().replaceFirst('Exception: ', '');
                        });
                      }
                    },
              child: const Text('Reject'),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      if ((type == 'gold_requirement' ||
                              type == 'follower_change') &&
                          (assignee == null ||
                              !dueAt.isAfter(DateTime.now()))) {
                        update(
                          () => error = 'Select staff and a future deadline.',
                        );
                        return;
                      }
                      update(() => saving = true);
                      try {
                        await widget.api
                            .reviewOrderWorkRequest(int.parse('${row['id']}'), {
                              'decision': 'approve',
                              'review_note': note.text.trim(),
                              if (assignee != null) 'assigned_to': assignee,
                              if (type == 'gold_requirement')
                                'task_due_at': _dateTimeValue(dueAt),
                              if (type == 'follower_change')
                                'followup_due_at': _dateTimeValue(dueAt),
                            });
                        if (dialogContext.mounted) Navigator.pop(dialogContext);
                        await _load();
                      } catch (e) {
                        update(() {
                          saving = false;
                          error = e.toString().replaceFirst('Exception: ', '');
                        });
                      }
                    },
              child: const Text('Approve'),
            ),
          ],
        ),
      ),
    );
    note.dispose();
  }

  String _requestLabel(String type) => switch (type) {
    'order_delay' => 'Order delay',
    'gold_requirement' => 'Gold requirement',
    'follower_change' => 'Follower change',
    _ => type,
  };

  Future<void> _raiseDiamondRequirement() async {
    final note = TextEditingController();
    DateTime? requiredBy;
    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Raise Diamond Requirement'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: note,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Requirement note *',
                    hintText: 'Quality, quantity or special instruction',
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                OutlinedButton.icon(
                  onPressed: () async {
                    final selected = await showDatePicker(
                      context: context,
                      initialDate:
                          requiredBy ??
                          DateTime.now().add(const Duration(days: 1)),
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 365)),
                    );
                    if (selected != null) {
                      setDialogState(() => requiredBy = selected);
                    }
                  },
                  icon: const Icon(Icons.calendar_today_outlined),
                  label: Text(
                    requiredBy == null
                        ? 'Select required date'
                        : _dateValue(requiredBy!),
                  ),
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
              onPressed: () {
                if (note.text.trim().isEmpty) return;
                Navigator.pop(dialogContext, true);
              },
              child: const Text('Raise for Approval'),
            ),
          ],
        ),
      ),
    );
    if (submitted != true) {
      note.dispose();
      return;
    }
    try {
      await widget.api.raiseDiamondRequirement(
        orderId: widget.orderId,
        note: note.text,
        requiredBy: requiredBy == null ? '' : _dateValue(requiredBy!),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Diamond requirement raised for admin approval.'),
        ),
      );
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      note.dispose();
    }
  }

  String _dateValue(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

  String _dateTimeValue(DateTime value) =>
      '${_dateValue(value)} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}:00';

  Future<void> _openUrl(String url) async {
    if (url.trim().isEmpty) return;
    final ok = await launchUrl(
      Uri.parse(url),
      mode: LaunchMode.inAppBrowserView,
      browserConfiguration: const BrowserConfiguration(showTitle: true),
    );
    if (!ok && mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Unable to open document.')));
    }
  }

  String _primaryImageUrl() {
    return (_order['primary_image_url'] ??
            _order['finish_photo_url'] ??
            _order['order_photo_url'] ??
            '')
        .toString();
  }

  bool _hasReadyDocuments() {
    final packingUrl = (_order['packing_list_url'] ?? '').toString();
    final challanUrl = (_order['delivery_challan_url'] ?? '').toString();
    return packingUrl.isNotEmpty || challanUrl.isNotEmpty;
  }

  @override
  Widget build(BuildContext context) {
    final title = (_order['order_no'] ?? 'Order Detail').toString();
    final status = (_order['status'] ?? '-').toString();
    final canTakeFollowup = _canTakeOrderFollowup;
    final imageUrl = _primaryImageUrl();
    final packingListUrl = (_order['packing_list_url'] ?? '').toString();
    final deliveryChallanUrl = (_order['delivery_challan_url'] ?? '')
        .toString();
    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: _loading
          ? null
          : !canTakeFollowup
          ? null
          : FloatingActionButton.extended(
              onPressed: _takeFollowup,
              icon: const Icon(Icons.add_task),
              label: const Text('Take Followup'),
            ),
      body: _loading && _order.isEmpty
          ? const FullScreenLoader()
          : _error.isNotEmpty && _order.isEmpty
          ? AppErrorState(message: _error, onRetry: _load)
          : _order.isEmpty
          ? const AppEmptyState(
              title: 'Order not found',
              message: 'Please go back and try again.',
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  if (_loading)
                    const LinearProgressIndicator(
                      minHeight: 2,
                      color: AppColors.brandRed,
                      backgroundColor: AppColors.border,
                    ),
                  if (_loading) const SizedBox(height: AppSpacing.lg),
                  if (_error.isNotEmpty) ...[
                    Container(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      decoration: BoxDecoration(
                        color: AppColors.danger.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        border: Border.all(
                          color: AppColors.danger.withValues(alpha: 0.3),
                        ),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.error_outline,
                            color: AppColors.danger,
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Text(
                              _error,
                              style: const TextStyle(
                                color: AppColors.danger,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  Container(
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
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                (_order['order_no'] ?? '-').toString(),
                                style: const TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            AppStatusBadge(
                              label: status,
                              color: _statusColor(status),
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Wrap(
                          spacing: AppSpacing.md,
                          runSpacing: AppSpacing.sm,
                          children: [
                            _metaChip('Type', _order['order_type'] ?? '-'),
                            _metaChip('Priority', _order['priority'] ?? '-'),
                            _metaChip('Due', _order['due_date'] ?? '-'),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Row(
                    children: [
                      Expanded(
                        child: _infoCard(
                          title: 'Customer',
                          value: _order['customer_name'] ?? '-',
                          subtitle: _order['customer_phone'] ?? '',
                          icon: Icons.person_outline,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: _infoCard(
                          title: 'Karigar',
                          value: _order['karigar_name'] ?? 'Not Assigned',
                          subtitle: _order['karigar_phone'] ?? '',
                          icon: Icons.badge_outlined,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.md),
                  _infoCard(
                    title: 'Order Follower',
                    value: _order['follower_name'] ?? 'Not Assigned',
                    subtitle: _order['followup_due_at'] == null
                        ? 'No follow-up deadline'
                        : 'Due ${AppFormatters.dateTime(_order['followup_due_at'])}',
                    icon: Icons.follow_the_signs_outlined,
                  ),
                  if (_canChangeFollower) ...[
                    const SizedBox(height: AppSpacing.sm),
                    Wrap(
                      spacing: AppSpacing.md,
                      runSpacing: AppSpacing.md,
                      children: [
                        if (_canAssignKarigar)
                          FilledButton.icon(
                            onPressed: _assignKarigar,
                            icon: const Icon(Icons.assignment_ind_outlined),
                            label: const Text('Assign karigar & follower'),
                          ),
                        OutlinedButton.icon(
                          onPressed: _changeFollower,
                          icon: const Icon(Icons.manage_accounts_outlined),
                          label: const Text('Change Follower'),
                        ),
                      ],
                    ),
                  ],
                  if (canTakeFollowup) ...[
                    const SizedBox(height: AppSpacing.md),
                    OutlinedButton.icon(
                      onPressed: _raiseWorkRequest,
                      icon: const Icon(Icons.add_circle_outline),
                      label: const Text('Request admin action'),
                    ),
                  ],
                  if (!canTakeFollowup &&
                      !{
                        'Ready',
                        'Packed',
                        'Dispatched',
                        'Delivered',
                        'Completed',
                        'Complete',
                        'Cancelled',
                      }.contains(status)) ...[
                    const SizedBox(height: AppSpacing.sm),
                    const Text(
                      'Only the assigned order follower can take this follow-up.',
                      style: TextStyle(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.lg),
                  if (_workRequests.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.lg),
                    const AppSectionTitle('Order requests'),
                    const SizedBox(height: AppSpacing.md),
                    ..._workRequests.whereType<Map>().map((raw) {
                      final row = raw.cast<String, dynamic>();
                      final type = '${row['request_type']}';
                      final state = '${row['status']}';
                      return Card(
                        margin: const EdgeInsets.only(bottom: AppSpacing.md),
                        child: Padding(
                          padding: const EdgeInsets.all(AppSpacing.lg),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${_requestLabel(type)} · $state',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: AppSpacing.sm),
                              Text('${row['details']}'),
                              if (type == 'gold_requirement')
                                Text('${row['gold_quantity_gm']} gm required'),
                              if (row['review_note'] != null)
                                Text('Admin: ${row['review_note']}'),
                              if (row['assignee_name'] != null)
                                Text('Assigned to: ${row['assignee_name']}'),
                              if (row['voucher_no'] != null)
                                Text('Voucher: ${row['voucher_no']}'),
                              if (_canChangeFollower && state == 'pending') ...[
                                const SizedBox(height: AppSpacing.md),
                                OutlinedButton.icon(
                                  onPressed: () => _reviewWorkRequest(row),
                                  icon: const Icon(Icons.fact_check_outlined),
                                  label: const Text('Review request'),
                                ),
                              ],
                            ],
                          ),
                        ),
                      );
                    }),
                  ],
                  if (_diamondRequirements.isNotEmpty ||
                      _canRaiseDiamondRequirement) ...[
                    Row(
                      children: [
                        const Expanded(
                          child: AppSectionTitle('Diamond Requirement'),
                        ),
                        if (_canRaiseDiamondRequirement)
                          OutlinedButton.icon(
                            onPressed: _raiseDiamondRequirement,
                            icon: const Icon(Icons.add, size: 18),
                            label: const Text('Raise'),
                          ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    if (_diamondRequirements.isEmpty)
                      const AppEmptyState(
                        title: 'No requirement raised',
                        message:
                            'Raise a request for admin approval and bag preparation assignment.',
                      )
                    else
                      ..._diamondRequirements.map((raw) {
                        final row = (raw as Map).cast<String, dynamic>();
                        final requirementStatus = (row['status'] ?? '')
                            .toString();
                        final bagNo = (row['bag_no'] ?? '').toString();
                        return Card(
                          margin: const EdgeInsets.only(bottom: AppSpacing.sm),
                          child: ListTile(
                            leading: const Icon(Icons.diamond_outlined),
                            title: Text(
                              (row['requirement_no'] ?? '-').toString(),
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            subtitle: Text(
                              '${requirementStatus.replaceAll('_', ' ')} · ${row['assignee_name'] ?? 'Awaiting admin'}${bagNo.isNotEmpty ? ' · $bagNo' : ''}',
                            ),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () async {
                              final changed = await Navigator.of(context)
                                  .push<bool>(
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          DiamondRequirementDetailScreen(
                                            api: widget.api,
                                            requirementId:
                                                int.tryParse(
                                                  row['id'].toString(),
                                                ) ??
                                                0,
                                          ),
                                    ),
                                  );
                              if (changed == true) _load();
                            },
                          ),
                        );
                      }),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  if (imageUrl.isNotEmpty) ...[
                    const AppSectionTitle('Order Image'),
                    const SizedBox(height: AppSpacing.md),
                    GestureDetector(
                      onTap: () {
                        showDialog(
                          context: context,
                          builder: (_) => Dialog(
                            child: InteractiveViewer(
                              child: Image.network(
                                imageUrl,
                                fit: BoxFit.contain,
                                errorBuilder: (_, __, ___) => const Padding(
                                  padding: EdgeInsets.all(AppSpacing.xl),
                                  child: Text('Unable to load image.'),
                                ),
                              ),
                            ),
                          ),
                        );
                      },
                      child: Container(
                        decoration: BoxDecoration(
                          color: AppColors.card,
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                          border: Border.all(color: AppColors.border),
                          boxShadow: AppShadows.soft,
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: AspectRatio(
                          aspectRatio: 4 / 3,
                          child: Image.network(
                            imageUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => const Center(
                              child: Text('Unable to load image.'),
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  if ((_order['order_notes'] ?? '').toString().isNotEmpty) ...[
                    const AppSectionTitle('Notes'),
                    const SizedBox(height: AppSpacing.md),
                    Container(
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      decoration: BoxDecoration(
                        color: AppColors.card,
                        borderRadius: BorderRadius.circular(AppRadius.lg),
                        border: Border.all(color: AppColors.border),
                        boxShadow: AppShadows.soft,
                      ),
                      child: Text((_order['order_notes'] ?? '').toString()),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  if (_hasReadyDocuments()) ...[
                    const AppSectionTitle('Ready Documents'),
                    const SizedBox(height: AppSpacing.md),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(AppSpacing.lg),
                        child: Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            if (packingListUrl.isNotEmpty)
                              FilledButton.icon(
                                onPressed: () => _openUrl(packingListUrl),
                                icon: const Icon(Icons.inventory_2_outlined),
                                label: const Text('Download Packing List'),
                              ),
                            if (deliveryChallanUrl.isNotEmpty)
                              FilledButton.icon(
                                onPressed: () => _openUrl(deliveryChallanUrl),
                                icon: const Icon(Icons.local_shipping_outlined),
                                label: const Text('Download Delivery Challan'),
                              ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  const AppSectionTitle('Order Items'),
                  const SizedBox(height: AppSpacing.md),
                  if (_items.isEmpty)
                    const AppEmptyState(
                      title: 'No items',
                      message: 'No order items added yet.',
                    )
                  else
                    ..._items.map((itemRaw) {
                      final item = (itemRaw as Map).cast<String, dynamic>();
                      return Container(
                        margin: const EdgeInsets.only(bottom: AppSpacing.md),
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
                            Text(
                              '${item['design_name'] ?? '-'} (${item['design_code'] ?? '-'})',
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            Text(
                              'Qty: ${item['qty'] ?? 0} | Size: ${item['size_label'] ?? '-'}',
                            ),
                            Text(
                              'Gold: ${(item['gold_required_gm'] ?? 0)} gm | Diamond: ${(item['diamond_required_cts'] ?? 0)} cts',
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            AppStatusBadge(
                              label: (item['item_status'] ?? '-').toString(),
                              color: _statusColor(
                                (item['item_status'] ?? '').toString(),
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                  const SizedBox(height: AppSpacing.lg),
                  const AppSectionTitle('Followups'),
                  const SizedBox(height: AppSpacing.md),
                  if (_followups.isEmpty)
                    AppEmptyState(
                      title: 'No followups yet',
                      message: canTakeFollowup
                          ? 'Tap "Take Followup" to add the first update.'
                          : 'The assigned follower can add the first update.',
                    )
                  else
                    ..._followups.map((rowRaw) {
                      final row = (rowRaw as Map).cast<String, dynamic>();
                      final imageUrl = (row['image_url'] ?? '').toString();
                      return Container(
                        margin: const EdgeInsets.only(bottom: AppSpacing.md),
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
                            Row(
                              children: [
                                const Icon(
                                  Icons.task_alt,
                                  color: AppColors.brandRed,
                                ),
                                const SizedBox(width: AppSpacing.sm),
                                Text(
                                  (row['stage'] ?? '-').toString(),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            Text((row['description'] ?? '').toString()),
                            const SizedBox(height: AppSpacing.sm),
                            Text(
                              'Next: ${(row['next_followup_date'] ?? '-')} | By: ${(row['followup_taken_by_name'] ?? '-')}',
                              style: const TextStyle(
                                color: AppColors.textSecondary,
                              ),
                            ),
                            if (imageUrl.isNotEmpty) ...[
                              const SizedBox(height: AppSpacing.sm),
                              OutlinedButton.icon(
                                onPressed: () {
                                  showDialog(
                                    context: context,
                                    builder: (_) => Dialog(
                                      child: InteractiveViewer(
                                        child: Image.network(
                                          imageUrl,
                                          fit: BoxFit.contain,
                                        ),
                                      ),
                                    ),
                                  );
                                },
                                icon: const Icon(Icons.image_outlined),
                                label: const Text('View Image'),
                              ),
                            ],
                          ],
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'Completed':
        return AppColors.success;
      case 'Cancelled':
        return AppColors.danger;
      case 'In Production':
        return AppColors.warning;
      case 'QC':
        return AppColors.brandGold;
      case 'Ready':
        return AppColors.stone;
      default:
        return AppColors.brandRed;
    }
  }

  Widget _metaChip(String label, Object value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.background,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: Text(
        '$label: $value',
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AppColors.textSecondary,
        ),
      ),
    );
  }

  Widget _infoCard({
    required String title,
    required Object value,
    required String subtitle,
    required IconData icon,
  }) {
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
          Icon(icon, color: AppColors.brandRed),
          const SizedBox(height: AppSpacing.sm),
          Text(
            title,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            value.toString(),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          if (subtitle.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.xs),
            Text(
              subtitle,
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
