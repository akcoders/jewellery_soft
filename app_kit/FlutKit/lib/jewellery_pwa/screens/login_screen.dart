import 'package:flutkit/jewellery_mobile/widgets/premium_surface.dart';
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
      body: PremiumBackdrop(
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
                    child: ConstrainedBox(
                      constraints: BoxConstraints(maxWidth: wide ? 980 : 440),
                      child: PremiumEntrance(
                        child: wide
                            ? Row(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  Expanded(child: _brandPanel(true)),
                                  const SizedBox(width: 40),
                                  Expanded(child: _formSurface()),
                                ],
                              )
                            : Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  _brandPanel(false),
                                  const SizedBox(height: 28),
                                  _formSurface(),
                                  const SizedBox(height: 24),
                                  _workspaceNote(),
                                ],
                              ),
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

  Widget _brandPanel(bool wide) => Padding(
    padding: EdgeInsets.symmetric(
      horizontal: wide ? 24 : 8,
      vertical: wide ? 40 : 14,
    ),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: wide ? 94 : 76,
          height: wide ? 94 : 76,
          padding: const EdgeInsets.all(9),
          decoration: BoxDecoration(
            color: AppColors.card,
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.brandGold),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0B5364AD),
                blurRadius: 24,
                offset: Offset(0, 8),
              ),
            ],
          ),
          child: Image.asset(
            'assets/images/brand/aabhushan_mark.png',
            fit: BoxFit.contain,
          ),
        ),
        const SizedBox(height: 18),
        const FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            'AABHUSHAN',
            style: TextStyle(
              fontFamily: 'CormorantGaramond',
              color: AppColors.textPrimary,
              fontSize: 36,
              fontWeight: FontWeight.w600,
              letterSpacing: 3,
            ),
          ),
        ),
        const SizedBox(height: 5),
        const Text(
          'JEWELLERY WORKSPACE',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: AppColors.textSecondary,
            fontSize: 9,
            fontWeight: FontWeight.w600,
            letterSpacing: 2.2,
          ),
        ),
        if (wide) ...[
          const SizedBox(height: 38),
          const FacetedDiamond(size: 72, color: AppColors.brandGold),
          const SizedBox(height: 26),
          const Text(
            'Every detail.\nBeautifully in place.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontFamily: 'CormorantGaramond',
              color: AppColors.textPrimary,
              fontSize: 39,
              height: 1.15,
            ),
          ),
          const SizedBox(height: 18),
          _workspaceNote(),
        ],
      ],
    ),
  );

  Widget _workspaceNote() => const Text(
    'ORDERS  ·  MATERIALS  ·  CRAFT',
    textAlign: TextAlign.center,
    style: TextStyle(
      color: AppColors.textSecondary,
      fontSize: 9,
      letterSpacing: 1.4,
    ),
  );

  Widget _formSurface() => PremiumSurface(
    padding: const EdgeInsets.all(24),
    borderRadius: 28,
    accent: AppColors.lavender,
    child: AutofillGroup(
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'WELCOME BACK',
              style: TextStyle(
                color: AppColors.textSecondary,
                fontSize: 9,
                letterSpacing: 1.8,
                fontWeight: FontWeight.w700,
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
              style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
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
                prefixIcon: Icon(LucideIcons.mail, size: 19),
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
                prefixIcon: const Icon(LucideIcons.lock_keyhole, size: 19),
                suffixIcon: IconButton(
                  tooltip: _obscure ? 'Show password' : 'Hide password',
                  onPressed: () => setState(() => _obscure = !_obscure),
                  icon: Icon(
                    _obscure ? LucideIcons.eye : LucideIcons.eye_off,
                    size: 19,
                  ),
                ),
              ),
              validator: (value) => value == null || value.isEmpty
                  ? 'Password is required'
                  : null,
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
    ),
  );
}
