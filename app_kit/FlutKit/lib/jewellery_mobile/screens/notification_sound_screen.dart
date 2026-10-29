import 'package:flutkit/jewellery_mobile/services/local_notification_service.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/notification_sound_catalog.dart';
import 'package:flutkit/jewellery_mobile/services/notification_sound_preview_service.dart';
import 'package:flutter/material.dart';

class NotificationSoundScreen extends StatefulWidget {
  const NotificationSoundScreen({super.key, required this.api});

  final MobileApiService api;

  @override
  State<NotificationSoundScreen> createState() =>
      _NotificationSoundScreenState();
}

class _NotificationSoundScreenState extends State<NotificationSoundScreen> {
  String _selectedKey = NotificationSoundCatalog.defaultKey;
  String _savedKey = NotificationSoundCatalog.defaultKey;
  String? _previewingKey;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  static const List<IconData> _icons = [
    Icons.auto_awesome_rounded,
    Icons.diamond_outlined,
    Icons.workspace_premium_outlined,
    Icons.star_border_rounded,
    Icons.notifications_active_outlined,
    Icons.nights_stay_outlined,
    Icons.wb_sunny_outlined,
    Icons.priority_high_rounded,
    Icons.circle_outlined,
    Icons.timelapse_rounded,
  ];

  @override
  void initState() {
    super.initState();
    _selectedKey = LocalNotificationService.instance.selectedSound.key;
    _savedKey = _selectedKey;
    _load();
  }

  @override
  void dispose() {
    NotificationSoundPreviewService.stop();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await widget.api.fetchNotificationSoundSettings();
      final selected = NotificationSoundCatalog.resolve(
        data['selected']?.toString(),
      );
      await LocalNotificationService.instance.selectSound(selected.key);
      if (!mounted) return;
      setState(() {
        _selectedKey = selected.key;
        _savedKey = selected.key;
        _error = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = _message(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _preview(AppNotificationSound sound) async {
    setState(() => _previewingKey = sound.key);
    try {
      await NotificationSoundPreviewService.play(sound.resourceName);
      await Future<void>.delayed(const Duration(milliseconds: 1150));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Unable to preview sound: ${_message(error)}'),
          ),
        );
      }
    } finally {
      if (mounted && _previewingKey == sound.key) {
        setState(() => _previewingKey = null);
      }
    }
  }

  Future<void> _save() async {
    if (_saving || _selectedKey == _savedKey) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final data = await widget.api.updateNotificationSound(_selectedKey);
      final selected = NotificationSoundCatalog.resolve(
        data['selected']?.toString() ?? _selectedKey,
      );
      await LocalNotificationService.instance.selectSound(selected.key);
      if (!mounted) return;
      setState(() {
        _selectedKey = selected.key;
        _savedKey = selected.key;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${selected.label} is now your notification sound.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (error) {
      if (mounted) setState(() => _error = _message(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  String _message(Object error) {
    return error.toString().replaceFirst(RegExp(r'^Exception:\s*'), '');
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final selected = NotificationSoundCatalog.resolve(_selectedKey);

    return Scaffold(
      appBar: AppBar(title: const Text('Notification sound')),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                  children: [
                    Container(
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            theme.colorScheme.primaryContainer,
                            theme.colorScheme.surfaceContainerHighest,
                          ],
                        ),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 52,
                            height: 52,
                            decoration: BoxDecoration(
                              color: theme.colorScheme.primary,
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Icon(
                              Icons.graphic_eq_rounded,
                              color: theme.colorScheme.onPrimary,
                              size: 30,
                            ),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Make alerts yours',
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  'Choose one sound for push notifications and new device reminders.',
                                  style: theme.textTheme.bodySmall,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (_loading) const LinearProgressIndicator(),
                    if (_error != null) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: theme.colorScheme.errorContainer,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Row(
                          children: [
                            Icon(
                              Icons.cloud_off_outlined,
                              color: theme.colorScheme.onErrorContainer,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _error!,
                                style: TextStyle(
                                  color: theme.colorScheme.onErrorContainer,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    Text(
                      '10 bundled sounds',
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Tap the play button to preview before saving.',
                      style: theme.textTheme.bodySmall,
                    ),
                    const SizedBox(height: 10),
                    for (
                      var index = 0;
                      index < NotificationSoundCatalog.sounds.length;
                      index++
                    )
                      _SoundTile(
                        sound: NotificationSoundCatalog.sounds[index],
                        icon: _icons[index],
                        selected:
                            NotificationSoundCatalog.sounds[index].key ==
                            _selectedKey,
                        previewing:
                            NotificationSoundCatalog.sounds[index].key ==
                            _previewingKey,
                        onSelect: () => setState(
                          () => _selectedKey =
                              NotificationSoundCatalog.sounds[index].key,
                        ),
                        onPreview: () =>
                            _preview(NotificationSoundCatalog.sounds[index]),
                      ),
                    const SizedBox(height: 8),
                    Text(
                      'Android may keep any manual sound change made in system notification settings. '
                      'This selection controls the channel used for future alerts.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
              decoration: BoxDecoration(
                color: theme.colorScheme.surface,
                border: Border(
                  top: BorderSide(color: theme.colorScheme.outlineVariant),
                ),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text('Selected', style: theme.textTheme.labelSmall),
                        Text(
                          selected.label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  FilledButton.icon(
                    onPressed: _loading || _saving || _selectedKey == _savedKey
                        ? null
                        : _save,
                    icon: _saving
                        ? const SizedBox(
                            width: 17,
                            height: 17,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.check_rounded),
                    label: Text(_saving ? 'Saving' : 'Save sound'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SoundTile extends StatelessWidget {
  const _SoundTile({
    required this.sound,
    required this.icon,
    required this.selected,
    required this.previewing,
    required this.onSelect,
    required this.onPreview,
  });

  final AppNotificationSound sound;
  final IconData icon;
  final bool selected;
  final bool previewing;
  final VoidCallback onSelect;
  final VoidCallback onPreview;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: Material(
        color: selected
            ? theme.colorScheme.primaryContainer.withValues(alpha: 0.48)
            : theme.colorScheme.surfaceContainerLow,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(
            color: selected
                ? theme.colorScheme.primary
                : theme.colorScheme.outlineVariant,
            width: selected ? 1.5 : 1,
          ),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onSelect,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 11, 8, 11),
            child: Row(
              children: [
                AnimatedContainer(
                  duration: MediaQuery.disableAnimationsOf(context)
                      ? Duration.zero
                      : const Duration(milliseconds: 180),
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: selected
                        ? theme.colorScheme.primary
                        : theme.colorScheme.surfaceContainerHighest,
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(
                    icon,
                    color: selected
                        ? theme.colorScheme.onPrimary
                        : theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              sound.label,
                              style: theme.textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          if (sound.key ==
                              NotificationSoundCatalog.defaultKey) ...[
                            const SizedBox(width: 7),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 7,
                                vertical: 2,
                              ),
                              decoration: BoxDecoration(
                                color: theme.colorScheme.tertiaryContainer,
                                borderRadius: BorderRadius.circular(99),
                              ),
                              child: Text(
                                'Default',
                                style: theme.textTheme.labelSmall,
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        sound.description,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Preview ${sound.label}',
                  onPressed: onPreview,
                  icon: Icon(
                    previewing
                        ? Icons.graphic_eq_rounded
                        : Icons.play_circle_outline_rounded,
                    color: theme.colorScheme.primary,
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Icon(
                    selected
                        ? Icons.check_circle_rounded
                        : Icons.radio_button_unchecked_rounded,
                    color: selected
                        ? theme.colorScheme.primary
                        : theme.colorScheme.outline,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
