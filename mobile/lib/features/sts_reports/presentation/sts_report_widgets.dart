import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/sts_reports/domain/sts_reports.dart';

class StsReportNotice extends StatelessWidget {
  const StsReportNotice({
    required this.message,
    this.warning = false,
    super.key,
  });
  final String message;
  final bool warning;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: warning ? const Color(0xFFFFF8E8) : NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(
        color: warning ? const Color(0xFFEBD9AD) : NusaColors.outline,
      ),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          warning ? Icons.info_rounded : Icons.info_outline_rounded,
          size: 19,
          color: warning ? const Color(0xFF8A670C) : NusaColors.primary,
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(fontSize: 12, height: 1.5),
          ),
        ),
      ],
    ),
  );
}

class StsReportMetrics extends StatelessWidget {
  const StsReportMetrics({required this.values, this.dark = false, super.key});
  final List<({String label, String value})> values;
  final bool dark;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final scale = MediaQuery.textScalerOf(context).scale(12) / 12;
      final columns = scale > 1.4 || constraints.maxWidth < 270 ? 2 : 3;
      final width = (constraints.maxWidth - (columns - 1) * 8) / columns;
      return Wrap(
        spacing: 8,
        runSpacing: 12,
        children: [
          for (final item in values)
            SizedBox(
              width: width,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.value,
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      color: dark ? Colors.white : NusaColors.primary,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    item.label,
                    style: TextStyle(
                      fontSize: 11,
                      color: dark
                          ? Colors.white.withValues(alpha: 0.8)
                          : NusaColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
        ],
      );
    },
  );
}

class StsReportSummaryCard extends StatelessWidget {
  const StsReportSummaryCard({
    required this.kind,
    required this.report,
    super.key,
  });
  final StsReportKind kind;
  final StsReportContext? report;

  @override
  Widget build(BuildContext context) {
    final summary = report?.summary;
    final ledger = kind == StsReportKind.ledger;
    return Container(
      key: const Key('sts-reports-summary'),
      padding: const EdgeInsets.all(16),
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
              Icon(
                ledger ? Icons.leaderboard_rounded : Icons.description_rounded,
                color: NusaColors.accent,
                size: 23,
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  ledger ? 'Rekap & Ranking STS' : 'Kesiapan Rapor STS',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          StsReportMetrics(
            dark: true,
            values: [
              (label: 'Siswa', value: '${summary?.studentCount ?? 0}'),
              if (ledger) ...[
                (label: 'Masuk ranking', value: '${summary?.rankedCount ?? 0}'),
                (label: 'Lengkap final', value: '${summary?.finalCount ?? 0}'),
              ] else ...[
                (
                  label: 'Nilai lengkap',
                  value: '${summary?.completeCount ?? 0}',
                ),
                (label: 'Siap cetak', value: '${summary?.readyCount ?? 0}'),
              ],
            ],
          ),
          if (report != null) ...[
            const SizedBox(height: 14),
            Text(
              '${report!.className ?? 'Tingkat ${report!.grade}'} · '
              '${report!.academicYear ?? 'Tahun pelajaran belum tersedia'}',
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.85),
                fontSize: 11,
                height: 1.4,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class StsReportStudentCard extends StatelessWidget {
  const StsReportStudentCard({
    required this.student,
    required this.report,
    required this.kind,
    required this.onOpen,
    super.key,
  });
  final StsReportStudent student;
  final StsReportContext report;
  final StsReportKind kind;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final ledger = kind == StsReportKind.ledger;
    final status = ledger
        ? student.rank != null && report.temporaryRanking
              ? 'Ranking sementara'
              : student.rankingStatus
        : student.ready
        ? 'Siap cetak'
        : 'Belum siap cetak';
    return Card(
      key: Key('sts-reports-student-${student.membershipId}'),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 40,
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: NusaColors.surfaceBlue,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: ledger
                        ? Text(
                            student.rank == null ? '—' : '#${student.rank}',
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: NusaColors.primary,
                            ),
                          )
                        : const Icon(
                            Icons.article_outlined,
                            color: NusaColors.primary,
                            size: 21,
                          ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          student.name,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${student.className} · Absen '
                          '${student.attendanceNumber ?? '—'}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: NusaColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Icon(
                    Icons.chevron_right_rounded,
                    size: 22,
                    color: NusaColors.textSecondary,
                  ),
                ],
              ),
              const SizedBox(height: 11),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  StsReportBadge(
                    label: status,
                    warning: ledger
                        ? report.temporaryRanking || student.rank == null
                        : !student.ready,
                  ),
                  StsReportBadge(
                    label:
                        '${student.gradeCount}/${report.subjects.length} nilai',
                  ),
                  if (ledger && student.draftCount > 0)
                    StsReportBadge(
                      label: '${student.draftCount} draf',
                      warning: true,
                    ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                'Rata-rata ${stsNumber(student.average)} · '
                'Jumlah ${stsNumber(student.total)}',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              if (!ledger && student.sourceChanged) ...[
                const SizedBox(height: 6),
                const Text(
                  'Sumber kehadiran berubah; periksa ulang di web.',
                  style: TextStyle(fontSize: 11, color: NusaColors.danger),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class StsReportBadge extends StatelessWidget {
  const StsReportBadge({required this.label, this.warning = false, super.key});
  final String label;
  final bool warning;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: warning ? const Color(0xFFFFF5DD) : NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(8),
    ),
    child: Text(
      label,
      style: TextStyle(
        fontSize: 10,
        fontWeight: FontWeight.w700,
        color: warning ? const Color(0xFF886712) : NusaColors.primary,
      ),
    ),
  );
}

class StsReportGradeCard extends StatelessWidget {
  const StsReportGradeCard({
    required this.grade,
    required this.subjectName,
    required this.kind,
    super.key,
  });
  final StsReportGrade grade;
  final String subjectName;
  final StsReportKind kind;

  @override
  Widget build(BuildContext context) {
    // Rapor must never show a draft score, even if a mismatched server sends it.
    final hiddenDraft = kind == StsReportKind.report && grade.draft;
    final value = hiddenDraft ? null : grade.value;
    final description = hiddenDraft
        ? 'Belum tersedia (nilai belum final)'
        : grade.excluded
        ? 'Tidak mengikuti STS'
        : value == null
        ? 'Belum tersedia'
        : grade.draft
        ? 'Draf · ${grade.description}'
        : grade.description;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    subjectName,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(width: 14),
                Text(
                  stsNumber(value),
                  style: const TextStyle(
                    fontSize: 18,
                    color: NusaColors.primary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 5),
            Text(
              description,
              style: TextStyle(
                fontSize: 11,
                height: 1.5,
                color: grade.draft || value == null
                    ? const Color(0xFF886712)
                    : NusaColors.textSecondary,
              ),
            ),
            if (grade.exclusionReason?.isNotEmpty == true) ...[
              const SizedBox(height: 5),
              Text(
                'Alasan: ${grade.exclusionReason}',
                style: const TextStyle(fontSize: 11, height: 1.5),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
