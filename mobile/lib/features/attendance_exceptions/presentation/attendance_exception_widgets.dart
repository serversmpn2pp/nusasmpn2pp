import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/attendance_exceptions/domain/attendance_exception.dart';

AppBar exceptionAppBar(String title, {List<Widget>? actions}) => AppBar(
  toolbarHeight: 68,
  title: Text(
    title,
    maxLines: 2,
    style: const TextStyle(fontSize: 18, height: 1.15),
  ),
  actions: actions,
);

class ExceptionErrorView extends StatelessWidget {
  const ExceptionErrorView(this.error, {required this.retry, super.key});
  final Object error;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(20),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.cloud_off_outlined, size: 36),
        const SizedBox(height: 12),
        Text(exceptionError(error), textAlign: TextAlign.center),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: retry,
          icon: const Icon(Icons.refresh),
          label: const Text('Coba lagi'),
        ),
      ],
    ),
  );
}

class ExceptionStatus extends StatelessWidget {
  const ExceptionStatus(this.active, {this.compact = false, super.key});
  final bool active;
  final bool compact;
  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.symmetric(
      horizontal: compact ? 8 : 10,
      vertical: compact ? 4 : 6,
    ),
    decoration: BoxDecoration(
      color: active ? NusaColors.successSurface : NusaColors.dangerSurface,
      borderRadius: BorderRadius.circular(compact ? 20 : 8),
    ),
    child: Text(
      active ? 'Aktif' : 'Dibatalkan',
      style: TextStyle(
        color: active ? NusaColors.success : NusaColors.danger,
        fontWeight: FontWeight.w600,
        fontSize: compact ? 10 : null,
      ),
    ),
  );
}

class ExceptionFacts extends StatelessWidget {
  const ExceptionFacts(this.values, {super.key});
  final Map<String, Object?> values;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (_, c) => Wrap(
      spacing: 16,
      runSpacing: 16,
      children: [
        for (final e in values.entries)
          SizedBox(
            width: c.maxWidth >= 560
                ? (c.maxWidth - 32) / 3
                : (c.maxWidth - 16) / 2,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  e.key,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  exceptionText(e.value),
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

class ExceptionHeading extends StatelessWidget {
  const ExceptionHeading(this.title, {super.key});
  final String title;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 24, bottom: 12),
    child: Text(
      title,
      style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
    ),
  );
}
