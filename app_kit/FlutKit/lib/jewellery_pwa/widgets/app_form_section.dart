import 'package:flutkit/jewellery_mobile/widgets/premium_surface.dart';
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
    if (name.contains('photo') ||
        name.contains('reference') ||
        name.contains('attachment')) {
      return LucideIcons.image;
    }
    if (name.contains('date')) return LucideIcons.calendar_days;
    return LucideIcons.notebook_pen;
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: AppSpacing.xl),
    child: PremiumSurface(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 2),
      borderRadius: 26,
      accent: AppColors.ice,
      child: Material(
        type: MaterialType.transparency,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [AppColors.paleGold, AppColors.paleEmerald],
                    ),
                    border: Border.all(
                      color: AppColors.brandGold.withValues(alpha: .35),
                    ),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(
                    icon ?? _sectionIcon,
                    color: AppColors.brandRed,
                    size: 19,
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w700,
                        letterSpacing: -.3,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            if (subtitle != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                subtitle!,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
            ],
            const SizedBox(height: 20),
            Container(
              height: 1,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    AppColors.brandGold.withValues(alpha: .4),
                    AppColors.border.withValues(alpha: .25),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            for (final child in children)
              Padding(padding: const EdgeInsets.only(bottom: 18), child: child),
          ],
        ),
      ),
    ),
  );
}
