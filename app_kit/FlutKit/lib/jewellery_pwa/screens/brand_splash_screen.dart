import 'dart:async';

import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter/material.dart';

class BrandSplashScreen extends StatefulWidget {
  const BrandSplashScreen({super.key, required this.onFinished});

  final VoidCallback onFinished;

  @override
  State<BrandSplashScreen> createState() => _BrandSplashScreenState();
}

class _BrandSplashScreenState extends State<BrandSplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _entrance = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 850),
  )..forward();
  Timer? _finishTimer;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _finishTimer ??= Timer(
      MediaQuery.disableAnimationsOf(context)
          ? const Duration(milliseconds: 250)
          : const Duration(milliseconds: 1500),
      () {
        if (mounted) widget.onFinished();
      },
    );
  }

  @override
  void dispose() {
    _finishTimer?.cancel();
    _entrance.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    final appear = CurvedAnimation(
      parent: _entrance,
      curve: Curves.easeOutCubic,
    );
    final content = Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 138,
          height: 138,
          padding: const EdgeInsets.all(17),
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: const Color(0xFFFFFBF4),
            border: Border.all(color: AppColors.brandGold, width: 1.5),
            boxShadow: [
              BoxShadow(
                color: AppColors.brandGold.withValues(alpha: 0.23),
                blurRadius: 42,
                spreadRadius: 5,
              ),
            ],
          ),
          child: Image.asset(
            'assets/images/brand/aabhushan_mark.png',
            fit: BoxFit.contain,
            semanticLabel: 'Aabhushan',
          ),
        ),
        const SizedBox(height: 28),
        const Text(
          'AABHUSHAN',
          style: TextStyle(
            fontFamily: 'CormorantGaramond',
            color: Color(0xFFFFFAF0),
            fontSize: 33,
            fontWeight: FontWeight.w600,
            letterSpacing: 4.2,
          ),
        ),
        const SizedBox(height: 9),
        const Text(
          'JEWELLERY WORKSPACE',
          style: TextStyle(
            color: AppColors.brandGold,
            fontSize: 10,
            fontWeight: FontWeight.w600,
            letterSpacing: 3,
          ),
        ),
        const SizedBox(height: 38),
        SizedBox(
          width: 92,
          child: reduceMotion
              ? Container(height: 2, color: AppColors.brandGold)
              : LinearProgressIndicator(
                  minHeight: 2,
                  backgroundColor: AppColors.brandGold.withValues(alpha: 0.22),
                  color: AppColors.brandGold,
                ),
        ),
      ],
    );

    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF351727), AppColors.plum, Color(0xFF6C2634)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: SafeArea(
          child: Stack(
            children: [
              Positioned(top: -190, right: -160, child: _Halo(diameter: 390)),
              Positioned(bottom: -230, left: -170, child: _Halo(diameter: 430)),
              Center(
                child: reduceMotion
                    ? content
                    : FadeTransition(
                        opacity: appear,
                        child: ScaleTransition(
                          scale: Tween<double>(
                            begin: 0.84,
                            end: 1,
                          ).animate(appear),
                          child: content,
                        ),
                      ),
              ),
              const Positioned(
                left: 0,
                right: 0,
                bottom: 30,
                child: Text(
                  'CRAFTED WITH PRECISION',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Color(0x99FFF9ED),
                    fontSize: 9,
                    letterSpacing: 2.1,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Halo extends StatelessWidget {
  const _Halo({required this.diameter});

  final double diameter;

  @override
  Widget build(BuildContext context) => Container(
    width: diameter,
    height: diameter,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      border: Border.all(color: AppColors.brandGold.withValues(alpha: 0.13)),
    ),
  );
}
