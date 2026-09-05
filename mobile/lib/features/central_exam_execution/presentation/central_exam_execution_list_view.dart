import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_execution/application/central_exam_execution_controller.dart';
import 'package:nusa/features/central_exam_execution/domain/central_exam_execution.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class CentralExamExecutionListView extends ConsumerStatefulWidget {
  const CentralExamExecutionListView({super.key});

  @override
  ConsumerState<CentralExamExecutionListView> createState() =>
      _CentralExamExecutionListViewState();
}

class _CentralExamExecutionListViewState
    extends ConsumerState<CentralExamExecutionListView> {
  final _search = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(centralExamExecutionControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pelaksanaan Ujian'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref
                      .read(centralExamExecutionControllerProvider.notifier)
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
                    _Summary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('central-exam-execution-search'),
                      controller: _search,
                      hintText: 'Cari kegiatan atau jenis ujian',
                      prefixIcon: Icons.search_rounded,
                      onChanged: ref
                          .read(centralExamExecutionControllerProvider.notifier)
                          .search,
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<String>(
                      fieldKey: const Key(
                        'central-exam-execution-status-filter',
                      ),
                      value: current.filter.status,
                      decoration: const InputDecoration(
                        labelText: 'Status kegiatan',
                        prefixIcon: Icon(Icons.flag_outlined),
                      ),
                      options: [
                        for (final option in current.statuses)
                          NusaDropdownOption(
                            value: option.code,
                            label: option.label,
                          ),
                      ],
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(
                                centralExamExecutionControllerProvider.notifier,
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
      ref.read(centralExamExecutionControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref
          .read(centralExamExecutionControllerProvider.notifier)
          .loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final CentralExamEventSummary summary;

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
        _Metric('Total', summary.total),
        _Metric('Aktif', summary.active),
        _Metric('Persiapan', summary.preparation),
        _Metric('Selesai', summary.finished),
      ],
    ),
  );
}

class _Metric extends StatelessWidget {
  const _Metric(this.label, this.value);
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
  final CentralExamExecutionPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: page.items.isEmpty
        ? ListView(
            padding: const EdgeInsets.all(24),
            children: const [
              SizedBox(height: 70),
              Icon(
                Icons.monitor_heart_outlined,
                size: 52,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada kegiatan ujian terpusat dalam cakupan akun.',
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
                return Padding(
                  padding: const EdgeInsets.only(top: 5),
                  child: loadingMore
                      ? const Center(child: CircularProgressIndicator())
                      : OutlinedButton(
                          onPressed: onLoadMore,
                          child: const Text('Muat Berikutnya'),
                        ),
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
        borderRadius: BorderRadius.circular(16),
        onTap: () => context.push('/pelaksanaan-ujian-terpusat/${event.id}'),
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
                      color: color.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(Icons.hub_rounded, color: color),
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
                  _Pill(event.statusLabel, color),
                ],
              ),
              const SizedBox(height: 11),
              Wrap(
                spacing: 12,
                runSpacing: 7,
                children: [
                  _Info(Icons.calendar_today_rounded, _period(event)),
                  _Info(
                    Icons.event_note_rounded,
                    '${event.scheduleCount} jadwal',
                  ),
                  _Info(
                    Icons.inventory_2_rounded,
                    '${event.readyPackageCount} paket siap',
                  ),
                  _Info(
                    Icons.groups_rounded,
                    '${event.participantCount} peserta',
                  ),
                ],
              ),
              const Divider(height: 20),
              const Row(
                children: [
                  Expanded(
                    child: Text(
                      'Buka pusat pelaksanaan',
                      style: TextStyle(
                        color: NusaColors.primary,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  Icon(Icons.chevron_right_rounded, color: NusaColors.primary),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill(this.label, this.color);
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
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
  const _Info(this.icon, this.text);
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: NusaColors.textSecondary),
      const SizedBox(width: 4),
      Text(
        text,
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

Color _statusColor(String status) => switch (status) {
  'aktif' => NusaColors.success,
  'selesai' => NusaColors.primary,
  'nonaktif' => NusaColors.textSecondary,
  _ => const Color(0xFF9A7000),
};

String _period(CentralExamEvent event) {
  final start = _date(event.startDate);
  final end = _date(event.endDate);
  return start == end ? start : '$start – $end';
}

String _date(String? value) {
  final date = value == null ? null : DateTime.tryParse(value);
  if (date == null) return '-';
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

String _message(Object error) => error is AppException
    ? error.message
    : 'Pelaksanaan ujian terpusat belum dapat dimuat.';
