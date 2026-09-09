import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/bk_grade_assignment/application/bk_grade_assignment_controller.dart';
import 'package:nusa/features/bk_grade_assignment/domain/bk_grade_assignment.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class BkGradeAssignmentView extends ConsumerStatefulWidget {
  const BkGradeAssignmentView({super.key});

  @override
  ConsumerState<BkGradeAssignmentView> createState() =>
      _BkGradeAssignmentViewState();
}

class _BkGradeAssignmentViewState extends ConsumerState<BkGradeAssignmentView> {
  int? _teacherId;
  final Set<int> _grades = {};
  bool _submitting = false;
  int? _endingId;

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(bkGradeAssignmentControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Penugasan Tingkat Guru BK'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) =>
              _ErrorState(message: _message(error), onRetry: _refresh),
          data: (page) => RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                const _InformationCard(),
                const SizedBox(height: 12),
                _SummaryCard(summary: page.summary),
                const SizedBox(height: 12),
                NusaDropdownField<int>(
                  fieldKey: const Key('bk-grade-assignment-year'),
                  value: page.selectedAcademicYear?.id,
                  options: [
                    for (final year in page.academicYears)
                      NusaDropdownOption(
                        value: year.id,
                        label: year.active ? '${year.name} · Aktif' : year.name,
                      ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Tahun Pelajaran',
                    prefixIcon: Icon(Icons.calendar_month_rounded),
                  ),
                  onChanged: (value) {
                    if (value == null) return;
                    setState(() {
                      _teacherId = null;
                      _grades.clear();
                    });
                    ref
                        .read(bkGradeAssignmentControllerProvider.notifier)
                        .selectAcademicYear(value);
                  },
                ),
                if (!page.summary.distributionActive) ...[
                  const SizedBox(height: 12),
                  const _InactiveDistributionNotice(),
                ],
                if (page.access.canManage) ...[
                  const SizedBox(height: 16),
                  _AssignmentForm(
                    page: page,
                    teacherId: _teacherId,
                    grades: _grades,
                    submitting: _submitting,
                    onTeacherChanged: (value) =>
                        setState(() => _teacherId = value),
                    onGradeChanged: (grade, selected) => setState(() {
                      selected ? _grades.add(grade) : _grades.remove(grade);
                    }),
                    onSubmit: () => _submit(page),
                  ),
                ],
                const SizedBox(height: 20),
                const _SectionHeading(
                  title: 'Pembagian Aktif',
                  subtitle:
                      'Satu tingkat dapat ditangani lebih dari satu Guru BK.',
                ),
                const SizedBox(height: 10),
                for (final level in page.levels) ...[
                  _LevelCard(
                    level: level,
                    canManage: page.access.canManage,
                    endingId: _endingId,
                    onEnd: _confirmEnd,
                  ),
                  const SizedBox(height: 10),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _refresh() =>
      ref.read(bkGradeAssignmentControllerProvider.notifier).refresh();

  Future<void> _submit(BkGradeAssignmentPage page) async {
    if (page.selectedAcademicYear == null) {
      _snack('Tahun pelajaran belum tersedia.');
      return;
    }
    if (_teacherId == null) {
      _snack('Pilih Guru BK terlebih dahulu.');
      return;
    }
    if (_grades.isEmpty) {
      _snack('Pilih minimal satu tingkat.');
      return;
    }

    setState(() => _submitting = true);
    try {
      final result = await ref
          .read(bkGradeAssignmentControllerProvider.notifier)
          .create(
            BkGradeAssignmentPayload(
              academicYearId: page.selectedAcademicYear!.id,
              teacherId: _teacherId!,
              grades: _grades.toList()..sort(),
            ),
          );
      if (!mounted) return;
      setState(() {
        _teacherId = null;
        _grades.clear();
      });
      _snack(result.message);
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _confirmEnd(BkGradeAssignment assignment) async {
    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Akhiri penugasan?'),
        content: Text(
          '${assignment.teacher.name} tidak lagi menangani Tingkat ${assignment.grade}. Riwayat penugasan tetap tersimpan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('bk-grade-assignment-confirm-end'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Akhiri'),
          ),
        ],
      ),
    );
    if (accepted != true || !mounted) return;

    setState(() => _endingId = assignment.id);
    try {
      final result = await ref
          .read(bkGradeAssignmentControllerProvider.notifier)
          .end(assignment.id);
      if (mounted) _snack(result.message);
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _endingId = null);
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _InformationCard extends StatelessWidget {
  const _InformationCard();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _RoundIcon(icon: Icons.psychology_alt_rounded),
        SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Pembagian tanggung jawab Guru BK',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: 15,
                ),
              ),
              SizedBox(height: 5),
              Text(
                'Semua Guru BK tetap dapat melihat laporan. Tindakan dan notifikasi utama mengikuti tingkat yang ditugaskan.',
                style: TextStyle(
                  color: Colors.white70,
                  height: 1.35,
                  fontSize: 11.5,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _RoundIcon extends StatelessWidget {
  const _RoundIcon({required this.icon});
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    width: 42,
    height: 42,
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .12),
      borderRadius: BorderRadius.circular(13),
    ),
    child: Icon(icon, color: NusaColors.accent),
  );
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});
  final BkGradeAssignmentSummary summary;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 13),
      child: Row(
        children: [
          _SummaryItem(value: summary.assignmentCount, label: 'Penugasan'),
          _SummaryItem(value: summary.teacherCount, label: 'Guru BK'),
          _SummaryItem(
            value: summary.filledLevelCount,
            label: 'Tingkat Terisi',
            accent: true,
          ),
        ],
      ),
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({
    required this.value,
    required this.label,
    this.accent = false,
  });
  final int value;
  final String label;
  final bool accent;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: accent ? NusaColors.primaryLight : NusaColors.primary,
            fontSize: 20,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10),
        ),
      ],
    ),
  );
}

class _InactiveDistributionNotice extends StatelessWidget {
  const _InactiveDistributionNotice();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF9DF),
      borderRadius: BorderRadius.circular(15),
      border: const Border.fromBorderSide(BorderSide(color: Color(0xFFF5D86A))),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.info_outline_rounded, color: Color(0xFF9A7100)),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Pembagian belum diaktifkan. Seluruh Guru BK masih dapat menangani laporan sampai penugasan pertama disimpan.',
            style: TextStyle(
              color: Color(0xFF725600),
              fontSize: 11.5,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );
}

class _AssignmentForm extends StatelessWidget {
  const _AssignmentForm({
    required this.page,
    required this.teacherId,
    required this.grades,
    required this.submitting,
    required this.onTeacherChanged,
    required this.onGradeChanged,
    required this.onSubmit,
  });

  final BkGradeAssignmentPage page;
  final int? teacherId;
  final Set<int> grades;
  final bool submitting;
  final ValueChanged<int?> onTeacherChanged;
  final void Function(int grade, bool selected) onGradeChanged;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionHeading(
            title: 'Tambah Penugasan',
            subtitle: 'Seorang Guru BK dapat menangani beberapa tingkat.',
          ),
          const SizedBox(height: 13),
          if (page.teachers.isEmpty)
            const _EmptyTeacherNotice()
          else ...[
            NusaDropdownField<int>(
              fieldKey: const Key('bk-grade-assignment-teacher'),
              value: teacherId,
              enabled: !submitting,
              options: [
                for (final teacher in page.teachers)
                  NusaDropdownOption(
                    value: teacher.id,
                    label: teacher.nip?.isNotEmpty == true
                        ? '${teacher.name} · ${teacher.nip}'
                        : teacher.name,
                  ),
              ],
              decoration: const InputDecoration(
                labelText: 'Guru BK',
                hintText: 'Pilih Guru BK',
                prefixIcon: Icon(Icons.person_search_rounded),
              ),
              onChanged: onTeacherChanged,
            ),
            const SizedBox(height: 13),
            const Text(
              'Tingkat yang ditangani',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 7),
            Wrap(
              spacing: 8,
              runSpacing: 7,
              children: [
                for (final level in page.levels)
                  FilterChip(
                    key: Key('bk-grade-assignment-level-${level.grade}'),
                    selected: grades.contains(level.grade),
                    onSelected: submitting
                        ? null
                        : (selected) => onGradeChanged(level.grade, selected),
                    avatar: Icon(
                      grades.contains(level.grade)
                          ? Icons.check_rounded
                          : Icons.school_outlined,
                      size: 17,
                    ),
                    label: Text(level.label),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            NusaPrimaryButton(
              key: const Key('bk-grade-assignment-submit'),
              label: 'Simpan Penugasan',
              loading: submitting,
              onPressed: submitting ? null : onSubmit,
            ),
          ],
        ],
      ),
    ),
  );
}

class _EmptyTeacherNotice extends StatelessWidget {
  const _EmptyTeacherNotice();

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
    ),
    child: const Text(
      'Belum ada pegawai dengan akun aktif dan role Guru BK.',
      style: TextStyle(color: NusaColors.textSecondary, fontSize: 12),
    ),
  );
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.subtitle});
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 2),
      Text(
        subtitle,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

class _LevelCard extends StatelessWidget {
  const _LevelCard({
    required this.level,
    required this.canManage,
    required this.endingId,
    required this.onEnd,
  });
  final BkGradeLevel level;
  final bool canManage;
  final int? endingId;
  final ValueChanged<BkGradeAssignment> onEnd;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 39,
                height: 39,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '${level.grade}',
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  level.label,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              _CountBadge(count: level.assignments.length),
            ],
          ),
          const SizedBox(height: 11),
          if (level.assignments.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text(
                'Belum ada Guru BK yang ditugaskan.',
                style: TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11.5,
                ),
              ),
            )
          else
            for (var index = 0; index < level.assignments.length; index++) ...[
              if (index > 0) const Divider(height: 18),
              _TeacherRow(
                assignment: level.assignments[index],
                canManage: canManage,
                ending: endingId == level.assignments[index].id,
                onEnd: onEnd,
              ),
            ],
        ],
      ),
    ),
  );
}

class _CountBadge extends StatelessWidget {
  const _CountBadge({required this.count});
  final int count;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: count == 0 ? NusaColors.background : NusaColors.successSurface,
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      '$count guru',
      style: TextStyle(
        color: count == 0 ? NusaColors.textSecondary : NusaColors.success,
        fontSize: 10,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _TeacherRow extends StatelessWidget {
  const _TeacherRow({
    required this.assignment,
    required this.canManage,
    required this.ending,
    required this.onEnd,
  });
  final BkGradeAssignment assignment;
  final bool canManage;
  final bool ending;
  final ValueChanged<BkGradeAssignment> onEnd;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      const CircleAvatar(
        radius: 18,
        backgroundColor: NusaColors.surfaceBlue,
        child: Icon(Icons.person_rounded, color: NusaColors.primary, size: 20),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              assignment.teacher.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            Text(
              assignment.teacher.nip?.isNotEmpty == true
                  ? 'NIP ${assignment.teacher.nip}'
                  : (assignment.teacher.position ?? 'Guru BK'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
              ),
            ),
          ],
        ),
      ),
      if (canManage)
        ending
            ? const SizedBox.square(
                dimension: 28,
                child: Padding(
                  padding: EdgeInsets.all(5),
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              )
            : IconButton(
                key: Key('bk-grade-assignment-end-${assignment.id}'),
                tooltip: 'Akhiri penugasan',
                visualDensity: VisualDensity.compact,
                onPressed: () => onEnd(assignment),
                icon: const Icon(Icons.remove_circle_outline_rounded),
              ),
    ],
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 54,
            color: NusaColors.textSecondary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Penugasan tingkat Guru BK belum dapat dimuat.',
};
