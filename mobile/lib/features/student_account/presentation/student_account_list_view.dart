import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_account/application/student_account_controller.dart';
import 'package:nusa/features/student_account/domain/student_account.dart';
import 'package:nusa/features/student_account/presentation/widgets/student_account_components.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentAccountListView extends ConsumerStatefulWidget {
  const StudentAccountListView({super.key});

  @override
  ConsumerState<StudentAccountListView> createState() =>
      _StudentAccountListViewState();
}

class _StudentAccountListViewState
    extends ConsumerState<StudentAccountListView> {
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
    final accounts = ref.watch(studentAccountListControllerProvider);
    final current = accounts.value;
    final selectedClass = current?.classes
        .where((item) => item.id == current.classId)
        .firstOrNull;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Akun Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: accounts.isLoading
                ? null
                : () => ref
                      .read(studentAccountListControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true && selectedClass != null
          ? FloatingActionButton.extended(
              key: const Key('create-class-student-accounts'),
              onPressed: _mutating
                  ? null
                  : () => _createClassAccounts(selectedClass),
              icon: const Icon(Icons.groups_rounded),
              label: const Text('Buat Akun Kelas'),
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
                    StudentAccountSummaryStrip(counts: current.counts),
                    const SizedBox(height: 9),
                    _AcademicYearNotice(page: current),
                    const SizedBox(height: 9),
                  ],
                  NusaTextField(
                    fieldKey: const Key('student-account-search'),
                    controller: _searchController,
                    hintText: 'Cari nama, NIS, NISN, atau username',
                    prefixIcon: Icons.search_rounded,
                    textInputAction: TextInputAction.search,
                    onChanged: _search,
                    onFieldSubmitted: (value) {
                      _debounce?.cancel();
                      ref
                          .read(studentAccountListControllerProvider.notifier)
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
                                    studentAccountListControllerProvider
                                        .notifier,
                                  )
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                  const SizedBox(height: 9),
                  NusaDropdownField<int>(
                    fieldKey: const Key('student-account-class-filter'),
                    value: current?.classId ?? 0,
                    decoration: const InputDecoration(
                      labelText: 'Kelas',
                      prefixIcon: Icon(Icons.class_outlined),
                    ),
                    options: [
                      const NusaDropdownOption(value: 0, label: 'Semua kelas'),
                      for (final schoolClass in current?.classes ?? const [])
                        NusaDropdownOption(
                          value: schoolClass.id,
                          label:
                              '${schoolClass.name} (${schoolClass.activeStudentCount} siswa)',
                        ),
                    ],
                    onChanged: accounts.isLoading
                        ? null
                        : (value) => ref
                              .read(
                                studentAccountListControllerProvider.notifier,
                              )
                              .filterClass(value == 0 ? null : value),
                  ),
                  const SizedBox(height: 9),
                  NusaDropdownField<String>(
                    fieldKey: const Key('student-account-status-filter'),
                    value: current?.status ?? 'semua',
                    decoration: const InputDecoration(
                      labelText: 'Status akun',
                      prefixIcon: Icon(Icons.account_circle_outlined),
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
                        value: 'tanpa_nisn',
                        label: 'NISN belum diisi',
                      ),
                    ],
                    onChanged: accounts.isLoading
                        ? null
                        : (value) {
                            if (value != null) {
                              ref
                                  .read(
                                    studentAccountListControllerProvider
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
                      .read(studentAccountListControllerProvider.notifier)
                      .refresh(),
                ),
                data: (page) => _AccountResults(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(studentAccountListControllerProvider.notifier)
                      .refresh,
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
      ref.read(studentAccountListControllerProvider.notifier).search(value);
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(studentAccountListControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _createClassAccounts(StudentAccountClass schoolClass) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.groups_rounded, color: NusaColors.primary),
        title: Text('Buat akun kelas ${schoolClass.name}?'),
        content: const Text(
          'Akun akan dibuat untuk siswa aktif yang memiliki NISN dan belum '
          'mempunyai akun. Username mengikuti NISN dan kata sandi awal '
          'terdiri dari delapan angka acak.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('confirm-create-class-student-accounts'),
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
          .read(studentAccountActionsProvider)
          .createClassAccounts(schoolClass.id);
      await ref.read(studentAccountListControllerProvider.notifier).refresh();
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

class _AcademicYearNotice extends StatelessWidget {
  const _AcademicYearNotice({required this.page});

  final StudentAccountPage page;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        const Icon(Icons.school_outlined, size: 18, color: NusaColors.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            page.academicYear == null
                ? 'Belum ada tahun pelajaran aktif.'
                : 'Tahun Pelajaran ${page.academicYear!.name}',
            style: const TextStyle(
              color: NusaColors.textPrimary,
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
}

class _AccountResults extends StatelessWidget {
  const _AccountResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
  });

  final StudentAccountPage page;
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
            SizedBox(height: 70),
            Icon(
              Icons.person_search_rounded,
              size: 52,
              color: NusaColors.primary,
            ),
            SizedBox(height: 12),
            Text(
              'Tidak ada akun siswa yang sesuai filter.',
              textAlign: TextAlign.center,
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('student-account-list'),
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
          return StudentAccountCard(
            item: item,
            onTap: () => context.push('/akun-siswa/${item.student.id}'),
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
    : 'Akun siswa belum dapat dimuat. Silakan coba lagi.';
