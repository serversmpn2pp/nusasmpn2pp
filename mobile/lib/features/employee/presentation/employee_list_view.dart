import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee/application/employee_controller.dart';
import 'package:nusa/features/employee/domain/employee.dart';
import 'package:nusa/features/employee/presentation/widgets/employee_components.dart';
import 'package:nusa/features/employee/presentation/widgets/employee_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class EmployeeListView extends ConsumerStatefulWidget {
  const EmployeeListView({super.key});

  @override
  ConsumerState<EmployeeListView> createState() => _EmployeeListViewState();
}

class _EmployeeListViewState extends ConsumerState<EmployeeListView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;
  bool _mutating = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final employees = ref.watch(employeeListControllerProvider);
    final current = employees.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Data Pegawai'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: employees.isLoading
                ? null
                : () => ref
                      .read(employeeListControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-employee'),
              onPressed: _mutating ? null : _addEmployee,
              icon: const Icon(Icons.person_add_alt_1_rounded),
              label: const Text('Tambah Pegawai'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: Column(
                children: [
                  if (current != null) ...[
                    EmployeeSummaryStrip(counts: current.counts),
                    const SizedBox(height: 9),
                  ],
                  NusaTextField(
                    fieldKey: const Key('employee-search'),
                    controller: _searchController,
                    hintText: 'Cari nama, NIP, NUPTK, NIK, atau jabatan',
                    prefixIcon: Icons.search_rounded,
                    textInputAction: TextInputAction.search,
                    onChanged: _search,
                    onFieldSubmitted: (value) {
                      _debounce?.cancel();
                      ref
                          .read(employeeListControllerProvider.notifier)
                          .search(value);
                    },
                    suffixIcon: _searchController.text.isEmpty
                        ? null
                        : IconButton(
                            tooltip: 'Hapus pencarian',
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                              ref
                                  .read(employeeListControllerProvider.notifier)
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                  const SizedBox(height: 9),
                  _EmployeeStatusFilter(
                    selected: current?.status ?? 'semua',
                    enabled: !employees.isLoading,
                    onSelected: (value) => ref
                        .read(employeeListControllerProvider.notifier)
                        .filterStatus(value),
                  ),
                  if (current != null && current.types.isNotEmpty) ...[
                    const SizedBox(height: 9),
                    NusaDropdownField<String>(
                      fieldKey: const Key('employee-type-filter'),
                      value: current.type,
                      decoration: const InputDecoration(
                        labelText: 'Jenis pegawai',
                        prefixIcon: Icon(Icons.groups_outlined),
                      ),
                      options: [
                        const NusaDropdownOption(
                          value: 'semua',
                          label: 'Semua jenis pegawai',
                        ),
                        for (final type in current.types)
                          NusaDropdownOption(value: type, label: type),
                      ],
                      onChanged: employees.isLoading
                          ? null
                          : (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      employeeListControllerProvider.notifier,
                                    )
                                    .filterType(value);
                              }
                            },
                    ),
                  ],
                ],
              ),
            ),
            Expanded(
              child: employees.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _EmployeeError(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(employeeListControllerProvider.notifier)
                      .refresh(),
                ),
                data: (page) => _EmployeeResults(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: () => ref
                      .read(employeeListControllerProvider.notifier)
                      .refresh(),
                  onLoadMore: _loadMore,
                  onOpen: (employee) => context.push('/pegawai/${employee.id}'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) {
        ref.read(employeeListControllerProvider.notifier).search(value);
      }
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(employeeListControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _addEmployee() async {
    final value = await showEmployeeFormSheet(context);
    if (value == null || !mounted) return;
    setState(() => _mutating = true);
    try {
      await ref.read(employeeActionsProvider).create(value);
      await ref.read(employeeListControllerProvider.notifier).refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(content: Text('Data pegawai berhasil ditambahkan.')),
        );
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _showError(Object error) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
  }
}

class _EmployeeStatusFilter extends StatelessWidget {
  const _EmployeeStatusFilter({
    required this.selected,
    required this.enabled,
    required this.onSelected,
  });

  final String selected;
  final bool enabled;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      for (final item in const [
        ('semua', 'Semua'),
        ('aktif', 'Aktif'),
        ('nonaktif', 'Nonaktif'),
      ])
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(right: item.$1 == 'nonaktif' ? 0 : 7),
            child: FilterChip(
              key: Key('employee-filter-${item.$1}'),
              label: SizedBox(
                width: double.infinity,
                child: Text(item.$2, textAlign: TextAlign.center),
              ),
              selected: selected == item.$1,
              showCheckmark: false,
              onSelected: enabled ? (_) => onSelected(item.$1) : null,
            ),
          ),
        ),
    ],
  );
}

class _EmployeeResults extends StatelessWidget {
  const _EmployeeResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
  });

  final EmployeePage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<EmployeeSummary> onOpen;

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
                  ? 'Belum ada pegawai pada filter ini.'
                  : 'Pegawai dengan kata kunci “${page.query}” tidak ditemukan.',
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
        key: const PageStorageKey<String>('employee-list-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 92),
        itemCount: page.items.length + 1,
        separatorBuilder: (context, index) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          if (index == page.items.length) {
            return page.pagination.hasNextPage
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
                    '${page.pagination.total} pegawai ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final employee = page.items[index];
          return EmployeeListCard(
            employee: employee,
            onTap: () => onOpen(employee),
          );
        },
      ),
    );
  }
}

class _EmployeeError extends StatelessWidget {
  const _EmployeeError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.badge_outlined, size: 48, color: NusaColors.primary),
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

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Data pegawai belum dapat dimuat. Silakan coba lagi.';
