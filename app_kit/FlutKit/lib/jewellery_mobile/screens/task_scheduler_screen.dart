import 'dart:convert';

import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/services/task_repository.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

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
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Attach completion proof',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              const Text(
                'A proof image is compulsory before this task can be completed.',
              ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, ImageSource.camera),
                icon: const Icon(Icons.camera_alt_outlined),
                label: const Text('Take Photo'),
              ),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, ImageSource.gallery),
                icon: const Icon(Icons.photo_library_outlined),
                label: const Text('Choose From Gallery'),
              ),
            ],
          ),
        ),
      ),
    );
    if (source == null) return;
    final image = await ImagePicker().pickImage(
      source: source,
      imageQuality: 82,
      maxWidth: 1800,
    );
    if (image == null || !mounted) return;
    final imageBytes = await image.readAsBytes();
    if (!mounted) return;

    final noteController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Complete task?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.memory(
                imageBytes,
                height: 150,
                width: double.infinity,
                fit: BoxFit.cover,
              ),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: noteController,
              minLines: 2,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Completion note (optional)',
                alignLabelWithHint: true,
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Submit Proof'),
          ),
        ],
      ),
    );
    if (confirmed != true) {
      noteController.dispose();
      return;
    }

    setState(() => _submitting = true);
    try {
      final encoded = base64Encode(imageBytes);
      await _repo.complete(
        id: task.id,
        proofBase64: encoded,
        proofNote: noteController.text.trim(),
      );
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
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
    } finally {
      noteController.dispose();
      if (mounted) setState(() => _submitting = false);
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
                  'Complete each task with proof. On time +2 · Late −2',
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
        ? (task.status == 'completed_on_time'
              ? 'Completed on time · +2'
              : 'Completed late · −2')
        : (task.isOverdue ? 'Overdue · −2' : 'Pending');
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
                  onPressed: _submitting ? null : () => _completeTask(task),
                  icon: const Icon(Icons.verified_outlined),
                  label: const Text('Complete With Proof'),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
