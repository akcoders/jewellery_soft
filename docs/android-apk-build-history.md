# Aabhushan Android APK build history

This file records how the distributable Android APK is built, verified, and
published. Update it for every Android release.

## Application details

| Item | Value |
| --- | --- |
| Application | Aabhushan |
| Android package | `com.aabhushan.erp` |
| Flutter source | `app_kit/FlutKit` |
| APK output | `app_kit/FlutKit/build/app/outputs/flutter-apk/app-release.apk` |
| Update feed | `GET /api/mobile/app-release?platform=android` |
| Release configuration | `app/Config/MobileAppRelease.php` |
| Push provider | OneSignal, delivered to Android through Firebase Cloud Messaging |
| Notification channels | Ten bundled, versioned channels selected per employee |
| Notification sounds | `android/app/src/main/res/raw/aabhushan_*` |

## Release history

| Date | Version | Build | Git tag | APK SHA-256 | Main changes |
| --- | --- | ---: | --- | --- | --- |
| 2026-10-13 | 1.0.4 | 5 | `android-v1.0.4-build5` | `00830d798dc1c81ec3054b25580335a18ef3c97cfbb0c6efe9877b9d1cb5fd1d` | Versioned custom notification channel and automatic update alert |
| 2026-10-13 | 1.0.5 | 6 | `android-v1.0.5-build6` | `9abeeae69488de92c27d3ca33948a8cd3abe5cf16e286683b31245b3a44d5dcd` | Required verified in-app updater, employee camera/gallery policy, and owner-supplied notification sound |
| 2026-10-13 | 1.0.6 | 7 | `android-v1.0.6-build7` | `30746a5dac6deeac9c10a6e083bcc98c5022b49cd9531b91ac6bc7db2fdaab3f` | Ten per-user notification sounds with preview, persisted selection, and matching OneSignal/local-reminder channels |
| 2026-10-13 | 1.0.7 | 8 | `android-v1.0.7-build8` | `1c1dc0eed80338188db56746dd06c3caeb3b71c9b7c14ced688d25fbd9b854df` | Weight-only broken-diamond returns, direct Broken inventory posting, and CTS-authoritative issue utilization |
| 2026-10-13 | 1.0.8 | 9 | `android-v1.0.8-build9` | `753ddd526ca3e8276e81537714f96c057cf4d7b4f5722f4169f5e6694196bc1d` | Permanent Broken Diamond stock product with weight-only presentation in admin, PWA, and Android |
| 2026-10-13 | 1.0.9 | 10 | `android-v1.0.9-build10` | `60fdf57727d2cf45402de163907ef847d61ee6a6057250f83bcf4cc4a86cab9c` | Order-family follow-up ownership, follow-up approval quality controls, and detailed transaction views |
| 2026-10-13 | 1.0.10 | 11 | `android-v1.0.10-build11` | `26c42a7b0aa409dbedc6daae3fbd5d3e96ccff3321756a0967d044480a9b84b2` | Fractional performance points, organized Android PDF folders, parent order mapping, and unified child follow-up approvals |
| 2026-10-14 | 1.0.11 | 12 | `android-v1.0.11-build12` | `c3c99e6b53d88c8d085c029502838d803a4159e3c7804268e27fbc3bf7c1023a` | Order-wise stone issuement (gold stays free-flow), complete order details in collapsible sections, stone issued per order. Signed with this workstation's `~/.android/debug.keystore` (certificate SHA-256 `ac5fcc988a3297f42d74e50a9b1a4cd36d323a9acaa58fcaff2cd104de4a5004`), which differs from the `key.md` identity. **Withdrawn 2026-10-14:** cannot update 1.0.9/1.0.10 installs (signer mismatch); the update feed was rolled back to 1.0.10 build 11. Rebuild with the original `04524F…` keystore before the next release |
| 2026-10-14 | 1.0.12 | 13 | `android-v1.0.12-build13` | `9ae9f2427d72ef3a18fce82263cafd9df2fd11da0ab90a071ae2424410db09b7` | Team performance for admins, 200-point monthly target and reward points, small-phone layout fixes. Signed with the new active identity `AC5FCC…` (see `key.md`); 1.0.9/1.0.10 installs must uninstall once, 1.0.11 updates normally |
| 2026-10-19 | 1.0.13 | 14 | `android-v1.0.13-build14` | `73b4bb1bde02496f802c4c83cf0d0a693f215604809368e7afd450238922a539` | Android workspace parity for order receiving, completion ratings, packing lists, delivery challans and work requests |
| 2026-10-29 | 1.0.14 | 15 | `android-v1.0.14-build15` | `71a47cd7f3bc805e8bb2873dba374d5b11f341d287d550025184dfd1328f6dbd` | Camera/gallery selection outside follow-ups, photo previews and replacement, ivory/emerald/gold UI with offline Manrope, bottom navigation, clearer order/material cards, persistent issuance save action, and completed-order issuance until actual receiving |
| 2026-10-29 | 1.0.15 | 16 | `android-v1.0.15-build16` | `bd61a24ffab320bef3ddd04c9b1eca79dcf2597b5cac34b87936da16bc834d0d` | Ice-blue, lavender and sapphire UI across native/PWA, diamond details and static texture, refined cards/forms/photo controls, accessible motion and contrast, Ready-only follow-ups with completion after receiving |

### 1.0.15 build 16 verification and deployment

- APK size: 68,767,642 bytes (68.8 MB). Package `com.aabhushan.erp`, minimum SDK 24 and target SDK 36 remain unchanged.
- APK v2 signature verified with certificate SHA-256 `ac5fcc988a3297f42d74e50a9b1a4cd36d323a9acaa58fcaff2cd104de4a5004`, matching the preceding active builds.
- All 98 Flutter tests pass. Phone layouts at 320 px with enlarged text, standard 390 px, and desktop at 1280 px were rendered and inspected. Contrast and reduced-motion regressions pass. Analyzer reports existing style infos and no errors or warnings.
- Six PHP release/update-feed tests pass (63 assertions). The built PWA was opened in Chrome at 390 px, its first frame and theme verified, and its login screenshot inspected with no horizontal overflow.
- Native/PWA share one palette and theme. Manrope body and Cormorant Garamond display fonts are bundled with their licenses; texture and diamond details use static vector drawing. Entry animations are finite and honor reduced motion.
- Follow-ups end at Ready; completed status follows receiving. This build includes the already committed server validation and admin correction page at `/admin/orders/ready-stage`. Deploy those PHP changes with the update feed; no new database migration is required for this release.
- Camera/gallery permissions and follow-up photo policy remain unchanged. Existing required fields, receiving locks and submission behavior are preserved.
- The matching PWA archive and generated `public/pwa/` staff app accompany this release. Preserve the separate `public/pwa/customer/` portal during deployment.

### 1.0.14 build 15 verification and deployment

- APK size: 68,340,156 bytes (68.3 MB). Android package remains `com.aabhushan.erp`, version code 15, minimum SDK 24, target SDK 36.
- APK v2 signature verified. Signing certificate SHA-256 remains `ac5fcc988a3297f42d74e50a9b1a4cd36d323a9acaa58fcaff2cd104de4a5004`, matching builds 13 and 14.
- All 75 Flutter tests pass, including narrow-phone/enlarged-text layout, photo chooser, required-field navigation and completed/received order cases. Analyzer reports existing style infos and no errors or warnings. Dashboard and order screenshots were rendered and inspected.
- The completed-order rule requires deploying the updated PHP API/services. No new database migration is needed. Receiving is checked again before an approved mixed-material voucher writes any material records; gold remains free-flow.
- Removed 622 unreachable demo assets from the release bundle; all three Android ABIs, notification sounds, icons, workspace logo and font licenses remain included.
- Follow-up camera/gallery permissions keep their existing policy.
- A fresh PWA build and upload archive accompany this release. Keep the separate `public/pwa/customer/` portal icons when deploying the staff PWA.
- The PHP unit suite has one pre-existing failure in `ArattaiRuntimeDiagnosticsTest::testConversationalPayloadsFollowLiveArattaiFieldRules`: its expected quick replies omit the IDs already emitted by the unchanged service. Issuance, photo-flow and release-feed regression tests pass.

## Prerequisites

- Flutter SDK compatible with the `pubspec.lock` file.
- JDK 17. This workstation uses
  `/opt/homebrew/Cellar/openjdk@17/17.0.20.1/libexec/openjdk.jdk/Contents/Home`.
- Android SDK command-line tools and build-tools 36.0.0.
- PHP dependencies installed with Composer for the server tests.
- GitHub CLI authenticated for `akcoders/jewellery_soft` when publishing.

Do not put the OneSignal REST API key, keystore password, or any other secret in
this file or in Git.

## Build procedure

1. Update `version:` in `app_kit/FlutKit/pubspec.yaml`. The value before `+` is
   the Android version name; the value after `+` is the monotonically increasing
   Android version code.
2. Update the version, version code, release URL, required flag, size label,
   message, and later the APK checksum in `app/Config/MobileAppRelease.php`.
3. When changing a notification sound, update its `res/raw/aabhushan_*`
   resource and increment that sound's channel ID in the server and Flutter
   catalogs plus `AabhushanApplication.kt`. Android keeps a channel's original
   sound permanently, so reusing an old channel ID will continue playing the
   old/default sound on existing devices.
4. Resolve dependencies and format the changed Dart files:

   ```bash
   cd app_kit/FlutKit
   flutter pub get
   dart format <changed-dart-files>
   ```

5. Run automated checks:

   ```bash
   cd app_kit/FlutKit
   flutter test --no-pub

   cd ../..
   ./vendor/bin/phpunit
   git diff --check
   ```

6. Build the release APK:

   ```bash
   cd app_kit/FlutKit
   JAVA_HOME=/opt/homebrew/Cellar/openjdk@17/17.0.20.1/libexec/openjdk.jdk/Contents/Home \
   PATH=/opt/homebrew/Cellar/openjdk@17/17.0.20.1/libexec/openjdk.jdk/Contents/Home/bin:$PATH \
   flutter build apk --release --no-pub
   ```

7. Record the size and SHA-256, then copy both values into
   `MobileAppRelease.php` and this history table:

   ```bash
   stat -f '%z' build/app/outputs/flutter-apk/app-release.apk
   shasum -a 256 build/app/outputs/flutter-apk/app-release.apk
   ```

8. Verify the manifest and signature before release:

   ```bash
   apkanalyzer manifest version-name build/app/outputs/flutter-apk/app-release.apk
   apkanalyzer manifest version-code build/app/outputs/flutter-apk/app-release.apk
   apkanalyzer manifest permissions build/app/outputs/flutter-apk/app-release.apk
   apksigner verify --verbose build/app/outputs/flutter-apk/app-release.apk
   ```

9. Commit and push the source, create the matching Git tag/release, and upload
   the APK using the exact filename configured in `MobileAppRelease.php`.
10. Verify the public release with a real HTTP GET, compare its SHA-256 with the
    configured digest, and call the update endpoint from a build with a lower
    version code.

## Signing compatibility

The current Android Gradle configuration signs release APKs with the project's
existing debug signing certificate. This is not ideal for a public production
app, but it must remain unchanged for these sideloaded updates: Android only
installs an update when its signing certificate matches the installed app.
Changing to a production keystore requires a planned migration and normally a
one-time uninstall/reinstall for this distribution channel. Never generate a
new signing key during a routine release.

## Required update behavior

The app compares its native build number with the server release feed. When a
newer release has `required: true`, the update dialog cannot be dismissed and
the rest of the app remains inaccessible. The app downloads the APK in its own
progress UI, verifies the configured SHA-256, and opens Android's package
installer. Android intentionally owns the final install confirmation and the
post-install **Open** action; a normal sideloaded app cannot silently approve
installation or force itself to relaunch.

## Push delivery timing

Application events are stored in `mobile_push_notifications`. Real-time events
make a short immediate OneSignal attempt after the business record is committed.
Failures remain persisted for the once-per-minute `mobile:run-notification-cycle`
worker; future and hourly reminders also remain on that worker. After OneSignal
accepts a push, Android Doze mode, battery optimization, network state, a
force-stopped app, or vendor firmware can still add device-side delay.

Firebase Cloud Messaging is the underlying Android transport; using FCM directly
would remove the OneSignal layer but would require the application to manage
tokens, refresh, targeting, retries, and delivery diagnostics. The current
hybrid path keeps OneSignal for immediate event delivery and keeps cron as the
safety net for scheduled reminders and retries.
