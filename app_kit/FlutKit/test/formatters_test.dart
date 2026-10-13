import 'package:flutter_test/flutter_test.dart';
import 'package:flutkit/jewellery_mobile/utils/formatters.dart';

void main() {
  group('AppFormatters.points', () {
    test('preserves fractional performance points', () {
      expect(AppFormatters.points(0.4), '0.4');
      expect(AppFormatters.points(0.25), '0.25');
    });

    test('omits unnecessary decimal zeroes', () {
      expect(AppFormatters.points(2), '2');
      expect(AppFormatters.points(2.5), '2.5');
    });
  });
}
