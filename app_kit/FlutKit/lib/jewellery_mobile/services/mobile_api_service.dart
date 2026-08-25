import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';

class MobileApiService {
  MobileApiService({required String baseUrl, String token = ''})
    : _baseUrl = _normalizeBaseUrl(baseUrl),
      _token = token;

  final http.Client _client = http.Client();
  String _baseUrl;
  String _token;

  String get baseUrl => _baseUrl;
  String get token => _token;

  set baseUrl(String value) {
    _baseUrl = _normalizeBaseUrl(value);
  }

  set token(String value) {
    _token = value;
  }

  Map<String, String> documentHeaders() {
    final headers = <String, String>{'Accept': 'application/pdf,*/*'};
    if (_token.trim().isNotEmpty) {
      headers['Authorization'] = 'Bearer $_token';
    }
    return headers;
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    final res = await _post(
      '/api/mobile/login',
      body: {
        'email': email.trim(),
        'password': password,
        'device_name': deviceName.trim(),
      },
      withAuth: false,
    );
    final data = (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
    _token = (data['token'] ?? '').toString();
    return data;
  }

  Future<Map<String, dynamic>> me() async {
    final res = await _get('/api/mobile/me');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<void> logout() async {
    try {
      await _post('/api/mobile/logout', body: const {});
    } catch (_) {}
  }

  Future<Map<String, dynamic>> fetchOrders({
    String status = '',
    String query = '',
    int page = 1,
    int limit = 20,
  }) async {
    final q = <String, String>{'page': '$page', 'limit': '$limit'};
    if (status.trim().isNotEmpty) q['status'] = status.trim();
    if (query.trim().isNotEmpty) q['q'] = query.trim();
    final res = await _get('/api/mobile/orders', query: q);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchOrderDetail(int orderId) async {
    final res = await _get('/api/mobile/orders/$orderId');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<List<dynamic>> fetchOrderFollowups(int orderId) async {
    final res = await _get('/api/mobile/orders/$orderId/followups');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<Map<String, dynamic>> addFollowup({
    required int orderId,
    required String stage,
    required String description,
    String nextFollowupDate = '',
    String imageBase64 = '',
  }) async {
    final payload = <String, dynamic>{
      'stage': stage,
      'description': description,
    };
    if (nextFollowupDate.trim().isNotEmpty) {
      payload['next_followup_date'] = nextFollowupDate.trim();
    }
    if (imageBase64.trim().isNotEmpty) {
      payload['image_base64'] = imageBase64.trim();
    }
    final res = await _post(
      '/api/mobile/orders/$orderId/followups',
      body: payload,
    );
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<List<dynamic>> fetchTasks({String query = ''}) async {
    final q = <String, String>{};
    if (query.trim().isNotEmpty) {
      q['q'] = query.trim();
    }
    final res = await _get('/api/mobile/tasks', query: q);
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<Map<String, dynamic>> createTask({
    required String title,
    required String note,
    required String scheduledAt,
  }) async {
    final res = await _post(
      '/api/mobile/tasks',
      body: {
        'title': title.trim(),
        'note': note.trim(),
        'scheduled_at': scheduledAt.trim(),
      },
    );
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<void> deleteTask(int id) async {
    await _post('/api/mobile/tasks/$id/delete', body: const {});
  }

  Future<List<dynamic>> fetchNotifications() async {
    final res = await _get('/api/mobile/notifications');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<Map<String, dynamic>> fetchNotificationStatus() async {
    final res = await _get('/api/mobile/notifications/status');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<void> markNotificationDone(int id) async {
    await _post('/api/mobile/notifications/$id/done', body: const {});
  }

  Future<void> confirmNotificationLocalFallback(
    int id, {
    required bool scheduled,
  }) async {
    await _post(
      '/api/mobile/notifications/$id/local-fallback',
      body: {'scheduled': scheduled},
    );
  }

  Future<Map<String, dynamic>> fetchInventorySummary() async {
    final res = await _get('/api/mobile/inventory/summary');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<List<dynamic>> fetchDiamondStock({String query = ''}) async {
    final q = <String, String>{};
    if (query.trim().isNotEmpty) q['q'] = query.trim();
    final res = await _get('/api/mobile/inventory/diamonds', query: q);
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchGoldStock({String query = ''}) async {
    final q = <String, String>{};
    if (query.trim().isNotEmpty) q['q'] = query.trim();
    final res = await _get('/api/mobile/inventory/gold', query: q);
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchStoneStock({String query = ''}) async {
    final q = <String, String>{};
    if (query.trim().isNotEmpty) q['q'] = query.trim();
    final res = await _get('/api/mobile/inventory/stones', query: q);
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchDiamondIssues() async {
    final res = await _get('/api/mobile/diamond/issues');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchDiamondReturns() async {
    final res = await _get('/api/mobile/diamond/returns');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchDiamondPurchases() async {
    final res = await _get('/api/mobile/diamond/purchases');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchGoldIssues() async {
    final res = await _get('/api/mobile/gold/issues');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchGoldReturns() async {
    final res = await _get('/api/mobile/gold/returns');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchGoldPurchases() async {
    final res = await _get('/api/mobile/gold/purchases');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchStoneIssues() async {
    final res = await _get('/api/mobile/stone/issues');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchStoneReturns() async {
    final res = await _get('/api/mobile/stone/returns');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchStonePurchases() async {
    final res = await _get('/api/mobile/stone/purchases');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchKarigars() async {
    final res = await _get('/api/mobile/lookups/karigars');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchVendors() async {
    final res = await _get('/api/mobile/lookups/vendors');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchLocations() async {
    final res = await _get('/api/mobile/lookups/locations');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchDiamondItems() async {
    final res = await _get('/api/mobile/lookups/diamond-items');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchGoldItems() async {
    final res = await _get('/api/mobile/lookups/gold-items');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchStoneItems() async {
    final res = await _get('/api/mobile/lookups/stone-items');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchDiamondIssueRefs() async {
    final res = await _get('/api/mobile/lookups/diamond-issues');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchGoldIssueRefs() async {
    final res = await _get('/api/mobile/lookups/gold-issues');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<List<dynamic>> fetchStoneIssueRefs() async {
    final res = await _get('/api/mobile/lookups/stone-issues');
    return (res['data'] as List?) ?? <dynamic>[];
  }

  Future<Map<String, dynamic>> createDiamondPurchase(
    Map<String, dynamic> payload,
  ) async {
    final res = await _post('/api/mobile/diamond/purchases', body: payload);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> createDiamondIssue(
    Map<String, dynamic> payload,
  ) async {
    final res = await _post('/api/mobile/diamond/issues', body: payload);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> createDiamondReturn(
    Map<String, dynamic> payload,
  ) async {
    final res = await _post('/api/mobile/diamond/returns', body: payload);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> createGoldPurchase(
    Map<String, dynamic> payload,
  ) async {
    final res = await _post('/api/mobile/gold/purchases', body: payload);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> createGoldIssue(
    Map<String, dynamic> payload,
  ) async {
    final res = await _post('/api/mobile/gold/issues', body: payload);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> createGoldReturn(
    Map<String, dynamic> payload,
  ) async {
    final res = await _post('/api/mobile/gold/returns', body: payload);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> createStonePurchase(
    Map<String, dynamic> payload,
  ) async {
    final res = await _post('/api/mobile/stone/purchases', body: payload);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> createStoneIssue(
    Map<String, dynamic> payload,
  ) async {
    final res = await _post('/api/mobile/stone/issues', body: payload);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> createStoneReturn(
    Map<String, dynamic> payload,
  ) async {
    final res = await _post('/api/mobile/stone/returns', body: payload);
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchDiamondIssueDetail(int id) async {
    final res = await _get('/api/mobile/diamond/issues/$id');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchDiamondReturnDetail(int id) async {
    final res = await _get('/api/mobile/diamond/returns/$id');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchDiamondPurchaseDetail(int id) async {
    final res = await _get('/api/mobile/diamond/purchases/$id');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchGoldIssueDetail(int id) async {
    final res = await _get('/api/mobile/gold/issues/$id');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchGoldReturnDetail(int id) async {
    final res = await _get('/api/mobile/gold/returns/$id');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchGoldPurchaseDetail(int id) async {
    final res = await _get('/api/mobile/gold/purchases/$id');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchStoneIssueDetail(int id) async {
    final res = await _get('/api/mobile/stone/issues/$id');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchStoneReturnDetail(int id) async {
    final res = await _get('/api/mobile/stone/returns/$id');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<Map<String, dynamic>> fetchStonePurchaseDetail(int id) async {
    final res = await _get('/api/mobile/stone/purchases/$id');
    return (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
  }

  Future<String> downloadPdf({
    required String path,
    required String fileName,
  }) async {
    final uri = Uri.parse('$_baseUrl$path');
    return _downloadPdfUri(uri: uri, fileName: fileName);
  }

  Future<String> downloadPdfFromUrl({
    required String url,
    required String fileName,
  }) async {
    final uri = Uri.parse(url);
    return _downloadPdfUri(uri: uri, fileName: fileName);
  }

  Future<String> _downloadPdfUri({
    required Uri uri,
    required String fileName,
  }) async {
    final response = await _safeRequest(
      () => _client.get(uri, headers: documentHeaders()),
    );

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(
        _extractDownloadError(response) ?? 'Unable to download PDF.',
      );
    }

    final contentType = response.headers['content-type']?.toLowerCase() ?? '';
    if (!contentType.contains('application/pdf')) {
      throw Exception(
        _extractDownloadError(response) ?? 'Server did not return a PDF file.',
      );
    }

    final bytes = response.bodyBytes;
    if (bytes.isEmpty) {
      throw Exception('Downloaded file is empty.');
    }

    final directory = await getApplicationDocumentsDirectory();
    final downloadDir = Directory(
      '${directory.path}${Platform.pathSeparator}downloads',
    );
    if (!await downloadDir.exists()) {
      await downloadDir.create(recursive: true);
    }
    final safeName = _resolvedFileName(response, fileName);
    final file = File('${downloadDir.path}${Platform.pathSeparator}$safeName');
    await file.writeAsBytes(bytes, flush: true);
    return file.path;
  }

  Future<Map<String, dynamic>> _get(
    String path, {
    Map<String, String>? query,
  }) async {
    final uri = Uri.parse(
      '$_baseUrl$path',
    ).replace(queryParameters: query == null || query.isEmpty ? null : query);
    final resp = await _safeRequest(
      () => _client.get(uri, headers: _headers(withAuth: true)),
    );
    return _decode(resp);
  }

  Future<Map<String, dynamic>> _post(
    String path, {
    required Map<String, dynamic> body,
    bool withAuth = true,
  }) async {
    final uri = Uri.parse('$_baseUrl$path');
    final resp = await _safeRequest(
      () => _client.post(
        uri,
        headers: _headers(withAuth: withAuth),
        body: jsonEncode(body),
      ),
    );
    return _decode(resp);
  }

  Future<http.Response> _safeRequest(
    Future<http.Response> Function() request,
  ) async {
    try {
      return await request().timeout(const Duration(seconds: 20));
    } on SocketException {
      throw Exception('Sorry app will not work without internet');
    } on http.ClientException {
      throw Exception('Sorry app will not work without internet');
    } on HandshakeException {
      throw Exception('Sorry app will not work without internet');
    } on TimeoutException {
      throw Exception('Request timed out. Please try again.');
    }
  }

  Map<String, String> _headers({required bool withAuth}) {
    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (withAuth && _token.trim().isNotEmpty) {
      headers['Authorization'] = 'Bearer $_token';
    }
    return headers;
  }

  Map<String, dynamic> _decode(http.Response response) {
    return decodeMobileApiResponse(response);
  }

  String _resolvedFileName(http.Response response, String fallback) {
    final disposition = response.headers['content-disposition'] ?? '';
    final match = RegExp(
      r'filename=\"?([^\";]+)\"?',
      caseSensitive: false,
    ).firstMatch(disposition);
    final headerName = match?.group(1)?.trim() ?? '';
    final candidate = headerName.isNotEmpty ? headerName : fallback.trim();
    final safeName = candidate.isEmpty ? 'document.pdf' : candidate;
    return safeName.toLowerCase().endsWith('.pdf') ? safeName : '$safeName.pdf';
  }

  String? _extractDownloadError(http.Response response) {
    try {
      final parsed = jsonDecode(response.body);
      if (parsed is Map<String, dynamic>) {
        return (parsed['message'] ?? parsed['error'] ?? '').toString().trim();
      }
    } catch (_) {}

    final body = response.body.trim();
    if (body.isEmpty) {
      return null;
    }
    if (body.startsWith('<!DOCTYPE') || body.startsWith('<html')) {
      return 'Server returned an HTML page instead of PDF.';
    }
    return body.length > 220 ? body.substring(0, 220) : body;
  }

  static String _normalizeBaseUrl(String value) {
    var normalized = value.trim();
    if (normalized.isEmpty) {
      return '';
    }
    if (!normalized.startsWith('http://') &&
        !normalized.startsWith('https://')) {
      normalized = 'https://$normalized';
    }
    if (normalized.endsWith('/')) {
      normalized = normalized.substring(0, normalized.length - 1);
    }
    return normalized;
  }
}

/// Decodes both the current API envelope and responses returned by older
/// deployments of the mobile API.
///
/// The current server responds with `{success, message, data}`. Some older
/// lookup endpoints returned their list/map directly, which made opening an
/// issuement form fail even though the response itself was valid JSON.
Map<String, dynamic> decodeMobileApiResponse(http.Response response) {
  final body = response.body.trim();
  final statusCode = response.statusCode;
  final isSuccessfulStatus = statusCode >= 200 && statusCode < 300;

  if (body.isEmpty) {
    throw Exception(
      'Server returned an empty response (HTTP $statusCode). Please try again.',
    );
  }

  dynamic decoded;
  try {
    decoded = jsonDecode(body);
  } on FormatException {
    final lowerBody = body.toLowerCase();
    final isHtml =
        lowerBody.startsWith('<!doctype') ||
        lowerBody.startsWith('<html') ||
        lowerBody.contains('<body');
    throw Exception(
      isHtml
          ? 'Server returned an HTML error page (HTTP $statusCode). Please update the server API and try again.'
          : 'Server returned an unreadable response (HTTP $statusCode). Please try again.',
    );
  }

  if (decoded is List) {
    if (!isSuccessfulStatus) {
      throw Exception('API request failed (HTTP $statusCode).');
    }
    return <String, dynamic>{'success': true, 'message': 'OK', 'data': decoded};
  }

  if (decoded is! Map) {
    throw Exception(
      'Server returned an unsupported response (HTTP $statusCode).',
    );
  }

  final parsed = decoded.map<String, dynamic>(
    (key, value) => MapEntry(key.toString(), value),
  );

  // Legacy endpoints returned a data map directly instead of an envelope.
  if (!parsed.containsKey('success')) {
    if (!isSuccessfulStatus) {
      final message = (parsed['message'] ?? parsed['error'] ?? '').toString();
      throw Exception(
        message.trim().isEmpty
            ? 'API request failed (HTTP $statusCode).'
            : message,
      );
    }
    return <String, dynamic>{'success': true, 'message': 'OK', 'data': parsed};
  }

  final successValue = parsed['success'];
  final success =
      successValue == true ||
      successValue == 1 ||
      successValue?.toString().toLowerCase() == 'true';
  if (!isSuccessfulStatus || !success) {
    final message = (parsed['message'] ?? parsed['error'] ?? '').toString();
    throw Exception(
      message.trim().isEmpty
          ? 'API request failed (HTTP $statusCode).'
          : message,
    );
  }

  return parsed;
}
