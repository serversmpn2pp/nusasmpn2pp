import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_point_recap/application/student_point_recap_controller.dart';
import 'package:nusa/features/student_point_recap/domain/student_point_recap.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentPointRecapListView extends ConsumerStatefulWidget {
  const StudentPointRecapListView({super.key});

  @override
  ConsumerState<StudentPointRecapListView> createState() =>
      _StudentPointRecapListViewState();
}

class _StudentPointRecapListViewState
    extends ConsumerState<StudentPointRecapListView> {
  final _searchController = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(studentPointRecapControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Rekap Poin Siswa'),
        actions: [
          if (current?.classSummaries.isNotEmpty == true)
            IconButton(
              key: const Key('point-recap-class-summary'),
              tooltip: 'Ringkasan kelas',
              onPressed: () => _showClassSummary(current!),
              icon: const Icon(Icons.bar_chart_rounded),
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
                      key: const Key('point-recap-search'),
                      controller: _searchController,
                      enabled: !state.isLoading,
                      onChanged: ref
                          .read(studentPointRecapControllerProvider.notifier)
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
                          fieldKey: const Key('point-recap-status-filter'),
                          value: current.filter.attentionStatus,
                          enabled: !state.isLoading,
                          decoration: const InputDecoration(
                            labelText: 'Perhatian',
                            prefixIcon: Icon(Icons.filter_alt_rounded),
                          ),
                          options: [
                            const NusaDropdownOption(
                              value: 'semua',
                              label: 'Semua siswa',
                            ),
                            for (final item
                                in current.options.attentionStatuses)
                              NusaDropdownOption(
                                value: item.code,
                                label: item.label,
                              ),
                          ],
                          onChanged: (value) {
                            if (value != null) {
                              ref
                                  .read(
                                    studentPointRecapControllerProvider
                                        .notifier,
                                  )
                                  .filterAttention(value);
                            }
                          },
                        );
                        final filter = OutlinedButton.icon(
                          key: const Key('point-recap-open-filter'),
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
      ref.read(studentPointRecapControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(studentPointRecapControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _open(StudentPointRecapItem item) {
    final page = ref.read(studentPointRecapControllerProvider).value;
    final query = <String, String>{};
    if (page?.filter.academicYearId != null) {
      query['tahun'] = '${page!.filter.academicYearId}';
    }
    context
        .push(
          Uri(
            path: '/rekap-poin-siswa/${item.student.id}',
            queryParameters: query,
          ).toString(),
        )
        .then((_) => _refresh());
  }

  Future<void> _showFilters(StudentPointRecapPage page) async {
    final result = await showModalBottomSheet<({int? yearId, int? classId})>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _FilterSheet(page: page),
    );
    if (result == null) return;
    await ref
        .read(studentPointRecapControllerProvider.notifier)
        .applyFilters(academicYearId: result.yearId, classId: result.classId);
  }

  void _showClassSummary(StudentPointRecapPage page) {
    showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => _ClassSummarySheet(items: page.classSummaries),
    );
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final StudentPointRecapSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Column(
      children: [
        Row(
          children: [
            _Stat(label: 'Siswa', value: summary.students, accent: true),
            _Stat(label: 'Berpoin', value: summary.withPoints),
            _Stat(label: 'Dekat Sanksi', value: summary.nearSanction),
          ],
        ),
        const SizedBox(height: 9),
        Row(
          children: [
            _Stat(label: 'Menunggu', value: summary.pendingReports),
            _Stat(label: 'Sanksi Aktif', value: summary.activeSanctions),
          ],
        ),
      ],
    ),
  );
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value, this.accent = false});
  final String label;
  final int value;
  final bool accent;
  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: accent ? NusaColors.accent : Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
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
  final StudentPointRecapPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;
  final ValueChanged<StudentPointRecapItem> onOpen;

  @override
  Widget build(BuildContext context) => page.items.isEmpty
      ? RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(28, 44, 28, 100),
            children: const [
              Icon(
                Icons.score_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Tidak ada siswa pada filter ini.',
                textAlign: TextAlign.center,
                style: TextStyle(fontWeight: FontWeight.w800),
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
                child: _StudentCard(
                  item: page.items[index],
                  onTap: () => onOpen(page.items[index]),
                ),
              );
            },
          ),
        );
}

class _StudentCard extends StatelessWidget {
  const _StudentCard({required this.item, required this.onTap});
  final StudentPointRecapItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = _indicatorColor(item.indicator.code);
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
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Center(
                  child: Text(
                    '${item.totalPoints}',
                    style: TextStyle(
                      color: color,
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.student.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${item.schoolClass?.name ?? 'Tanpa kelas'} · NISN ${item.student.nisn ?? '-'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                    const SizedBox(height: 8),
                    _Badge(label: item.indicator.label, color: color),
                    const SizedBox(height: 8),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        minHeight: 5,
                        value: (item.indicator.percentage / 100).clamp(0, 1),
                        backgroundColor: NusaColors.outline,
                        color: color,
                      ),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      '${item.pendingReports} laporan menunggu · ${item.activeSanctions} sanksi aktif',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 9.5,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, size: 20),
            ],
          ),
        ),
      ),
    );
  }
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.page});
  final StudentPointRecapPage page;
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
            fieldKey: const Key('point-recap-year-filter'),
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
            fieldKey: const Key('point-recap-class-filter'),
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
            key: const Key('point-recap-apply-filter'),
            onPressed: () => context.pop((yearId: _yearId, classId: _classId)),
            child: const Text('Terapkan Filter'),
          ),
        ],
      ),
    );
  }
}

class _ClassSummarySheet extends StatelessWidget {
  const _ClassSummarySheet({required this.items});
  final List<StudentPointClassSummary> items;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Ringkasan Per Kelas',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        Flexible(
          child: ListView.separated(
            shrinkWrap: true,
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final item = items[index];
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.schoolClass.name,
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            Text(
                              '${item.withPoints}/${item.students} siswa berpoin · ${item.pending} menunggu',
                              style: const TextStyle(
                                color: NusaColors.textSecondary,
                                fontSize: 10,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        '${item.totalPoints} poin',
                        style: const TextStyle(
                          color: NusaColors.primary,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    ),
  );
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800),
    ),
  );
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

Color _indicatorColor(String code) => switch (code) {
  'sanksi_aktif' || 'ambang_tertinggi' => const Color(0xFFD84A3A),
  'mendekati_sanksi' || 'menunggu_verifikasi' => const Color(0xFFC58F00),
  'terpantau' => NusaColors.primaryLight,
  _ => NusaColors.success,
};

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Rekap poin siswa belum dapat dimuat.',
};
