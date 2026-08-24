import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/school_class/application/school_class_controller.dart';
import 'package:nusa/features/school_class/domain/school_class.dart';
import 'package:nusa/features/school_class/presentation/widgets/school_class_components.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class SchoolClassListView extends ConsumerStatefulWidget {
  const SchoolClassListView({this.scheduleMode = false, super.key});

  final bool scheduleMode;

  @override
  ConsumerState<SchoolClassListView> createState() =>
      _SchoolClassListViewState();
}

class _SchoolClassListViewState extends ConsumerState<SchoolClassListView> {
  final _searchController = TextEditingController();
  Timer? _searchDebounce;
  bool _loadingMore = false;

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    setState(() {});
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) {
        ref.read(schoolClassListControllerProvider.notifier).search(value);
      }
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) {
      return;
    }

    setState(() => _loadingMore = true);
    try {
      await ref.read(schoolClassListControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_errorMessage(error))));
      }
    } finally {
      if (mounted) {
        setState(() => _loadingMore = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final classes = ref.watch(schoolClassListControllerProvider);
    final current = classes.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: Text(widget.scheduleMode ? 'Jadwal Pelajaran' : 'Data Kelas'),
        actions: [
          IconButton(
            onPressed: classes.isLoading
                ? null
                : () => ref
                      .read(schoolClassListControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Perbarui',
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 10),
              child: Column(
                children: [
                  if (current != null) ...[
                    SchoolClassSummaryStrip(counts: current.counts),
                    const SizedBox(height: 12),
                  ],
                  NusaTextField(
                    fieldKey: const Key('class-search'),
                    controller: _searchController,
                    hintText: 'Cari kelas atau nama wali kelas',
                    prefixIcon: Icons.search_rounded,
                    textInputAction: TextInputAction.search,
                    onChanged: _onSearchChanged,
                    onFieldSubmitted: (value) {
                      _searchDebounce?.cancel();
                      ref
                          .read(schoolClassListControllerProvider.notifier)
                          .search(value);
                    },
                    suffixIcon: _searchController.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                              ref
                                  .read(
                                    schoolClassListControllerProvider.notifier,
                                  )
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                            tooltip: 'Hapus pencarian',
                          ),
                  ),
                  if (current?.academicYears.isNotEmpty == true) ...[
                    const SizedBox(height: 10),
                    _AcademicYearFilter(
                      key: ValueKey(current!.academicYearId),
                      years: current.academicYears,
                      selectedId: current.academicYearId,
                      enabled: !classes.isLoading,
                      onSelected: (yearId) => ref
                          .read(schoolClassListControllerProvider.notifier)
                          .filterAcademicYear(yearId),
                    ),
                  ],
                  const SizedBox(height: 10),
                  _StatusFilters(
                    selected: current?.status ?? 'semua',
                    enabled: !classes.isLoading,
                    onSelected: (status) => ref
                        .read(schoolClassListControllerProvider.notifier)
                        .filterStatus(status),
                  ),
                ],
              ),
            ),
            Expanded(
              child: classes.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _ClassErrorState(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(schoolClassListControllerProvider.notifier)
                      .refresh(),
                ),
                data: (page) => _ClassResults(
                  page: page,
                  scheduleMode: widget.scheduleMode,
                  loadingMore: _loadingMore,
                  onRefresh: () => ref
                      .read(schoolClassListControllerProvider.notifier)
                      .refresh(),
                  onLoadMore: _loadMore,
                  onOpen: (schoolClass) => context.push(
                    widget.scheduleMode
                        ? '/kelas/${schoolClass.id}?tab=jadwal'
                        : '/kelas/${schoolClass.id}',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AcademicYearFilter extends StatelessWidget {
  const _AcademicYearFilter({
    required this.years,
    required this.selectedId,
    required this.enabled,
    required this.onSelected,
    super.key,
  });

  final List<AcademicYear> years;
  final int? selectedId;
  final bool enabled;
  final ValueChanged<int?> onSelected;

  @override
  Widget build(BuildContext context) => NusaDropdownField<int?>(
    fieldKey: const Key('class-year-filter'),
    value: selectedId,
    enabled: enabled,
    decoration: const InputDecoration(prefixIcon: Icon(Icons.school_outlined)),
    options: [
      const NusaDropdownOption<int?>(
        value: null,
        label: 'Semua tahun pelajaran',
      ),
      for (final year in years)
        NusaDropdownOption<int?>(
          value: year.id,
          label: year.active ? '${year.name} · Aktif' : year.name,
        ),
    ],
    onChanged: onSelected,
  );
}

class _StatusFilters extends StatelessWidget {
  const _StatusFilters({
    required this.selected,
    required this.enabled,
    required this.onSelected,
  });

  final String selected;
  final bool enabled;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (final status in const [
          ('semua', 'Semua'),
          ('aktif', 'Aktif'),
          ('nonaktif', 'Nonaktif'),
        ]) ...[
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(right: status.$1 == 'nonaktif' ? 0 : 7),
              child: FilterChip(
                key: Key('class-filter-${status.$1}'),
                label: SizedBox(
                  width: double.infinity,
                  child: Text(status.$2, textAlign: TextAlign.center),
                ),
                selected: selected == status.$1,
                onSelected: enabled ? (_) => onSelected(status.$1) : null,
                showCheckmark: false,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _ClassResults extends StatelessWidget {
  const _ClassResults({
    required this.page,
    required this.scheduleMode,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
  });

  final SchoolClassPage page;
  final bool scheduleMode;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<SchoolClassSummary> onOpen;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 52),
          children: [
            const Icon(
              Icons.meeting_room_outlined,
              size: 52,
              color: NusaColors.primary,
            ),
            const SizedBox(height: 12),
            Text(
              page.query.isEmpty
                  ? scheduleMode
                        ? 'Belum ada kelas untuk menampilkan jadwal.'
                        : 'Belum ada kelas pada filter ini.'
                  : 'Kelas dengan kata kunci “${page.query}” tidak ditemukan.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('class-list-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
        itemCount: page.items.length + 1,
        separatorBuilder: (context, index) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          if (index == page.items.length) {
            return Padding(
              padding: const EdgeInsets.only(top: 5),
              child: page.pagination.hasNextPage
                  ? OutlinedButton.icon(
                      onPressed: loadingMore ? null : onLoadMore,
                      icon: loadingMore
                          ? const SizedBox.square(
                              dimension: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.expand_more_rounded),
                      label: Text(
                        loadingMore ? 'Memuat...' : 'Muat lebih banyak',
                      ),
                    )
                  : Text(
                      '${page.pagination.total} kelas ditampilkan',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
            );
          }

          final schoolClass = page.items[index];
          return SchoolClassListCard(
            schoolClass: schoolClass,
            onTap: () => onOpen(schoolClass),
          );
        },
      ),
    );
  }
}

class _ClassErrorState extends StatelessWidget {
  const _ClassErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.cloud_off_rounded,
              color: NusaColors.primary,
              size: 48,
            ),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 14),
            FilledButton.tonalIcon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Coba lagi'),
            ),
          ],
        ),
      ),
    );
  }
}

String _errorMessage(Object error) {
  return error is AppException
      ? error.message
      : 'Data kelas belum dapat dimuat.';
}
