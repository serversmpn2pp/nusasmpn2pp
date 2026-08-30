import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_assistance/application/student_assistance_controller.dart';
import 'package:nusa/features/student_assistance/domain/student_assistance.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentAssistanceListView extends ConsumerStatefulWidget {
  const StudentAssistanceListView({super.key});

  @override
  ConsumerState<StudentAssistanceListView> createState() =>
      _StudentAssistanceListViewState();
}

class _StudentAssistanceListViewState
    extends ConsumerState<StudentAssistanceListView> {
  final _searchController = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(studentAssistanceControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pendampingan Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref
                      .read(studentAssistanceControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('student-assistance-add'),
              onPressed: () => _openCreate(current!),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Mulai'),
            )
          : null,
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
                      key: const Key('student-assistance-search'),
                      controller: _searchController,
                      enabled: !state.isLoading,
                      onChanged: ref
                          .read(studentAssistanceControllerProvider.notifier)
                          .search,
                      decoration: const InputDecoration(
                        hintText: 'Cari nama, NIS, atau NISN',
                        prefixIcon: Icon(Icons.search_rounded),
                      ),
                    ),
                    const SizedBox(height: 8),
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final status = NusaDropdownField<String>(
                          fieldKey: const Key(
                            'student-assistance-status-filter',
                          ),
                          value: current.filter.status,
                          enabled: !state.isLoading,
                          decoration: const InputDecoration(
                            labelText: 'Status',
                            prefixIcon: Icon(Icons.flag_rounded),
                          ),
                          options: [
                            const NusaDropdownOption(
                              value: 'semua',
                              label: 'Semua status',
                            ),
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
                                    studentAssistanceControllerProvider
                                        .notifier,
                                  )
                                  .filterStatus(value);
                            }
                          },
                        );
                        final filter = OutlinedButton.icon(
                          key: const Key('student-assistance-open-filter'),
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
      ref.read(studentAssistanceControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(studentAssistanceControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_message(error))));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _open(StudentAssistanceItem item) =>
      context.push('/pendampingan-siswa/${item.id}').then((_) => _refresh());

  void _openCreate(StudentAssistancePage page) {
    final query = <String, String>{};
    if (page.filter.academicYearId != null) {
      query['tahun'] = '${page.filter.academicYearId}';
    }
    if (page.filter.classId != null) query['kelas'] = '${page.filter.classId}';
    context
        .push(
          Uri(
            path: '/pendampingan-siswa/tambah',
            queryParameters: query,
          ).toString(),
        )
        .then((_) => _refresh());
  }

  Future<void> _showFilters(StudentAssistancePage page) async {
    final result = await showModalBottomSheet<({int? yearId, int? classId})>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _FilterSheet(page: page),
    );
    if (result == null) return;
    await ref
        .read(studentAssistanceControllerProvider.notifier)
        .applyFilters(academicYearId: result.yearId, classId: result.classId);
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final StudentAssistanceSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _Stat(label: 'Total', value: summary.total),
        _Stat(label: 'Dalam Proses', value: summary.inProgress),
        _Stat(label: 'Selesai', value: summary.completed),
      ],
    ),
  );
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});
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
            fontSize: 19,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.7),
            fontSize: 9,
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
  final StudentAssistancePage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<StudentAssistanceItem> onOpen;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: page.items.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: const [
              SizedBox(height: 120),
              Icon(Icons.support_agent_rounded, size: 52),
              SizedBox(height: 10),
              Center(child: Text('Belum ada pendampingan sesuai filter.')),
            ],
          )
        : ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 3, 16, 88),
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
                child: _Card(item: item, onTap: () => onOpen(item)),
              );
            },
          ),
  );
}

class _Card extends StatelessWidget {
  const _Card({required this.item, required this.onTap});
  final StudentAssistanceItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final completed = item.status == 'selesai';
    final color = completed ? NusaColors.success : NusaColors.accent;
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.13),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  completed ? Icons.task_alt_rounded : Icons.handshake_rounded,
                  color: color,
                ),
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
                        _Status(label: item.statusLabel, color: color),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${item.schoolClass?.name ?? 'Tanpa kelas'} · ${item.typeLabel}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      _filled(item.result) ? item.result! : item.note,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 10.5, height: 1.35),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      '${_dateLabel(item.date)} · ${item.officer?.name ?? 'Petugas belum ditentukan'}',
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
              const SizedBox(width: 3),
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
  final StudentAssistancePage page;
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
            fieldKey: const Key('student-assistance-year-filter'),
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
            fieldKey: const Key('student-assistance-class-filter'),
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
            key: const Key('student-assistance-apply-filter'),
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

bool _filled(String? value) => value != null && value.trim().isNotEmpty;
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
  _ => 'Data pendampingan belum dapat dimuat.',
};
