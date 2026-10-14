# Android APK signing key

This file records the signing identity and recovery procedure only. The private
keystore and its credentials must never be added to this repository or written
in this file.

## Active signing identity

- App ID: `com.aabhushan.erp`
- Used by: Android 1.0.11, build 12, and every later release
- Signing certificate SHA-256:
  `AC5FCC988A3297F42D74E50A9B1A4CD36D323A9ACAA58FCAFF2CD104DE4A5004`
- Keystore on the current release workstation: `~/.android/debug.keystore`
- Gradle alias: `AndroidDebugKey` (the Android Gradle debug signing config)

The certificate fingerprint must match before publishing any update. Verify a
candidate APK with:

```bash
apksigner verify --print-certs path/to/app-release.apk
```

## Backup and recovery

1. Keep the original `~/.android/debug.keystore` unchanged on the release
   workstation. Do not delete it, regenerate it, or replace it with another
   machine's debug keystore.
2. Keep an encrypted backup of that exact keystore in the organization's
   approved password manager or encrypted offline backup. Restrict access to
   the release maintainer.
3. To build on another workstation, restore the exact keystore to
   `~/.android/debug.keystore` with owner-only file permissions before building.
4. Verify the restored keystore's certificate fingerprint and the built APK's
   fingerprint against the value above before publishing.
5. If the keystore is lost, do not silently create a replacement. A new signer
   cannot update installations signed with this identity; coordinate an
   uninstall/reinstall migration.

Keystore files and Android signing properties are excluded by the repository's
`.gitignore`. Never commit or share the keystore, passwords, or private key
material.

## Legacy signer notes

Android 1.0.9 (build 10) and 1.0.10 (build 11) were signed with an earlier
certificate, SHA-256
`04524F5032317D5CC42EC2E032EED012C8525EA5A51D59BB5E6A2F7E1EEC4061`, whose
keystore stayed on another workstation. From 1.0.11 (build 12) the active
identity above is used; phones on 1.0.9/1.0.10 must uninstall the old app
once and install the current APK. Copy the active `~/.android/debug.keystore`
to every release workstation so all builds share this certificate.


Builds 1.0.6 through 1.0.8 used a different signing certificate. The original
private key for that certificate was not found. Users moving from those builds
must uninstall the old app and install the 1.0.9 build 10 APK; subsequent
updates must continue using the active signing identity documented above.
