import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

/// Intrinsic-height cards keep workspace summaries readable at large text sizes.
class WorkspaceCardLayout extends StatelessWidget {
  const WorkspaceCardLayout({
    super.key,
    required this.children,
    this.minimumWidth = 220,
    this.maxColumns = 4,
    this.spacing = 12,
  });

  final List<Widget> children;
  final double minimumWidth;
  final int maxColumns;
  final double spacing;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final columns =
          ((constraints.maxWidth + spacing) / (minimumWidth + spacing))
              .floor()
              .clamp(1, maxColumns);
      final width = (constraints.maxWidth - spacing * (columns - 1)) / columns;
      return Wrap(
        spacing: spacing,
        runSpacing: spacing,
        children: [
          for (final child in children) SizedBox(width: width, child: child),
        ],
      );
    },
  );
}

class WorkspaceMetricCard extends StatelessWidget {
  const WorkspaceMetricCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    this.caption,
    this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final String? caption;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.lg),
      side: const BorderSide(color: AppColors.border),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                WorkspaceIconTile(icon: icon, color: color),
                const Spacer(),
                if (onTap != null)
                  const Icon(
                    LucideIcons.arrow_up_right,
                    size: 17,
                    color: AppColors.textSecondary,
                  ),
              ],
            ),
            const SizedBox(height: 18),
            Text(
              value,
              style: const TextStyle(
                color: AppColors.plum,
                fontSize: 29,
                fontWeight: FontWeight.w700,
                letterSpacing: -1,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppColors.textSecondary,
              ),
            ),
            if (caption != null) ...[
              const SizedBox(height: 8),
              Text(
                caption!,
                style: const TextStyle(
                  fontSize: 11,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ],
        ),
      ),
    ),
  );
}

class WorkspaceIconTile extends StatelessWidget {
  const WorkspaceIconTile({
    super.key,
    required this.icon,
    this.color = AppColors.plum,
    this.size = 40,
  });

  final IconData icon;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      color: color.withValues(alpha: .09),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Icon(icon, color: color, size: size * .48),
  );
}

class WorkspaceBadge extends StatelessWidget {
  const WorkspaceBadge(
    this.label, {
    super.key,
    this.color = AppColors.plum,
    this.icon,
  });

  final String label;
  final Color color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .08),
      borderRadius: BorderRadius.circular(30),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (icon != null) ...[
          Icon(icon, color: color, size: 13),
          const SizedBox(width: 5),
        ],
        Flexible(
          child: Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    ),
  );
}

class WorkspacePageHeading extends StatelessWidget {
  const WorkspacePageHeading({
    super.key,
    required this.title,
    required this.description,
    required this.icon,
    this.trailing,
  });

  final String title;
  final String description;
  final IconData icon;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      WorkspaceIconTile(icon: icon, size: 46),
      const SizedBox(width: 13),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w700,
                color: AppColors.plum,
                letterSpacing: -.6,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              description,
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
                height: 1.5,
              ),
            ),
          ],
        ),
      ),
      if (trailing != null) ...[const SizedBox(width: 8), trailing!],
    ],
  );
}

class WorkspaceMetadata extends StatelessWidget {
  const WorkspaceMetadata({
    super.key,
    required this.icon,
    required this.text,
    this.color = AppColors.textSecondary,
  });

  final IconData icon;
  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisSize: MainAxisSize.min,
    children: [
      Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Icon(icon, size: 14, color: color),
      ),
      const SizedBox(width: 7),
      Flexible(
        child: Text(
          text,
          style: TextStyle(fontSize: 12, color: color, height: 1.5),
        ),
      ),
    ],
  );
}
