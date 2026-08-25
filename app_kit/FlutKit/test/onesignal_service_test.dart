import 'package:flutkit/jewellery_mobile/services/onesignal_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('OneSignalService external identity', () {
    test('normalizes email as the preferred external ID', () {
      expect(
        OneSignalService.externalIdForUser('  Shweta@AABHUSHAN.IN ', 'Shweta'),
        'shweta@aabhushan.in',
      );
    });

    test('uses normalized name when email is unavailable', () {
      expect(
        OneSignalService.externalIdForUser('', ' Divyanshu  Mishra '),
        'divyanshu_mishra',
      );
    });

    test('rejects empty and reserved fallback identities', () {
      expect(OneSignalService.externalIdForUser('', ' '), isNull);
      expect(OneSignalService.externalIdForUser('', 'unknown'), isNull);
    });
  });

  group('PushNotificationStatus', () {
    test('is ready only with permission, identity and subscription', () {
      const incomplete = PushNotificationStatus(
        initialized: true,
        permissionGranted: true,
        optedIn: true,
      );
      const ready = PushNotificationStatus(
        initialized: true,
        permissionGranted: true,
        optedIn: true,
        subscriptionId: 'subscription-id',
        pushToken: 'push-token',
        externalUserId: 'user@example.com',
      );

      expect(incomplete.isReady, isFalse);
      expect(ready.isReady, isTrue);
    });

    test('copyWith can clear stale identity and errors', () {
      const status = PushNotificationStatus(
        externalUserId: 'user@example.com',
        error: 'old error',
      );

      final cleared = status.copyWith(
        clearExternalUserId: true,
        clearError: true,
      );

      expect(cleared.externalUserId, isNull);
      expect(cleared.error, isNull);
    });
  });
}
