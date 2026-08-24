import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student/application/student_controller.dart';
import 'package:nusa/features/student/domain/student.dart';
import 'package:nusa/features/student/presentation/widgets/student_components.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentListView extends ConsumerStatefulWidget {
  const StudentListView({super.key});

  @override
  ConsumerState<StudentListView> createState() => _StudentListViewState();
}

class _StudentListViewState extends ConsumerState<StudentListView> {
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
        ref.read(studentListControllerProvider.notifier).search(value);
      }
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) {
      return;
    }

    setState(() => _loadingMore = true);
    try {
      await ref.read(studentListControllerProvider.notifier).loadMore();
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
    final students = ref.watch(studentListControllerProvider);
    final current = students.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Data Siswa'),
        actions: [
          IconButton(
            onPressed: students.isLoading
                ? null
                : () => ref
                      .read(studentListControllerProvider.notifier)
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
                    StudentSummaryStrip(counts: current.counts),
                    const SizedBox(height: 12),
                  ],
                  NusaTextField(
                    fieldKey: const Key('student-search'),
                    controller: _searchController,
                    hintText: 'Cari nama, NIS, NISN, atau NIK',
                    prefixIcon: Icons.search_rounded,
                    textInputAction: TextInputAction.search,
                    onChanged: _onSearchChanged,
                    onFieldSubmitted: (value) {
                      _searchDebounce?.cancel();
                      ref
                          .read(studentListControllerProvider.notifier)
                          .search(value);
                    },
                    suffixIcon: _searchController.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                              ref
                                  .read(studentListControllerProvider.notifier)
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                            tooltip: 'Hapus pencarian',
                          ),
                  ),
                  const SizedBox(height: 10),
                  _StatusFilters(
                    selected: current?.status ?? 'semua',
                    enabled: !students.isLoading,
                    onSelected: (status) => ref
                        .read(studentListControllerProvider.notifier)
                        .filterStatus(status),
                  ),
                ],
              ),
            ),
            Expanded(
              child: students.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _StudentErrorState(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(studentListControllerProvider.notifier)
                      .refresh(),
                ),
                data: (page) => _StudentResults(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: () => ref
                      .read(studentListControllerProvider.notifier)
                      .refresh(),
                  onLoadMore: _loadMore,
                  onOpen: (student) => context.push('/siswa/${student.id}'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
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
                key: Key('student-filter-${status.$1}'),
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

class _StudentResults extends StatelessWidget {
  const _StudentResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
  });

  final StudentPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<StudentSummary> onOpen;

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
              Icons.person_search_rounded,
              size: 52,
              color: NusaColors.primary,
            ),
            const SizedBox(height: 12),
            Text(
              page.query.isEmpty
                  ? 'Belum ada siswa pada filter ini.'
                  : 'Siswa dengan kata kunci “${page.query}” tidak ditemukan.',
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
        key: const PageStorageKey<String>('student-list-scroll'),
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
                      '${page.pagination.total} siswa ditampilkan',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
            );
          }

          final student = page.items[index];
          return StudentListCard(
            student: student,
            onTap: () => onOpen(student),
          );
        },
      ),
    );
  }
}

class _StudentErrorState extends StatelessWidget {
  const _StudentErrorState({required this.message, required this.onRetry});

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
      : 'Data siswa belum dapat dimuat.';
}
