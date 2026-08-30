import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_sanction/application/student_sanction_controller.dart';
import 'package:nusa/features/student_sanction/domain/student_sanction.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentSanctionListView extends ConsumerStatefulWidget {
  const StudentSanctionListView({super.key});

  @override
  ConsumerState<StudentSanctionListView> createState() =>
      _StudentSanctionListViewState();
}

class _StudentSanctionListViewState
    extends ConsumerState<StudentSanctionListView> {
  final _searchController = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(studentSanctionControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pelaksanaan Sanksi Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(studentSanctionControllerProvider.notifier).refresh,
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
                    _Summary(summary: current.summary),
                    const SizedBox(height: 9),
                    TextField(
                      key: const Key('student-sanction-search'),
                      controller: _searchController,
                      enabled: !state.isLoading,
                      onChanged: ref
                          .read(studentSanctionControllerProvider.notifier)
                          .search,
                      decoration: const InputDecoration(
                        hintText: 'Cari siswa, NIS/NISN, atau jenis sanksi',
                        prefixIcon: Icon(Icons.search_rounded),
                      ),
                    ),
                    const SizedBox(height: 8),
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final status = NusaDropdownField<String>(
                          fieldKey: const Key('student-sanction-status-filter'),
                          value: current.filter.status,
                          enabled: !state.isLoading,
                          decoration: const InputDecoration(
                            labelText: 'Status',
                            prefixIcon: Icon(Icons.flag_rounded),
                          ),
                          options: [
                            for (final item in current.options.statuses)
                              NusaDropdownOption(
                                value: item.code,
                                label: item.label,
                              ),
                          ],
                          onChanged: (value) {
                            if (value != null) {
                              ref
                                  .read(
                                    studentSanctionControllerProvider.notifier,
                                  )
                                  .filterStatus(value);
                            }
                          },
                        );
                        final filter = OutlinedButton.icon(
                          key: const Key('student-sanction-open-filter'),
                          onPressed: state.isLoading
                              ? null
                              : () => _showFilters(current),
                          icon: const Icon(Icons.tune_rounded),
                          label: const Text('Tahun/Kelas'),
                        );
                        if (constraints.maxWidth < 330) {
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              status,
                              const SizedBox(height: 8),
                              SizedBox(height: 48, child: filter),
                            ],
                          );
                        }
                        return Row(
                          children: [
                            Expanded(child: status),
                            const SizedBox(width: 8),
                            SizedBox(height: 56, child: filter),
                          ],
                        );
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
                        onOpen: _open,
                      ),
                error: (error, stackTrace) =>
                    _Error(message: _message(error), onRetry: _refresh),
                data: (page) => _Results(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: _refresh,
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

  Future<void> _refresh() =>
      ref.read(studentSanctionControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(studentSanctionControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _open(StudentSanctionItem item) => context
      .push('/pelaksanaan-sanksi-siswa/${item.id}')
      .then((_) => _refresh());

  Future<void> _showFilters(StudentSanctionPage page) async {
    final result = await showModalBottomSheet<({int? yearId, int? classId})>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _FilterSheet(page: page),
    );
    if (result == null) return;
    await ref
        .read(studentSanctionControllerProvider.notifier)
        .applyFilters(academicYearId: result.yearId, classId: result.classId);
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final StudentSanctionSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _Stat(label: 'Aktif', value: summary.active),
        _Stat(label: 'Menunggu', value: summary.waiting),
        _Stat(label: 'Diproses', value: summary.inProgress),
        _Stat(label: 'Terlambat', value: summary.overdue, alert: true),
        _Stat(label: 'Selesai', value: summary.completed),
      ],
    ),
  );
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value, this.alert = false});
  final String label;
  final int value;
  final bool alert;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: alert && value > 0 ? NusaColors.accent : Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.7),
            fontSize: 8,
          ),
        ),
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
    required this.onOpen,
  });
  final StudentSanctionPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<StudentSanctionItem> onOpen;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: page.items.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: const [
              SizedBox(height: 110),
              Icon(Icons.gavel_rounded, size: 52),
              SizedBox(height: 10),
              Center(child: Text('Belum ada sanksi sesuai filter.')),
              SizedBox(height: 5),
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 28),
                child: Text(
                  'Sanksi dibuat otomatis saat poin siswa mencapai batas aturan.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
              ),
            ],
          )
        : ListView.builder(
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
              final item = page.items[index];
              return Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: _SanctionCard(item: item, onTap: () => onOpen(item)),
              );
            },
          ),
  );
}

class _SanctionCard extends StatelessWidget {
  const _SanctionCard({required this.item, required this.onTap});
  final StudentSanctionItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(item.status, item.overdue);
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        key: Key('student-sanction-item-${item.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 43,
                height: 43,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(Icons.gavel_rounded, color: color),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            item.student.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w900),
                          ),
                        ),
                        _Status(
                          label: item.overdue ? 'Terlambat' : item.statusLabel,
                          color: color,
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${item.schoolClass?.name ?? 'Tanpa kelas'} · ${item.triggerPoints} poin',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      item.rule.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 11, height: 1.35),
                    ),
                    const SizedBox(height: 7),
                    Row(
                      children: [
                        const Icon(Icons.person_outline_rounded, size: 14),
                        const SizedBox(width: 3),
                        Expanded(
                          child: Text(
                            item.officer?.name ?? 'Petugas belum ditentukan',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 9.5,
                            ),
                          ),
                        ),
                        if (item.deadline != null) ...[
                          const SizedBox(width: 5),
                          Icon(
                            Icons.event_rounded,
                            size: 13,
                            color: item.overdue ? Colors.red : null,
                          ),
                          const SizedBox(width: 2),
                          Text(
                            _dateLabel(item.deadline!),
                            style: TextStyle(
                              color: item.overdue
                                  ? Colors.red
                                  : NusaColors.textSecondary,
                              fontSize: 9,
                              fontWeight: item.overdue
                                  ? FontWeight.w800
                                  : FontWeight.normal,
                            ),
                          ),
                        ],
                        if (item.evidenceCount > 0) ...[
                          const SizedBox(width: 7),
                          const Icon(Icons.attach_file_rounded, size: 13),
                          Text(
                            '${item.evidenceCount}',
                            style: const TextStyle(fontSize: 9),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 2),
              const Icon(Icons.chevron_right_rounded, size: 20),
            ],
          ),
        ),
      ),
    );
  }
}

class _Status extends StatelessWidget {
  const _Status({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.13),
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: color,
        fontSize: 8.5,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.page});
  final StudentSanctionPage page;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late int? _yearId = widget.page.filter.academicYearId;
  late int? _classId = widget.page.filter.classId;

  @override
  Widget build(BuildContext context) {
    final classes = widget.page.options.classes
        .where((item) => _yearId == null || item.academicYearId == _yearId)
        .toList();
    if (_classId != null && !classes.any((item) => item.id == _classId)) {
      _classId = null;
    }
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        16,
        16,
        16 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Filter Tahun dan Kelas',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 14),
          NusaDropdownField<int?>(
            fieldKey: const Key('student-sanction-year-filter'),
            value: _yearId,
            decoration: const InputDecoration(labelText: 'Tahun pelajaran'),
            options: [
              const NusaDropdownOption(value: null, label: 'Tahun aktif'),
              for (final item in widget.page.options.academicYears)
                NusaDropdownOption(
                  value: item.id,
                  label: '${item.name}${item.active ? ' · Aktif' : ''}',
                ),
            ],
            onChanged: (value) => setState(() {
              _yearId = value;
              _classId = null;
            }),
          ),
          const SizedBox(height: 11),
          NusaDropdownField<int?>(
            fieldKey: const Key('student-sanction-class-filter'),
            value: _classId,
            decoration: const InputDecoration(labelText: 'Kelas'),
            options: [
              const NusaDropdownOption(value: null, label: 'Semua kelas'),
              for (final item in classes)
                NusaDropdownOption(value: item.id, label: item.name),
            ],
            onChanged: (value) => setState(() => _classId = value),
          ),
          const SizedBox(height: 16),
          FilledButton(
            key: const Key('student-sanction-apply-filter'),
            onPressed: () => context.pop((yearId: _yearId, classId: _classId)),
            child: const Text('Terapkan Filter'),
          ),
        ],
      ),
    );
  }
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.onRetry});
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
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

Color _statusColor(String status, bool overdue) {
  if (overdue) return Colors.red;
  return switch (status) {
    'selesai' => NusaColors.success,
    'diproses' => NusaColors.primary,
    'dibatalkan' => NusaColors.textSecondary,
    _ => const Color(0xFFC58F00),
  };
}

String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  return date == null
      ? value
      : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Data pelaksanaan sanksi belum dapat dimuat.',
};
