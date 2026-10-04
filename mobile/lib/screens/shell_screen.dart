import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../l10n/app_localizations.dart';
import '../providers/providers.dart';

/// Bottom navigation (Home, Shop, Orders, Support, Account) + shared app-bar actions.
class ShellScreen extends StatelessWidget {
  const ShellScreen({super.key, required this.shell});
  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      body: shell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: shell.currentIndex,
        onDestinationSelected: (i) => shell.goBranch(i, initialLocation: i == shell.currentIndex),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.home_outlined), selectedIcon: const Icon(Icons.home), label: l.navHome),
          NavigationDestination(icon: const Icon(Icons.storefront_outlined), selectedIcon: const Icon(Icons.storefront), label: l.navShop),
          NavigationDestination(icon: const Icon(Icons.receipt_long_outlined), selectedIcon: const Icon(Icons.receipt_long), label: l.navOrders),
          NavigationDestination(icon: const Icon(Icons.support_agent_outlined), selectedIcon: const Icon(Icons.support_agent), label: l.navSupport),
          NavigationDestination(icon: const Icon(Icons.account_circle_outlined), selectedIcon: const Icon(Icons.account_circle), label: l.navAccount),
        ],
      ),
    );
  }
}

/// Cart + notification icons with live badges, for use in AppBar.actions.
class AppBarActions extends ConsumerWidget {
  const AppBarActions({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cartCount = ref.watch(cartProvider).value?.itemCount ?? 0;
    final unread = ref.watch(noticesProvider).value?.unread ?? 0;
    Widget badge(Widget icon, int n) => n == 0 ? icon : Badge(label: Text(n > 99 ? '99+' : '$n'), backgroundColor: Theme.of(context).colorScheme.tertiary, textColor: Theme.of(context).colorScheme.onTertiary, child: icon);
    return Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(tooltip: 'Cart', onPressed: () => context.push('/cart'), icon: badge(const Icon(Icons.shopping_cart_outlined), cartCount)),
      IconButton(tooltip: 'Notifications', onPressed: () => context.push('/notifications'), icon: badge(const Icon(Icons.notifications_outlined), unread)),
    ]);
  }
}
