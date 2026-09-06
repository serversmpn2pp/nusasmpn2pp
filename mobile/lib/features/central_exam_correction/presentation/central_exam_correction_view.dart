import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_correction/application/central_exam_correction_controller.dart';
import 'package:nusa/features/central_exam_results/application/central_exam_results_controller.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_correction.dart';
import 'package:nusa/features/class_assessment/presentation/widgets/class_assessment_operation_widgets.dart';

class CentralExamCorrectionView extends ConsumerStatefulWidget {
  const CentralExamCorrectionView({
    required this.eventId,
    required this.scheduleId,
    super.key,
  });

  final int eventId;
  final int scheduleId;

  @override
  ConsumerState<CentralExamCorrectionView> createState() =>
      _CentralExamCorrectionViewState();
}

class _CentralExamCorrectionViewState
    extends ConsumerState<CentralExamCorrectionView> {
  int? _classId;
  String _status = 'semua';
  final Map<int, String> _edits = {};
  Timer? _autoSaveDebounce;
  Future<bool>? _activeAutoSave;
  bool _autoSaveRequested = false;
  bool _dirty = false;
  bool _autoSaving = false;
  int _draftRevision = 0;
  String? _autoSaveError;
  DateTime? _lastSavedAt;
  AssessmentCorrectionData? _savedData;

  CentralExamCorrectionRequest get _request => (
    eventId: widget.eventId,
    scheduleId: widget.scheduleId,
    classId: _classId,
    status: _status,
  );

  @override
  void dispose() {
    _autoSaveDebounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(centralExamCorrectionsProvider(_request));
    return PopScope(
      canPop: !_dirty && !_autoSaving,
      onPopInvokedWithResult: _handlePop,
      child: Scaffold(
        backgroundColor: NusaColors.background,
        appBar: AppBar(
          title: const Text('Koreksi Uraian Terpusat'),
          actions: [
            IconButton(
              tooltip: 'Perbarui',
              onPressed: _autoSaving ? null : _refresh,
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
            data: (remoteData) {
              final data = _savedData ?? remoteData;
              return RefreshIndicator(
                onRefresh: _refresh,
                child: ListView(
                  key: const PageStorageKey('central-exam-correction'),
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                  children: [
                    AssessmentOperationHero(
                      assessment: data.assessment,
                      eyebrow: 'KOREKSI URAIAN UJIAN TERPUSAT',
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
                      classKey: const Key('central-correction-class-filter'),
                      statusKey: const Key('central-correction-status-filter'),
                      onClassChanged: (value) =>
                          _changeFilter(data, classId: value),
                      onStatusChanged: (value) =>
                          _changeFilter(data, status: value),
                    ),
                    const SizedBox(height: 11),
                    _AutoSaveNotice(
                      canCorrect: data.canCorrect,
                      dirty: _dirty,
                      saving: _autoSaving,
                      error: _autoSaveError,
                      lastSavedAt: _lastSavedAt,
                      onRetry: _autoSaveError == null
                          ? null
                          : () => _runAutoSave(data, showError: true),
                    ),
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
                        message: 'Paket ini tidak memiliki soal uraian atau unggahan file.',
                      )
                    else if (data.items.isEmpty)
                      const AssessmentOperationEmpty(
                        message: 'Tidak ada jawaban yang sesuai dengan filter.',
                      )
                    else
                      for (final item in data.items) ...[
                        _CentralCorrectionCard(
                          key: Key('central-correction-${item.id}'),
                          item: item,
                          canCorrect: data.canCorrect,
                          onScoreChanged:
                              !data.canCorrect || item.answerId == null
                              ? null
                              : (value) => _markDirty(data, item, value),
                        ),
                        const SizedBox(height: 9),
                      ],
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Future<void> _handlePop(bool didPop, Object? result) async {
    if (didPop || !mounted) return;
    final data =
        _savedData ?? ref.read(centralExamCorrectionsProvider(_request)).value;
    var canLeave = data == null;
    if (data != null) canLeave = await _flushAutoSave(data, showError: true);
    if (!canLeave && mounted) {
      canLeave =
          await showDialog<bool>(
            context: context,
            builder: (context) => AlertDialog(
              icon: const Icon(Icons.cloud_off_rounded),
              title: const Text('Skor belum tersimpan'),
              content: const Text(
                'Autosave belum berhasil. Tetap di halaman ini agar skor tidak hilang, atau keluar tanpa menyimpan.',
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context, false),
                  child: const Text('Tetap di Sini'),
                ),
                TextButton(
                  onPressed: () => Navigator.pop(context, true),
                  child: const Text('Keluar Tanpa Menyimpan'),
                ),
              ],
            ),
          ) ??
          false;
    }
    if (!canLeave || !mounted) return;
    setState(() => _dirty = false);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) Navigator.of(context).pop(result);
    });
  }

  void _markDirty(
    AssessmentCorrectionData data,
    AssessmentCorrectionItem item,
    String value,
  ) {
    _autoSaveDebounce?.cancel();
    setState(() {
      _edits[item.answerId!] = value.trim();
      _dirty = true;
      _draftRevision++;
      _autoSaveError = null;
    });
    _autoSaveDebounce = Timer(
      const Duration(milliseconds: 900),
      () => unawaited(_runAutoSave(data)),
    );
  }

  Future<void> _refresh() async {
    final current =
        _savedData ?? ref.read(centralExamCorrectionsProvider(_request)).value;
    if (current != null && !await _flushAutoSave(current, showError: true)) {
      return;
    }
    setState(() => _savedData = null);
    ref.invalidate(centralExamCorrectionsProvider(_request));
    await ref.read(centralExamCorrectionsProvider(_request).future);
  }

  Future<void> _changeFilter(
    AssessmentCorrectionData data, {
    int? classId,
    String? status,
  }) async {
    if (!await _flushAutoSave(data, showError: true) || !mounted) return;
    setState(() {
      _classId = status == null ? classId : _classId;
      _status = status ?? _status;
      _savedData = null;
      _edits.clear();
    });
  }

  Future<bool> _flushAutoSave(
    AssessmentCorrectionData data, {
    required bool showError,
  }) async {
    _autoSaveDebounce?.cancel();
    final active = _activeAutoSave;
    if (active != null) await active;
    if (!_dirty) return true;
    return _runAutoSave(data, showError: showError);
  }

  Future<bool> _runAutoSave(
    AssessmentCorrectionData data, {
    bool showError = false,
  }) {
    final active = _activeAutoSave;
    if (active != null) {
      _autoSaveRequested = true;
      return active.then((_) {
        if (!_dirty) return true;
        return _runAutoSave(_savedData ?? data, showError: showError);
      });
    }

    late Future<bool> operation;
    operation = _executeAutoSave(data, showError: showError).whenComplete(() {
      if (identical(_activeAutoSave, operation)) _activeAutoSave = null;
      if (_autoSaveRequested && mounted) {
        _autoSaveRequested = false;
        final current = _savedData ?? data;
        if (_dirty) {
          _autoSaveDebounce = Timer(
            const Duration(milliseconds: 250),
            () => unawaited(_runAutoSave(current)),
          );
        }
      }
    });
    _activeAutoSave = operation;
    return operation;
  }

  Future<bool> _executeAutoSave(
    AssessmentCorrectionData data, {
    required bool showError,
  }) async {
    if (!data.canCorrect || !_dirty) return !data.canCorrect;
    late List<AssessmentScorePayload> scores;
    try {
      scores = _scorePayload(data);
    } catch (error) {
      final message = error is FormatException
          ? error.message
          : 'Skor belum dapat diproses.';
      if (mounted) {
        setState(() => _autoSaveError = message);
        if (showError) _snack(message);
      }
      return false;
    }

    final revision = _draftRevision;
    if (mounted) {
      setState(() {
        _autoSaving = true;
        _autoSaveError = null;
      });
    }
    try {
      final saved = await ref.read(centralExamCorrectionSaveProvider)(
        request: _request,
        scores: scores,
      );
      if (mounted) {
        setState(() {
          _savedData = saved;
          if (revision == _draftRevision) {
            _dirty = false;
            _edits.clear();
          }
          _lastSavedAt = DateTime.now();
          _autoSaveError = null;
        });
      }
      ref.invalidate(centralExamResultsDetailProvider);
      return true;
    } catch (error) {
      final message = _message(error);
      if (mounted) {
        setState(() => _autoSaveError = message);
        if (showError) _snack(message);
      }
      return false;
    } finally {
      if (mounted) setState(() => _autoSaving = false);
    }
  }

  List<AssessmentScorePayload> _scorePayload(AssessmentCorrectionData data) {
    final items = {
      for (final item in data.items)
        if (item.answerId != null) item.answerId!: item,
    };
    return _edits.entries
        .map((entry) {
          final item = items[entry.key];
          if (item == null) {
            throw const FormatException(
              'Jawaban sudah berubah. Muat ulang halaman.',
            );
          }
          final text = entry.value.replaceAll(',', '.');
          final score = text.isEmpty ? null : double.tryParse(text);
          if (text.isNotEmpty && score == null) {
            throw FormatException(
              'Skor ${item.student.name} harus berupa angka.',
            );
          }
          if (score != null && (score < 0 || score > item.question.weight)) {
            throw FormatException(
              'Skor ${item.student.name} harus antara 0 dan ${assessmentNumber(item.question.weight)}.',
            );
          }
          return AssessmentScorePayload(answerId: entry.key, score: score);
        })
        .toList(growable: false);
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _AutoSaveNotice extends StatelessWidget {
  const _AutoSaveNotice({
    required this.canCorrect,
    required this.dirty,
    required this.saving,
    required this.error,
    required this.lastSavedAt,
    required this.onRetry,
  });

  final bool canCorrect;
  final bool dirty;
  final bool saving;
  final String? error;
  final DateTime? lastSavedAt;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final (icon, color, text) = !canCorrect
        ? (
            Icons.visibility_outlined,
            NusaColors.primary,
            'Mode lihat. Hanya pengelola paket atau guru mapel terkait yang dapat mengubah skor.',
          )
        : error != null
        ? (Icons.cloud_off_rounded, Colors.red.shade700, error!)
        : saving
        ? (
            Icons.cloud_upload_outlined,
            NusaColors.primary,
            'Menyimpan skor otomatis...',
          )
        : dirty
        ? (
            Icons.schedule_rounded,
            const Color(0xFF9A7000),
            'Perubahan menunggu autosave.',
          )
        : lastSavedAt != null
        ? (
            Icons.cloud_done_rounded,
            NusaColors.success,
            'Tersimpan otomatis ${_time(lastSavedAt!)}.',
          )
        : (
            Icons.info_outline_rounded,
            NusaColors.primary,
            'Skor disimpan otomatis setelah Anda berhenti mengetik.',
          );
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 19, color: color),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: const TextStyle(fontSize: 10.5))),
          if (onRetry != null)
            TextButton(onPressed: onRetry, child: const Text('Coba Lagi')),
        ],
      ),
    );
  }

  static String _time(DateTime value) =>
      '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
}

class _CentralCorrectionCard extends StatelessWidget {
  const _CentralCorrectionCard({
    required this.item,
    required this.canCorrect,
    required this.onScoreChanged,
    super.key,
  });

  final AssessmentCorrectionItem item;
  final bool canCorrect;
  final ValueChanged<String>? onScoreChanged;

  @override
  Widget build(BuildContext context) {
    final statusTone = !item.answered
        ? 'netral'
        : item.corrected
        ? 'aktif'
        : 'peringatan';
    final statusLabel = !item.answered
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
                        maxLines: 2,
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
                color: item.answered ? Colors.white : NusaColors.background,
                borderRadius: BorderRadius.circular(11),
                border: Border.all(color: NusaColors.outline),
              ),
              child: SelectableText(
                item.answered ? item.answer : 'Belum dijawab',
                style: TextStyle(
                  color: item.answered
                      ? NusaColors.textPrimary
                      : NusaColors.textSecondary,
                  fontSize: 11,
                  height: 1.45,
                  fontStyle: item.answered
                      ? FontStyle.normal
                      : FontStyle.italic,
                ),
              ),
            ),
            if (item.answered && item.answerId != null) ...[
              const SizedBox(height: 11),
              if (canCorrect)
                TextFormField(
                  key: Key('central-correction-score-${item.answerId}'),
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
                )
              else
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: NusaColors.background,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: NusaColors.outline),
                  ),
                  child: Text(
                    'Skor: ${item.score == null ? 'Belum dikoreksi' : '${assessmentNumber(item.score)} / ${assessmentNumber(item.question.weight)}'}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

String _message(Object error) => error is AppException
    ? error.message
    : 'Koreksi uraian ujian terpusat belum dapat diproses.';
