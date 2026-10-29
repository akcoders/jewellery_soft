import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

export 'premium_motion.dart' show PremiumEntrance;

/// Static diamond paper, shared by the native workspace and browser app.
class PremiumBackdrop extends StatelessWidget {
  const PremiumBackdrop({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFFFDFEFF), AppColors.background, Color(0xFFF0EBFC)],
      ),
    ),
    child: CustomPaint(
      painter: const _PearlGrain(),
      child: Theme(
        data: Theme.of(
          context,
        ).copyWith(scaffoldBackgroundColor: Colors.transparent),
        child: child,
      ),
    ),
  );
}

class PremiumSurface extends StatelessWidget {
  const PremiumSurface({
    super.key,
    required this.child,
    this.padding = EdgeInsets.zero,
    this.borderRadius = 24,
    this.accent,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final double borderRadius;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final tone = accent ?? AppColors.ice;
    final radius = BorderRadius.circular(borderRadius);
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: radius,
        boxShadow: AppShadows.soft,
      ),
      child: ClipRRect(
        borderRadius: radius,
        child: Material(
          color: Colors.transparent,
          child: Ink(
            decoration: BoxDecoration(
              borderRadius: radius,
              border: Border.all(color: AppColors.border),
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Colors.white,
                  Color.alphaBlend(tone.withValues(alpha: 0.18), Colors.white),
                  Color.alphaBlend(tone.withValues(alpha: 0.28), Colors.white),
                ],
              ),
            ),
            child: Padding(padding: padding, child: child),
          ),
        ),
      ),
    );
  }
}

/// Fine cut lines keep the mark crisp at any display density.
class FacetedDiamond extends StatelessWidget {
  const FacetedDiamond({super.key, this.size = 44, this.color});
  final double size;
  final Color? color;

  @override
  Widget build(BuildContext context) => ExcludeSemantics(
    child: SizedBox.square(
      dimension: size,
      child: CustomPaint(
        painter: _DiamondPainter(color ?? AppColors.brandGold),
      ),
    ),
  );
}

class _DiamondPainter extends CustomPainter {
  const _DiamondPainter(this.color);
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.translate(size.width * 0.08, size.height * 0.12);
    canvas.scale(size.width * 0.84, size.height * 0.76);
    final silhouette = Path()
      ..moveTo(0.22, 0.02)
      ..lineTo(0.78, 0.02)
      ..lineTo(1, 0.30)
      ..lineTo(0.5, 1)
      ..lineTo(0, 0.30)
      ..close();
    canvas.drawPath(silhouette, Paint()..color = color.withValues(alpha: 0.07));
    final lines = Path()
      ..addPath(silhouette, Offset.zero)
      ..moveTo(0, 0.30)
      ..lineTo(1, 0.30)
      ..moveTo(0.22, 0.02)
      ..lineTo(0.35, 0.30)
      ..lineTo(0.5, 1)
      ..moveTo(0.78, 0.02)
      ..lineTo(0.65, 0.30)
      ..lineTo(0.5, 1)
      ..moveTo(0.35, 0.30)
      ..lineTo(0.5, 0.02)
      ..lineTo(0.65, 0.30);
    canvas.drawPath(
      lines,
      Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.25 / size.width
        ..strokeJoin = StrokeJoin.round,
    );
    canvas.restore();
  }

  @override
  bool shouldRepaint(_DiamondPainter oldDelegate) => color != oldDelegate.color;
}

class _PearlGrain extends CustomPainter {
  const _PearlGrain();

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = AppColors.rose.withValues(alpha: 0.06);
    // Deterministic, static micrograin: no image, blur, timer or random repaint.
    for (double y = 3; y < size.height; y += 9) {
      for (double x = 3 + (y.toInt() % 7); x < size.width; x += 11) {
        canvas.drawCircle(Offset(x, y), 0.45, paint);
      }
    }
  }

  @override
  bool shouldRepaint(_PearlGrain oldDelegate) => false;
}
