import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/report_verification/application/report_verification_controller.dart';
import 'package:nusa/features/report_verification/domain/report_verification.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ReportVerificationListView extends ConsumerStatefulWidget {
  const ReportVerificationListView({super.key});

  @override
  ConsumerState<ReportVerificationListView> createState() =>
      _ReportVerificationListViewState();
}

class _ReportVerificationListViewState
    extends ConsumerState<ReportVerificationListView> {
  final _searchController = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final asyncPage = ref.watch(reportVerificationControllerProvider);
    final current = asyncPage.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pemeriksaan & Pengesahan'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: asyncPage.isLoading
                ? null
                : ref
                      .read(reportVerificationControllerProvider.notifier)
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
                    _VerificationSummary(page: current),
                    const SizedBox(height: 9),
                    TextField(
                      key: const Key('report-verification-search'),
                      controller: _searchController,
                      enabled: !asyncPage.isLoading,
                      onChanged: ref
                          .read(reportVerificationControllerProvider.notifier)
                          .search,
                      decoration: const InputDecoration(
                        hintText: 'Nomor laporan, siswa, NISN, atau kelas',
                        prefixIcon: Icon(Icons.search_rounded),
                      ),
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<String>(
                      fieldKey: const Key('report-verification-queue-filter'),
                      value: current.filter.queue,
                      enabled: !asyncPage.isLoading,
                      decoration: const InputDecoration(
                        labelText: 'Antrean proses',
                        prefixIcon: Icon(Icons.filter_alt_outlined),
                      ),
                      options: [
                        for (final option in current.queueOptions)
                          NusaDropdownOption(
                            value: option.code,
                            label: option.label,
                          ),
                      ],
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(
                                reportVerificationControllerProvider.notifier,
                              )
                              .selectQueue(value);
                        }
                      },
                    ),
                  ],
                ),
              ),
            Expanded(
              child: asyncPage.when(
                loading: () => current == null
                    ? const Center(child: CircularProgressIndicator())
                    : _TaskResults(
                        page: current,
                        loadingMore: _loadingMore,
                        onRefresh: ref
                            .read(
                              reportVerificationControllerProvider.notifier,
                            )
                            .refresh,
                        onLoadMore: _loadMore,
                        onOpen: _open,
                      ),
                error: (error, stackTrace) => _PageError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(reportVerificationControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _TaskResults(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(reportVerificationControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onOpen: _open,
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
          .read(reportVerificationControllerProvider.notifier)
          .loadMore();
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _open(ReportVerificationTask task) async {
    final changed = await context.push<bool>(
      '/pemeriksaan-pengesahan/${task.report.id}',
    );
    if (changed == true && mounted) {
      await ref
          .read(reportVerificationControllerProvider.notifier)
          .refresh();
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _VerificationSummary extends StatelessWidget {
  const _VerificationSummary({required this.page});

  final ReportVerificationPage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(13, 13, 13, 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(
              Icons.verified_user_rounded,
              color: NusaColors.accent,
              size: 21,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                _accessLabel(page.access),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            _SummaryValue(label: 'Aktif', value: page.summary.active),
            _SummaryValue(label: 'BK', value: page.summary.counseling),
            _SummaryValue(label: 'Wakil', value: page.summary.approval),
            _SummaryValue(
              label: 'Terlambat',
              value: page.summary.overdue,
              warning: page.summary.overdue > 0,
            ),
            _SummaryValue(label: 'Selesai', value: page.summary.completed),
          ],
        ),
      ],
    ),
  );
}

class _SummaryValue extends StatelessWidget {
  const _SummaryValue({
    required this.label,
    required this.value,
    this.warning = false,
  });

  final String label;
  final int value;
  final bool warning;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: warning ? NusaColors.accent : Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.67),
            fontSize: 8,
          ),
        ),
      ],
    ),
  );
}

class _TaskResults extends StatelessWidget {
  const _TaskResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
  });

  final ReportVerificationPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<ReportVerificationTask> onOpen;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: page.items.isEmpty
        ? const _EmptyTasks()
        : ListView.builder(
            key: const PageStorageKey<String>('report-verification-list'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 3, 16, 28),
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
              final task = page.items[index];
              return Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: _TaskCard(task: task, onTap: () => onOpen(task)),
              );
            },
          ),
  );
}

class _TaskCard extends StatelessWidget {
  const _TaskCard({required this.task, required this.onTap});

  final ReportVerificationTask task;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final report = task.report;
    final statusColor = _statusColor(report.verificationStatus);
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        key: Key('report-verification-task-${report.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(13),
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
                      color: statusColor.withValues(alpha: 0.11),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(
                      task.activeStage == 2
                          ? Icons.approval_rounded
                          : Icons.fact_check_rounded,
                      color: statusColor,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          report.student?.name ?? 'Siswa',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        Text(
                          '${report.number} · ${report.schoolClass?.name ?? 'Belum ada kelas'}',
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
                  const Icon(Icons.chevron_right_rounded),
                ],
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  _Badge(label: report.verificationStatusLabel, color: statusColor),
                  if (report.totalPoints > 0)
                    _Badge(
                      label: '${report.totalPoints} poin',
                      color: Colors.deepOrange,
                    ),
                  _Badge(
                    label: '${task.facts.completedCount}/6 fakta',
                    color: NusaColors.primary,
                  ),
                ],
              ),
              const SizedBox(height: 9),
              Text(
                task.userTask,
                style: const TextStyle(
                  color: NusaColors.primaryDark,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 7),
              _ProcessSteps(stage: task.activeStage),
              if (task.dayLimit > 0) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    Icon(
                      task.overdue
                          ? Icons.warning_amber_rounded
                          : Icons.schedule_rounded,
                      size: 16,
                      color: task.overdue
                          ? Colors.redAccent
                          : NusaColors.textSecondary,
                    ),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        task.overdue
                            ? 'Terlambat ${task.remainingDays.abs().clamp(1, 999)} hari'
                            : '${task.waitingDays} hari diproses · sisa ${task.remainingDays.clamp(0, 999)} hari',
                        style: TextStyle(
                          color: task.overdue
                              ? Colors.redAccent
                              : NusaColors.textSecondary,
                          fontSize: 9.5,
                          fontWeight: task.overdue
                              ? FontWeight.w800
                              : FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
              if (task.lastDecision != null) ...[
                const Divider(height: 18),
                Text(
                  'Keputusan BK terakhir: ${task.lastDecision!.resultLabel}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ProcessSteps extends StatelessWidget {
  const _ProcessSteps({required this.stage});

  final int stage;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(child: _Step(label: '1. Pemeriksaan BK', state: _state(1))),
      const SizedBox(width: 6),
      Expanded(child: _Step(label: '2. Pengesahan Wakil', state: _state(2))),
    ],
  );

  int _state(int value) => stage > value ? 2 : (stage == value ? 1 : 0);
}

class _Step extends StatelessWidget {
  const _Step({required this.label, required this.state});

  final String label;
  final int state;

  @override
  Widget build(BuildContext context) {
    final color = state == 2
        ? NusaColors.success
        : state == 1
        ? const Color(0xFF8A6800)
        : NusaColors.textSecondary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 7),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: TextStyle(color: color, fontSize: 8.5, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.09),
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: color.withValues(alpha: 0.2)),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 8.5, fontWeight: FontWeight.w800),
    ),
  );
}

class _EmptyTasks extends StatelessWidget {
  const _EmptyTasks();

  @override
  Widget build(BuildContext context) => ListView(
    physics: const AlwaysScrollableScrollPhysics(),
    padding: const EdgeInsets.all(36),
    children: const [
      Icon(Icons.task_alt_rounded, size: 58, color: NusaColors.success),
      SizedBox(height: 12),
      Text(
        'Tidak ada laporan dalam antrean ini.',
        textAlign: TextAlign.center,
        style: TextStyle(fontWeight: FontWeight.w800),
      ),
      SizedBox(height: 5),
      Text(
        'Tarik ke bawah untuk memperbarui data.',
        textAlign: TextAlign.center,
        style: TextStyle(color: NusaColors.textSecondary, fontSize: 11),
      ),
    ],
  );
}

class _PageError extends StatelessWidget {
  const _PageError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonal(onPressed: onRetry, child: const Text('Coba Lagi')),
        ],
      ),
    ),
  );
}

String _accessLabel(ReportVerificationAccess access) {
  if (access.canReview && access.canApprove) return 'Pemeriksaan BK & Pengesahan Wakil';
  if (access.canReview) return 'Antrean Pemeriksaan BK';
  if (access.canApprove) return 'Antrean Pengesahan Wakil Kesiswaan';
  return 'Monitoring Proses Laporan';
}

Color _statusColor(String status) => switch (status) {
  'disahkan' || 'ditetapkan_pembinaan' => NusaColors.success,
  'tidak_terbukti' || 'dibatalkan' => Colors.grey,
  'menunggu_pengesahan_wakil' => Colors.deepOrange,
  'perlu_klarifikasi' || 'dikembalikan_bk' => const Color(0xFF9A7200),
  _ => NusaColors.primary,
};

String _errorMessage(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Antrean pemeriksaan belum dapat dimuat.',
};
