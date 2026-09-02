import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_entry/domain/grade_entry.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GradeEntryFilters extends StatelessWidget {
  const GradeEntryFilters({
    required this.page,
    required this.enabled,
    required this.onAssignmentChanged,
    required this.onSemesterChanged,
    required this.onComponentChanged,
    super.key,
  });

  final GradeEntryPage page;
  final bool enabled;
  final ValueChanged<int?> onAssignmentChanged;
  final ValueChanged<String> onSemesterChanged;
  final ValueChanged<int?> onComponentChanged;

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
                'Pilih Penilaian',
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
            fieldKey: const Key('grade-entry-assignment-filter'),
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
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 2,
                child: NusaDropdownField<String>(
                  fieldKey: const Key('grade-entry-semester-filter'),
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
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                flex: 3,
                child: NusaDropdownField<int>(
                  fieldKey: const Key('grade-entry-component-filter'),
                  value: page.filter.componentId,
                  enabled: enabled,
                  decoration: const InputDecoration(labelText: 'Komponen'),
                  options: [
                    for (final component in page.components)
                      NusaDropdownOption(
                        value: component.id,
                        label: component.label,
                      ),
                  ],
                  onChanged: onComponentChanged,
                ),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

class GradeEntrySummaryCard extends StatelessWidget {
  const GradeEntrySummaryCard({required this.summary, super.key});

  final GradeEntrySummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _SummaryItem(label: 'Siswa', value: '${summary.studentCount}'),
        _SummaryItem(label: 'Terisi', value: '${summary.filledCount}'),
        _SummaryItem(label: 'Belum', value: '${summary.emptyCount}'),
        _SummaryItem(
          label: 'Rata-rata',
          value: summary.average == null
              ? '–'
              : _formatNumber(summary.average!),
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
            color: Colors.white.withValues(alpha: 0.7),
            fontSize: 9.5,
          ),
        ),
      ],
    ),
  );
}

class GradePublicationCard extends StatelessWidget {
  const GradePublicationCard({
    required this.publication,
    required this.enabled,
    required this.dirty,
    this.canPublishOverride,
    required this.onPublish,
    required this.onUnpublish,
    super.key,
  });

  final GradePublication publication;
  final bool enabled;
  final bool dirty;
  final bool? canPublishOverride;
  final VoidCallback onPublish;
  final VoidCallback onUnpublish;

  @override
  Widget build(BuildContext context) {
    final published = publication.published;
    final color = published ? NusaColors.success : const Color(0xFFE6A600);
    final progress = publication.targetCount <= 0
        ? 0.0
        : (publication.valueCount / publication.targetCount).clamp(0.0, 1.0);

    return Card(
      key: const Key('grade-publication-card'),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    published
                        ? Icons.visibility_rounded
                        : Icons.edit_note_rounded,
                    color: color,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Publikasi Nilai',
                        style: TextStyle(
                          color: NusaColors.textPrimary,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        published
                            ? 'Sudah dipublikasikan'
                            : 'Masih berupa draf',
                        style: TextStyle(
                          color: color,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
                _PublicationBadge(published: published),
              ],
            ),
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 7,
                backgroundColor: NusaColors.outline,
                color: published ? NusaColors.success : NusaColors.primary,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              '${publication.valueCount} dari ${publication.targetCount} nilai '
              'terisi pada ${publication.componentCount} komponen aktif',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10.5,
              ),
            ),
            if (publication.publishedAtLabel != null) ...[
              const SizedBox(height: 3),
              Text(
                'Dipublikasikan ${publication.publishedAtLabel}',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10,
                ),
              ),
            ],
            const SizedBox(height: 11),
            SizedBox(
              width: double.infinity,
              child: published
                  ? OutlinedButton.icon(
                      key: const Key('unpublish-grades'),
                      onPressed: enabled && publication.canUnpublish && !dirty
                          ? onUnpublish
                          : null,
                      icon: const Icon(Icons.visibility_off_rounded),
                      label: const Text('Jadikan Draf'),
                    )
                  : FilledButton.tonalIcon(
                      key: const Key('publish-grades'),
                      onPressed:
                          enabled &&
                              (canPublishOverride ?? publication.canPublish) &&
                              !dirty
                          ? onPublish
                          : null,
                      icon: const Icon(Icons.publish_rounded),
                      label: Text(
                        dirty
                            ? 'Simpan Perubahan Dahulu'
                            : 'Publikasikan Nilai',
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PublicationBadge extends StatelessWidget {
  const _PublicationBadge({required this.published});

  final bool published;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: (published ? NusaColors.success : const Color(0xFFE6A600))
          .withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      published ? 'Terbit' : 'Draf',
      style: TextStyle(
        color: published ? NusaColors.success : const Color(0xFFB98200),
        fontSize: 9.5,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class GradeStudentCard extends StatelessWidget {
  const GradeStudentCard({
    required this.student,
    required this.usesPredicate,
    required this.predicateOptions,
    required this.scoreValue,
    required this.predicateValue,
    required this.notes,
    required this.enabled,
    required this.onScoreChanged,
    required this.onPredicateChanged,
    required this.onEditNotes,
    super.key,
  });

  final GradeEntryStudent student;
  final bool usesPredicate;
  final List<String> predicateOptions;
  final String scoreValue;
  final String? predicateValue;
  final String notes;
  final bool enabled;
  final ValueChanged<String> onScoreChanged;
  final ValueChanged<String?> onPredicateChanged;
  final VoidCallback onEditNotes;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('grade-student-${student.studentId}'),
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
                      _identityLabel(student),
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
              const SizedBox(width: 9),
              SizedBox(
                width: 104,
                child: usesPredicate
                    ? NusaDropdownField<String>(
                        fieldKey: Key('grade-predicate-${student.studentId}'),
                        value: predicateValue ?? '',
                        enabled: enabled,
                        decoration: const InputDecoration(
                          labelText: 'Predikat',
                          contentPadding: EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 12,
                          ),
                        ),
                        options: [
                          const NusaDropdownOption(value: '', label: 'Kosong'),
                          for (final option in predicateOptions)
                            NusaDropdownOption(value: option, label: option),
                        ],
                        onChanged: (value) => onPredicateChanged(
                          value?.isEmpty == true ? null : value,
                        ),
                      )
                    : TextFormField(
                        key: Key('grade-score-${student.studentId}'),
                        initialValue: scoreValue,
                        enabled: enabled,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        textInputAction: TextInputAction.next,
                        inputFormatters: [
                          FilteringTextInputFormatter.allow(
                            RegExp(r'^\d{0,3}([\.,]\d{0,2})?$'),
                          ),
                        ],
                        onChanged: onScoreChanged,
                        decoration: const InputDecoration(
                          labelText: 'Nilai',
                          hintText: '0–100',
                          contentPadding: EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 12,
                          ),
                        ),
                      ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          InkWell(
            key: Key('grade-notes-${student.studentId}'),
            onTap: enabled ? onEditNotes : null,
            borderRadius: BorderRadius.circular(11),
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(11),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.sticky_note_2_outlined,
                    size: 16,
                    color: NusaColors.primary,
                  ),
                  const SizedBox(width: 7),
                  Expanded(
                    child: Text(
                      notes.trim().isEmpty ? 'Tambahkan catatan' : notes.trim(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: notes.trim().isEmpty
                            ? NusaColors.textSecondary
                            : NusaColors.textPrimary,
                        fontSize: 10.5,
                      ),
                    ),
                  ),
                  const Icon(
                    Icons.chevron_right_rounded,
                    size: 18,
                    color: NusaColors.textSecondary,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

String _identityLabel(GradeEntryStudent student) {
  if (student.nisn?.isNotEmpty == true) return 'NISN ${student.nisn}';
  if (student.nis?.isNotEmpty == true) return 'NIS ${student.nis}';
  return 'Identitas belum tersedia';
}

String _formatNumber(double value) {
  final text = value.toStringAsFixed(2);
  return text.replaceFirst(RegExp(r'\.?0+$'), '');
}
