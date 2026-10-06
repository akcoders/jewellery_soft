import 'dart:convert';
import 'dart:typed_data';

import 'package:flutkit/jewellery_pwa/screens/diamond_requirements_screen.dart';
import 'package:flutkit/jewellery_mobile/screens/diamond_bag_create_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/order_detail_screen.dart';
import 'package:flutkit/jewellery_pwa/screens/issuement_create_screen.dart';
import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/services/task_repository.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutkit/jewellery_pwa/services/app_image_picker.dart';

class TaskSchedulerScreen extends StatefulWidget {
  const TaskSchedulerScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<TaskSchedulerScreen> createState() => _TaskSchedulerScreenState();
}

class _TaskSchedulerScreenState extends State<TaskSchedulerScreen> {
  late final TaskRepository _repo;
  List<TaskItem> _tasks = [];
  bool _loading = true;
  bool _submitting = false;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _repo = TaskRepository(api: widget.api);
    TaskRefreshBus.tick.addListener(_load);
    _load();
  }

  @override
  void dispose() {
    TaskRefreshBus.tick.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = '';
    });
    try {
      final tasks = await _repo.load();
      tasks.sort((a, b) {
        if (a.isDone != b.isDone) return a.isDone ? 1 : -1;
        return a.scheduledAt.compareTo(b.scheduledAt);
      });
      if (mounted) setState(() => _tasks = tasks);
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _completeTask(TaskItem task) async {
    if (_submitting) return;
    setState(() => _submitting = true);
    try {
      final image = await AppImagePicker().pickImage(
        context,
        title: 'Task completion proof',
        imageQuality: 82,
      );
      if (image == null || !mounted) return;
      final imageBytes = await image.readAsBytes();
      if (!mounted) return;

      final note = await showDialog<String>(
        context: context,
        builder: (context) => _TaskProofDialog(imageBytes: imageBytes),
      );
      if (note == null || !mounted) return;
      await _repo.complete(
        id: task.id,
        proofBase64: base64Encode(imageBytes),
        proofNote: note,
      );
      if (!mounted) return;
      await _load();
      TaskRefreshBus.notify();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              task.isOverdue
                  ? 'Task completed. Late score has been recorded.'
                  : 'Task completed on time. +2 points.',
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _openWorkflowTask(TaskItem task) async {
    final changed = await Navigator.of(context).push<Object?>(
      MaterialPageRoute(
        builder: (_) => task.isGoldRequestTask
            ? IssuementCreateScreen(
                api: widget.api,
                workRequestId: task.referenceId,
                initialKarigarId: task.requestKarigarId,
              )
            : task.isDiamondBagRequestTask
            ? DiamondBagCreateScreen(
                api: widget.api,
                orderId: task.orderId,
                workRequestId: task.referenceId,
              )
            : task.isOrderFollowupTask
            ? OrderDetailScreen(api: widget.api, orderId: task.orderId)
            : DiamondRequirementDetailScreen(
                api: widget.api,
                requirementId: task.referenceId,
              ),
      ),
    );
    if (changed == true ||
        task.isOrderFollowupTask ||
        task.isGoldRequestTask ||
        task.isDiamondBagRequestTask) {
      await _load();
      TaskRefreshBus.notify();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading)
      return const FullScreenLoader(message: 'Loading assigned tasks...');
    if (_error.isNotEmpty)
      return AppErrorState(message: _error, onRetry: _load);
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          Container(
            padding: const EdgeInsets.all(AppSpacing.lg),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF43143F), Color(0xFFB81D24)],
              ),
              borderRadius: BorderRadius.circular(AppRadius.lg),
            ),
            child: const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'My Assigned Tasks',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 21,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 5),
                Text(
                  'Points update when the assigned work is completed. On time +2 · Late −2',
                  style: TextStyle(color: Colors.white70),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          if (_tasks.isEmpty)
            const AppEmptyState(
              title: 'No assigned tasks',
              message: 'Tasks assigned by admin will appear here.',
            ),
          ..._tasks.map(_taskCard),
        ],
      ),
    );
  }

  Widget _taskCard(TaskItem task) {
    final completed = task.isDone;
    final color = completed
        ? AppColors.success
        : (task.isOverdue ? AppColors.danger : AppColors.brandGold);
    final status = completed
        ? task.scoreDelta == 0
              ? 'Completed · 0'
              : task.scoreDelta > 0
              ? 'Completed on time · +${task.scoreDelta.toStringAsFixed(0)}'
              : 'Completed late · ${task.scoreDelta.toStringAsFixed(0)}'
        : (task.isOverdue ? 'Overdue' : 'Pending');
    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(
          color: task.isOverdue
              ? AppColors.danger.withValues(alpha: .35)
              : AppColors.border,
        ),
        boxShadow: AppShadows.soft,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 9,
                height: 44,
                decoration: BoxDecoration(
                  color: color,
                  borderRadius: BorderRadius.circular(20),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      task.title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    Text(
                      'Assigned by ${task.assignedByName}',
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: .12),
                  borderRadius: BorderRadius.circular(99),
                ),
                child: Text(
                  task.priority.toUpperCase(),
                  style: TextStyle(
                    color: color,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          if (task.note.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                task.note,
                style: const TextStyle(color: AppColors.textSecondary),
              ),
            ),
          const Divider(height: 25),
          Row(
            children: [
              Icon(Icons.schedule, size: 17, color: color),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  'Due ${AppFormatters.dateTime(task.scheduledAt)}',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
              Text(
                status,
                style: TextStyle(
                  color: color,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          if (!completed)
            Padding(
              padding: const EdgeInsets.only(top: 14),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _submitting
                      ? null
                      : () =>
                            (task.isDiamondBagTask ||
                                task.isOrderFollowupTask ||
                                task.isGoldRequestTask)
                            ? _openWorkflowTask(task)
                            : _completeTask(task),
                  icon: Icon(
                    (task.isDiamondBagTask ||
                            task.isOrderFollowupTask ||
                            task.isGoldRequestTask)
                        ? Icons.inventory_2_outlined
                        : Icons.verified_outlined,
                  ),
                  label: Text(
                    task.isDiamondBagTask
                        ? 'Open Bag Creation Request'
                        : task.isOrderFollowupTask
                        ? 'Open order follow-up'
                        : task.isGoldRequestTask
                        ? 'Create gold issuement'
                        : 'Complete With Proof',
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _TaskProofDialog extends StatefulWidget {
  const _TaskProofDialog({required this.imageBytes});

  final Uint8List imageBytes;

  @override
  State<_TaskProofDialog> createState() => _TaskProofDialogState();
}

class _TaskProofDialogState extends State<_TaskProofDialog> {
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Complete task?'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Image.memory(
              widget.imageBytes,
              height: 160,
              width: double.infinity,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => const Padding(
                padding: EdgeInsets.all(24),
                child: Text('Photo selected. Preview is unavailable.'),
              ),
            ),
          ),
          const SizedBox(height: 20),
          TextField(
            controller: _note,
            minLines: 2,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Completion note (optional)',
              alignLabelWithHint: true,
            ),
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: () => Navigator.pop(context, _note.text.trim()),
        child: const Text('Submit Proof'),
      ),
    ],
  );
}
