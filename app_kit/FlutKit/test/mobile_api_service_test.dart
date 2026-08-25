import 'package:flutter_test/flutter_test.dart';
import 'package:flutkit/jewellery_mobile/services/mobile_api_service.dart';
import 'package:http/http.dart' as http;

void main() {
  group('decodeMobileApiResponse', () {
    test('keeps the current API envelope', () {
      final result = decodeMobileApiResponse(
        http.Response('{"success":true,"message":"OK","data":[{"id":1}]}', 200),
      );

      expect(result['success'], isTrue);
      expect(result['data'], isA<List<dynamic>>());
    });

    test('wraps a legacy lookup list as successful data', () {
      final result = decodeMobileApiResponse(
        http.Response('[{"id":1,"name":"Gold"}]', 200),
      );

      expect(result['success'], isTrue);
      expect(result['data'], isA<List<dynamic>>());
      expect((result['data'] as List).first['name'], 'Gold');
    });

    test('wraps a legacy response map as successful data', () {
      final result = decodeMobileApiResponse(
        http.Response('{"token":"mobile-token"}', 200),
      );

      expect(result['success'], isTrue);
      expect((result['data'] as Map)['token'], 'mobile-token');
    });

    test('uses the API error message', () {
      expect(
        () => decodeMobileApiResponse(
          http.Response('{"success":false,"message":"Invalid item"}', 422),
        ),
        throwsA(
          isA<Exception>().having(
            (error) => error.toString(),
            'message',
            contains('Invalid item'),
          ),
        ),
      );
    });

    test('describes an HTML response instead of reporting invalid JSON', () {
      expect(
        () => decodeMobileApiResponse(
          http.Response('<!doctype html><html><body>Error</body></html>', 500),
        ),
        throwsA(
          isA<Exception>().having(
            (error) => error.toString(),
            'message',
            contains('HTML error page'),
          ),
        ),
      );
    });

    test('describes an empty response', () {
      expect(
        () => decodeMobileApiResponse(http.Response('', 502)),
        throwsA(
          isA<Exception>().having(
            (error) => error.toString(),
            'message',
            contains('empty response'),
          ),
        ),
      );
    });
  });
}
