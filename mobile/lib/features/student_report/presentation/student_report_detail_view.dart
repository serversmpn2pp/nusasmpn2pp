import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_report/application/student_report_controller.dart';
import 'package:nusa/features/student_report/data/student_report_evidence_saver.dart';
import 'package:nusa/features/student_report/domain/student_report.dart';

class StudentReportDetailView extends ConsumerStatefulWidget {
  const StudentReportDetailView({
    required this.reportId,
    this.scope = StudentReportScope.all,
    super.key,
  });

  final int reportId;
  final StudentReportScope scope;

  @override
  ConsumerState<StudentReportDetailView> createState() =>
      _StudentReportDetailViewState();
}

class _StudentReportDetailViewState
    extends ConsumerState<StudentReportDetailView> {
  int? _downloadingEvidenceId;

  @override
  Widget build(BuildContext context) {
    final detail = widget.scope == StudentReportScope.guardianStudents
        ? ref.watch(guardianStudentReportDetailProvider(widget.reportId))
        : ref.watch(studentReportDetailProvider(widget.reportId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Laporan Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: detail.isLoading ? null : _invalidateDetail,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: _errorMessage(error),
            onRetry: _invalidateDetail,
          ),
          data: _buildDetail,
        ),
      ),
    );
  }

  Widget _buildDetail(StudentReportDetail detail) {
    final report = detail.report;
    return RefreshIndicator(
      onRefresh: () async {
        _invalidateDetail();
        if (widget.scope == StudentReportScope.guardianStudents) {
          await ref.read(
            guardianStudentReportDetailProvider(widget.reportId).future,
          );
        } else {
          await ref.read(studentReportDetailProvider(widget.reportId).future);
        }
      },
      child: ListView(
        key: const Key('student-report-detail-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
        children: [
          _DetailHero(report: report),
          const SizedBox(height: 10),
          _DetailSection(
            icon: Icons.info_outline_rounded,
            title: 'Informasi Laporan',
            child: Column(
              children: [
                _FactRow(label: 'Siswa', value: report.student?.name ?? '-'),
                _FactRow(
                  label: 'NIS / NISN',
                  value:
                      '${report.student?.nis ?? '-'} / ${report.student?.nisn ?? '-'}',
                ),
                _FactRow(
                  label: 'Kelas',
                  value:
                      '${report.schoolClass?.name ?? '-'} · ${report.academicYear?.name ?? '-'}',
                ),
                _FactRow(
                  label: 'Waktu',
                  value:
                      '${_dateLabel(report.incidentDate)}${report.incidentTime == null ? '' : ' · ${report.incidentTime} WIB'}',
                ),
                _FactRow(label: 'Tempat', value: report.place ?? '-'),
                _FactRow(label: 'Pelapor', value: report.reporter?.name ?? '-'),
                _FactRow(
                  label: 'Wali kelas',
                  value: report.homeroomTeacher?.name ?? '-',
                ),
                _FactRow(
                  label: 'Guru wali',
                  value: report.studentAdvisor?.name ?? '-',
                  divider: false,
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          _TextSection(
            icon: Icons.description_outlined,
            title: 'Kronologi',
            text: report.chronology,
          ),
          const SizedBox(height: 10),
          _TextSection(
            icon: Icons.health_and_safety_outlined,
            title: 'Tindakan Awal',
            text: report.initialAction,
          ),
          if (detail.violations.isNotEmpty) ...[
            const SizedBox(height: 10),
            _DetailSection(
              icon: Icons.gavel_rounded,
              title: 'Butir Pelanggaran',
              badge: '${report.totalPoints} poin',
              child: Column(
                children: [
                  for (
                    var index = 0;
                    index < detail.violations.length;
                    index++
                  ) ...[
                    _ViolationItem(item: detail.violations[index]),
                    if (index < detail.violations.length - 1)
                      const Divider(height: 18),
                  ],
                ],
              ),
            ),
          ],
          if (detail.counselingDecisions.isNotEmpty ||
              detail.approvals.isNotEmpty) ...[
            const SizedBox(height: 10),
            _DetailSection(
              icon: Icons.verified_outlined,
              title: 'Keputusan Pemeriksaan',
              child: Column(
                children: [
                  for (final decision in detail.counselingDecisions)
                    _ProcessItem(
                      title: decision.resultLabel,
                      subtitle:
                          '${decision.officer ?? 'BK'} · ${_dateTimeLabel(decision.processedAt)}',
                      description: decision.note,
                      color: NusaColors.primary,
                    ),
                  for (final approval in detail.approvals)
                    _ProcessItem(
                      title: '${approval.typeLabel}: ${approval.decisionLabel}',
                      subtitle:
                          '${approval.officer ?? '-'} · ${_dateTimeLabel(approval.processedAt)}',
                      description: approval.note,
                      color: NusaColors.success,
                    ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 10),
          _DetailSection(
            icon: Icons.attach_file_rounded,
            title: 'Bukti Pendukung',
            badge: '${detail.evidence.length} file',
            child: detail.evidence.isEmpty
                ? const _EmptySection(text: 'Belum ada bukti pendukung.')
                : Column(
                    children: [
                      for (final evidence in detail.evidence)
                        ListTile(
                          key: Key('student-report-evidence-${evidence.id}'),
                          contentPadding: EdgeInsets.zero,
                          leading: Icon(
                            evidence.type == 'foto'
                                ? Icons.image_outlined
                                : Icons.picture_as_pdf_outlined,
                            color: NusaColors.primary,
                          ),
                          title: Text(
                            evidence.fileName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          subtitle: Text(
                            '${evidence.sizeLabel}${_filled(evidence.note) ? ' · ${evidence.note}' : ''}',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          trailing: IconButton(
                            key: Key(
                              'download-student-report-evidence-${evidence.id}',
                            ),
                            tooltip: 'Unduh bukti',
                            onPressed: _downloadingEvidenceId == null
                                ? () => _downloadEvidence(evidence)
                                : null,
                            icon: _downloadingEvidenceId == evidence.id
                                ? const SizedBox.square(
                                    dimension: 19,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.download_rounded),
                          ),
                        ),
                    ],
                  ),
          ),
          const SizedBox(height: 10),
          _DetailSection(
            icon: Icons.visibility_outlined,
            title: 'Saksi Kejadian',
            badge: '${detail.witnesses.length} saksi',
            child: detail.witnesses.isEmpty
                ? const _EmptySection(text: 'Belum ada saksi yang dicatat.')
                : Column(
                    children: [
                      for (final witness in detail.witnesses)
                        _ProcessItem(
                          title: witness.name,
                          subtitle:
                              '${witness.typeLabel} · ${_dateTimeLabel(witness.recordedAt)}',
                          description: witness.statement,
                          color: Colors.teal,
                        ),
                    ],
                  ),
          ),
          if (detail.clarifications.isNotEmpty) ...[
            const SizedBox(height: 10),
            _DetailSection(
              icon: Icons.record_voice_over_outlined,
              title: 'Klarifikasi Siswa',
              child: Column(
                children: [
                  for (final item in detail.clarifications)
                    _ProcessItem(
                      title: item.methodLabel,
                      subtitle:
                          '${item.recordedBy ?? '-'} · ${_dateTimeLabel(item.deliveredAt)}',
                      description: item.content,
                      color: Colors.indigo,
                    ),
                ],
              ),
            ),
          ],
          if (detail.followUps.isNotEmpty) ...[
            const SizedBox(height: 10),
            _DetailSection(
              icon: Icons.follow_the_signs_rounded,
              title: 'Tindak Lanjut',
              child: Column(
                children: [
                  for (final item in detail.followUps)
                    _ProcessItem(
                      title: item.typeLabel,
                      subtitle:
                          '${_dateLabel(item.date)}${item.time == null ? '' : ' · ${item.time}'} · ${item.officer ?? '-'}',
                      description:
                          '${item.summary}${_filled(item.result) ? '\nHasil: ${item.result}' : ''}',
                      color: NusaColors.success,
                    ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 10),
          _DetailSection(
            icon: Icons.timeline_rounded,
            title: 'Linimasa Proses',
            child: detail.timeline.isEmpty
                ? const _EmptySection(text: 'Belum ada riwayat proses.')
                : Column(
                    children: [
                      for (final item in detail.timeline)
                        _TimelineItem(item: item),
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  Future<void> _downloadEvidence(StudentReportEvidence evidence) async {
    setState(() => _downloadingEvidenceId = evidence.id);
    try {
      final download = await ref
          .read(studentReportActionsProvider)
          .downloadEvidence(evidence: evidence, scope: widget.scope);
      final saved = await ref
          .read(studentReportEvidenceSaverProvider)
          .save(download);
      if (mounted) {
        _showMessage(
          saved ? 'Bukti berhasil disimpan.' : 'Penyimpanan dibatalkan.',
        );
      }
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _downloadingEvidenceId = null);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  void _invalidateDetail() {
    if (widget.scope == StudentReportScope.guardianStudents) {
      ref.invalidate(guardianStudentReportDetailProvider(widget.reportId));
    } else {
      ref.invalidate(studentReportDetailProvider(widget.reportId));
    }
  }
}

class _DetailHero extends StatelessWidget {
  const _DetailHero({required this.report});
  final StudentReportItem report;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(report.verificationStatus);
    return Container(
      padding: const EdgeInsets.all(17),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(19),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  report.number,
                  style: const TextStyle(
                    color: NusaColors.accent,
                    fontWeight: FontWeight.w800,
                    fontSize: 12,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.22),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: color.withValues(alpha: 0.5)),
                ),
                child: Text(
                  report.verificationStatusLabel,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          Text(
            report.student?.name ?? 'Siswa',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 21,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            '${report.typeLabel} · ${report.levelLabel}${report.totalPoints > 0 ? ' · ${report.totalPoints} poin' : ''}',
            style: const TextStyle(color: Colors.white70, fontSize: 11),
          ),
          if (report.deadline.at != null) ...[
            const SizedBox(height: 11),
            Row(
              children: [
                Icon(
                  report.deadline.overdue
                      ? Icons.warning_amber_rounded
                      : Icons.schedule_rounded,
                  color: report.deadline.overdue
                      ? Colors.orangeAccent
                      : NusaColors.accent,
                  size: 18,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    '${report.deadline.stageLabel ?? 'Tenggat'}: ${_dateTimeLabel(report.deadline.at)}${report.deadline.overdue ? ' · Terlewat' : ''}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _DetailSection extends StatelessWidget {
  const _DetailSection({
    required this.icon,
    required this.title,
    required this.child,
    this.badge,
  });

  final IconData icon;
  final String title;
  final String? badge;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 21, color: NusaColors.primary),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              if (badge != null)
                Text(
                  badge!,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    ),
  );
}

class _TextSection extends StatelessWidget {
  const _TextSection({
    required this.icon,
    required this.title,
    required this.text,
  });
  final IconData icon;
  final String title;
  final String? text;

  @override
  Widget build(BuildContext context) => _DetailSection(
    icon: icon,
    title: title,
    child: Text(
      _filled(text) ? text! : '-',
      style: const TextStyle(fontSize: 12, height: 1.45),
    ),
  );
}

class _FactRow extends StatelessWidget {
  const _FactRow({
    required this.label,
    required this.value,
    this.divider = true,
  });
  final String label;
  final String value;
  final bool divider;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 88,
            child: Text(
              label,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10.5,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
      if (divider) const Divider(height: 17),
    ],
  );
}

class _ViolationItem extends StatelessWidget {
  const _ViolationItem({required this.item});
  final StudentReportViolation item;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
        decoration: BoxDecoration(
          color: NusaColors.surfaceBlue,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          item.code,
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 9,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      const SizedBox(width: 8),
      Expanded(child: Text(item.name, style: const TextStyle(fontSize: 11.5))),
      Text(
        '${item.points} poin',
        style: const TextStyle(
          color: Colors.deepOrange,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );
}

class _ProcessItem extends StatelessWidget {
  const _ProcessItem({
    required this.title,
    required this.subtitle,
    required this.description,
    required this.color,
  });
  final String title;
  final String subtitle;
  final String? description;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: color.withValues(alpha: 0.16)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: TextStyle(
            color: color,
            fontWeight: FontWeight.w800,
            fontSize: 11.5,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          subtitle,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
        if (_filled(description)) ...[
          const SizedBox(height: 6),
          Text(description!, style: const TextStyle(fontSize: 11, height: 1.4)),
        ],
      ],
    ),
  );
}

class _TimelineItem extends StatelessWidget {
  const _TimelineItem({required this.item});
  final StudentReportTimeline item;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Column(
        children: [
          Container(
            width: 12,
            height: 12,
            decoration: const BoxDecoration(
              color: NusaColors.primary,
              shape: BoxShape.circle,
            ),
          ),
          Container(width: 2, height: 52, color: NusaColors.outline),
        ],
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Padding(
          padding: const EdgeInsets.only(bottom: 13),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.title,
                style: const TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                '${item.user ?? 'Sistem'} · ${_dateTimeLabel(item.occurredAt)}',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9,
                ),
              ),
              if (_filled(item.description))
                Text(item.description!, style: const TextStyle(fontSize: 10.5)),
            ],
          ),
        ),
      ),
    ],
  );
}

class _EmptySection extends StatelessWidget {
  const _EmptySection({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Text(
    text,
    style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
  );
}

class _DetailError extends StatelessWidget {
  const _DetailError({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

bool _filled(String? value) => value != null && value.trim().isNotEmpty;

Color _statusColor(String status) => switch (status) {
  'disahkan' || 'ditetapkan_pembinaan' => NusaColors.success,
  'tidak_terbukti' => Colors.teal,
  'dibatalkan' => Colors.grey,
  'menunggu_pengesahan_wakil' => Colors.deepOrange,
  'perlu_klarifikasi' || 'dikembalikan_bk' => Colors.amber,
  _ => NusaColors.primaryLight,
};

String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  return date == null
      ? (value.isEmpty ? '-' : value)
      : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _dateTimeLabel(DateTime? value) => value == null
    ? '-'
    : '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';

String _errorMessage(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Detail laporan siswa belum dapat dimuat.',
};
