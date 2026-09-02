import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_package/application/question_package_controller.dart';
import 'package:nusa/features/question_package/domain/question_package.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class QuestionPackageListView extends ConsumerStatefulWidget {
  const QuestionPackageListView({super.key});

  @override
  ConsumerState<QuestionPackageListView> createState() =>
      _QuestionPackageListViewState();
}

class _QuestionPackageListViewState
    extends ConsumerState<QuestionPackageListView> {
  final _search = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(questionPackageControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Paket Soal'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(questionPackageControllerProvider.notifier).refresh,
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
                    _SummaryCard(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('question-package-search'),
                      controller: _search,
                      hintText: 'Cari kegiatan, mapel, atau kelas',
                      prefixIcon: Icons.search_rounded,
                      onChanged: ref
                          .read(questionPackageControllerProvider.notifier)
                          .search,
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<int?>(
                      fieldKey: const Key('question-package-event-filter'),
                      value: current.filter.eventId,
                      decoration: const InputDecoration(
                        labelText: 'Kegiatan ujian',
                        prefixIcon: Icon(Icons.event_note_rounded),
                      ),
                      options: [
                        const NusaDropdownOption(
                          value: null,
                          label: 'Semua kegiatan ujian',
                        ),
                        for (final event in current.references.events)
                          NusaDropdownOption(
                            value: event.id,
                            label: event.name,
                          ),
                      ],
                      onChanged: ref
                          .read(questionPackageControllerProvider.notifier)
                          .filterEvent,
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<String>(
                      fieldKey: const Key('question-package-status-filter'),
                      value: current.filter.status,
                      decoration: const InputDecoration(
                        labelText: 'Status paket',
                        prefixIcon: Icon(Icons.flag_outlined),
                      ),
                      options: [
                        for (final item in current.references.statuses)
                          NusaDropdownOption(
                            value: item.code,
                            label: item.label,
                          ),
                      ],
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(questionPackageControllerProvider.notifier)
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
                error: (error, stackTrace) => _ErrorState(
                  message: _message(error),
                  onRetry: ref
                      .read(questionPackageControllerProvider.notifier)
                      .refresh,
                ),
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
      ref.read(questionPackageControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(questionPackageControllerProvider.notifier).loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});
  final QuestionPackageSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 5),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        _Metric(label: 'Jadwal', value: summary.total),
        _Metric(label: 'Siap', value: summary.ready),
        _Metric(label: 'Draf', value: summary.draft),
        _Metric(label: 'Belum', value: summary.unbuilt),
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
  final QuestionPackagePage page;
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
                Icons.inventory_2_outlined,
                size: 52,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada jadwal paket dalam cakupan akun.',
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
                if (loadingMore) {
                  return const Padding(
                    padding: EdgeInsets.all(18),
                    child: Center(child: CircularProgressIndicator()),
                  );
                }
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: OutlinedButton(
                    onPressed: onLoadMore,
                    child: const Text('Muat Berikutnya'),
                  ),
                );
              }
              return Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: _ScheduleCard(schedule: page.items[index]),
              );
            },
          ),
  );
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({required this.schedule});
  final QuestionPackageSchedule schedule;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(schedule.status);
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => context.push('/paket-soal/${schedule.id}'),
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
                    child: Icon(Icons.inventory_2_rounded, color: color),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${schedule.subject} · Tingkat ${schedule.grade}',
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                        Text(
                          schedule.event.name,
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
                  _StatusPill(label: schedule.statusLabel, color: color),
                ],
              ),
              const SizedBox(height: 11),
              Wrap(
                spacing: 12,
                runSpacing: 7,
                children: [
                  _Info(Icons.calendar_today_rounded, _date(schedule.date)),
                  _Info(Icons.schedule_rounded, schedule.time ?? '-'),
                  _Info(
                    Icons.class_rounded,
                    schedule.classes.isEmpty
                        ? '-'
                        : schedule.classes.join(', '),
                  ),
                  _Info(Icons.quiz_rounded, '${schedule.questionCount} soal'),
                  _Info(
                    Icons.stars_rounded,
                    '${_number(schedule.totalWeight)} bobot',
                  ),
                ],
              ),
              const Divider(height: 20),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      schedule.canManage ? 'Kelola paket' : 'Pantau paket',
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: NusaColors.primary,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});
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
  'siap' => NusaColors.success,
  'draft' => const Color(0xFFB57900),
  _ => NusaColors.textSecondary,
};
String _date(String? value) {
  final date = value == null ? null : DateTime.tryParse(value);
  if (date == null) return value ?? '-';
  return '${date.day.toString().padLeft(2, '0')}-${date.month.toString().padLeft(2, '0')}-${date.year}';
}

String _number(double value) =>
    value == value.roundToDouble() ? '${value.toInt()}' : '$value';
String _message(Object error) => error is AppException
    ? error.message
    : 'Paket soal belum dapat dimuat. Silakan coba lagi.';
