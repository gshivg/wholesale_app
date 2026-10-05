import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:royal_snacks/core/api.dart';
import 'package:royal_snacks/core/app_config.dart';
import 'package:royal_snacks/demo/demo_api.dart';
import 'package:royal_snacks/models/models.dart';
import 'package:shared_preferences/shared_preferences.dart';

Future<DemoApi> freshApi() async {
  AppConfig.instance = AppConfig.fromYaml(File('assets/config/shared.yaml').readAsStringSync());
  SharedPreferences.setMockInitialValues({});
  final api = DemoApi(await SharedPreferences.getInstance());
  await api.post('/auth/login', data: {'phone': '9100000001', 'password': 'x'});
  return api;
}

void main() {
  test('every launch starts fresh: no token, no remembered state', () async {
    final a = await freshApi();
    await a.put('/cart/items', data: {'variant_id': 1, 'quantity': 6});
    final b = await freshApi(); // "reopened"
    expect((await b.get('/cart'))['data']['lines'], isEmpty);
    expect((await b.get('/orders'))['data'], hasLength(3));
  });

  test('login needs a valid mobile number; unauthenticated calls are 401', () async {
    AppConfig.instance = AppConfig.fromYaml(File('assets/config/shared.yaml').readAsStringSync());
    SharedPreferences.setMockInitialValues({});
    final api = DemoApi(await SharedPreferences.getInstance());
    await expectLater(api.get('/me'), throwsA(isA<ApiException>().having((e) => e.status, 'status', 401)));
    await expectLater(api.post('/auth/login', data: {'phone': '123', 'password': 'x'}), throwsA(isA<ApiException>()));
  });

  test('catalogue, search and categories', () async {
    final api = await freshApi();
    final cats = (await api.get('/categories'))['data'] as List;
    expect(cats, hasLength(8));
    final all = await api.get('/products');
    expect((all['meta'] as Map)['total'], 22);
    expect((all['data'] as List).length, 20); // first page
    expect(((await api.get('/products', query: {'page': 2}))['data'] as List).length, 2);
    final bhujia = await api.get('/products', query: {'q': 'bhujia'});
    expect((bhujia['data'] as List).map((p) => (p as Map)['name']), containsAll(['Aloo Bhujia', 'Bikaneri Bhujia']));
    final p = Product.fromJson(((bhujia['data'] as List).firstWhere((p) => (p as Map)['name'] == 'Aloo Bhujia') as Map).cast<String, dynamic>());
    expect(p.deals.single.summary, 'Buy 10, get 1 free');
  });

  test('pricing: GST split, minimums, deals, slab and coupon match the server rules', () async {
    final api = await freshApi();
    final bhujia = (await api.get('/products', query: {'q': 'aloo'}))['data'][0]['variants'][0]['id'] as int; // ₹720/case, 12% GST
    var cart = Cart.fromJson((await api.put('/cart/items', data: {'variant_id': bhujia, 'quantity': 2}))['data'] as Map<String, dynamic>);
    expect(cart.canCheckout, isFalse); // < ₹2,000 and < 5 cases
    expect(cart.errors.map((e) => e.code), containsAll(['min_value', 'min_cases']));

    cart = Cart.fromJson((await api.put('/cart/items', data: {'variant_id': bhujia, 'quantity': 10}))['data'] as Map<String, dynamic>);
    expect(cart.lines.single.freeQuantity, 1); // buy 10 get 1 free
    expect(cart.totals.gross, 7200);
    expect(cart.totals.cgst, cart.totals.sgst);
    expect(cart.totals.total, 8064); // 7,200 + 12 %
    expect(cart.canCheckout, isTrue);

    cart = Cart.fromJson((await api.put('/cart/items', data: {'variant_id': bhujia, 'quantity': 20}))['data'] as Map<String, dynamic>);
    expect(cart.totals.slabPercent, 2); // ₹14,400 ≥ ₹10,000
    expect(cart.totals.slabDiscount, 288);

    await expectLater(api.post('/cart/coupon', data: {'code': 'NOPE'}), throwsA(isA<ApiException>()));
    cart = Cart.fromJson((await api.post('/cart/coupon', data: {'code': 'festive5'}))['data'] as Map<String, dynamic>);
    expect(cart.couponCode, 'FESTIVE5');
    expect(cart.totals.couponDiscount, closeTo(705.60, 0.01)); // 5 % of 14,112
  });

  test('place order → live progression; online orders wait for payment; cancel restocks', () async {
    final api = await freshApi();
    final v = (await api.get('/products', query: {'q': 'masala magic'}))['data'][0]['variants'][0]['id'] as int;
    await api.put('/cart/items', data: {'variant_id': v, 'quantity': 6});
    final placed = await api.post('/orders', data: {'payment_method': 'online'});
    final id = placed['data']['id'] as int;
    expect(placed['data']['status'], 'placed');
    expect(placed['data']['can_pay'], isTrue);
    expect((await api.get('/cart'))['data']['lines'], isEmpty);

    final pid = placed['payment']['id'] as int;
    var r = await api.post('/payments/$pid/confirm', data: {'method': 'card', 'instrument': '4000 0000 0000 0002'});
    expect(r['payment']['status'], 'failed');
    final pid2 = (await api.post('/orders/$id/payments'))['payment']['id'] as int;
    r = await api.post('/payments/$pid2/confirm', data: {'method': 'upi', 'instrument': 'success@sandbox'});
    expect(r['payment']['status'], 'paid');
    expect(r['data']['payment_status'], 'paid');

    final cancelled = await api.post('/orders/$id/cancel', data: {'reason': 'Changed my mind'});
    expect(cancelled['data']['status'], 'cancelled');
    expect(cancelled['data']['payment_status'], 'refunded');
  });

  test('seeded orders cover delivered, out-for-delivery (with OTP) and packing', () async {
    final api = await freshApi();
    final orders = (await api.get('/orders'))['data'] as List;
    final byStatus = {for (final o in orders) (o as Map)['status']: o['id']};
    expect(byStatus.keys, containsAll(['delivered', 'out_for_delivery', 'packing']));
    final d = OrderDetail.fromJson((await api.get('/orders/${byStatus['out_for_delivery']}'))['data'] as Map<String, dynamic>);
    expect(d.deliveryOtp, hasLength(4));
    expect(d.agentName, isNotNull);
    expect(d.stages.where((s) => s.reached), hasLength(6));
    final delivered = OrderDetail.fromJson((await api.get('/orders/${byStatus['delivered']}'))['data'] as Map<String, dynamic>);
    expect(delivered.summary.paymentStatus, 'paid');
    expect(delivered.deliveryOtp, isNull);
  });

  test('registration → OTP → pending account that cannot order; support ticket flow', () async {
    AppConfig.instance = AppConfig.fromYaml(File('assets/config/shared.yaml').readAsStringSync());
    SharedPreferences.setMockInitialValues({});
    final api = DemoApi(await SharedPreferences.getInstance());
    final fields = {'name': 'Test Owner', 'phone': '9876500001', 'email': 't@x.com', 'password': 'secret123', 'shop_name': 'T Store', 'shop_type': 'Kirana / General store', 'pan': 'ABCDE1234F', 'address_line1': '5 Road', 'city': 'Lucknow'};
    await expectLater(api.post('/auth/register', data: {...fields, 'pincode': '110001'}), throwsA(isA<ApiException>()));
    expect((await api.post('/auth/register', data: {...fields, 'pincode': '226010'}))['otp_hint'], '123456');
    await expectLater(api.post('/auth/otp/verify', data: {'phone': '9876500001', 'code': '000000'}), throwsA(isA<ApiException>()));
    final session = await api.post('/auth/otp/verify', data: {'phone': '9876500001', 'code': '123456'});
    final user = AppUser.fromJson(session['user'] as Map<String, dynamic>);
    expect(user.canOrder, isFalse);
    expect(user.shop!.status, 'pending');
    await expectLater(api.get('/cart'), throwsA(isA<ApiException>().having((e) => e.code, 'code', 'shop_pending')));

    final t = await api.post('/tickets', data: {'category': 'delay', 'subject': 'Where is it?', 'message': 'Please help'});
    final id = t['data']['id'];
    expect((t['data']['messages'] as List), hasLength(2)); // auto-assigned agent acknowledges
    await api.post('/tickets/$id/close');
    final after = await api.post('/tickets/$id/messages', data: {'message': 'Still broken'});
    expect(after['data']['status'], 'open'); // customer reply reopens
  });
}
