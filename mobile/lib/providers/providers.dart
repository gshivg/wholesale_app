import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/api.dart';
import '../models/models.dart';

final prefsProvider = Provider<SharedPreferences>((ref) => throw UnimplementedError('override in main'));

final apiProvider = Provider<Api>((ref) {
  final api = Api(ref.watch(prefsProvider));
  api.locale = ref.watch(localeProvider);
  api.onUnauthorized = () => ref.read(authProvider.notifier).sessionExpired();
  return api;
});

// ── Locale (scaffolding: only enabled locales in shared.yaml are offered) ────────
class LocaleNotifier extends Notifier<String> {
  @override
  String build() => ref.watch(prefsProvider).getString('locale') ?? 'en';
  Future<void> set(String code) async {
    await ref.read(prefsProvider).setString('locale', code);
    state = code;
  }
}

final localeProvider = NotifierProvider<LocaleNotifier, String>(LocaleNotifier.new);

// ── Auth ───────────────────────────────────────────────────────────────────────
class AuthNotifier extends AsyncNotifier<AppUser?> {
  Api get _api => ref.read(apiProvider);

  @override
  Future<AppUser?> build() async {
    if (_api.token == null) return null;
    try {
      final j = await _api.get('/me');
      return AppUser.fromJson(Map<String, dynamic>.from(j['data'] as Map));
    } on ApiException catch (e) {
      if (e.status == 401 || e.status == 403) {
        await _api.setToken(null);
        return null;
      }
      rethrow;
    }
  }

  Future<void> _accept(Map<String, dynamic> j) async {
    await _api.setToken(j['token'] as String);
    state = AsyncData(AppUser.fromJson(Map<String, dynamic>.from(j['user'] as Map)));
  }

  /// Throws [ApiException] (code `phone_unverified` → caller should go to OTP screen).
  Future<void> login(String phone, String password) async {
    final j = await _api.post('/auth/login', data: {'phone': phone, 'password': password, 'device': 'mobile'});
    await _accept(j);
  }

  /// Returns the prototype OTP hint (null in production).
  Future<String?> register(Map<String, dynamic> fields, {XFile? photo}) async {
    final form = FormData.fromMap({...fields});
    if (photo != null) {
      form.files.add(MapEntry('shop_photo', MultipartFile.fromBytes(await photo.readAsBytes(), filename: photo.name)));
    }
    final j = await _api.post('/auth/register', data: form);
    return j['otp_hint'] as String?;
  }

  Future<String?> sendOtp(String phone, String purpose) async {
    final j = await _api.post('/auth/otp/send', data: {'phone': phone, 'purpose': purpose});
    return j['otp_hint'] as String?;
  }

  Future<void> verifyOtp(String phone, String code) async {
    final j = await _api.post('/auth/otp/verify', data: {'phone': phone, 'code': code, 'device': 'mobile'});
    await _accept(j);
  }

  Future<void> resetPassword(String phone, String code, String password) =>
      _api.post('/auth/password/reset', data: {'phone': phone, 'code': code, 'password': password, 'password_confirmation': password});

  Future<void> refresh() async {
    final j = await _api.get('/me');
    state = AsyncData(AppUser.fromJson(Map<String, dynamic>.from(j['data'] as Map)));
  }

  Future<void> updateProfile(Map<String, dynamic> fields) async {
    final j = await _api.put('/me', data: fields);
    state = AsyncData(AppUser.fromJson(Map<String, dynamic>.from(j['data'] as Map)));
  }

  Future<void> changePassword(String current, String next) =>
      _api.post('/me/password', data: {'current_password': current, 'password': next, 'password_confirmation': next});

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } catch (_) {}
    await _api.setToken(null);
    state = const AsyncData(null);
  }

  Future<void> sessionExpired() async {
    await _api.setToken(null);
    state = const AsyncData(null);
  }
}

final authProvider = AsyncNotifierProvider<AuthNotifier, AppUser?>(AuthNotifier.new);

// ── Catalogue ───────────────────────────────────────────────────────────────────
final categoriesProvider = FutureProvider.autoDispose<List<Category>>((ref) async {
  final j = await ref.watch(apiProvider).get('/categories');
  return [for (final c in j['data'] as List) Category.fromJson(Map<String, dynamic>.from(c as Map))];
});

class CatalogQuery {
  const CatalogQuery({this.category, this.search = ''});
  final int? category;
  final String search;
  CatalogQuery copyWith({int? category, bool clearCategory = false, String? search}) =>
      CatalogQuery(category: clearCategory ? null : (category ?? this.category), search: search ?? this.search);
}

class CatalogQueryNotifier extends Notifier<CatalogQuery> {
  @override
  CatalogQuery build() => const CatalogQuery();
  void set(CatalogQuery q) => state = q;
}

final catalogQueryProvider = NotifierProvider<CatalogQueryNotifier, CatalogQuery>(CatalogQueryNotifier.new);

class CatalogState {
  const CatalogState(this.items, this.page, this.lastPage);
  final List<Product> items;
  final int page, lastPage;
  bool get hasMore => page < lastPage;
}

class CatalogNotifier extends AsyncNotifier<CatalogState> {
  Future<CatalogState> _fetch(int page, List<Product> prior) async {
    final q = ref.read(catalogQueryProvider);
    final j = await ref.read(apiProvider).get('/products', query: {
      'page': page,
      if (q.category != null) 'category': q.category,
      if (q.search.trim().isNotEmpty) 'q': q.search.trim(),
    });
    final meta = Map<String, dynamic>.from(j['meta'] as Map);
    return CatalogState([...prior, for (final p in j['data'] as List) Product.fromJson(Map<String, dynamic>.from(p as Map))], meta['page'] as int, meta['last_page'] as int);
  }

  @override
  Future<CatalogState> build() {
    ref.watch(catalogQueryProvider);
    return _fetch(1, const []);
  }

  Future<void> loadMore() async {
    final cur = state.value;
    if (cur == null || !cur.hasMore) return;
    state = AsyncData(await _fetch(cur.page + 1, cur.items));
  }
}

final catalogProvider = AsyncNotifierProvider<CatalogNotifier, CatalogState>(CatalogNotifier.new);

final offersProvider = FutureProvider.autoDispose<Offers>((ref) async => Offers.fromJson(await ref.watch(apiProvider).get('/offers')));

// ── Cart ────────────────────────────────────────────────────────────────────────
class CartNotifier extends AsyncNotifier<Cart?> {
  Api get _api => ref.read(apiProvider);

  @override
  Future<Cart?> build() async {
    final user = await ref.watch(authProvider.future);
    if (user == null || !user.canOrder) return null;
    return _parse(await _api.get('/cart'));
  }

  Cart _parse(Map<String, dynamic> j) => Cart.fromJson(Map<String, dynamic>.from(j['data'] as Map));

  Future<void> setQty(int variantId, int qty) async => state = AsyncData(_parse(await _api.put('/cart/items', data: {'variant_id': variantId, 'quantity': qty})));
  Future<void> remove(int variantId) async => state = AsyncData(_parse(await _api.delete('/cart/items/$variantId')));
  Future<void> clear() async => state = AsyncData(_parse(await _api.delete('/cart')));
  Future<void> applyCoupon(String code) async => state = AsyncData(_parse(await _api.post('/cart/coupon', data: {'code': code})));
  Future<void> removeCoupon() async => state = AsyncData(_parse(await _api.delete('/cart/coupon')));
  Future<void> reload() async => state = AsyncData(_parse(await _api.get('/cart')));
}

final cartProvider = AsyncNotifierProvider<CartNotifier, Cart?>(CartNotifier.new);

// ── Orders ──────────────────────────────────────────────────────────────────────
final ordersProvider = FutureProvider.autoDispose.family<List<OrderSummary>, String>((ref, filter) async {
  final j = await ref.watch(apiProvider).get('/orders', query: {if (filter == 'active') 'active': 1, if (filter == 'delivered') 'status': 'delivered', 'page': 1});
  return [for (final o in j['data'] as List) OrderSummary.fromJson(Map<String, dynamic>.from(o as Map))];
});

final orderProvider = FutureProvider.autoDispose.family<OrderDetail, int>((ref, id) async {
  final j = await ref.watch(apiProvider).get('/orders/$id');
  return OrderDetail.fromJson(Map<String, dynamic>.from(j['data'] as Map));
});

class OrderActions {
  OrderActions(this.ref);
  final Ref ref;
  Api get _api => ref.read(apiProvider);

  /// Returns (order id, payment intent for online orders).
  Future<({int id, PaymentIntent? payment})> place(String method, String? notes) async {
    final j = await _api.post('/orders', data: {'payment_method': method, if (notes != null && notes.isNotEmpty) 'notes': notes});
    ref.invalidate(cartProvider);
    ref.invalidate(ordersProvider);
    return (
      id: (j['data'] as Map)['id'] as int,
      payment: j['payment'] == null ? null : PaymentIntent.fromJson(Map<String, dynamic>.from(j['payment'] as Map)),
    );
  }

  Future<void> cancel(int id, String? reason) async {
    await _api.post('/orders/$id/cancel', data: {if (reason != null && reason.isNotEmpty) 'reason': reason});
    ref.invalidate(orderProvider(id));
    ref.invalidate(ordersProvider);
  }

  Future<void> reorder(int id) async {
    await _api.post('/orders/$id/reorder');
    await ref.read(cartProvider.notifier).reload();
  }

  Future<PaymentIntent> startPayment(int orderId) async =>
      PaymentIntent.fromJson(Map<String, dynamic>.from((await _api.post('/orders/$orderId/payments'))['payment'] as Map));

  /// Returns true when the sandbox payment succeeded.
  Future<bool> confirmPayment(int orderId, int paymentId, String method, String instrument) async {
    final j = await _api.post('/payments/$paymentId/confirm', data: {'method': method, 'instrument': instrument});
    ref.invalidate(orderProvider(orderId));
    ref.invalidate(ordersProvider);
    return (j['payment'] as Map)['status'] == 'paid';
  }

  Future<String> invoiceUrl(int orderId) async => (await _api.get('/orders/$orderId/invoice-link'))['url'] as String;
}

final orderActionsProvider = Provider<OrderActions>(OrderActions.new);

// ── Support ─────────────────────────────────────────────────────────────────────
final ticketsProvider = FutureProvider.autoDispose<List<Ticket>>((ref) async {
  final j = await ref.watch(apiProvider).get('/tickets');
  return [for (final t in j['data'] as List) Ticket.fromJson(Map<String, dynamic>.from(t as Map))];
});

final ticketProvider = FutureProvider.autoDispose.family<Ticket, int>((ref, id) async {
  final j = await ref.watch(apiProvider).get('/tickets/$id');
  return Ticket.fromJson(Map<String, dynamic>.from(j['data'] as Map));
});

class TicketActions {
  TicketActions(this.ref);
  final Ref ref;
  Api get _api => ref.read(apiProvider);

  Future<FormData> _form(Map<String, dynamic> fields, List<XFile> photos) async {
    final f = FormData.fromMap(fields);
    for (final p in photos) {
      f.files.add(MapEntry('photos[]', MultipartFile.fromBytes(await p.readAsBytes(), filename: p.name)));
    }
    return f;
  }

  Future<Ticket> create({required String category, required String subject, required String message, int? orderId, List<XFile> photos = const []}) async {
    final j = await _api.post('/tickets', data: await _form({'category': category, 'subject': subject, 'message': message, 'order_id': ?orderId}, photos));
    ref.invalidate(ticketsProvider);
    return Ticket.fromJson(Map<String, dynamic>.from(j['data'] as Map));
  }

  Future<void> reply(int id, String message, List<XFile> photos) async {
    await _api.post('/tickets/$id/messages', data: await _form({'message': message}, photos));
    ref.invalidate(ticketProvider(id));
    ref.invalidate(ticketsProvider);
  }

  Future<void> close(int id) async {
    await _api.post('/tickets/$id/close');
    ref.invalidate(ticketProvider(id));
    ref.invalidate(ticketsProvider);
  }

  Future<void> rate(int id, int stars) async {
    await _api.post('/tickets/$id/rating', data: {'rating': stars});
    ref.invalidate(ticketProvider(id));
  }
}

final ticketActionsProvider = Provider<TicketActions>(TicketActions.new);

// ── Notifications ───────────────────────────────────────────────────────────────
class NoticeFeed {
  const NoticeFeed(this.items, this.unread);
  final List<Notice> items;
  final int unread;
}

final noticesProvider = FutureProvider.autoDispose<NoticeFeed>((ref) async {
  final j = await ref.watch(apiProvider).get('/notifications');
  return NoticeFeed([for (final n in j['data'] as List) Notice.fromJson(Map<String, dynamic>.from(n as Map))], j['unread'] as int);
});

Future<void> markNoticesRead(WidgetRef ref, {String? id}) async {
  await ref.read(apiProvider).post('/notifications/read', data: {'id': ?id});
  ref.invalidate(noticesProvider);
}
