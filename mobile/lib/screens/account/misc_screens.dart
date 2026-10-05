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

class OffersScreen extends ConsumerWidget {
  const OffersScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final offers = ref.watch(offersProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.offers)),
      body: AsyncBody<Offers>(
        value: offers,
        onRetry: () => ref.invalidate(offersProvider),
        builder: (o) => ListView(padding: const EdgeInsets.all(16), children: [
          Text(l.volumeDiscounts, style: context.tt.titleLarge),
          const SizedBox(height: 8),
          Row(children: [
            for (final s in o.slabs)
              Expanded(child: Card(color: context.cs.primaryContainer, child: Padding(padding: const EdgeInsets.all(14), child: Column(children: [
                Text('${trimNum(s.percent)}%', style: context.tt.headlineMedium?.copyWith(color: context.cs.primary, fontWeight: FontWeight.w700)),
                Text('over ${inr(s.min)}', textAlign: TextAlign.center, style: TextStyle(color: context.cs.onPrimaryContainer, fontSize: 12)),
              ])))),
          ]),
          const SizedBox(height: 20),
          Text('Coupons', style: context.tt.titleLarge),
          const SizedBox(height: 4),
          Text('Apply a code in your cart. Private codes are shared by our sales team.', style: TextStyle(color: context.cs.onSurfaceVariant)),
          const SizedBox(height: 8),
          for (final c in o.coupons)
            Card(
              color: context.cs.tertiaryContainer,
              margin: const EdgeInsets.only(bottom: 10),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: const BorderSide(color: Color(0xFFB8860B), width: 1.5)),
              child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [Expanded(child: Text(c.code, style: context.tt.titleLarge?.copyWith(letterSpacing: 2, color: context.cs.onTertiaryContainer))), Text(c.summary, style: TextStyle(fontWeight: FontWeight.w700, color: context.cs.primary))]),
                Text(c.title, style: context.tt.titleSmall),
                if (c.description != null) Text(c.description!),
                Text('Min order ${inr(c.minOrder)}${c.endsAt != null ? ' · valid till ${fmtDate(c.endsAt)}' : ''}', style: TextStyle(color: context.cs.onTertiaryContainer, fontSize: 12)),
                const SizedBox(height: 8),
                FilledButton(
                  onPressed: () async {
                    try {
                      await ref.read(cartProvider.notifier).applyCoupon(c.code);
                      if (context.mounted) { showSnack(context, '${c.code} applied to your cart'); context.push('/cart'); }
                    } catch (e) {
                      if (context.mounted) showSnack(context, errorText(e), error: true);
                    }
                  },
                  child: const Text('Apply to cart'),
                ),
              ])),
            ),
          const SizedBox(height: 12),
          Text('Product deals', style: context.tt.titleLarge),
          const SizedBox(height: 8),
          if (o.deals.isEmpty) const Text('No product deals right now.'),
          for (final d in o.deals)
            Card(margin: const EdgeInsets.only(bottom: 10), child: ListTile(
              leading: CircleAvatar(backgroundColor: context.cs.tertiaryContainer, child: Icon(Icons.sell, color: context.cs.onTertiaryContainer)),
              title: Text(d.title), subtitle: Text('${d.description ?? d.summary}\nMin ${d.minQty} case(s)${d.endsAt != null ? ' · ends ${fmtDate(d.endsAt)}' : ''}'), isThreeLine: true,
              trailing: StatusChip('active', label: d.summary),
            )),
        ]),
      ),
    );
  }
}

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final feed = ref.watch(noticesProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.notifications), actions: [TextButton(onPressed: () => markNoticesRead(ref), child: const Text('Mark all read', style: TextStyle(color: Colors.white)))]),
      body: AsyncBody<NoticeFeed>(
        value: feed,
        onRetry: () => ref.invalidate(noticesProvider),
        builder: (f) => f.items.isEmpty
            ? const EmptyState(icon: Icons.notifications_off_outlined, title: "You're all caught up")
            : RefreshIndicator(
                onRefresh: () async => ref.invalidate(noticesProvider),
                child: ListView.separated(
                  itemCount: f.items.length,
                  separatorBuilder: (_, _) => const Divider(),
                  itemBuilder: (_, i) {
                    final n = f.items[i];
                    final icon = switch (n.kind) { 'order' => Icons.local_shipping_outlined, 'ticket' => Icons.support_agent_outlined, 'account' => Icons.badge_outlined, 'offer' => Icons.local_offer_outlined, _ => Icons.notifications_outlined };
                    return ListTile(
                      tileColor: n.read ? null : context.cs.primaryContainer.withValues(alpha: .5),
                      leading: CircleAvatar(backgroundColor: context.cs.primaryContainer, child: Icon(icon, color: context.cs.primary)),
                      title: Text(n.title, style: TextStyle(fontWeight: n.read ? FontWeight.w400 : FontWeight.w600)),
                      subtitle: Text(n.body),
                      trailing: Text(n.at == null ? '' : timeAgo(n.at!), style: TextStyle(color: context.cs.onSurfaceVariant, fontSize: 12)),
                      onTap: () async {
                        await markNoticesRead(ref, id: n.id);
                        if (!context.mounted) return;
                        if (n.orderId != null) context.push('/orders/${n.orderId}');
                        if (n.ticketId != null) context.push('/support/${n.ticketId}');
                      },
                    );
                  },
                ),
              ),
      ),
    );
  }
}

class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final user = ref.watch(authProvider).value;
    final shop = user?.shop;
    final cs = context.cs;
    return Scaffold(
      appBar: AppBar(title: Text(l.profile)),
      body: user == null || shop == null
          ? const SizedBox.shrink()
          : ListView(children: [
              RoyalHeader(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 24, 20, 28),
                  child: Row(children: [
                    CircleAvatar(radius: 32, backgroundColor: context.gold, child: Text(shop.shopName.substring(0, 1), style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: cs.primary))),
                    const SizedBox(width: 16),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(shop.shopName, style: context.tt.titleLarge?.copyWith(color: Colors.white, fontWeight: FontWeight.w600)),
                      Text('${user.name} · +91 ${user.phone}', style: TextStyle(color: context.goldLight)),
                      const SizedBox(height: 6),
                      StatusChip(shop.status),
                    ])),
                  ]),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(16),
                child: Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(children: [
                  _row('Shop type', shop.shopType),
                  _row('PAN', shop.pan ?? '—'),
                  _row('GSTIN', shop.gstin ?? 'Unregistered'),
                  _row('Address', '${shop.addressLine1}, ${shop.city} ${shop.pincode}'),
                  _row('Serving hub', shop.hubName ?? '—'),
                  const Divider(height: 24),
                  _row('Credit limit', inr(shop.creditLimit)),
                  _row('Credit used', inr(shop.creditUsed)),
                  _row('Available', inr(shop.creditAvailable), bold: true),
                ]))),
              ),
              if (kDemo) const Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 8), child: Banner2(icon: Icons.science_outlined, tone: BannerTone.warn, child: Text('Demo mode: built-in sample data, no server. Everything resets when the app is closed.'))),
              ListTile(leading: const Icon(Icons.edit_outlined), title: const Text('Edit contact & address'), trailing: const Icon(Icons.chevron_right), onTap: () => _editProfile(context, ref)),
              ListTile(leading: const Icon(Icons.local_offer_outlined), title: Text(l.offers), trailing: const Icon(Icons.chevron_right), onTap: () => context.push('/offers')),
              ListTile(leading: const Icon(Icons.lock_reset_outlined), title: const Text('Change password'), trailing: const Icon(Icons.chevron_right), onTap: () => _changePassword(context, ref)),
              if (AppConfig.instance.localeOptions.length > 1)
                ListTile(
                  leading: const Icon(Icons.language),
                  title: Text(l.language),
                  trailing: DropdownButton<String>(
                    value: ref.watch(localeProvider),
                    underline: const SizedBox.shrink(),
                    items: [for (final o in AppConfig.instance.localeOptions) DropdownMenuItem(value: o.code, child: Text(o.name))],
                    onChanged: (v) => ref.read(localeProvider.notifier).set(v!),
                  ),
                )
              else
                ListTile(leading: const Icon(Icons.language), title: Text(l.language), subtitle: Text(AppConfig.instance.localeOptions.first.name)),
              ListTile(leading: const Icon(Icons.support_agent_outlined), title: const Text('Contact us'), subtitle: Text('${AppConfig.instance.supportEmail}\n${AppConfig.instance.supportPhone}'), isThreeLine: true),
              const Divider(),
              ListTile(leading: Icon(Icons.logout, color: cs.error), title: Text(l.logout, style: TextStyle(color: cs.error)), onTap: () => ref.read(authProvider.notifier).logout()),
              const SizedBox(height: 24),
            ]),
    );
  }

  Widget _row(String k, String v, {bool bold = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 110, child: Text(k, style: const TextStyle(color: Colors.black54))),
          Expanded(child: Text(v, textAlign: TextAlign.right, style: TextStyle(fontWeight: bold ? FontWeight.w700 : FontWeight.w400))),
        ]),
      );

  Future<void> _editProfile(BuildContext context, WidgetRef ref) async {
    final user = ref.read(authProvider).value!;
    final shop = user.shop!;
    final name = TextEditingController(text: user.name);
    final email = TextEditingController(text: user.email ?? '');
    final l1 = TextEditingController(text: shop.addressLine1);
    final city = TextEditingController(text: shop.city);
    final pin = TextEditingController(text: shop.pincode);
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(c).viewInsets.bottom + 20),
        child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('Edit contact & address', style: Theme.of(c).textTheme.titleLarge),
          const SizedBox(height: 16),
          TextField(controller: name, decoration: const InputDecoration(labelText: 'Your name')),
          const SizedBox(height: 12),
          TextField(controller: email, decoration: const InputDecoration(labelText: 'Email')),
          const SizedBox(height: 12),
          TextField(controller: l1, decoration: const InputDecoration(labelText: 'Address line 1')),
          const SizedBox(height: 12),
          Row(children: [Expanded(child: TextField(controller: pin, keyboardType: TextInputType.number, maxLength: 6, decoration: const InputDecoration(labelText: 'PIN code'))), const SizedBox(width: 12), Expanded(child: TextField(controller: city, decoration: const InputDecoration(labelText: 'City')))]),
          FilledButton(
            onPressed: () async {
              try {
                await ref.read(authProvider.notifier).updateProfile({'name': name.text.trim(), 'email': email.text.trim(), 'address_line1': l1.text.trim(), 'city': city.text.trim(), 'pincode': pin.text.trim()});
                if (c.mounted) Navigator.pop(c);
                if (context.mounted) showSnack(context, 'Profile saved');
              } catch (e) {
                if (c.mounted) showSnack(c, errorText(e), error: true);
              }
            },
            child: const Text('Save'),
          ),
        ])),
      ),
    );
  }

  Future<void> _changePassword(BuildContext context, WidgetRef ref) async {
    final cur = TextEditingController(), next = TextEditingController();
    await showDialog<void>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Change password'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: cur, obscureText: true, decoration: const InputDecoration(labelText: 'Current password')),
          const SizedBox(height: 12),
          TextField(controller: next, obscureText: true, decoration: const InputDecoration(labelText: 'New password (min 8)')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancel')),
          FilledButton(
            onPressed: () async {
              try {
                await ref.read(authProvider.notifier).changePassword(cur.text, next.text);
                if (c.mounted) Navigator.pop(c);
                if (context.mounted) showSnack(context, 'Password changed');
              } catch (e) {
                if (c.mounted) showSnack(c, errorText(e), error: true);
              }
            },
            child: const Text('Update'),
          ),
        ],
      ),
    );
  }
}
