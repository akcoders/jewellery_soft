import 'package:flutter/material.dart';
import '../widgets/premium_motion.dart';

class AppColors {
  AppColors._();

  static const Color background = Color(0xFFF5F7FF);
  static const Color card = Color(0xFFFFFFFF);
  static const Color surface = card;
  static const Color textPrimary = Color(0xFF252D48);
  static const Color textSecondary = Color(0xFF515B76);
  static const Color border = Color(0xFFDDE3F3);

  static const Color pearl = background;
  static const Color sapphire = Color(0xFF5364AD);
  static const Color ice = Color(0xFFD5E6FC);
  static const Color lavender = Color(0xFFB3A0DF);
  static const Color champagne = Color(0xFFD5E6FC);
  static const Color rose = Color(0xFFB3A0DF);
  static const Color silver = Color(0xFF899AC1);
  static const Color taupe = Color(0xFF5364AD);
  // Compatibility tokens keep every workflow on the same luminous palette.
  static const Color emerald = taupe;
  static const Color brandRed = taupe;
  static const Color brandGold = Color(0xFF6274B6);
  static const Color plum = taupe;
  static const Color paleGold = Color(0xFFEAF1FD);
  static const Color paleEmerald = Color(0xFFEFECFA);

  static const Color diamond = Color(0xFF4F73B0);
  static const Color gold = Color(0xFF80612A);
  static const Color stone = Color(0xFF8564AC);
  static const Color success = Color(0xFF526FA6);
  static const Color warning = Color(0xFF96662B);
  static const Color danger = Color(0xFFAC4C6D);
}

class AppSpacing {
  AppSpacing._();

  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 24;
  static const double xxl = 32;
}

class AppRadius {
  AppRadius._();

  static const double sm = 8;
  static const double md = 12;
  static const double lg = 18;
  static const double xl = 24;
}

class AppShadows {
  AppShadows._();

  static const List<BoxShadow> soft = [
    BoxShadow(color: Color(0x0C354D98), blurRadius: 24, offset: Offset(0, 7)),
  ];
}

class AppTheme {
  AppTheme._();

  static ThemeData light() {
    final colorScheme =
        ColorScheme.fromSeed(
          seedColor: AppColors.brandRed,
          primary: AppColors.brandRed,
          secondary: AppColors.brandGold,
          surface: AppColors.card,
        ).copyWith(
          outline: AppColors.border,
          outlineVariant: AppColors.border,
          onSurface: AppColors.textPrimary,
          onSurfaceVariant: AppColors.textSecondary,
          primaryContainer: AppColors.paleEmerald,
          onPrimaryContainer: AppColors.emerald,
          error: AppColors.danger,
          onPrimary: Colors.white,
          onSecondary: Colors.white,
          surfaceContainerLowest: Colors.white,
          surfaceContainerLow: AppColors.background,
          surfaceContainer: AppColors.paleGold,
          surfaceTint: Colors.transparent,
        );

    final baseTextTheme = ThemeData.light().textTheme.apply(
      fontFamily: 'Manrope',
      bodyColor: AppColors.textPrimary,
      displayColor: AppColors.textPrimary,
    );

    return ThemeData(
      useMaterial3: true,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      fontFamily: 'Manrope',
      colorScheme: colorScheme,
      scaffoldBackgroundColor: AppColors.background,
      splashFactory: InkSparkle.splashFactory,
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: PremiumPageTransitionsBuilder(),
          TargetPlatform.iOS: PremiumPageTransitionsBuilder(),
          TargetPlatform.macOS: PremiumPageTransitionsBuilder(),
          TargetPlatform.windows: PremiumPageTransitionsBuilder(),
          TargetPlatform.linux: PremiumPageTransitionsBuilder(),
          TargetPlatform.fuchsia: PremiumPageTransitionsBuilder(),
        },
      ),
      dividerColor: AppColors.border,
      iconTheme: const IconThemeData(color: AppColors.textPrimary, size: 21),
      textTheme: baseTextTheme.copyWith(
        displaySmall: baseTextTheme.displaySmall?.copyWith(
          fontFamily: 'CormorantGaramond',
          fontWeight: FontWeight.w600,
          letterSpacing: -0.7,
        ),
        headlineMedium: baseTextTheme.headlineMedium?.copyWith(
          fontFamily: 'CormorantGaramond',
          fontWeight: FontWeight.w600,
          letterSpacing: -0.4,
        ),
        headlineSmall: baseTextTheme.headlineSmall?.copyWith(
          fontWeight: FontWeight.w700,
          letterSpacing: -0.5,
        ),
        titleMedium: baseTextTheme.titleMedium?.copyWith(
          fontWeight: FontWeight.w700,
        ),
        bodyMedium: baseTextTheme.bodyMedium?.copyWith(height: 1.45),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.background,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
          fontFamily: 'Manrope',
          color: AppColors.textPrimary,
          fontSize: 18,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.3,
        ),
        iconTheme: IconThemeData(color: AppColors.textPrimary),
      ),
      cardTheme: const CardThemeData(
        color: AppColors.card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.lg)),
          side: BorderSide(color: AppColors.border),
        ),
      ),
      listTileTheme: const ListTileThemeData(
        iconColor: AppColors.textSecondary,
        textColor: AppColors.textPrimary,
        contentPadding: EdgeInsets.symmetric(horizontal: AppSpacing.lg),
        minVerticalPadding: 12,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFFFFFFFF),
        helperMaxLines: 3,
        errorMaxLines: 3,
        floatingLabelStyle: const TextStyle(
          color: AppColors.brandRed,
          fontWeight: FontWeight.w600,
        ),
        labelStyle: const TextStyle(color: AppColors.textSecondary),
        hintStyle: const TextStyle(color: AppColors.textSecondary),
        prefixIconColor: AppColors.textSecondary,
        suffixIconColor: AppColors.textSecondary,
        errorStyle: const TextStyle(fontWeight: FontWeight.w500),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          borderSide: const BorderSide(color: AppColors.brandRed, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          borderSide: const BorderSide(color: AppColors.danger),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          borderSide: const BorderSide(color: AppColors.danger, width: 1.6),
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.lg,
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: Colors.white,
        selectedColor: AppColors.plum,
        side: const BorderSide(color: AppColors.border),
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
        labelStyle: const TextStyle(
          fontFamily: 'Manrope',
          color: AppColors.textSecondary,
          fontWeight: FontWeight.w600,
        ),
        secondaryLabelStyle: const TextStyle(
          fontFamily: 'Manrope',
          color: Colors.white,
          fontWeight: FontWeight.w600,
        ),
        checkmarkColor: Colors.white,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.card,
        surfaceTintColor: Colors.transparent,
        indicatorColor: AppColors.paleEmerald,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          return TextStyle(
            fontFamily: 'Manrope',
            fontSize: 11,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w700
                : FontWeight.w500,
            color: states.contains(WidgetState.selected)
                ? AppColors.emerald
                : AppColors.textSecondary,
          );
        }),
      ),
      tooltipTheme: TooltipThemeData(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        textStyle: baseTextTheme.bodySmall?.copyWith(color: Colors.white),
        decoration: BoxDecoration(
          color: AppColors.plum,
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.textPrimary,
          side: const BorderSide(color: AppColors.border),
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
          textStyle: const TextStyle(
            fontFamily: 'Manrope',
            fontWeight: FontWeight.w700,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.lg),
          ),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.brandRed,
          foregroundColor: Colors.white,
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
          textStyle: const TextStyle(
            fontFamily: 'Manrope',
            fontWeight: FontWeight.w700,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.lg),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.brandRed,
          minimumSize: const Size(48, 48),
          textStyle: const TextStyle(
            fontFamily: 'Manrope',
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.plum,
        contentTextStyle: baseTextTheme.bodyMedium?.copyWith(
          color: Colors.white,
        ),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.card,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.xl),
        ),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AppColors.card,
        surfaceTintColor: Colors.transparent,
        showDragHandle: true,
        dragHandleColor: AppColors.border,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
      ),
      tabBarTheme: const TabBarThemeData(
        labelColor: AppColors.taupe,
        unselectedLabelColor: AppColors.textSecondary,
        indicatorColor: AppColors.brandGold,
        dividerColor: AppColors.border,
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AppColors.paleGold,
        foregroundColor: AppColors.textPrimary,
        elevation: 2,
        shape: StadiumBorder(),
      ),
    );
  }
}
