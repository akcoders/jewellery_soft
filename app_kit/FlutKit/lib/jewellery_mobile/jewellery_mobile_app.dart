import 'package:flutkit/jewellery_mobile/screens/app_shell.dart';
import 'package:flutkit/jewellery_mobile/screens/login_screen.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:flutkit/jewellery_mobile/services/onesignal_service.dart';
import 'package:flutkit/jewellery_mobile/session/mobile_session_store.dart';
import 'package:flutter/material.dart';

class JewelleryMobileApp extends StatefulWidget {
  const JewelleryMobileApp({super.key, required this.initialSession});

  final MobileSession? initialSession;

  @override
  State<JewelleryMobileApp> createState() => _JewelleryMobileAppState();
}

class _JewelleryMobileAppState extends State<JewelleryMobileApp> {
  MobileSession? _session;

  @override
  void initState() {
    super.initState();
    _session = widget.initialSession;
    final session = _session;
    if (session != null && session.isValid) {
      OneSignalService.syncUser(
        userEmail: session.userEmail,
        userName: session.userName,
      );
    }
  }

  Future<void> _onLoginSuccess({
    required String baseUrl,
    required String token,
    required String userName,
    required String userEmail,
  }) async {
    final session = MobileSession(
      baseUrl: baseUrl,
      token: token,
      userName: userName,
      userEmail: userEmail,
    );
    await MobileSessionStore.save(session);
    await OneSignalService.syncUser(userEmail: userEmail, userName: userName);
    setState(() {
      _session = session;
    });
  }

  Future<void> _logout() async {
    final session = _session;
    if (session != null && session.isValid) {
      final api = MobileApiService(
        baseUrl: session.baseUrl,
        token: session.token,
      );
      await api.logout();
    }
    await OneSignalService.clearUser();
    await MobileSessionStore.clear();
    if (!mounted) return;
    setState(() {
      _session = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final session = _session;
    if (session == null || !session.isValid) {
      return LoginScreen(onLoginSuccess: _onLoginSuccess);
    }

    return AppShell(session: session, onLogout: _logout);
  }
}
