import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_mobile/widgets/app_state_widgets.dart';
import 'package:flutter/material.dart';

class AdminTasksScreen extends StatefulWidget {
  const AdminTasksScreen({super.key, required this.api});
  final MobileApiService api;

  @override
  State<AdminTasksScreen> createState() => _AdminTasksScreenState();
}

class _AdminTasksScreenState extends State<AdminTasksScreen> {
  List<dynamic> _tasks = [];
  List<dynamic> _staff = [];
  bool _loading = true;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await widget.api.fetchAdminTasks();
      if (!mounted) return;
      setState(() {
        _tasks = (data['tasks'] as List?) ?? [];
        _staff = (data['staff'] as List?) ?? [];
        _loading = false;
        _error = '';
      });
    } catch (e) {
      if (mounted)
        setState(() {
          _loading = false;
          _error = e.toString().replaceFirst('Exception: ', '');
        });
    }
  }

  Future<void> _assign() async {
    final title = TextEditingController();
    final note = TextEditingController();
    int? staffId;
    String priority = 'normal';
    DateTime dueAt = DateTime.now().add(const Duration(days: 1));
    bool saving = false;
    String error = '';
    final staffRows = _staff
        .whereType<Map>()
        .map((row) => row.cast<String, dynamic>())
        .toList();
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const Text('Assign staff task'),
          content: SizedBox(
            width: 440,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  DropdownButtonFormField<int>(
                    initialValue: staffId,
                    decoration: const InputDecoration(
                      labelText: 'Staff member *',
                    ),
                    items: staffRows
                        .map(
                          (row) => DropdownMenuItem<int>(
                            value: int.tryParse('${row['id']}'),
                            child: Text('${row['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: saving
                        ? null
                        : (value) => update(() => staffId = value),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  TextField(
                    controller: title,
                    maxLength: 160,
                    decoration: const InputDecoration(
                      labelText: 'Task title *',
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  TextField(
                    controller: note,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Instructions',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  DropdownButtonFormField<String>(
                    initialValue: priority,
                    decoration: const InputDecoration(labelText: 'Priority'),
                    items: const [
                      DropdownMenuItem(value: 'low', child: Text('Low')),
                      DropdownMenuItem(value: 'normal', child: Text('Normal')),
                      DropdownMenuItem(value: 'high', child: Text('High')),
                      DropdownMenuItem(value: 'urgent', child: Text('Urgent')),
                    ],
                    onChanged: saving
                        ? null
                        : (value) => update(() => priority = value ?? priority),
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
                    label: Text('Due ${AppFormatters.dateTime(dueAt)}'),
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
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      if (staffId == null ||
                          title.text.trim().isEmpty ||
                          !dueAt.isAfter(DateTime.now())) {
                        update(
                          () => error =
                              'Select staff, title and a future due time.',
                        );
                        return;
                      }
                      update(() {
                        saving = true;
                        error = '';
                      });
                      try {
                        await widget.api.createAdminTask({
                          'admin_user_id': staffId,
                          'title': title.text.trim(),
                          'note': note.text.trim(),
                          'priority': priority,
                          'scheduled_at': _dateTime(dueAt),
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
              child: const Text('Assign task'),
            ),
          ],
        ),
      ),
    );
    title.dispose();
    note.dispose();
  }

  String _dateTime(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')} '
      '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}:00';

  Future<void> _cancel(int id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel task?'),
        content: const Text('The staff member will no longer have this task.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Cancel task'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.api.cancelAdminTask(id);
      await _load();
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error.isNotEmpty)
      return AppErrorState(message: _error, onRetry: _load);
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          FilledButton.icon(
            onPressed: _assign,
            icon: const Icon(Icons.add_task),
            label: const Text('Assign a task'),
          ),
          const SizedBox(height: AppSpacing.xl),
          if (_tasks.isEmpty)
            const AppEmptyState(
              title: 'No staff tasks',
              message: 'Assign a task to begin.',
            ),
          ..._tasks.whereType<Map>().map((row) {
            final id = int.tryParse('${row['id']}') ?? 0;
            final status = '${row['status']}';
            final manual =
                row['reference_type'] == null ||
                '${row['reference_type']}'.isEmpty;
            return Card(
              margin: const EdgeInsets.only(bottom: AppSpacing.md),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${row['title']}',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      '${row['assignee_name'] ?? 'Staff'} · $status · Due ${AppFormatters.dateTime(row['scheduled_at'])}',
                    ),
                    if (row['note'] != null && '${row['note']}'.isNotEmpty) ...[
                      const SizedBox(height: AppSpacing.sm),
                      Text('${row['note']}'),
                    ],
                    if (manual && status == 'pending' && id > 0) ...[
                      const SizedBox(height: AppSpacing.md),
                      OutlinedButton.icon(
                        onPressed: () => _cancel(id),
                        icon: const Icon(Icons.cancel_outlined),
                        label: const Text('Cancel task'),
                      ),
                    ],
                  ],
                ),
              ),
            );
          }),
        ],
      ),
    );
  }
}
