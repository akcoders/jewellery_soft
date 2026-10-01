import 'dart:math' as math;

import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter/material.dart';

/// A small jewellery inspired loading mark shared by full page and button states.
class AppLoadingIndicator extends StatefulWidget {
  const AppLoadingIndicator({super.key, this.size = 48, this.light = false});

  final double size;
  final bool light;

  @override
  State<AppLoadingIndicator> createState() => _AppLoadingIndicatorState();
}

class _AppLoadingIndicatorState extends State<AppLoadingIndicator>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1700),
  )..repeat();

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.stop();
    } else if (!_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    return Semantics(
      label: 'Loading',
      child: SizedBox.square(
        dimension: widget.size,
        child: AnimatedBuilder(
          animation: _controller,
          builder: (context, _) => CustomPaint(
            painter: _JewelleryLoaderPainter(
              progress: reduceMotion ? 0.25 : _controller.value,
              color: widget.light ? Colors.white : AppColors.brandRed,
              gold: widget.light ? AppColors.paleGold : AppColors.brandGold,
            ),
          ),
        ),
      ),
    );
  }
}

class _JewelleryLoaderPainter extends CustomPainter {
  const _JewelleryLoaderPainter({
    required this.progress,
    required this.color,
    required this.gold,
  });

  final double progress;
  final Color color;
  final Color gold;

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final radius = size.shortestSide * 0.43;
    final track = Paint()
      ..color = gold.withValues(alpha: 0.22)
      ..style = PaintingStyle.stroke
      ..strokeWidth = size.shortestSide * 0.055;
    final orbit = Paint()
      ..color = gold
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = size.shortestSide * 0.055;
    canvas.drawCircle(center, radius, track);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -math.pi / 2 + progress * math.pi * 2,
      math.pi * 0.7,
      false,
      orbit,
    );

    canvas.save();
    canvas.translate(center.dx, center.dy);
    canvas.rotate(math.sin(progress * math.pi * 2) * 0.08);
    final gem = Path()
      ..moveTo(0, -radius * 0.6)
      ..lineTo(radius * 0.52, -radius * 0.13)
      ..lineTo(0, radius * 0.68)
      ..lineTo(-radius * 0.52, -radius * 0.13)
      ..close();
    canvas.drawPath(gem, Paint()..color = color);
    final facet = Path()
      ..moveTo(-radius * 0.52, -radius * 0.13)
      ..lineTo(radius * 0.52, -radius * 0.13)
      ..moveTo(0, -radius * 0.6)
      ..lineTo(0, radius * 0.68);
    canvas.drawPath(
      facet,
      Paint()
        ..color = gold
        ..style = PaintingStyle.stroke
        ..strokeWidth = size.shortestSide * 0.025,
    );
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _JewelleryLoaderPainter oldDelegate) =>
      oldDelegate.progress != progress ||
      oldDelegate.color != color ||
      oldDelegate.gold != gold;
}

class FullScreenLoader extends StatelessWidget {
  const FullScreenLoader({
    super.key,
    this.message = 'Preparing your workspace',
  });

  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Container(
          constraints: const BoxConstraints(maxWidth: 290),
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 30),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(AppRadius.xl),
            border: Border.all(color: AppColors.border),
            boxShadow: AppShadows.soft,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const AppLoadingIndicator(size: 54),
              const SizedBox(height: AppSpacing.lg),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
