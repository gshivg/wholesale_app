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
import '../shell_screen.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final user = ref.watch(authProvider).value;
    final shop = user?.shop;
    final active = ref.watch(ordersProvider('active'));
    final offers = ref.watch(offersProvider);
    final cs = context.cs;

    Future<void> refresh() async {
      await ref.read(authProvider.notifier).refresh();
      ref.invalidate(ordersProvider('active'));
      ref.invalidate(offersProvider);
      ref.invalidate(noticesProvider);
      ref.invalidate(cartProvider);
    }

    return Scaffold(
      appBar: AppBar(
        title: Row(children: [const BrandLogo(size: 32), const SizedBox(width: 10), Text(AppConfig.instance.brandName)]),
        actions: const [AppBarActions()],
      ),
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(padding: const EdgeInsets.only(bottom: 24), children: [
          RoyalHeader(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(l.greeting(user?.name.split(' ').first ?? ''), style: context.tt.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w600)),
                const SizedBox(height: 4),
                Text('${shop?.shopName ?? ''} · ${shop?.city ?? ''}', style: TextStyle(color: context.goldLight)),
                if (shop?.hubName != null) Text('Served by ${shop!.hubName}', style: TextStyle(color: Colors.white.withValues(alpha: .75), fontSize: 12)),
                const SizedBox(height: 16),
                Row(children: [
                  Expanded(child: _HeroStat(label: 'Credit available', value: inr(shop?.creditAvailable ?? 0), icon: Icons.credit_score_outlined)),
                  const SizedBox(width: 12),
                  Expanded(child: _HeroStat(label: 'Orders in progress', value: '${active.value?.length ?? 0}', icon: Icons.local_shipping_outlined)),
                ]),
              ]),
            ),
          ),
          if (shop != null && shop.status != 'approved')
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: Banner2(
                icon: shop.status == 'pending' ? Icons.hourglass_top : Icons.block,
                tone: shop.status == 'pending' ? BannerTone.warn : BannerTone.error,
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(shop.status == 'pending' ? l.pendingTitle : 'Account ${shop.status}', style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text(shop.status == 'pending' ? l.pendingBody : (shop.statusNote ?? 'Please contact support.')),
                ]),
              ),
            ),
          if (user?.canOrder == true) ...[
            SectionHeader('Live deliveries', actionLabel: 'All orders', onAction: () => context.go('/orders')),
            active.when(
              data: (orders) => orders.isEmpty
                  ? Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: Card(child: Padding(padding: const EdgeInsets.all(20), child: Row(children: [
                      Icon(Icons.inventory_2_outlined, color: cs.outline), const SizedBox(width: 12), const Expanded(child: Text('No orders in progress. Time to restock?')),
                      FilledButton.tonal(onPressed: () => context.go('/shop'), child: const Text('Shop')),
                    ]))))
                  : Column(children: [for (final o in orders.take(3)) _ActiveOrderCard(o)]),
              loading: () => const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
              error: (e, _) => ErrorState(message: errorText(e), onRetry: () => ref.invalidate(ordersProvider('active'))),
            ),
          ],
          SectionHeader('Offers for you', actionLabel: 'See all', onAction: () => context.push('/offers')),
          offers.when(
            data: (o) => SizedBox(
              height: 128,
              child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16), children: [
                for (final c in o.coupons) _CouponCard(c),
                for (final d in o.deals.take(4)) _DealCard(d),
              ]),
            ),
            loading: () => const SizedBox(height: 128, child: Center(child: CircularProgressIndicator())),
            error: (e, _) => const SizedBox.shrink(),
          ),
          offers.maybeWhen(
            data: (o) => o.slabs.isEmpty ? const SizedBox.shrink() : Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Banner2(icon: Icons.savings_outlined, child: Text('Volume discounts: ${o.slabs.map((s) => '${trimNum(s.percent)}% over ${inr(s.min)}').join(' · ')}')),
            ),
            orElse: () => const SizedBox.shrink(),
          ),
          const SectionHeader('Browse categories'),
          ref.watch(categoriesProvider).maybeWhen(
                data: (cats) => Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Wrap(spacing: 8, runSpacing: 8, children: [
                    for (final c in cats)
                      ActionChip(
                        avatar: Icon(iconFor(c.icon), size: 18),
                        label: Text(c.name),
                        onPressed: () {
                          ref.read(catalogQueryProvider.notifier).set(CatalogQuery(category: c.id));
                          context.go('/shop');
                        },
                      ),
                  ]),
                ),
                orElse: () => const SizedBox.shrink(),
              ),
        ]),
      ),
    );
  }
}

class _HeroStat extends StatelessWidget {
  const _HeroStat({required this.label, required this.value, required this.icon});
  final String label, value;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: Colors.white.withValues(alpha: .12), borderRadius: BorderRadius.circular(14), border: Border.all(color: Colors.white.withValues(alpha: .18))),
        child: Row(children: [
          Container(width: 36, height: 36, decoration: BoxDecoration(color: context.gold, borderRadius: BorderRadius.circular(10)), child: Icon(icon, size: 20, color: context.cs.onTertiary)),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16)),
            Text(label, style: TextStyle(color: Colors.white.withValues(alpha: .75), fontSize: 11)),
          ])),
        ]),
      );
}

class _ActiveOrderCard extends ConsumerWidget {
  const _ActiveOrderCard(this.o);
  final OrderSummary o;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(orderProvider(o.id));
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => context.push('/orders/${o.id}'),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text(o.number, style: context.tt.titleMedium)),
                StatusChip(o.status, label: o.statusLabel),
              ]),
              Text('${o.totalCases} cases · ${inr(o.total)}', style: TextStyle(color: context.cs.onSurfaceVariant)),
              const SizedBox(height: 12),
              detail.maybeWhen(
                data: (d) => Column(children: [
                  OrderTracker(stages: d.stages, status: d.summary.status),
                  if (d.deliveryOtp != null) Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Banner2(icon: Icons.pin_outlined, tone: BannerTone.warn, child: Text('Delivery OTP: ${d.deliveryOtp}  — share with the driver', style: const TextStyle(fontWeight: FontWeight.w600))),
                  ),
                ]),
                orElse: () => const SizedBox(height: 92, child: Center(child: LinearProgressIndicator())),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}

class _CouponCard extends StatelessWidget {
  const _CouponCard(this.c);
  final CouponInfo c;
  @override
  Widget build(BuildContext context) => Container(
        width: 240,
        margin: const EdgeInsets.only(right: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: context.cs.tertiaryContainer, borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFB8860B), width: 1.5)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [Icon(Icons.sell_outlined, size: 18, color: context.cs.onTertiaryContainer), const SizedBox(width: 6), Text(c.code, style: context.tt.titleMedium?.copyWith(letterSpacing: 1.5, color: context.cs.onTertiaryContainer))]),
          const SizedBox(height: 6),
          Text(c.summary, style: TextStyle(fontWeight: FontWeight.w700, color: context.cs.primary)),
          const Spacer(),
          Text(c.description ?? c.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: context.cs.onTertiaryContainer, fontSize: 12)),
        ]),
      );
}

class _DealCard extends StatelessWidget {
  const _DealCard(this.d);
  final DealInfo d;
  @override
  Widget build(BuildContext context) => Container(
        width: 240,
        margin: const EdgeInsets.only(right: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: context.cs.primaryContainer, borderRadius: BorderRadius.circular(16)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          StatusChip('active', label: 'DEAL'),
          const SizedBox(height: 6),
          Text(d.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.tt.titleSmall),
          const Spacer(),
          Text('${d.summary} · min ${d.minQty} case(s)', style: TextStyle(color: context.cs.onPrimaryContainer, fontSize: 12)),
        ]),
      );
}
