import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_recap/application/grade_recap_controller.dart';
import 'package:nusa/features/grade_recap/domain/grade_recap.dart';
import 'package:nusa/features/grade_recap/presentation/widgets/grade_recap_components.dart';

class GradeRecapView extends ConsumerWidget {
  const GradeRecapView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final result = ref.watch(gradeRecapControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Rekap Nilai Rapor'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: result.isLoading
                ? null
                : ref.read(gradeRecapControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: result.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _GradeRecapError(
            message: _errorMessage(error),
            onRetry: ref.read(gradeRecapControllerProvider.notifier).refresh,
          ),
          data: (page) => _GradeRecapContent(page: page),
        ),
      ),
    );
  }
}

class _GradeRecapContent extends ConsumerWidget {
  const _GradeRecapContent({required this.page});

  final GradeRecapPage page;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (page.assignments.isEmpty) {
      return RefreshIndicator(
        onRefresh: ref.read(gradeRecapControllerProvider.notifier).refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(38),
          children: const [
            Icon(
              Icons.assessment_outlined,
              size: 52,
              color: NusaColors.primary,
            ),
            SizedBox(height: 14),
            Text(
              'Belum ada penugasan guru mata pelajaran aktif untuk direkap.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    final controller = ref.read(gradeRecapControllerProvider.notifier);
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        key: const PageStorageKey<String>('grade-recap-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          GradeRecapFilters(
            page: page,
            enabled: true,
            onAssignmentChanged: controller.selectAssignment,
            onSemesterChanged: controller.selectSemester,
          ),
          const SizedBox(height: 10),
          GradeRecapSummaryCard(summary: page.summary),
          if (page.selectedAssignment case final assignment?) ...[
            const SizedBox(height: 10),
            GradeRecapAssignmentCard(assignment: assignment),
          ],
          for (final warning in page.warnings) ...[
            const SizedBox(height: 9),
            GradeRecapWarning(message: warning),
          ],
          const SizedBox(height: 18),
          const _SectionTitle(title: 'Komponen & Bobot'),
          const SizedBox(height: 9),
          GradeRecapSchemeGrid(categories: page.categories),
          const SizedBox(height: 20),
          Row(
            children: [
              const Expanded(child: _SectionTitle(title: 'Rekap Siswa')),
              Text(
                '${page.summary.studentCount} siswa',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          if (page.students.isEmpty)
            const _InlineEmpty(
              message: 'Belum ada siswa aktif pada kelas penugasan ini.',
            )
          else
            for (final student in page.students) ...[
              GradeRecapStudentCard(
                student: student,
                categories: page.categories,
                finalGradeLabel: page.finalGradeLabel,
              ),
              const SizedBox(height: 9),
            ],
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) => Text(
    title,
    style: const TextStyle(
      color: NusaColors.textPrimary,
      fontSize: 16,
      fontWeight: FontWeight.w800,
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

class _GradeRecapError extends StatelessWidget {
  const _GradeRecapError({required this.message, required this.onRetry});

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

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Rekap nilai rapor belum dapat dimuat.';
