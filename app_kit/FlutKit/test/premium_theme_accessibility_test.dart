import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutkit/jewellery_mobile/widgets/premium_surface.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

double contrast(Color a, Color b) {
  final values = [a.computeLuminance(), b.computeLuminance()]..sort();
  return (values.last + 0.05) / (values.first + 0.05);
}

void main() {
  test('soft surfaces keep body copy and primary actions readable', () {
    for (final surface in [
      AppColors.background,
      AppColors.card,
      AppColors.paleGold,
      AppColors.paleEmerald,
      for (final accent in [
        AppColors.ice,
        AppColors.lavender,
        AppColors.brandGold,
        AppColors.diamond,
      ])
        Color.alphaBlend(accent.withValues(alpha: 0.28), Colors.white),
    ]) {
      for (final ink in [AppColors.textPrimary, AppColors.textSecondary]) {
        expect(contrast(ink, surface), greaterThanOrEqualTo(4.5));
      }
    }
    expect(contrast(Colors.white, AppColors.taupe), greaterThanOrEqualTo(4.5));
  });

  testWidgets('reduced motion avoids decorative entrance frames', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(disableAnimations: true),
          child: PremiumBackdrop(child: child!),
        ),
        home: const Scaffold(
          body: PremiumEntrance(
            child: PremiumSurface(child: Text('Order ready')),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Order ready'), findsOneWidget);
    expect(tester.binding.transientCallbackCount, 0);
    expect(tester.takeException(), isNull);
  });
}
