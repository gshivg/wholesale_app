import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/format.dart';
import '../../core/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../models/models.dart';
import '../../providers/providers.dart';
import '../../widgets/widgets.dart';
import '../shell_screen.dart';

class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});
  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> {
  String _filter = 'all';

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final orders = ref.watch(ordersProvider(_filter));
    return Scaffold(
      appBar: AppBar(title: Text(l.myOrders), actions: const [AppBarActions()]),
      body: Column(children: [
        SizedBox(
          height: 56,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10), children: [
            for (final f in const {'all': 'All', 'active': 'In progress', 'delivered': 'Delivered'}.entries)
              Padding(padding: const EdgeInsets.only(right: 8), child: ChoiceChip(label: Text(f.value), selected: _filter == f.key, onSelected: (_) => setState(() => _filter = f.key))),
          ]),
        ),
        Expanded(
          child: AsyncBody<List<OrderSummary>>(
            value: orders,
            onRetry: () => ref.invalidate(ordersProvider(_filter)),
            builder: (list) => list.isEmpty
                ? EmptyState(icon: Icons.receipt_long_outlined, title: l.noOrders, action: FilledButton(onPressed: () => context.go('/shop'), child: const Text('Start shopping')))
                : RefreshIndicator(
                    onRefresh: () async => ref.invalidate(ordersProvider(_filter)),
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                      itemCount: list.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 10),
                      itemBuilder: (_, i) => _OrderTile(list[i]),
                    ),
                  ),
          ),
        ),
      ]),
    );
  }
}

class _OrderTile extends StatelessWidget {
  const _OrderTile(this.o);
  final OrderSummary o;
  @override
  Widget build(BuildContext context) => Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => context.push('/orders/${o.id}'),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(children: [
              Container(width: 44, height: 44, decoration: BoxDecoration(color: context.cs.primaryContainer, borderRadius: BorderRadius.circular(22)), child: Icon(o.isActive ? Icons.local_shipping_outlined : Icons.receipt_long_outlined, color: context.cs.primary)),
              const SizedBox(width: 14),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(o.number, style: context.tt.titleSmall),
                Text('${fmtDate(o.placedAt)} · ${o.itemCount} items · ${o.totalCases} cases', style: TextStyle(color: context.cs.onSurfaceVariant, fontSize: 12)),
                Text(o.paymentMethod.toUpperCase(), style: TextStyle(color: context.cs.onSurfaceVariant, fontSize: 11)),
              ])),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [Text(inr(o.total), style: context.tt.titleSmall), const SizedBox(height: 6), StatusChip(o.status, label: o.statusLabel)]),
            ]),
          ),
        ),
      );
}
