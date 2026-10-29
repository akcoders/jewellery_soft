import 'package:flutter/material.dart';

/// A finite arrival animation. Accessibility settings always take precedence.
class PremiumEntrance extends StatelessWidget {
  const PremiumEntrance({super.key, required this.child, this.order = 0});

  final Widget child;
  final int order;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.disableAnimationsOf(context)) return child;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 420 + order.clamp(0, 5) * 45),
      curve: Curves.easeOutCubic,
      child: child,
      builder: (context, value, child) => Opacity(
        opacity: value,
        child: Transform.translate(
          offset: Offset(0, 10 * (1 - value)),
          child: child,
        ),
      ),
    );
  }
}

class PremiumPageTransitionsBuilder extends PageTransitionsBuilder {
  const PremiumPageTransitionsBuilder();

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    if (MediaQuery.disableAnimationsOf(context)) return child;
    final eased = animation.drive(CurveTween(curve: Curves.easeOutCubic));
    return FadeTransition(
      opacity: eased,
      child: SlideTransition(
        position: eased.drive(
          Tween(begin: const Offset(0, 0.025), end: Offset.zero),
        ),
        child: child,
      ),
    );
  }
}
