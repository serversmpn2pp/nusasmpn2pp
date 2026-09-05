import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/application/class_assessment_monitoring_controller.dart';
import 'package:nusa/features/class_assessment/application/class_assessment_operations_controller.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_correction.dart';
import 'package:nusa/features/class_assessment/presentation/widgets/class_assessment_operation_widgets.dart';

class ClassAssessmentCorrectionView extends ConsumerStatefulWidget {
  const ClassAssessmentCorrectionView({required this.assessmentId, super.key});

  final int assessmentId;

  @override
  ConsumerState<ClassAssessmentCorrectionView> createState() =>
      _ClassAssessmentCorrectionViewState();
}

class _ClassAssessmentCorrectionViewState
    extends ConsumerState<ClassAssessmentCorrectionView> {
  int? _classId;
  String _status = 'semua';
  final Map<int, String> _edits = {};
  bool _saving = false;

  AssessmentCorrectionRequest get _request =>
      (assessmentId: widget.assessmentId, classId: _classId, status: _status);

  Future<void> _refresh() async {
    final request = _request;
    ref.invalidate(classAssessmentCorrectionsProvider(request));
    await ref.read(classAssessmentCorrectionsProvider(request).future);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(classAssessmentCorrectionsProvider(_request));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Koreksi Uraian'),
        actions: [
          IconButton(
            tooltip: 'Lihat hasil',
            onPressed: () =>
                context.push('/asesmen-kelas/${widget.assessmentId}/hasil'),
            icon: const Icon(Icons.assessment_outlined),
          ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: _saving ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => AssessmentOperationError(
            message: _message(error, 'Jawaban uraian belum dapat dimuat.'),
            onRetry: _refresh,
          ),
          data: (data) => RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              key: const PageStorageKey<String>('class-assessment-correction'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
              children: [
                AssessmentOperationHero(
                  assessment: data.assessment,
                  eyebrow: 'KOREKSI JAWABAN URAIAN',
                ),
                const SizedBox(height: 11),
                AssessmentMetricsGrid(
                  items: [
                    AssessmentMetricData(
                      label: 'Soal manual',
                      value: '${data.manualQuestionCount}',
                      icon: Icons.subject_rounded,
                      color: NusaColors.primary,
                    ),
                    AssessmentMetricData(
                      label: 'Jawaban terisi',
                      value: '${data.summary.answered}',
                      icon: Icons.edit_note_rounded,
                      color: NusaColors.primaryLight,
                    ),
                    AssessmentMetricData(
                      label: 'Belum dikoreksi',
                      value: '${data.summary.pending}',
                      icon: Icons.rate_review_outlined,
                      color: const Color(0xFF9A7000),
                    ),
                    AssessmentMetricData(
                      label: 'Sudah dikoreksi',
                      value: '${data.summary.corrected}',
                      icon: Icons.task_alt_rounded,
                      color: NusaColors.success,
                    ),
                  ],
                ),
                const SizedBox(height: 11),
                AssessmentFilterCard(
                  classes: data.classes,
                  statuses: data.statuses,
                  selectedClassId: _classId,
                  selectedStatus: _status,
                  classKey: const Key('assessment-correction-class-filter'),
                  statusKey: const Key('assessment-correction-status-filter'),
                  onClassChanged: (value) => _changeFilter(classId: value),
                  onStatusChanged: (value) => _changeFilter(status: value),
                ),
                const SizedBox(height: 11),
                _SaveNotice(changes: _edits.length),
                const SizedBox(height: 14),
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Jawaban Peserta',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    Text(
                      '${data.items.length} jawaban',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 9),
                if (data.manualQuestionCount == 0)
                  const AssessmentOperationEmpty(
                    message: 'Asesmen ini tidak memiliki soal uraian atau unggahan file.',
                  )
                else if (data.items.isEmpty)
                  const AssessmentOperationEmpty(
                    message: 'Tidak ada jawaban yang sesuai dengan filter.',
                  )
                else
                  for (final item in data.items) ...[
                    _CorrectionCard(
                      key: Key('assessment-correction-${item.id}'),
                      item: item,
                      onScoreChanged: item.answerId == null
                          ? null
                          : (value) => setState(
                              () => _edits[item.answerId!] = value.trim(),
                            ),
                    ),
                    const SizedBox(height: 9),
                  ],
                if (data.items.isNotEmpty) const SizedBox(height: 76),
              ],
            ),
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: NusaColors.outline)),
          ),
          child: FilledButton.icon(
            key: const Key('assessment-correction-save'),
            onPressed: _edits.isEmpty || _saving ? null : _save,
            icon: _saving
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.save_outlined),
            label: Text(
              _saving
                  ? 'Menyimpan...'
                  : 'Simpan Koreksi${_edits.isEmpty ? '' : ' (${_edits.length})'}',
            ),
          ),
        ),
      ),
    );
  }

  void _changeFilter({int? classId, String? status}) {
    setState(() {
      _classId = status == null ? classId : _classId;
      _status = status ?? _status;
      _edits.clear();
    });
  }

  Future<void> _save() async {
    final data = ref.read(classAssessmentCorrectionsProvider(_request)).value;
    if (data == null) return;
    final items = {
      for (final item in data.items)
        if (item.answerId != null) item.answerId!: item,
    };
    final payload = <AssessmentScorePayload>[];

    for (final entry in _edits.entries) {
      final item = items[entry.key];
      if (item == null) continue;
      final text = entry.value.replaceAll(',', '.');
      final score = text.isEmpty ? null : double.tryParse(text);
      if (text.isNotEmpty && score == null) {
        _snack('Skor ${item.student.name} harus berupa angka.');
        return;
      }
      if (score != null && (score < 0 || score > item.question.weight)) {
        _snack(
          'Skor ${item.student.name} harus antara 0 dan ${assessmentNumber(item.question.weight)}.',
        );
        return;
      }
      payload.add(AssessmentScorePayload(answerId: entry.key, score: score));
    }
    if (payload.isEmpty) return;

    setState(() => _saving = true);
    try {
      await ref
          .read(classAssessmentOperationsActionsProvider)
          .saveCorrections(request: _request, scores: payload);
      ref.invalidate(classAssessmentCorrectionsProvider(_request));
      ref.invalidate(
        classAssessmentResultsProvider((
          assessmentId: widget.assessmentId,
          classId: null,
          status: 'semua',
        )),
      );
      if (mounted) {
        setState(_edits.clear);
        _snack('${payload.length} koreksi jawaban berhasil disimpan.');
      }
    } catch (error) {
      if (mounted) _snack(_message(error, 'Koreksi belum dapat disimpan.'));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _SaveNotice extends StatelessWidget {
  const _SaveNotice({required this.changes});

  final int changes;

  @override
  Widget build(BuildContext context) {
    final changed = changes > 0;
    final color = changed ? const Color(0xFF9A7000) : NusaColors.primary;
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Row(
        children: [
          Icon(
            changed ? Icons.edit_note_rounded : Icons.info_outline_rounded,
            size: 19,
            color: color,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              changed ? '$changes perubahan belum disimpan.' : 'Isi skor sesuai bobot maksimal. Kosongkan skor untuk membatalkan koreksi.',
              style: const TextStyle(fontSize: 10.5, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}

class _CorrectionCard extends StatelessWidget {
  const _CorrectionCard({
    required this.item,
    required this.onScoreChanged,
    super.key,
  });

  final AssessmentCorrectionItem item;
  final ValueChanged<String>? onScoreChanged;

  @override
  Widget build(BuildContext context) {
    final answered = item.answered;
    final statusTone = !answered
        ? 'netral'
        : item.corrected
        ? 'aktif'
        : 'peringatan';
    final statusLabel = !answered
        ? 'Belum dijawab'
        : item.corrected
        ? 'Sudah dikoreksi'
        : 'Belum dikoreksi';
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
                const SizedBox(width: 7),
                AssessmentToneBadge(label: statusLabel, tone: statusTone),
              ],
            ),
            const Divider(height: 21),
            Wrap(
              spacing: 7,
              runSpacing: 6,
              children: [
                AssessmentToneBadge(
                  label: 'No. ${item.question.number}',
                  tone: 'netral',
                ),
                AssessmentToneBadge(
                  label: item.question.typeLabel,
                  tone: 'netral',
                ),
                AssessmentToneBadge(
                  label: 'Maks. ${assessmentNumber(item.question.weight)}',
                  tone: 'peringatan',
                ),
              ],
            ),
            const SizedBox(height: 10),
            const Text(
              'Pertanyaan',
              style: TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 9.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 3),
            Text(item.question.question, style: const TextStyle(height: 1.4)),
            if (item.question.rubric?.trim().isNotEmpty == true) ...[
              const SizedBox(height: 9),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(9),
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Text(
                  'Rubrik: ${item.question.rubric}',
                  style: const TextStyle(fontSize: 10, height: 1.4),
                ),
              ),
            ],
            const SizedBox(height: 10),
            const Text(
              'Jawaban siswa',
              style: TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 9.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: answered ? Colors.white : NusaColors.background,
                borderRadius: BorderRadius.circular(11),
                border: Border.all(color: NusaColors.outline),
              ),
              child: SelectableText(
                answered ? item.answer : 'Belum dijawab',
                style: TextStyle(
                  color: answered
                      ? NusaColors.textPrimary
                      : NusaColors.textSecondary,
                  fontSize: 11,
                  height: 1.45,
                  fontStyle: answered ? FontStyle.normal : FontStyle.italic,
                ),
              ),
            ),
            if (answered && item.answerId != null) ...[
              const SizedBox(height: 11),
              TextFormField(
                key: Key(
                  'assessment-correction-score-${item.answerId}-${item.score}',
                ),
                initialValue: item.score == null
                    ? ''
                    : assessmentNumber(item.score),
                onChanged: onScoreChanged,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'[0-9,.]')),
                ],
                decoration: InputDecoration(
                  labelText: 'Skor',
                  hintText: '0 - ${assessmentNumber(item.question.weight)}',
                  prefixIcon: const Icon(Icons.stars_outlined),
                  suffixText: '/ ${assessmentNumber(item.question.weight)}',
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

String _message(Object error, String fallback) =>
    error is AppException ? error.message : fallback;
