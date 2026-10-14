import 'dart:math' as math;

import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

/// Monthly target (200 points) with progress ring and reward points earned
/// for every point scored above the target.
class PerformanceTargetCard extends StatelessWidget {
  const PerformanceTargetCard({
    super.key,
    required this.score,
    required this.target,
    required this.rewardPoints,
    required this.rewardPerPoint,
  });

  final double score;
  final double target;
  final double rewardPoints;
  final double rewardPerPoint;

  @override
  Widget build(BuildContext context) {
    final safeTarget = target <= 0 ? 200.0 : target;
    final progress = (score / safeTarget).clamp(0.0, 1.0);
    final achieved = score >= safeTarget;
    final remaining = math.max(0, safeTarget - score);
    final accent = achieved ? AppColors.success : AppColors.brandGold;

    final ring = SizedBox(
      width: 132,
      height: 132,
      child: Stack(
        alignment: Alignment.center,
        children: [
          SizedBox.expand(
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: progress),
              duration: const Duration(milliseconds: 900),
              curve: Curves.easeOutCubic,
              builder: (context, value, _) => CircularProgressIndicator(
                value: value,
                strokeWidth: 11,
                strokeCap: StrokeCap.round,
                backgroundColor: AppColors.border,
                valueColor: AlwaysStoppedAnimation(accent),
              ),
            ),
          ),
          Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '${(progress * 100).round()}%',
                style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w800,
                  color: AppColors.plum,
                  letterSpacing: -0.5,
                ),
              ),
              Text(
                'of ${safeTarget.toStringAsFixed(0)}',
                style: const TextStyle(
                  fontSize: 11,
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ],
      ),
    );

    final details = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(
              achieved ? LucideIcons.trophy : LucideIcons.target,
              color: accent,
              size: 20,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                achieved
                    ? 'Monthly target reached'
                    : '${_fmt(remaining)} points to target',
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  color: AppColors.plum,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          'Target is ${safeTarget.toStringAsFixed(0)} points every month. '
          'Every point above ${safeTarget.toStringAsFixed(0)} earns '
          '${_fmt(rewardPerPoint)} reward point.',
          style: const TextStyle(
            fontSize: 12,
            height: 1.5,
            color: AppColors.textSecondary,
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            _pill(
              icon: LucideIcons.chart_no_axes_combined,
              label: 'Score ${_fmt(score)}',
              color: AppColors.plum,
            ),
            _pill(
              icon: LucideIcons.gift,
              label: '${_fmt(rewardPoints)} reward pts',
              color: rewardPoints > 0
                  ? AppColors.success
                  : AppColors.textSecondary,
              filled: rewardPoints > 0,
            ),
          ],
        ),
      ],
    );

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.xl),
        border: Border.all(
          color: achieved
              ? AppColors.success.withValues(alpha: 0.35)
              : AppColors.border,
        ),
        boxShadow: [
          BoxShadow(
            color: accent.withValues(alpha: 0.08),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          if (constraints.maxWidth < 440) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(child: ring),
                const SizedBox(height: 18),
                details,
              ],
            );
          }
          return Row(
            children: [
              ring,
              const SizedBox(width: 22),
              Expanded(child: details),
            ],
          );
        },
      ),
    );
  }

  Widget _pill({
    required IconData icon,
    required String label,
    required Color color,
    bool filled = false,
  }) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
    decoration: BoxDecoration(
      color: filled ? color : color.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(999),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: filled ? Colors.white : color),
        const SizedBox(width: 6),
        Flexible(
          child: Text(
            label,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: filled ? Colors.white : color,
            ),
          ),
        ),
      ],
    ),
  );

  static String _fmt(num value) => value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(1);
}
