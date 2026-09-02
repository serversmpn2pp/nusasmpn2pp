import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_early_warning/application/student_early_warning_controller.dart';
import 'package:nusa/features/student_early_warning/domain/student_early_warning.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentEarlyWarningListView extends ConsumerStatefulWidget {
  const StudentEarlyWarningListView({super.key});

  @override
  ConsumerState<StudentEarlyWarningListView> createState() =>
      _StudentEarlyWarningListViewState();
}

class _StudentEarlyWarningListViewState
    extends ConsumerState<StudentEarlyWarningListView> {
  final _searchController = TextEditingController();
  bool _loadingMore = false;
  bool _processing = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(studentEarlyWarningControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Peringatan Dini Siswa'),
        actions: [
          if (current?.access.canProcess == true)
            IconButton(
              key: const Key('student-warning-process'),
              tooltip: 'Jalankan deteksi',
              onPressed: state.isLoading || _processing ? null : _process,
              icon: _processing
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2.2),
                    )
                  : const Icon(Icons.manage_search_rounded),
            ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading ? null : _refresh,
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
                      key: const Key('student-warning-search'),
                      controller: _searchController,
                      enabled: !state.isLoading,
                      onChanged: ref
                          .read(studentEarlyWarningControllerProvider.notifier)
                          .search,
                      decoration: const InputDecoration(
                        hintText: 'Cari nama, NIS, atau NISN',
                        prefixIcon: Icon(Icons.search_rounded),
                      ),
                    ),
                    const SizedBox(height: 8),
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final type = NusaDropdownField<String>(
                          fieldKey: const Key('student-warning-type-filter'),
                          value: current.filter.type,
                          enabled: !state.isLoading,
                          decoration: const InputDecoration(
                            labelText: 'Jenis',
                            prefixIcon: Icon(Icons.warning_amber_rounded),
                          ),
                          options: [
                            const NusaDropdownOption(
                              value: 'semua',
                              label: 'Semua jenis',
                            ),
                            for (final item in current.options.types)
                              NusaDropdownOption(
                                value: item.code,
                                label: item.label,
                              ),
                          ],
                          onChanged: (value) {
                            if (value != null) {
                              ref
                                  .read(
                                    studentEarlyWarningControllerProvider
                                        .notifier,
                                  )
                                  .filterType(value);
                            }
                          },
                        );
                        final filter = OutlinedButton.icon(
                          key: const Key('student-warning-open-filter'),
                          onPressed: state.isLoading
                              ? null
                              : () => _showFilters(current),
                          icon: const Icon(Icons.tune_rounded),
                          label: const Text('Filter'),
                        );
                        if (constraints.maxWidth < 330) {
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              type,
                              const SizedBox(height: 8),
                              SizedBox(height: 48, child: filter),
                            ],
                          );
                        }
                        return Row(
                          children: [
                            Expanded(child: type),
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
      ref.read(studentEarlyWarningControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(studentEarlyWarningControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _open(StudentEarlyWarningItem item) =>
      context.push('/peringatan-dini-siswa/${item.id}').then((_) => _refresh());

  Future<void> _process() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Jalankan deteksi peringatan?'),
        content: const Text(
          'NUSA akan menghitung ulang kondisi siswa pada tahun pelajaran yang dipilih. Peringatan dapat dibuat, diperbarui, atau diselesaikan otomatis.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('student-warning-confirm-process'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Jalankan'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _processing = true);
    try {
      final result = await ref
          .read(studentEarlyWarningControllerProvider.notifier)
          .process();
      if (mounted) _snack(result.message);
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

  Future<void> _showFilters(StudentEarlyWarningPage page) async {
    final result =
        await showModalBottomSheet<
          ({String level, String status, int? yearId, int? classId})
        >(
          context: context,
          isScrollControlled: true,
          useSafeArea: true,
          builder: (context) => _FilterSheet(page: page),
        );
    if (result == null) return;
    final notifier = ref.read(studentEarlyWarningControllerProvider.notifier);
    await notifier.applyFilters(
      level: result.level,
      status: result.status,
      academicYearId: result.yearId,
      classId: result.classId,
    );
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final StudentEarlyWarningSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    padding: const EdgeInsets.all(12),
    child: Column(
      children: [
        Row(
          children: [
            _Stat(label: 'Aktif', value: summary.active, prominent: true),
            _Stat(label: 'Penting', value: summary.important),
            _Stat(label: 'Dekat Sanksi', value: summary.nearSanction),
          ],
        ),
        const SizedBox(height: 9),
        Row(
          children: [
            _Stat(label: 'Pola Berulang', value: summary.repeatedPattern),
            _Stat(label: 'Sanksi Aktif', value: summary.activeSanction),
          ],
        ),
      ],
    ),
  );
}

class _Stat extends StatelessWidget {
  const _Stat({
    required this.label,
    required this.value,
    this.prominent = false,
  });
  final String label;
  final int value;
  final bool prominent;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: prominent ? NusaColors.accent : Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          textAlign: TextAlign.center,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: Colors.white70, fontSize: 8.5),
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
  final StudentEarlyWarningPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;
  final ValueChanged<StudentEarlyWarningItem> onOpen;

  @override
  Widget build(BuildContext context) => page.items.isEmpty
      ? RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(28, 44, 28, 100),
            children: const [
              Icon(
                Icons.health_and_safety_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Tidak ada peringatan pada filter ini.',
                textAlign: TextAlign.center,
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              SizedBox(height: 5),
              Text(
                'Tarik layar untuk memperbarui data dari server.',
                textAlign: TextAlign.center,
                style: TextStyle(color: NusaColors.textSecondary, fontSize: 11),
              ),
            ],
          ),
        )
      : RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 3, 16, 100),
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
              return Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: _WarningCard(
                  item: page.items[index],
                  onTap: () => onOpen(page.items[index]),
                ),
              );
            },
          ),
        );
}

class _WarningCard extends StatelessWidget {
  const _WarningCard({required this.item, required this.onTap});
  final StudentEarlyWarningItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final important = item.level == 'penting';
    final active = item.status == 'aktif';
    final color = !active
        ? NusaColors.textSecondary
        : important
        ? const Color(0xFFD84A3A)
        : const Color(0xFFC58F00);
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(17),
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
                child: Icon(_typeIcon(item.type), color: color),
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
                        _Badge(label: item.levelLabel, color: color),
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
                      item.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      item.message,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 10, height: 1.35),
                    ),
                    const SizedBox(height: 7),
                    Row(
                      children: [
                        Icon(
                          item.activeAssistance == null
                              ? Icons.pending_actions_rounded
                              : Icons.handshake_rounded,
                          size: 14,
                          color: NusaColors.textSecondary,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            item.activeAssistance == null
                                ? 'Belum ada pendampingan aktif'
                                : 'Pendampingan sedang berjalan',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 9.5,
                            ),
                          ),
                        ),
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

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.12),
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
  final StudentEarlyWarningPage page;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late String _level = widget.page.filter.level;
  late String _status = widget.page.filter.status;
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
    return SingleChildScrollView(
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
            'Filter Peringatan',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 14),
          NusaDropdownField<String>(
            fieldKey: const Key('student-warning-level-filter'),
            value: _level,
            decoration: const InputDecoration(labelText: 'Tingkat'),
            options: [
              const NusaDropdownOption(value: 'semua', label: 'Semua tingkat'),
              for (final item in widget.page.options.levels)
                NusaDropdownOption(value: item.code, label: item.label),
            ],
            onChanged: (value) => setState(() => _level = value ?? 'semua'),
          ),
          const SizedBox(height: 10),
          NusaDropdownField<String>(
            fieldKey: const Key('student-warning-status-filter'),
            value: _status,
            decoration: const InputDecoration(labelText: 'Status'),
            options: [
              const NusaDropdownOption(value: 'semua', label: 'Semua status'),
              for (final item in widget.page.options.statuses)
                NusaDropdownOption(value: item.code, label: item.label),
            ],
            onChanged: (value) => setState(() => _status = value ?? 'aktif'),
          ),
          const SizedBox(height: 10),
          NusaDropdownField<int?>(
            fieldKey: const Key('student-warning-year-filter'),
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
          const SizedBox(height: 10),
          NusaDropdownField<int?>(
            fieldKey: const Key('student-warning-class-filter'),
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
            key: const Key('student-warning-apply-filter'),
            onPressed: () => context.pop((
              level: _level,
              status: _status,
              yearId: _yearId,
              classId: _classId,
            )),
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

IconData _typeIcon(String type) => switch (type) {
  'mendekati_sanksi' => Icons.speed_rounded,
  'pelanggaran_berulang' => Icons.repeat_rounded,
  'sering_terlambat' => Icons.more_time_rounded,
  'sanksi_belum_selesai' => Icons.gavel_rounded,
  _ => Icons.warning_amber_rounded,
};

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Peringatan dini siswa belum dapat dimuat.',
};
