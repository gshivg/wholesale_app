import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api.dart';
import '../../core/app_config.dart';
import '../../core/theme.dart';
import '../../providers/providers.dart';
import '../../widgets/widgets.dart';

/// 4-step shop registration: owner → shop (PAN/GSTIN) → address (serviceable PIN) → OTP.
class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key, this.verifyPhone});
  final String? verifyPhone;
  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _keys = List.generate(3, (_) => GlobalKey<FormState>());
  int _step = 0;
  bool _busy = false;
  String? _otpHint, _phoneToVerify;
  XFile? _photo;

  final name = TextEditingController(), phone = TextEditingController(), email = TextEditingController();
  final password = TextEditingController(), confirm = TextEditingController();
  final shopName = TextEditingController(), pan = TextEditingController(), gstin = TextEditingController();
  final line1 = TextEditingController(), line2 = TextEditingController(), landmark = TextEditingController();
  final pincode = TextEditingController(), city = TextEditingController();
  final otp = TextEditingController();
  late String shopType = AppConfig.instance.shopTypes.first;

  static const _fieldStep = {
    'name': 0, 'phone': 0, 'email': 0, 'password': 0,
    'shop_name': 1, 'shop_type': 1, 'pan': 1, 'gstin': 1,
    'address_line1': 2, 'pincode': 2, 'city': 2, 'shop_photo': 2,
  };

  @override
  void initState() {
    super.initState();
    if (widget.verifyPhone != null) {
      _phoneToVerify = widget.verifyPhone;
      _step = 3;
    }
  }

  Future<void> _guard(Future<void> Function() f) async {
    setState(() => _busy = true);
    try {
      await f();
    } on ApiException catch (e) {
      if (!mounted) return;
      final firstField = e.fieldErrors.keys.firstOrNull;
      if (firstField != null && _fieldStep.containsKey(firstField)) setState(() => _step = _fieldStep[firstField]!);
      showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _next() {
    if (!_keys[_step].currentState!.validate()) return;
    if (_step < 2) {
      setState(() => _step++);
    } else {
      _submit();
    }
  }

  Future<void> _submit() => _guard(() async {
        _otpHint = await ref.read(authProvider.notifier).register({
          'name': name.text.trim(), 'phone': phone.text.trim(), 'email': email.text.trim(),
          'password': password.text, 'password_confirmation': confirm.text,
          'shop_name': shopName.text.trim(), 'shop_type': shopType, 'pan': pan.text.trim().toUpperCase(),
          if (gstin.text.trim().isNotEmpty) 'gstin': gstin.text.trim().toUpperCase(),
          'address_line1': line1.text.trim(), if (line2.text.trim().isNotEmpty) 'address_line2': line2.text.trim(),
          if (landmark.text.trim().isNotEmpty) 'landmark': landmark.text.trim(),
          'pincode': pincode.text.trim(), 'city': city.text.trim(),
        }, photo: _photo);
        setState(() {
          _phoneToVerify = phone.text.trim();
          _step = 3;
        });
      });

  Future<void> _verify() => _guard(() async {
        await ref.read(authProvider.notifier).verifyOtp(_phoneToVerify!, otp.text.trim());
      });

  Future<void> _resend() => _guard(() async {
        _otpHint = await ref.read(authProvider.notifier).sendOtp(_phoneToVerify!, 'register');
        if (mounted) showSnack(context, 'OTP sent again');
      });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Register your shop'), leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => _step > 0 && _step < 3 ? setState(() => _step--) : context.go('/login'))),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
          child: Row(children: [
            for (var i = 0; i < 4; i++)
              Expanded(child: Container(margin: const EdgeInsets.symmetric(horizontal: 3), height: 4, decoration: BoxDecoration(color: i <= _step ? context.gold : context.cs.surfaceContainerHighest, borderRadius: BorderRadius.circular(2)))),
          ]),
        ),
        Expanded(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: ListView(padding: const EdgeInsets.all(20), children: [
                [_owner(), _shop(), _address(), _verifyStep()][_step],
              ]),
            ),
          ),
        ),
      ]),
    );
  }

  Widget _title(String t, String sub) => Padding(
        padding: const EdgeInsets.only(bottom: 20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(t, style: context.tt.headlineSmall), const SizedBox(height: 4), Text(sub, style: TextStyle(color: context.cs.onSurfaceVariant))]),
      );

  Widget _nav({String next = 'Next'}) => Padding(
        padding: const EdgeInsets.only(top: 24),
        child: Row(children: [
          if (_step > 0) Expanded(child: OutlinedButton(onPressed: () => setState(() => _step--), child: const Text('Back'))),
          if (_step > 0) const SizedBox(width: 12),
          Expanded(flex: 2, child: FilledButton(onPressed: _busy ? null : _next, child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : Text(next))),
        ]),
      );

  static String? _req(String? v, [String msg = 'Required']) => (v ?? '').trim().isEmpty ? msg : null;

  Widget _gap() => const SizedBox(height: 16);

  Widget _owner() => Form(
        key: _keys[0],
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          _title('About you', 'The shop owner / person who will place orders.'),
          TextFormField(controller: name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: "Owner's full name"), validator: _req),
          _gap(),
          TextFormField(
            controller: phone, keyboardType: TextInputType.phone, maxLength: 10,
            decoration: const InputDecoration(labelText: 'Mobile number', prefixText: '+91 ', helperText: 'Used to log in'),
            validator: (v) => RegExp(r'^[6-9]\d{9}$').hasMatch(v?.trim() ?? '') ? null : 'Enter a valid 10-digit mobile number',
          ),
          TextFormField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email address'),
              validator: (v) => RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(v?.trim() ?? '') ? null : 'Enter a valid email'),
          _gap(),
          TextFormField(controller: password, obscureText: true, decoration: const InputDecoration(labelText: 'Password', helperText: 'At least 8 characters'), validator: (v) => (v ?? '').length >= 8 ? null : 'Use at least 8 characters'),
          _gap(),
          TextFormField(controller: confirm, obscureText: true, decoration: const InputDecoration(labelText: 'Confirm password'), validator: (v) => v == password.text ? null : 'Passwords do not match'),
          _nav(),
          TextButton(onPressed: () => context.go('/login'), child: const Text('I already have an account')),
        ]),
      );

  Widget _shop() => Form(
        key: _keys[1],
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          _title('Your shop', 'We verify PAN and GSTIN before activating ordering.'),
          TextFormField(controller: shopName, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Shop name'), validator: _req),
          _gap(),
          DropdownButtonFormField<String>(
            initialValue: shopType, isExpanded: true, decoration: const InputDecoration(labelText: 'Type of shop'),
            items: [for (final t in AppConfig.instance.shopTypes) DropdownMenuItem(value: t, child: Text(t))],
            onChanged: (v) => setState(() => shopType = v!),
          ),
          _gap(),
          TextFormField(
            controller: pan, textCapitalization: TextCapitalization.characters, maxLength: 10,
            decoration: const InputDecoration(labelText: 'PAN', helperText: 'e.g. ABCDE1234F'),
            validator: (v) => RegExp(r'^[A-Z]{5}[0-9]{4}[A-Z]$').hasMatch((v ?? '').trim().toUpperCase()) ? null : 'Enter a valid PAN',
          ),
          TextFormField(
            controller: gstin, textCapitalization: TextCapitalization.characters, maxLength: 15,
            decoration: const InputDecoration(labelText: 'GSTIN (optional)', helperText: 'Leave blank if not GST-registered'),
            validator: (v) {
              final g = (v ?? '').trim().toUpperCase();
              if (g.isEmpty) return null;
              if (!RegExp(r'^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$').hasMatch(g)) return 'Enter a valid 15-character GSTIN';
              if (g.substring(2, 12) != pan.text.trim().toUpperCase()) return 'GSTIN does not match the PAN';
              return null;
            },
          ),
          _nav(),
        ]),
      );

  Widget _address() {
    final pin = pincode.text.trim();
    final area = pin.length == 6 ? AppConfig.instance.areaFor(pin) : null;
    return Form(
      key: _keys[2],
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        _title('Delivery address', 'We currently deliver in ${AppConfig.instance.serviceAreas.map((a) => a.district).join(', ')}.'),
        TextFormField(controller: line1, decoration: const InputDecoration(labelText: 'Address line 1'), validator: _req),
        _gap(),
        TextFormField(controller: line2, decoration: const InputDecoration(labelText: 'Address line 2 (optional)')),
        _gap(),
        TextFormField(controller: landmark, decoration: const InputDecoration(labelText: 'Landmark (optional)')),
        _gap(),
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
            child: TextFormField(
              controller: pincode, keyboardType: TextInputType.number, maxLength: 6, decoration: const InputDecoration(labelText: 'PIN code'),
              onChanged: (v) => setState(() {
                final a = v.length == 6 ? AppConfig.instance.areaFor(v) : null;
                if (a != null && city.text.isEmpty) city.text = a.district;
              }),
              validator: (v) => (v ?? '').length != 6 ? 'Enter 6 digits' : (AppConfig.instance.areaFor(v!) == null ? 'Not in our service area yet' : null),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(child: TextFormField(controller: city, decoration: const InputDecoration(labelText: 'City / town'), validator: _req)),
        ]),
        if (pin.length == 6)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: area != null
                ? Banner2(icon: Icons.check_circle_outline, tone: BannerTone.success, child: Text('Great — we deliver to ${area.district} district.'))
                : const Banner2(icon: Icons.location_off_outlined, tone: BannerTone.warn, child: Text("We don't deliver to this PIN code yet.")),
          ),
        _gap(),
        Row(children: [
          if (_photo != null) Padding(padding: const EdgeInsets.only(right: 12), child: Icon(Icons.check_circle, color: context.cs.primary)),
          Expanded(child: OutlinedButton.icon(
            icon: const Icon(Icons.add_a_photo_outlined),
            label: Text(_photo == null ? 'Add shop photo (optional)' : _photo!.name, overflow: TextOverflow.ellipsis),
            onPressed: () async {
              final p = await ImagePicker().pickImage(source: ImageSource.gallery, maxWidth: 1600, imageQuality: 85);
              if (p != null) setState(() => _photo = p);
            },
          )),
        ]),
        _nav(next: 'Create account'),
      ]),
    );
  }

  Widget _verifyStep() => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        _title('Verify your mobile', 'Enter the 6-digit code sent to +91 $_phoneToVerify.'),
        if (_otpHint != null) Banner2(icon: Icons.sms_outlined, tone: BannerTone.warn, child: Text('Prototype mode — no SMS is sent. Use OTP $_otpHint.')),
        _gap(),
        TextField(controller: otp, keyboardType: TextInputType.number, maxLength: 6, textAlign: TextAlign.center, style: const TextStyle(fontSize: 28, letterSpacing: 12), decoration: const InputDecoration(labelText: 'OTP')),
        const SizedBox(height: 8),
        FilledButton(onPressed: _busy ? null : _verify, child: const Text('Verify & continue')),
        TextButton(onPressed: _busy ? null : _resend, child: const Text('Resend OTP')),
      ]);
}
