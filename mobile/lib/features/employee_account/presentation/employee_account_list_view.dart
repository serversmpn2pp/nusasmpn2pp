import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_account/application/employee_account_controller.dart';
import 'package:nusa/features/employee_account/domain/employee_account.dart';
import 'package:nusa/features/employee_account/presentation/widgets/employee_account_components.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class EmployeeAccountListView extends ConsumerStatefulWidget {
  const EmployeeAccountListView({super.key});

  @override
  ConsumerState<EmployeeAccountListView> createState() =>
      _EmployeeAccountListViewState();
}

class _EmployeeAccountListViewState
    extends ConsumerState<EmployeeAccountListView> {
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
    final accounts = ref.watch(employeeAccountListControllerProvider);
    final current = accounts.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Akun Pegawai'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: accounts.isLoading
                ? null
                : () => ref
                      .read(employeeAccountListControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton:
          current?.canManage == true && current!.counts.withoutAccount > 0
          ? FloatingActionButton.extended(
              key: const Key('create-all-employee-accounts'),
              onPressed: _mutating ? null : _createAllAccounts,
              icon: const Icon(Icons.group_add_rounded),
              label: const Text('Buat Akun Semua'),
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
                    EmployeeAccountSummaryStrip(counts: current.counts),
                    const SizedBox(height: 9),
                  ],
                  NusaTextField(
                    fieldKey: const Key('employee-account-search'),
                    controller: _searchController,
                    hintText: 'Cari nama, NIP, jabatan, atau username',
                    prefixIcon: Icons.search_rounded,
                    textInputAction: TextInputAction.search,
                    onChanged: _search,
                    onFieldSubmitted: (value) {
                      _debounce?.cancel();
                      ref
                          .read(employeeAccountListControllerProvider.notifier)
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
                                  .read(
                                    employeeAccountListControllerProvider
                                        .notifier,
                                  )
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                  const SizedBox(height: 9),
                  NusaDropdownField<String>(
                    fieldKey: const Key('employee-account-status-filter'),
                    value: current?.status ?? 'semua',
                    decoration: const InputDecoration(
                      labelText: 'Status akun',
                      prefixIcon: Icon(Icons.manage_accounts_outlined),
                    ),
                    options: const [
                      NusaDropdownOption(
                        value: 'semua',
                        label: 'Semua status akun',
                      ),
                      NusaDropdownOption(
                        value: 'sudah',
                        label: 'Sudah punya akun',
                      ),
                      NusaDropdownOption(
                        value: 'belum',
                        label: 'Belum punya akun',
                      ),
                      NusaDropdownOption(
                        value: 'tanpa_nip',
                        label: 'Tanpa NIP',
                      ),
                    ],
                    onChanged: accounts.isLoading
                        ? null
                        : (value) {
                            if (value != null) {
                              ref
                                  .read(
                                    employeeAccountListControllerProvider
                                        .notifier,
                                  )
                                  .filterStatus(value);
                            }
                          },
                  ),
                ],
              ),
            ),
            Expanded(
              child: accounts.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _AccountListError(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(employeeAccountListControllerProvider.notifier)
                      .refresh(),
                ),
                data: (page) => _AccountResults(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: () => ref
                      .read(employeeAccountListControllerProvider.notifier)
                      .refresh(),
                  onLoadMore: _loadMore,
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
        ref.read(employeeAccountListControllerProvider.notifier).search(value);
      }
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(employeeAccountListControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _createAllAccounts() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.group_add_rounded, color: NusaColors.primary),
        title: const Text('Buat semua akun?'),
        content: const Text(
          'Akun akan dibuat untuk seluruh pegawai aktif yang memiliki NIP '
          'dan belum mempunyai akun. Username mengikuti NIP tanpa spasi.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('confirm-create-all-employee-accounts'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Ya, Buat Akun'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _mutating = true);
    try {
      final result = await ref
          .read(employeeAccountActionsProvider)
          .createAllAccounts();
      await ref.read(employeeAccountListControllerProvider.notifier).refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(
              '${result.created} akun dibuat, ${result.skipped} dilewati.',
            ),
          ),
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

class _AccountResults extends StatelessWidget {
  const _AccountResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
  });

  final EmployeeAccountPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 90),
            Icon(
              Icons.manage_search_rounded,
              size: 52,
              color: NusaColors.primary,
            ),
            SizedBox(height: 12),
            Text(
              'Tidak ada akun pegawai yang sesuai filter.',
              textAlign: TextAlign.center,
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('employee-account-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
        itemCount: page.items.length + (page.pagination.hasNextPage ? 1 : 0),
        separatorBuilder: (context, index) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          if (index == page.items.length) {
            return Center(
              child: OutlinedButton.icon(
                onPressed: loadingMore ? null : onLoadMore,
                icon: loadingMore
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more_rounded),
                label: const Text('Muat berikutnya'),
              ),
            );
          }

          final item = page.items[index];
          return EmployeeAccountCard(
            item: item,
            onTap: () => context.push('/akun-pegawai/${item.employee.id}'),
          );
        },
      ),
    );
  }
}

class _AccountListError extends StatelessWidget {
  const _AccountListError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_outlined,
            size: 48,
            color: NusaColors.primary,
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

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Akun pegawai belum dapat dimuat. Silakan coba lagi.';
