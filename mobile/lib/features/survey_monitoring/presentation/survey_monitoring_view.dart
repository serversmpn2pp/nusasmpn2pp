import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/survey_monitoring/application/survey_monitoring_controller.dart';
import 'package:nusa/features/survey_monitoring/domain/survey_monitoring.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class SurveyMonitoringView extends ConsumerStatefulWidget {
  const SurveyMonitoringView({super.key});

  @override
  ConsumerState<SurveyMonitoringView> createState() =>
      _SurveyMonitoringViewState();
}

class _SurveyMonitoringViewState extends ConsumerState<SurveyMonitoringView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final monitoring = ref.watch(surveyMonitoringControllerProvider);
    final current = monitoring.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Monitoring Survei'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: monitoring.isLoading
                ? null
                : ref.read(surveyMonitoringControllerProvider.notifier).refresh,
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
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _MonitoringSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaDropdownField<int>(
                      fieldKey: const Key('survey-monitoring-year-filter'),
                      value: current.filter.academicYearId,
                      enabled: !monitoring.isLoading,
                      decoration: const InputDecoration(
                        labelText: 'Tahun pelajaran',
                        prefixIcon: Icon(Icons.calendar_month_rounded),
                      ),
                      options: [
                        for (final year in current.academicYears)
                          NusaDropdownOption<int>(
                            value: year.id,
                            label:
                                '${year.name}${year.active ? ' · Aktif' : ''}',
                          ),
                      ],
                      onChanged: (value) => ref
                          .read(surveyMonitoringControllerProvider.notifier)
                          .filterAcademicYear(value),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key(
                              'survey-monitoring-semester-filter',
                            ),
                            value: current.filter.semester,
                            enabled: !monitoring.isLoading,
                            decoration: const InputDecoration(
                              labelText: 'Semester',
                            ),
                            options: const [
                              NusaDropdownOption(
                                value: 'ganjil',
                                label: 'Ganjil',
                              ),
                              NusaDropdownOption(
                                value: 'genap',
                                label: 'Genap',
                              ),
                            ],
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      surveyMonitoringControllerProvider
                                          .notifier,
                                    )
                                    .filterSemester(value);
                              }
                            },
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key(
                              'survey-monitoring-status-filter',
                            ),
                            value: current.filter.status,
                            enabled: !monitoring.isLoading,
                            decoration: const InputDecoration(
                              labelText: 'Status',
                            ),
                            options: const [
                              NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua',
                              ),
                              NusaDropdownOption(
                                value: 'belum',
                                label: 'Belum',
                              ),
                              NusaDropdownOption(
                                value: 'berjalan',
                                label: 'Berjalan',
                              ),
                              NusaDropdownOption(
                                value: 'lengkap',
                                label: 'Lengkap',
                              ),
                            ],
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      surveyMonitoringControllerProvider
                                          .notifier,
                                    )
                                    .filterStatus(value);
                              }
                            },
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    NusaTextField(
                      fieldKey: const Key('survey-monitoring-search'),
                      controller: _searchController,
                      hintText: 'Cari guru, NIP, mapel, atau kelas',
                      prefixIcon: Icons.search_rounded,
                      enabled: !monitoring.isLoading,
                      onChanged: _search,
                      suffixIcon: _searchController.text.isEmpty
                          ? null
                          : IconButton(
                              onPressed: _clearSearch,
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: monitoring.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _MonitoringError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(surveyMonitoringControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _MonitoringResults(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(surveyMonitoringControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onOpen: (item) => context.push(
                    '/monitoring-survei/${item.id}'
                    '?semester=${page.filter.semester}',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) {
        ref.read(surveyMonitoringControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(surveyMonitoringControllerProvider.notifier).search('');
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(surveyMonitoringControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_errorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _MonitoringSummary extends StatelessWidget {
  const _MonitoringSummary({required this.summary});

  final SurveyMonitoringSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _SummaryItem(label: 'Penugasan', value: summary.assignments),
        _SummaryItem(label: 'Target', value: summary.responseTarget),
        _SummaryItem(label: 'Respons', value: summary.responses),
        _SummaryItem(label: 'Terbuka', value: summary.openResults),
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
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 9,
          ),
        ),
      ],
    ),
  );
}

class _MonitoringResults extends StatelessWidget {
  const _MonitoringResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
  });

  final SurveyMonitoringPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<SurveyMonitoringAssignment> onOpen;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: const [
            Icon(Icons.analytics_outlined, size: 50, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada penugasan yang sesuai dengan filter.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('survey-monitoring-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
        itemCount: page.items.length + 1,
        separatorBuilder: (context, index) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          if (index == page.items.length) {
            return page.pagination.hasNextPage
                ? OutlinedButton.icon(
                    onPressed: loadingMore ? null : onLoadMore,
                    icon: loadingMore
                        ? const SizedBox.square(
                            dimension: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.expand_more_rounded),
                    label: Text(
                      loadingMore ? 'Memuat...' : 'Muat lebih banyak',
                    ),
                  )
                : Text(
                    '${page.pagination.total} penugasan ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final item = page.items[index];
          return _AssignmentCard(
            item: item,
            minimumRespondents: page.minimumRespondents,
            onTap: () => onOpen(item),
          );
        },
      ),
    );
  }
}

class _AssignmentCard extends StatelessWidget {
  const _AssignmentCard({
    required this.item,
    required this.minimumRespondents,
    required this.onTap,
  });

  final SurveyMonitoringAssignment item;
  final int minimumRespondents;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('survey-monitoring-${item.id}'),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: NusaColors.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.person_search_rounded,
                    color: NusaColors.primary,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.teacherName,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        '${item.subjectName} · ${item.className}',
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
                _ResponseBadge(status: item.responseStatus),
                const SizedBox(width: 2),
                const Icon(Icons.chevron_right_rounded, size: 20),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(5),
                    child: LinearProgressIndicator(
                      minHeight: 8,
                      value: (item.responsePercentage / 100)
                          .clamp(0.0, 1.0)
                          .toDouble(),
                      backgroundColor: NusaColors.outline,
                    ),
                  ),
                ),
                const SizedBox(width: 9),
                Text(
                  '${item.respondentCount}/${item.studentCount}',
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(
                  item.resultsOpen
                      ? Icons.lock_open_rounded
                      : Icons.lock_rounded,
                  size: 14,
                  color: item.resultsOpen
                      ? NusaColors.success
                      : NusaColors.textSecondary,
                ),
                const SizedBox(width: 5),
                Expanded(
                  child: Text(
                    item.resultsOpen
                        ? 'Hasil terbuka · Rata-rata ${item.average?.toStringAsFixed(2) ?? '-'}'
                        : 'Menunggu minimal $minimumRespondents responden',
                    style: TextStyle(
                      color: item.resultsOpen
                          ? NusaColors.success
                          : NusaColors.textSecondary,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _ResponseBadge extends StatelessWidget {
  const _ResponseBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'lengkap' => ('Lengkap', NusaColors.success),
      'berjalan' => ('Proses', NusaColors.primaryLight),
      _ => ('Belum', NusaColors.textSecondary),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 9,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _MonitoringError extends StatelessWidget {
  const _MonitoringError({required this.message, required this.onRetry});

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
    : 'Monitoring survei belum dapat dimuat.';
