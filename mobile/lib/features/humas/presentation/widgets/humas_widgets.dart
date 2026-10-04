import 'package:flutter/material.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/humas/domain/humas.dart';

String humasError(Object error) =>
    error is ValidationException && error.errors.isNotEmpty
    ? error.errors.values.expand((messages) => messages).join('\n')
    : error is AppException
    ? error.message
    : 'Data Humas belum dapat dimuat. Silakan coba lagi.';

class HumasErrorView extends StatelessWidget {
  const HumasErrorView(this.error, {required this.onRetry, super.key});
  final Object error;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 42,
            color: NusaColors.textSecondary,
          ),
          const SizedBox(height: 16),
          Text(humasError(error), textAlign: TextAlign.center),
          const SizedBox(height: 16),
          OutlinedButton(onPressed: onRetry, child: const Text('Coba lagi')),
        ],
      ),
    ),
  );
}

class HumasCard extends StatelessWidget {
  const HumasCard({
    required this.title,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.children = const [],
    super.key,
  });
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  if (trailing != null) ...[
                    const SizedBox(width: 8),
                    trailing!,
                  ],
                ],
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 8),
                Text(
                  subtitle!,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    height: 1.45,
                  ),
                ),
              ],
              ...children,
            ],
          ),
        ),
      ),
    ),
  );
}

class HumasBadge extends StatelessWidget {
  const HumasBadge(this.label, {super.key});
  final String label;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(
      label,
      style: const TextStyle(fontSize: 12, color: NusaColors.primary),
    ),
  );
}

class HumasFacts extends StatelessWidget {
  const HumasFacts(this.values, {super.key});
  final Map<String, Object?> values;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      for (final entry in values.entries)
        if (entry.value != null && '${entry.value}'.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 10),
            child: Text(
              '${entry.key}: ${humasText(entry.value)}',
              style: const TextStyle(height: 1.5),
            ),
          ),
    ],
  );
}

Future<void> humasRun(
  BuildContext context,
  Future<void> Function() action,
) async {
  try {
    await action();
  } catch (error) {
    if (context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(humasError(error))));
    }
  }
}
