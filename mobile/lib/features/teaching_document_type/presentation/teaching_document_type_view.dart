import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document_type/application/teaching_document_type_controller.dart';
import 'package:nusa/features/teaching_document_type/domain/teaching_document_type.dart';
import 'package:nusa/features/teaching_document_type/presentation/widgets/teaching_document_type_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class TeachingDocumentTypeView extends ConsumerStatefulWidget {
  const TeachingDocumentTypeView({super.key});

  @override
  ConsumerState<TeachingDocumentTypeView> createState() =>
      _TeachingDocumentTypeViewState();
}

class _TeachingDocumentTypeViewState
    extends ConsumerState<TeachingDocumentTypeView> {
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
    final types = ref.watch(teachingDocumentTypeControllerProvider);
    final current = types.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Jenis Perangkat Ajar'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: types.isLoading || _mutating
                ? null
                : ref
                      .read(teachingDocumentTypeControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current == null
          ? null
          : FloatingActionButton.extended(
              key: const Key('add-teaching-document-type'),
              onPressed: _mutating
                  ? null
                  : () => _openForm(nextOrder: current.nextOrder),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah'),
            ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _TypeSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('teaching-document-type-search'),
                      controller: _searchController,
                      hintText: 'Cari nama, kode, atau deskripsi',
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
                      children: [
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key(
                              'teaching-document-type-status-filter',
                            ),
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
                                      teachingDocumentTypeControllerProvider
                                          .notifier,
                                    )
                                    .filterStatus(value);
                              }
                            },
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key(
                              'teaching-document-type-requirement-filter',
                            ),
                            value: current.requirement,
                            options: const [
                              NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua jenis',
                              ),
                              NusaDropdownOption(
                                value: 'wajib',
                                label: 'Wajib',
                              ),
                              NusaDropdownOption(
                                value: 'opsional',
                                label: 'Opsional',
                              ),
                            ],
                            decoration: const InputDecoration(
                              labelText: 'Kewajiban',
                              prefixIcon: Icon(Icons.rule_rounded),
                            ),
                            enabled: !types.isLoading && !_mutating,
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      teachingDocumentTypeControllerProvider
                                          .notifier,
                                    )
                                    .filterRequirement(value);
                              }
                            },
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            Expanded(
              child: types.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _TypeError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(teachingDocumentTypeControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _TypeResults(
                  page: page,
                  mutating: _mutating,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(teachingDocumentTypeControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onEdit: (item) =>
                      _openForm(existing: item, nextOrder: page.nextOrder),
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
        ref.read(teachingDocumentTypeControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(teachingDocumentTypeControllerProvider.notifier).search('');
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref
          .read(teachingDocumentTypeControllerProvider.notifier)
          .loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm({
    required int nextOrder,
    TeachingDocumentType? existing,
  }) async {
    final value = await showModalBottomSheet<TeachingDocumentTypeFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => TeachingDocumentTypeFormSheet(
        existing: existing,
        nextOrder: nextOrder,
      ),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Jenis perangkat ajar berhasil ditambahkan.'
          : 'Jenis perangkat ajar berhasil diperbarui.',
      operation: existing == null
          ? () => ref.read(teachingDocumentTypeActionsProvider).create(value)
          : () => ref
                .read(teachingDocumentTypeActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _confirmDeactivate(TeachingDocumentType item) async {
    final documentText = item.documentCount == 0
        ? 'Jenis ini tidak akan tersedia pada unggahan baru.'
        : '${item.documentCount} dokumen lama tetap tersimpan dan tetap dapat diperiksa. '
              'Jenis ini hanya disembunyikan dari unggahan baru.';
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan jenis?'),
            content: Text('${item.name} akan dinonaktifkan. $documentText'),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-teaching-document-type-deactivate'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage:
          'Jenis perangkat ajar dinonaktifkan. Dokumen lama tetap tersimpan.',
      operation: () =>
          ref.read(teachingDocumentTypeActionsProvider).deactivate(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(teachingDocumentTypeControllerProvider.notifier).refresh();
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

class _TypeSummary extends StatelessWidget {
  const _TypeSummary({required this.summary});

  final TeachingDocumentTypeSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 13),
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
        _SummaryItem(label: 'Wajib', value: summary.mandatory),
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

class _TypeResults extends StatelessWidget {
  const _TypeResults({
    required this.page,
    required this.mutating,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onEdit,
    required this.onDeactivate,
  });

  final TeachingDocumentTypePage page;
  final bool mutating;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<TeachingDocumentType> onEdit;
  final ValueChanged<TeachingDocumentType> onDeactivate;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: const [
            Icon(
              Icons.folder_off_outlined,
              size: 50,
              color: NusaColors.primary,
            ),
            SizedBox(height: 12),
            Text(
              'Belum ada jenis perangkat ajar pada filter ini.',
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
        key: const PageStorageKey<String>('teaching-document-type-list'),
        physics: const AlwaysScrollableScrollPhysics(),
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
                    '${page.pagination.total} jenis ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final item = page.items[index];
          return _TypeCard(
            item: item,
            onEdit: mutating ? null : () => onEdit(item),
            onDeactivate: mutating || !item.active
                ? null
                : () => onDeactivate(item),
          );
        },
      ),
    );
  }
}

class _TypeCard extends StatelessWidget {
  const _TypeCard({required this.item, this.onEdit, this.onDeactivate});

  final TeachingDocumentType item;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('teaching-document-type-${item.id}'),
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: (item.mandatory ? NusaColors.accent : NusaColors.primary)
                  .withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(
              item.mandatory
                  ? Icons.assignment_turned_in_outlined
                  : Icons.description_outlined,
              color: NusaColors.primary,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${item.code} · Urutan ${item.order}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
                if (item.description?.isNotEmpty == true) ...[
                  const SizedBox(height: 5),
                  Text(
                    item.description!,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 11.5, height: 1.3),
                  ),
                ],
                const SizedBox(height: 7),
                Wrap(
                  spacing: 6,
                  runSpacing: 5,
                  children: [
                    _Badge(
                      label: item.mandatory ? 'Wajib' : 'Opsional',
                      color: item.mandatory
                          ? NusaColors.accent
                          : NusaColors.primary,
                    ),
                    _Badge(
                      label: item.active ? 'Aktif' : 'Nonaktif',
                      color: item.active
                          ? NusaColors.success
                          : NusaColors.textSecondary,
                    ),
                    _Badge(
                      label: '${item.documentCount} dokumen',
                      color: NusaColors.primary,
                    ),
                  ],
                ),
              ],
            ),
          ),
          PopupMenuButton<String>(
            key: Key('teaching-document-type-menu-${item.id}'),
            tooltip: 'Aksi jenis perangkat ajar',
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

class _TypeError extends StatelessWidget {
  const _TypeError({required this.message, required this.onRetry});

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

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Jenis perangkat ajar belum dapat diproses.';
}
