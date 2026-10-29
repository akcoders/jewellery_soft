import 'package:flutter/material.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/premium_surface.dart';

class AppSectionTitle extends StatelessWidget {
  const AppSectionTitle(this.text, {super.key, this.trailing});
  final String text;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      const FacetedDiamond(size: 14, color: AppColors.brandGold),
      const SizedBox(width: 10),
      Expanded(
        child: Text(
          text,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
            letterSpacing: -.25,
          ),
        ),
      ),
      if (trailing != null) ...[const SizedBox(width: 8), trailing!],
    ],
  );
}
