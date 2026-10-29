import 'dart:async';

import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/premium_surface.dart';
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
  );
  Timer? _finishTimer;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _entrance.value = 1;
    } else if (_finishTimer == null) {
      _entrance.forward();
    }
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
    return Scaffold(
      body: PremiumBackdrop(
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(28),
              child: AnimatedBuilder(
                animation: _entrance,
                builder: (context, child) {
                  final value = Curves.easeOutCubic.transform(_entrance.value);
                  return Opacity(
                    opacity: value,
                    child: Transform.translate(
                      offset: Offset(0, 14 * (1 - value)),
                      child: child,
                    ),
                  );
                },
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const FacetedDiamond(size: 48, color: AppColors.brandGold),
                    const SizedBox(height: 28),
                    Container(
                      width: 116,
                      height: 116,
                      padding: const EdgeInsets.all(15),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: AppColors.card,
                        border: Border.all(color: AppColors.brandGold),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x0D5364AD),
                            blurRadius: 36,
                            offset: Offset(0, 12),
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
                    const FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        'AABHUSHAN',
                        style: TextStyle(
                          fontFamily: 'CormorantGaramond',
                          color: AppColors.textPrimary,
                          fontSize: 35,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 3.2,
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'JEWELLERY WORKSPACE',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 9,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 2.4,
                      ),
                    ),
                    const SizedBox(height: 30),
                    SizedBox(
                      width: 88,
                      child: AnimatedBuilder(
                        animation: _entrance,
                        builder: (context, child) => LinearProgressIndicator(
                          value: _entrance.value,
                          minHeight: 2,
                          color: AppColors.brandGold,
                          backgroundColor: AppColors.paleGold,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    const SizedBox(height: 30),
                    const Text(
                      'CRAFTED WITH PRECISION',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 9,
                        letterSpacing: 1.8,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
