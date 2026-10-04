import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../providers/providers.dart';
import '../../widgets/widgets.dart';

class ForgotScreen extends ConsumerStatefulWidget {
  const ForgotScreen({super.key});
  @override
  ConsumerState<ForgotScreen> createState() => _ForgotScreenState();
}

class _ForgotScreenState extends ConsumerState<ForgotScreen> {
  final _phone = TextEditingController();
  final _code = TextEditingController();
  final _pass = TextEditingController();
  int _step = 1;
  String? _hint;
  bool _busy = false;

  Future<void> _run(Future<void> Function() f) async {
    setState(() => _busy = true);
    try {
      await f();
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Reset password')),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          if (_step == 1) ...[
            const Text("We'll send a one-time code to your registered mobile number."),
            const SizedBox(height: 16),
            TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Mobile number', prefixText: '+91 ')),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _busy
                  ? null
                  : () => _run(() async {
                        _hint = await ref.read(authProvider.notifier).sendOtp(_phone.text.trim(), 'reset');
                        setState(() => _step = 2);
                      }),
              child: const Text('Send OTP'),
            ),
          ] else ...[
            if (_hint != null) Banner2(icon: Icons.sms_outlined, tone: BannerTone.warn, child: Text('Prototype mode — your OTP is $_hint')),
            const SizedBox(height: 16),
            TextField(controller: _code, keyboardType: TextInputType.number, maxLength: 6, decoration: const InputDecoration(labelText: 'OTP')),
            const SizedBox(height: 8),
            TextField(controller: _pass, obscureText: true, decoration: const InputDecoration(labelText: 'New password (min 8 characters)')),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _busy
                  ? null
                  : () => _run(() async {
                        await ref.read(authProvider.notifier).resetPassword(_phone.text.trim(), _code.text.trim(), _pass.text);
                        if (!context.mounted) return;
                        showSnack(context, 'Password updated. Please log in.');
                        context.pop();
                      }),
              child: const Text('Update password'),
            ),
          ],
        ]),
      );
}
