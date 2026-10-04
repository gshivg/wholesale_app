import 'package:flutter/material.dart';
import 'app_config.dart';

/// Royal blue & gold, Material 3.
class AppTheme {
  static ThemeData light(AppConfig c) {
    final scheme = ColorScheme.fromSeed(
      seedColor: c.royalBlue,
      brightness: Brightness.light,
    ).copyWith(
      primary: c.royalBlue,
      onPrimary: Colors.white,
      primaryContainer: const Color(0xFFDCE3FF),
      onPrimaryContainer: const Color(0xFF00164F),
      secondary: const Color(0xFF5B5F71),
      secondaryContainer: const Color(0xFFE0E2F9),
      tertiary: c.gold,
      onTertiary: const Color(0xFF261A00),
      tertiaryContainer: const Color(0xFFFFE9A8),
      onTertiaryContainer: const Color(0xFF3B2A00),
      surface: const Color(0xFFFDFBF5),
      surfaceContainerLowest: Colors.white,
      surfaceContainerLow: const Color(0xFFF8F5EB),
      surfaceContainer: const Color(0xFFF2EFE5),
      surfaceContainerHigh: const Color(0xFFECE9DF),
      surfaceContainerHighest: const Color(0xFFE6E3D9),
    );
    final text = Typography.material2021().black.apply(bodyColor: scheme.onSurface, displayColor: scheme.onSurface);
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: scheme.surface,
      textTheme: text,
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.primary,
        foregroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 2,
        centerTitle: false,
        titleTextStyle: text.titleLarge?.copyWith(color: Colors.white, fontWeight: FontWeight.w500),
        iconTheme: const IconThemeData(color: Colors.white),
        actionsIconTheme: const IconThemeData(color: Colors.white),
      ),
      cardTheme: CardThemeData(
        color: scheme.surfaceContainerLowest,
        elevation: 1,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(minimumSize: const Size(64, 48), shape: const StadiumBorder(), textStyle: text.labelLarge),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(minimumSize: const Size(64, 48), shape: const StadiumBorder()),
      ),
      inputDecorationTheme: InputDecorationTheme(
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        selectedColor: scheme.secondaryContainer,
        side: BorderSide(color: scheme.outlineVariant),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: scheme.surfaceContainer,
        indicatorColor: scheme.primaryContainer,
        labelTextStyle: WidgetStatePropertyAll(text.labelMedium),
      ),
      snackBarTheme: SnackBarThemeData(behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
      dividerTheme: DividerThemeData(color: scheme.outlineVariant, space: 1),
    );
  }
}

extension BrandColors on BuildContext {
  ColorScheme get cs => Theme.of(this).colorScheme;
  TextTheme get tt => Theme.of(this).textTheme;
  Color get gold => AppConfig.instance.gold;
  Color get goldLight => AppConfig.instance.goldLight;
  Color get royalDark => AppConfig.instance.royalBlueDark;
}
