import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_status_badge.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_pwa/screens/order_followup_form_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/diamond_bag_create_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
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
  List<dynamic> _workRequests = [];
  List<dynamic> _karigars = [];
  List<dynamic> _assignmentCustomers = [];
  bool _canAssignKarigar = false;
  bool _diamondSupported = false;
  bool _canCreateDiamondBag = false;
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
        _workRequests = requests;
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
          insetPadding: const EdgeInsets.symmetric(
            horizontal: 16,
            vertical: 24,
          ),
          title: const Text('Change Order Follower'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                DropdownButtonFormField<int>(
                  isExpanded: true,
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
                    icon: const Icon(LucideIcons.calendar_days),
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
                      isExpanded: true,
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
                    isExpanded: true,
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
                    isExpanded: true,
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
                    icon: const Icon(LucideIcons.calendar_days),
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
                    isExpanded: true,
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
                      icon: const Icon(LucideIcons.calendar_days),
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
                      type == 'diamond_requirement' ||
                      type == 'follower_change') ...[
                    const SizedBox(height: AppSpacing.lg),
                    DropdownButtonFormField<int>(
                      isExpanded: true,
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
                      icon: const Icon(LucideIcons.calendar_days),
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
            tooltip: 'Refresh order',
            onPressed: _loading ? null : _load,
            icon: const Icon(LucideIcons.refresh_cw),
          ),
        ],
      ),
      floatingActionButton: _loading
          ? null
          : !canTakeFollowup
          ? null
          : FloatingActionButton.extended(
              onPressed: _takeFollowup,
              icon: const Icon(LucideIcons.clipboard_plus),
              label: const Text('Add follow-up'),
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
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 104),
                physics: const AlwaysScrollableScrollPhysics(),
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
                            LucideIcons.circle_alert,
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
                  _overviewCard(status),
                  const SizedBox(height: 24),
                  const AppSectionTitle('People & responsibility'),
                  const SizedBox(height: 14),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final people = [
                        _infoCard(
                          title: 'Customer',
                          value: _order['customer_name'] ?? '-',
                          subtitle: _order['customer_phone'] ?? '',
                          icon: LucideIcons.user_round,
                        ),
                        _infoCard(
                          title: 'Karigar',
                          value: _order['karigar_name'] ?? 'Not assigned',
                          subtitle: _order['karigar_phone'] ?? '',
                          icon: LucideIcons.hammer,
                        ),
                      ];
                      if (constraints.maxWidth < 520) {
                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            people[0],
                            const SizedBox(height: 12),
                            people[1],
                          ],
                        );
                      }
                      return Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(child: people[0]),
                          const SizedBox(width: 12),
                          Expanded(child: people[1]),
                        ],
                      );
                    },
                  ),
                  const SizedBox(height: 12),
                  _infoCard(
                    title: 'Order follower',
                    value: _order['follower_name'] ?? 'Not assigned',
                    subtitle: _order['followup_due_at'] == null
                        ? 'No follow-up deadline'
                        : 'Due ${AppFormatters.dateTime(_order['followup_due_at'])}',
                    icon: LucideIcons.user_round_check,
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
                            icon: const Icon(LucideIcons.users_round),
                            label: const Text('Assign team'),
                          ),
                        OutlinedButton.icon(
                          onPressed: _changeFollower,
                          icon: const Icon(LucideIcons.user_round_cog),
                          label: const Text('Change follower'),
                        ),
                      ],
                    ),
                  ],
                  if (canTakeFollowup) ...[
                    const SizedBox(height: AppSpacing.md),
                    OutlinedButton.icon(
                      onPressed: _raiseWorkRequest,
                      icon: const Icon(LucideIcons.circle_plus),
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
                                _requestLabel(type),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 10),
                              AppStatusBadge(
                                label: state.replaceAll('_', ' '),
                                color: state == 'rejected'
                                    ? AppColors.danger
                                    : state == 'pending'
                                    ? AppColors.brandGold
                                    : AppColors.success,
                              ),
                              const SizedBox(height: 14),
                              Text(
                                '${row['details']}',
                                style: const TextStyle(height: 1.6),
                              ),
                              if (type == 'gold_requirement')
                                _detailLine(
                                  LucideIcons.gem,
                                  '${row['gold_quantity_gm']} gm required',
                                ),
                              if (row['review_note'] != null)
                                _detailLine(
                                  LucideIcons.message_square,
                                  'Admin: ${row['review_note']}',
                                ),
                              if (row['assignee_name'] != null)
                                _detailLine(
                                  LucideIcons.user_round,
                                  'Assigned to ${row['assignee_name']}',
                                ),
                              if (row['voucher_no'] != null)
                                _detailLine(
                                  LucideIcons.receipt,
                                  'Voucher ${row['voucher_no']}',
                                ),
                              if (_canChangeFollower && state == 'pending') ...[
                                const SizedBox(height: AppSpacing.md),
                                OutlinedButton.icon(
                                  onPressed: () => _reviewWorkRequest(row),
                                  icon: const Icon(LucideIcons.clipboard_check),
                                  label: const Text('Review request'),
                                ),
                              ],
                            ],
                          ),
                        ),
                      );
                    }),
                  ],
                  if (_diamondSupported) ...[
                    Row(
                      children: [
                        const Expanded(child: AppSectionTitle('Diamond Bag')),
                        if (_canCreateDiamondBag)
                          OutlinedButton.icon(
                            onPressed: _createDiamondBag,
                            icon: const Icon(
                              LucideIcons.package_plus,
                              size: 18,
                            ),
                            label: const Text('Create Bag'),
                          ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      _canCreateDiamondBag
                          ? 'Create the size-wise bag directly for this Diamond / Jadau order.'
                          : 'Use Diamond requirement under Request admin action to ask for bag preparation.',
                      style: const TextStyle(color: AppColors.textSecondary),
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
                                label: const Text('Packing list'),
                              ),
                            if (deliveryChallanUrl.isNotEmpty)
                              FilledButton.icon(
                                onPressed: () => _openUrl(deliveryChallanUrl),
                                icon: const Icon(LucideIcons.truck),
                                label: const Text('Delivery challan'),
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
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                _metaChip('Qty', item['qty'] ?? 0),
                                _metaChip('Size', item['size_label'] ?? '-'),
                                _metaChip(
                                  'Gold',
                                  '${item['gold_required_gm'] ?? 0} gm',
                                ),
                                _metaChip(
                                  'Diamond',
                                  '${item['diamond_required_cts'] ?? 0} cts',
                                ),
                              ],
                            ),
                            const SizedBox(height: 14),
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
                  const AppSectionTitle('Follow-up timeline'),
                  const SizedBox(height: AppSpacing.md),
                  if (_followups.isEmpty)
                    AppEmptyState(
                      title: 'No followups yet',
                      message: canTakeFollowup
                          ? 'Tap "Add follow-up" to record the first update.'
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
                                  LucideIcons.circle_check,
                                  color: AppColors.brandRed,
                                ),
                                const SizedBox(width: AppSpacing.sm),
                                Expanded(
                                  child: Text(
                                    (row['stage'] ?? '-').toString(),
                                    style: Theme.of(
                                      context,
                                    ).textTheme.titleMedium,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            Text((row['description'] ?? '').toString()),
                            const SizedBox(height: AppSpacing.sm),
                            const Divider(height: 24),
                            _detailLine(
                              LucideIcons.calendar_clock,
                              'Next ${AppFormatters.dateTime(row['next_followup_date'])}',
                            ),
                            _detailLine(
                              LucideIcons.user_round,
                              '${row['followup_taken_by_name'] ?? '-'}',
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
                                icon: const Icon(LucideIcons.image),
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

  Widget _overviewCard(String status) => Container(
    padding: const EdgeInsets.all(22),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [AppColors.plum, Color(0xFF603047)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(AppRadius.xl),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(LucideIcons.gem, size: 20, color: AppColors.paleGold),
            SizedBox(width: 10),
            Text(
              'ORDER OVERVIEW',
              style: TextStyle(
                color: Color(0xFFE9D7DE),
                fontSize: 11,
                letterSpacing: 1.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Text(
          '${_order['order_no'] ?? '-'}',
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 14),
        DecoratedBox(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(30),
          ),
          child: AppStatusBadge(label: status, color: _statusColor(status)),
        ),
        const SizedBox(height: 20),
        Wrap(
          spacing: 20,
          runSpacing: 14,
          children: [
            _overviewMetric('Order type', '${_order['order_type'] ?? '-'}'),
            _overviewMetric('Priority', '${_order['priority'] ?? '-'}'),
            _overviewMetric('Due date', AppFormatters.date(_order['due_date'])),
          ],
        ),
      ],
    ),
  );

  Widget _overviewMetric(String label, String value) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: const TextStyle(color: Color(0xFFDCC5CF), fontSize: 11),
      ),
      const SizedBox(height: 5),
      Text(
        value,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w600,
          fontSize: 13,
        ),
      ),
    ],
  );

  Widget _detailLine(IconData icon, String label) => Padding(
    padding: const EdgeInsets.only(top: 10),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: AppColors.textSecondary),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
              height: 1.5,
            ),
          ),
        ),
      ],
    ),
  );

  Widget _infoCard({
    required String title,
    required Object value,
    required String subtitle,
    required IconData icon,
  }) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(11),
            decoration: BoxDecoration(
              color: AppColors.paleGold,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: AppColors.plum, size: 21),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  value.toString(),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                if (subtitle.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                      height: 1.5,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
