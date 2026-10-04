import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'providers/providers.dart';
import 'screens/account/misc_screens.dart';
import 'screens/auth/forgot_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/cart/cart_screen.dart';
import 'screens/catalog/catalog_screen.dart';
import 'screens/home/home_screen.dart';
import 'screens/orders/order_detail_screen.dart';
import 'screens/orders/orders_screen.dart';
import 'screens/shell_screen.dart';
import 'screens/support/support_screens.dart';

/// Refresh the router whenever the auth state changes.
class _AuthListenable extends ChangeNotifier {
  _AuthListenable(Ref ref) {
    ref.listen(authProvider, (_, _) => notifyListeners());
  }
}

final routerProvider = Provider<GoRouter>((ref) {
  final listenable = _AuthListenable(ref);
  const authPaths = {'/login', '/register', '/forgot'};

  return GoRouter(
    initialLocation: '/home',
    refreshListenable: listenable,
    redirect: (context, state) {
      final auth = ref.read(authProvider);
      if (auth.isLoading) return '/splash';
      final loggedIn = auth.value != null;
      final loc = state.matchedLocation;
      if (!loggedIn) return authPaths.contains(loc) ? null : '/login';
      if (authPaths.contains(loc) || loc == '/splash') return '/home';
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const Scaffold(body: Center(child: CircularProgressIndicator()))),
      GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/register', builder: (_, s) => RegisterScreen(verifyPhone: s.extra as String?)),
      GoRoute(path: '/forgot', builder: (_, _) => const ForgotScreen()),
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => ShellScreen(shell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: '/home', builder: (_, _) => const HomeScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/shop', builder: (_, _) => const CatalogScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/orders', builder: (_, _) => const OrdersScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/support', builder: (_, _) => const SupportScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/account', builder: (_, _) => const AccountScreen())]),
        ],
      ),
      GoRoute(path: '/cart', builder: (_, _) => const CartScreen()),
      GoRoute(path: '/offers', builder: (_, _) => const OffersScreen()),
      GoRoute(path: '/notifications', builder: (_, _) => const NotificationsScreen()),
      GoRoute(path: '/orders/:id', builder: (_, s) => OrderDetailScreen(id: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/support/new', builder: (_, s) => NewTicketScreen(orderId: int.tryParse(s.uri.queryParameters['order'] ?? ''))),
      GoRoute(path: '/support/:id', builder: (_, s) => TicketScreen(id: int.parse(s.pathParameters['id']!))),
    ],
  );
});
