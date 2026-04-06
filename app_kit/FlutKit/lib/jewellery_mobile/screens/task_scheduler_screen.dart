import 'package:flutkit/jewellery_mobile/services/task_refresh_bus.dart';
import 'package:flutkit/jewellery_mobile/services/task_repository.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_section_title.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_mobile/screens/task_form_screen.dart';
import 'package:flutter/material.dart';

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
      tasks.sort((a, b) => a.scheduledAt.compareTo(b.scheduledAt));
      if (!mounted) return;
      setState(() => _tasks = tasks);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _addTask() async {
    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(builder: (_) => const TaskFormScreen()),
    );
    if (result == null) return;

    try {
      final created = await _repo.create(
        title: (result['title'] ?? '').toString(),
        note: (result['note'] ?? '').toString(),
        scheduledAt: result['scheduledAt'] as DateTime,
      );
      if (created != null) {
        setState(() {
          _tasks = [..._tasks, created]..sort((a, b) => a.scheduledAt.compareTo(b.scheduledAt));
        });
      } else {
        await _load();
      }
      TaskRefreshBus.notify();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Task saved.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  Future<void> _deleteTask(TaskItem task) async {
    try {
      await _repo.delete(task.id);
      await _load();
      TaskRefreshBus.notify();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Task removed.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _loading
          ? const FullScreenLoader(message: 'Loading tasks...')
          : _error.isNotEmpty
              ? AppErrorState(message: _error, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _tasks.isEmpty
                      ? ListView(
                          padding: const EdgeInsets.all(AppSpacing.lg),
                          children: const [
                            AppEmptyState(
                              title: 'No tasks yet',
                              message: 'Create a task to schedule reminders.',
                            ),
                          ],
                        )
                      : ListView(
                          padding: const EdgeInsets.all(AppSpacing.lg),
                          children: [
                            const AppSectionTitle('Scheduled Tasks'),
                            const SizedBox(height: AppSpacing.md),
                            ..._tasks.map((task) {
                              return Container(
                                margin: const EdgeInsets.only(bottom: AppSpacing.md),
                                decoration: BoxDecoration(
                                  color: AppColors.card,
                                  borderRadius: BorderRadius.circular(AppRadius.lg),
                                  border: Border.all(color: AppColors.border),
                                  boxShadow: AppShadows.soft,
                                ),
                                child: ListTile(
                                  leading: const Icon(Icons.alarm, color: AppColors.brandRed),
                                  title: Text(
                                    task.title,
                                    style: const TextStyle(fontWeight: FontWeight.w700),
                                  ),
                                  subtitle: Text(
                                    '${AppFormatters.dateTime(task.scheduledAt)}'
                                    '${task.note.isNotEmpty ? '\n${task.note}' : ''}',
                                  ),
                                  trailing: IconButton(
                                    icon: const Icon(Icons.delete_outline),
                                    onPressed: () => _deleteTask(task),
                                  ),
                                ),
                              );
                            }),
                          ],
                        ),
                ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addTask,
        icon: const Icon(Icons.add),
        label: const Text('Add Task'),
      ),
    );
  }
}
