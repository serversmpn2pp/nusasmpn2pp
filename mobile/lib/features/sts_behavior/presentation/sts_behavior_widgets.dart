import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/sts_behavior/domain/sts_behavior.dart';

String stsDisplayDate(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value;
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

class StsBehaviorSummary extends StatelessWidget {
  const StsBehaviorSummary({
    required this.report,
    required this.onInfo,
    super.key,
  });

  final StsBehaviorReport? report;
  final VoidCallback onInfo;

  @override
  Widget build(BuildContext context) {
    final total = report?.students.length ?? 0;
    final ready =
        report?.students.where((student) => student.ready).length ?? 0;
    return Container(
      key: const Key('sts-summary'),
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.fact_check_rounded,
                color: NusaColors.accent,
                size: 22,
              ),
              const SizedBox(width: 9),
              const Expanded(
                child: Text(
                  'Pembinaan & Lampiran Rapor',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              IconButton(
                key: const Key('sts-info'),
                tooltip: 'Tentang lampiran dan privasi',
                onPressed: onInfo,
                icon: const Icon(Icons.info_outline_rounded, size: 21),
                color: Colors.white,
                visualDensity: VisualDensity.compact,
              ),
            ],
          ),
          const SizedBox(height: 5),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _SummaryNumber(label: 'Siswa', value: total),
              _SummaryNumber(label: 'Siap', value: ready),
              _SummaryNumber(
                label: 'Perlu diperiksa',
                value: total - ready,
                highlight: total > ready,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Semantics(
            label: '$ready dari $total lampiran siap',
            child: ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: total == 0 ? 0 : ready / total,
                minHeight: 4,
                color: NusaColors.accent,
                backgroundColor: Colors.white.withValues(alpha: 0.16),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryNumber extends StatelessWidget {
  const _SummaryNumber({
    required this.label,
    required this.value,
    this.highlight = false,
  });

  final String label;
  final int value;
  final bool highlight;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: highlight ? NusaColors.accent : Colors.white,
            fontSize: 21,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.8),
            fontSize: 10,
          ),
        ),
      ],
    ),
  );
}

class StsBehaviorPeriod extends StatelessWidget {
  const StsBehaviorPeriod({required this.report, super.key});

  final StsBehaviorReport report;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      _DateLine(
        icon: Icons.date_range_rounded,
        text: '${stsDisplayDate(report.start)} – ${stsDisplayDate(report.end)}',
      ),
      const SizedBox(height: 5),
      _DateLine(
        icon: Icons.event_available_rounded,
        text: 'Rapor: ${stsDisplayDate(report.reportDate)}',
      ),
    ],
  );
}

class _DateLine extends StatelessWidget {
  const _DateLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 16, color: NusaColors.textSecondary),
      const SizedBox(width: 7),
      Expanded(
        child: Text(
          text,
          style: const TextStyle(
            fontSize: 11.5,
            color: NusaColors.textSecondary,
          ),
        ),
      ),
    ],
  );
}

class StsBehaviorNotice extends StatelessWidget {
  const StsBehaviorNotice({
    required this.message,
    this.title,
    this.warning = false,
    this.error = false,
    super.key,
  });

  final String message;
  final String? title;
  final bool warning, error;

  @override
  Widget build(BuildContext context) {
    final color = error
        ? NusaColors.danger
        : warning
        ? const Color(0xFF866018)
        : NusaColors.primary;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: error
            ? NusaColors.dangerSurface
            : warning
            ? const Color(0xFFFFF8E8)
            : NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: color.withValues(alpha: 0.16)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            error
                ? Icons.error_outline_rounded
                : warning
                ? Icons.schedule_rounded
                : Icons.shield_outlined,
            color: color,
            size: 20,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (title != null) ...[
                  Text(
                    title!,
                    style: TextStyle(
                      color: color,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 3),
                ],
                Text(
                  message,
                  style: TextStyle(color: color, fontSize: 11.5, height: 1.4),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class StsBehaviorSectionTitle extends StatelessWidget {
  const StsBehaviorSectionTitle({required this.title, this.detail, super.key});

  final String title;
  final String? detail;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Expanded(
        child: Text(
          title,
          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
        ),
      ),
      if (detail != null) ...[
        const SizedBox(width: 8),
        Text(
          detail!,
          style: const TextStyle(fontSize: 11, color: NusaColors.textSecondary),
        ),
      ],
    ],
  );
}

class StsBehaviorStudentCard extends StatelessWidget {
  const StsBehaviorStudentCard({
    required this.student,
    required this.canReview,
    required this.selected,
    required this.onSelected,
    required this.onOpen,
    super.key,
  });

  final StsBehaviorStudent student;
  final bool canReview, selected;
  final ValueChanged<bool> onSelected;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 10),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(18),
      side: BorderSide(
        color: selected ? NusaColors.primary : NusaColors.outline,
        width: selected ? 1.5 : 1,
      ),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      key: Key('sts-student-${student.memberId}'),
      onTap: onOpen,
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            StsBehaviorStudentHeading(
              student: student,
              trailing: canReview
                  ? Checkbox(
                      key: Key('sts-select-${student.memberId}'),
                      value: selected,
                      onChanged: (value) => onSelected(value == true),
                    )
                  : const Icon(
                      Icons.chevron_right_rounded,
                      color: NusaColors.textSecondary,
                    ),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                StsBehaviorBadge(
                  label: student.ready
                      ? 'Siap · sudah diperiksa'
                      : 'Perlu pemeriksaan',
                  color: student.ready
                      ? const Color(0xFF237B3D)
                      : const Color(0xFF866018),
                  icon: student.ready
                      ? Icons.check_circle_outline_rounded
                      : Icons.pending_actions_rounded,
                ),
                StsBehaviorBadge(
                  label: '${student.incidentCount} kejadian',
                  color: NusaColors.primary,
                ),
              ],
            ),
            const SizedBox(height: 12),
            StsBehaviorPoints(student: student),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: Text(
                    canReview ? 'Buka dan periksa' : 'Lihat ringkasan',
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const Icon(
                  Icons.arrow_forward_rounded,
                  size: 16,
                  color: NusaColors.primary,
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class StsBehaviorStudentHeading extends StatelessWidget {
  const StsBehaviorStudentHeading({
    required this.student,
    this.trailing,
    super.key,
  });

  final StsBehaviorStudent student;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: student.ready
              ? NusaColors.successSurface
              : NusaColors.surfaceBlue,
          borderRadius: BorderRadius.circular(13),
        ),
        child: Icon(
          student.ready ? Icons.task_alt_rounded : Icons.fact_check_rounded,
          color: student.ready ? const Color(0xFF237B3D) : NusaColors.primary,
          size: 23,
        ),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              student.name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 3),
            Text(
              'Nomor absen ${student.attendanceNumber ?? '—'}',
              style: const TextStyle(
                fontSize: 10.5,
                color: NusaColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
      ?trailing,
    ],
  );
}

class StsBehaviorPoints extends StatelessWidget {
  const StsBehaviorPoints({required this.student, super.key});

  final StsBehaviorStudent student;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(vertical: 9, horizontal: 6),
    decoration: BoxDecoration(
      color: NusaColors.background,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _PointNumber(label: 'Poin masuk', value: student.pointsIn),
        _PointNumber(label: 'Pengurangan', value: student.pointsOut),
        _PointNumber(
          label: 'Saldo poin',
          value: student.balance,
          emphasis: true,
        ),
      ],
    ),
  );
}

class _PointNumber extends StatelessWidget {
  const _PointNumber({
    required this.label,
    required this.value,
    this.emphasis = false,
  });

  final String label;
  final int value;
  final bool emphasis;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: emphasis ? NusaColors.primary : NusaColors.textPrimary,
            fontWeight: FontWeight.w800,
            fontSize: 14,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontSize: 9.5,
            color: NusaColors.textSecondary,
          ),
        ),
      ],
    ),
  );
}

class StsBehaviorBadge extends StatelessWidget {
  const StsBehaviorBadge({
    required this.label,
    required this.color,
    this.icon,
    super.key,
  });

  final String label;
  final Color color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.08),
      border: Border.all(color: color.withValues(alpha: 0.18)),
      borderRadius: BorderRadius.circular(9),
    ),
    child: Text.rich(
      TextSpan(
        children: [
          if (icon != null)
            WidgetSpan(
              alignment: PlaceholderAlignment.middle,
              child: Padding(
                padding: const EdgeInsets.only(right: 4),
                child: Icon(icon, size: 13, color: color),
              ),
            ),
          TextSpan(text: label),
        ],
      ),
      style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700),
    ),
  );
}
