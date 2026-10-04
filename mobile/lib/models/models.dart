// Plain typed wrappers over the API's JSON. Money values are rupees (double).

double _d(dynamic v) => v == null ? 0 : (v is num ? v.toDouble() : double.tryParse('$v') ?? 0);
int _i(dynamic v) => v == null ? 0 : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);
DateTime? _t(dynamic v) => v == null ? null : DateTime.tryParse('$v');
List<Map<String, dynamic>> _list(dynamic v) => [for (final e in (v as List? ?? const [])) Map<String, dynamic>.from(e as Map)];

class Shop {
  Shop.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        shopName = j['shop_name'] as String,
        shopType = j['shop_type'] as String? ?? '',
        gstin = j['gstin'] as String?,
        pan = j['pan'] as String?,
        addressLine1 = j['address_line1'] as String? ?? '',
        addressLine2 = j['address_line2'] as String?,
        landmark = j['landmark'] as String?,
        city = j['city'] as String? ?? '',
        district = j['district'] as String? ?? '',
        pincode = j['pincode'] as String? ?? '',
        photoUrl = j['photo_url'] as String?,
        status = j['status'] as String? ?? 'pending',
        statusNote = j['status_note'] as String?,
        creditLimit = _d(j['credit_limit']),
        creditUsed = _d(j['credit_used']),
        creditAvailable = _d(j['credit_available']),
        hubName = (j['hub'] as Map?)?['name'] as String?;
  final int id;
  final String shopName, shopType, addressLine1, city, district, pincode, status;
  final String? gstin, pan, addressLine2, landmark, photoUrl, statusNote, hubName;
  final double creditLimit, creditUsed, creditAvailable;
}

class AppUser {
  AppUser.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        name = j['name'] as String,
        phone = j['phone'] as String,
        email = j['email'] as String?,
        locale = j['locale'] as String? ?? 'en',
        canOrder = j['can_order'] == true,
        shop = j['shop'] == null ? null : Shop.fromJson(Map<String, dynamic>.from(j['shop'] as Map));
  final int id;
  final String name, phone, locale;
  final String? email;
  final bool canOrder;
  final Shop? shop;
  bool get isApproved => shop?.status == 'approved';
}

class Category {
  Category.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        name = j['name'] as String,
        icon = j['icon'] as String?,
        productCount = _i(j['product_count']);
  final int id, productCount;
  final String name;
  final String? icon;
}

class Variant {
  Variant.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        label = j['label'] as String,
        packsPerCase = _i(j['packs_per_case']),
        price = _d(j['price']),
        mrp = _d(j['mrp']),
        packPrice = _d(j['pack_price']),
        minQty = _i(j['min_qty']),
        maxQty = _i(j['max_qty']),
        available = j['available'] == null ? null : _i(j['available']),
        inStock = j['in_stock'] != false;
  final int id, packsPerCase, minQty, maxQty;
  final String label;
  final double price, mrp, packPrice;
  final int? available;
  final bool inStock;
}

class DealInfo {
  DealInfo.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        title = j['title'] as String,
        description = j['description'] as String?,
        summary = j['summary'] as String? ?? '',
        type = j['type'] as String? ?? '',
        minQty = _i(j['min_qty']),
        endsAt = _t(j['ends_at']);
  final int id, minQty;
  final String title, summary, type;
  final String? description;
  final DateTime? endsAt;
}

class Product {
  Product.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        name = j['name'] as String,
        brand = j['brand'] as String?,
        description = j['description'] as String?,
        categoryName = (j['category'] as Map)['name'] as String,
        categoryIcon = (j['category'] as Map)['icon'] as String?,
        imageUrl = j['image_url'] as String?,
        accent = j['accent'] as String?,
        gstRate = _d(j['gst_rate']),
        isFeatured = j['is_featured'] == true,
        variants = [for (final v in _list(j['variants'])) Variant.fromJson(v)],
        deals = [for (final d in _list(j['deals'])) DealInfo.fromJson(d)];
  final int id;
  final String name, categoryName;
  final String? brand, description, categoryIcon, imageUrl, accent;
  final double gstRate;
  final bool isFeatured;
  final List<Variant> variants;
  final List<DealInfo> deals;
}

class CartLine {
  CartLine.fromJson(Map<String, dynamic> j)
      : variantId = _i(j['variant_id']),
        productId = _i(j['product_id']),
        name = j['name'] as String,
        label = j['label'] as String,
        accent = j['accent'] as String?,
        quantity = _i(j['quantity']),
        freeQuantity = _i(j['free_quantity']),
        unitPrice = _d(j['unit_price']),
        gross = _d(j['gross']),
        dealDiscount = _d(j['deal_discount']),
        dealTitle = j['deal_title'] as String?,
        gstRate = _d(j['gst_rate']),
        total = _d(j['total']),
        minQty = _i(j['min_qty']);
  final int variantId, productId, quantity, freeQuantity, minQty;
  final String name, label;
  final String? accent, dealTitle;
  final double unitPrice, gross, dealDiscount, gstRate, total;
}

class Totals {
  Totals.fromJson(Map<String, dynamic> j)
      : gross = _d(j['gross']),
        dealDiscount = _d(j['deal_discount']),
        subtotal = _d(j['subtotal']),
        slabPercent = _d(j['slab_percent']),
        slabDiscount = _d(j['slab_discount']),
        couponDiscount = _d(j['coupon_discount']),
        taxable = _d(j['taxable']),
        cgst = _d(j['cgst']),
        sgst = _d(j['sgst']),
        igst = _d(j['igst']),
        roundOff = _d(j['round_off']),
        total = _d(j['total']),
        totalCases = _i(j['total_cases']),
        freeCases = _i(j['free_cases']),
        savings = _d(j['savings']);
  final double gross, dealDiscount, subtotal, slabPercent, slabDiscount, couponDiscount, taxable, cgst, sgst, igst, roundOff, total, savings;
  final int totalCases, freeCases;
}

class CartIssue {
  CartIssue.fromJson(Map<String, dynamic> j)
      : code = j['code'] as String? ?? '',
        message = j['message'] as String? ?? '',
        variantId = j['variant_id'] == null ? null : _i(j['variant_id']);
  final String code, message;
  final int? variantId;
}

class Cart {
  Cart.fromJson(Map<String, dynamic> j)
      : lines = [for (final l in _list(j['lines'])) CartLine.fromJson(l)],
        totals = Totals.fromJson(Map<String, dynamic>.from(j['totals'] as Map)),
        couponCode = (j['coupon'] as Map)['applied'] == true ? (j['coupon'] as Map)['code'] as String? : null,
        couponDiscount = _d((j['coupon'] as Map)['discount']),
        nextSlab = j['next_slab'] == null ? null : Map<String, dynamic>.from(j['next_slab'] as Map),
        errors = [for (final e in _list(j['errors'])) CartIssue.fromJson(e)],
        canCheckout = j['can_checkout'] == true;
  final List<CartLine> lines;
  final Totals totals;
  final String? couponCode;
  final double couponDiscount;
  final Map<String, dynamic>? nextSlab;
  final List<CartIssue> errors;
  final bool canCheckout;
  int get itemCount => lines.length;
  int quantityOf(int variantId) => lines.where((l) => l.variantId == variantId).fold(0, (a, l) => a + l.quantity);
}

class OrderSummary {
  OrderSummary.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        number = j['number'] as String,
        status = j['status'] as String,
        statusLabel = j['status_label'] as String? ?? '',
        paymentMethod = j['payment_method'] as String? ?? '',
        paymentStatus = j['payment_status'] as String? ?? '',
        total = _d(j['total']),
        totalCases = _i(j['total_cases']),
        itemCount = _i(j['item_count']),
        placedAt = _t(j['placed_at']);
  final int id, totalCases, itemCount;
  final String number, status, statusLabel, paymentMethod, paymentStatus;
  final double total;
  final DateTime? placedAt;
  bool get isActive => !const {'delivered', 'cancelled', 'returned'}.contains(status);
}

class OrderStageInfo {
  OrderStageInfo.fromJson(Map<String, dynamic> j)
      : key = j['key'] as String,
        label = j['label'] as String,
        description = j['description'] as String? ?? '',
        reached = j['reached'] == true,
        at = _t(j['at']);
  final String key, label, description;
  final bool reached;
  final DateTime? at;
}

class OrderItem {
  OrderItem.fromJson(Map<String, dynamic> j)
      : name = j['name'] as String,
        label = j['label'] as String,
        quantity = _i(j['quantity']),
        freeQuantity = _i(j['free_quantity']),
        unitPrice = _d(j['unit_price']),
        gstRate = _d(j['gst_rate']),
        total = _d(j['total']);
  final String name, label;
  final int quantity, freeQuantity;
  final double unitPrice, gstRate, total;
}

class OrderEventInfo {
  OrderEventInfo.fromJson(Map<String, dynamic> j)
      : label = j['label'] as String,
        note = j['note'] as String?,
        at = _t(j['at']);
  final String label;
  final String? note;
  final DateTime? at;
}

class OrderDetail {
  OrderDetail.fromJson(Map<String, dynamic> j)
      : summary = OrderSummary.fromJson(j),
        stages = [for (final s in _list(j['stages'])) OrderStageInfo.fromJson(s)],
        items = [for (final s in _list(j['items'])) OrderItem.fromJson(s)],
        events = [for (final s in _list(j['events'])) OrderEventInfo.fromJson(s)],
        totals = Map<String, dynamic>.from(j['totals'] as Map),
        address = Map<String, dynamic>.from(j['address'] as Map),
        canCancel = j['can_cancel'] == true,
        canPay = j['can_pay'] == true,
        deliveryOtp = j['delivery_otp'] as String?,
        agentName = (j['agent'] as Map?)?['name'] as String?,
        agentPhone = (j['agent'] as Map?)?['phone'] as String?,
        hubName = (j['hub'] as Map)['name'] as String,
        invoiceAvailable = j['invoice_available'] == true,
        cancelReason = j['cancel_reason'] as String?,
        notes = j['notes'] as String?;
  final OrderSummary summary;
  final List<OrderStageInfo> stages;
  final List<OrderItem> items;
  final List<OrderEventInfo> events;
  final Map<String, dynamic> totals, address;
  final bool canCancel, canPay, invoiceAvailable;
  final String? deliveryOtp, agentName, agentPhone, cancelReason, notes;
  final String hubName;
  double total(String k) => _d(totals[k]);
}

class Ticket {
  Ticket.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        number = j['number'] as String,
        subject = j['subject'] as String,
        category = j['category'] as String,
        categoryLabel = j['category_label'] as String? ?? '',
        status = j['status'] as String,
        agentName = j['agent_name'] as String?,
        orderNumber = (j['order'] as Map?)?['number'] as String?,
        rating = j['rating'] == null ? null : _i(j['rating']),
        lastReplyAt = _t(j['last_reply_at']),
        messages = [for (final m in _list(j['messages'])) TicketMessage.fromJson(m)];
  final int id;
  final String number, subject, category, categoryLabel, status;
  final String? agentName, orderNumber;
  final int? rating;
  final DateTime? lastReplyAt;
  final List<TicketMessage> messages;
  bool get isOpen => status == 'open' || status == 'in_progress';
}

class TicketMessage {
  TicketMessage.fromJson(Map<String, dynamic> j)
      : body = j['body'] as String,
        fromStaff = j['from_staff'] == true,
        author = j['author'] as String? ?? '',
        attachments = [for (final a in (j['attachments'] as List? ?? const [])) '$a'],
        at = _t(j['at']);
  final String body, author;
  final bool fromStaff;
  final List<String> attachments;
  final DateTime? at;
}

class Notice {
  Notice.fromJson(Map<String, dynamic> j)
      : id = j['id'] as String,
        title = j['title'] as String? ?? '',
        body = j['body'] as String? ?? '',
        kind = j['kind'] as String? ?? 'info',
        orderId = j['order_id'] == null ? null : _i(j['order_id']),
        ticketId = j['ticket_id'] == null ? null : _i(j['ticket_id']),
        read = j['read'] == true,
        at = _t(j['at']);
  final String id, title, body, kind;
  final int? orderId, ticketId;
  final bool read;
  final DateTime? at;
}

class Offers {
  Offers.fromJson(Map<String, dynamic> j)
      : slabs = [for (final s in _list(j['slabs'])) (min: _d(s['min_subtotal']), percent: _d(s['percent']))],
        deals = [for (final d in _list(j['deals'])) DealInfo.fromJson(d)],
        coupons = [for (final c in _list(j['coupons'])) CouponInfo.fromJson(c)];
  final List<({double min, double percent})> slabs;
  final List<DealInfo> deals;
  final List<CouponInfo> coupons;
}

class CouponInfo {
  CouponInfo.fromJson(Map<String, dynamic> j)
      : code = j['code'] as String,
        title = j['title'] as String,
        description = j['description'] as String?,
        summary = j['summary'] as String? ?? '',
        minOrder = _d(j['min_order']),
        endsAt = _t(j['ends_at']);
  final String code, title, summary;
  final String? description;
  final double minOrder;
  final DateTime? endsAt;
}

class PaymentIntent {
  PaymentIntent.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        amount = _d(j['amount']);
  final int id;
  final double amount;
}
