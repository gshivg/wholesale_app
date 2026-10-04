import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';
import 'package:royal_snacks/core/app_config.dart';
import 'package:royal_snacks/core/format.dart';
import 'package:royal_snacks/core/theme.dart';
import 'package:royal_snacks/l10n/app_localizations.dart';
import 'package:royal_snacks/models/models.dart';
import 'package:royal_snacks/providers/providers.dart';
import 'package:royal_snacks/screens/auth/login_screen.dart';
import 'package:royal_snacks/widgets/widgets.dart';
import 'package:shared_preferences/shared_preferences.dart';

AppConfig loadConfig() => AppConfig.instance = AppConfig.fromYaml(File('assets/config/shared.yaml').readAsStringSync());

void main() {
  group('pkl-generated shared config', () {
    test('exposes brand colours, stages, service areas and test instruments', () {
      final c = loadConfig();
      expect(c.brandName, 'Royal Snacks');
      expect(c.royalBlue, const Color(0xFF1B3A8C));
      expect(c.gold, const Color(0xFFD4A017));
      expect(c.stages.map((s) => s.key), ['placed', 'confirmed', 'packing', 'packed', 'dispatched', 'out_for_delivery', 'delivered']);
      expect(c.areaFor('211001')?.district, 'Prayagraj');
      expect(c.areaFor('226010')?.hubCode, 'LKO');
      expect(c.areaFor('110001'), isNull);
      expect(c.minOrderValue, 2000);
      expect(c.testCards['4111111111111111'], 'success');
      expect(c.enabledLocales.map((l) => l.languageCode), ['en']);
      // server-only values never reach the client asset
      expect(File('assets/config/shared.yaml').readAsStringSync().contains('prototypeCode'), isFalse);
    });
  });

  group('formatting', () {
    test('rupees use Indian digit grouping', () {
      expect(inr(123456), '₹1,23,456');
      expect(inr(1234567.5), '₹12,34,567.50');
      expect(inr(0), '₹0');
      expect(trimNum(6.0), '6');
      expect(trimNum(2.5), '2.5');
    });
  });

  group('models', () {
    test('Cart parses totals, coupon and issues', () {
      final cart = Cart.fromJson({
        'lines': [
          {'variant_id': 3, 'product_id': 1, 'name': 'Chips', 'label': '20g × 60', 'quantity': 6, 'free_quantity': 1, 'unit_price': 540, 'gross': 3240, 'deal_discount': 97.2, 'deal_title': 'Chips carnival', 'gst_rate': 12, 'total': 3000, 'min_qty': 2},
        ],
        'totals': {'gross': 3240, 'deal_discount': 97.2, 'subtotal': 3142.8, 'slab_percent': 0, 'slab_discount': 0, 'coupon_discount': 200, 'taxable': 2942.8, 'cgst': 176.57, 'sgst': 176.57, 'igst': 0, 'round_off': 0.06, 'total': 3296, 'total_cases': 6, 'free_cases': 1, 'savings': 297.2},
        'coupon': {'code': 'KIRANA200', 'applied': true, 'message': 'x', 'discount': 200},
        'next_slab': {'min_subtotal': 10000, 'percent': 2, 'remaining': 6857.2},
        'errors': [{'code': 'min_value', 'message': 'Minimum order value is ₹2,000'}],
        'can_checkout': false,
      });
      expect(cart.quantityOf(3), 6);
      expect(cart.couponCode, 'KIRANA200');
      expect(cart.totals.total, 3296);
      expect(cart.errors.single.variantId, isNull);
      expect(cart.canCheckout, isFalse);
      expect(cart.nextSlab!['percent'], 2);
    });

    test('AppUser knows whether it can order', () {
      final u = AppUser.fromJson({'id': 1, 'name': 'R', 'phone': '9100000001', 'can_order': false, 'shop': {'id': 1, 'shop_name': 'S', 'status': 'pending'}});
      expect(u.canOrder, isFalse);
      expect(u.isApproved, isFalse);
    });
  });

  group('widgets', () {
    Widget host(Widget child, {List<Override> overrides = const []}) => ProviderScope(
          overrides: overrides,
          child: MaterialApp(
            theme: AppTheme.light(AppConfig.instance),
            localizationsDelegates: const [AppLocalizations.delegate, GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate],
            supportedLocales: AppLocalizations.supportedLocales,
            home: Scaffold(body: child),
          ),
        );

    testWidgets('OrderTracker marks reached stages and the current one', (t) async {
      loadConfig();
      final stages = [
        for (final (i, s) in AppConfig.instance.stages.indexed) OrderStageInfo.fromJson({'key': s.key, 'label': s.label, 'reached': i <= 4, 'at': i <= 4 ? '2026-10-04T10:0$i:00Z' : null}),
      ];
      await t.pumpWidget(host(OrderTracker(stages: stages, status: 'dispatched')));
      expect(find.text('Left warehouse'), findsOneWidget);
      expect(find.byIcon(Icons.check), findsNWidgets(4)); // placed..packed done, dispatched is "current"
      expect(find.byIcon(Icons.warehouse_outlined), findsOneWidget);
    });

    testWidgets('QtyStepper respects min and max', (t) async {
      loadConfig();
      var v = 3;
      await t.pumpWidget(host(StatefulBuilder(builder: (c, set) => QtyStepper(value: v, min: 2, max: 4, onChanged: (n) => set(() => v = n)))));
      await t.tap(find.byIcon(Icons.remove));
      await t.pump();
      expect(v, 2);
      await t.tap(find.byIcon(Icons.remove));
      await t.pump();
      expect(v, 2); // clamped to min
      await t.tap(find.byIcon(Icons.add));
      await t.pump();
      await t.tap(find.byIcon(Icons.add));
      await t.pump();
      expect(v, 4);
      await t.tap(find.byIcon(Icons.add), warnIfMissed: false);
      expect(v, 4); // disabled at max
    });

    testWidgets('Login validates the mobile number before calling the API', (t) async {
      loadConfig();
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      await t.pumpWidget(host(const LoginScreen(), overrides: [prefsProvider.overrideWithValue(prefs)]));
      await t.pump();
      expect(find.text('Welcome back'), findsOneWidget);
      await t.tap(find.widgetWithText(FilledButton, 'Log in'));
      await t.pump();
      expect(find.text('Enter a valid 10-digit mobile number'), findsOneWidget);
      expect(find.text('Enter your password'), findsOneWidget);
    });
  });
}
