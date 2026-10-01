import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/pwa_install_service.dart';
import 'package:flutkit/jewellery_pwa/widgets/pwa_install_prompt.dart';
import 'package:flutkit/jewellery_pwa/widgets/full_screen_loader.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
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
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFF7F3ED), Color(0xFFEEE5DC), Color(0xFFF9F7F4)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final wide = constraints.maxWidth >= 840;
              return SingleChildScrollView(
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                padding: EdgeInsets.all(wide ? 40 : 20),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: (constraints.maxHeight - (wide ? 80 : 40)).clamp(
                      0,
                      double.infinity,
                    ),
                  ),
                  child: Center(
                    child: Container(
                      constraints: BoxConstraints(maxWidth: wide ? 960 : 440),
                      clipBehavior: Clip.antiAlias,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(28),
                        border: Border.all(color: Colors.white),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x16421D32),
                            blurRadius: 50,
                            offset: Offset(0, 18),
                          ),
                        ],
                      ),
                      child: wide
                          ? IntrinsicHeight(
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Expanded(child: _brandPanel(true)),
                                  Expanded(
                                    child: Padding(
                                      padding: const EdgeInsets.all(38),
                                      child: _loginForm(),
                                    ),
                                  ),
                                ],
                              ),
                            )
                          : Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                _brandPanel(false),
                                Padding(
                                  padding: const EdgeInsets.all(24),
                                  child: _loginForm(),
                                ),
                              ],
                            ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _brandPanel(bool wide) => Container(
    width: double.infinity,
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        colors: [Color(0xFF2D1424), AppColors.plum, Color(0xFF713443)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
    ),
    child: Stack(
      clipBehavior: Clip.hardEdge,
      children: [
        Positioned(
          top: -95,
          right: -90,
          child: Container(
            width: 270,
            height: 270,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: AppColors.brandGold.withValues(alpha: .2),
              ),
            ),
          ),
        ),
        Positioned(
          bottom: -170,
          left: -85,
          child: Container(
            width: 340,
            height: 340,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: AppColors.brandGold.withValues(alpha: .15),
              ),
            ),
          ),
        ),
        Center(
          child: Padding(
            padding: EdgeInsets.symmetric(
              horizontal: 32,
              vertical: wide ? 60 : 26,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: wide ? 100 : 74,
                  height: wide ? 100 : 74,
                  padding: const EdgeInsets.all(10),
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
                const SizedBox(height: 18),
                const Text(
                  'AABHUSHAN',
                  style: TextStyle(
                    fontFamily: 'CormorantGaramond',
                    color: Colors.white,
                    fontSize: 34,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 2.8,
                  ),
                ),
                const SizedBox(height: 5),
                const Text(
                  'JEWELLERY WORKSPACE',
                  style: TextStyle(
                    color: AppColors.brandGold,
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 2.3,
                  ),
                ),
                if (wide) ...[
                  const SizedBox(height: 40),
                  const Text(
                    'Your craft.\nOne workspace.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontFamily: 'CormorantGaramond',
                      color: Color(0xFFFFF6E8),
                      fontSize: 38,
                      height: 1.15,
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'ORDERS  ·  INVENTORY  ·  TEAM',
                    style: TextStyle(
                      color: Colors.white60,
                      fontSize: 9,
                      letterSpacing: 1.1,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    ),
  );

  Widget _loginForm() => AutofillGroup(
    child: Form(
      key: _formKey,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'WELCOME BACK',
            style: TextStyle(
              color: AppColors.gold,
              fontSize: 10,
              letterSpacing: 1.6,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Aabhushan ERP',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 7),
          const Text(
            'Sign in to your workspace.',
            style: TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 28),
          TextFormField(
            controller: _emailCtrl,
            readOnly: _loading,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.next,
            autofillHints: const [AutofillHints.email],
            decoration: const InputDecoration(
              labelText: 'Email',
              prefixIcon: Icon(LucideIcons.mail, size: 20),
            ),
            validator: (value) => value == null || value.trim().isEmpty
                ? 'Email is required'
                : null,
          ),
          const SizedBox(height: 18),
          TextFormField(
            controller: _passwordCtrl,
            readOnly: _loading,
            obscureText: _obscure,
            textInputAction: TextInputAction.done,
            onFieldSubmitted: (_) => _login(),
            autofillHints: const [AutofillHints.password],
            decoration: InputDecoration(
              labelText: 'Password',
              prefixIcon: const Icon(LucideIcons.lock_keyhole, size: 20),
              suffixIcon: IconButton(
                tooltip: _obscure ? 'Show password' : 'Hide password',
                onPressed: () => setState(() => _obscure = !_obscure),
                icon: Icon(
                  _obscure ? LucideIcons.eye : LucideIcons.eye_off,
                  size: 20,
                ),
              ),
            ),
            validator: (value) =>
                value == null || value.isEmpty ? 'Password is required' : null,
          ),
          const SizedBox(height: 26),
          FilledButton.icon(
            onPressed: _loading ? null : _login,
            icon: _loading
                ? const AppLoadingIndicator(size: 20, light: true)
                : const Icon(LucideIcons.arrow_right, size: 19),
            label: Text(_loading ? 'Signing in...' : 'Sign In'),
          ),
          ValueListenableBuilder<bool>(
            valueListenable: PwaInstallService.available,
            builder: (context, available, _) => !available
                ? const SizedBox.shrink()
                : Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: OutlinedButton.icon(
                      onPressed: () => showPwaInstallPrompt(context),
                      icon: const Icon(LucideIcons.download, size: 18),
                      label: const Text('Install Aabhushan ERP'),
                    ),
                  ),
          ),
        ],
      ),
    ),
  );
}
