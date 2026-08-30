import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_report/application/student_report_controller.dart';
import 'package:nusa/features/student_report/domain/student_report.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentReportListView extends ConsumerStatefulWidget {
  const StudentReportListView({super.key});

  @override
  ConsumerState<StudentReportListView> createState() =>
      _StudentReportListViewState();
}

class _StudentReportListViewState extends ConsumerState<StudentReportListView> {
  final _searchController = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reports = ref.watch(studentReportControllerProvider);
    final current = reports.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Daftar Laporan Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: reports.isLoading
                ? null
                : ref.read(studentReportControllerProvider.notifier).refresh,
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
                    _ReportSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    TextField(
                      key: const Key('student-report-search'),
                      controller: _searchController,
                      enabled: !reports.isLoading,
                      onChanged: ref
                          .read(studentReportControllerProvider.notifier)
                          .search,
                      decoration: const InputDecoration(
                        hintText: 'Nomor, siswa, NISN, tempat, atau kronologi',
                        prefixIcon: Icon(Icons.search_rounded),
                      ),
                    ),
                    const SizedBox(height: 8),
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final verification = NusaDropdownField<String>(
                          fieldKey: const Key(
                            'student-report-verification-filter',
                          ),
                          value: current.filter.verificationStatus,
                          enabled: !reports.isLoading,
                          decoration: const InputDecoration(
                            labelText: 'Status pemeriksaan',
                            prefixIcon: Icon(Icons.fact_check_outlined),
                          ),
                          options: [
                            const NusaDropdownOption(
                              value: 'semua',
                              label: 'Semua pemeriksaan',
                            ),
                            for (final option
                                in current.options.verificationStatuses)
                              NusaDropdownOption(
                                value: option.code,
                                label: option.label,
                              ),
                          ],
                          onChanged: (value) {
                            if (value != null) {
                              ref
                                  .read(
                                    studentReportControllerProvider.notifier,
                                  )
                                  .filterVerification(value);
                            }
                          },
                        );
                        final filterButton = OutlinedButton.icon(
                          key: const Key('open-student-report-filters'),
                          onPressed: reports.isLoading
                              ? null
                              : () => _showFilters(current),
                          icon: const Icon(Icons.tune_rounded),
                          label: Text(
                            _activeFilterCount(current.filter) == 0
                                ? 'Filter'
                                : 'Filter ${_activeFilterCount(current.filter)}',
                          ),
                        );
                        if (constraints.maxWidth < 330) {
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              verification,
                              const SizedBox(height: 8),
                              SizedBox(height: 48, child: filterButton),
                            ],
                          );
                        }
                        return Row(
                          children: [
                            Expanded(child: verification),
                            const SizedBox(width: 8),
                            SizedBox(height: 56, child: filterButton),
                          ],
                        );
                      },
                    ),
                  ],
                ),
              ),
            Expanded(
              child: reports.when(
                loading: () => current == null
                    ? const Center(child: CircularProgressIndicator())
                    : _ReportResults(
                        page: current,
                        loadingMore: _loadingMore,
                        onRefresh: ref
                            .read(studentReportControllerProvider.notifier)
                            .refresh,
                        onLoadMore: _loadMore,
                        onOpen: _openReport,
                      ),
                error: (error, stackTrace) => _ReportError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(studentReportControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _ReportResults(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(studentReportControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onOpen: _openReport,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _showFilters(StudentReportPage page) async {
    final result = await showModalBottomSheet<_StudentReportFilterValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _ReportFilterSheet(page: page),
    );
    if (result == null) return;
    if (result.reset) {
      await ref.read(studentReportControllerProvider.notifier).resetFilters();
      return;
    }
    await ref
        .read(studentReportControllerProvider.notifier)
        .applyFilters(
          status: result.status,
          level: result.level,
          type: result.type,
          academicYearId: result.academicYearId,
          classId: result.classId,
        );
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(studentReportControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_errorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _openReport(StudentReportItem report) {
    context.push('/daftar-laporan-siswa/${report.id}');
  }
}

class _ReportSummary extends StatelessWidget {
  const _ReportSummary({required this.summary});

  final StudentReportSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _SummaryItem(label: 'Total', value: summary.total),
        _SummaryItem(label: 'Menunggu BK', value: summary.waitingCounseling),
        _SummaryItem(label: 'Wakil', value: summary.waitingApproval),
        _SummaryItem(label: 'Disahkan', value: summary.approved),
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
            color: Colors.white.withValues(alpha: 0.68),
            fontSize: 8.5,
          ),
        ),
      ],
    ),
  );
}

class _ReportResults extends StatelessWidget {
  const _ReportResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
  });

  final StudentReportPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<StudentReportItem> onOpen;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: page.items.isEmpty
        ? const _EmptyReports()
        : ListView.builder(
            key: const PageStorageKey<String>('student-report-list'),
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
              final report = page.items[index];
              return Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: _ReportCard(report: report, onTap: () => onOpen(report)),
              );
            },
          ),
  );
}

class _ReportCard extends StatelessWidget {
  const _ReportCard({required this.report, required this.onTap});

  final StudentReportItem report;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(report.verificationStatus);
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        key: Key('student-report-${report.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.11),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(
                      report.type == 'pelanggaran'
                          ? Icons.gavel_rounded
                          : report.type == 'pembinaan'
                          ? Icons.support_agent_rounded
                          : Icons.campaign_rounded,
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
                          '${report.number} · ${_dateLabel(report.incidentDate)}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
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
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  _Chip(label: report.typeLabel, color: NusaColors.primary),
                  _Chip(
                    label: report.verificationStatusLabel,
                    color: statusColor,
                  ),
                  if (report.totalPoints > 0)
                    _Chip(
                      label: '${report.totalPoints} poin',
                      color: Colors.deepOrange,
                    ),
                ],
              ),
              const SizedBox(height: 9),
              Row(
                children: [
                  const Icon(
                    Icons.class_outlined,
                    size: 16,
                    color: NusaColors.textSecondary,
                  ),
                  const SizedBox(width: 5),
                  Expanded(
                    child: Text(
                      '${report.schoolClass?.name ?? 'Belum ada kelas'} · ${report.place ?? 'Tempat belum dicatat'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 10.5),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  _Count(icon: Icons.attach_file, value: report.evidenceCount),
                  const SizedBox(width: 12),
                  _Count(
                    icon: Icons.visibility_outlined,
                    value: report.witnessCount,
                  ),
                  const SizedBox(width: 12),
                  _Count(
                    icon: Icons.follow_the_signs_rounded,
                    value: report.followUpCount,
                  ),
                ],
              ),
              if (report.deadline.at != null) ...[
                const SizedBox(height: 5),
                Text(
                  report.deadline.overdue
                      ? 'Tenggat terlewat · ${report.deadline.stageLabel ?? ''}'
                      : '${report.deadline.stageLabel ?? 'Tenggat'} · ${_shortDateTime(report.deadline.at!)}',
                  style: TextStyle(
                    color: report.deadline.overdue
                        ? Colors.red
                        : NusaColors.textSecondary,
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
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

class _Count extends StatelessWidget {
  const _Count({required this.icon, required this.value});
  final IconData icon;
  final int value;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, size: 15, color: NusaColors.textSecondary),
      const SizedBox(width: 3),
      Text('$value', style: const TextStyle(fontSize: 9.5)),
    ],
  );
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w700),
    ),
  );
}

class _ReportFilterSheet extends StatefulWidget {
  const _ReportFilterSheet({required this.page});
  final StudentReportPage page;

  @override
  State<_ReportFilterSheet> createState() => _ReportFilterSheetState();
}

class _ReportFilterSheetState extends State<_ReportFilterSheet> {
  late String _status = widget.page.filter.status;
  late String _level = widget.page.filter.level;
  late String _type = widget.page.filter.type;
  late int? _academicYearId = widget.page.filter.academicYearId;
  late int? _classId = widget.page.filter.classId;

  @override
  Widget build(BuildContext context) {
    final classes = widget.page.options.classes
        .where(
          (item) =>
              _academicYearId == null || item.academicYearId == _academicYearId,
        )
        .toList(growable: false);
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        12,
        16,
        16 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Filter Laporan',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const SizedBox(height: 6),
            NusaDropdownField<String>(
              fieldKey: const Key('student-report-type-filter'),
              value: _type,
              decoration: const InputDecoration(labelText: 'Jenis laporan'),
              options: [
                const NusaDropdownOption(value: 'semua', label: 'Semua jenis'),
                for (final option in widget.page.options.types)
                  NusaDropdownOption(value: option.code, label: option.label),
              ],
              onChanged: (value) {
                if (value != null) setState(() => _type = value);
              },
            ),
            const SizedBox(height: 9),
            Row(
              children: [
                Expanded(
                  child: NusaDropdownField<String>(
                    fieldKey: const Key('student-report-status-filter'),
                    value: _status,
                    decoration: const InputDecoration(
                      labelText: 'Status laporan',
                    ),
                    options: [
                      const NusaDropdownOption(
                        value: 'semua',
                        label: 'Semua status',
                      ),
                      for (final option in widget.page.options.statuses)
                        NusaDropdownOption(
                          value: option.code,
                          label: option.label,
                        ),
                    ],
                    onChanged: (value) {
                      if (value != null) setState(() => _status = value);
                    },
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: NusaDropdownField<String>(
                    fieldKey: const Key('student-report-level-filter'),
                    value: _level,
                    decoration: const InputDecoration(labelText: 'Tingkat'),
                    options: [
                      const NusaDropdownOption(
                        value: 'semua',
                        label: 'Semua tingkat',
                      ),
                      for (final option in widget.page.options.levels)
                        NusaDropdownOption(
                          value: option.code,
                          label: option.label,
                        ),
                    ],
                    onChanged: (value) {
                      if (value != null) setState(() => _level = value);
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 9),
            NusaDropdownField<int?>(
              fieldKey: const Key('student-report-year-filter'),
              value: _academicYearId,
              decoration: const InputDecoration(labelText: 'Tahun pelajaran'),
              options: [
                const NusaDropdownOption<int?>(
                  value: null,
                  label: 'Semua tahun pelajaran',
                ),
                for (final year in widget.page.options.academicYears)
                  NusaDropdownOption<int?>(
                    value: year.id,
                    label: '${year.name}${year.active ? ' · Aktif' : ''}',
                  ),
              ],
              onChanged: (value) => setState(() {
                _academicYearId = value;
                _classId = null;
              }),
            ),
            const SizedBox(height: 9),
            NusaDropdownField<int?>(
              fieldKey: const Key('student-report-class-filter'),
              value: _classId,
              decoration: const InputDecoration(labelText: 'Kelas'),
              options: [
                const NusaDropdownOption<int?>(
                  value: null,
                  label: 'Semua kelas',
                ),
                for (final schoolClass in classes)
                  NusaDropdownOption<int?>(
                    value: schoolClass.id,
                    label: schoolClass.name,
                  ),
              ],
              onChanged: (value) => setState(() => _classId = value),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    key: const Key('reset-student-report-filters'),
                    onPressed: () => Navigator.pop(
                      context,
                      const _StudentReportFilterValue.reset(),
                    ),
                    child: const Text('Reset'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 2,
                  child: FilledButton(
                    key: const Key('apply-student-report-filters'),
                    onPressed: () => Navigator.pop(
                      context,
                      _StudentReportFilterValue(
                        status: _status,
                        level: _level,
                        type: _type,
                        academicYearId: _academicYearId,
                        classId: _classId,
                      ),
                    ),
                    child: const Text('Terapkan Filter'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _StudentReportFilterValue {
  const _StudentReportFilterValue({
    required this.status,
    required this.level,
    required this.type,
    required this.academicYearId,
    required this.classId,
  }) : reset = false;

  const _StudentReportFilterValue.reset()
    : status = 'semua',
      level = 'semua',
      type = 'semua',
      academicYearId = null,
      classId = null,
      reset = true;

  final String status;
  final String level;
  final String type;
  final int? academicYearId;
  final int? classId;
  final bool reset;
}

class _EmptyReports extends StatelessWidget {
  const _EmptyReports();

  @override
  Widget build(BuildContext context) => ListView(
    physics: const AlwaysScrollableScrollPhysics(),
    padding: const EdgeInsets.all(30),
    children: const [
      Icon(Icons.assignment_outlined, size: 50, color: NusaColors.primary),
      SizedBox(height: 10),
      Text(
        'Tidak ada laporan dalam cakupan dan filter ini.',
        textAlign: TextAlign.center,
      ),
    ],
  );
}

class _ReportError extends StatelessWidget {
  const _ReportError({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;

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
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

int _activeFilterCount(StudentReportFilter filter) => [
  filter.status != 'semua',
  filter.level != 'semua',
  filter.type != 'semua',
  filter.academicYearId != null,
  filter.classId != null,
].where((active) => active).length;

Color _statusColor(String status) => switch (status) {
  'disahkan' || 'ditetapkan_pembinaan' => NusaColors.success,
  'tidak_terbukti' => Colors.teal,
  'dibatalkan' => Colors.grey,
  'menunggu_pengesahan_wakil' => Colors.deepOrange,
  'perlu_klarifikasi' || 'dikembalikan_bk' => Colors.amber.shade800,
  _ => NusaColors.primary,
};

String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  return date == null
      ? value
      : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _shortDateTime(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';

String _errorMessage(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Daftar laporan siswa belum dapat dimuat.',
};
