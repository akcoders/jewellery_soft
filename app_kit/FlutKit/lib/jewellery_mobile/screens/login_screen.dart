import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/pwa_install_service.dart';
import 'package:flutkit/jewellery_mobile/widgets/pwa_install_prompt.dart';
import 'package:flutkit/jewellery_mobile/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_mobile/theme/app_theme.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

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
    return Scaffold(
      body: Stack(
        children: [
          Align(
            alignment: Alignment.topCenter,
            child: Container(
              height: 320,
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    Color(0xFF351727),
                    AppColors.plum,
                    Color(0xFF692535),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.only(
                  bottomLeft: Radius.circular(36),
                  bottomRight: Radius.circular(36),
                ),
              ),
            ),
          ),
          SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(18, 28, 18, 32),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 420),
                  child: Column(
                    children: [
                      Container(
                        width: 86,
                        height: 86,
                        padding: const EdgeInsets.all(9),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFFBF4),
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.brandGold),
                        ),
                        child: Image.asset(
                          'assets/images/brand/aabhushan_mark.png',
                          fit: BoxFit.contain,
                        ),
                      ),
                      const SizedBox(height: 15),
                      const Text(
                        'AABHUSHAN',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 23,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 3.2,
                        ),
                      ),
                      const SizedBox(height: 5),
                      const Text(
                        'JEWELLERY WORKSPACE',
                        style: TextStyle(
                          color: AppColors.brandGold,
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 2.5,
                        ),
                      ),
                      const SizedBox(height: 28),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          color: AppColors.card,
                          borderRadius: BorderRadius.circular(AppRadius.xl),
                          border: Border.all(color: AppColors.border),
                          boxShadow: AppShadows.soft,
                        ),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(
                                'Aabhushan ERP',
                                style: Theme.of(
                                  context,
                                ).textTheme.headlineSmall,
                              ),
                              const SizedBox(height: 5),
                              const Text(
                                'Welcome back. Sign in to manage your work.',
                                style: TextStyle(
                                  color: AppColors.textSecondary,
                                ),
                              ),
                              const SizedBox(height: 27),
                              TextFormField(
                                controller: _emailCtrl,
                                keyboardType: TextInputType.emailAddress,
                                autofillHints: const [AutofillHints.email],
                                decoration: const InputDecoration(
                                  labelText: 'Email',
                                  prefixIcon: Icon(LucideIcons.mail, size: 20),
                                ),
                                validator: (v) =>
                                    (v == null || v.trim().isEmpty)
                                    ? 'Email is required'
                                    : null,
                              ),
                              const SizedBox(height: 16),
                              TextFormField(
                                controller: _passwordCtrl,
                                obscureText: _obscure,
                                autofillHints: const [AutofillHints.password],
                                decoration: InputDecoration(
                                  labelText: 'Password',
                                  prefixIcon: const Icon(
                                    LucideIcons.lock_keyhole,
                                  ),
                                  suffixIcon: IconButton(
                                    tooltip: _obscure
                                        ? 'Show password'
                                        : 'Hide password',
                                    onPressed: () =>
                                        setState(() => _obscure = !_obscure),
                                    icon: Icon(
                                      _obscure
                                          ? LucideIcons.eye
                                          : LucideIcons.eye_off,
                                    ),
                                  ),
                                ),
                                validator: (v) => (v == null || v.isEmpty)
                                    ? 'Password is required'
                                    : null,
                              ),
                              const SizedBox(height: 24),
                              FilledButton.icon(
                                onPressed: _loading ? null : _login,
                                icon: _loading
                                    ? const AppLoadingIndicator(
                                        size: 19,
                                        light: true,
                                      )
                                    : const Icon(LucideIcons.log_in, size: 20),
                                label: Text(
                                  _loading ? 'Signing in...' : 'Sign In',
                                ),
                              ),
                              ValueListenableBuilder<bool>(
                                valueListenable: PwaInstallService.available,
                                builder: (context, available, _) {
                                  if (!available)
                                    return const SizedBox.shrink();
                                  return Padding(
                                    padding: const EdgeInsets.only(top: 12),
                                    child: OutlinedButton.icon(
                                      onPressed: () =>
                                          showPwaInstallPrompt(context),
                                      icon: const Icon(
                                        LucideIcons.download,
                                        size: 19,
                                      ),
                                      label: const Text(
                                        'Install Aabhushan ERP',
                                      ),
                                    ),
                                  );
                                },
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
