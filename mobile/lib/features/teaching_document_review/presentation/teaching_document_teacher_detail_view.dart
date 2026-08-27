import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';
import 'package:nusa/features/teaching_document_review/application/teaching_document_review_controller.dart';
import 'package:nusa/features/teaching_document_review/domain/teaching_document_review.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class TeachingDocumentTeacherDetailView extends ConsumerStatefulWidget {
  const TeachingDocumentTeacherDetailView({
    required this.teacherId,
    this.initialAcademicYearId,
    this.initialSemester = 1,
    super.key,
  });

  final int teacherId;
  final int? initialAcademicYearId;
  final int initialSemester;

  @override
  ConsumerState<TeachingDocumentTeacherDetailView> createState() =>
      _TeachingDocumentTeacherDetailViewState();
}

class _TeachingDocumentTeacherDetailViewState
    extends ConsumerState<TeachingDocumentTeacherDetailView> {
  late int? _academicYearId = widget.initialAcademicYearId;
  late int _semester = widget.initialSemester == 2 ? 2 : 1;

  TeachingDocumentTeacherQuery get _query => (
    teacherId: widget.teacherId,
    academicYearId: _academicYearId,
    semester: _semester,
  );

  @override
  Widget build(BuildContext context) {
    final query = _query;
    final detail = ref.watch(teachingDocumentTeacherDetailProvider(query));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Kelengkapan Guru'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: detail.isLoading
                ? null
                : () => ref.invalidate(
                    teachingDocumentTeacherDetailProvider(query),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _TeacherDetailError(
            message: _errorMessage(error),
            onRetry: () =>
                ref.invalidate(teachingDocumentTeacherDetailProvider(query)),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () => ref.refresh(
              teachingDocumentTeacherDetailProvider(query).future,
            ),
            child: ListView(
              key: const PageStorageKey<String>(
                'teaching-document-teacher-detail',
              ),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
              children: [
                _TeacherHeader(detail: data),
                const SizedBox(height: 11),
                NusaDropdownField<int?>(
                  fieldKey: const Key('review-teacher-year'),
                  value: _academicYearId ?? data.filter.academicYearId,
                  decoration: const InputDecoration(
                    labelText: 'Tahun pelajaran',
                    prefixIcon: Icon(Icons.calendar_month_rounded),
                  ),
                  options: [
                    for (final year in data.academicYears)
                      NusaDropdownOption<int?>(
                        value: year.id,
                        label: '${year.name}${year.active ? ' · Aktif' : ''}',
                      ),
                  ],
                  onChanged: (value) => setState(() => _academicYearId = value),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    for (final semester in [1, 2])
                      Expanded(
                        child: Padding(
                          padding: EdgeInsets.only(
                            right: semester == 1 ? 8 : 0,
                          ),
                          child: ChoiceChip(
                            key: Key('review-teacher-semester-$semester'),
                            label: SizedBox(
                              width: double.infinity,
                              child: Text(
                                'Semester $semester',
                                textAlign: TextAlign.center,
                              ),
                            ),
                            selected: _semester == semester,
                            showCheckmark: false,
                            onSelected: (_) =>
                                setState(() => _semester = semester),
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 18),
                const Text(
                  'Dokumen per Penugasan',
                  style: TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                const Text(
                  'Ketuk dokumen yang sudah diunggah untuk melihat dan memeriksanya.',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                const SizedBox(height: 9),
                for (
                  var index = 0;
                  index < data.assignments.length;
                  index++
                ) ...[
                  _AssignmentReviewCard(
                    assignment: data.assignments[index],
                    onOpen: (id) =>
                        context.push('/pemeriksaan-perangkat-ajar/dokumen/$id'),
                  ),
                  if (index < data.assignments.length - 1)
                    const SizedBox(height: 9),
                ],
                if (data.legacyDocuments.isNotEmpty) ...[
                  const SizedBox(height: 15),
                  _LegacyWarning(count: data.legacyDocuments.length),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TeacherHeader extends StatelessWidget {
  const _TeacherHeader({required this.detail});

  final TeachingDocumentTeacherDetail detail;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          detail.employee.name,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          detail.employee.nip ?? 'NIP belum tersedia',
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.7),
            fontSize: 10.5,
          ),
        ),
        const SizedBox(height: 13),
        ClipRRect(
          borderRadius: BorderRadius.circular(9),
          child: LinearProgressIndicator(
            value: detail.summary.completeness / 100,
            minHeight: 8,
            backgroundColor: Colors.white.withValues(alpha: 0.18),
            valueColor: AlwaysStoppedAnimation(
              detail.summary.completeness >= 100
                  ? NusaColors.success
                  : NusaColors.accent,
            ),
          ),
        ),
        const SizedBox(height: 7),
        Row(
          children: [
            Text(
              '${detail.summary.uploadedCount}/${detail.summary.requiredCount} dokumen wajib',
              style: const TextStyle(color: Colors.white, fontSize: 10.5),
            ),
            const Spacer(),
            Text(
              '${detail.summary.completeness}%',
              style: const TextStyle(
                color: NusaColors.accent,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
        const SizedBox(height: 11),
        Wrap(
          spacing: 7,
          runSpacing: 6,
          children: [
            _HeaderMetric(
              label: '${detail.summary.waitingCount} menunggu',
              icon: Icons.hourglass_top_rounded,
            ),
            _HeaderMetric(
              label: '${detail.summary.revisionCount} perbaikan',
              icon: Icons.build_circle_outlined,
            ),
            _HeaderMetric(
              label: '${detail.summary.reviewedCount} diperiksa',
              icon: Icons.verified_rounded,
            ),
          ],
        ),
      ],
    ),
  );
}

class _HeaderMetric extends StatelessWidget {
  const _HeaderMetric({required this.label, required this.icon});

  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: Colors.white, size: 13),
        const SizedBox(width: 5),
        Text(label, style: const TextStyle(color: Colors.white, fontSize: 9)),
      ],
    ),
  );
}

class _AssignmentReviewCard extends StatelessWidget {
  const _AssignmentReviewCard({required this.assignment, required this.onOpen});

  final TeachingDocumentAssignment assignment;
  final ValueChanged<int> onOpen;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: NusaColors.surface,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Column(
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(13),
          decoration: const BoxDecoration(
            color: NusaColors.surfaceBlue,
            borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
          ),
          child: Text(
            '${assignment.subject.name} · Tingkat ${assignment.gradeLabel}',
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
        for (var index = 0; index < assignment.slots.length; index++) ...[
          _ReviewSlot(
            slot: assignment.slots[index],
            onTap: assignment.slots[index].document == null
                ? null
                : () => onOpen(assignment.slots[index].document!.id),
          ),
          if (index < assignment.slots.length - 1)
            const Divider(height: 1, indent: 13, endIndent: 13),
        ],
      ],
    ),
  );
}

class _ReviewSlot extends StatelessWidget {
  const _ReviewSlot({required this.slot, required this.onTap});

  final TeachingDocumentSlot slot;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final document = slot.document;
    return InkWell(
      key: Key('review-document-slot-${slot.type.id}'),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
        child: Row(
          children: [
            Icon(
              document == null
                  ? Icons.upload_file_outlined
                  : Icons.picture_as_pdf_rounded,
              color: document == null
                  ? NusaColors.textSecondary
                  : _statusColor(document.status),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          slot.type.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      if (slot.type.required)
                        const Text(
                          'WAJIB',
                          style: TextStyle(
                            color: NusaColors.primary,
                            fontSize: 8,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                    ],
                  ),
                  Text(
                    document?.statusLabel ?? 'Belum diunggah',
                    style: TextStyle(
                      color: document == null
                          ? NusaColors.textSecondary
                          : _statusColor(document.status),
                      fontSize: 10,
                    ),
                  ),
                  if (document?.reviewerNote != null)
                    Text(
                      document!.reviewerNote!,
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
            if (document != null)
              const Icon(
                Icons.chevron_right_rounded,
                color: NusaColors.primary,
              ),
          ],
        ),
      ),
    );
  }
}

class _LegacyWarning extends StatelessWidget {
  const _LegacyWarning({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: Colors.orange.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Text(
      'Ada $count dokumen lama tanpa tingkat. Dokumen tersebut belum dihitung sebagai kelengkapan.',
      style: const TextStyle(fontSize: 11),
    ),
  );
}

class _TeacherDetailError extends StatelessWidget {
  const _TeacherDetailError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(message, textAlign: TextAlign.center),
        const SizedBox(height: 10),
        FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
      ],
    ),
  );
}

Color _statusColor(String status) => switch (status) {
  'sudah_diperiksa' => NusaColors.success,
  'perlu_perbaikan' => Colors.deepOrange,
  _ => NusaColors.primaryLight,
};

String _errorMessage(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Terjadi gangguan saat memuat kelengkapan guru.',
};
