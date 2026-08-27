import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_grades/domain/my_grades.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class MyGradesIdentityCard extends StatelessWidget {
  const MyGradesIdentityCard({
    required this.student,
    required this.schoolClass,
    super.key,
  });

  final MyGradesStudent student;
  final MyGradesClass? schoolClass;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.16),
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.14),
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Icon(Icons.school_rounded, color: NusaColors.accent),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                student.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                _studentIdentity(student),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.72),
                  fontSize: 10.5,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 9),
        Container(
          constraints: const BoxConstraints(maxWidth: 100),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            children: [
              Text(
                schoolClass?.name ?? 'Belum ada kelas',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontSize: 11,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (schoolClass?.attendanceNumber != null)
                Text(
                  'Absen ${schoolClass!.attendanceNumber}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 8.5,
                  ),
                ),
            ],
          ),
        ),
      ],
    ),
  );
}

class MyGradesFilters extends StatelessWidget {
  const MyGradesFilters({
    required this.page,
    required this.enabled,
    required this.onAcademicYearChanged,
    required this.onSemesterChanged,
    super.key,
  });

  final MyGradesPage page;
  final bool enabled;
  final ValueChanged<int?> onAcademicYearChanged;
  final ValueChanged<String> onSemesterChanged;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.tune_rounded, size: 19, color: NusaColors.primary),
              SizedBox(width: 7),
              Text(
                'Periode Nilai',
                style: TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) {
              final narrow = constraints.maxWidth < 390;
              final year = NusaDropdownField<int>(
                fieldKey: const Key('my-grades-academic-year-filter'),
                value: page.filter.academicYearId,
                enabled: enabled,
                decoration: const InputDecoration(
                  labelText: 'Tahun pelajaran',
                  prefixIcon: Icon(Icons.calendar_month_rounded),
                ),
                options: [
                  for (final item in page.academicYears)
                    NusaDropdownOption(value: item.id, label: item.label),
                ],
                onChanged: onAcademicYearChanged,
              );
              final semester = NusaDropdownField<String>(
                fieldKey: const Key('my-grades-semester-filter'),
                value: page.filter.semester,
                enabled: enabled,
                decoration: const InputDecoration(labelText: 'Semester'),
                options: const [
                  NusaDropdownOption(value: 'ganjil', label: 'Ganjil'),
                  NusaDropdownOption(value: 'genap', label: 'Genap'),
                ],
                onChanged: (value) {
                  if (value != null) onSemesterChanged(value);
                },
              );

              if (narrow) {
                return Column(
                  children: [year, const SizedBox(height: 9), semester],
                );
              }

              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(flex: 3, child: year),
                  const SizedBox(width: 9),
                  Expanded(flex: 2, child: semester),
                ],
              );
            },
          ),
        ],
      ),
    ),
  );
}

class MyGradesSummaryCard extends StatelessWidget {
  const MyGradesSummaryCard({required this.summary, super.key});

  final MyGradesSummary summary;

  @override
  Widget build(BuildContext context) => Card(
    key: const Key('my-grades-summary'),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 14),
      child: Row(
        children: [
          _SummaryItem(
            icon: Icons.menu_book_rounded,
            label: 'Dirilis',
            value: '${summary.subjectCount}',
            color: NusaColors.primary,
          ),
          _SummaryItem(
            icon: Icons.lock_open_rounded,
            label: 'Terbuka',
            value: '${summary.openCount}',
            color: NusaColors.success,
          ),
          _SummaryItem(
            icon: Icons.assignment_outlined,
            label: 'Perlu survei',
            value: '${summary.surveyRequiredCount}',
            color: const Color(0xFFB98200),
          ),
        ],
      ),
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Icon(icon, size: 19, color: color),
        const SizedBox(height: 4),
        Text(
          value,
          style: TextStyle(
            color: color,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
      ],
    ),
  );
}

class LockedGradeSubjectCard extends StatelessWidget {
  const LockedGradeSubjectCard({
    required this.subject,
    required this.onFillSurvey,
    super.key,
  });

  final MyGradesSubject subject;
  final VoidCallback onFillSurvey;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('my-grade-subject-${subject.assignmentId}'),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(18),
      side: BorderSide(color: NusaColors.accent.withValues(alpha: 0.65)),
    ),
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        children: [
          _SubjectHeader(subject: subject, locked: true),
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF9E5),
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.lock_outline_rounded,
                  size: 20,
                  color: Color(0xFFA67C00),
                ),
                SizedBox(width: 9),
                Expanded(
                  child: Text(
                    'Isi survei singkat untuk membuka rincian nilai mata '
                    'pelajaran ini.',
                    style: TextStyle(
                      color: Color(0xFF765B00),
                      fontSize: 10.5,
                      height: 1.4,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 11),
          SizedBox(
            width: double.infinity,
            child: FilledButton.tonalIcon(
              key: Key('fill-survey-${subject.assignmentId}'),
              onPressed: onFillSurvey,
              icon: const Icon(Icons.rate_review_rounded),
              label: const Text('Isi Survei dan Buka Nilai'),
            ),
          ),
        ],
      ),
    ),
  );
}

class OpenGradeSubjectCard extends StatelessWidget {
  const OpenGradeSubjectCard({
    required this.subject,
    this.initiallyExpanded = false,
    super.key,
  });

  final MyGradesSubject subject;
  final bool initiallyExpanded;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('my-grade-subject-${subject.assignmentId}'),
    clipBehavior: Clip.antiAlias,
    child: ExpansionTile(
      key: PageStorageKey<String>('my-grade-${subject.assignmentId}'),
      initiallyExpanded: initiallyExpanded,
      tilePadding: const EdgeInsets.fromLTRB(14, 10, 11, 10),
      childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
      shape: const Border(),
      collapsedShape: const Border(),
      iconColor: NusaColors.primary,
      collapsedIconColor: NusaColors.textSecondary,
      title: _SubjectHeader(subject: subject, locked: false),
      children: [
        const Divider(height: 1),
        const SizedBox(height: 13),
        if (!subject.usesPredicate && subject.categories.isNotEmpty) ...[
          _CategoryGrid(categories: subject.categories),
          const SizedBox(height: 14),
        ],
        if (subject.components.isEmpty)
          const _NoComponents()
        else
          for (var index = 0; index < subject.components.length; index++) ...[
            _GradeComponentRow(
              component: subject.components[index],
              usesPredicate: subject.usesPredicate,
            ),
            if (index != subject.components.length - 1)
              const Divider(height: 17),
          ],
        if (!subject.usesPredicate && !subject.complete) ...[
          const SizedBox(height: 13),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(11),
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Text(
              'Nilai akhir belum tersedia karena komponen berbobot belum '
              'lengkap atau skema bobot belum ditetapkan.',
              style: TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10.5,
                height: 1.4,
              ),
            ),
          ),
        ],
      ],
    ),
  );
}

class _SubjectHeader extends StatelessWidget {
  const _SubjectHeader({required this.subject, required this.locked});

  final MyGradesSubject subject;
  final bool locked;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: locked ? const Color(0xFFFFF9E5) : NusaColors.surfaceBlue,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(
          locked ? Icons.lock_rounded : Icons.auto_stories_rounded,
          color: locked ? const Color(0xFFB98200) : NusaColors.primary,
          size: 21,
        ),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              subject.subjectName,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: NusaColors.textPrimary,
                fontSize: 13,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              subject.teacherName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 9.5,
              ),
            ),
            if (subject.publishedAtLabel != null)
              Text(
                'Dirilis ${subject.publishedAtLabel}',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 8.5,
                ),
              ),
          ],
        ),
      ),
      const SizedBox(width: 8),
      _FinalGradeBadge(subject: subject, locked: locked),
    ],
  );
}

class _FinalGradeBadge extends StatelessWidget {
  const _FinalGradeBadge({required this.subject, required this.locked});

  final MyGradesSubject subject;
  final bool locked;

  @override
  Widget build(BuildContext context) {
    final success = subject.passed == true;
    final color = locked
        ? const Color(0xFFB98200)
        : success
        ? NusaColors.success
        : NusaColors.primary;
    return Container(
      constraints: const BoxConstraints(minWidth: 61, maxWidth: 76),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(11),
      ),
      child: Column(
        children: [
          Text(
            locked
                ? 'Status'
                : subject.usesPredicate
                ? 'Nilai'
                : subject.finalGradeLabel,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 8,
              fontWeight: FontWeight.w700,
            ),
          ),
          Text(
            locked
                ? 'Terkunci'
                : subject.usesPredicate
                ? 'Predikat'
                : formatGrade(subject.finalGrade),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: color,
              fontSize: locked || subject.usesPredicate ? 10 : 17,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (!locked && !subject.usesPredicate && subject.minimumGrade != null)
            Text(
              success ? 'Tuntas' : 'Belum tuntas',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: success ? NusaColors.success : const Color(0xFFB98200),
                fontSize: 7.5,
                fontWeight: FontWeight.w700,
              ),
            ),
        ],
      ),
    );
  }
}

class _CategoryGrid extends StatelessWidget {
  const _CategoryGrid({required this.categories});

  final List<MyGradeCategory> categories;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final width = constraints.maxWidth < 270
          ? constraints.maxWidth
          : (constraints.maxWidth - 8) / 2;
      return Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final category in categories)
            SizedBox(
              width: width,
              child: _CategoryItem(category: category),
            ),
        ],
      );
    },
  );
}

class _CategoryItem extends StatelessWidget {
  const _CategoryItem({required this.category});

  final MyGradeCategory category;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
    decoration: BoxDecoration(
      color: NusaColors.background,
      borderRadius: BorderRadius.circular(11),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                category.label,
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                '${category.filledCount}/${category.targetCount} nilai · ${category.weight}%',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 8.5,
                ),
              ),
            ],
          ),
        ),
        Text(
          formatGrade(category.average),
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 14,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    ),
  );
}

class _GradeComponentRow extends StatelessWidget {
  const _GradeComponentRow({
    required this.component,
    required this.usesPredicate,
  });

  final MyGradeComponent component;
  final bool usesPredicate;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
        decoration: BoxDecoration(
          color: NusaColors.surfaceBlue,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          component.typeLabel,
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 8.5,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      const SizedBox(width: 9),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              component.name,
              style: const TextStyle(
                color: NusaColors.textPrimary,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
            if (component.dateLabel != null)
              Text(
                component.dateLabel!,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 8.5,
                ),
              ),
            if (component.notes?.trim().isNotEmpty == true)
              Text(
                component.notes!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9,
                  fontStyle: FontStyle.italic,
                ),
              ),
          ],
        ),
      ),
      const SizedBox(width: 8),
      Text(
        usesPredicate
            ? component.predicateLabel ?? 'Belum dinilai'
            : formatGrade(component.score),
        textAlign: TextAlign.right,
        style: const TextStyle(
          color: NusaColors.primary,
          fontSize: 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    ],
  );
}

class _NoComponents extends StatelessWidget {
  const _NoComponents();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.symmetric(vertical: 10),
    child: Text(
      'Belum ada komponen nilai yang dapat ditampilkan.',
      textAlign: TextAlign.center,
      style: TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
    ),
  );
}

String _studentIdentity(MyGradesStudent student) {
  if (student.nisn?.isNotEmpty == true) return 'NISN ${student.nisn}';
  if (student.nis?.isNotEmpty == true) return 'NIS ${student.nis}';
  return 'Identitas belum tersedia';
}

String formatGrade(double? value) {
  if (value == null) return '–';
  return value.toStringAsFixed(2).replaceFirst(RegExp(r'\.?0+$'), '');
}
