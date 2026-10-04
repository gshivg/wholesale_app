import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/format.dart';
import '../../core/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../models/models.dart';
import '../../providers/providers.dart';
import '../../widgets/widgets.dart';
import '../shell_screen.dart';

class CatalogScreen extends ConsumerStatefulWidget {
  const CatalogScreen({super.key});
  @override
  ConsumerState<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends ConsumerState<CatalogScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 400) ref.read(catalogProvider.notifier).loadMore();
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final q = ref.watch(catalogQueryProvider);
    final cats = ref.watch(categoriesProvider);
    final catalog = ref.watch(catalogProvider);
    final canOrder = ref.watch(authProvider).value?.canOrder ?? false;
    if (_search.text != q.search && q.search.isEmpty) _search.clear();

    return Scaffold(
      appBar: AppBar(title: Text(l.navShop), actions: const [AppBarActions()]),
      body: Column(children: [
        Container(
          color: context.cs.primary,
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 14),
          child: SearchBar(
            controller: _search,
            hintText: l.search,
            leading: const Icon(Icons.search),
            elevation: const WidgetStatePropertyAll(0),
            trailing: [if (q.search.isNotEmpty) IconButton(icon: const Icon(Icons.close), onPressed: () { _search.clear(); ref.read(catalogQueryProvider.notifier).set(q.copyWith(search: '')); })],
            onSubmitted: (v) => ref.read(catalogQueryProvider.notifier).set(q.copyWith(search: v)),
          ),
        ),
        SizedBox(
          height: 56,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10), children: [
            Padding(padding: const EdgeInsets.only(right: 8), child: ChoiceChip(label: const Text('All'), selected: q.category == null, onSelected: (_) => ref.read(catalogQueryProvider.notifier).set(q.copyWith(clearCategory: true)))),
            for (final c in cats.value ?? const <Category>[])
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(avatar: Icon(iconFor(c.icon), size: 18), label: Text(c.name), selected: q.category == c.id, onSelected: (_) => ref.read(catalogQueryProvider.notifier).set(q.copyWith(category: c.id))),
              ),
          ]),
        ),
        if (!canOrder) const Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 8), child: Banner2(icon: Icons.hourglass_top, tone: BannerTone.warn, child: Text('Browsing only — ordering unlocks once your account is approved.'))),
        Expanded(
          child: AsyncBody<CatalogState>(
            value: catalog,
            onRetry: () => ref.invalidate(catalogProvider),
            builder: (s) => s.items.isEmpty
                ? const EmptyState(icon: Icons.search_off, title: 'No products found', subtitle: 'Try a different search or category.')
                : RefreshIndicator(
                    onRefresh: () async => ref.invalidate(catalogProvider),
                    child: ListView.separated(
                      controller: _scroll,
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                      itemCount: s.items.length + (s.hasMore ? 1 : 0),
                      separatorBuilder: (_, _) => const SizedBox(height: 12),
                      itemBuilder: (_, i) => i >= s.items.length ? const Center(child: Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator())) : ProductCard(s.items[i], canOrder: canOrder),
                    ),
                  ),
          ),
        ),
      ]),
    );
  }
}

class ProductCard extends ConsumerStatefulWidget {
  const ProductCard(this.p, {super.key, required this.canOrder});
  final Product p;
  final bool canOrder;
  @override
  ConsumerState<ProductCard> createState() => _ProductCardState();
}

class _ProductCardState extends ConsumerState<ProductCard> {
  late final Map<int, int> _qty = {for (final v in widget.p.variants) v.id: v.minQty};
  final Set<int> _busy = {};

  Future<void> _add(Variant v) async {
    setState(() => _busy.add(v.id));
    try {
      final inCart = ref.read(cartProvider).value?.quantityOf(v.id) ?? 0;
      await ref.read(cartProvider.notifier).setQty(v.id, (inCart + _qty[v.id]!).clamp(v.minQty, v.maxQty));
      if (mounted) showSnack(context, '${widget.p.name} added to cart');
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(v.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.p;
    final l = AppLocalizations.of(context);
    final cart = ref.watch(cartProvider).value;
    final cs = context.cs;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Padding(padding: const EdgeInsets.all(12), child: ProductArt(accent: p.accent, icon: iconFor(p.categoryIcon), size: 72, radius: 16)),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(0, 12, 12, 0),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(p.name, style: context.tt.titleMedium),
                Text('${p.brand ?? ''} · GST ${trimNum(p.gstRate)}%', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
                if (p.deals.isNotEmpty) Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Wrap(spacing: 6, runSpacing: 4, children: [for (final d in p.deals.take(2)) _DealBadge(d.summary)]),
                ),
              ]),
            ),
          ),
        ]),
        for (final v in p.variants)
          Container(
            padding: const EdgeInsets.fromLTRB(16, 10, 12, 10),
            decoration: BoxDecoration(border: Border(top: BorderSide(color: cs.outlineVariant))),
            child: Row(children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(v.label, style: context.tt.titleSmall),
                Row(children: [
                  Text(inr(v.price), style: context.tt.titleMedium?.copyWith(color: cs.primary, fontWeight: FontWeight.w700)),
                  const SizedBox(width: 6),
                  Text(inr(v.mrp), style: TextStyle(decoration: TextDecoration.lineThrough, color: cs.onSurfaceVariant, fontSize: 12)),
                  Text(' / case', style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
                ]),
                Text(
                  'Min ${v.minQty} · ${v.inStock ? '${v.available ?? '—'} in stock' : 'Out of stock'}${(cart?.quantityOf(v.id) ?? 0) > 0 ? ' · ${cart!.quantityOf(v.id)} in cart' : ''}',
                  style: TextStyle(fontSize: 12, color: v.inStock ? const Color(0xFF1B6B3A) : cs.error),
                ),
              ])),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                QtyStepper(value: _qty[v.id]!, min: v.minQty, max: v.maxQty, onChanged: (n) => setState(() => _qty[v.id] = n)),
                const SizedBox(height: 6),
                FilledButton.icon(
                  style: FilledButton.styleFrom(minimumSize: const Size(0, 36), padding: const EdgeInsets.symmetric(horizontal: 16)),
                  onPressed: widget.canOrder && v.inStock && !_busy.contains(v.id) ? () => _add(v) : null,
                  icon: _busy.contains(v.id) ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.add_shopping_cart, size: 18),
                  label: Text(l.addToCart),
                ),
              ]),
            ]),
          ),
      ]),
    );
  }
}

class _DealBadge extends StatelessWidget {
  const _DealBadge(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: context.cs.tertiaryContainer, borderRadius: BorderRadius.circular(10)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.sell, size: 12, color: context.cs.onTertiaryContainer), const SizedBox(width: 4), Text(text, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: context.cs.onTertiaryContainer))]),
      );
}
