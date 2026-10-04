import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/app_config.dart';
import '../../core/format.dart';
import '../../core/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../models/models.dart';
import '../../providers/providers.dart';
import '../../widgets/widgets.dart';
import 'payment_sheet.dart';

class CartScreen extends ConsumerStatefulWidget {
  const CartScreen({super.key});
  @override
  ConsumerState<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends ConsumerState<CartScreen> {
  final _coupon = TextEditingController();
  final _notes = TextEditingController();
  String _payment = 'cod';
  bool _busy = false;

  Future<void> _guard(Future<void> Function() f) async {
    try {
      await f();
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    }
  }

  Future<void> _place(Cart cart) async {
    setState(() => _busy = true);
    try {
      final r = await ref.read(orderActionsProvider).place(_payment, _notes.text.trim());
      if (!mounted) return;
      context.go('/orders/${r.id}');
      if (r.payment != null) {
        // Online order: open the sandbox payment sheet right away.
        await Future<void>.delayed(const Duration(milliseconds: 300));
        if (mounted) await showPaymentSheet(context, orderId: r.id, intent: r.payment!);
      } else if (mounted) {
        showSnack(context, 'Order placed!');
      }
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final cartAsync = ref.watch(cartProvider);
    final user = ref.watch(authProvider).value;
    final offers = ref.watch(offersProvider).value;
    final cs = context.cs;

    return Scaffold(
      appBar: AppBar(title: Text(l.cart), actions: [
        if ((cartAsync.value?.lines ?? const []).isNotEmpty) TextButton(onPressed: () => _guard(() => ref.read(cartProvider.notifier).clear()), child: const Text('Clear', style: TextStyle(color: Colors.white))),
      ]),
      body: AsyncBody<Cart?>(
        value: cartAsync,
        onRetry: () => ref.invalidate(cartProvider),
        builder: (cart) {
          if (cart == null) return const EmptyState(icon: Icons.lock_outline, title: 'Ordering is locked', subtitle: 'Your account is awaiting approval.');
          if (cart.lines.isEmpty) {
            return EmptyState(icon: Icons.shopping_cart_outlined, title: l.cartEmpty, action: FilledButton(onPressed: () => context.go('/shop'), child: const Text('Browse products')));
          }
          final t = cart.totals;
          final credit = user?.shop;
          return ListView(padding: const EdgeInsets.all(16), children: [
            Card(
              child: Column(children: [
                for (var i = 0; i < cart.lines.length; i++) ...[
                  if (i > 0) const Divider(),
                  _CartLineTile(cart.lines[i], issues: cart.errors.where((e) => e.variantId == cart.lines[i].variantId).toList()),
                ],
              ]),
            ),
            const SizedBox(height: 12),
            if (cart.nextSlab != null)
              _SlabProgress(subtotal: t.subtotal, next: cart.nextSlab!)
            else if (t.slabPercent > 0)
              Banner2(icon: Icons.verified_outlined, tone: BannerTone.success, child: Text('Top volume discount unlocked: ${trimNum(t.slabPercent)}% off!', style: const TextStyle(fontWeight: FontWeight.w600))),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Coupon', style: context.tt.titleMedium),
                  const SizedBox(height: 8),
                  if (cart.couponCode != null)
                    Row(children: [
                      Chip(avatar: const Icon(Icons.sell_outlined, size: 16), label: Text(cart.couponCode!), backgroundColor: cs.tertiaryContainer),
                      const SizedBox(width: 8),
                      Expanded(child: Text('saves ${inr(cart.couponDiscount)}', style: const TextStyle(color: Color(0xFF1B6B3A)))),
                      TextButton(onPressed: () => _guard(() => ref.read(cartProvider.notifier).removeCoupon()), child: Text(l.remove)),
                    ])
                  else ...[
                    Row(children: [
                      Expanded(child: TextField(controller: _coupon, textCapitalization: TextCapitalization.characters, decoration: InputDecoration(labelText: l.couponCode, isDense: true))),
                      const SizedBox(width: 8),
                      FilledButton.tonal(
                        onPressed: () => _guard(() async {
                          await ref.read(cartProvider.notifier).applyCoupon(_coupon.text.trim());
                          _coupon.clear();
                          if (context.mounted) showSnack(context, 'Coupon applied');
                        }),
                        child: Text(l.apply),
                      ),
                    ]),
                    if (offers != null && offers.coupons.isNotEmpty) Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Wrap(spacing: 8, runSpacing: 4, children: [
                        for (final c in offers.coupons)
                          ActionChip(avatar: const Icon(Icons.sell_outlined, size: 16), label: Text('${c.code} · ${c.summary}'), onPressed: () => _guard(() => ref.read(cartProvider.notifier).applyCoupon(c.code))),
                      ]),
                    ),
                  ],
                ]),
              ),
            ),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(children: [
                  Align(alignment: Alignment.centerLeft, child: Text(l.orderSummary, style: context.tt.titleMedium)),
                  const SizedBox(height: 8),
                  _kv('Items (${t.totalCases} cases)', inr(t.gross)),
                  if (t.dealDiscount > 0) _kv('Deals', '−${inr(t.dealDiscount)}', good: true),
                  if (t.slabDiscount > 0) _kv('Volume discount (${trimNum(t.slabPercent)}%)', '−${inr(t.slabDiscount)}', good: true),
                  if (t.couponDiscount > 0) _kv('Coupon ${cart.couponCode}', '−${inr(t.couponDiscount)}', good: true),
                  _kv('Taxable value', inr(t.taxable)),
                  if (t.cgst > 0) ...[_kv('CGST', inr(t.cgst), muted: true), _kv('SGST', inr(t.sgst), muted: true)],
                  if (t.igst > 0) _kv('IGST', inr(t.igst), muted: true),
                  if (t.roundOff != 0) _kv('Round off', inr(t.roundOff), muted: true),
                  const Divider(height: 24),
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text(l.total, style: context.tt.titleMedium),
                    Text(inr(t.total), style: context.tt.titleLarge?.copyWith(color: cs.primary, fontWeight: FontWeight.w700)),
                  ]),
                  if (t.savings > 0) Padding(padding: const EdgeInsets.only(top: 8), child: Text('You save ${inr(t.savings)} 🎉', style: const TextStyle(color: Color(0xFF1B6B3A), fontWeight: FontWeight.w600))),
                ]),
              ),
            ),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(l.payment, style: context.tt.titleMedium),
                  const SizedBox(height: 4),
                  RadioGroup<String>(
                    groupValue: _payment,
                    onChanged: (v) => setState(() => _payment = v!),
                    child: Column(children: [
                      RadioListTile<String>(contentPadding: EdgeInsets.zero, value: 'cod', title: Text(l.cashOnDelivery), subtitle: const Text('Pay our driver when you receive the goods')),
                      RadioListTile<String>(contentPadding: EdgeInsets.zero, value: 'online', title: Text(l.payOnline), subtitle: const Text('Card or UPI — test payments only')),
                      RadioListTile<String>(
                        contentPadding: EdgeInsets.zero, value: 'credit', enabled: (credit?.creditLimit ?? 0) > 0,
                        title: Text(l.payLater),
                        subtitle: Text((credit?.creditLimit ?? 0) > 0 ? 'Available: ${inr(credit!.creditAvailable)}' : 'Not enabled for your account'),
                      ),
                    ]),
                  ),
                  TextField(controller: _notes, maxLines: 2, decoration: const InputDecoration(labelText: 'Delivery notes (optional)')),
                ]),
              ),
            ),
            const SizedBox(height: 12),
            for (final e in cart.errors.where((e) => e.variantId == null)) Padding(padding: const EdgeInsets.only(bottom: 8), child: Banner2(icon: Icons.error_outline, tone: BannerTone.error, child: Text(e.message))),
            FilledButton.icon(
              style: FilledButton.styleFrom(backgroundColor: context.gold, foregroundColor: cs.onTertiary, minimumSize: const Size.fromHeight(54)),
              onPressed: cart.canCheckout && !_busy ? () => _place(cart) : null,
              icon: _busy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.lock_outline),
              label: Text('${l.placeOrder} · ${inr(t.total)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            ),
            const SizedBox(height: 8),
            Center(child: Text(l.minOrderHint(inr(AppConfig.instance.minOrderValue), AppConfig.instance.minTotalCases), style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12))),
            const SizedBox(height: 24),
          ]);
        },
      ),
    );
  }

  Widget _kv(String k, String v, {bool good = false, bool muted = false}) {
    final color = good ? const Color(0xFF1B6B3A) : (muted ? context.cs.onSurfaceVariant : null);
    return Padding(padding: const EdgeInsets.symmetric(vertical: 3), child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text(k, style: TextStyle(color: color)), Text(v, style: TextStyle(color: color))]));
  }
}

class _SlabProgress extends StatelessWidget {
  const _SlabProgress({required this.subtotal, required this.next});
  final double subtotal;
  final Map<String, dynamic> next;
  @override
  Widget build(BuildContext context) {
    final min = (next['min_subtotal'] as num).toDouble();
    final remaining = (next['remaining'] as num).toDouble();
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: context.cs.tertiaryContainer, borderRadius: BorderRadius.circular(12)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [Icon(Icons.savings_outlined, color: context.cs.onTertiaryContainer), const SizedBox(width: 10), Expanded(child: Text('Add ${inr(remaining)} more to unlock ${trimNum(next['percent'] as num)}% off', style: TextStyle(fontWeight: FontWeight.w600, color: context.cs.onTertiaryContainer)))]),
        const SizedBox(height: 10),
        ClipRRect(borderRadius: BorderRadius.circular(3), child: LinearProgressIndicator(value: (subtotal / min).clamp(0, 1), minHeight: 6, color: context.cs.primary, backgroundColor: Colors.white54)),
      ]),
    );
  }
}

class _CartLineTile extends ConsumerWidget {
  const _CartLineTile(this.l, {required this.issues});
  final CartLine l;
  final List<CartIssue> issues;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = context.cs;
    Future<void> set(int q) async {
      try {
        await ref.read(cartProvider.notifier).setQty(l.variantId, q);
      } catch (e) {
        if (context.mounted) showSnack(context, errorText(e), error: true);
      }
    }

    return Padding(
      padding: const EdgeInsets.all(12),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        ProductArt(accent: l.accent, icon: Icons.cookie_outlined, size: 52),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(l.name, style: context.tt.titleSmall),
          Text('${l.label} · ${inr(l.unitPrice)}/case · GST ${trimNum(l.gstRate)}%', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
          if (l.dealDiscount > 0) Text('${l.dealTitle} · −${inr(l.dealDiscount)}', style: const TextStyle(color: Color(0xFF9A7200), fontSize: 12, fontWeight: FontWeight.w600)),
          if (l.freeQuantity > 0) Text('🎁 ${l.freeQuantity} free case(s) — ${l.dealTitle}', style: const TextStyle(color: Color(0xFF9A7200), fontSize: 12, fontWeight: FontWeight.w600)),
          for (final i in issues) Text(i.message, style: TextStyle(color: cs.error, fontSize: 12)),
          const SizedBox(height: 8),
          Row(children: [
            QtyStepper(value: l.quantity, min: 1, allowZero: true, onChanged: set),
            const Spacer(),
            Text(inr(l.gross - l.dealDiscount), style: context.tt.titleSmall),
          ]),
        ])),
      ]),
    );
  }
}
