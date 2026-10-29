import 'dart:convert';

import 'package:flutkit/jewellery_mobile/services/followup_stage_policy.dart';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutkit/jewellery_mobile/services/followup_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_pwa/services/app_image_picker.dart';

class OrderFollowupFormScreen extends StatefulWidget {
  const OrderFollowupFormScreen({
    super.key,
    required this.api,
    required this.orderId,
    required this.stages,
    this.familyOrders = const [],
    this.allowGallery = false,
  });

  final MobileApiService api;
  final int orderId;
  final List<String> stages;
  final List<dynamic> familyOrders;
  final bool allowGallery;

  @override
  State<OrderFollowupFormScreen> createState() =>
      _OrderFollowupFormScreenState();
}

class _OrderFollowupFormScreenState extends State<OrderFollowupFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _descCtrl = TextEditingController();
  String? _stage;
  DateTime? _nextDate;
  TimeOfDay? _nextTime;
  XFile? _picked;
  ImageSource _pickedSource = ImageSource.camera;
  bool _saving = false;
  bool _sameForSubOrders = true;
  final Map<int, TextEditingController> _childDescriptions = {};
  final Map<int, TextEditingController> _childNextDates = {};

  List<String> get _stages =>
      FollowupStagePolicy.selectableStages(widget.stages);
  bool get _requiresNextFollowup =>
      FollowupStagePolicy.requiresNextFollowup(_stage);

  @override
  void initState() {
    super.initState();
    _stage = _stages.first;
    for (final raw in widget.familyOrders.whereType<Map>()) {
      final id = int.tryParse('${raw['id']}') ?? 0;
      if (id > 0 && id != widget.orderId) {
        _childDescriptions[id] = TextEditingController();
        _childNextDates[id] = TextEditingController();
      }
    }
  }

  @override
  void didUpdateWidget(OrderFollowupFormScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!_stages.contains(_stage)) _stage = _stages.first;
  }

  @override
  void dispose() {
    _descCtrl.dispose();
    for (final controller in [
      ..._childDescriptions.values,
      ..._childNextDates.values,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _pickImage() async {
    final file = await AppImagePicker().pickImage(
      context,
      title: 'Add follow-up evidence',
      imageQuality: 85,
      maxWidth: 1800,
      allowGallery: widget.allowGallery,
      onSourceSelected: (source) => _pickedSource = source,
    );
    if (!mounted) return;
    if (file != null) setState(() => _picked = file);
  }

  Future<void> _submit() async {
    final valid = _formKey.currentState?.validate() ?? false;
    if (!valid || _saving || !_stages.contains(_stage)) return;

    setState(() => _saving = true);
    try {
      var base64Image = '';
      if (_picked != null) {
        final bytes = await _picked!.readAsBytes();
        base64Image = base64Encode(bytes);
      }

      final nextFollowupDateTime = _followupDateTimeString();
      final scheduleAt = _nextDate == null
          ? null
          : FollowupNotificationService.normalizedFollowupTime(
              nextFollowupDateTime,
            );
      if (scheduleAt != null && scheduleAt.isBefore(DateTime.now())) {
        throw Exception('Next followup time must be in the future.');
      }

      final response = await widget.api.addFollowup(
        orderId: widget.orderId,
        stage: _stage!,
        description: _descCtrl.text.trim(),
        nextFollowupDate: nextFollowupDateTime,
        imageBase64: base64Image,
        imageSource: _pickedSource.name,
        sameForSubOrders: _sameForSubOrders,
        childFollowups: _childDescriptions.entries
            .map(
              (entry) => {
                'order_id': entry.key,
                'stage': _stage,
                'description': entry.value.text.trim(),
                'next_followup_date': _requiresNextFollowup
                    ? (_childNextDates[entry.key]?.text.trim() ?? '')
                    : '',
              },
            )
            .toList(),
      );
      final notification =
          (response['notification'] as Map?)?.cast<String, dynamic>() ?? {};
      final queued = notification['queued'] == true;
      if (!mounted) return;
      if (scheduleAt != null && !queued) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              (notification['message'] ??
                      'Followup saved but push notification could not be queued.')
                  .toString(),
            ),
          ),
        );
      }
      TaskRefreshBus.notify();
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Take Followup')),
      body: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const AppSectionTitle('Followup Details'),
                const SizedBox(height: AppSpacing.md),
                DropdownButtonFormField<String>(
                  key: ValueKey(_stage),
                  initialValue: _stage,
                  decoration: const InputDecoration(labelText: 'Stage'),
                  items: _stages
                      .map(
                        (stage) =>
                            DropdownMenuItem(value: stage, child: Text(stage)),
                      )
                      .toList(),
                  onChanged: _saving ? null : (v) => setState(() => _stage = v),
                  validator: (v) =>
                      !_stages.contains(v) ? 'Select a production stage' : null,
                ),
                const SizedBox(height: AppSpacing.md),
                TextFormField(
                  controller: _descCtrl,
                  minLines: 3,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Description',
                    alignLabelWithHint: true,
                  ),
                  validator: (v) => (v == null || v.trim().isEmpty)
                      ? 'Description is required'
                      : null,
                ),
                if (_childDescriptions.isNotEmpty) ...[
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Same for sub order'),
                    subtitle: const Text(
                      'One follow-up and one approval for the complete order family.',
                    ),
                    value: _sameForSubOrders,
                    onChanged: (value) =>
                        setState(() => _sameForSubOrders = value),
                  ),
                  if (!_sameForSubOrders)
                    ...widget.familyOrders
                        .whereType<Map>()
                        .where((row) => '${row['id']}' != '${widget.orderId}')
                        .map((row) {
                          final id = int.tryParse('${row['id']}') ?? 0;
                          return Card(
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Column(
                                children: [
                                  Align(
                                    alignment: Alignment.centerLeft,
                                    child: Text(
                                      '${row['order_no']} — ${row['order_name']}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                  TextFormField(
                                    controller: _childDescriptions[id],
                                    decoration: const InputDecoration(
                                      labelText: 'Sub order follow-up',
                                    ),
                                    validator: (v) =>
                                        !_sameForSubOrders &&
                                            (v ?? '').trim().isEmpty
                                        ? 'Follow-up is required'
                                        : null,
                                  ),
                                  if (_requiresNextFollowup)
                                    TextFormField(
                                      controller: _childNextDates[id],
                                      decoration: const InputDecoration(
                                        labelText:
                                            'Next follow-up (YYYY-MM-DD HH:MM)',
                                      ),
                                      validator: (v) =>
                                          FollowupStagePolicy.validateNextFollowup(
                                            v ?? '',
                                            required:
                                                !_sameForSubOrders &&
                                                _requiresNextFollowup,
                                          ),
                                    ),
                                ],
                              ),
                            ),
                          );
                        }),
                ],
                const SizedBox(height: AppSpacing.lg),
                if (!_requiresNextFollowup)
                  const Text(
                    'Ready is the final production stage. No next follow-up is needed.',
                    style: TextStyle(color: AppColors.textSecondary),
                  )
                else ...[
                  const AppSectionTitle('Next Followup'),
                  const SizedBox(height: AppSpacing.md),
                  FormField<String>(
                    key: ValueKey('next-followup-$_stage'),
                    validator: (_) => FollowupStagePolicy.validateNextFollowup(
                      _followupDateTimeString(),
                      required: _requiresNextFollowup,
                    ),
                    builder: (field) => Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        OutlinedButton.icon(
                          onPressed: _saving
                              ? null
                              : () async {
                                  final now = DateTime.now();
                                  final picked = await showDatePicker(
                                    context: context,
                                    firstDate: DateTime(
                                      now.year,
                                      now.month,
                                      now.day,
                                    ),
                                    lastDate: DateTime(now.year + 3),
                                    initialDate: _nextDate ?? now,
                                  );
                                  if (picked != null && mounted) {
                                    setState(() => _nextDate = picked);
                                    field.didChange(_followupDateTimeString());
                                    field.validate();
                                  }
                                },
                          icon: const Icon(Icons.event),
                          label: Text(
                            _nextDate == null
                                ? 'Select next followup date *'
                                : 'Date: ${AppFormatters.date(_nextDate)}',
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        OutlinedButton.icon(
                          onPressed: _saving || _nextDate == null
                              ? null
                              : () async {
                                  final picked = await showTimePicker(
                                    context: context,
                                    initialTime:
                                        _nextTime ??
                                        const TimeOfDay(hour: 9, minute: 0),
                                  );
                                  if (picked != null && mounted) {
                                    setState(() => _nextTime = picked);
                                    field.didChange(_followupDateTimeString());
                                    field.validate();
                                  }
                                },
                          icon: const Icon(Icons.access_time),
                          label: Text(
                            _nextTime == null
                                ? 'Followup time: 9:00 AM (tap to change)'
                                : 'Time: ${_nextTime!.format(context)}',
                          ),
                        ),
                        if (field.hasError)
                          Padding(
                            padding: const EdgeInsets.only(top: AppSpacing.sm),
                            child: Text(
                              field.errorText!,
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.error,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: AppSpacing.md),
                OutlinedButton.icon(
                  onPressed: _pickImage,
                  icon: Icon(
                    widget.allowGallery
                        ? Icons.add_photo_alternate_outlined
                        : Icons.camera_alt_outlined,
                  ),
                  label: Text(
                    _picked == null
                        ? (widget.allowGallery
                              ? 'Add evidence photo'
                              : 'Take evidence photo')
                        : (_pickedSource == ImageSource.gallery
                              ? 'Gallery photo selected'
                              : 'Camera photo captured'),
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  widget.allowGallery
                      ? 'Your employee settings allow camera or gallery evidence.'
                      : 'Camera-only evidence is enabled for your account.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                SafeArea(
                  top: false,
                  child: SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: _saving ? null : _submit,
                      icon: _saving
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.save),
                      label: Text(_saving ? 'Saving...' : 'Save Followup'),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _followupDateTimeString() {
    if (!_requiresNextFollowup || _nextDate == null) {
      return '';
    }

    final time = _nextTime ?? const TimeOfDay(hour: 9, minute: 0);
    return '${_nextDate!.year.toString().padLeft(4, '0')}-${_nextDate!.month.toString().padLeft(2, '0')}-${_nextDate!.day.toString().padLeft(2, '0')} ${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}:00';
  }
}
