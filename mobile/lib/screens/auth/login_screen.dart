import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/app_config.dart';
import '../../core/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/providers.dart';
import '../../widgets/widgets.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false, _obscure = true;

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await ref.read(authProvider.notifier).login(_phone.text.trim(), _password.text);
      // router redirect takes over
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.code == 'phone_unverified') {
        context.go('/register', extra: _phone.text.trim());
      } else {
        showSnack(context, e.message, error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final cfg = AppConfig.instance;
    return Scaffold(
      body: Column(children: [
        RoyalHeader(
          child: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 32, 24, 40),
              child: SizedBox(
                width: double.infinity,
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const BrandLogo(size: 64),
                  const SizedBox(height: 20),
                  Text(cfg.brandName, style: context.tt.headlineMedium?.copyWith(color: Colors.white, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 4),
                  Text(cfg.tagline, style: TextStyle(color: context.goldLight)),
                ]),
              ),
            ),
          ),
        ),
        Expanded(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: Form(
                  key: _form,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Text('Welcome back', style: context.tt.headlineSmall),
                    const SizedBox(height: 4),
                    Text('Log in to order, track deliveries and get support.', style: TextStyle(color: context.cs.onSurfaceVariant)),
                    const SizedBox(height: 24),
                    TextFormField(
                      controller: _phone,
                      keyboardType: TextInputType.phone,
                      autofillHints: const [AutofillHints.username],
                      decoration: InputDecoration(labelText: l.mobileNumber, prefixIcon: const Icon(Icons.phone_outlined), prefixText: '+91 '),
                      validator: (v) => RegExp(r'^[6-9]\d{9}$').hasMatch(v?.trim() ?? '') ? null : 'Enter a valid 10-digit mobile number',
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _password,
                      obscureText: _obscure,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        labelText: l.password,
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined), onPressed: () => setState(() => _obscure = !_obscure)),
                      ),
                      validator: (v) => (v ?? '').isEmpty ? 'Enter your password' : null,
                    ),
                    Align(alignment: Alignment.centerRight, child: TextButton(onPressed: () => context.push('/forgot'), child: Text(l.forgotPassword))),
                    const SizedBox(height: 8),
                    FilledButton(onPressed: _busy ? null : _submit, child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : Text(l.login)),
                    const SizedBox(height: 12),
                    OutlinedButton(onPressed: () => context.push('/register'), child: Text(l.register)),
                  ]),
                ),
              ),
            ),
          ),
        ),
      ]),
    );
  }
}
