import 'dart:typed_data';

import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:image_picker/image_picker.dart';

class AppPhotoField extends StatelessWidget {
  const AppPhotoField({
    super.key,
    required this.onPick,
    this.file,
    this.onRemove,
    this.title = 'Add a photo',
    this.subtitle = 'Camera or gallery',
    this.busy = false,
    this.enabled = true,
  });

  final XFile? file;
  final VoidCallback onPick;
  final VoidCallback? onRemove;
  final String title;
  final String subtitle;
  final bool busy;
  final bool enabled;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (file != null) ...[
        AppPhotoPreview(
          file: file!,
          onRemove: enabled && !busy ? onRemove : null,
        ),
        const SizedBox(height: 12),
      ],
      AppPhotoAddButton(
        onPressed: enabled && !busy ? onPick : null,
        title: file == null ? title : 'Replace photo',
        subtitle: subtitle,
        busy: busy,
      ),
    ],
  );
}

class AppPhotoPreview extends StatefulWidget {
  const AppPhotoPreview({super.key, required this.file, this.onRemove});

  final XFile file;
  final VoidCallback? onRemove;

  @override
  State<AppPhotoPreview> createState() => _AppPhotoPreviewState();
}

class _AppPhotoPreviewState extends State<AppPhotoPreview> {
  late Future<Uint8List> _bytes;

  @override
  void initState() {
    super.initState();
    _bytes = widget.file.readAsBytes();
  }

  @override
  void didUpdateWidget(AppPhotoPreview oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.file != widget.file) _bytes = widget.file.readAsBytes();
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Uint8List>(
    future: _bytes,
    builder: (context, snapshot) => AppAttachmentTile(
      name: widget.file.name,
      imageBytes: snapshot.data,
      onRemove: widget.onRemove,
    ),
  );
}

class AppPhotoAddButton extends StatelessWidget {
  const AppPhotoAddButton({
    super.key,
    required this.onPressed,
    this.title = 'Add photos',
    this.subtitle = 'Open camera or choose from gallery',
    this.busy = false,
  });

  final VoidCallback? onPressed;
  final String title;
  final String subtitle;
  final bool busy;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.paleGold.withValues(alpha: 0.45),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(18),
      side: BorderSide(color: AppColors.brandGold.withValues(alpha: 0.25)),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onPressed,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Row(
          children: [
            SizedBox(
              width: 28,
              height: 28,
              child: busy
                  ? const AppLoadingIndicator(size: 26)
                  : const Icon(LucideIcons.image_plus, color: AppColors.plum),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    busy ? 'Opening photos…' : title,
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(LucideIcons.plus, size: 18, color: AppColors.plum),
          ],
        ),
      ),
    ),
  );
}

class AppAttachmentTile extends StatelessWidget {
  const AppAttachmentTile({
    super.key,
    required this.name,
    this.imageBytes,
    this.onRemove,
  });

  final String name;
  final Uint8List? imageBytes;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: AppColors.border),
    ),
    child: Row(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: Container(
            width: 54,
            height: 54,
            color: AppColors.background,
            child: imageBytes == null
                ? const Icon(LucideIcons.file, color: AppColors.brandGold)
                : Image.memory(
                    imageBytes!,
                    fit: BoxFit.cover,
                    cacheWidth: 160,
                    errorBuilder: (_, _, _) => const Icon(
                      LucideIcons.image,
                      color: AppColors.brandGold,
                    ),
                  ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(name, maxLines: 2, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 3),
              Text(
                'Ready to attach',
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
        if (onRemove != null)
          IconButton(
            tooltip: 'Remove $name',
            onPressed: onRemove,
            icon: const Icon(LucideIcons.x, size: 18),
          ),
      ],
    ),
  );
}
