import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_entry/application/grade_entry_controller.dart';
import 'package:nusa/features/grade_entry/domain/grade_entry.dart';
import 'package:nusa/features/grade_entry/presentation/widgets/grade_entry_components.dart';

class GradeEntryView extends ConsumerStatefulWidget {
  const GradeEntryView({super.key});

  @override
  ConsumerState<GradeEntryView> createState() => _GradeEntryViewState();
}

class _GradeEntryViewState extends ConsumerState<GradeEntryView> {
  final Map<int, String> _scores = {};
  final Map<int, String?> _predicates = {};
  final Map<int, String> _notes = {};
  int? _loadedComponentId;
  bool _dirty = false;
  bool _mutating = false;
  bool _autoSaving = false;
  bool _autoSaveRequested = false;
  int _draftRevision = 0;
  Timer? _autoSaveDebounce;
  Future<bool>? _activeAutoSave;
  String? _autoSaveError;
  DateTime? _lastAutoSavedAt;
  String? _lastSaveMessage;

  @override
  void dispose() {
    _autoSaveDebounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final result = ref.watch(gradeEntryControllerProvider);
    final current = result.value;
    if (current != null) _synchronizeDraft(current);

    return PopScope(
      canPop: !_dirty && !_autoSaving,
      onPopInvokedWithResult: _handlePop,
      child: Scaffold(
        backgroundColor: NusaColors.background,
        appBar: AppBar(
          title: const Text('Input Nilai'),
          actions: [
            IconButton(
              tooltip: 'Perbarui',
              onPressed: result.isLoading || _mutating ? null : _refresh,
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
        bottomNavigationBar:
            current?.selectedComponent != null && current?.canInput == true
            ? _SaveBar(
                dirty: _dirty,
                loading: _mutating,
                autoSaving: _autoSaving,
                autoSaveError: _autoSaveError,
                lastAutoSavedAt: _lastAutoSavedAt,
                onSave: _mutating || _autoSaving ? null : () => _save(current!),
              )
            : null,
        body: SafeArea(
          top: false,
          child: result.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, stackTrace) => _GradeEntryError(
              message: _errorMessage(error),
              onRetry: ref.read(gradeEntryControllerProvider.notifier).refresh,
            ),
            data: (page) => _buildContent(page),
          ),
        ),
      ),
    );
  }

  Future<void> _handlePop(bool didPop, Object? result) async {
    if (didPop || !mounted) return;
    final page = ref.read(gradeEntryControllerProvider).value;
    var canLeave = page == null;
    if (page != null) {
      canLeave = await _flushAutoSave(page, showError: true);
    }
    if (!canLeave && mounted) {
      canLeave =
          await showDialog<bool>(
            context: context,
            builder: (context) => AlertDialog(
              icon: const Icon(
                Icons.cloud_off_rounded,
                color: NusaColors.primary,
              ),
              title: const Text('Perubahan belum tersimpan'),
              content: const Text(
                'Autosave belum berhasil. Tetap di halaman ini untuk mencoba '
                'lagi agar perubahan nilai tidak hilang.',
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

  Widget _buildContent(GradeEntryPage page) {
    if (page.assignments.isEmpty) {
      return RefreshIndicator(
        onRefresh: ref.read(gradeEntryControllerProvider.notifier).refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(38),
          children: const [
            Icon(
              Icons.co_present_outlined,
              size: 52,
              color: NusaColors.primary,
            ),
            SizedBox(height: 14),
            Text(
              'Belum ada penugasan guru mata pelajaran aktif dalam cakupan akun ini.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _refresh,
      child: ListView(
        key: const PageStorageKey<String>('grade-entry-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          GradeEntryFilters(
            page: page,
            enabled: !_mutating,
            onAssignmentChanged: (value) => _changeFilter(
              () => ref
                  .read(gradeEntryControllerProvider.notifier)
                  .selectAssignment(value),
            ),
            onSemesterChanged: (value) => _changeFilter(
              () => ref
                  .read(gradeEntryControllerProvider.notifier)
                  .selectSemester(value),
            ),
            onComponentChanged: (value) => _changeFilter(
              () => ref
                  .read(gradeEntryControllerProvider.notifier)
                  .selectComponent(value),
            ),
          ),
          const SizedBox(height: 10),
          GradeEntrySummaryCard(summary: page.summary),
          const SizedBox(height: 10),
          GradePublicationCard(
            publication: page.publication,
            enabled: !_mutating,
            dirty: _dirty,
            canPublishOverride: _draftComplete(page),
            onPublish: () => _changePublication(page, publish: true),
            onUnpublish: () => _changePublication(page, publish: false),
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Daftar Siswa',
                  style: TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (page.selectedComponent != null)
                Flexible(
                  child: Text(
                    page.selectedComponent!.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.end,
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 9),
          if (page.selectedComponent == null)
            const _InlineEmpty(
              message:
                  'Belum ada komponen nilai aktif untuk semester yang dipilih.',
            )
          else if (page.students.isEmpty)
            const _InlineEmpty(
              message: 'Belum ada siswa aktif pada kelas penugasan ini.',
            )
          else
            for (final student in page.students) ...[
              GradeStudentCard(
                student: student,
                usesPredicate: page.usesPredicate,
                predicateOptions: page.predicateOptions,
                scoreValue: _scores[student.studentId] ?? '',
                predicateValue: _predicates[student.studentId],
                notes: _notes[student.studentId] ?? '',
                enabled: page.canInput && !_mutating,
                onScoreChanged: (value) {
                  _scores[student.studentId] = value;
                  _markDirtyAndSchedule(page);
                },
                onPredicateChanged: (value) {
                  _predicates[student.studentId] = value;
                  _markDirtyAndSchedule(page);
                },
                onEditNotes: () => _editNotes(page, student),
              ),
              const SizedBox(height: 9),
            ],
        ],
      ),
    );
  }

  void _synchronizeDraft(GradeEntryPage page) {
    if (_loadedComponentId == page.filter.componentId) return;
    _loadedComponentId = page.filter.componentId;
    _scores
      ..clear()
      ..addEntries(
        page.students.map(
          (student) => MapEntry(student.studentId, _scoreText(student.score)),
        ),
      );
    _predicates
      ..clear()
      ..addEntries(
        page.students.map(
          (student) => MapEntry(student.studentId, student.predicate),
        ),
      );
    _notes
      ..clear()
      ..addEntries(
        page.students.map(
          (student) => MapEntry(student.studentId, student.notes ?? ''),
        ),
      );
    _dirty = false;
    _autoSaveError = null;
  }

  void _markDirtyAndSchedule(GradeEntryPage page) {
    if (!mounted) return;
    _autoSaveDebounce?.cancel();
    setState(() {
      _dirty = true;
      _draftRevision++;
      _autoSaveError = null;
    });
    _autoSaveDebounce = Timer(
      const Duration(milliseconds: 900),
      () => unawaited(_runAutoSave(page)),
    );
  }

  Future<void> _refresh() =>
      _changeFilter(ref.read(gradeEntryControllerProvider.notifier).refresh);

  Future<void> _changeFilter(Future<void> Function() operation) async {
    final current = ref.read(gradeEntryControllerProvider).value;
    if (current != null && (_dirty || _autoSaving)) {
      final saved = await _flushAutoSave(current, showError: true);
      if (!saved) return;
    }
    if (mounted) {
      setState(() {
        _dirty = false;
        _loadedComponentId = null;
      });
    }
    await operation();
  }

  Future<void> _save(GradeEntryPage page) async {
    setState(() => _mutating = true);
    try {
      final saved = await _flushAutoSave(page, showError: true);
      if (!saved || !mounted) return;
      _loadedComponentId = null;
      await ref.read(gradeEntryControllerProvider.notifier).refresh();
      if (mounted) {
        _showSuccessMessage(
          _lastSaveMessage ?? 'Nilai berhasil disimpan sebagai draf.',
        );
      }
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _changePublication(
    GradeEntryPage page, {
    required bool publish,
  }) async {
    final assignmentId = page.filter.assignmentId;
    if (assignmentId == null) return;
    if (_dirty || _autoSaving) {
      final saved = await _flushAutoSave(page, showError: true);
      if (!saved || !mounted) return;
    }
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: Icon(
              publish ? Icons.publish_rounded : Icons.visibility_off_rounded,
              color: NusaColors.primary,
            ),
            title: Text(publish ? 'Publikasikan nilai?' : 'Jadikan draf?'),
            content: Text(
              publish
                  ? 'Nilai semester ${page.filter.semester} akan dapat dilihat '
                        'oleh siswa dan notifikasi akan dikirim.'
                  : 'Nilai tidak lagi dapat dilihat siswa sampai dipublikasikan kembali.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: Key(
                  publish
                      ? 'confirm-publish-grades'
                      : 'confirm-unpublish-grades',
                ),
                onPressed: () => Navigator.pop(context, true),
                child: Text(publish ? 'Publikasikan' : 'Jadikan Draf'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(() async {
      final actions = ref.read(gradeEntryActionsProvider);
      final message = publish
          ? await actions.publish(
              assignmentId: assignmentId,
              semester: page.filter.semester,
            )
          : await actions.unpublish(
              assignmentId: assignmentId,
              semester: page.filter.semester,
            );
      _loadedComponentId = null;
      await ref.read(gradeEntryControllerProvider.notifier).refresh();
      return message;
    });
  }

  Future<void> _editNotes(
    GradeEntryPage page,
    GradeEntryStudent student,
  ) async {
    final result = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _GradeNotesSheet(
        studentName: student.name,
        initialValue: _notes[student.studentId] ?? '',
      ),
    );
    if (result == null || !mounted) return;
    _notes[student.studentId] = result;
    _markDirtyAndSchedule(page);
  }

  Future<bool> _flushAutoSave(
    GradeEntryPage page, {
    required bool showError,
  }) async {
    _autoSaveDebounce?.cancel();
    final active = _activeAutoSave;
    if (active != null) await active;
    if (!_dirty) return true;
    return _runAutoSave(page, showError: showError);
  }

  Future<bool> _runAutoSave(GradeEntryPage page, {bool showError = false}) {
    final active = _activeAutoSave;
    if (active != null) {
      _autoSaveRequested = true;
      return active.then((_) {
        if (!_dirty) return true;
        final current = ref.read(gradeEntryControllerProvider).value;
        return _runAutoSave(current ?? page, showError: showError);
      });
    }

    late Future<bool> operation;
    operation = _executeAutoSave(page, showError: showError).whenComplete(() {
      if (identical(_activeAutoSave, operation)) _activeAutoSave = null;
      if (_autoSaveRequested && mounted) {
        _autoSaveRequested = false;
        final current = ref.read(gradeEntryControllerProvider).value;
        if (current != null && _dirty) {
          _autoSaveDebounce?.cancel();
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
    GradeEntryPage page, {
    required bool showError,
  }) async {
    if (_loadedComponentId != page.filter.componentId || !page.canInput) {
      return false;
    }
    late GradeEntryFormValue form;
    try {
      form = _formValue(page);
    } catch (error) {
      final message = _errorMessage(error);
      if (mounted) {
        setState(() => _autoSaveError = message);
        if (showError) _showErrorMessage(message);
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
      final message = await ref.read(gradeEntryActionsProvider).save(form);
      _lastSaveMessage = message;
      if (mounted) {
        setState(() {
          if (revision == _draftRevision) _dirty = false;
          _lastAutoSavedAt = DateTime.now();
          _autoSaveError = null;
        });
      }
      return true;
    } catch (error) {
      final message = _errorMessage(error);
      if (mounted) {
        setState(() => _autoSaveError = message);
        if (showError) _showErrorMessage(message);
      }
      return false;
    } finally {
      if (mounted) setState(() => _autoSaving = false);
    }
  }

  GradeEntryFormValue _formValue(GradeEntryPage page) {
    final component = page.selectedComponent;
    if (component == null) {
      throw const ValidationException('Komponen nilai belum dipilih.');
    }
    final scores = <int, double?>{};
    for (final student in page.students) {
      final raw = (_scores[student.studentId] ?? '').trim().replaceAll(
        ',',
        '.',
      );
      final score = raw.isEmpty ? null : double.tryParse(raw);
      if (!page.usesPredicate &&
          raw.isNotEmpty &&
          (score == null || score < 0 || score > 100)) {
        throw ValidationException(
          'Nilai ${student.name} harus berupa angka antara 0 sampai 100.',
        );
      }
      scores[student.studentId] = page.usesPredicate ? null : score;
    }
    return GradeEntryFormValue(
      componentId: component.id,
      scores: scores,
      predicates: {
        for (final student in page.students)
          student.studentId: page.usesPredicate
              ? _predicates[student.studentId]
              : null,
      },
      notes: {
        for (final student in page.students)
          student.studentId: (_notes[student.studentId] ?? '').trim().isEmpty
              ? null
              : _notes[student.studentId]!.trim(),
      },
    );
  }

  bool _draftComplete(GradeEntryPage page) =>
      page.students.isNotEmpty &&
      page.students.every((student) {
        if (page.usesPredicate) {
          return (_predicates[student.studentId] ?? '').trim().isNotEmpty;
        }
        final value = double.tryParse(
          (_scores[student.studentId] ?? '').trim().replaceAll(',', '.'),
        );
        return value != null && value >= 0 && value <= 100;
      });

  Future<void> _runMutation(Future<String> Function() operation) async {
    setState(() => _mutating = true);
    try {
      final message = await operation();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(message)));
    } catch (error) {
      if (mounted) _showErrorMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _showErrorMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  void _showSuccessMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _SaveBar extends StatelessWidget {
  const _SaveBar({
    required this.dirty,
    required this.loading,
    required this.autoSaving,
    required this.autoSaveError,
    required this.lastAutoSavedAt,
    this.onSave,
  });

  final bool dirty;
  final bool loading;
  final bool autoSaving;
  final String? autoSaveError;
  final DateTime? lastAutoSavedAt;
  final VoidCallback? onSave;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    elevation: 12,
    child: SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              key: const Key('grade-autosave-status'),
              children: [
                if (autoSaving)
                  const SizedBox.square(
                    dimension: 14,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                else
                  Icon(
                    autoSaveError != null
                        ? Icons.error_outline_rounded
                        : dirty
                        ? Icons.schedule_rounded
                        : Icons.cloud_done_rounded,
                    size: 16,
                    color: autoSaveError != null
                        ? Colors.red
                        : NusaColors.primary,
                  ),
                const SizedBox(width: 7),
                Expanded(
                  child: Text(
                    autoSaving
                        ? 'Menyimpan otomatis...'
                        : autoSaveError != null
                        ? 'Autosave gagal: $autoSaveError'
                        : dirty
                        ? 'Perubahan akan disimpan otomatis'
                        : lastAutoSavedAt != null
                        ? 'Tersimpan otomatis'
                        : 'Autosave aktif',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: autoSaveError != null
                          ? Colors.red
                          : NusaColors.textSecondary,
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 7),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-grades'),
                onPressed: onSave,
                icon: loading || autoSaving
                    ? const SizedBox.square(
                        dimension: 17,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Icon(
                        dirty ? Icons.save_rounded : Icons.check_circle_rounded,
                      ),
                label: Text(
                  loading || autoSaving
                      ? 'Menyimpan...'
                      : dirty
                      ? 'Simpan Sekarang'
                      : 'Nilai Tersimpan',
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _GradeNotesSheet extends StatefulWidget {
  const _GradeNotesSheet({
    required this.studentName,
    required this.initialValue,
  });

  final String studentName;
  final String initialValue;

  @override
  State<_GradeNotesSheet> createState() => _GradeNotesSheetState();
}

class _GradeNotesSheetState extends State<_GradeNotesSheet> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initialValue,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      18,
      20,
      20 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Catatan ${widget.studentName}',
            style: const TextStyle(
              color: NusaColors.textPrimary,
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const Key('grade-notes-input'),
            controller: _controller,
            autofocus: true,
            minLines: 3,
            maxLines: 5,
            maxLength: 255,
            decoration: const InputDecoration(
              hintText: 'Catatan opsional untuk siswa',
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              key: const Key('save-grade-notes'),
              onPressed: () => Navigator.pop(context, _controller.text),
              child: const Text('Simpan Catatan'),
            ),
          ),
        ],
      ),
    ),
  );
}

class _InlineEmpty extends StatelessWidget {
  const _InlineEmpty({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(24),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(16),
    ),
    child: Column(
      children: [
        const Icon(Icons.inbox_outlined, size: 38, color: NusaColors.primary),
        const SizedBox(height: 9),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(color: NusaColors.textSecondary),
        ),
      ],
    ),
  );
}

class _GradeEntryError extends StatelessWidget {
  const _GradeEntryError({required this.message, required this.onRetry});

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

String _scoreText(double? value) {
  if (value == null) return '';
  final text = value.toStringAsFixed(2);
  return text.replaceFirst(RegExp(r'\.?0+$'), '');
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Input nilai belum dapat diproses.';
}
