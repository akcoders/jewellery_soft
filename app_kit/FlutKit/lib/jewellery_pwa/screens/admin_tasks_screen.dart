import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_state_widgets.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:flutkit/jewellery_pwa/widgets/app_status_badge.dart';

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
  String _filter = 'All';

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
                    isExpanded: true,
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
                    icon: const Icon(LucideIcons.calendar_days),
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
    if (_loading) return const FullScreenLoader();
    if (_error.isNotEmpty) {
      return AppErrorState(message: _error, onRetry: _load);
    }
    final rows = _tasks.whereType<Map>().where((row) {
      final status = '${row['status']}';
      return _filter == 'All' ||
          (_filter == 'Pending' && status == 'pending') ||
          (_filter == 'Completed' && status.startsWith('completed'));
    }).toList();
    final pending = _tasks
        .whereType<Map>()
        .where((row) => row['status'] == 'pending')
        .length;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Icon(
                    LucideIcons.clipboard_check,
                    color: AppColors.brandGold,
                    size: 28,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'A clear plan for your team',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '$pending pending · ${_tasks.length} total tasks',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: 18),
                  FilledButton.icon(
                    onPressed: _assign,
                    icon: const Icon(LucideIcons.plus, size: 19),
                    label: const Text('Assign a task'),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final filter in const ['All', 'Pending', 'Completed'])
                ChoiceChip(
                  label: Text(filter),
                  selected: _filter == filter,
                  onSelected: (_) => setState(() => _filter = filter),
                ),
            ],
          ),
          const SizedBox(height: 16),
          if (rows.isEmpty)
            const AppEmptyState(
              title: 'No tasks here',
              message: 'Assign a task or choose another filter.',
            ),
          ...rows.map(_taskCard),
        ],
      ),
    );
  }

  Widget _taskCard(Map row) {
    final id = int.tryParse('${row['id']}') ?? 0;
    final status = '${row['status']}';
    final manual =
        row['reference_type'] == null || '${row['reference_type']}'.isEmpty;
    final color = switch (status) {
      'completed_on_time' => AppColors.success,
      'completed_late' => AppColors.warning,
      'cancelled' => AppColors.textSecondary,
      _ => AppColors.plum,
    };
    final label = switch (status) {
      'completed_on_time' => 'Completed on time',
      'completed_late' => 'Completed late',
      'pending' => 'Pending',
      'cancelled' => 'Cancelled',
      _ => status.replaceAll('_', ' '),
    };
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                AppStatusBadge(label: label, color: color),
                if ('${row['priority'] ?? ''}' == 'high')
                  const AppStatusBadge(
                    label: 'High priority',
                    color: AppColors.danger,
                  ),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              '${row['title']}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 14),
            _taskMetadata(
              LucideIcons.user_round,
              '${row['assignee_name'] ?? 'Staff'}',
            ),
            const SizedBox(height: 10),
            _taskMetadata(
              LucideIcons.calendar_clock,
              'Due ${AppFormatters.dateTime(row['scheduled_at'])}',
            ),
            if (row['note'] != null && '${row['note']}'.isNotEmpty) ...[
              const Divider(height: 28),
              Text(
                '${row['note']}',
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  height: 1.6,
                ),
              ),
            ],
            if (!manual) ...[
              const SizedBox(height: 14),
              _taskMetadata(
                LucideIcons.link,
                'Linked to ${'${row['reference_type']}'.replaceAll('_', ' ')}',
              ),
            ],
            if (manual && status == 'pending' && id > 0) ...[
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => _cancel(id),
                icon: const Icon(LucideIcons.circle_x, size: 17),
                label: const Text('Cancel task'),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _taskMetadata(IconData icon, String text) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 16, color: AppColors.textSecondary),
      const SizedBox(width: 9),
      Expanded(child: Text(text, style: const TextStyle(fontSize: 13))),
    ],
  );
}
