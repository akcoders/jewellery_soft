import 'package:shared_preferences/shared_preferences.dart';

class MobileSession {
  const MobileSession({
    required this.baseUrl,
    required this.token,
    required this.userName,
    required this.userEmail,
    this.roleCodes = const [],
  });

  final String baseUrl;
  final String token;
  final String userName;
  final String userEmail;
  final List<String> roleCodes;

  bool get isValid => baseUrl.trim().isNotEmpty && token.trim().isNotEmpty;
  bool get canUsePerformance => roleCodes.isNotEmpty && !isAdmin;
  bool get isAdmin => roleCodes.any(
    (role) =>
        const {'SUPER_ADMIN', 'ADMIN', 'OWNER'}.contains(role.toUpperCase()),
  );
}

class MobileSessionStore {
  static const _keyBaseUrl = 'jm_base_url';
  static const _keyToken = 'jm_token';
  static const _keyUserName = 'jm_user_name';
  static const _keyUserEmail = 'jm_user_email';
  static const _keyRoleCodes = 'jm_role_codes';

  static Future<MobileSession?> load() async {
    final prefs = await SharedPreferences.getInstance();
    final baseUrl = prefs.getString(_keyBaseUrl) ?? '';
    final token = prefs.getString(_keyToken) ?? '';
    if (baseUrl.trim().isEmpty || token.trim().isEmpty) {
      return null;
    }

    return MobileSession(
      baseUrl: baseUrl,
      token: token,
      userName: prefs.getString(_keyUserName) ?? '',
      userEmail: prefs.getString(_keyUserEmail) ?? '',
      roleCodes: prefs.getStringList(_keyRoleCodes) ?? const [],
    );
  }

  static Future<void> save(MobileSession session) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyBaseUrl, session.baseUrl);
    await prefs.setString(_keyToken, session.token);
    await prefs.setString(_keyUserName, session.userName);
    await prefs.setString(_keyUserEmail, session.userEmail);
    await prefs.setStringList(_keyRoleCodes, session.roleCodes);
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyBaseUrl);
    await prefs.remove(_keyToken);
    await prefs.remove(_keyUserName);
    await prefs.remove(_keyUserEmail);
    await prefs.remove(_keyRoleCodes);
  }
}
