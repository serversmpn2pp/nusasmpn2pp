import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_preparation/application/central_exam_preparation_controller.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class CentralExamPreparationListView extends ConsumerStatefulWidget {
  const CentralExamPreparationListView({super.key});

  @override
  ConsumerState<CentralExamPreparationListView> createState() =>
      _CentralExamPreparationListViewState();
}

class _CentralExamPreparationListViewState
    extends ConsumerState<CentralExamPreparationListView> {
  final _search = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(centralExamPreparationControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Ujian Terpusat'),
        actions: [
          IconButton(
            tooltip: 'Pelaksanaan ujian',
            onPressed: () => context.push('/pelaksanaan-ujian-terpusat'),
            icon: const Icon(Icons.monitor_heart_outlined),
          ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref
                      .read(centralExamPreparationControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManageMain == true
          ? FloatingActionButton.extended(
              key: const Key('central-exam-preparation-create'),
              onPressed: () => _openForm(current!.references),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Buat Ujian'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _SummaryCard(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('central-exam-preparation-search'),
                      controller: _search,
                      hintText: 'Cari nama, kode, atau jenis ujian',
                      prefixIcon: Icons.search_rounded,
                      onChanged: _searchChanged,
                      suffixIcon: _search.text.isEmpty
                          ? null
                          : IconButton(
                              onPressed: () {
                                _search.clear();
                                setState(() {});
                                ref
                                    .read(
                                      centralExamPreparationControllerProvider
                                          .notifier,
                                    )
                                    .search('');
                              },
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<String>(
                      fieldKey: const Key('central-exam-preparation-status'),
                      value: current.status,
                      decoration: const InputDecoration(
                        labelText: 'Status kegiatan',
                        prefixIcon: Icon(Icons.flag_outlined),
                      ),
                      options: [
                        const NusaDropdownOption(
                          value: 'semua',
                          label: 'Semua status',
                        ),
                        for (final item in current.references.statuses)
                          NusaDropdownOption(
                            value: item.code,
                            label: item.label,
                          ),
                      ],
                      onChanged: state.isLoading
                          ? null
                          : (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      centralExamPreparationControllerProvider
                                          .notifier,
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
                    _ErrorState(message: _message(error), onRetry: _refresh),
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

  void _searchChanged(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) {
        ref
            .read(centralExamPreparationControllerProvider.notifier)
            .search(value);
      }
    });
  }

  Future<void> _refresh() =>
      ref.read(centralExamPreparationControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref
          .read(centralExamPreparationControllerProvider.notifier)
          .loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm(CentralExamPreparationReferences references) async {
    final changed = await context.push<bool>(
      '/ujian-terpusat/tambah',
      extra: references,
    );
    if (changed == true) await _refresh();
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});
  final CentralExamPreparationSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 13),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        _Metric(label: 'Total', value: summary.total),
        _Metric(label: 'Persiapan', value: summary.draft),
        _Metric(label: 'Aktif', value: summary.active),
        _Metric(label: 'Selesai', value: summary.completed),
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
  final CentralExamPreparationPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: page.items.isEmpty
        ? ListView(
            padding: const EdgeInsets.all(28),
            children: const [
              SizedBox(height: 64),
              Icon(
                Icons.account_tree_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada Ujian Terpusat dalam cakupan akun.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 2, 16, 90),
            itemCount:
                page.items.length +
                (page.pagination.hasNextPage || loadingMore ? 1 : 0),
            itemBuilder: (context, index) {
              if (index == page.items.length) {
                return loadingMore
                    ? const Padding(
                        padding: EdgeInsets.all(16),
                        child: Center(child: CircularProgressIndicator()),
                      )
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
  final CentralExamEvent event;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(event.status);
    return Card(
      child: InkWell(
        key: Key('central-exam-event-${event.id}'),
        borderRadius: BorderRadius.circular(18),
        onTap: () => context.push('/ujian-terpusat/${event.id}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 43,
                    height: 43,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.11),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(Icons.account_tree_rounded, color: color),
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
                        const SizedBox(height: 2),
                        Text(
                          '${event.examType} · ${event.academicYear}',
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 10.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 7),
                  _Pill(label: event.statusLabel, color: color),
                ],
              ),
              const SizedBox(height: 11),
              Wrap(
                spacing: 12,
                runSpacing: 7,
                children: [
                  _Info(Icons.tag_rounded, event.code),
                  _Info(
                    Icons.event_outlined,
                    '${_date(event.startsOn)}–${_date(event.endsOn)}',
                  ),
                  _Info(
                    Icons.groups_outlined,
                    '${event.committeeCount} panitia',
                  ),
                  _Info(Icons.schedule_outlined, '${event.sessionCount} sesi'),
                  _Info(
                    Icons.meeting_room_outlined,
                    '${event.roomCount} ruang',
                  ),
                ],
              ),
              const SizedBox(height: 10),
              LinearProgressIndicator(
                value:
                    [
                      event.committeeCount > 0,
                      event.sessionCount > 0,
                      event.roomCount > 0,
                    ].where((ready) => ready).length /
                    3,
                minHeight: 5,
                borderRadius: BorderRadius.circular(8),
                backgroundColor: NusaColors.outline,
                color: NusaColors.success,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800),
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

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 52),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
        ],
      ),
    ),
  );
}

Color _statusColor(String status) => switch (status) {
  'aktif' => NusaColors.success,
  'selesai' => NusaColors.primaryLight,
  'nonaktif' => Colors.grey,
  _ => const Color(0xFFE59A00),
};

String _date(DateTime? value) => value == null
    ? '-'
    : '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}';

String _message(Object error) => error is AppException
    ? error.message
    : 'Persiapan Ujian Terpusat belum dapat dimuat.';
