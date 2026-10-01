import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class AppFormSection extends StatelessWidget {
  const AppFormSection({
    super.key,
    required this.title,
    required this.children,
    this.icon,
    this.subtitle,
  });

  final String title;
  final List<Widget> children;
  final IconData? icon;
  final String? subtitle;

  IconData get _sectionIcon {
    final name = title.toLowerCase();
    if (name.contains('customer') || name.contains('supplier')) {
      return LucideIcons.user_round;
    }
    if (name.contains('gold')) return LucideIcons.gem;
    if (name.contains('diamond')) return LucideIcons.diamond;
    if (name.contains('stone')) return LucideIcons.sparkles;
    if (name.contains('payment') || name.contains('amount')) {
      return LucideIcons.wallet;
    }
    if (name.contains('item') || name.contains('material')) {
      return LucideIcons.package;
    }
    if (name.contains('date')) return LucideIcons.calendar_days;
    return LucideIcons.notebook_pen;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.xl),
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 4),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(AppRadius.xl),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.soft,
      ),
      child: Material(
        type: MaterialType.transparency,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: AppColors.paleGold,
                    borderRadius: BorderRadius.circular(AppRadius.md),
                  ),
                  child: Icon(
                    icon ?? _sectionIcon,
                    color: AppColors.plum,
                    size: 20,
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.plum,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            if (subtitle != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(subtitle!, style: Theme.of(context).textTheme.bodySmall),
            ],
            const SizedBox(height: AppSpacing.lg),
            const Divider(height: 1),
            const SizedBox(height: AppSpacing.lg),
            for (final child in children)
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: child,
              ),
          ],
        ),
      ),
    );
  }
}
