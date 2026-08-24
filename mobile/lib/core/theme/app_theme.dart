import 'package:flutter/material.dart';

abstract final class NusaColors {
  static const primary = Color(0xFF15477A);
  static const primaryDark = Color(0xFF082F5B);
  static const primaryLight = Color(0xFF2D78BA);
  static const accent = Color(0xFFF1C40F);
  static const background = Color(0xFFF8FAFD);
  static const surface = Colors.white;
  static const surfaceBlue = Color(0xFFF1F7FD);
  static const textPrimary = Color(0xFF0C315C);
  static const textSecondary = Color(0xFF6E7F95);
  static const outline = Color(0xFFDCE5EF);
  static const success = Color(0xFF2FA552);
  static const successSurface = Color(0xFFF0FAF3);
}

abstract final class AppColors {
  static const primary = NusaColors.primary;
  static const surface = NusaColors.background;
}

abstract final class AppTheme {
  static final light = _buildLightTheme();

  static ThemeData _buildLightTheme() {
    final colorScheme =
        ColorScheme.fromSeed(
          seedColor: AppColors.primary,
          brightness: Brightness.light,
          surface: NusaColors.surface,
        ).copyWith(
          primary: NusaColors.primary,
          secondary: NusaColors.accent,
          surface: NusaColors.surface,
          outline: NusaColors.outline,
        );

    final base = ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: NusaColors.background,
    );

    return base.copyWith(
      textTheme: base.textTheme.apply(
        bodyColor: NusaColors.textPrimary,
        displayColor: NusaColors.textPrimary,
      ),
      appBarTheme: const AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: NusaColors.background,
        foregroundColor: NusaColors.textPrimary,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        hintStyle: const TextStyle(color: NusaColors.textSecondary),
        prefixIconColor: NusaColors.textSecondary,
        suffixIconColor: NusaColors.textSecondary,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: NusaColors.outline),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: NusaColors.outline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: NusaColors.primary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: colorScheme.error),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(15),
          ),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shadowColor: NusaColors.primary.withValues(alpha: 0.08),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: const BorderSide(color: NusaColors.outline),
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: NusaColors.outline,
        thickness: 1,
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: NusaColors.accent,
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }
}
