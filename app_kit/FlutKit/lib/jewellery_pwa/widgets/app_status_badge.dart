import 'package:flutter/material.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';

class AppStatusBadge extends StatelessWidget {
  const AppStatusBadge({super.key, required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final tone = color.computeLuminance() > .35
        ? HSLColor.fromColor(color).withLightness(.4).toColor()
        : color;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Color.alphaBlend(tone.withValues(alpha: .1), Colors.white),
            AppColors.background,
          ],
        ),
        borderRadius: BorderRadius.circular(100),
        border: Border.all(color: tone.withValues(alpha: .23)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Transform.rotate(
            angle: .7854,
            child: Container(
              width: 5,
              height: 5,
              decoration: BoxDecoration(
                color: tone,
                borderRadius: BorderRadius.circular(1),
              ),
            ),
          ),
          const SizedBox(width: 7),
          Flexible(
            child: Text(
              label.isEmpty ? 'Not set' : label,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: tone,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
