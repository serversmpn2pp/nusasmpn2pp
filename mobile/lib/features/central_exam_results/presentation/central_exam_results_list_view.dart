import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_results/application/central_exam_results_controller.dart';
import 'package:nusa/features/central_exam_results/domain/central_exam_results.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class CentralExamResultsListView extends ConsumerStatefulWidget {
  const CentralExamResultsListView({super.key});

  @override
  ConsumerState<CentralExamResultsListView> createState() =>
      _CentralExamResultsListViewState();
}

class _CentralExamResultsListViewState
    extends ConsumerState<CentralExamResultsListView> {
  final _search = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(centralExamResultsControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Nilai & Hasil Ujian'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref
                      .read(centralExamResultsControllerProvider.notifier)
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
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _Summary(data: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('central-exam-results-search'),
                      controller: _search,
                      hintText: 'Cari kegiatan ujian',
                      prefixIcon: Icons.search_rounded,
                      onChanged: ref
                          .read(centralExamResultsControllerProvider.notifier)
                          .search,
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<String>(
                      fieldKey: const Key('central-exam-results-status'),
                      value: current.filter.status,
                      decoration: const InputDecoration(
                        labelText: 'Status kegiatan',
                        prefixIcon: Icon(Icons.flag_outlined),
                      ),
                      options: [
                        for (final item in current.statuses)
                          NusaDropdownOption(
                            value: item.code,
                            label: item.label,
                          ),
                      ],
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(
                                centralExamResultsControllerProvider.notifier,
                              )
                              .filterStatus(value);
                        }
                      },
                    ),
                  ],
                ),
              ),
            Expanded(
              child: state.when(
                loading: () => current == null
                    ? const Center(child: CircularProgressIndicator())
                    : _Results(
                        page: current,
                        loadingMore: _loadingMore,
                        onRefresh: _refresh,
                        onLoadMore: _loadMore,
                      ),
                error: (error, stackTrace) =>
                    _Error(message: _message(error), onRetry: _refresh),
                data: (page) => _Results(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: _refresh,
                  onLoadMore: _loadMore,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _refresh() =>
      ref.read(centralExamResultsControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(centralExamResultsControllerProvider.notifier).loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.data});
  final CentralExamResultsListSummary data;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 13),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        _Metric(label: 'Kegiatan', value: data.total),
        _Metric(label: 'Aktif', value: data.active),
        _Metric(label: 'Selesai', value: data.finished),
      ],
    ),
  );
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});
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
        Text(label, style: const TextStyle(color: Colors.white70, fontSize: 9)),
      ],
    ),
  );
}

class _Results extends StatelessWidget {
  const _Results({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
  });
  final CentralExamResultsPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: page.items.isEmpty
        ? ListView(
            children: const [
              SizedBox(height: 75),
              Icon(Icons.fact_check_outlined, size: 52),
              SizedBox(height: 12),
              Text(
                'Belum ada hasil ujian terpusat dalam cakupan akun.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 2, 16, 28),
            itemCount:
                page.items.length +
                (page.pagination.hasNextPage || loadingMore ? 1 : 0),
            itemBuilder: (context, index) {
              if (index == page.items.length) {
                return loadingMore
                    ? const Center(child: CircularProgressIndicator())
                    : OutlinedButton(
                        onPressed: onLoadMore,
                        child: const Text('Muat Berikutnya'),
                      );
              }
              return Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: _EventCard(event: page.items[index]),
              );
            },
          ),
  );
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});
  final CentralExamResultEvent event;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      key: Key('central-exam-results-event-${event.id}'),
      borderRadius: BorderRadius.circular(16),
      onTap: () => context.push('/hasil-ujian-terpusat/${event.id}'),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: NusaColors.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Icon(
                    Icons.fact_check_outlined,
                    color: NusaColors.primary,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        event.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        '${event.type ?? 'Ujian Terpusat'} · ${event.academicYear ?? '-'}',
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
            const SizedBox(height: 11),
            Wrap(
              spacing: 12,
              runSpacing: 7,
              children: [
                _Info(Icons.calendar_today_outlined, event.period),
                _Info(
                  Icons.event_note_outlined,
                  '${event.scheduleCount} jadwal',
                ),
                _Info(
                  Icons.groups_outlined,
                  '${event.participantCount} peserta',
                ),
                _Info(
                  Icons.task_alt_outlined,
                  '${event.finishedParticipantCount} selesai',
                ),
                _Info(
                  Icons.publish_outlined,
                  '${event.appliedCount} diterapkan',
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _Info extends StatelessWidget {
  const _Info(this.icon, this.label);
  final IconData icon;
  final String label;
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: NusaColors.textSecondary),
      const SizedBox(width: 4),
      Text(
        label,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10),
      ),
    ],
  );
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

String _message(Object error) => error is AppException
    ? error.message
    : 'Nilai ujian terpusat belum dapat dimuat.';
