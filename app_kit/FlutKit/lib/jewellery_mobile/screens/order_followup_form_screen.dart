import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutkit/jewellery_mobile/services/followup_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';

class OrderFollowupFormScreen extends StatefulWidget {
  const OrderFollowupFormScreen({
    super.key,
    required this.api,
    required this.orderId,
    required this.stages,
  });

  final MobileApiService api;
  final int orderId;
  final List<String> stages;

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
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _stage = widget.stages.isNotEmpty ? widget.stages.first : null;
  }

  @override
  void dispose() {
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final file = await picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (!mounted) return;
    setState(() => _picked = file);
  }

  Future<void> _submit() async {
    final valid = _formKey.currentState?.validate() ?? false;
    if (!valid || _saving || _stage == null) return;

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
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const AppSectionTitle('Followup Details'),
              const SizedBox(height: AppSpacing.md),
              DropdownButtonFormField<String>(
                initialValue: _stage,
                decoration: const InputDecoration(labelText: 'Stage'),
                items: widget.stages
                    .map(
                      (stage) =>
                          DropdownMenuItem(value: stage, child: Text(stage)),
                    )
                    .toList(),
                onChanged: (v) => setState(() => _stage = v),
                validator: (v) =>
                    (v == null || v.isEmpty) ? 'Stage is required' : null,
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
              const SizedBox(height: AppSpacing.lg),
              const AppSectionTitle('Next Followup'),
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                onPressed: () async {
                  final now = DateTime.now();
                  final picked = await showDatePicker(
                    context: context,
                    firstDate: DateTime(now.year - 1),
                    lastDate: DateTime(now.year + 3),
                    initialDate: _nextDate ?? now,
                  );
                  if (picked != null && mounted) {
                    setState(() => _nextDate = picked);
                  }
                },
                icon: const Icon(Icons.event),
                label: Text(
                  _nextDate == null
                      ? 'Select next followup date (optional)'
                      : 'Date: ${AppFormatters.date(_nextDate)}',
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                onPressed: _nextDate == null
                    ? null
                    : () async {
                        final picked = await showTimePicker(
                          context: context,
                          initialTime: _nextTime ?? TimeOfDay.now(),
                        );
                        if (picked != null && mounted) {
                          setState(() => _nextTime = picked);
                        }
                      },
                icon: const Icon(Icons.access_time),
                label: Text(
                  _nextTime == null
                      ? 'Select followup time'
                      : 'Time: ${_nextTime!.format(context)}',
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                onPressed: _pickImage,
                icon: const Icon(Icons.image),
                label: Text(
                  _picked == null
                      ? 'Attach image (optional)'
                      : 'Image selected',
                ),
              ),
              const Spacer(),
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
    );
  }

  String _followupDateTimeString() {
    if (_nextDate == null) {
      return '';
    }

    final time = _nextTime ?? const TimeOfDay(hour: 9, minute: 0);
    return '${_nextDate!.year.toString().padLeft(4, '0')}-${_nextDate!.month.toString().padLeft(2, '0')}-${_nextDate!.day.toString().padLeft(2, '0')} ${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}:00';
  }
}
