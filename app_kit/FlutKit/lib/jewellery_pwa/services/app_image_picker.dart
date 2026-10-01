import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:image_picker/image_picker.dart';

/// Keeps camera and gallery available wherever the app asks for a photo.
class AppImagePicker {
  AppImagePicker({ImagePicker? picker}) : _picker = picker ?? ImagePicker();

  final ImagePicker _picker;

  Future<XFile?> pickImage(
    BuildContext context, {
    String title = 'Add a photo',
    int imageQuality = 85,
    double maxWidth = 1800,
  }) async {
    final images = await pickImages(
      context,
      title: title,
      allowMultiple: false,
      imageQuality: imageQuality,
      maxWidth: maxWidth,
    );
    return images.firstOrNull;
  }

  Future<List<XFile>> pickImages(
    BuildContext context, {
    String title = 'Add photos',
    bool allowMultiple = true,
    int imageQuality = 85,
    double maxWidth = 1800,
  }) async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      useSafeArea: true,
      showDragHandle: false,
      isScrollControlled: true,
      backgroundColor: AppColors.background,
      constraints: const BoxConstraints(maxWidth: 560),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (sheetContext) =>
          _PhotoSourceSheet(title: title, allowMultiple: allowMultiple),
    );
    if (source == null || !context.mounted) return [];

    try {
      if (source == ImageSource.gallery && allowMultiple) {
        return await _picker.pickMultiImage(
          imageQuality: imageQuality,
          maxWidth: maxWidth,
          requestFullMetadata: false,
        );
      }
      final image = await _picker.pickImage(
        source: source,
        imageQuality: imageQuality,
        maxWidth: maxWidth,
        requestFullMetadata: false,
      );
      return image == null ? [] : [image];
    } catch (error) {
      if (context.mounted) {
        final denied =
            error is PlatformException &&
            (error.code.contains('denied') ||
                error.code.contains('restricted'));
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              denied
                  ? 'Photo access is blocked. Allow access in settings, or try the other photo option.'
                  : 'Could not open ${source == ImageSource.camera ? 'the camera' : 'your gallery'}. Please try again or choose the other option.',
            ),
          ),
        );
      }
      return [];
    }
  }
}

class _PhotoSourceSheet extends StatelessWidget {
  const _PhotoSourceSheet({required this.title, required this.allowMultiple});

  final String title;
  final bool allowMultiple;

  @override
  Widget build(BuildContext context) => SafeArea(
    top: false,
    child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              IconButton(
                tooltip: 'Close photo options',
                onPressed: () => Navigator.pop(context),
                icon: const Icon(LucideIcons.x, size: 20),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            'Capture a new photo or choose one you already have.',
            style: Theme.of(
              context,
            ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 24),
          _SourceOption(
            icon: LucideIcons.camera,
            title: 'Open camera',
            subtitle: 'Take a photo now',
            color: AppColors.plum,
            onTap: () => Navigator.pop(context, ImageSource.camera),
          ),
          const SizedBox(height: 12),
          _SourceOption(
            icon: LucideIcons.images,
            title: 'Choose from gallery',
            subtitle: allowMultiple
                ? 'Select one or more photos'
                : 'Select a photo from your device',
            color: AppColors.brandGold,
            onTap: () => Navigator.pop(context, ImageSource.gallery),
          ),
        ],
      ),
    ),
  );
}

class _SourceOption extends StatelessWidget {
  const _SourceOption({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(20),
      side: const BorderSide(color: AppColors.border),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(13),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Icon(icon, color: color, size: 25),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: Theme.of(context).textTheme.titleSmall),
                  const SizedBox(height: 4),
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
            const Icon(LucideIcons.chevron_right, size: 18),
          ],
        ),
      ),
    ),
  );
}
