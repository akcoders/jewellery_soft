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
