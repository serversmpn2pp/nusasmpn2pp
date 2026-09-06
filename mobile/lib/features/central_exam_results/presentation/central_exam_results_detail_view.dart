import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_results/application/central_exam_results_controller.dart';
import 'package:nusa/features/central_exam_results/domain/central_exam_results.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';
import 'package:nusa/features/class_assessment/presentation/widgets/class_assessment_operation_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class CentralExamResultsDetailView extends ConsumerStatefulWidget {
  const CentralExamResultsDetailView({required this.eventId, super.key});
  final int eventId;

  @override
  ConsumerState<CentralExamResultsDetailView> createState() =>
      _CentralExamResultsDetailViewState();
}

class _CentralExamResultsDetailViewState
    extends ConsumerState<CentralExamResultsDetailView> {
  int? _scheduleId;
  int? _classId;
  String _status = 'semua';
  bool _applying = false;
  CentralExamResultLifecycleAction? _lifecycleAction;

  CentralExamResultsRequest get _request => (
    eventId: widget.eventId,
    scheduleId: _scheduleId,
    classId: _classId,
    status: _status,
  );

  Future<void> _refresh() async {
    ref.invalidate(centralExamResultsDetailProvider(_request));
    await ref.read(centralExamResultsDetailProvider(_request).future);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(centralExamResultsDetailProvider(_request));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Hasil Ujian Terpusat'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => AssessmentOperationError(
            message: _message(error),
            onRetry: _refresh,
          ),
          data: (data) => RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              key: const PageStorageKey('central-exam-results-detail'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                _EventHero(event: data.event),
                const SizedBox(height: 11),
                _SchedulePicker(
                  schedules: data.schedules,
                  selectedId: data.selectedScheduleId,
                  onChanged: (value) => setState(() {
                    _scheduleId = value;
                    _classId = null;
                    _status = 'semua';
                  }),
                ),
                const SizedBox(height: 11),
                if (data.selectedScheduleId == null)
                  const AssessmentOperationEmpty(
                    message:
                        'Belum ada paket soal pada jadwal dalam cakupan akun.',
                  )
                else ...[
                  AssessmentOperationHero(
                    assessment: data.results.assessment,
                    eyebrow: 'HASIL PAKET TERPILIH',
                  ),
                  const SizedBox(height: 11),
                  _FinalizationCard(
                    finalization: data.finalization,
                    busyAction: _lifecycleAction,
                    onAction: (action) => _confirmLifecycle(data, action),
                  ),
                  const SizedBox(height: 11),
                  _TargetAndAction(
                    data: data,
                    applying: _applying,
                    onApply: () => _confirmApply(data),
                    onCorrection: () => context.push(
                      '/hasil-ujian-terpusat/${widget.eventId}/jadwal/${data.selectedScheduleId}/koreksi-uraian',
                    ),
                  ),
                  const SizedBox(height: 11),
                  AssessmentMetricsGrid(
                    items: [
                      AssessmentMetricData(
                        label: 'Peserta',
                        value: '${data.results.summary.total}',
                        icon: Icons.groups_rounded,
                        color: NusaColors.primary,
                      ),
                      AssessmentMetricData(
                        label: 'Hasil final',
                        value: '${data.results.summary.finalResults}',
                        icon: Icons.task_alt_rounded,
                        color: NusaColors.success,
                      ),
                      AssessmentMetricData(
                        label: 'Rata-rata',
                        value: assessmentNumber(data.results.summary.average),
                        icon: Icons.insights_rounded,
                        color: NusaColors.primaryLight,
                      ),
                      AssessmentMetricData(
                        label: 'Tertinggi / terendah',
                        value:
                            '${assessmentNumber(data.results.summary.highest)} / ${assessmentNumber(data.results.summary.lowest)}',
                        icon: Icons.leaderboard_outlined,
                        color: const Color(0xFF9A7000),
                      ),
                      AssessmentMetricData(
                        label: data.results.assessment.minimumScore == null
                            ? 'Selesai'
                            : 'Tuntas',
                        value: data.results.assessment.minimumScore == null
                            ? '${data.results.summary.finished}'
                            : '${data.results.summary.passed}',
                        icon: Icons.verified_outlined,
                        color: NusaColors.success,
                      ),
                      AssessmentMetricData(
                        label: 'Perlu koreksi',
                        value: '${data.results.summary.needsCorrection}',
                        icon: Icons.rate_review_outlined,
                        color: const Color(0xFF9A7000),
                      ),
                    ],
                  ),
                  const SizedBox(height: 11),
                  AssessmentFilterCard(
                    classes: data.results.classes,
                    statuses: data.results.statuses,
                    selectedClassId: _classId,
                    selectedStatus: _status,
                    classKey: const Key('central-results-class-filter'),
                    statusKey: const Key('central-results-status-filter'),
                    onClassChanged: (value) => setState(() => _classId = value),
                    onStatusChanged: (value) => setState(() => _status = value),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Nilai Peserta',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      Text(
                        '${data.results.items.length} siswa',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 9),
                  if (data.results.items.isEmpty)
                    const AssessmentOperationEmpty(
                      message:
                          'Belum ada hasil siswa yang sesuai dengan filter.',
                    )
                  else
                    for (final item in data.results.items) ...[
                      _ResultCard(
                        key: Key('central-result-${item.id}'),
                        item: item,
                        questionCount: data.results.questionCount,
                      ),
                      const SizedBox(height: 9),
                    ],
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _confirmApply(CentralExamResultsDetail data) async {
    final scheduleId = data.selectedScheduleId;
    if (scheduleId == null || !data.canApply) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Terapkan hasil ke nilai?'),
        content: Text(
          'Koreksi objektif dijalankan lebih dahulu. Hanya hasil final dengan komponen nilai yang valid yang akan diterapkan.\n\n'
          'Hasil final: ${data.results.summary.finalResults}\n'
          'Perlu koreksi: ${data.results.summary.needsCorrection}\n'
          'Belum selesai: ${data.results.summary.notFinished}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('central-results-confirm-apply'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Terapkan Nilai'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _applying = true);
    try {
      final result = await ref.read(centralExamResultsApplyProvider)(
        eventId: widget.eventId,
        scheduleId: scheduleId,
      );
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(result.message)));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_message(error))));
      }
    } finally {
      if (mounted) setState(() => _applying = false);
    }
  }

  Future<void> _confirmLifecycle(
    CentralExamResultsDetail data,
    CentralExamResultLifecycleAction action,
  ) async {
    final scheduleId = data.selectedScheduleId;
    if (scheduleId == null || _lifecycleAction != null) return;
    final copy = _lifecycleCopy(action);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(copy.title),
        content: Text(copy.description),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: Key('central-results-confirm-${action.name}'),
            onPressed: () => Navigator.pop(context, true),
            child: Text(copy.confirmLabel),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _lifecycleAction = action);
    try {
      final result = await ref.read(centralExamResultsLifecycleProvider)(
        eventId: widget.eventId,
        scheduleId: scheduleId,
        action: action,
      );
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(result.message)));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_message(error))));
      }
    } finally {
      if (mounted) setState(() => _lifecycleAction = null);
    }
  }
}

({String title, String description, String confirmLabel}) _lifecycleCopy(
  CentralExamResultLifecycleAction action,
) => switch (action) {
  CentralExamResultLifecycleAction.finalize => (
    title: 'Finalisasi hasil?',
    description: 'Koreksi otomatis akan dijalankan. Setelah final, skor dikunci sampai finalisasi dibatalkan.',
    confirmLabel: 'Finalisasi',
  ),
  CentralExamResultLifecycleAction.cancelFinalization => (
    title: 'Batalkan finalisasi?',
    description: 'Hasil kembali menjadi draf dan skor dapat dikoreksi kembali.',
    confirmLabel: 'Buka Kembali',
  ),
  CentralExamResultLifecycleAction.publish => (
    title: 'Publikasikan hasil?',
    description: 'Siswa yang telah menyelesaikan ujian dapat melihat nilainya di menu Ujian Saya.',
    confirmLabel: 'Publikasikan',
  ),
  CentralExamResultLifecycleAction.unpublish => (
    title: 'Batalkan publikasi?',
    description: 'Hasil tidak lagi terlihat oleh siswa, tetapi skor tetap dalam keadaan final.',
    confirmLabel: 'Batalkan Publikasi',
  ),
};

class _EventHero extends StatelessWidget {
  const _EventHero({required this.event});
  final CentralExamResultEvent event;
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
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
        Text(
          'NILAI & HASIL · ${event.code}',
          style: const TextStyle(
            color: NusaColors.accent,
            fontSize: 10,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          event.name,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          '${event.type ?? 'Ujian Terpusat'} · ${event.academicYear ?? '-'} · ${event.semester}',
          style: const TextStyle(color: Colors.white70, fontSize: 10.5),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            const Icon(
              Icons.date_range_outlined,
              size: 14,
              color: NusaColors.accent,
            ),
            const SizedBox(width: 4),
            Expanded(
              child: Text(
                event.period,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white70, fontSize: 10),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}

class _SchedulePicker extends StatelessWidget {
  const _SchedulePicker({
    required this.schedules,
    required this.selectedId,
    required this.onChanged,
  });
  final List<CentralExamResultSchedule> schedules;
  final int? selectedId;
  final ValueChanged<int> onChanged;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: NusaDropdownField<int>(
        fieldKey: const Key('central-results-schedule-filter'),
        value: selectedId,
        decoration: const InputDecoration(
          labelText: 'Jadwal dan mata pelajaran',
          prefixIcon: Icon(Icons.event_note_outlined),
        ),
        options: [
          for (final item in schedules)
            NusaDropdownOption(value: item.id, label: item.label),
        ],
        onChanged: (value) {
          if (value != null) onChanged(value);
        },
      ),
    ),
  );
}

class _FinalizationCard extends StatelessWidget {
  const _FinalizationCard({
    required this.finalization,
    required this.busyAction,
    required this.onAction,
  });

  final CentralExamResultFinalization finalization;
  final CentralExamResultLifecycleAction? busyAction;
  final ValueChanged<CentralExamResultLifecycleAction> onAction;

  @override
  Widget build(BuildContext context) {
    final readiness = finalization.readiness;
    final statusColor = switch (finalization.status) {
      'dipublikasikan' => NusaColors.success,
      'final' => NusaColors.primary,
      _ => const Color(0xFF9A7000),
    };
    final blockers = <String>[
      if (readiness.questionCount == 0) 'Paket belum memiliki soal.',
      if (readiness.totalParticipants == 0) 'Belum ada peserta.',
      if (readiness.unfinishedParticipants > 0)
        '${readiness.unfinishedParticipants} peserta wajib belum selesai.',
      if (readiness.pendingManualCorrections > 0)
        '${readiness.pendingManualCorrections} jawaban uraian belum dikoreksi.',
    ];

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(
                    finalization.isPublished
                        ? Icons.campaign_rounded
                        : finalization.isFinal
                        ? Icons.lock_rounded
                        : Icons.edit_note_rounded,
                    color: statusColor,
                    size: 19,
                  ),
                ),
                const SizedBox(width: 9),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Finalisasi & Publikasi',
                        style: TextStyle(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        'Tahapan hasil ujian terpusat',
                        style: TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 9.5,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    finalization.statusLabel,
                    style: TextStyle(
                      color: statusColor,
                      fontSize: 9.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _LifecycleMetric(
                    label: 'Wajib selesai',
                    value:
                        '${readiness.finishedParticipants}/${readiness.requiredParticipants}',
                  ),
                ),
                Expanded(
                  child: _LifecycleMetric(
                    label: 'Tidak hadir',
                    value: '${readiness.absentParticipants}',
                  ),
                ),
                Expanded(
                  child: _LifecycleMetric(
                    label: 'Uraian tertunda',
                    value: '${readiness.pendingManualCorrections}',
                  ),
                ),
              ],
            ),
            if (finalization.finalizedAt != null) ...[
              const SizedBox(height: 9),
              _LifecycleAudit(
                icon: Icons.lock_clock_outlined,
                label:
                    'Final ${_formatLifecycleTime(finalization.finalizedAt!)}${_actorSuffix(finalization.finalizedBy)}',
              ),
            ],
            if (finalization.publishedAt != null) ...[
              const SizedBox(height: 5),
              _LifecycleAudit(
                icon: Icons.visibility_outlined,
                label:
                    'Dipublikasikan ${_formatLifecycleTime(finalization.publishedAt!)}${_actorSuffix(finalization.publishedBy)}',
              ),
            ],
            if (!finalization.isFinal && blockers.isNotEmpty) ...[
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF8E1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  blockers.join('\n'),
                  style: const TextStyle(
                    color: Color(0xFF795800),
                    fontSize: 10,
                    height: 1.45,
                  ),
                ),
              ),
            ],
            if (finalization.canManage) ...[
              const SizedBox(height: 11),
              if (finalization.canFinalize)
                _LifecycleButton(
                  key: const Key('central-results-finalize'),
                  label: 'Finalisasi Hasil',
                  icon: Icons.lock_rounded,
                  busy: busyAction == CentralExamResultLifecycleAction.finalize,
                  onPressed: busyAction == null
                      ? () =>
                            onAction(CentralExamResultLifecycleAction.finalize)
                      : null,
                ),
              if (finalization.canPublish)
                _LifecycleButton(
                  key: const Key('central-results-publish'),
                  label: 'Publikasikan ke Siswa',
                  icon: Icons.campaign_rounded,
                  busy: busyAction == CentralExamResultLifecycleAction.publish,
                  onPressed: busyAction == null
                      ? () => onAction(CentralExamResultLifecycleAction.publish)
                      : null,
                ),
              if (finalization.canUnpublish)
                _LifecycleButton(
                  key: const Key('central-results-unpublish'),
                  label: 'Batalkan Publikasi',
                  icon: Icons.visibility_off_outlined,
                  busy:
                      busyAction == CentralExamResultLifecycleAction.unpublish,
                  outlined: true,
                  onPressed: busyAction == null
                      ? () =>
                            onAction(CentralExamResultLifecycleAction.unpublish)
                      : null,
                ),
              if (finalization.canCancelFinalization)
                _LifecycleButton(
                  key: const Key('central-results-cancel-finalization'),
                  label: 'Buka Kembali Hasil',
                  icon: Icons.lock_open_rounded,
                  busy:
                      busyAction ==
                      CentralExamResultLifecycleAction.cancelFinalization,
                  outlined: true,
                  onPressed: busyAction == null
                      ? () => onAction(
                          CentralExamResultLifecycleAction.cancelFinalization,
                        )
                      : null,
                ),
            ] else ...[
              const SizedBox(height: 9),
              const Text(
                'Akun ini memiliki akses lihat. Perubahan status hasil hanya tersedia bagi pengelola paket atau guru mata pelajaran terkait.',
                style: TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10,
                  height: 1.4,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _LifecycleMetric extends StatelessWidget {
  const _LifecycleMetric({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Column(
    children: [
      Text(
        value,
        style: const TextStyle(
          color: NusaColors.primary,
          fontSize: 16,
          fontWeight: FontWeight.w900,
        ),
      ),
      const SizedBox(height: 2),
      Text(
        label,
        textAlign: TextAlign.center,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 8.5),
      ),
    ],
  );
}

class _LifecycleAudit extends StatelessWidget {
  const _LifecycleAudit({required this.icon, required this.label});
  final IconData icon;
  final String label;
  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, size: 13, color: NusaColors.textSecondary),
      const SizedBox(width: 5),
      Expanded(
        child: Text(
          label,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
      ),
    ],
  );
}

class _LifecycleButton extends StatelessWidget {
  const _LifecycleButton({
    required this.label,
    required this.icon,
    required this.busy,
    required this.onPressed,
    this.outlined = false,
    super.key,
  });
  final String label;
  final IconData icon;
  final bool busy;
  final VoidCallback? onPressed;
  final bool outlined;

  @override
  Widget build(BuildContext context) {
    final iconWidget = busy
        ? const SizedBox.square(
            dimension: 16,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        : Icon(icon);
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: outlined
          ? OutlinedButton.icon(
              onPressed: onPressed,
              icon: iconWidget,
              label: Text(busy ? 'Memproses...' : label),
            )
          : FilledButton.icon(
              onPressed: onPressed,
              icon: iconWidget,
              label: Text(busy ? 'Memproses...' : label),
            ),
    );
  }
}

String _actorSuffix(String? actor) =>
    actor == null || actor.trim().isEmpty ? '' : ' · oleh $actor';

String _formatLifecycleTime(DateTime value) {
  final local = value.toLocal();
  String two(int number) => number.toString().padLeft(2, '0');
  return '${two(local.day)}-${two(local.month)}-${local.year} ${two(local.hour)}:${two(local.minute)}';
}

class _TargetAndAction extends StatelessWidget {
  const _TargetAndAction({
    required this.data,
    required this.applying,
    required this.onApply,
    required this.onCorrection,
  });
  final CentralExamResultsDetail data;
  final bool applying;
  final VoidCallback onApply;
  final VoidCallback onCorrection;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Icon(Icons.flag_outlined, color: NusaColors.primary),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'Tujuan Nilai',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              Text(
                '${data.results.questionCount} soal · bobot ${assessmentNumber(data.results.totalWeight)}',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9.5,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          for (final item in data.results.assessment.classes)
            Padding(
              padding: const EdgeInsets.only(bottom: 5),
              child: Text(
                '${item.name} · ${item.component ?? 'Komponen nilai belum diatur'}',
                style: const TextStyle(fontSize: 10.5),
              ),
            ),
          const SizedBox(height: 6),
          if (data.canApply)
            FilledButton.icon(
              key: const Key('central-results-apply'),
              onPressed: applying || !data.finalization.isFinal
                  ? null
                  : onApply,
              icon: applying
                  ? const SizedBox.square(
                      dimension: 17,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.publish_rounded),
              label: Text(applying ? 'Memproses...' : 'Terapkan ke Nilai'),
            )
          else
            const Text(
              'Akun ini memiliki akses lihat. Penerapan nilai hanya tersedia bagi pengelola paket/guru mata pelajaran terkait.',
              style: TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
                height: 1.4,
              ),
            ),
          if (data.canApply && !data.finalization.isFinal) ...[
            const SizedBox(height: 6),
            const Text(
              'Finalisasi hasil terlebih dahulu sebelum menerapkannya ke komponen nilai.',
              style: TextStyle(
                color: Color(0xFF9A7000),
                fontSize: 10,
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: 8),
          OutlinedButton.icon(
            key: const Key('central-results-correction'),
            onPressed: onCorrection,
            icon: const Icon(Icons.rate_review_outlined),
            label: Text(
              data.canApply ? 'Koreksi Uraian' : 'Lihat Koreksi Uraian',
            ),
          ),
          if (data.results.summary.needsCorrection > 0) ...[
            const SizedBox(height: 6),
            Text(
              '${data.results.summary.needsCorrection} peserta masih memerlukan koreksi sebelum hasil menjadi final.',
              style: const TextStyle(
                color: Color(0xFF9A7000),
                fontSize: 10,
                height: 1.4,
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

class _ResultCard extends StatelessWidget {
  const _ResultCard({
    required this.item,
    required this.questionCount,
    super.key,
  });
  final AssessmentResultItem item;
  final int questionCount;
  @override
  Widget build(BuildContext context) {
    final color = assessmentToneColor(item.statusTone);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.student.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        '${item.className} · Absen ${item.student.rollNumber ?? '-'} · NISN ${item.student.nationalStudentNumber ?? '-'}',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 9.5,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  assessmentNumber(item.score),
                  style: TextStyle(
                    color: color,
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 7,
              runSpacing: 6,
              children: [
                AssessmentToneBadge(
                  label: item.statusLabel,
                  tone: item.statusTone,
                ),
                AssessmentToneBadge(
                  label: item.appliedToGrades
                      ? 'Sudah masuk nilai'
                      : 'Belum masuk nilai',
                  tone: item.appliedToGrades ? 'aktif' : 'netral',
                ),
                AssessmentInlineInfo(
                  icon: Icons.quiz_outlined,
                  label: '${item.savedAnswers}/$questionCount dijawab',
                ),
                AssessmentInlineInfo(
                  icon: Icons.check_rounded,
                  label: '${item.correct} benar',
                ),
                if (item.manualCorrections > 0)
                  AssessmentInlineInfo(
                    icon: Icons.rate_review_outlined,
                    label: '${item.manualCorrections} uraian belum dikoreksi',
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

String _message(Object error) => error is AppException
    ? error.message
    : 'Hasil ujian terpusat belum dapat dimuat.';
