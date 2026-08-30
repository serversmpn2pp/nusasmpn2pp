import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/report_verification/application/report_verification_controller.dart';
import 'package:nusa/features/report_verification/domain/report_verification.dart';
import 'package:nusa/features/student_report/application/student_report_controller.dart';
import 'package:nusa/features/student_report/data/student_report_evidence_saver.dart';
import 'package:nusa/features/student_report/domain/student_report.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ReportVerificationDetailView extends ConsumerStatefulWidget {
  const ReportVerificationDetailView({required this.reportId, super.key});

  final int reportId;

  @override
  ConsumerState<ReportVerificationDetailView> createState() =>
      _ReportVerificationDetailViewState();
}

class _ReportVerificationDetailViewState
    extends ConsumerState<ReportVerificationDetailView> {
  final _reviewNoteController = TextEditingController();
  final _approvalNoteController = TextEditingController();
  String _reviewResult = 'sanksi_poin';
  String _approvalDecision = 'sahkan';
  Set<int> _selectedViolationIds = {};
  int? _initializedReportId;
  int? _downloadingEvidenceId;
  bool _submitting = false;

  @override
  void dispose() {
    _reviewNoteController.dispose();
    _approvalNoteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final asyncDetail = ref.watch(
      reportVerificationDetailProvider(widget.reportId),
    );
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Pemeriksaan'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: asyncDetail.isLoading || _submitting
                ? null
                : () => ref.invalidate(
                    reportVerificationDetailProvider(widget.reportId),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: asyncDetail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: _errorMessage(error),
            onRetry: () => ref.invalidate(
              reportVerificationDetailProvider(widget.reportId),
            ),
          ),
          data: (detail) {
            _initialize(detail);
            return _DetailContent(
              detail: detail,
              reviewResult: _reviewResult,
              approvalDecision: _approvalDecision,
              selectedViolationIds: _selectedViolationIds,
              reviewNoteController: _reviewNoteController,
              approvalNoteController: _approvalNoteController,
              submitting: _submitting,
              downloadingEvidenceId: _downloadingEvidenceId,
              onReviewResultChanged: (value) =>
                  setState(() => _reviewResult = value),
              onApprovalDecisionChanged: (value) =>
                  setState(() => _approvalDecision = value),
              onPickViolations: () => _pickViolations(detail),
              onReview: () => _submitReview(detail),
              onApprove: () => _submitApproval(detail),
              onDownloadEvidence: _downloadEvidence,
            );
          },
        ),
      ),
    );
  }

  void _initialize(ReportVerificationDetail detail) {
    if (_initializedReportId == detail.reportDetail.report.id) return;
    _initializedReportId = detail.reportDetail.report.id;
    if (detail.reviewOptions.isNotEmpty) {
      _reviewResult = detail.reviewOptions.first.code;
    }
    if (detail.approvalOptions.isNotEmpty) {
      _approvalDecision = detail.approvalOptions.first.code;
    }
    _selectedViolationIds = detail.reportDetail.violations
        .map((item) => item.violationTypeId)
        .whereType<int>()
        .toSet();
  }

  Future<void> _pickViolations(ReportVerificationDetail detail) async {
    final selected = await showModalBottomSheet<Set<int>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _ViolationPicker(
        items: detail.violationOptions,
        selectedIds: _selectedViolationIds,
      ),
    );
    if (selected != null && mounted) {
      setState(() => _selectedViolationIds = selected);
    }
  }

  Future<void> _submitReview(ReportVerificationDetail detail) async {
    if (_submitting) return;
    if (_reviewResult == 'sanksi_poin' && _selectedViolationIds.isEmpty) {
      _showMessage('Pilih minimal satu butir pelanggaran berpoin.');
      return;
    }
    final option = detail.reviewOptions.firstWhere(
      (item) => item.code == _reviewResult,
      orElse: () => detail.reviewOptions.first,
    );
    final confirmed = await _confirm(
      icon: _reviewResult == 'tidak_terbukti'
          ? Icons.rule_rounded
          : Icons.fact_check_rounded,
      title: 'Simpan keputusan BK?',
      message: _reviewResult == 'sanksi_poin'
          ? '${_selectedViolationIds.length} butir akan direkomendasikan kepada Wakil Kesiswaan. Poin belum resmi sebelum disahkan.'
          : 'Hasil pemeriksaan akan disimpan sebagai “${option.label}”.',
      confirmLabel: 'Ya, simpan keputusan',
    );
    if (!confirmed || !mounted) return;

    setState(() => _submitting = true);
    try {
      final result = await ref
          .read(reportVerificationActionsProvider)
          .review(
            reportId: detail.reportDetail.report.id,
            result: _reviewResult,
            violationIds: _selectedViolationIds.toList(),
            note: _reviewNoteController.text,
          );
      if (mounted) _showMessage(result.message);
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _submitApproval(ReportVerificationDetail detail) async {
    if (_submitting) return;
    if (_approvalDecision == 'kembalikan' &&
        _approvalNoteController.text.trim().isEmpty) {
      _showMessage('Catatan wajib diisi ketika rekomendasi dikembalikan.');
      return;
    }
    final approving = _approvalDecision == 'sahkan';
    final confirmed = await _confirm(
      icon: approving ? Icons.verified_rounded : Icons.undo_rounded,
      title: approving ? 'Sahkan rekomendasi poin?' : 'Kembalikan kepada BK?',
      message: approving
          ? '${detail.reportDetail.report.totalPoints} poin akan resmi tercatat pada siswa dan dapat memicu aturan sanksi.'
          : 'Laporan kembali ke antrean BK untuk diperiksa ulang sesuai catatan Anda.',
      confirmLabel: approving ? 'Ya, sahkan poin' : 'Ya, kembalikan',
    );
    if (!confirmed || !mounted) return;

    setState(() => _submitting = true);
    try {
      final result = await ref
          .read(reportVerificationActionsProvider)
          .approve(
            reportId: detail.reportDetail.report.id,
            decision: _approvalDecision,
            note: _approvalNoteController.text,
          );
      if (mounted) _showMessage(result.message);
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<bool> _confirm({
    required IconData icon,
    required String title,
    required String message,
    required String confirmLabel,
  }) async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: Icon(icon, color: NusaColors.primary),
            title: Text(title),
            content: Text(message),
            actions: [
              TextButton(
                onPressed: () => context.pop(false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('report-verification-confirm-submit'),
                onPressed: () => context.pop(true),
                child: Text(confirmLabel),
              ),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _downloadEvidence(StudentReportEvidence evidence) async {
    setState(() => _downloadingEvidenceId = evidence.id);
    try {
      final download = await ref
          .read(studentReportActionsProvider)
          .downloadEvidence(evidence: evidence);
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
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({
    required this.detail,
    required this.reviewResult,
    required this.approvalDecision,
    required this.selectedViolationIds,
    required this.reviewNoteController,
    required this.approvalNoteController,
    required this.submitting,
    required this.downloadingEvidenceId,
    required this.onReviewResultChanged,
    required this.onApprovalDecisionChanged,
    required this.onPickViolations,
    required this.onReview,
    required this.onApprove,
    required this.onDownloadEvidence,
  });

  final ReportVerificationDetail detail;
  final String reviewResult;
  final String approvalDecision;
  final Set<int> selectedViolationIds;
  final TextEditingController reviewNoteController;
  final TextEditingController approvalNoteController;
  final bool submitting;
  final int? downloadingEvidenceId;
  final ValueChanged<String> onReviewResultChanged;
  final ValueChanged<String> onApprovalDecisionChanged;
  final VoidCallback onPickViolations;
  final VoidCallback onReview;
  final VoidCallback onApprove;
  final ValueChanged<StudentReportEvidence> onDownloadEvidence;

  @override
  Widget build(BuildContext context) {
    final data = detail.reportDetail;
    return ListView(
      key: const PageStorageKey<String>('report-verification-detail'),
      padding: const EdgeInsets.fromLTRB(16, 7, 16, 30),
      children: [
        _ReportHero(detail: detail),
        const SizedBox(height: 11),
        _ProcessCard(process: detail.process),
        const SizedBox(height: 11),
        _ReportFacts(detail: data),
        const SizedBox(height: 11),
        _EvidenceCard(
          items: data.evidence,
          downloadingId: downloadingEvidenceId,
          onDownload: onDownloadEvidence,
        ),
        const SizedBox(height: 11),
        _SupportingFacts(detail: data),
        const SizedBox(height: 11),
        _CurrentViolations(items: data.violations),
        if (data.counselingDecisions.isNotEmpty ||
            data.approvals.isNotEmpty) ...[
          const SizedBox(height: 11),
          _DecisionHistory(detail: data),
        ],
        if (detail.canReview) ...[
          const SizedBox(height: 11),
          _ReviewForm(
            detail: detail,
            result: reviewResult,
            selectedViolationIds: selectedViolationIds,
            noteController: reviewNoteController,
            submitting: submitting,
            onResultChanged: onReviewResultChanged,
            onPickViolations: onPickViolations,
            onSubmit: onReview,
          ),
        ],
        if (detail.canApprove) ...[
          const SizedBox(height: 11),
          _ApprovalForm(
            detail: detail,
            decision: approvalDecision,
            noteController: approvalNoteController,
            submitting: submitting,
            onDecisionChanged: onApprovalDecisionChanged,
            onSubmit: onApprove,
          ),
        ],
        if (!detail.canReview && !detail.canApprove) ...[
          const SizedBox(height: 11),
          const _ReadOnlyNotice(),
        ],
        const SizedBox(height: 11),
        _TimelineCard(items: data.timeline),
      ],
    );
  }
}

class _ReportHero extends StatelessWidget {
  const _ReportHero({required this.detail});

  final ReportVerificationDetail detail;

  @override
  Widget build(BuildContext context) {
    final report = detail.reportDetail.report;
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
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              _WhiteBadge(label: report.verificationStatusLabel),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            report.student?.name ?? 'Siswa',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            '${report.schoolClass?.name ?? 'Kelas belum ditentukan'} · NISN ${report.student?.nisn ?? '-'}',
            style: const TextStyle(color: Colors.white70, fontSize: 10.5),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(
                Icons.assignment_ind_rounded,
                size: 17,
                color: NusaColors.accent,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  detail.process.userTask,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          if (report.totalPoints > 0) ...[
            const SizedBox(height: 8),
            Text(
              '${report.totalPoints} poin ${report.verificationStatus == 'disahkan' ? 'resmi' : 'direkomendasikan'}',
              style: const TextStyle(
                color: NusaColors.accent,
                fontSize: 13,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _WhiteBadge extends StatelessWidget {
  const _WhiteBadge({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(maxWidth: 142),
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Text(
      label,
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
      textAlign: TextAlign.center,
      style: const TextStyle(
        color: Colors.white,
        fontSize: 8.5,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class _ProcessCard extends StatelessWidget {
  const _ProcessCard({required this.process});

  final ReportVerificationProcess process;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.route_rounded,
    title: 'Alur Penanganan',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: _ProcessStep(
                label: '1. Pemeriksaan BK',
                state: _stepState(process.activeStage, 1),
              ),
            ),
            const SizedBox(width: 7),
            Expanded(
              child: _ProcessStep(
                label: '2. Pengesahan Wakil',
                state: _stepState(process.activeStage, 2),
              ),
            ),
          ],
        ),
        if (process.dayLimit > 0) ...[
          const SizedBox(height: 11),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: (process.overdue ? Colors.red : NusaColors.primary)
                  .withValues(alpha: 0.06),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Row(
              children: [
                Icon(
                  process.overdue
                      ? Icons.warning_amber_rounded
                      : Icons.schedule_rounded,
                  color: process.overdue ? Colors.red : NusaColors.primary,
                  size: 19,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    process.overdue
                        ? 'Proses terlambat ${process.remainingDays.abs().clamp(1, 999)} hari.'
                        : '${process.waitingDays} hari diproses · sisa ${process.remainingDays.clamp(0, 999)} hari.',
                    style: TextStyle(
                      color: process.overdue ? Colors.red : NusaColors.primary,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 11),
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: [
            _FactChip(label: 'Kronologi', complete: process.facts.chronology),
            _FactChip(label: 'Lokasi', complete: process.facts.location),
            _FactChip(label: 'Butir', complete: process.facts.violation),
            _FactChip(label: 'Bukti', complete: process.facts.evidence),
            _FactChip(label: 'Saksi', complete: process.facts.witness),
            _FactChip(
              label: 'Klarifikasi',
              complete: process.facts.clarification,
            ),
          ],
        ),
      ],
    ),
  );
}

class _ProcessStep extends StatelessWidget {
  const _ProcessStep({required this.label, required this.state});
  final String label;
  final int state;

  @override
  Widget build(BuildContext context) {
    final color = state == 2
        ? NusaColors.success
        : state == 1
        ? const Color(0xFF8A6800)
        : NusaColors.textSecondary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 9),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.22)),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: TextStyle(
          color: color,
          fontSize: 9,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

int _stepState(int active, int value) =>
    active > value ? 2 : (active == value ? 1 : 0);

class _FactChip extends StatelessWidget {
  const _FactChip({required this.label, required this.complete});
  final String label;
  final bool complete;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: (complete ? NusaColors.success : Colors.grey).withValues(
        alpha: 0.09,
      ),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(
          complete ? Icons.check_circle_rounded : Icons.remove_circle_outline,
          size: 13,
          color: complete ? NusaColors.success : NusaColors.textSecondary,
        ),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 9.5)),
      ],
    ),
  );
}

class _ReportFacts extends StatelessWidget {
  const _ReportFacts({required this.detail});
  final StudentReportDetail detail;

  @override
  Widget build(BuildContext context) {
    final report = detail.report;
    return _SectionCard(
      icon: Icons.description_outlined,
      title: 'Fakta Laporan',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _InfoRow(label: 'Kejadian', value: _dateLabel(report.incidentDate)),
          _InfoRow(label: 'Waktu', value: report.incidentTime ?? '-'),
          _InfoRow(label: 'Tempat', value: report.place ?? '-'),
          _InfoRow(label: 'Pelapor', value: report.reporter?.name ?? '-'),
          const Divider(height: 20),
          const _Label(text: 'Kronologi'),
          Text(_value(report.chronology), style: const TextStyle(height: 1.45)),
          const SizedBox(height: 12),
          const _Label(text: 'Tindakan awal'),
          Text(
            _value(report.initialAction),
            style: const TextStyle(height: 1.45),
          ),
        ],
      ),
    );
  }
}

class _EvidenceCard extends StatelessWidget {
  const _EvidenceCard({
    required this.items,
    required this.downloadingId,
    required this.onDownload,
  });

  final List<StudentReportEvidence> items;
  final int? downloadingId;
  final ValueChanged<StudentReportEvidence> onDownload;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.attach_file_rounded,
    title: 'Bukti Privat (${items.length})',
    child: items.isEmpty
        ? const _EmptyText(text: 'Belum ada bukti yang dilampirkan.')
        : Column(
            children: [
              for (var index = 0; index < items.length; index++) ...[
                _EvidenceTile(
                  item: items[index],
                  loading: downloadingId == items[index].id,
                  onTap: () => onDownload(items[index]),
                ),
                if (index < items.length - 1) const Divider(height: 14),
              ],
            ],
          ),
  );
}

class _EvidenceTile extends StatelessWidget {
  const _EvidenceTile({
    required this.item,
    required this.loading,
    required this.onTap,
  });
  final StudentReportEvidence item;
  final bool loading;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      const Icon(Icons.insert_drive_file_outlined, color: NusaColors.primary),
      const SizedBox(width: 9),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              item.fileName,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
            ),
            Text(
              '${item.type} · ${item.sizeLabel}',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 9,
              ),
            ),
          ],
        ),
      ),
      IconButton(
        key: Key('verification-evidence-download-${item.id}'),
        tooltip: 'Unduh bukti',
        onPressed: loading ? null : onTap,
        icon: loading
            ? const SizedBox.square(
                dimension: 19,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.download_rounded),
      ),
    ],
  );
}

class _SupportingFacts extends StatelessWidget {
  const _SupportingFacts({required this.detail});
  final StudentReportDetail detail;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.groups_2_outlined,
    title: 'Saksi & Klarifikasi',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (detail.witnesses.isEmpty)
          const _EmptyText(text: 'Belum ada saksi.')
        else
          for (final item in detail.witnesses)
            _Entry(
              title: '${item.name} · ${item.typeLabel}',
              subtitle: item.statement,
            ),
        const Divider(height: 20),
        if (detail.clarifications.isEmpty)
          const _EmptyText(text: 'Belum ada klarifikasi siswa.')
        else
          for (final item in detail.clarifications)
            _Entry(
              title: item.methodLabel,
              subtitle:
                  '${item.content}${_filled(item.companion) ? '\nPendamping: ${item.companion}' : ''}',
            ),
      ],
    ),
  );
}

class _CurrentViolations extends StatelessWidget {
  const _CurrentViolations({required this.items});
  final List<StudentReportViolation> items;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.gavel_rounded,
    title: 'Butir Pelanggaran Saat Ini',
    child: items.isEmpty
        ? const _EmptyText(text: 'Belum diklasifikasikan sebagai pelanggaran.')
        : Column(
            children: [
              for (final item in items)
                _Entry(
                  title: '${item.code} · ${item.name}',
                  subtitle: '${item.level} · ${item.points} poin',
                  color: Colors.deepOrange,
                ),
            ],
          ),
  );
}

class _DecisionHistory extends StatelessWidget {
  const _DecisionHistory({required this.detail});
  final StudentReportDetail detail;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.history_rounded,
    title: 'Riwayat Keputusan',
    child: Column(
      children: [
        for (final item in detail.counselingDecisions)
          _Entry(
            title: 'BK · ${item.resultLabel}',
            subtitle:
                '${item.officer ?? 'BK'} · ${_dateTimeLabel(item.processedAt)}${_filled(item.note) ? '\n${item.note}' : ''}',
            color: NusaColors.primary,
          ),
        for (final item in detail.approvals)
          _Entry(
            title: '${item.typeLabel} · ${item.decisionLabel}',
            subtitle:
                '${item.officer ?? '-'} · ${_dateTimeLabel(item.processedAt)}${_filled(item.note) ? '\n${item.note}' : ''}',
            color: NusaColors.success,
          ),
      ],
    ),
  );
}

class _ReviewForm extends StatelessWidget {
  const _ReviewForm({
    required this.detail,
    required this.result,
    required this.selectedViolationIds,
    required this.noteController,
    required this.submitting,
    required this.onResultChanged,
    required this.onPickViolations,
    required this.onSubmit,
  });

  final ReportVerificationDetail detail;
  final String result;
  final Set<int> selectedViolationIds;
  final TextEditingController noteController;
  final bool submitting;
  final ValueChanged<String> onResultChanged;
  final VoidCallback onPickViolations;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final selected = detail.violationOptions
        .where((item) => selectedViolationIds.contains(item.id))
        .toList();
    final totalPoints = selected.fold<int>(0, (sum, item) => sum + item.points);
    return _SectionCard(
      key: const Key('report-verification-review-form'),
      icon: Icons.fact_check_rounded,
      title: 'Keputusan Pemeriksaan BK',
      accent: NusaColors.accent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Periksa fakta sebelum memilih hasil. Pembinaan dan tidak terbukti selesai di BK.',
            style: TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 12),
          NusaDropdownField<String>(
            fieldKey: const Key('report-verification-review-result'),
            value: result,
            enabled: !submitting,
            decoration: const InputDecoration(
              labelText: 'Hasil pemeriksaan BK',
              prefixIcon: Icon(Icons.rule_rounded),
            ),
            options: [
              for (final option in detail.reviewOptions)
                NusaDropdownOption(value: option.code, label: option.label),
            ],
            onChanged: (value) {
              if (value != null) onResultChanged(value);
            },
          ),
          if (result == 'sanksi_poin') ...[
            const SizedBox(height: 10),
            OutlinedButton.icon(
              key: const Key('report-verification-pick-violations'),
              onPressed: submitting ? null : onPickViolations,
              icon: const Icon(Icons.playlist_add_check_circle_rounded),
              label: Text(
                selected.isEmpty
                    ? 'Pilih butir pelanggaran'
                    : '${selected.length} butir · $totalPoints poin',
              ),
            ),
            if (selected.isNotEmpty) ...[
              const SizedBox(height: 8),
              for (final item in selected)
                Padding(
                  padding: const EdgeInsets.only(bottom: 5),
                  child: Text(
                    '• ${item.code} ${item.name} (${item.points} poin)',
                    style: const TextStyle(fontSize: 10.5),
                  ),
                ),
            ],
          ],
          const SizedBox(height: 10),
          TextField(
            key: const Key('report-verification-review-note'),
            controller: noteController,
            enabled: !submitting,
            minLines: 3,
            maxLines: 5,
            textCapitalization: TextCapitalization.sentences,
            decoration: const InputDecoration(
              labelText: 'Catatan keputusan (opsional)',
              alignLabelWithHint: true,
              prefixIcon: Icon(Icons.notes_rounded),
            ),
          ),
          const SizedBox(height: 12),
          NusaPrimaryButton(
            key: const Key('report-verification-submit-review'),
            label: 'Simpan Keputusan BK',
            loading: submitting,
            onPressed: onSubmit,
          ),
        ],
      ),
    );
  }
}

class _ApprovalForm extends StatelessWidget {
  const _ApprovalForm({
    required this.detail,
    required this.decision,
    required this.noteController,
    required this.submitting,
    required this.onDecisionChanged,
    required this.onSubmit,
  });

  final ReportVerificationDetail detail;
  final String decision;
  final TextEditingController noteController;
  final bool submitting;
  final ValueChanged<String> onDecisionChanged;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) => _SectionCard(
    key: const Key('report-verification-approval-form'),
    icon: Icons.verified_rounded,
    title: 'Pengesahan Wakil Kesiswaan',
    accent: NusaColors.accent,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(11),
          decoration: BoxDecoration(
            color: const Color(0xFFFFF7DA),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(
            '${detail.reportDetail.report.totalPoints} poin masih berupa rekomendasi BK dan belum resmi.',
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(height: 11),
        NusaDropdownField<String>(
          fieldKey: const Key('report-verification-approval-decision'),
          value: decision,
          enabled: !submitting,
          decoration: const InputDecoration(
            labelText: 'Keputusan Wakil Kesiswaan',
            prefixIcon: Icon(Icons.approval_rounded),
          ),
          options: [
            for (final option in detail.approvalOptions)
              NusaDropdownOption(value: option.code, label: option.label),
          ],
          onChanged: (value) {
            if (value != null) onDecisionChanged(value);
          },
        ),
        const SizedBox(height: 10),
        TextField(
          key: const Key('report-verification-approval-note'),
          controller: noteController,
          enabled: !submitting,
          minLines: 3,
          maxLines: 5,
          textCapitalization: TextCapitalization.sentences,
          decoration: InputDecoration(
            labelText: decision == 'kembalikan'
                ? 'Catatan pengembalian (wajib)'
                : 'Catatan pengesahan (opsional)',
            alignLabelWithHint: true,
            prefixIcon: const Icon(Icons.notes_rounded),
          ),
        ),
        const SizedBox(height: 12),
        NusaPrimaryButton(
          key: const Key('report-verification-submit-approval'),
          label: decision == 'sahkan'
              ? 'Sahkan Poin Siswa'
              : 'Kembalikan kepada BK',
          loading: submitting,
          onPressed: onSubmit,
        ),
      ],
    ),
  );
}

class _ReadOnlyNotice extends StatelessWidget {
  const _ReadOnlyNotice();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: const Row(
      children: [
        Icon(Icons.visibility_outlined, color: NusaColors.primary),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Laporan ini dapat dipantau. Tidak ada tindakan yang perlu atau boleh dilakukan pada status saat ini.',
            style: TextStyle(fontSize: 10.5, height: 1.4),
          ),
        ),
      ],
    ),
  );
}

class _TimelineCard extends StatelessWidget {
  const _TimelineCard({required this.items});
  final List<StudentReportTimeline> items;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.timeline_rounded,
    title: 'Linimasa Proses',
    child: items.isEmpty
        ? const _EmptyText(text: 'Belum ada riwayat proses.')
        : Column(
            children: [
              for (final item in items)
                _Entry(
                  title: item.title,
                  subtitle:
                      '${item.user ?? 'Sistem'} · ${_dateTimeLabel(item.occurredAt)}${_filled(item.description) ? '\n${item.description}' : ''}',
                  color: NusaColors.primary,
                ),
            ],
          ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.icon,
    required this.title,
    required this.child,
    this.accent,
    super.key,
  });

  final IconData icon;
  final String title;
  final Widget child;
  final Color? accent;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Container(
      decoration: BoxDecoration(
        border: accent == null
            ? null
            : Border(left: BorderSide(color: accent!, width: 4)),
        borderRadius: BorderRadius.circular(16),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: NusaColors.primary, size: 21),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
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

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 7),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 76,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _Label extends StatelessWidget {
  const _Label({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 4),
    child: Text(
      text,
      style: const TextStyle(
        color: NusaColors.textSecondary,
        fontSize: 10,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class _Entry extends StatelessWidget {
  const _Entry({
    required this.title,
    required this.subtitle,
    this.color = NusaColors.textPrimary,
  });
  final String title;
  final String subtitle;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    margin: const EdgeInsets.only(bottom: 8),
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.05),
      borderRadius: BorderRadius.circular(11),
      border: Border.all(color: color.withValues(alpha: 0.12)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: TextStyle(
            color: color,
            fontSize: 10.5,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 3),
        Text(subtitle, style: const TextStyle(fontSize: 10, height: 1.35)),
      ],
    ),
  );
}

class _EmptyText extends StatelessWidget {
  const _EmptyText({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Text(
    text,
    style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
  );
}

class _ViolationPicker extends StatefulWidget {
  const _ViolationPicker({required this.items, required this.selectedIds});

  final List<ReportViolationOption> items;
  final Set<int> selectedIds;

  @override
  State<_ViolationPicker> createState() => _ViolationPickerState();
}

class _ViolationPickerState extends State<_ViolationPicker> {
  final _searchController = TextEditingController();
  late Set<int> _selected;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _selected = {...widget.selectedIds};
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final normalized = _query.toLowerCase();
    final filtered = widget.items.where((item) {
      final text = '${item.code} ${item.name} ${item.category ?? ''}'
          .toLowerCase();
      return normalized.isEmpty || text.contains(normalized);
    }).toList();
    final points = widget.items
        .where((item) => _selected.contains(item.id))
        .fold<int>(0, (sum, item) => sum + item.points);
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.86,
      minChildSize: 0.55,
      maxChildSize: 0.96,
      builder: (context, scrollController) => Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 42,
                    height: 4,
                    decoration: BoxDecoration(
                      color: NusaColors.outline,
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                ),
                const SizedBox(height: 13),
                const Text(
                  'Pilih Butir Pelanggaran',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 3),
                Text(
                  '${_selected.length} butir dipilih · $points poin',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 11,
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  key: const Key('report-verification-violation-search'),
                  controller: _searchController,
                  onChanged: (value) => setState(() => _query = value.trim()),
                  decoration: const InputDecoration(
                    hintText: 'Cari kode, nama, atau kategori',
                    prefixIcon: Icon(Icons.search_rounded),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: filtered.isEmpty
                ? const Center(child: Text('Butir tidak ditemukan.'))
                : ListView.builder(
                    controller: scrollController,
                    padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
                    itemCount: filtered.length,
                    itemBuilder: (context, index) {
                      final item = filtered[index];
                      return CheckboxListTile(
                        key: Key('violation-option-${item.id}'),
                        value: _selected.contains(item.id),
                        controlAffinity: ListTileControlAffinity.leading,
                        title: Text(
                          '${item.code} · ${item.name}',
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        subtitle: Text(
                          '${item.category ?? '-'} · ${item.level} · ${item.points} poin',
                          style: const TextStyle(fontSize: 9.5),
                        ),
                        onChanged: (checked) => setState(() {
                          if (checked == true) {
                            _selected.add(item.id);
                          } else {
                            _selected.remove(item.id);
                          }
                        }),
                      );
                    },
                  ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => setState(_selected.clear),
                      child: const Text('Kosongkan'),
                    ),
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    flex: 2,
                    child: FilledButton(
                      key: const Key('report-verification-save-violations'),
                      onPressed: () => context.pop({..._selected}),
                      child: const Text('Gunakan Pilihan'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
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
String _value(String? value) => _filled(value) ? value! : '-';

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
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Pemeriksaan laporan belum dapat diproses.',
};
