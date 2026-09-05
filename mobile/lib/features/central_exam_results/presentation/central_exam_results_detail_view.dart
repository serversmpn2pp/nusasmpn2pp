import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
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
                  _TargetAndAction(
                    data: data,
                    applying: _applying,
                    onApply: () => _confirmApply(data),
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
}

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

class _TargetAndAction extends StatelessWidget {
  const _TargetAndAction({
    required this.data,
    required this.applying,
    required this.onApply,
  });
  final CentralExamResultsDetail data;
  final bool applying;
  final VoidCallback onApply;
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
              onPressed: applying ? null : onApply,
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
          if (data.results.summary.needsCorrection > 0) ...[
            const SizedBox(height: 8),
            const Text(
              'Jawaban uraian yang belum final perlu diselesaikan sebelum nilai diterapkan. Koreksi uraian terpusat akan dilanjutkan pada tahap berikutnya.',
              style: TextStyle(
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
