import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class AssessmentOperationHero extends StatelessWidget {
  const AssessmentOperationHero({
    required this.assessment,
    required this.eyebrow,
    super.key,
  });

  final AssessmentOperationHeader assessment;
  final String eyebrow;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(19),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.16),
          blurRadius: 16,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                '$eyebrow · ${assessment.code}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.accent,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            AssessmentToneBadge(
              label: assessment.statusLabel,
              tone: assessment.status == 'nonaktif' ? 'bahaya' : 'aktif',
              onDark: true,
            ),
          ],
        ),
        const SizedBox(height: 7),
        Text(
          assessment.name,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          '${assessment.subject} · Kelas ${assessment.grade} · ${capitalizeAssessment(assessment.semester)}',
          style: const TextStyle(color: Colors.white70, fontSize: 11),
        ),
        const SizedBox(height: 11),
        Wrap(
          spacing: 8,
          runSpacing: 6,
          children: [
            AssessmentInlineInfo(
              icon: Icons.schedule_rounded,
              label: '${assessment.durationMinutes} menit',
              onDark: true,
            ),
            AssessmentInlineInfo(
              icon: Icons.quiz_outlined,
              label: '${assessment.displayQuestionCount} soal',
              onDark: true,
            ),
            if (assessment.minimumScore != null)
              AssessmentInlineInfo(
                icon: Icons.workspace_premium_outlined,
                label: 'KKM ${assessment.minimumScore}',
                onDark: true,
              ),
          ],
        ),
      ],
    ),
  );
}

class AssessmentFilterCard extends StatelessWidget {
  const AssessmentFilterCard({
    required this.classes,
    required this.statuses,
    required this.selectedClassId,
    required this.selectedStatus,
    required this.classKey,
    required this.statusKey,
    required this.onClassChanged,
    required this.onStatusChanged,
    super.key,
  });

  final List<AssessmentFilterClass> classes;
  final List<AssessmentFilterOption> statuses;
  final int? selectedClassId;
  final String selectedStatus;
  final Key classKey;
  final Key statusKey;
  final ValueChanged<int?> onClassChanged;
  final ValueChanged<String> onStatusChanged;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final fields = [
            NusaDropdownField<int>(
              fieldKey: classKey,
              value: selectedClassId ?? 0,
              decoration: const InputDecoration(
                labelText: 'Kelas',
                prefixIcon: Icon(Icons.class_outlined),
              ),
              options: [
                const NusaDropdownOption(value: 0, label: 'Semua kelas'),
                for (final item in classes)
                  NusaDropdownOption(value: item.id, label: item.label),
              ],
              onChanged: (value) =>
                  onClassChanged(value == null || value == 0 ? null : value),
            ),
            NusaDropdownField<String>(
              fieldKey: statusKey,
              value: selectedStatus,
              decoration: const InputDecoration(
                labelText: 'Status',
                prefixIcon: Icon(Icons.filter_alt_outlined),
              ),
              options: [
                for (final item in statuses)
                  NusaDropdownOption(value: item.code, label: item.label),
              ],
              onChanged: (value) {
                if (value != null) onStatusChanged(value);
              },
            ),
          ];
          if (constraints.maxWidth < 390) {
            return Column(
              children: [fields.first, const SizedBox(height: 8), fields.last],
            );
          }
          return Row(
            children: [
              Expanded(child: fields.first),
              const SizedBox(width: 9),
              Expanded(child: fields.last),
            ],
          );
        },
      ),
    ),
  );
}

class AssessmentMetricsGrid extends StatelessWidget {
  const AssessmentMetricsGrid({required this.items, super.key});

  final List<AssessmentMetricData> items;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final columns = constraints.maxWidth >= 520 ? 4 : 2;
      final width = (constraints.maxWidth - ((columns - 1) * 9)) / columns;
      return Wrap(
        spacing: 9,
        runSpacing: 9,
        children: [
          for (final item in items)
            SizedBox(
              width: width,
              child: _MetricCard(item: item),
            ),
        ],
      );
    },
  );
}

class AssessmentMetricData {
  const AssessmentMetricData({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.item});

  final AssessmentMetricData item;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(11),
      child: Row(
        children: [
          Container(
            width: 35,
            height: 35,
            decoration: BoxDecoration(
              color: item.color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(item.icon, size: 18, color: item.color),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  item.label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 9,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class AssessmentToneBadge extends StatelessWidget {
  const AssessmentToneBadge({
    required this.label,
    required this.tone,
    this.onDark = false,
    super.key,
  });

  final String label;
  final String tone;
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    final color = assessmentToneColor(tone);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: onDark
            ? Colors.white.withValues(alpha: 0.12)
            : color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
        border: onDark ? Border.all(color: Colors.white24) : null,
      ),
      child: Text(
        label,
        style: TextStyle(
          color: onDark ? Colors.white : color,
          fontSize: 9,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class AssessmentInlineInfo extends StatelessWidget {
  const AssessmentInlineInfo({
    required this.icon,
    required this.label,
    this.onDark = false,
    super.key,
  });

  final IconData icon;
  final String label;
  final bool onDark;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(
        icon,
        size: 14,
        color: onDark ? NusaColors.accent : NusaColors.textSecondary,
      ),
      const SizedBox(width: 4),
      Text(
        label,
        style: TextStyle(
          color: onDark ? Colors.white70 : NusaColors.textSecondary,
          fontSize: 10,
          fontWeight: FontWeight.w600,
        ),
      ),
    ],
  );
}

class AssessmentOperationEmpty extends StatelessWidget {
  const AssessmentOperationEmpty({required this.message, super.key});

  final String message;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(32, 30, 32, 60),
    child: Column(
      children: [
        const Icon(
          Icons.inbox_outlined,
          size: 48,
          color: NusaColors.textSecondary,
        ),
        const SizedBox(height: 10),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(color: NusaColors.textSecondary),
        ),
      ],
    ),
  );
}

class AssessmentOperationError extends StatelessWidget {
  const AssessmentOperationError({
    required this.message,
    required this.onRetry,
    super.key,
  });

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: NusaColors.primary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

Color assessmentToneColor(String tone) => switch (tone) {
  'aktif' => NusaColors.success,
  'peringatan' => const Color(0xFF9A7000),
  'bahaya' => const Color(0xFFC62828),
  _ => NusaColors.textSecondary,
};

String assessmentDateTime(DateTime? value) {
  if (value == null) return '-';
  return '${value.day.toString().padLeft(2, '0')}/'
      '${value.month.toString().padLeft(2, '0')}/${value.year} '
      '${value.hour.toString().padLeft(2, '0')}:'
      '${value.minute.toString().padLeft(2, '0')}';
}

String assessmentTime(DateTime? value) {
  if (value == null) return '-';
  return '${value.hour.toString().padLeft(2, '0')}:'
      '${value.minute.toString().padLeft(2, '0')}';
}

String assessmentNumber(double? value) {
  if (value == null) return '-';
  return value == value.roundToDouble()
      ? value.toInt().toString()
      : value
            .toStringAsFixed(2)
            .replaceFirst(RegExp(r'0+$'), '')
            .replaceFirst(RegExp(r'\.$'), '');
}

String capitalizeAssessment(String value) => value.isEmpty
    ? value
    : '${value.substring(0, 1).toUpperCase()}${value.substring(1)}';
