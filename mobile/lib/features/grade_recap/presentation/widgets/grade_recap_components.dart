import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_recap/domain/grade_recap.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GradeRecapFilters extends StatelessWidget {
  const GradeRecapFilters({
    required this.page,
    required this.enabled,
    required this.onAssignmentChanged,
    required this.onSemesterChanged,
    super.key,
  });

  final GradeRecapPage page;
  final bool enabled;
  final ValueChanged<int?> onAssignmentChanged;
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
                'Filter Rekap',
                style: TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          NusaDropdownField<int>(
            fieldKey: const Key('grade-recap-assignment-filter'),
            value: page.filter.assignmentId,
            enabled: enabled,
            menuMaxHeight: 330,
            decoration: const InputDecoration(
              labelText: 'Guru · kelas · mata pelajaran',
              prefixIcon: Icon(Icons.co_present_rounded),
            ),
            options: [
              for (final assignment in page.assignments)
                NusaDropdownOption(
                  value: assignment.id,
                  label: assignment.label,
                ),
            ],
            onChanged: onAssignmentChanged,
          ),
          const SizedBox(height: 9),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 240),
            child: NusaDropdownField<String>(
              fieldKey: const Key('grade-recap-semester-filter'),
              value: page.filter.semester,
              enabled: enabled,
              decoration: const InputDecoration(
                labelText: 'Semester',
                prefixIcon: Icon(Icons.calendar_month_rounded),
              ),
              options: const [
                NusaDropdownOption(value: 'ganjil', label: 'Ganjil'),
                NusaDropdownOption(value: 'genap', label: 'Genap'),
              ],
              onChanged: (value) {
                if (value != null) onSemesterChanged(value);
              },
            ),
          ),
        ],
      ),
    ),
  );
}

class GradeRecapSummaryCard extends StatelessWidget {
  const GradeRecapSummaryCard({required this.summary, super.key});

  final GradeRecapSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('grade-recap-summary'),
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 15),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.16),
          blurRadius: 16,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Row(
      children: [
        _SummaryItem(label: 'Siswa', value: '${summary.studentCount}'),
        _SummaryItem(label: 'Lengkap', value: '${summary.completeCount}'),
        _SummaryItem(label: 'Belum', value: '${summary.incompleteCount}'),
        _SummaryItem(
          label: 'Rata-rata',
          value: formatGrade(summary.finalAverage),
        ),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 9.5,
          ),
        ),
      ],
    ),
  );
}

class GradeRecapAssignmentCard extends StatelessWidget {
  const GradeRecapAssignmentCard({required this.assignment, super.key});

  final GradeRecapAssignment assignment;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(
              Icons.auto_stories_rounded,
              color: NusaColors.primary,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  assignment.subjectName,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${assignment.className} · ${assignment.academicYearName}',
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  assignment.employeeName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
              ],
            ),
          ),
          if (assignment.activeAcademicYear)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
              decoration: BoxDecoration(
                color: NusaColors.successSurface,
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Text(
                'Aktif',
                style: TextStyle(
                  color: NusaColors.success,
                  fontSize: 9.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
        ],
      ),
    ),
  );
}

class GradeRecapWarning extends StatelessWidget {
  const GradeRecapWarning({required this.message, super.key});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF9E5),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.accent.withValues(alpha: 0.55)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.info_outline_rounded,
          size: 20,
          color: Color(0xFFA67C00),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(
              color: Color(0xFF765B00),
              fontSize: 11,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );
}

class GradeRecapSchemeGrid extends StatelessWidget {
  const GradeRecapSchemeGrid({required this.categories, super.key});

  final List<GradeRecapCategory> categories;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final itemWidth = constraints.maxWidth < 300
          ? constraints.maxWidth
          : (constraints.maxWidth - 9) / 2;
      return Wrap(
        spacing: 9,
        runSpacing: 9,
        children: [
          for (final category in categories)
            SizedBox(
              width: itemWidth,
              child: _CategoryCard(category: category),
            ),
        ],
      );
    },
  );
}

class _CategoryCard extends StatelessWidget {
  const _CategoryCard({required this.category});

  final GradeRecapCategory category;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: NusaColors.surfaceBlue,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(
            _categoryIcon(category.code),
            color: NusaColors.primary,
            size: 19,
          ),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                category.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                '${category.componentCount} komponen · ${category.weight}%',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9.5,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class GradeRecapStudentCard extends StatelessWidget {
  const GradeRecapStudentCard({
    required this.student,
    required this.categories,
    required this.finalGradeLabel,
    super.key,
  });

  final GradeRecapStudent student;
  final List<GradeRecapCategory> categories;
  final String finalGradeLabel;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('grade-recap-student-${student.studentId}'),
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Text(
                  student.attendanceNumber?.toString() ?? '–',
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      student.name,
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
                      _studentIdentity(student),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      student.status,
                      style: TextStyle(
                        color: student.complete
                            ? NusaColors.success
                            : const Color(0xFFB98200),
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 9),
              Container(
                constraints: const BoxConstraints(minWidth: 62),
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: student.complete
                      ? NusaColors.successSurface
                      : NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  children: [
                    Text(
                      finalGradeLabel,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 8.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      formatGrade(student.finalGrade),
                      style: TextStyle(
                        color: student.complete
                            ? NusaColors.success
                            : NusaColors.primary,
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 11),
          LayoutBuilder(
            builder: (context, constraints) {
              final itemWidth = constraints.maxWidth < 270
                  ? constraints.maxWidth
                  : (constraints.maxWidth - 8) / 2;
              return Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final category in categories)
                    SizedBox(
                      width: itemWidth,
                      child: _StudentCategory(
                        category: category,
                        result: student.categories[category.code],
                      ),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    ),
  );
}

class _StudentCategory extends StatelessWidget {
  const _StudentCategory({required this.category, required this.result});

  final GradeRecapCategory category;
  final GradeRecapCategoryResult? result;

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
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                '${result?.filled ?? 0}/${result?.target ?? 0} terisi · ${result?.weight ?? category.weight}%',
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
        const SizedBox(width: 5),
        Text(
          formatGrade(result?.average),
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 13,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    ),
  );
}

IconData _categoryIcon(String code) => switch (code) {
  'formatif' => Icons.edit_note_rounded,
  'sumatif' => Icons.fact_check_rounded,
  'sts' => Icons.event_note_rounded,
  'sas_saj' => Icons.workspace_premium_rounded,
  _ => Icons.analytics_rounded,
};

String _studentIdentity(GradeRecapStudent student) {
  if (student.nisn?.isNotEmpty == true) return 'NISN ${student.nisn}';
  if (student.nis?.isNotEmpty == true) return 'NIS ${student.nis}';
  return 'Identitas belum tersedia';
}

String formatGrade(double? value) {
  if (value == null) return '–';
  return value.toStringAsFixed(2).replaceFirst(RegExp(r'\.?0+$'), '');
}
