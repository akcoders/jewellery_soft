import 'package:flutter/material.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class AppEmptyState extends StatelessWidget {
  const AppEmptyState({super.key, required this.title, this.message});

  final String title;
  final String? message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        margin: const EdgeInsets.all(AppSpacing.xl),
        constraints: const BoxConstraints(maxWidth: 340),
        padding: const EdgeInsets.all(AppSpacing.xl),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(AppRadius.xl),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: const BoxDecoration(
                color: AppColors.paleGold,
                shape: BoxShape.circle,
              ),
              child: const Icon(
                LucideIcons.package_open,
                size: 28,
                color: AppColors.plum,
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              title,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            if (message != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                message!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.textSecondary),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class AppErrorState extends StatelessWidget {
  const AppErrorState({
    super.key,
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final connectionError =
        message.toLowerCase().contains('internet') ||
        message.toLowerCase().contains('network') ||
        message.toLowerCase().contains('socket');
    return Center(
      child: Container(
        margin: const EdgeInsets.all(AppSpacing.xl),
        constraints: const BoxConstraints(maxWidth: 340),
        padding: const EdgeInsets.all(AppSpacing.xl),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(AppRadius.xl),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: AppColors.danger.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(
                connectionError
                    ? LucideIcons.wifi_off
                    : LucideIcons.circle_alert,
                size: 28,
                color: AppColors.danger,
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              connectionError
                  ? 'Connection interrupted'
                  : 'Something went wrong',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              connectionError
                  ? 'Check your internet connection and try again.'
                  : message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.textSecondary),
            ),
            const SizedBox(height: AppSpacing.lg),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(LucideIcons.refresh_cw, size: 18),
              label: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}
