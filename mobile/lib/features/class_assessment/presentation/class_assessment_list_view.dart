import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/application/class_assessment_controller.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ClassAssessmentListView extends ConsumerStatefulWidget {
  const ClassAssessmentListView({super.key});

  @override
  ConsumerState<ClassAssessmentListView> createState() =>
      _ClassAssessmentListViewState();
}

class _ClassAssessmentListViewState
    extends ConsumerState<ClassAssessmentListView> {
  final _search = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(classAssessmentControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Asesmen Kelas'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(classAssessmentControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        key: const Key('class-assessment-create'),
        onPressed: () async {
          final changed = await context.push<bool>('/asesmen-kelas/tambah');
          if (changed == true) {
            ref.read(classAssessmentControllerProvider.notifier).refresh();
          }
        },
        icon: const Icon(Icons.add_rounded),
        label: const Text('Tambah'),
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
                      fieldKey: const Key('class-assessment-search'),
                      controller: _search,
                      hintText: 'Cari asesmen, mapel, atau kelas',
                      prefixIcon: Icons.search_rounded,
                      onChanged: ref
                          .read(classAssessmentControllerProvider.notifier)
                          .search,
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<String>(
                      fieldKey: const Key('class-assessment-status-filter'),
                      value: current.filter.status,
                      decoration: const InputDecoration(
                        labelText: 'Status asesmen',
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
                        const NusaDropdownOption(
                          value: 'nonaktif',
                          label: 'Nonaktif',
                        ),
                      ],
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(classAssessmentControllerProvider.notifier)
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
                      .read(classAssessmentControllerProvider.notifier)
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
      ref.read(classAssessmentControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(classAssessmentControllerProvider.notifier).loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});
  final ClassAssessmentSummary summary;

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
        _Metric(label: 'Total', value: summary.total),
        _Metric(label: 'Draf', value: summary.draft),
        _Metric(label: 'Terjadwal', value: summary.scheduled),
        _Metric(label: 'Berjalan', value: summary.ongoing),
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
  final ClassAssessmentPage page;
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
                Icons.assignment_outlined,
                size: 52,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada asesmen kelas dalam cakupan akun.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 2, 16, 88),
            itemCount:
                page.items.length +
                (page.pagination.hasNextPage || loadingMore ? 1 : 0),
            itemBuilder: (context, index) {
              if (index == page.items.length) {
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
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
                child: _AssessmentCard(assessment: page.items[index]),
              );
            },
          ),
  );
}

class _AssessmentCard extends StatelessWidget {
  const _AssessmentCard({required this.assessment});
  final ClassAssessment assessment;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(assessment.status);
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => context.push('/asesmen-kelas/${assessment.id}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
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
                      color: color.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(Icons.assignment_rounded, color: color),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          assessment.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${assessment.subject} · Kelas ${assessment.grade}',
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 10.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  _StatusPill(label: assessment.statusLabel, color: color),
                ],
              ),
              const SizedBox(height: 11),
              Wrap(
                spacing: 12,
                runSpacing: 7,
                children: [
                  _Info(Icons.event_rounded, _dateTime(assessment.startsAt)),
                  _Info(
                    Icons.timer_outlined,
                    '${assessment.durationMinutes} menit',
                  ),
                  _Info(
                    Icons.class_outlined,
                    assessment.classes.isEmpty
                        ? '-'
                        : assessment.classes.join(', '),
                  ),
                  _Info(
                    Icons.quiz_outlined,
                    '${assessment.questionCount}/${assessment.targetQuestions} soal',
                  ),
                  _Info(
                    Icons.groups_outlined,
                    '${assessment.participantCount} peserta',
                  ),
                ],
              ),
              if (!assessment.questionsReady) ...[
                const SizedBox(height: 10),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 7,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF8E1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Text(
                    'Jumlah soal belum memenuhi target.',
                    style: TextStyle(
                      color: Color(0xFF8A6500),
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
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
  'berlangsung' => NusaColors.success,
  'terjadwal' => NusaColors.primary,
  'selesai' => const Color(0xFF6B7280),
  'nonaktif' => const Color(0xFF9CA3AF),
  _ => const Color(0xFFB57900),
};
String _dateTime(DateTime? value) {
  if (value == null) return '-';
  return '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
}

String _message(Object error) => error is AppException
    ? error.message
    : 'Asesmen kelas belum dapat dimuat. Silakan coba lagi.';
