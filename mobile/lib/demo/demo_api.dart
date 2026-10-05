import 'dart:math';

import 'package:dio/dio.dart';

import '../core/api.dart';
import '../core/app_config.dart';

/// In-memory stand-in for the Laravel API, used when the app is built with `--dart-define=DEMO=true`.
///
/// It speaks the same JSON as `/api/v1`, so no screen or provider knows the difference. All state
/// lives in this object: closing the app discards everything and the next launch starts fresh.
/// Pricing mirrors the server's `PricingService` (deals → volume slab → coupon → GST, in paise).
class DemoApi extends Api {
  DemoApi(super.prefs) {
    _seed();
  }

  // ── session (never persisted) ──────────────────────────────────────────────────
  String? _token;
  @override
  String? get token => _token;
  @override
  Future<void> setToken(String? t) async => _token = t;

  static const _otp = '123456';
  final _rng = Random();
  int _seq = 100;
  int _next() => ++_seq;

  late Map<String, dynamic> _user;
  Map<String, dynamic>? _pendingUser;
  late final List<_Cat> _cats;
  late final List<_Prod> _prods;
  late final List<_Deal> _deals;
  late final List<_Coupon> _coupons;
  final Map<int, int> _cart = {};
  String? _couponCode;
  final Set<String> _usedCoupons = {};
  final List<_Order> _orders = [];
  final List<_Ticket> _tickets = [];
  final List<Map<String, dynamic>> _notices = [];
  final Map<int, int> _payments = {}; // payment id → order id

  // ── Api surface ────────────────────────────────────────────────────────────────
  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) async => _route('GET', path, query: query);
  @override
  Future<Map<String, dynamic>> post(String path, {Object? data}) async => _route('POST', path, data: data);
  @override
  Future<Map<String, dynamic>> put(String path, {Object? data}) async => _route('PUT', path, data: data);
  @override
  Future<Map<String, dynamic>> delete(String path) async => _route('DELETE', path);

  Map<String, dynamic> _fields(Object? data) {
    if (data is FormData) return {for (final e in data.fields) e.key: e.value};
    if (data is Map) return Map<String, dynamic>.from(data);
    return {};
  }

  ApiException _err(String message, {int status = 422, String? code, String? field}) =>
      ApiException(message, status: status, code: code, fieldErrors: field == null ? const {} : {field: message});

  Future<Map<String, dynamic>> _route(String method, String path, {Map<String, dynamic>? query, Object? data}) async {
    await Future<void>.delayed(const Duration(milliseconds: 120)); // feel like a network
    final f = _fields(data);
    final q = query ?? const {};
    RegExpMatch? m(String re) => RegExp('^$re\$').firstMatch(path);
    bool is_(String meth, String re) => method == meth && RegExp('^$re\$').hasMatch(path);

    // auth
    if (is_('POST', '/auth/login')) return _login(f);
    if (is_('POST', '/auth/register')) return _register(f);
    if (is_('POST', '/auth/otp/send')) return {'message': 'sent', 'otp_hint': _otp};
    if (is_('POST', '/auth/otp/verify')) return _verify(f);
    if (is_('POST', '/auth/password/reset')) {
      if (f['code'] != _otp) throw _err('Incorrect or expired OTP.', field: 'code');
      return {'message': 'Password updated. Please log in.'};
    }
    if (is_('POST', '/auth/logout')) {
      _token = null;
      return {'message': 'Logged out.'};
    }
    if (_token == null) throw ApiException('Unauthenticated.', status: 401);

    // profile
    if (is_('GET', '/me')) return {'data': _userJson()};
    if (is_('PUT', '/me')) return _updateMe(f);
    if (is_('POST', '/me/password')) {
      if ('${f['password']}'.length < 8) throw _err('The password must be at least 8 characters.', field: 'password');
      return {'message': 'Password changed.'};
    }

    // catalogue
    if (is_('GET', '/categories')) {
      return {'data': [for (final c in _cats) {'id': c.id, 'name': c.name, 'slug': c.name.toLowerCase(), 'icon': c.icon, 'product_count': _prods.where((p) => p.cat == c).length}]};
    }
    if (is_('GET', '/products')) return _products(q);
    if (is_('GET', '/offers')) return _offers();

    // cart (ordering needs an approved shop, like the server)
    if (path.startsWith('/cart') || is_('POST', '/orders') || path.startsWith('/payments') || RegExp(r'^/orders/\d+/(cancel|reorder|payments)$').hasMatch(path)) {
      _requireApproved();
    }
    if (is_('GET', '/cart')) return {'data': _cartJson()};
    if (is_('PUT', '/cart/items')) {
      final vid = int.parse('${f['variant_id']}');
      final qty = int.parse('${f['quantity']}');
      if (qty <= 0) {
        _cart.remove(vid);
      } else {
        _cart[vid] = qty;
      }
      return {'data': _cartJson()};
    }
    final rm = m('/cart/items/(\\d+)');
    if (method == 'DELETE' && rm != null) {
      _cart.remove(int.parse(rm.group(1)!));
      return {'data': _cartJson()};
    }
    if (is_('DELETE', '/cart')) {
      _cart.clear();
      _couponCode = null;
      return {'data': _cartJson()};
    }
    if (is_('POST', '/cart/coupon')) {
      final code = '${f['code']}'.trim().toUpperCase();
      final quote = _quote(code);
      if (quote.couponMessage != null && quote.couponApplied == false) throw _err(quote.couponMessage!, field: 'code');
      _couponCode = code;
      return {'data': _cartJson()};
    }
    if (is_('DELETE', '/cart/coupon')) {
      _couponCode = null;
      return {'data': _cartJson()};
    }

    // orders
    if (is_('GET', '/orders')) return _orderList(q);
    if (is_('POST', '/orders')) return _placeOrder(f);
    final om = m('/orders/(\\d+)');
    if (method == 'GET' && om != null) return {'data': _orderJson(_order(om.group(1)!))};
    final oc = m('/orders/(\\d+)/cancel');
    if (method == 'POST' && oc != null) return _cancelOrder(_order(oc.group(1)!), '${f['reason'] ?? ''}');
    final oro = m('/orders/(\\d+)/reorder');
    if (method == 'POST' && oro != null) {
      for (final l in _order(oro.group(1)!).lines) {
        _cart[l.variantId] = l.qty;
      }
      return {'message': 'Items added to your cart.'};
    }
    final op = m('/orders/(\\d+)/payments');
    if (method == 'POST' && op != null) return {'payment': _intent(_order(op.group(1)!))};
    if (method == 'GET' && m('/orders/(\\d+)/invoice-link') != null) return {'url': 'demo://invoice'};
    final pc = m('/payments/(\\d+)/confirm');
    if (method == 'POST' && pc != null) return _confirmPayment(int.parse(pc.group(1)!), f);

    // support
    if (is_('GET', '/tickets')) return {'data': [for (final t in _tickets.reversed) _ticketJson(t)], 'meta': {'page': 1, 'last_page': 1, 'total': _tickets.length}};
    if (is_('POST', '/tickets')) return _createTicket(f);
    final tm = m('/tickets/(\\d+)');
    if (method == 'GET' && tm != null) return {'data': _ticketJson(_ticket(tm.group(1)!), messages: true)};
    final tmsg = m('/tickets/(\\d+)/messages');
    if (method == 'POST' && tmsg != null) {
      final t = _ticket(tmsg.group(1)!);
      t.messages.add(_Msg('You', '${f['message']}', false));
      if (t.status == 'resolved' || t.status == 'closed') t.status = 'open';
      t.lastReply = DateTime.now();
      return {'data': _ticketJson(t, messages: true)};
    }
    final tc = m('/tickets/(\\d+)/close');
    if (method == 'POST' && tc != null) {
      _ticket(tc.group(1)!).status = 'closed';
      return {};
    }
    final tr = m('/tickets/(\\d+)/rating');
    if (method == 'POST' && tr != null) {
      _ticket(tr.group(1)!).rating = int.parse('${f['rating']}');
      return {};
    }

    // notifications
    if (is_('GET', '/notifications')) {
      return {'data': _notices.reversed.toList(), 'unread': _notices.where((n) => n['read'] != true).length};
    }
    if (is_('POST', '/notifications/read')) {
      for (final n in _notices) {
        if (f['id'] == null || n['id'] == f['id']) n['read'] = true;
      }
      return {'unread': _notices.where((n) => n['read'] != true).length};
    }
    throw ApiException('Not available in demo mode: $method $path', status: 404);
  }

  // ── auth ───────────────────────────────────────────────────────────────────────
  Map<String, dynamic> _login(Map<String, dynamic> f) {
    final phone = '${f['phone']}'.trim();
    if (!RegExp(r'^[6-9]\d{9}$').hasMatch(phone) || '${f['password']}'.isEmpty) throw _err('Incorrect mobile number or password.', field: 'phone');
    final u = (_pendingUser != null && _pendingUser!['phone'] == phone) ? _pendingUser! : _user;
    _user = u;
    _token = 'demo-token';
    return {'token': _token, 'user': _userJson()};
  }

  Map<String, dynamic> _register(Map<String, dynamic> f) {
    if (f['phone'] == _user['phone']) throw _err('The phone has already been taken.', field: 'phone');
    final area = AppConfig.instance.areaFor('${f['pincode']}');
    if (area == null) throw _err('We currently deliver only in Prayagraj, Varanasi, Kanpur and Lucknow districts.', field: 'pincode');
    _pendingUser = {
      'id': 2, 'name': f['name'], 'phone': f['phone'], 'email': f['email'], 'role': 'customer', 'locale': 'en', 'phone_verified': false, 'can_order': false,
      'shop': {
        'id': 2, 'shop_name': f['shop_name'], 'shop_type': f['shop_type'], 'gstin': f['gstin'], 'pan': f['pan'], 'address_line1': f['address_line1'], 'address_line2': f['address_line2'],
        'landmark': f['landmark'], 'city': f['city'], 'district': area.district, 'state': 'Uttar Pradesh', 'pincode': f['pincode'], 'photo_url': null, 'status': 'pending', 'status_note': null,
        'credit_limit': 0, 'credit_used': 0, 'credit_available': 0, 'hub': {'code': area.hubCode, 'name': '${area.district} hub'},
      },
    };
    return {'message': 'Registered.', 'phone': f['phone'], 'otp_hint': _otp};
  }

  Map<String, dynamic> _verify(Map<String, dynamic> f) {
    if (f['code'] != _otp) throw _err('Incorrect or expired OTP.', field: 'code');
    _user = (_pendingUser != null && _pendingUser!['phone'] == f['phone']) ? _pendingUser! : _user;
    _user['phone_verified'] = true;
    _token = 'demo-token';
    return {'token': _token, 'user': _userJson()};
  }

  Map<String, dynamic> _userJson() => _user;

  Map<String, dynamic> _updateMe(Map<String, dynamic> f) {
    for (final k in ['name', 'email']) {
      if (f[k] != null) _user[k] = f[k];
    }
    final shop = _user['shop'] as Map<String, dynamic>;
    for (final k in ['address_line1', 'city']) {
      if (f[k] != null) shop[k] = f[k];
    }
    if (f['pincode'] != null) {
      final area = AppConfig.instance.areaFor('${f['pincode']}');
      if (area == null) throw _err('We do not deliver to this PIN code yet.', field: 'pincode');
      shop['pincode'] = f['pincode'];
      shop['district'] = area.district;
    }
    return {'data': _user};
  }

  void _requireApproved() {
    if (_user['can_order'] != true) {
      throw ApiException('Your account is awaiting approval. We will notify you once it is verified.', status: 403, code: 'shop_pending');
    }
  }

  // ── catalogue ──────────────────────────────────────────────────────────────────
  Map<String, dynamic> _products(Map<String, dynamic> q) {
    var list = _prods.toList();
    if (q['category'] != null) list = list.where((p) => p.cat.id == int.parse('${q['category']}')).toList();
    final term = '${q['q'] ?? ''}'.trim().toLowerCase();
    if (term.isNotEmpty) list = list.where((p) => p.name.toLowerCase().contains(term) || p.brand.toLowerCase().contains(term)).toList();
    list.sort((a, b) => a.featured == b.featured ? a.name.compareTo(b.name) : (a.featured ? -1 : 1));
    final page = int.tryParse('${q['page'] ?? 1}') ?? 1;
    const per = 20;
    final last = max(1, (list.length / per).ceil());
    return {
      'data': [for (final p in list.skip((page - 1) * per).take(per)) _productJson(p)],
      'meta': {'page': page, 'last_page': last, 'total': list.length},
    };
  }

  Map<String, dynamic> _productJson(_Prod p) => {
        'id': p.id, 'name': p.name, 'slug': p.name.toLowerCase().replaceAll(' ', '-'), 'brand': p.brand, 'description': p.description,
        'category': {'id': p.cat.id, 'name': p.cat.name, 'icon': p.cat.icon}, 'image_url': null, 'accent': p.accent, 'gst_rate': p.gst, 'hsn_code': null, 'is_featured': p.featured,
        'variants': [
          for (final v in p.variants)
            {
              'id': v.id, 'sku': 'SKU${v.id}', 'label': v.label, 'packs_per_case': v.packs, 'price': v.price, 'mrp': v.mrp, 'pack_price': (v.price / v.packs * 100).round() / 100,
              'min_qty': v.min, 'max_qty': 500, 'available': v.stock, 'in_stock': v.stock >= v.min,
            }
        ],
        'deals': [for (final d in _deals.where((d) => d.appliesTo(p))) _dealJson(d)],
      };

  Map<String, dynamic> _dealJson(_Deal d) =>
      {'id': d.id, 'title': d.title, 'description': d.description, 'type': d.type, 'summary': d.summary, 'min_qty': d.minQty, 'ends_at': DateTime.now().add(const Duration(days: 60)).toIso8601String()};

  Map<String, dynamic> _offers() => {
        'slabs': [for (final s in AppConfig.instance.slabs) {'min_subtotal': s.min, 'percent': s.percent}],
        'deals': [for (final d in _deals) _dealJson(d)],
        'coupons': [
          for (final c in _coupons.where((c) => c.public))
            {'code': c.code, 'title': c.title, 'description': c.description, 'summary': c.summary, 'min_order': c.minOrder, 'ends_at': DateTime.now().add(const Duration(days: 90)).toIso8601String()}
        ],
      };

  // ── pricing (paise) ────────────────────────────────────────────────────────────
  _Quote _quote(String? couponCode) {
    final lines = <_Line>[];
    final errors = <Map<String, dynamic>>[];
    for (final e in _cart.entries) {
      final p = _prods.firstWhere((p) => p.variants.any((v) => v.id == e.key));
      final v = p.variants.firstWhere((v) => v.id == e.key);
      final qty = e.value;
      final unit = (v.price * 100).round();
      final gross = unit * qty;
      var deal = 0, free = 0;
      String? dealTitle;
      var best = 0;
      for (final d in _deals) {
        if (!d.appliesTo(p) || qty < max(1, d.minQty)) continue;
        var disc = 0, fr = 0;
        if (d.type == 'percent') {
          disc = (gross * d.value / 100).round();
        } else if (d.type == 'flat') {
          disc = min(gross, (d.value * 100).round() * qty);
        } else if (d.type == 'bxgy' && d.buy > 0) {
          fr = (qty ~/ d.buy) * d.free;
        }
        final benefit = disc + fr * unit;
        if (benefit > best) {
          best = benefit;
          deal = disc;
          free = fr;
          dealTitle = d.title;
        }
      }
      if (qty < v.min) errors.add({'code': 'min_qty', 'variant_id': v.id, 'message': '${p.name} (${v.label}): minimum ${v.min} cases.'});
      if (qty + free > v.stock) errors.add({'code': 'stock', 'variant_id': v.id, 'message': '${p.name} (${v.label}): only ${v.stock} cases in stock.'});
      lines.add(_Line(v.id, p.id, p.name, v.label, p.accent, qty, free, unit, (v.mrp * 100).round(), gross, deal, dealTitle, p.gst, v.min));
    }
    final grossTotal = lines.fold(0, (a, l) => a + l.gross);
    final dealTotal = lines.fold(0, (a, l) => a + l.deal);
    final subtotal = grossTotal - dealTotal;
    final cases = lines.fold(0, (a, l) => a + l.qty);

    final slabs = AppConfig.instance.slabs;
    ({double min, double percent})? slab;
    ({double min, double percent})? nextSlab;
    for (final s in slabs) {
      if (s.min * 100 <= subtotal) {
        slab = s;
      } else {
        nextSlab ??= s;
      }
    }
    final slabPct = slab?.percent ?? 0;
    final slabTotal = (subtotal * slabPct / 100).round();
    final afterSlab = subtotal - slabTotal;

    var couponTotal = 0;
    var applied = false;
    String? couponMsg;
    _Coupon? coupon;
    if (couponCode != null && couponCode.isNotEmpty) {
      coupon = _coupons.where((c) => c.code == couponCode.toUpperCase()).firstOrNull;
      if (coupon == null) {
        couponMsg = 'This coupon is invalid or has expired.';
      } else if (_usedCoupons.contains(coupon.code)) {
        couponMsg = 'You have already used this coupon.';
      } else if (afterSlab < coupon.minOrder * 100) {
        couponMsg = 'Add items worth ₹${coupon.minOrder.round()} or more to use this coupon.';
      } else {
        applied = true;
        couponTotal = coupon.type == 'percent' ? (afterSlab * coupon.value / 100).round() : (coupon.value * 100).round();
        if (coupon.maxDiscount != null) couponTotal = min(couponTotal, (coupon.maxDiscount! * 100).round());
        couponTotal = min(couponTotal, afterSlab);
      }
    }

    final minValue = AppConfig.instance.minOrderValue, minCases = AppConfig.instance.minTotalCases;
    if (lines.isNotEmpty && subtotal < minValue * 100) errors.add({'code': 'min_value', 'message': 'Minimum order value is ₹$minValue (after deals).'});
    if (lines.isNotEmpty && cases < minCases) errors.add({'code': 'min_cases', 'message': 'Minimum order is $minCases cases in total.'});

    final slabAlloc = _allocate(slabTotal, [for (final l in lines) l.gross - l.deal]);
    final couponAlloc = _allocate(couponTotal, [for (var i = 0; i < lines.length; i++) lines[i].gross - lines[i].deal - slabAlloc[i]]);
    var taxable = 0, tax = 0;
    for (var i = 0; i < lines.length; i++) {
      final l = lines[i];
      l.slab = slabAlloc[i];
      l.coupon = couponAlloc[i];
      l.taxable = l.gross - l.deal - l.slab - l.coupon;
      l.tax = (l.taxable * l.gst / 100).round();
      l.total = l.taxable + l.tax;
      taxable += l.taxable;
      tax += l.tax;
    }
    final grand = taxable + tax;
    final rounded = (grand / 100).round() * 100;
    final cgst = tax ~/ 2;
    return _Quote(
      lines: lines, gross: grossTotal, deal: dealTotal, subtotal: subtotal, slabPct: slabPct, slab: slabTotal, coupon: couponTotal, taxable: taxable,
      cgst: cgst, sgst: tax - cgst, roundOff: rounded - grand, total: rounded, cases: cases, free: lines.fold(0, (a, l) => a + l.free),
      couponCode: applied ? coupon!.code : couponCode, couponApplied: applied, couponMessage: couponMsg,
      next: nextSlab == null ? null : {'min_subtotal': nextSlab.min, 'percent': nextSlab.percent, 'remaining': max(0, (nextSlab.min * 100).round() - subtotal) / 100},
      errors: errors, canCheckout: lines.isNotEmpty && errors.isEmpty,
    );
  }

  List<int> _allocate(int total, List<int> weights) {
    final out = List.filled(weights.length, 0);
    final sum = weights.fold(0, (a, b) => a + b);
    if (total <= 0 || sum <= 0) return out;
    var given = 0;
    for (var i = 0; i < weights.length; i++) {
      out[i] = i == weights.length - 1 ? total - given : (total * weights[i] / sum).floor();
      given += out[i];
    }
    return out;
  }

  double _r(int p) => p / 100;

  Map<String, dynamic> _cartJson() {
    final q = _quote(_couponCode);
    if (_couponCode != null && !q.couponApplied) _couponCode = null; // no longer qualifies
    return _quoteJson(q);
  }

  Map<String, dynamic> _quoteJson(_Quote q) => {
        'lines': [
          for (final l in q.lines)
            {
              'variant_id': l.variantId, 'product_id': l.productId, 'name': l.name, 'label': l.label, 'accent': l.accent, 'quantity': l.qty, 'free_quantity': l.free,
              'unit_price': _r(l.unit), 'gross': _r(l.gross), 'deal_discount': _r(l.deal), 'deal_title': l.dealTitle, 'gst_rate': l.gst, 'total': _r(l.total), 'min_qty': l.minQty,
            }
        ],
        'totals': {
          'gross': _r(q.gross), 'deal_discount': _r(q.deal), 'subtotal': _r(q.subtotal), 'slab_percent': q.slabPct, 'slab_discount': _r(q.slab), 'coupon_discount': _r(q.coupon),
          'taxable': _r(q.taxable), 'cgst': _r(q.cgst), 'sgst': _r(q.sgst), 'igst': 0, 'round_off': _r(q.roundOff), 'total': _r(q.total), 'total_cases': q.cases, 'free_cases': q.free,
          'savings': _r(q.deal + q.slab + q.coupon),
        },
        'coupon': {'code': q.couponApplied ? q.couponCode : null, 'applied': q.couponApplied, 'message': q.couponMessage, 'discount': _r(q.coupon)},
        'next_slab': q.next,
        'errors': q.errors,
        'can_checkout': q.canCheckout,
      };

  // ── orders ─────────────────────────────────────────────────────────────────────
  _Order _order(String id) => _orders.firstWhere((o) => o.id == int.parse(id), orElse: () => throw ApiException('Not found.', status: 404));

  Map<String, dynamic> _orderList(Map<String, dynamic> q) {
    var list = _orders.reversed.toList();
    if (q['active'] != null) list = list.where((o) => !const {'delivered', 'cancelled', 'returned'}.contains(o.status)).toList();
    if (q['status'] != null) list = list.where((o) => o.status == q['status']).toList();
    return {'data': [for (final o in list) _orderSummary(o)], 'meta': {'page': 1, 'last_page': 1, 'total': list.length}};
  }

  String _label(String status) {
    for (final s in AppConfig.instance.stages) {
      if (s.key == status) return s.label;
    }
    return status == 'cancelled' ? 'Cancelled' : status;
  }

  Map<String, dynamic> _orderSummary(_Order o) => {
        'id': o.id, 'number': o.number, 'status': o.status, 'status_label': _label(o.status), 'payment_method': o.method, 'payment_status': o.paymentStatus,
        'total': _r(o.q.total), 'total_cases': o.q.cases, 'item_count': o.lines.length, 'placed_at': o.placedAt.toIso8601String(),
      };

  Map<String, dynamic> _orderJson(_Order o) {
    final stages = AppConfig.instance.stages;
    final reached = o.reachedIndex;
    final times = o.stageTimes;
    final status = o.status;
    final out = status == 'dispatched' || status == 'out_for_delivery';
    return {
      ..._orderSummary(o),
      'stages': [
        for (var i = 0; i < stages.length; i++)
          {'key': stages[i].key, 'label': stages[i].label, 'description': stages[i].description, 'reached': i <= reached, 'at': i <= reached ? times[i].toIso8601String() : null}
      ],
      'is_final': const {'delivered', 'cancelled'}.contains(status),
      'cancel_reason': o.cancelReason,
      'can_cancel': AppConfig.instance.customerCancellable.contains(status),
      'can_pay': o.method == 'online' && o.paymentStatus != 'paid' && status == 'placed',
      'delivery_otp': out ? o.otp : null,
      'agent': out ? {'name': 'Ravi Kumar', 'phone': '9000000012'} : null,
      'hub': {'code': 'PRG', 'name': 'Prayagraj Main Warehouse'},
      'notes': o.notes,
      'address': o.address,
      'invoice_available': reached >= 1 && status != 'cancelled',
      'items': [
        for (final l in o.q.lines)
          {'id': l.variantId, 'variant_id': l.variantId, 'name': l.name, 'label': l.label, 'quantity': l.qty, 'free_quantity': l.free, 'unit_price': _r(l.unit), 'deal_title': l.dealTitle, 'taxable': _r(l.taxable), 'gst_rate': l.gst, 'tax': _r(l.tax), 'total': _r(l.total)}
      ],
      'totals': {
        'gross': _r(o.q.gross), 'deal_discount': _r(o.q.deal), 'slab_discount': _r(o.q.slab), 'coupon_discount': _r(o.q.coupon), 'coupon_code': o.q.couponApplied ? o.q.couponCode : null,
        'taxable': _r(o.q.taxable), 'cgst': _r(o.q.cgst), 'sgst': _r(o.q.sgst), 'igst': 0, 'round_off': _r(o.q.roundOff), 'total': _r(o.q.total),
      },
      'events': [
        for (var i = 0; i <= reached; i++) {'status': stages[i].key, 'label': stages[i].label, 'note': i == 0 ? 'Order placed' : null, 'at': times[i].toIso8601String()},
        if (o.cancelled) {'status': 'cancelled', 'label': 'Cancelled', 'note': o.cancelReason ?? 'Order cancelled', 'at': o.cancelledAt!.toIso8601String()},
      ],
    };
  }

  Map<String, dynamic> _placeOrder(Map<String, dynamic> f) {
    final method = '${f['payment_method']}';
    final q = _quote(_couponCode);
    if (!q.canCheckout) throw _err(q.errors.isEmpty ? 'Your cart is empty.' : '${q.errors.first['message']}', field: 'cart');
    final shop = _user['shop'] as Map<String, dynamic>;
    if (method == 'credit' && _r(q.total) > (shop['credit_available'] as num)) throw _err('Not enough credit.', field: 'payment_method');
    final o = _newOrder(q, method, '${f['notes'] ?? ''}'.trim().isEmpty ? null : '${f['notes']}', DateTime.now());
    _commitStock(q, -1);
    if (q.couponApplied) _usedCoupons.add(q.couponCode!);
    if (method == 'credit') _adjustCredit(_r(q.total));
    _cart.clear();
    _couponCode = null;
    _notice('Order placed', 'Order ${o.number} for ₹${_r(q.total).round()} was placed.', 'order', orderId: o.id);
    return {'data': _orderJson(o), if (method == 'online') 'payment': _intent(o)};
  }

  _Order _newOrder(_Quote q, String method, String? notes, DateTime at, {int? fixedStage}) {
    final shop = _user['shop'] as Map<String, dynamic>;
    final o = _Order(
      id: _next(), number: 'RS${at.year % 100}${at.month.toString().padLeft(2, '0')}${at.day.toString().padLeft(2, '0')}-${1000 + _rng.nextInt(9000)}',
      placedAt: at, method: method, q: q, notes: notes, otp: '${1000 + _rng.nextInt(9000)}', fixedStage: fixedStage,
      address: {
        'name': shop['shop_name'], 'contact': _user['name'], 'phone': _user['phone'], 'line1': shop['address_line1'], 'line2': shop['address_line2'],
        'city': shop['city'], 'pincode': shop['pincode'], 'state': 'Uttar Pradesh',
      },
    );
    o.paymentStatus = method == 'cod' ? 'pending' : (method == 'credit' ? 'pending' : 'pending');
    if (method == 'online' && fixedStage != null) o.paymentStatus = 'paid';
    if (fixedStage != null && method == 'cod' && fixedStage >= 6) o.paymentStatus = 'paid';
    if (method != 'online' || fixedStage != null) o.progressStart = at;
    _orders.add(o);
    return o;
  }

  void _commitStock(_Quote q, int sign) {
    for (final l in q.lines) {
      final v = _prods.expand((p) => p.variants).firstWhere((v) => v.id == l.variantId);
      v.stock += sign * (l.qty + l.free);
    }
  }

  void _adjustCredit(double delta) {
    final s = _user['shop'] as Map<String, dynamic>;
    s['credit_used'] = (s['credit_used'] as num) + delta;
    s['credit_available'] = max(0, (s['credit_limit'] as num) - (s['credit_used'] as num));
  }

  Map<String, dynamic> _cancelOrder(_Order o, String reason) {
    if (!AppConfig.instance.customerCancellable.contains(o.status)) throw ApiException('You are not allowed to make this change.', status: 403);
    o.cancelled = true;
    o.cancelledAt = DateTime.now();
    o.cancelReason = reason.isEmpty ? null : reason;
    if (o.paymentStatus == 'paid') o.paymentStatus = 'refunded';
    _commitStock(o.q, 1);
    if (o.method == 'credit') _adjustCredit(-_r(o.q.total));
    _notice('Order cancelled', '${o.number} was cancelled.', 'order', orderId: o.id);
    return {'data': _orderJson(o)};
  }

  Map<String, dynamic> _intent(_Order o) {
    final id = _next();
    _payments[id] = o.id;
    return {'id': id, 'reference': 'sbx_demo$id', 'amount': _r(o.q.total), 'currency': 'INR', 'gateway': 'sandbox', 'test_instruments': []};
  }

  Map<String, dynamic> _confirmPayment(int id, Map<String, dynamic> f) {
    final o = _orders.firstWhere((o) => o.id == _payments[id]);
    final outcome = AppConfig.instance.testCards['${f['instrument']}'.replaceAll(' ', '')];
    if (outcome == null) throw _err('Unknown sandbox instrument. Use a test card or test UPI id.');
    final ok = outcome == 'success';
    if (ok) {
      o.paymentStatus = 'paid';
      o.progressStart ??= DateTime.now(); // staff pick the order up once it is paid
      _notice('Payment received', 'Payment for ${o.number} was successful.', 'order', orderId: o.id);
    } else {
      o.paymentStatus = 'failed';
      _notice('Payment failed', 'Payment for ${o.number} failed. You can retry from the order page.', 'order', orderId: o.id);
    }
    return {'payment': {'id': id, 'status': ok ? 'paid' : 'failed'}, 'data': _orderJson(o)};
  }

  // ── tickets & notifications ─────────────────────────────────────────────────────
  _Ticket _ticket(String id) => _tickets.firstWhere((t) => t.id == int.parse(id), orElse: () => throw ApiException('Not found.', status: 404));

  Map<String, dynamic> _ticketJson(_Ticket t, {bool messages = false}) => {
        'id': t.id, 'number': t.number, 'subject': t.subject, 'category': t.category, 'category_label': AppConfig.instance.ticketCategoryLabel(t.category), 'status': t.status,
        'priority': 'normal', 'order': t.orderNumber == null ? null : {'id': 0, 'number': t.orderNumber}, 'agent_name': 'Asha (Support)', 'rating': t.rating,
        'created_at': t.created.toIso8601String(), 'last_reply_at': t.lastReply.toIso8601String(),
        if (messages) 'messages': [for (final m in t.messages) {'id': 0, 'body': m.body, 'from_staff': m.staff, 'author': m.staff ? 'Asha (Support)' : 'You', 'attachments': [], 'at': m.at.toIso8601String()}],
      };

  Map<String, dynamic> _createTicket(Map<String, dynamic> f) {
    final orderId = int.tryParse('${f['order_id'] ?? ''}');
    final t = _Ticket(_next(), 'TKT-${10000 + _rng.nextInt(89999)}', '${f['subject']}', '${f['category']}', orderId == null ? null : _orders.where((o) => o.id == orderId).firstOrNull?.number);
    t.messages.add(_Msg('You', '${f['message']}', false));
    t.messages.add(_Msg('Asha', 'Thanks for reaching out — we have logged this and an agent is looking into it. We will update you here shortly.', true));
    t.status = 'in_progress';
    _tickets.add(t);
    _notice('Reply on ${t.number}', 'Thanks for reaching out — we have logged this…', 'ticket', ticketId: t.id);
    return {'data': _ticketJson(t, messages: true)};
  }

  void _notice(String title, String body, String kind, {int? orderId, int? ticketId}) {
    _notices.add({'id': 'n${_next()}', 'title': title, 'body': body, 'kind': kind, 'order_id': orderId, 'ticket_id': ticketId, 'read': false, 'at': DateTime.now().toIso8601String()});
  }

  // ── seed data ──────────────────────────────────────────────────────────────────
  void _seed() {
    _user = {
      'id': 1, 'name': 'Ramesh Gupta', 'phone': '9100000001', 'email': 'ramesh@example.com', 'role': 'customer', 'locale': 'en', 'phone_verified': true, 'can_order': true,
      'shop': {
        'id': 1, 'shop_name': 'Gupta General Store', 'shop_type': 'Kirana / General store', 'gstin': '09ABCDE1234F1Z5', 'pan': 'ABCDE1234F', 'address_line1': '12 Civil Lines',
        'address_line2': null, 'landmark': 'Near Hanuman Mandir', 'city': 'Prayagraj', 'district': 'Prayagraj', 'state': 'Uttar Pradesh', 'pincode': '211001', 'photo_url': null,
        'status': 'approved', 'status_note': null, 'credit_limit': 50000, 'credit_used': 0, 'credit_available': 50000, 'hub': {'code': 'PRG', 'name': 'Prayagraj Main Warehouse'},
      },
    };

    _cats = [
      _Cat(1, 'Chips & Crisps', 'bubble_chart'), _Cat(2, 'Namkeen & Mixtures', 'restaurant'), _Cat(3, 'Biscuits & Cookies', 'cookie'), _Cat(4, 'Instant Noodles & Pasta', 'ramen_dining'),
      _Cat(5, 'Beverages', 'local_drink'), _Cat(6, 'Chocolates & Sweets', 'icecream'), _Cat(7, 'Popcorn & Roasted Snacks', 'sports_bar'), _Cat(8, 'Rusk, Papad & Others', 'bakery_dining'),
    ];
    var pid = 0, vid = 0;
    _Prod p(int cat, String name, String brand, String desc, double gst, String accent, bool featured, List<(String, int, double, double, int)> vs) => _Prod(
          ++pid, _cats[cat - 1], name, brand, desc, gst, accent, featured, [for (final v in vs) _Var(++vid, v.$1, v.$2, v.$3, v.$4, v.$5, 80 + _rng.nextInt(400))]);
    _prods = [
      p(1, 'Classic Salted Chips', 'Royal Crunch', 'Thin-sliced potatoes, lightly salted.', 12, '#F4C542', true, [('20g × 60 packs', 60, 540, 600, 2), ('52g × 36 packs', 36, 720, 800, 2)]),
      p(1, 'Masala Magic Chips', 'Royal Crunch', 'Desi masala kick in every bite.', 12, '#E4572E', true, [('20g × 60 packs', 60, 540, 600, 2), ('52g × 36 packs', 36, 720, 800, 2)]),
      p(1, 'Cream & Onion Chips', 'Royal Crunch', 'Tangy cream and onion seasoning.', 12, '#4C9F70', false, [('52g × 36 packs', 36, 720, 800, 2)]),
      p(1, 'Tomato Tango Chips', 'Royal Crunch', 'Sweet-sour tomato flavour.', 12, '#C0392B', false, [('20g × 60 packs', 60, 540, 600, 2)]),
      p(2, 'Aloo Bhujia', 'Shahi Namkeen', 'Crispy potato-gram noodles with spices.', 12, '#E9A23B', true, [('40g × 48 packs', 48, 720, 800, 2), ('200g × 24 packs', 24, 1920, 2160, 1)]),
      p(2, 'Moong Dal', 'Shahi Namkeen', 'Crunchy fried moong dal, lightly salted.', 12, '#D4A017', false, [('40g × 48 packs', 48, 720, 800, 2)]),
      p(2, 'Khatta Meetha Mixture', 'Shahi Namkeen', 'Sweet-and-sour Indian snack mix.', 12, '#B5651D', false, [('35g × 50 packs', 50, 650, 750, 2)]),
      p(2, 'Bikaneri Bhujia', 'Shahi Namkeen', 'Bikaner-style spicy sev.', 12, '#CC5500', true, [('40g × 48 packs', 48, 740, 820, 2), ('400g × 12 packs', 12, 2000, 2250, 1)]),
      p(3, 'Glucose Biscuits', 'Royal Bakes', 'Everyday energy biscuits.', 18, '#F2C94C', true, [('60g × 60 packs', 60, 600, 660, 3), ('120g × 36 packs', 36, 780, 860, 2)]),
      p(3, 'Cream Sandwich Biscuits', 'Royal Bakes', 'Vanilla cream-filled sandwich biscuits.', 18, '#8E6BBF', false, [('55g × 48 packs', 48, 720, 800, 2)]),
      p(3, 'Butter Cookies', 'Royal Bakes', 'Rich, buttery cookies.', 18, '#E0A458', true, [('75g × 40 packs', 40, 880, 980, 2)]),
      p(3, 'Choco Chip Cookies', 'Royal Bakes', 'Loaded with choco chips.', 18, '#5D4037', false, [('60g × 48 packs', 48, 960, 1080, 2)]),
      p(4, 'Masala Instant Noodles', 'Royal Bowl', 'Classic masala 2-minute noodles.', 12, '#F57C00', true, [('70g × 48 packs', 48, 576, 648, 3)]),
      p(4, 'Macaroni Masala', 'Royal Bowl', 'Cheesy-masala macaroni pasta.', 12, '#FBC02D', false, [('65g × 48 packs', 48, 648, 720, 2)]),
      p(5, 'Mango Fruit Drink', 'Royal Sip', 'Ready-to-drink mango fruit beverage.', 12, '#FFB300', true, [('200ml × 24 packs', 24, 360, 420, 3), ('1L × 12 packs', 12, 720, 840, 2)]),
      p(5, 'Jeera Soda', 'Royal Sip', 'Spiced cumin sparkling soda.', 28, '#8BC34A', false, [('250ml × 24 packs', 24, 400, 480, 3)]),
      p(6, 'Milk Choco Bar', 'Royal Treats', 'Creamy milk chocolate bar.', 18, '#6D4C41', true, [('25g × 72 packs', 72, 1080, 1260, 2)]),
      p(6, 'Fruit Gummies', 'Royal Treats', 'Chewy assorted fruit gummies.', 18, '#E91E63', false, [('20g × 80 packs', 80, 760, 880, 2)]),
      p(7, 'Butter Popcorn', 'Royal Pop', 'Ready-to-eat buttery popcorn.', 12, '#FFCA28', false, [('30g × 50 packs', 50, 600, 700, 2)]),
      p(7, 'Roasted Makhana Peri-Peri', 'Royal Pop', 'Light roasted fox nuts, peri-peri.', 12, '#D84315', true, [('30g × 40 packs', 40, 1040, 1200, 2)]),
      p(8, 'Elaichi Rusk', 'Royal Bakes', 'Cardamom tea-time rusk.', 18, '#CDA434', false, [('200g × 24 packs', 24, 1200, 1320, 2)]),
      p(8, 'Urad Papad', 'Shahi Namkeen', 'Classic spiced urad dal papad.', 5, '#BCAAA4', false, [('200g × 30 packs', 30, 1350, 1500, 2)]),
    ];
    _Prod byName(String n) => _prods.firstWhere((p) => p.name == n);
    _deals = [
      _Deal(1, 'Chips carnival', '3% off all chips when you take 5+ cases', 'percent', 3, 0, 0, 5, cat: _cats[0]),
      _Deal(2, 'Bhujia: buy 10 get 1 free', 'Every 10 cases of Aloo Bhujia, 1 case free', 'bxgy', 0, 10, 1, 10, prod: byName('Aloo Bhujia')),
      _Deal(3, 'Glucose flat ₹30 off', '₹30 off per case on Glucose Biscuits', 'flat', 30, 0, 0, 3, prod: byName('Glucose Biscuits')),
      _Deal(4, 'Noodles: buy 6 get 1 free', 'Every 6 cases, 1 case free', 'bxgy', 0, 6, 1, 6, prod: byName('Masala Instant Noodles')),
      _Deal(5, 'Mango drink 8% off', '8% off Mango Fruit Drink, 4+ cases', 'percent', 8, 0, 0, 4, prod: byName('Mango Fruit Drink')),
    ];
    _coupons = [
      _Coupon('WELCOME500', 'Welcome gift', '₹500 off your order of ₹5,000+', 'flat', 500, null, 5000, true),
      _Coupon('FESTIVE5', 'Festive 5%', '5% off on orders above ₹8,000 (up to ₹2,000)', 'percent', 5, 2000, 8000, true),
      _Coupon('SNACKUP10', 'Snack-up 10%', '10% off, max ₹3,000, on orders above ₹20,000', 'percent', 10, 3000, 20000, true),
      _Coupon('KIRANA200', 'Kirana partner', 'Private code shared by our sales team: ₹200 off', 'flat', 200, null, 2500, false),
    ];

    // History: one delivered, one out for delivery, one being packed — so tracking has something to show.
    Map<int, int> items(Map<String, int> m) => {for (final e in m.entries) _prods.firstWhere((p) => p.name == e.key).variants.first.id: e.value};
    void seedOrder(Map<String, int> cart, String method, int stage, Duration ago) {
      _cart
        ..clear()
        ..addAll(items(cart));
      final q = _quote(null);
      _newOrder(q, method, null, DateTime.now().subtract(ago), fixedStage: stage);
      if (method == 'credit') _adjustCredit(_r(q.total));
      _cart.clear();
    }

    seedOrder({'Classic Salted Chips': 6, 'Aloo Bhujia': 10, 'Glucose Biscuits': 4}, 'cod', 6, const Duration(days: 6));
    seedOrder({'Masala Instant Noodles': 7, 'Mango Fruit Drink': 5}, 'credit', 5, const Duration(hours: 5));
    seedOrder({'Butter Cookies': 4, 'Elaichi Rusk': 3}, 'cod', 2, const Duration(hours: 2));

    final t = _Ticket(_next(), 'TKT-48213', 'Two cartons of chips arrived crushed', 'damaged', _orders.first.number)
      ..status = 'in_progress'
      ..created = DateTime.now().subtract(const Duration(days: 5))
      ..lastReply = DateTime.now().subtract(const Duration(days: 4));
    t.messages
      ..add(_Msg('You', 'Two cartons from my last delivery were damaged in transit.', false))
      ..add(_Msg('Asha', 'Sorry about that! We have logged it for a credit note. Could you confirm how many packs were affected?', true));
    _tickets.add(t);

    _notice('Out for delivery · ${_orders[1].number}', 'Our delivery partner is on the way.', 'order', orderId: _orders[1].id);
    _notice('Welcome to Royal Snacks', 'This is a demo — nothing here is real and everything resets when you close the app.', 'info');
  }
}

// ── internal model ───────────────────────────────────────────────────────────────
class _Cat {
  _Cat(this.id, this.name, this.icon);
  final int id;
  final String name, icon;
}

class _Var {
  _Var(this.id, this.label, this.packs, this.price, this.mrp, this.min, this.stock);
  final int id, packs, min;
  final String label;
  final double price, mrp;
  int stock;
}

class _Prod {
  _Prod(this.id, this.cat, this.name, this.brand, this.description, this.gst, this.accent, this.featured, this.variants);
  final int id;
  final _Cat cat;
  final String name, brand, description, accent;
  final double gst;
  final bool featured;
  final List<_Var> variants;
}

class _Deal {
  _Deal(this.id, this.title, this.description, this.type, this.value, this.buy, this.free, this.minQty, {this.prod, this.cat});
  final int id, buy, free, minQty;
  final String title, description, type;
  final double value;
  final _Prod? prod;
  final _Cat? cat;
  bool appliesTo(_Prod p) => prod != null ? prod!.id == p.id : (cat != null ? cat!.id == p.cat.id : true);
  String get summary => switch (type) {
        'percent' => '${value == value.roundToDouble() ? value.toInt() : value}% off',
        'flat' => '₹${value.round()} off per case',
        _ => 'Buy $buy, get $free free',
      };
}

class _Coupon {
  _Coupon(this.code, this.title, this.description, this.type, this.value, this.maxDiscount, this.minOrder, this.public);
  final String code, title, description, type;
  final double value, minOrder;
  final double? maxDiscount;
  final bool public;
  String get summary {
    final v = type == 'percent' ? '${value.round()}% off' : '₹${value.round()} off';
    return type == 'percent' && maxDiscount != null ? '$v (up to ₹${maxDiscount!.round()})' : v;
  }
}

class _Line {
  _Line(this.variantId, this.productId, this.name, this.label, this.accent, this.qty, this.free, this.unit, this.mrp, this.gross, this.deal, this.dealTitle, this.gst, this.minQty);
  final int variantId, productId, qty, free, unit, mrp, gross, deal, minQty;
  final String name, label, accent;
  final String? dealTitle;
  final double gst;
  int slab = 0, coupon = 0, taxable = 0, tax = 0, total = 0;
}

class _Quote {
  _Quote({
    required this.lines, required this.gross, required this.deal, required this.subtotal, required this.slabPct, required this.slab, required this.coupon, required this.taxable,
    required this.cgst, required this.sgst, required this.roundOff, required this.total, required this.cases, required this.free, required this.couponCode, required this.couponApplied,
    required this.couponMessage, required this.next, required this.errors, required this.canCheckout,
  });
  final List<_Line> lines;
  final int gross, deal, subtotal, slab, coupon, taxable, cgst, sgst, roundOff, total, cases, free;
  final double slabPct;
  final String? couponCode, couponMessage;
  final bool couponApplied, canCheckout;
  final Map<String, dynamic>? next;
  final List<Map<String, dynamic>> errors;
}

class _Order {
  _Order({required this.id, required this.number, required this.placedAt, required this.method, required this.q, required this.notes, required this.otp, required this.address, this.fixedStage});
  final int id;
  final String number, method, otp;
  final DateTime placedAt;
  final _Quote q;
  final String? notes;
  final Map<String, dynamic> address;
  final int? fixedStage;
  List<_Line> get lines => q.lines;
  String paymentStatus = 'pending';
  DateTime? progressStart;
  bool cancelled = false;
  DateTime? cancelledAt;
  String? cancelReason;

  /// Seconds after `progressStart` at which each stage is reached (a live order moves along by itself).
  static const _offsets = [0, 8, 20, 35, 50, 65, 90];

  int get reachedIndex {
    if (fixedStage != null) return fixedStage!;
    if (progressStart == null) return 0; // waiting for online payment
    final s = DateTime.now().difference(progressStart!).inSeconds;
    var idx = 0;
    for (var i = 0; i < _offsets.length; i++) {
      if (s >= _offsets[i]) idx = i;
    }
    return idx;
  }

  List<DateTime> get stageTimes => [
        for (var i = 0; i < _offsets.length; i++)
          fixedStage != null ? placedAt.add(Duration(minutes: 25 * i)) : (i == 0 ? placedAt : (progressStart ?? placedAt).add(Duration(seconds: _offsets[i]))),
      ];

  String get status {
    if (cancelled) return 'cancelled';
    final key = AppConfig.instance.stages[reachedIndex].key;
    if (key == 'delivered' && method == 'cod') paymentStatus = 'paid'; // cash collected on delivery
    return key;
  }
}

class _Msg {
  _Msg(this.who, this.body, this.staff) : at = DateTime.now();
  final String who, body;
  final bool staff;
  final DateTime at;
}

class _Ticket {
  _Ticket(this.id, this.number, this.subject, this.category, this.orderNumber)
      : created = DateTime.now(),
        lastReply = DateTime.now();
  final int id;
  final String number, subject, category;
  final String? orderNumber;
  String status = 'open';
  int? rating;
  DateTime created, lastReply;
  final List<_Msg> messages = [];
}
