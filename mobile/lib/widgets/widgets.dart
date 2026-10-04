import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api.dart';
import '../core/app_config.dart';
import '../core/format.dart';
import '../core/theme.dart';
import '../models/models.dart';

void showSnack(BuildContext context, String message, {bool error = false}) {
  final cs = Theme.of(context).colorScheme;
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Row(children: [
        Icon(error ? Icons.error_outline : Icons.check_circle_outline, color: Colors.white, size: 20),
        const SizedBox(width: 12),
        Expanded(child: Text(message)),
      ]),
      backgroundColor: error ? cs.error : const Color(0xFF303034),
    ));
}

String errorText(Object e) => e is ApiException ? e.message : 'Something went wrong. Please try again.';

/// Material icon names sent by the server (category icons) → Flutter icons.
IconData iconFor(String? name) => switch (name) {
      'bubble_chart' => Icons.bubble_chart,
      'restaurant' => Icons.restaurant,
      'cookie' => Icons.cookie_outlined,
      'ramen_dining' => Icons.ramen_dining,
      'local_drink' => Icons.local_drink,
      'icecream' => Icons.icecream,
      'sports_bar' => Icons.sports_bar,
      'bakery_dining' => Icons.bakery_dining,
      _ => Icons.category_outlined,
    };

Color accentColor(String? hex, Color fallback) {
  if (hex == null || !RegExp(r'^#[0-9A-Fa-f]{6}$').hasMatch(hex)) return fallback;
  return Color(int.parse('FF${hex.substring(1)}', radix: 16));
}

/// Renders an [AsyncValue] with consistent loading / error (with retry) states.
class AsyncBody<T> extends StatelessWidget {
  const AsyncBody({super.key, required this.value, required this.builder, this.onRetry});
  final AsyncValue<T> value;
  final Widget Function(T data) builder;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => value.when(
        data: builder,
        loading: () => const Center(child: Padding(padding: EdgeInsets.all(48), child: CircularProgressIndicator())),
        error: (e, _) => ErrorState(message: errorText(e), onRetry: onRetry),
      );
}

class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.cloud_off, size: 56, color: context.cs.outline),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            if (onRetry != null) ...[const SizedBox(height: 16), FilledButton.tonal(onPressed: onRetry, child: const Text('Retry'))],
          ]),
        ),
      );
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.title, this.subtitle, this.action});
  final IconData icon;
  final String title;
  final String? subtitle;
  final Widget? action;
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 72, color: context.cs.outlineVariant),
            const SizedBox(height: 12),
            Text(title, style: context.tt.titleMedium, textAlign: TextAlign.center),
            if (subtitle != null) ...[const SizedBox(height: 4), Text(subtitle!, textAlign: TextAlign.center, style: TextStyle(color: context.cs.onSurfaceVariant))],
            if (action != null) ...[const SizedBox(height: 16), action!],
          ]),
        ),
      );
}

class Money extends StatelessWidget {
  const Money(this.value, {super.key, this.style});
  final num value;
  final TextStyle? style;
  @override
  Widget build(BuildContext context) => Text(inr(value), style: style);
}

class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.actionLabel, this.onAction});
  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 8, 8),
        child: Row(children: [
          Expanded(child: Text(title, style: context.tt.titleLarge)),
          if (actionLabel != null) TextButton(onPressed: onAction, child: Text(actionLabel!)),
        ]),
      );
}

class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key, this.label});
  final String status;
  final String? label;

  static (Color, Color) colors(BuildContext c, String s) {
    final cs = c.cs;
    return switch (s) {
      'delivered' || 'resolved' || 'paid' || 'approved' => (const Color(0xFFBFF0CC), const Color(0xFF00210E)),
      'cancelled' || 'returned' || 'failed' || 'rejected' || 'suspended' => (cs.errorContainer, cs.onErrorContainer),
      'placed' || 'closed' || 'pending' => (cs.surfaceContainerHigh, cs.onSurfaceVariant),
      'dispatched' || 'out_for_delivery' || 'open' => (cs.tertiaryContainer, cs.onTertiaryContainer),
      _ => (cs.primaryContainer, cs.onPrimaryContainer),
    };
  }

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = colors(context, status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
      child: Text(label ?? statusLabelFallback(status), style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w500)),
    );
  }
}

class QtyStepper extends StatelessWidget {
  const QtyStepper({super.key, required this.value, required this.onChanged, this.min = 1, this.max = 999, this.allowZero = false});
  final int value, min, max;
  final bool allowZero;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return Container(
      height: 40,
      decoration: BoxDecoration(color: cs.surfaceContainerHigh, borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(
          visualDensity: VisualDensity.compact,
          icon: Icon(Icons.remove, color: cs.primary),
          onPressed: () => onChanged(value - 1 < min ? (allowZero ? 0 : min) : value - 1),
        ),
        SizedBox(width: 32, child: Text('$value', textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16))),
        IconButton(visualDensity: VisualDensity.compact, icon: Icon(Icons.add, color: cs.primary), onPressed: value >= max ? null : () => onChanged(value + 1)),
      ]),
    );
  }
}

/// Gradient tile used where a product has no photo.
class ProductArt extends StatelessWidget {
  const ProductArt({super.key, required this.accent, required this.icon, this.size = 56, this.radius = 12});
  final String? accent;
  final IconData icon;
  final double size, radius;
  @override
  Widget build(BuildContext context) {
    final c = accentColor(accent, context.cs.primary);
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(radius),
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [c, c.withValues(alpha: .8), context.royalDark]),
      ),
      child: Icon(icon, color: Colors.white.withValues(alpha: .92), size: size * .5),
    );
  }
}

/// Horizontal progress tracker driven by the stage list from the server.
class OrderTracker extends StatelessWidget {
  const OrderTracker({super.key, required this.stages, required this.status});
  final List<OrderStageInfo> stages;
  final String status;

  static const _icons = {
    'placed': Icons.receipt_long,
    'confirmed': Icons.verified_outlined,
    'packing': Icons.inventory_2_outlined,
    'packed': Icons.takeout_dining_outlined,
    'dispatched': Icons.warehouse_outlined,
    'out_for_delivery': Icons.local_shipping_outlined,
    'delivered': Icons.home_outlined,
  };

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final lastReached = stages.lastIndexWhere((s) => s.reached);
    return SizedBox(
      height: 112,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        itemCount: stages.length,
        itemBuilder: (_, i) {
          final s = stages[i];
          final current = i == lastReached && s.key != 'delivered' && s.reached;
          final done = s.reached && !current;
          final bg = current ? context.gold : (done ? cs.primary : cs.surfaceContainerHighest);
          final fg = current ? cs.onTertiary : (done ? Colors.white : cs.onSurfaceVariant);
          return SizedBox(
            width: 92,
            child: Column(children: [
              Row(children: [
                Expanded(child: Container(height: 3, color: i == 0 ? Colors.transparent : (s.reached ? cs.primary : cs.surfaceContainerHighest))),
                Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    color: bg,
                    shape: BoxShape.circle,
                    boxShadow: current ? [BoxShadow(color: context.gold.withValues(alpha: .35), spreadRadius: 5)] : null,
                  ),
                  child: Icon(done ? Icons.check : (_icons[s.key] ?? Icons.circle), size: 18, color: fg),
                ),
                Expanded(child: Container(height: 3, color: i == stages.length - 1 ? Colors.transparent : (i < lastReached ? cs.primary : cs.surfaceContainerHighest))),
              ]),
              const SizedBox(height: 8),
              Text(s.label, textAlign: TextAlign.center, maxLines: 2, style: context.tt.labelSmall?.copyWith(fontWeight: FontWeight.w600)),
              if (s.at != null) Text(fmtDateTime(s.at), textAlign: TextAlign.center, style: context.tt.labelSmall?.copyWith(fontSize: 9, color: cs.onSurfaceVariant)),
            ]),
          );
        },
      ),
    );
  }
}

class Banner2 extends StatelessWidget {
  const Banner2({super.key, required this.icon, required this.child, this.tone = BannerTone.info});
  final IconData icon;
  final Widget child;
  final BannerTone tone;
  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final (bg, fg) = switch (tone) {
      BannerTone.info => (cs.primaryContainer, cs.onPrimaryContainer),
      BannerTone.warn => (cs.tertiaryContainer, cs.onTertiaryContainer),
      BannerTone.error => (cs.errorContainer, cs.onErrorContainer),
      BannerTone.success => (const Color(0xFFBFF0CC), const Color(0xFF00210E)),
    };
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
      child: DefaultTextStyle.merge(
        style: TextStyle(color: fg),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: fg), const SizedBox(width: 12), Expanded(child: child)]),
      ),
    );
  }
}

enum BannerTone { info, warn, error, success }

/// Royal-blue gradient hero used on the home and account screens.
class RoyalHeader extends StatelessWidget {
  const RoyalHeader({super.key, required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context) {
    final c = AppConfig.instance;
    return Container(
      decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [c.royalBlue, c.royalBlueDark])),
      child: Stack(children: [
        Positioned(right: -30, bottom: -40, child: Icon(Icons.cookie, size: 200, color: c.gold.withValues(alpha: .13))),
        Positioned(left: 0, right: 0, bottom: 0, child: Container(height: 3, color: c.gold)),
        child,
      ]),
    );
  }
}

class BrandLogo extends StatelessWidget {
  const BrandLogo({super.key, this.size = 56});
  final double size;
  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: context.gold,
          borderRadius: BorderRadius.circular(size * .28),
          border: Border.all(color: Colors.white.withValues(alpha: .35), width: 2),
        ),
        alignment: Alignment.center,
        child: Text(AppConfig.instance.brandName.substring(0, 1), style: TextStyle(fontSize: size * .5, fontWeight: FontWeight.w800, color: context.cs.primary)),
      );
}
