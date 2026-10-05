import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show rootBundle;
import 'package:yaml/yaml.dart';

/// `flutter run --dart-define=DEMO=true` (or `make demo`): the app runs against a built-in,
/// in-memory fake server with seed data. Nothing is persisted; every launch starts fresh.
const bool kDemo = bool.fromEnvironment('DEMO');

/// Values that come from the single pkl source of truth (`shared/config.pkl`),
/// rendered to `assets/config/shared.yaml` by `make config`.
class AppConfig {
  AppConfig._(this._y);
  final YamlMap _y;

  static late AppConfig instance;

  /// Override with `--dart-define=API_BASE=https://api.example.com`.
  static const _apiBase = String.fromEnvironment('API_BASE');
  static String get apiBase {
    if (_apiBase.isNotEmpty) return _apiBase;
    if (kIsWeb) return 'http://localhost:8000';
    return defaultTargetPlatform == TargetPlatform.android ? 'http://10.0.2.2:8000' : 'http://localhost:8000';
  }

  static Future<AppConfig> load() async {
    final text = await rootBundle.loadString('assets/config/shared.yaml');
    return instance = AppConfig.fromYaml(text);
  }

  factory AppConfig.fromYaml(String text) => AppConfig._(loadYaml(text) as YamlMap);

  static Color _hex(String h) => Color(int.parse('FF${h.substring(1)}', radix: 16));

  YamlMap get _brand => _y['brand'] as YamlMap;
  String get brandName => _brand['name'] as String;
  String get tagline => _brand['tagline'] as String;
  String get supportEmail => _brand['supportEmail'] as String;
  String get supportPhone => _brand['supportPhone'] as String;
  Color get royalBlue => _hex(_brand['royalBlue'] as String);
  Color get royalBlueDark => _hex(_brand['royalBlueDark'] as String);
  Color get gold => _hex(_brand['gold'] as String);
  Color get goldLight => _hex(_brand['goldLight'] as String);
  Color get ivory => _hex(_brand['ivory'] as String);

  String get currencySymbol => (_y['payments'] as YamlMap)['currencySymbol'] as String;

  List<Locale> get enabledLocales => [
        for (final l in _y['locales'] as YamlList)
          if (l['enabled'] == true) Locale(l['code'] as String),
      ];
  List<({String code, String name})> get localeOptions => [
        for (final l in _y['locales'] as YamlList)
          if (l['enabled'] == true) (code: l['code'] as String, name: l['name'] as String),
      ];

  List<String> get shopTypes => [for (final t in _y['shopTypes'] as YamlList) t as String];

  List<ServiceArea> get serviceAreas => [
        for (final a in _y['serviceAreas'] as YamlList)
          ServiceArea(a['district'] as String, a['pinPrefix'] as String, a['hubCode'] as String),
      ];

  ServiceArea? areaFor(String pincode) {
    for (final a in serviceAreas) {
      if (pincode.startsWith(a.pinPrefix)) return a;
    }
    return null;
  }

  List<({double min, double percent})> get slabs => [
        for (final s in _y['pricingSlabs'] as YamlList) (min: (s['minSubtotal'] as num).toDouble(), percent: (s['percent'] as num).toDouble()),
      ];

  int get minOrderValue => (_y['orders'] as YamlMap)['minOrderValue'] as int;
  int get minTotalCases => (_y['orders'] as YamlMap)['minTotalCases'] as int;
  List<String> get customerCancellable => [for (final s in (_y['orders'] as YamlMap)['customerCancellable'] as YamlList) s as String];

  List<OrderStage> get stages => [
        for (final s in (_y['orders'] as YamlMap)['stages'] as YamlList)
          OrderStage(s['key'] as String, s['label'] as String, s['description'] as String),
      ];

  List<({String key, String label})> get ticketCategories => [
        for (final c in (_y['support'] as YamlMap)['categories'] as YamlList) (key: c['key'] as String, label: c['label'] as String),
      ];

  String ticketCategoryLabel(String key) => ticketCategories.firstWhere((c) => c.key == key, orElse: () => (key: key, label: key)).label;

  /// Sandbox test instruments: value → "success" | "failure".
  Map<String, String> get testCards => {
        for (final e in ((_y['payments'] as YamlMap)['testCards'] as YamlMap).entries) '${e.key}': '${e.value}',
      };
}

class ServiceArea {
  const ServiceArea(this.district, this.pinPrefix, this.hubCode);
  final String district, pinPrefix, hubCode;
}

class OrderStage {
  const OrderStage(this.key, this.label, this.description);
  final String key, label, description;
}
