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

  @override
  Widget build(BuildContext context) {
    final result = ref.watch(gradeEntryControllerProvider);
    final current = result.value;
    if (current != null) _synchronizeDraft(current);

    return Scaffold(
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
              onSave: _mutating ? null : () => _save(current!),
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
    );
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
                  _markDirty();
                },
                onPredicateChanged: (value) {
                  setState(() {
                    _predicates[student.studentId] = value;
                    _dirty = true;
                  });
                },
                onEditNotes: () => _editNotes(student),
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
  }

  void _markDirty() {
    if (_dirty || !mounted) return;
    setState(() => _dirty = true);
  }

  Future<void> _refresh() =>
      _changeFilter(ref.read(gradeEntryControllerProvider.notifier).refresh);

  Future<void> _changeFilter(Future<void> Function() operation) async {
    if (_dirty && !await _confirmDiscard()) return;
    if (mounted) {
      setState(() {
        _dirty = false;
        _loadedComponentId = null;
      });
    }
    await operation();
  }

  Future<bool> _confirmDiscard() async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.edit_note_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Buang perubahan?'),
            content: const Text(
              'Ada nilai atau catatan yang belum disimpan. Perubahan tersebut '
              'akan hilang jika Anda melanjutkan.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Tetap di Sini'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Buang'),
              ),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _save(GradeEntryPage page) async {
    final component = page.selectedComponent;
    if (component == null) return;
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
        _showErrorMessage(
          'Nilai ${student.name} harus berupa angka antara 0 sampai 100.',
        );
        return;
      }
      scores[student.studentId] = page.usesPredicate ? null : score;
    }

    await _runMutation(() async {
      final message = await ref
          .read(gradeEntryActionsProvider)
          .save(
            GradeEntryFormValue(
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
                  student.studentId:
                      (_notes[student.studentId] ?? '').trim().isEmpty
                      ? null
                      : _notes[student.studentId]!.trim(),
              },
            ),
          );
      _loadedComponentId = null;
      _dirty = false;
      await ref.read(gradeEntryControllerProvider.notifier).refresh();
      return message;
    });
  }

  Future<void> _changePublication(
    GradeEntryPage page, {
    required bool publish,
  }) async {
    final assignmentId = page.filter.assignmentId;
    if (assignmentId == null) return;
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

  Future<void> _editNotes(GradeEntryStudent student) async {
    final controller = TextEditingController(text: _notes[student.studentId]);
    final result = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          18,
          20,
          20 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Catatan ${student.name}',
              style: const TextStyle(
                color: NusaColors.textPrimary,
                fontSize: 17,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              key: const Key('grade-notes-input'),
              controller: controller,
              autofocus: true,
              minLines: 3,
              maxLines: 5,
              maxLength: 255,
              decoration: const InputDecoration(
                hintText: 'Catatan opsional untuk siswa',
              ),
            ),
            const SizedBox(height: 10),
            FilledButton(
              key: const Key('save-grade-notes'),
              onPressed: () => Navigator.pop(context, controller.text),
              child: const Text('Simpan Catatan'),
            ),
          ],
        ),
      ),
    );
    controller.dispose();
    if (result == null || !mounted) return;
    setState(() {
      _notes[student.studentId] = result;
      _dirty = true;
    });
  }

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
}

class _SaveBar extends StatelessWidget {
  const _SaveBar({required this.dirty, required this.loading, this.onSave});

  final bool dirty;
  final bool loading;
  final VoidCallback? onSave;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    elevation: 12,
    child: SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
        child: FilledButton.icon(
          key: const Key('save-grades'),
          onPressed: onSave,
          icon: loading
              ? const SizedBox.square(
                  dimension: 17,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : Icon(dirty ? Icons.save_rounded : Icons.check_circle_rounded),
          label: Text(
            loading
                ? 'Menyimpan...'
                : dirty
                ? 'Simpan Perubahan'
                : 'Simpan Nilai',
          ),
        ),
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
