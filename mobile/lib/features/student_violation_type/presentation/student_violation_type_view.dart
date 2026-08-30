import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_violation_type/application/student_violation_type_controller.dart';
import 'package:nusa/features/student_violation_type/domain/student_violation_type.dart';
import 'package:nusa/features/student_violation_type/presentation/widgets/student_violation_type_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentViolationTypeView extends ConsumerStatefulWidget {
  const StudentViolationTypeView({super.key});

  @override
  ConsumerState<StudentViolationTypeView> createState() =>
      _StudentViolationTypeViewState();
}

class _StudentViolationTypeViewState
    extends ConsumerState<StudentViolationTypeView> {
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
    final types = ref.watch(studentViolationTypeControllerProvider);
    final current = types.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Jenis Pelanggaran & Poin'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: types.isLoading || _mutating
                ? null
                : ref
                      .read(studentViolationTypeControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-violation-type'),
              onPressed: _mutating ? null : _openForm,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 7),
                child: Column(
                  children: [
                    _ViolationSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('violation-type-search'),
                      controller: _searchController,
                      hintText: 'Cari kode atau jenis pelanggaran',
                      prefixIcon: Icons.search_rounded,
                      enabled: !types.isLoading && !_mutating,
                      onChanged: _search,
                      suffixIcon: _searchController.text.isEmpty
                          ? null
                          : IconButton(
                              onPressed: _clearSearch,
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key('violation-type-level-filter'),
                            value: current.level,
                            options: [
                              const NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua tingkat',
                              ),
                              for (final item in current.references.levels)
                                NusaDropdownOption(
                                  value: item.code,
                                  label: item.label,
                                ),
                            ],
                            decoration: const InputDecoration(
                              labelText: 'Tingkat',
                              prefixIcon: Icon(
                                Icons.signal_cellular_alt_rounded,
                              ),
                            ),
                            enabled: !types.isLoading && !_mutating,
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      studentViolationTypeControllerProvider
                                          .notifier,
                                    )
                                    .filterLevel(value);
                              }
                            },
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key('violation-type-status-filter'),
                            value: current.status,
                            options: const [
                              NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua status',
                              ),
                              NusaDropdownOption(
                                value: 'aktif',
                                label: 'Aktif',
                              ),
                              NusaDropdownOption(
                                value: 'nonaktif',
                                label: 'Nonaktif',
                              ),
                            ],
                            decoration: const InputDecoration(
                              labelText: 'Status',
                              prefixIcon: Icon(Icons.toggle_on_outlined),
                            ),
                            enabled: !types.isLoading && !_mutating,
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      studentViolationTypeControllerProvider
                                          .notifier,
                                    )
                                    .filterStatus(value);
                              }
                            },
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<int>(
                      fieldKey: const Key('violation-type-category-filter'),
                      value: current.categoryId ?? 0,
                      options: [
                        const NusaDropdownOption(
                          value: 0,
                          label: 'Semua kategori pembinaan',
                        ),
                        for (final category in current.references.categories)
                          NusaDropdownOption(
                            value: category.id,
                            label:
                                '${category.name}${category.active ? '' : ' (Nonaktif)'}',
                          ),
                      ],
                      decoration: const InputDecoration(
                        labelText: 'Kategori',
                        prefixIcon: Icon(Icons.category_outlined),
                      ),
                      enabled: !types.isLoading && !_mutating,
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(
                                studentViolationTypeControllerProvider.notifier,
                              )
                              .filterCategory(value == 0 ? null : value);
                        }
                      },
                    ),
                  ],
                ),
              ),
            Expanded(
              child: types.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _ViolationError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(studentViolationTypeControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _ViolationResults(
                  page: page,
                  mutating: _mutating,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(studentViolationTypeControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onEdit: (item) => _openForm(existing: item),
                  onDeactivate: _confirmDeactivate,
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
        ref.read(studentViolationTypeControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(studentViolationTypeControllerProvider.notifier).search('');
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref
          .read(studentViolationTypeControllerProvider.notifier)
          .loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm({StudentViolationType? existing}) async {
    final page = ref.read(studentViolationTypeControllerProvider).value;
    if (page == null) return;
    final value = await showModalBottomSheet<StudentViolationTypeFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => StudentViolationTypeFormSheet(
        existing: existing,
        references: page.references,
      ),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Jenis pelanggaran berhasil ditambahkan.'
          : 'Jenis pelanggaran berhasil diperbarui; laporan lama tetap.',
      operation: existing == null
          ? () => ref.read(studentViolationTypeActionsProvider).create(value)
          : () => ref
                .read(studentViolationTypeActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _confirmDeactivate(StudentViolationType item) async {
    final usage = item.usageCount == 0
        ? 'Data tidak akan tersedia pada laporan baru.'
        : 'Data telah dipakai pada ${item.usageCount} laporan. Seluruh kode, '
              'nama, tingkat, dan poin lama tetap tersimpan.';
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan jenis pelanggaran?'),
            content: Text('${item.code} · ${item.name}\n\n$usage'),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-violation-type-deactivate'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage: 'Jenis pelanggaran dinonaktifkan; riwayat tetap aman.',
      operation: () =>
          ref.read(studentViolationTypeActionsProvider).deactivate(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(studentViolationTypeControllerProvider.notifier).refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(successMessage)));
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

class _ViolationSummary extends StatelessWidget {
  const _ViolationSummary({required this.summary});

  final StudentViolationTypeSummary summary;

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
        _SummaryItem(label: 'Total', value: summary.total),
        _SummaryItem(label: 'Aktif', value: summary.active),
        _SummaryItem(label: 'Nonaktif', value: summary.inactive),
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
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 10,
          ),
        ),
      ],
    ),
  );
}

class _ViolationResults extends StatelessWidget {
  const _ViolationResults({
    required this.page,
    required this.mutating,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onEdit,
    required this.onDeactivate,
  });

  final StudentViolationTypePage page;
  final bool mutating;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<StudentViolationType> onEdit;
  final ValueChanged<StudentViolationType> onDeactivate;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: const [
            Icon(Icons.gavel_rounded, size: 50, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada jenis pelanggaran pada filter ini.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('violation-type-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 92),
        itemCount: page.items.length + 2,
        separatorBuilder: (context, index) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          if (index == 0) {
            return Container(
              padding: const EdgeInsets.all(11),
              decoration: BoxDecoration(
                color: NusaColors.accent.withValues(alpha: 0.13),
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline_rounded, size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Perubahan bobot hanya berlaku untuk laporan berikutnya. Riwayat lama tidak berubah.',
                      style: TextStyle(fontSize: 11.5, height: 1.35),
                    ),
                  ),
                ],
              ),
            );
          }
          if (index == page.items.length + 1) {
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
                    '${page.pagination.total} jenis pelanggaran ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final item = page.items[index - 1];
          final canManage = page.access.canManage && !mutating;
          return _ViolationCard(
            item: item,
            onEdit: canManage ? () => onEdit(item) : null,
            onDeactivate: canManage && item.active
                ? () => onDeactivate(item)
                : null,
          );
        },
      ),
    );
  }
}

class _ViolationCard extends StatelessWidget {
  const _ViolationCard({required this.item, this.onEdit, this.onDeactivate});

  final StudentViolationType item;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('violation-type-${item.id}'),
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            padding: const EdgeInsets.symmetric(vertical: 8),
            decoration: BoxDecoration(
              color: _levelColor(item.level).withValues(alpha: 0.11),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Column(
              children: [
                Text(
                  '${item.points}',
                  style: TextStyle(
                    color: _levelColor(item.level),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  'POIN',
                  style: TextStyle(
                    color: _levelColor(item.level),
                    fontSize: 8,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${item.code} · ${item.name}',
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  item.category?.name ?? 'Tanpa kategori pembinaan',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  runSpacing: 5,
                  children: [
                    _Badge(
                      label: item.levelLabel,
                      color: _levelColor(item.level),
                    ),
                    _Badge(
                      label: item.active ? 'Aktif' : 'Nonaktif',
                      color: item.active
                          ? NusaColors.success
                          : NusaColors.textSecondary,
                    ),
                    _Badge(
                      label: '${item.usageCount} laporan',
                      color: NusaColors.primary,
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (onEdit != null || onDeactivate != null)
            PopupMenuButton<String>(
              key: Key('violation-type-menu-${item.id}'),
              tooltip: 'Aksi jenis pelanggaran',
              onSelected: (value) {
                if (value == 'edit') onEdit?.call();
                if (value == 'deactivate') onDeactivate?.call();
              },
              itemBuilder: (context) => [
                PopupMenuItem(
                  value: 'edit',
                  enabled: onEdit != null,
                  child: const Text('Ubah'),
                ),
                if (item.active)
                  PopupMenuItem(
                    value: 'deactivate',
                    enabled: onDeactivate != null,
                    child: const Text('Nonaktifkan'),
                  ),
              ],
            ),
        ],
      ),
    ),
  );
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800),
    ),
  );
}

class _ViolationError extends StatelessWidget {
  const _ViolationError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
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

Color _levelColor(String level) => switch (level) {
  'berat' => const Color(0xFFC23A3A),
  'sedang' => const Color(0xFFB57900),
  _ => NusaColors.success,
};

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Jenis pelanggaran belum dapat diproses.';
}
