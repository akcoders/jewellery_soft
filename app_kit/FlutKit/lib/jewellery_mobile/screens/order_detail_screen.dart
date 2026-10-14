import 'dart:convert';

import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_status_badge.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_mobile/screens/order_followup_form_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/diamond_bag_create_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/diamond_bags_screen.dart';
import 'package:flutkit/jewellery_mobile/widgets/order_full_details.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
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
  List<dynamic> _familyOrders = [];
  List<dynamic> _workRequests = [];
  List<dynamic> _diamondBags = [];
  List<dynamic> _stoneIssues = [];
  bool _isReceived = false;
  List<dynamic> _karigars = [];
  List<dynamic> _assignmentCustomers = [];
  bool _canAssignKarigar = false;
  bool _diamondSupported = false;
  bool _canCreateDiamondBag = false;
  bool _canTakeOrderFollowup = false;
  bool _followupGalleryEnabled = false;
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
        _familyOrders = (data['family_orders'] as List?) ?? <dynamic>[];
        _workRequests = requests;
        _diamondBags = (data['diamond_bags'] as List?) ?? <dynamic>[];
        _stoneIssues = (data['stone_issues'] as List?) ?? <dynamic>[];
        _isReceived = data['is_received'] == true;
        _karigars = (data['karigars'] as List?) ?? <dynamic>[];
        _assignmentCustomers =
            (data['assignment_customers'] as List?) ?? <dynamic>[];
        _canAssignKarigar =
            data['can_assign_karigar'] == true ||
            data['can_assign_karigar'] == 1;
        _diamondSupported =
            data['diamond_supported'] == true || data['diamond_supported'] == 1;
        _canCreateDiamondBag =
            data['can_create_diamond_bag'] == true ||
            data['can_create_diamond_bag'] == 1;
        _canTakeOrderFollowup =
            data['can_add_followup'] == true || data['can_add_followup'] == 1;
        _followupGalleryEnabled =
            data['followup_gallery_enabled'] == true ||
            data['followup_gallery_enabled'] == 1;
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
          familyOrders: _familyOrders,
          allowGallery: _followupGalleryEnabled,
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
    final isRepair =
        (_order['order_type'] ?? '').toString().toLowerCase() == 'repair';
    final repairReceiveWeight =
        double.tryParse('${_order['repair_receive_weight_gm'] ?? ''}') ?? 0;
    final repairIssueWeight = TextEditingController(
      text: isRepair && repairReceiveWeight > 0
          ? repairReceiveWeight.toStringAsFixed(3)
          : '',
    );
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
                  if (isRepair) ...[
                    const SizedBox(height: AppSpacing.lg),
                    TextField(
                      controller: repairIssueWeight,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: InputDecoration(
                        labelText:
                            'Old ornament gross weight issued to karigar (gm) *',
                        helperText: repairReceiveWeight > 0
                            ? 'Customer se received: ${repairReceiveWeight.toStringAsFixed(3)} gm'
                            : 'Required for repair orders',
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
                          (isRepair &&
                              (double.tryParse(repairIssueWeight.text.trim()) ??
                                      0) <=
                                  0) ||
                          !dueAt.isAfter(DateTime.now())) {
                        update(
                          () => error = isRepair
                              ? 'Select customer, karigar, follower, future follow-up and enter issued old ornament gross weight.'
                              : 'Select customer, karigar, follower and a future follow-up time.',
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
                          repairIssueWeightGm: isRepair
                              ? double.tryParse(repairIssueWeight.text.trim())
                              : null,
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
    repairIssueWeight.dispose();
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
                        value: 'diamond_requirement',
                        child: Text('Diamond requirement'),
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
    final requestedDueAt = DateTime.tryParse(
      (row['requested_due_at'] ?? '').toString(),
    );
    final currentOrderDueAt = DateTime.tryParse(
      (row['due_date'] ?? '').toString(),
    );
    final tomorrow = DateUtils.dateOnly(
      DateTime.now().add(const Duration(days: 1)),
    );
    final minimumDelayDueAt =
        currentOrderDueAt != null &&
            !DateUtils.dateOnly(currentOrderDueAt).isBefore(tomorrow)
        ? DateUtils.dateOnly(currentOrderDueAt.add(const Duration(days: 1)))
        : tomorrow;
    DateTime dueAt =
        requestedDueAt != null &&
            !DateUtils.dateOnly(requestedDueAt).isBefore(minimumDelayDueAt)
        ? requestedDueAt
        : minimumDelayDueAt;
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
                  if (type == 'order_delay') ...[
                    const SizedBox(height: AppSpacing.lg),
                    OutlinedButton.icon(
                      onPressed: saving
                          ? null
                          : () async {
                              final day = await showDatePicker(
                                context: dialogContext,
                                initialDate: DateUtils.dateOnly(dueAt),
                                firstDate: minimumDelayDueAt,
                                lastDate: minimumDelayDueAt.add(
                                  const Duration(days: 730),
                                ),
                              );
                              if (day != null) update(() => dueAt = day);
                            },
                      icon: const Icon(Icons.calendar_today),
                      label: Text(
                        'Updated order due date: ${AppFormatters.date(dueAt)}',
                      ),
                    ),
                  ],
                  if (type == 'gold_requirement' ||
                      type == 'diamond_requirement' ||
                      type == 'follower_change') ...[
                    const SizedBox(height: AppSpacing.lg),
                    DropdownButtonFormField<int>(
                      initialValue: assignee,
                      decoration: InputDecoration(
                        labelText: type == 'follower_change'
                            ? 'New follower *'
                            : type == 'diamond_requirement'
                            ? 'Create bag by *'
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
                    decoration: InputDecoration(
                      labelText: type == 'order_delay'
                          ? 'Admin note *'
                          : 'Decision note (required to reject)',
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
                      if (type == 'order_delay' &&
                          (note.text.trim().isEmpty ||
                              !DateUtils.dateOnly(
                                dueAt,
                              ).isAfter(DateUtils.dateOnly(DateTime.now())))) {
                        update(
                          () => error =
                              'Enter an admin note and select a future updated due date.',
                        );
                        return;
                      }
                      if ((type == 'gold_requirement' ||
                              type == 'diamond_requirement' ||
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
                              if (type == 'order_delay')
                                'approved_due_at': _dateValue(dueAt),
                              if (assignee != null) 'assigned_to': assignee,
                              if (type == 'gold_requirement')
                                'task_due_at': _dateTimeValue(dueAt),
                              if (type == 'diamond_requirement')
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
    'diamond_requirement' => 'Diamond requirement',
    'follower_change' => 'Follower change',
    _ => type,
  };

  Future<void> _createDiamondBag() async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) =>
            DiamondBagCreateScreen(api: widget.api, orderId: widget.orderId),
      ),
    );
    if (changed == true) await _load();
  }

  Future<void> _openDiamondBag(int bagId) async {
    if (bagId <= 0) return;
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => DiamondBagDetailScreen(api: widget.api, bagId: bagId),
      ),
    );
  }

  Future<void> _issueDiamondBag(Map<String, dynamic> bag) async {
    XFile? evidence;
    var picking = false;
    var saving = false;
    var error = '';
    final notes = TextEditingController();
    final issued = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: Text('Issue ${bag['bag_no'] ?? 'diamond bag'}'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'The complete available bag will be issued to the order’s assigned karigar.',
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
                const SizedBox(height: AppSpacing.lg),
                OutlinedButton.icon(
                  onPressed: picking || saving
                      ? null
                      : () async {
                          update(() => picking = true);
                          final file = await ImagePicker().pickImage(
                            source: ImageSource.camera,
                            imageQuality: 85,
                            maxWidth: 1800,
                            requestFullMetadata: false,
                          );
                          if (dialogContext.mounted) {
                            update(() {
                              evidence = file ?? evidence;
                              picking = false;
                            });
                          }
                        },
                  icon: picking
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.add_a_photo_outlined),
                  label: Text(
                    evidence == null
                        ? 'Take Evidence Photo *'
                        : 'Replace ${evidence!.name}',
                  ),
                ),
                if (evidence != null)
                  const Padding(
                    padding: EdgeInsets.only(top: 8),
                    child: Text(
                      'Evidence attached and ready to upload.',
                      style: TextStyle(
                        color: AppColors.success,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                const SizedBox(height: AppSpacing.md),
                TextField(
                  controller: notes,
                  maxLength: 500,
                  maxLines: 3,
                  enabled: !saving,
                  decoration: const InputDecoration(
                    labelText: 'Issue note (optional)',
                    border: OutlineInputBorder(),
                  ),
                ),
                if (error.isNotEmpty)
                  Text(error, style: const TextStyle(color: AppColors.danger)),
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
            FilledButton.icon(
              onPressed: saving || evidence == null
                  ? null
                  : () async {
                      update(() {
                        saving = true;
                        error = '';
                      });
                      try {
                        final result = await widget.api.issueDiamondBag(
                          _int(bag['id']),
                          attachmentBase64: await _imageData(evidence!),
                          notes: notes.text.trim(),
                        );
                        if (!dialogContext.mounted) return;
                        Navigator.pop(dialogContext, true);
                        if (mounted) {
                          final approval = result['approval_required'] == true;
                          ScaffoldMessenger.of(this.context).showSnackBar(
                            SnackBar(
                              content: Text(
                                approval
                                    ? 'Issuement sent for admin approval.'
                                    : 'Diamond bag issued successfully.',
                              ),
                            ),
                          );
                        }
                      } catch (exception) {
                        if (!dialogContext.mounted) return;
                        update(() {
                          saving = false;
                          error = exception.toString().replaceFirst(
                            'Exception: ',
                            '',
                          );
                        });
                      }
                    },
              icon: saving
                  ? const SizedBox(
                      width: 17,
                      height: 17,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send_outlined, size: 18),
              label: const Text('Create Issuement'),
            ),
          ],
        ),
      ),
    );
    notes.dispose();
    if (issued == true) await _load();
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

  Widget _issuedDiamondBagCard(Map<String, dynamic> bag) {
    final bagId = _int(bag['id']);
    final imageUrl = (bag['audit_image_url'] ?? '').toString();
    final status = (bag['status'] ?? 'ready').toString();
    final canIssue = bag['can_issue'] == true || bag['can_issue'] == 1;
    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: bagId > 0 ? () => _openDiamondBag(bagId) : null,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 52,
                height: 52,
                clipBehavior: Clip.antiAlias,
                decoration: BoxDecoration(
                  color: AppColors.diamond.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: imageUrl.isEmpty
                    ? const Icon(
                        Icons.diamond_outlined,
                        color: AppColors.brandRed,
                      )
                    : Image.network(
                        imageUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, _, _) => const Icon(
                          Icons.diamond_outlined,
                          color: AppColors.brandRed,
                        ),
                      ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            (bag['bag_no'] ?? 'Diamond bag').toString(),
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const Icon(
                          Icons.chevron_right,
                          size: 20,
                          color: AppColors.textSecondary,
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${AppFormatters.quantity(bag['issued_pcs'])} pcs · ${AppFormatters.quantity(bag['issued_cts'])} cts issued',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 7),
                    Wrap(
                      spacing: 7,
                      runSpacing: 7,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        AppStatusBadge(
                          label: status == 'issued'
                              ? 'ISSUED TO ORDER'
                              : (status == 'pending_issue_approval'
                                    ? 'ISSUE APPROVAL PENDING'
                                    : 'READY TO ISSUE'),
                          color: status == 'issued'
                              ? AppColors.success
                              : AppColors.warning,
                        ),
                        Text(
                          '${_int(bag['item_count'])} calibrated row${_int(bag['item_count']) == 1 ? '' : 's'}',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 11,
                          ),
                        ),
                        if ((bag['last_issued_on'] ?? '').toString().isNotEmpty)
                          Text(
                            'Issued ${AppFormatters.date(bag['last_issued_on'])}',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 11,
                            ),
                          ),
                      ],
                    ),
                    if (canIssue) ...[
                      const SizedBox(height: AppSpacing.md),
                      FilledButton.icon(
                        onPressed: () => _issueDiamondBag(bag),
                        icon: const Icon(Icons.send_outlined, size: 17),
                        label: const Text('Issue with Evidence'),
                      ),
                    ] else if ((bag['issue_blocked_reason'] ?? '')
                        .toString()
                        .isNotEmpty) ...[
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        bag['issue_blocked_reason'].toString(),
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
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
                              if (type == 'order_delay')
                                Text(
                                  row['approved_due_at'] != null
                                      ? 'Updated due: ${AppFormatters.date(row['approved_due_at'])}'
                                      : 'Requested due: ${AppFormatters.date(row['requested_due_at'])}',
                                ),
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
                  if (_diamondSupported || _diamondBags.isNotEmpty) ...[
                    Row(
                      children: [
                        const Expanded(child: AppSectionTitle('Diamond Bag')),
                        if (_canCreateDiamondBag)
                          OutlinedButton.icon(
                            onPressed: _createDiamondBag,
                            icon: const Icon(
                              Icons.inventory_2_outlined,
                              size: 18,
                            ),
                            label: const Text('Create Bag'),
                          ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    if (_diamondBags.isEmpty)
                      Text(
                        _canCreateDiamondBag
                            ? 'No diamond bag has been issued to this order yet. You can prepare a size-wise bag now.'
                            : 'No diamond bag has been issued to this order yet.',
                        style: const TextStyle(color: AppColors.textSecondary),
                      )
                    else
                      ..._diamondBags.whereType<Map>().map(
                        (bag) =>
                            _issuedDiamondBagCard(bag.cast<String, dynamic>()),
                      ),
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
                  const AppSectionTitle('Complete order details'),
                  const SizedBox(height: AppSpacing.md),
                  OrderFullDetails(
                    order: _order,
                    stoneIssues: _stoneIssues,
                    isReceived: _isReceived,
                  ),
                  const SizedBox(height: AppSpacing.lg),
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
                      final approvalStatus =
                          (row['approval_status'] ?? 'approved')
                              .toString()
                              .toLowerCase();
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
                              '${row['source_order_no'] ?? ''}${row['followup_scope'] == 'family' ? ' · Same for family' : ''}',
                              style: const TextStyle(
                                color: AppColors.textSecondary,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: AppSpacing.sm),
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
                            AppStatusBadge(
                              label:
                                  approvalStatus == 'approved' &&
                                      '${row['points_enabled']}' == '0'
                                  ? 'Approved · No point'
                                  : _approvalLabel(approvalStatus),
                              color: _approvalColor(approvalStatus),
                            ),
                            if ((row['review_note'] ?? '')
                                .toString()
                                .trim()
                                .isNotEmpty) ...[
                              const SizedBox(height: AppSpacing.sm),
                              Text(
                                'Review: ${row['review_note']}',
                                style: const TextStyle(
                                  color: AppColors.textSecondary,
                                ),
                              ),
                            ],
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

  String _approvalLabel(String status) => switch (status) {
    'pending' => 'Pending approval',
    'rejected' => 'Disapproved',
    _ => 'Approved',
  };

  Color _approvalColor(String status) => switch (status) {
    'pending' => AppColors.warning,
    'rejected' => AppColors.danger,
    _ => AppColors.success,
  };

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

  int _int(dynamic value) {
    return value is num ? value.toInt() : int.tryParse('$value') ?? 0;
  }
}
