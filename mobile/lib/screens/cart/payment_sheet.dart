import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/app_config.dart';
import '../../core/format.dart';
import '../../core/theme.dart';
import '../../models/models.dart';
import '../../providers/providers.dart';
import '../../widgets/widgets.dart';

/// Sandbox payment: mimics a gateway sheet. Test instruments come from shared.yaml.
Future<void> showPaymentSheet(BuildContext context, {required int orderId, required PaymentIntent intent}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => Padding(padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom), child: _PaymentSheet(orderId: orderId, intent: intent)),
  );
}

class _PaymentSheet extends ConsumerStatefulWidget {
  const _PaymentSheet({required this.orderId, required this.intent});
  final int orderId;
  final PaymentIntent intent;
  @override
  ConsumerState<_PaymentSheet> createState() => _PaymentSheetState();
}

class _PaymentSheetState extends ConsumerState<_PaymentSheet> {
  String _method = 'card';
  final _instrument = TextEditingController(text: '4111 1111 1111 1111');
  bool _busy = false;
  String? _error;

  Future<void> _pay() async {
    setState(() { _busy = true; _error = null; });
    try {
      final ok = await ref.read(orderActionsProvider).confirmPayment(widget.orderId, widget.intent.id, _method, _instrument.text.trim());
      if (!mounted) return;
      Navigator.of(context).pop();
      showSnack(context, ok ? 'Payment successful' : 'Payment failed — you can retry from the order page', error: !ok);
    } catch (e) {
      setState(() => _error = errorText(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _use(String method, String value) => setState(() { _method = method; _instrument.text = value; });

  @override
  Widget build(BuildContext context) {
    final cards = AppConfig.instance.testCards;
    String find(String outcome, bool upi) => cards.entries.firstWhere((e) => e.value == outcome && e.key.contains('@') == upi, orElse: () => const MapEntry('', '')).key;
    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
          Row(children: [
            Expanded(child: Text('Sandbox payment', style: context.tt.titleLarge)),
            Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4), decoration: BoxDecoration(color: context.cs.tertiaryContainer, borderRadius: BorderRadius.circular(12)), child: const Text('TEST MODE', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700))),
          ]),
          const SizedBox(height: 8),
          Text(inr(widget.intent.amount), style: context.tt.headlineMedium?.copyWith(color: context.cs.primary, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          SegmentedButton<String>(
            segments: const [ButtonSegment(value: 'card', icon: Icon(Icons.credit_card), label: Text('Card')), ButtonSegment(value: 'upi', icon: Icon(Icons.account_balance_wallet_outlined), label: Text('UPI'))],
            selected: {_method},
            onSelectionChanged: (s) => _use(s.first, s.first == 'card' ? find('success', false) : find('success', true)),
          ),
          const SizedBox(height: 16),
          TextField(controller: _instrument, decoration: InputDecoration(labelText: _method == 'card' ? 'Card number' : 'UPI ID', errorText: _error)),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            ActionChip(avatar: const Icon(Icons.check_circle, size: 16, color: Color(0xFF1B6B3A)), label: const Text('Success card'), onPressed: () => _use('card', find('success', false))),
            ActionChip(avatar: Icon(Icons.cancel, size: 16, color: context.cs.error), label: const Text('Failing card'), onPressed: () => _use('card', find('failure', false))),
            ActionChip(avatar: const Icon(Icons.check_circle, size: 16, color: Color(0xFF1B6B3A)), label: const Text('success@sandbox'), onPressed: () => _use('upi', find('success', true))),
            ActionChip(avatar: Icon(Icons.cancel, size: 16, color: context.cs.error), label: const Text('failure@sandbox'), onPressed: () => _use('upi', find('failure', true))),
          ]),
          const SizedBox(height: 8),
          Text('No real money moves. Any expiry / CVV works.', style: TextStyle(color: context.cs.onSurfaceVariant, fontSize: 12)),
          const SizedBox(height: 16),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: context.gold, foregroundColor: context.cs.onTertiary),
            onPressed: _busy ? null : _pay,
            child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Pay now'),
          ),
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Pay later')),
        ]),
      ),
    );
  }
}
