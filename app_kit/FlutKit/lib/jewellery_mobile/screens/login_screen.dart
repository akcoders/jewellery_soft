import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/pwa_install_service.dart';
import 'package:flutkit/jewellery_mobile/widgets/pwa_install_prompt.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.onLoginSuccess});

  final Future<void> Function({
    required String baseUrl,
    required String token,
    required String userName,
    required String userEmail,
    required List<String> roleCodes,
  })
  onLoginSuccess;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  static const String _defaultBaseUrl = 'https://aabhushan.webignitors.in';
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _loading = false;
  bool _obscure = true;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    final valid = _formKey.currentState?.validate() ?? false;
    if (!valid || _loading) return;

    setState(() => _loading = true);
    try {
      final api = MobileApiService(baseUrl: _defaultBaseUrl);
      final loginData = await api.login(
        email: _emailCtrl.text.trim(),
        password: _passwordCtrl.text,
        deviceName: kIsWeb ? 'Aabhushan PWA' : 'Android App',
      );
      final me = await api.me();
      if (!mounted) return;
      await widget.onLoginSuccess(
        baseUrl: api.baseUrl,
        token: api.token,
        userName: (me['name'] ?? loginData['user']?['name'] ?? 'Admin')
            .toString(),
        userEmail:
            (me['email'] ??
                    loginData['user']?['email'] ??
                    _emailCtrl.text.trim())
                .toString(),
        roleCodes:
            ((me['role_codes'] ?? loginData['user']?['role_codes']) as List?)
                ?.map((role) => role.toString())
                .toList() ??
            const [],
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                  side: BorderSide(color: color.outlineVariant),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          'Aabhushan ERP',
                          style: Theme.of(context).textTheme.headlineSmall
                              ?.copyWith(fontWeight: FontWeight.w700),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Order Followup + Gold/Diamond/Stone Inventory',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        const SizedBox(height: 20),
                        TextFormField(
                          controller: _emailCtrl,
                          keyboardType: TextInputType.emailAddress,
                          decoration: const InputDecoration(
                            labelText: 'Email',
                            prefixIcon: Icon(Icons.mail_outline),
                          ),
                          validator: (v) => (v == null || v.trim().isEmpty)
                              ? 'Email is required'
                              : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _passwordCtrl,
                          obscureText: _obscure,
                          decoration: InputDecoration(
                            labelText: 'Password',
                            prefixIcon: const Icon(Icons.lock_outline),
                            suffixIcon: IconButton(
                              onPressed: () =>
                                  setState(() => _obscure = !_obscure),
                              icon: Icon(
                                _obscure
                                    ? Icons.visibility
                                    : Icons.visibility_off,
                              ),
                            ),
                          ),
                          validator: (v) => (v == null || v.isEmpty)
                              ? 'Password is required'
                              : null,
                        ),
                        const SizedBox(height: 18),
                        FilledButton.icon(
                          onPressed: _loading ? null : _login,
                          icon: _loading
                              ? const SizedBox(
                                  width: 16,
                                  height: 16,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.login),
                          label: Text(_loading ? 'Signing in...' : 'Sign In'),
                        ),
                        ValueListenableBuilder<bool>(
                          valueListenable: PwaInstallService.available,
                          builder: (context, available, _) {
                            if (!available) return const SizedBox.shrink();
                            return Padding(
                              padding: const EdgeInsets.only(top: 10),
                              child: OutlinedButton.icon(
                                onPressed: () => showPwaInstallPrompt(context),
                                icon: const Icon(Icons.install_mobile_outlined),
                                label: const Text('Install Aabhushan ERP'),
                              ),
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
