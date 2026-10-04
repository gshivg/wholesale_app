import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'core/app_config.dart';
import 'core/theme.dart';
import 'l10n/app_localizations.dart';
import 'providers/providers.dart';
import 'router.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final config = await AppConfig.load();
  final prefs = await SharedPreferences.getInstance();
  runApp(ProviderScope(
    // Fail fast instead of Riverpod 3's automatic retry — screens have their own Retry button.
    retry: (_, _) => null,
    overrides: [prefsProvider.overrideWithValue(prefs)],
    child: RoyalSnacksApp(config: config),
  ));
}

class RoyalSnacksApp extends ConsumerWidget {
  const RoyalSnacksApp({super.key, required this.config});
  final AppConfig config;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final code = ref.watch(localeProvider);
    return MaterialApp.router(
      title: config.brandName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(config),
      routerConfig: ref.watch(routerProvider),
      locale: Locale(code),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [AppLocalizations.delegate, GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate, GlobalCupertinoLocalizations.delegate],
    );
  }
}
