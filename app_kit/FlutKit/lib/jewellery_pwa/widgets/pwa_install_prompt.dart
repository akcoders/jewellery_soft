import 'package:flutkit/jewellery_mobile/services/pwa_install_service.dart';
import 'package:flutter/material.dart';

Future<void> showPwaInstallPrompt(BuildContext context) async {
  final result = await PwaInstallService.promptInstall();
  if (!context.mounted) return;

  if (result.manual && !result.installed) {
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(Icons.install_mobile_outlined),
        title: Text(
          result.platform == 'ios'
              ? 'Install on iPhone / iPad'
              : 'Install Aabhushan ERP',
        ),
        content: Text(result.message),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Got it'),
          ),
        ],
      ),
    );
    return;
  }

  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(
        result.installed
            ? 'Aabhushan ERP installed successfully.'
            : 'Installation was not completed.',
      ),
    ),
  );
}
