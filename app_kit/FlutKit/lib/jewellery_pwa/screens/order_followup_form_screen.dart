import 'dart:convert';

import 'package:flutkit/jewellery_pwa/services/app_image_picker.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_photo_field.dart';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutkit/jewellery_mobile/services/followup_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_section_title.dart';

class OrderFollowupFormScreen extends StatefulWidget {
  const OrderFollowupFormScreen({
    super.key,
    required this.api,
    required this.orderId,
    required this.stages,
    this.allowGallery = false,
  });

  final MobileApiService api;
  final int orderId;
  final List<String> stages;
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
  bool _saving = false;
  bool _pickingPhoto = false;
  String _imageSource = 'camera';

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
    if (_pickingPhoto || _saving) return;
    setState(() => _pickingPhoto = true);
    try {
      final image = await AppImagePicker().pickImage(
        context,
        title: 'Followup photo',
        allowGallery: widget.allowGallery,
        onSourceSelected: (source) => _imageSource = source.name,
      );
      if (mounted && image != null) setState(() => _picked = image);
    } finally {
      if (mounted) setState(() => _pickingPhoto = false);
    }
  }

  Future<void> _submit() async {
    final valid = _formKey.currentState?.validate() ?? false;
    if (!valid || _saving || _pickingPhoto || _stage == null) return;

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
        imageSource: _imageSource,
      );
      if (!mounted) return;
      if (response['approval_required'] == true) {
        await showDialog<void>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(Icons.hourglass_top),
            title: const Text('Approval requested'),
            content: Text(
              (response['submission_message'] ??
                      'Follow-up sent for admin approval. It remains pending until approved.')
                  .toString(),
            ),
            actions: [
              FilledButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('OK'),
              ),
            ],
          ),
        );
      }
      if (!mounted) return;
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
          child: ListView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
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
              AppPhotoField(
                file: _picked,
                title: 'Add a followup photo',
                subtitle: widget.allowGallery
                    ? 'Optional · camera or gallery'
                    : 'Optional · camera only (admin controlled)',
                busy: _pickingPhoto,
                enabled: !_saving,
                onPick: _pickImage,
                onRemove: () => setState(() => _picked = null),
              ),
              const SizedBox(height: AppSpacing.xl),
              SafeArea(
                top: false,
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: _saving || _pickingPhoto ? null : _submit,
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
