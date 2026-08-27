import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_grades/application/my_grades_controller.dart';
import 'package:nusa/features/my_grades/domain/my_grades.dart';
import 'package:nusa/features/my_grades/presentation/widgets/my_grades_components.dart';

class MyGradesView extends ConsumerWidget {
  const MyGradesView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final result = ref.watch(myGradesControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Nilai Saya'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: result.isLoading
                ? null
                : ref.read(myGradesControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: result.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _MyGradesError(
            message: _errorMessage(error),
            onRetry: ref.read(myGradesControllerProvider.notifier).refresh,
          ),
          data: (page) => _MyGradesContent(page: page),
        ),
      ),
    );
  }
}

class _MyGradesContent extends ConsumerWidget {
  const _MyGradesContent({required this.page});

  final MyGradesPage page;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(myGradesControllerProvider.notifier);
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        key: const PageStorageKey<String>('my-grades-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          if (page.student case final student?) ...[
            MyGradesIdentityCard(
              student: student,
              schoolClass: page.schoolClass,
            ),
            const SizedBox(height: 10),
          ],
          MyGradesFilters(
            page: page,
            enabled: page.academicYears.isNotEmpty,
            onAcademicYearChanged: controller.selectAcademicYear,
            onSemesterChanged: controller.selectSemester,
          ),
          const SizedBox(height: 10),
          MyGradesSummaryCard(summary: page.summary),
          const SizedBox(height: 19),
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Nilai Mata Pelajaran',
                  style: TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Text(
                page.filter.semester == 'ganjil'
                    ? 'Semester Ganjil'
                    : 'Semester Genap',
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          if (page.subjects.isEmpty)
            _EmptyGrades(
              message:
                  page.emptyMessage ??
                  'Belum ada nilai yang dipublikasikan untuk semester ini.',
            )
          else
            for (var index = 0; index < page.subjects.length; index++) ...[
              if (page.subjects[index].open)
                OpenGradeSubjectCard(
                  subject: page.subjects[index],
                  initiallyExpanded: index == 0,
                )
              else
                LockedGradeSubjectCard(
                  subject: page.subjects[index],
                  onFillSurvey: () =>
                      _openSurvey(context, controller, page.subjects[index]),
                ),
              const SizedBox(height: 9),
            ],
        ],
      ),
    );
  }

  Future<void> _openSurvey(
    BuildContext context,
    MyGradesController controller,
    MyGradesSubject subject,
  ) async {
    final completed = await context.push<bool>(
      '/survei-pembelajaran/${subject.assignmentId}/${subject.surveySemester}',
    );
    if (completed != true) return;
    await controller.refresh();
    if (!context.mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          content: Text('Survei berhasil dikirim. Nilai sudah terbuka.'),
        ),
      );
  }
}

class _EmptyGrades extends StatelessWidget {
  const _EmptyGrades({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(27),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        const Icon(
          Icons.workspace_premium_outlined,
          size: 42,
          color: NusaColors.primary,
        ),
        const SizedBox(height: 10),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 11.5,
            height: 1.4,
          ),
        ),
      ],
    ),
  );
}

class _MyGradesError extends StatelessWidget {
  const _MyGradesError({required this.message, required this.onRetry});

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

String _errorMessage(Object error) =>
    error is AppException ? error.message : 'Nilai Anda belum dapat dimuat.';
