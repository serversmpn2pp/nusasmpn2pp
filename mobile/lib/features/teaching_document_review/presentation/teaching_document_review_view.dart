import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document_review/application/teaching_document_review_controller.dart';
import 'package:nusa/features/teaching_document_review/domain/teaching_document_review.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class TeachingDocumentReviewView extends ConsumerStatefulWidget {
  const TeachingDocumentReviewView({super.key});

  @override
  ConsumerState<TeachingDocumentReviewView> createState() =>
      _TeachingDocumentReviewViewState();
}

class _TeachingDocumentReviewViewState
    extends ConsumerState<TeachingDocumentReviewView> {
  final _searchController = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final monitoring = ref.watch(teachingDocumentReviewControllerProvider);
    final current = monitoring.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pemeriksaan Perangkat Ajar'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: monitoring.isLoading
                ? null
                : ref
                      .read(teachingDocumentReviewControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 7, 16, 8),
                child: Column(
                  children: [
                    _ReviewSummary(summary: current.summary),
                    const SizedBox(height: 10),
                    _ReviewFilters(
                      page: current,
                      searchController: _searchController,
                      enabled: !monitoring.isLoading,
                      notifier: ref.read(
                        teachingDocumentReviewControllerProvider.notifier,
                      ),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: monitoring.when(
                loading: () => current == null
                    ? const Center(child: CircularProgressIndicator())
                    : _TeacherResults(
                        page: current,
                        loadingMore: _loadingMore,
                        onRefresh: ref
                            .read(
                              teachingDocumentReviewControllerProvider.notifier,
                            )
                            .refresh,
                        onLoadMore: _loadMore,
                        onOpen: _openTeacher,
                      ),
                error: (error, stackTrace) => _ReviewError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(teachingDocumentReviewControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _TeacherResults(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(teachingDocumentReviewControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onOpen: _openTeacher,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref
          .read(teachingDocumentReviewControllerProvider.notifier)
          .loadMore();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_errorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _openTeacher(TeachingDocumentTeacherReview teacher) {
    final page = ref.read(teachingDocumentReviewControllerProvider).value!;
    context.push(
      '/pemeriksaan-perangkat-ajar/guru/${teacher.employee.id}'
      '?tahun=${page.filter.academicYearId ?? ''}&semester=${page.filter.semester}',
    );
  }
}

class _ReviewSummary extends StatelessWidget {
  const _ReviewSummary({required this.summary});

  final TeachingDocumentReviewSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 13),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _SummaryItem(label: 'Guru', value: summary.teacherCount),
        _SummaryItem(label: 'Lengkap', value: summary.completeCount),
        _SummaryItem(label: 'Belum', value: summary.incompleteCount),
        _SummaryItem(label: 'Menunggu', value: summary.waitingCount),
        _SummaryItem(label: 'Perbaikan', value: summary.revisionCount),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.label, required this.value});

  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.7),
            fontSize: 8.5,
          ),
        ),
      ],
    ),
  );
}

class _ReviewFilters extends StatelessWidget {
  const _ReviewFilters({
    required this.page,
    required this.searchController,
    required this.enabled,
    required this.notifier,
  });

  final TeachingDocumentReviewPage page;
  final TextEditingController searchController;
  final bool enabled;
  final TeachingDocumentReviewController notifier;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      TextField(
        key: const Key('teaching-document-review-search'),
        controller: searchController,
        enabled: enabled,
        onChanged: notifier.search,
        textInputAction: TextInputAction.search,
        decoration: const InputDecoration(
          hintText: 'Cari guru, NIP, atau mata pelajaran',
          prefixIcon: Icon(Icons.search_rounded),
        ),
      ),
      const SizedBox(height: 8),
      NusaDropdownField<int?>(
        fieldKey: const Key('teaching-document-review-year'),
        value: page.filter.academicYearId,
        enabled: enabled,
        decoration: const InputDecoration(
          labelText: 'Tahun pelajaran',
          prefixIcon: Icon(Icons.calendar_month_rounded),
        ),
        options: [
          for (final year in page.academicYears)
            NusaDropdownOption<int?>(
              value: year.id,
              label: '${year.name}${year.active ? ' · Aktif' : ''}',
            ),
        ],
        onChanged: notifier.filterAcademicYear,
      ),
      const SizedBox(height: 8),
      Row(
        children: [
          Expanded(
            child: NusaDropdownField<int>(
              fieldKey: const Key('teaching-document-review-semester'),
              value: page.filter.semester,
              enabled: enabled,
              decoration: const InputDecoration(labelText: 'Semester'),
              options: const [
                NusaDropdownOption(value: 1, label: 'Semester 1'),
                NusaDropdownOption(value: 2, label: 'Semester 2'),
              ],
              onChanged: (value) {
                if (value != null) notifier.filterSemester(value);
              },
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: NusaDropdownField<String>(
              fieldKey: const Key('teaching-document-review-completeness'),
              value: page.filter.completeness,
              enabled: enabled,
              decoration: const InputDecoration(labelText: 'Kelengkapan'),
              options: const [
                NusaDropdownOption(value: 'semua', label: 'Semua'),
                NusaDropdownOption(value: 'lengkap', label: 'Lengkap'),
                NusaDropdownOption(
                  value: 'belum_lengkap',
                  label: 'Belum lengkap',
                ),
              ],
              onChanged: (value) {
                if (value != null) notifier.filterCompleteness(value);
              },
            ),
          ),
        ],
      ),
      const SizedBox(height: 8),
      NusaDropdownField<String>(
        fieldKey: const Key('teaching-document-review-status'),
        value: page.filter.documentStatus,
        enabled: enabled,
        decoration: const InputDecoration(
          labelText: 'Status dokumen',
          prefixIcon: Icon(Icons.fact_check_outlined),
        ),
        options: const [
          NusaDropdownOption(value: 'semua', label: 'Semua status'),
          NusaDropdownOption(value: 'belum_diunggah', label: 'Belum diunggah'),
          NusaDropdownOption(
            value: 'menunggu_pemeriksaan',
            label: 'Menunggu pemeriksaan',
          ),
          NusaDropdownOption(
            value: 'perlu_perbaikan',
            label: 'Perlu perbaikan',
          ),
          NusaDropdownOption(
            value: 'sudah_diperiksa',
            label: 'Sudah diperiksa',
          ),
        ],
        onChanged: (value) {
          if (value != null) notifier.filterDocumentStatus(value);
        },
      ),
    ],
  );
}

class _TeacherResults extends StatelessWidget {
  const _TeacherResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
  });

  final TeachingDocumentReviewPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<TeachingDocumentTeacherReview> onOpen;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: page.items.isEmpty
        ? const _EmptyTeachers()
        : ListView.builder(
            key: const PageStorageKey<String>('teaching-document-review-list'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 3, 16, 26),
            itemCount:
                page.items.length +
                (page.pagination.hasNextPage || loadingMore ? 1 : 0),
            itemBuilder: (context, index) {
              if (index >= page.items.length) {
                if (!loadingMore) {
                  WidgetsBinding.instance.addPostFrameCallback(
                    (_) => onLoadMore(),
                  );
                }
                return const Padding(
                  padding: EdgeInsets.all(16),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              return Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: _TeacherCard(
                  teacher: page.items[index],
                  onTap: () => onOpen(page.items[index]),
                ),
              );
            },
          ),
  );
}

class _TeacherCard extends StatelessWidget {
  const _TeacherCard({required this.teacher, required this.onTap});

  final TeachingDocumentTeacherReview teacher;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: InkWell(
      key: Key('teaching-document-review-teacher-${teacher.employee.id}'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: NusaColors.surfaceBlue,
                  foregroundColor: NusaColors.primary,
                  child: Text(
                    _initials(teacher.employee.name),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        teacher.employee.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      Text(
                        teacher.employee.nip ?? 'NIP belum tersedia',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              teacher.subjects.map((subject) => subject.name).join(' · '),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 11),
            ),
            const SizedBox(height: 3),
            Text(
              'Tingkat ${teacher.grades.join(', ')}',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: LinearProgressIndicator(
                      value: teacher.percentage / 100,
                      minHeight: 7,
                      backgroundColor: NusaColors.outline,
                      color: teacher.complete
                          ? NusaColors.success
                          : NusaColors.primary,
                    ),
                  ),
                ),
                const SizedBox(width: 9),
                Text(
                  '${teacher.uploadedCount}/${teacher.requiredCount}',
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 9),
            Wrap(
              spacing: 6,
              runSpacing: 5,
              children: [
                _StatusChip(
                  label: '${teacher.waitingCount} menunggu',
                  color: NusaColors.primaryLight,
                ),
                _StatusChip(
                  label: '${teacher.revisionCount} perbaikan',
                  color: Colors.deepOrange,
                ),
                _StatusChip(
                  label: '${teacher.reviewedCount} diperiksa',
                  color: NusaColors.success,
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.11),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w700),
    ),
  );
}

class _EmptyTeachers extends StatelessWidget {
  const _EmptyTeachers();

  @override
  Widget build(BuildContext context) => ListView(
    physics: const AlwaysScrollableScrollPhysics(),
    padding: const EdgeInsets.all(28),
    children: const [
      Icon(Icons.manage_search_rounded, size: 48, color: NusaColors.primary),
      SizedBox(height: 10),
      Text(
        'Tidak ada guru yang sesuai dengan filter.',
        textAlign: TextAlign.center,
      ),
    ],
  );
}

class _ReviewError extends StatelessWidget {
  const _ReviewError({required this.message, required this.onRetry});

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

String _initials(String name) {
  final words = name.trim().split(RegExp(r'\s+'));
  return words.take(2).map((word) => word[0]).join().toUpperCase();
}

String _errorMessage(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Terjadi gangguan saat memuat pemeriksaan perangkat ajar.',
};
