import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/application/class_assessment_monitoring_controller.dart';
import 'package:nusa/features/class_assessment/application/class_assessment_operations_controller.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';
import 'package:nusa/features/class_assessment/presentation/widgets/class_assessment_operation_widgets.dart';

class ClassAssessmentResultsView extends ConsumerStatefulWidget {
  const ClassAssessmentResultsView({required this.assessmentId, super.key});

  final int assessmentId;

  @override
  ConsumerState<ClassAssessmentResultsView> createState() =>
      _ClassAssessmentResultsViewState();
}

class _ClassAssessmentResultsViewState
    extends ConsumerState<ClassAssessmentResultsView> {
  int? _classId;
  String _status = 'semua';
  bool _applying = false;

  AssessmentOperationRequest get _request =>
      (assessmentId: widget.assessmentId, classId: _classId, status: _status);

  Future<void> _refresh() async {
    final request = _request;
    ref.invalidate(classAssessmentResultsProvider(request));
    await ref.read(classAssessmentResultsProvider(request).future);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(classAssessmentResultsProvider(_request));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Hasil Asesmen'),
        actions: [
          IconButton(
            tooltip: 'Monitoring asesmen',
            onPressed: () => context.push(
              '/asesmen-kelas/${widget.assessmentId}/monitoring',
            ),
            icon: const Icon(Icons.monitor_heart_outlined),
          ),
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
            message: _message(error, 'Hasil asesmen belum dapat dimuat.'),
            onRetry: _refresh,
          ),
          data: (data) => RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              key: const PageStorageKey<String>('class-assessment-results'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                AssessmentOperationHero(
                  assessment: data.assessment,
                  eyebrow: 'HASIL ASESMEN KELAS',
                ),
                const SizedBox(height: 11),
                _TargetCard(data: data),
                const SizedBox(height: 11),
                _OperationActions(
                  applying: _applying,
                  onCorrection: () => context.push(
                    '/asesmen-kelas/${widget.assessmentId}/koreksi-uraian',
                  ),
                  onApply: () => _confirmApply(data),
                ),
                const SizedBox(height: 11),
                AssessmentMetricsGrid(
                  items: [
                    AssessmentMetricData(
                      label: 'Peserta',
                      value: '${data.summary.total}',
                      icon: Icons.groups_rounded,
                      color: NusaColors.primary,
                    ),
                    AssessmentMetricData(
                      label: 'Sudah selesai',
                      value: '${data.summary.finished}',
                      icon: Icons.task_alt_rounded,
                      color: NusaColors.success,
                    ),
                    AssessmentMetricData(
                      label: 'Rata-rata final',
                      value: assessmentNumber(data.summary.average),
                      icon: Icons.insights_rounded,
                      color: NusaColors.primaryLight,
                    ),
                    AssessmentMetricData(
                      label: 'Nilai tertinggi',
                      value: assessmentNumber(data.summary.highest),
                      icon: Icons.emoji_events_outlined,
                      color: const Color(0xFF9A7000),
                    ),
                    AssessmentMetricData(
                      label: data.assessment.minimumScore == null
                          ? 'Hasil final'
                          : 'Tuntas',
                      value: data.assessment.minimumScore == null
                          ? '${data.summary.finalResults}'
                          : '${data.summary.passed}',
                      icon: Icons.verified_outlined,
                      color: NusaColors.success,
                    ),
                    AssessmentMetricData(
                      label: 'Perlu koreksi',
                      value: '${data.summary.needsCorrection}',
                      icon: Icons.rate_review_outlined,
                      color: const Color(0xFF9A7000),
                    ),
                  ],
                ),
                const SizedBox(height: 11),
                AssessmentFilterCard(
                  classes: data.classes,
                  statuses: data.statuses,
                  selectedClassId: _classId,
                  selectedStatus: _status,
                  classKey: const Key('assessment-results-class-filter'),
                  statusKey: const Key('assessment-results-status-filter'),
                  onClassChanged: (value) => setState(() => _classId = value),
                  onStatusChanged: (value) => setState(() => _status = value),
                ),
                const SizedBox(height: 11),
                _CorrectionNotice(summary: data.summary),
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
                      '${data.items.length} siswa',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 9),
                if (data.items.isEmpty)
                  const AssessmentOperationEmpty(
                    message: 'Belum ada hasil siswa yang sesuai dengan filter.',
                  )
                else
                  for (final item in data.items) ...[
                    _ResultCard(
                      key: Key('assessment-result-${item.id}'),
                      item: item,
                      questionCount: data.questionCount,
                    ),
                    const SizedBox(height: 9),
                  ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _confirmApply(AssessmentResultsData data) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Masukkan hasil ke nilai?'),
        content: Text(
          'Koreksi objektif akan dijalankan terlebih dahulu. Hanya peserta yang sudah selesai dan seluruh jawabannya sudah dikoreksi yang dapat dimasukkan.\n\n'
          'Hasil final saat ini: ${data.summary.finalResults}\n'
          'Masih perlu koreksi: ${data.summary.needsCorrection}\n'
          'Belum selesai: ${data.summary.notFinished}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('assessment-results-confirm-apply'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Masukkan Nilai'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _applying = true);
    try {
      final result = await ref
          .read(classAssessmentOperationsActionsProvider)
          .applyResults(widget.assessmentId);
      ref.invalidate(classAssessmentResultsProvider(_request));
      ref.invalidate(
        classAssessmentMonitoringProvider((
          assessmentId: widget.assessmentId,
          classId: null,
          status: 'semua',
        )),
      );
      ref.invalidate(
        classAssessmentCorrectionsProvider((
          assessmentId: widget.assessmentId,
          classId: null,
          status: 'semua',
        )),
      );
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(result.message)));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_message(error, 'Nilai belum dapat dimasukkan.')),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _applying = false);
    }
  }
}

class _OperationActions extends StatelessWidget {
  const _OperationActions({
    required this.applying,
    required this.onCorrection,
    required this.onApply,
  });

  final bool applying;
  final VoidCallback onCorrection;
  final VoidCallback onApply;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Tindakan Nilai',
            style: TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          const Text(
            'Selesaikan koreksi uraian, lalu masukkan hasil final ke komponen nilai kelas.',
            style: TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 10),
          LayoutBuilder(
            builder: (context, constraints) {
              final correction = OutlinedButton.icon(
                key: const Key('assessment-results-open-correction'),
                onPressed: applying ? null : onCorrection,
                icon: const Icon(Icons.rate_review_outlined),
                label: const Text('Koreksi Uraian'),
              );
              final apply = FilledButton.icon(
                key: const Key('assessment-results-apply'),
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
                label: Text(applying ? 'Memproses...' : 'Masukkan ke Nilai'),
              );
              if (constraints.maxWidth < 330) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [correction, const SizedBox(height: 8), apply],
                );
              }
              return Row(
                children: [
                  Expanded(child: correction),
                  const SizedBox(width: 8),
                  Expanded(child: apply),
                ],
              );
            },
          ),
        ],
      ),
    ),
  );
}

class _TargetCard extends StatelessWidget {
  const _TargetCard({required this.data});

  final AssessmentResultsData data;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
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
                '${data.questionCount} soal · bobot ${assessmentNumber(data.totalWeight)}',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9.5,
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          for (final item in data.assessment.classes)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  Container(
                    width: 7,
                    height: 7,
                    decoration: const BoxDecoration(
                      color: NusaColors.accent,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 7),
                  Text(
                    item.name,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      item.component ?? 'Komponen nilai belum tersedia',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.right,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
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

class _CorrectionNotice extends StatelessWidget {
  const _CorrectionNotice({required this.summary});

  final AssessmentResultsSummary summary;

  @override
  Widget build(BuildContext context) {
    final needsCorrection = summary.needsCorrection > 0;
    final color = needsCorrection
        ? const Color(0xFF9A7000)
        : NusaColors.success;
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            needsCorrection
                ? Icons.info_outline_rounded
                : Icons.check_circle_outline,
            color: color,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              needsCorrection
                  ? '${summary.needsCorrection} hasil masih menunggu koreksi. Koreksi objektif dijalankan otomatis saat nilai dimasukkan; jawaban uraian dapat diperiksa dari tombol di atas.'
                  : '${summary.finalResults} hasil sudah final; ${summary.appliedToGrades} sudah masuk ke komponen nilai.',
              style: const TextStyle(fontSize: 10.5, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
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
    final progress = questionCount == 0
        ? 0.0
        : (item.savedAnswers / questionCount).clamp(0.0, 1.0);
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
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 9.5,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      assessmentNumber(item.score),
                      style: TextStyle(
                        color: color,
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      _scoreState(item.scoreState),
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 8.5,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 9),
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 7,
                backgroundColor: NusaColors.outline,
                color: NusaColors.primaryLight,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              '${item.savedAnswers} dari $questionCount soal dijawab · ${item.workStatusLabel}',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 9.5,
              ),
            ),
            const SizedBox(height: 9),
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
                  icon: Icons.check_rounded,
                  label: '${item.correct} benar',
                ),
                AssessmentInlineInfo(
                  icon: Icons.close_rounded,
                  label: '${item.incorrect} salah',
                ),
                if (item.manualCorrections > 0)
                  AssessmentInlineInfo(
                    icon: Icons.rate_review_outlined,
                    label: '${item.manualCorrections} uraian belum dikoreksi',
                  ),
                if (item.finishedAt != null)
                  AssessmentInlineInfo(
                    icon: Icons.schedule_rounded,
                    label: 'Selesai ${assessmentTime(item.finishedAt)}',
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

String _scoreState(String value) => switch (value) {
  'akhir' => 'Nilai akhir',
  'sementara' => 'Nilai sementara',
  _ => 'Belum tersedia',
};

String _message(Object error, String fallback) =>
    error is AppException ? error.message : fallback;
