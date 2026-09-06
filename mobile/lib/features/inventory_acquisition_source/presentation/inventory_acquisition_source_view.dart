import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_acquisition_source/application/inventory_acquisition_source_controller.dart';
import 'package:nusa/features/inventory_acquisition_source/domain/inventory_acquisition_source.dart';
import 'package:nusa/features/inventory_acquisition_source/presentation/widgets/inventory_acquisition_source_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class InventoryAcquisitionSourceView extends ConsumerStatefulWidget {
  const InventoryAcquisitionSourceView({super.key});

  @override
  ConsumerState<InventoryAcquisitionSourceView> createState() =>
      _InventoryAcquisitionSourceViewState();
}

class _InventoryAcquisitionSourceViewState
    extends ConsumerState<InventoryAcquisitionSourceView> {
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
    final sources = ref.watch(inventoryAcquisitionSourceControllerProvider);
    final current = sources.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Sumber Perolehan'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: sources.isLoading || _mutating
                ? null
                : ref
                      .read(
                        inventoryAcquisitionSourceControllerProvider.notifier,
                      )
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-inventory-acquisition-source'),
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
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _AcquisitionSourceSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key(
                        'inventory-acquisition-source-search',
                      ),
                      controller: _searchController,
                      hintText: 'Cari nama, kode, atau deskripsi',
                      prefixIcon: Icons.search_rounded,
                      enabled: !sources.isLoading && !_mutating,
                      onChanged: _search,
                      suffixIcon: _searchController.text.isEmpty
                          ? null
                          : IconButton(
                              tooltip: 'Hapus pencarian',
                              onPressed: _clearSearch,
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<String>(
                      fieldKey: const Key(
                        'inventory-acquisition-source-status-filter',
                      ),
                      value: current.status,
                      options: const [
                        NusaDropdownOption(
                          value: 'semua',
                          label: 'Semua status',
                        ),
                        NusaDropdownOption(value: 'aktif', label: 'Aktif'),
                        NusaDropdownOption(
                          value: 'nonaktif',
                          label: 'Nonaktif',
                        ),
                      ],
                      decoration: const InputDecoration(
                        labelText: 'Status sumber',
                        prefixIcon: Icon(Icons.toggle_on_outlined),
                      ),
                      enabled: !sources.isLoading && !_mutating,
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(
                                inventoryAcquisitionSourceControllerProvider
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
              child: sources.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _AcquisitionSourceError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(
                        inventoryAcquisitionSourceControllerProvider.notifier,
                      )
                      .refresh,
                ),
                data: (page) => _AcquisitionSourceResults(
                  page: page,
                  mutating: _mutating,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(
                        inventoryAcquisitionSourceControllerProvider.notifier,
                      )
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
        ref
            .read(inventoryAcquisitionSourceControllerProvider.notifier)
            .search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(inventoryAcquisitionSourceControllerProvider.notifier).search('');
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref
          .read(inventoryAcquisitionSourceControllerProvider.notifier)
          .loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm({InventoryAcquisitionSource? existing}) async {
    final value =
        await showModalBottomSheet<InventoryAcquisitionSourceFormValue>(
          context: context,
          isScrollControlled: true,
          useSafeArea: true,
          builder: (context) =>
              InventoryAcquisitionSourceFormSheet(existing: existing),
        );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Sumber perolehan berhasil ditambahkan.'
          : 'Sumber perolehan berhasil diperbarui.',
      operation: existing == null
          ? () => ref
                .read(inventoryAcquisitionSourceActionsProvider)
                .create(value)
          : () => ref
                .read(inventoryAcquisitionSourceActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _confirmDeactivate(InventoryAcquisitionSource item) async {
    final usageText = item.assetUnitsCount == 0
        ? 'Sumber tidak akan tersedia untuk penerimaan aset baru.'
        : 'Sumber telah digunakan oleh ${item.assetUnitsCount} unit aset. '
              'Seluruh data dan riwayat aset tetap tersimpan.';
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan sumber?'),
            content: Text('${item.name} akan dinonaktifkan. $usageText'),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key(
                  'confirm-inventory-acquisition-source-deactivate',
                ),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage: 'Sumber dinonaktifkan; riwayat aset tetap tersimpan.',
      operation: () => ref
          .read(inventoryAcquisitionSourceActionsProvider)
          .deactivate(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref
          .read(inventoryAcquisitionSourceControllerProvider.notifier)
          .refresh();
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

class _AcquisitionSourceSummary extends StatelessWidget {
  const _AcquisitionSourceSummary({required this.summary});

  final InventoryAcquisitionSourceSummary summary;

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

class _AcquisitionSourceResults extends StatelessWidget {
  const _AcquisitionSourceResults({
    required this.page,
    required this.mutating,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onEdit,
    required this.onDeactivate,
  });

  final InventoryAcquisitionSourcePage page;
  final bool mutating;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<InventoryAcquisitionSource> onEdit;
  final ValueChanged<InventoryAcquisitionSource> onDeactivate;

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
              Icons.account_balance_wallet_outlined,
              size: 50,
              color: NusaColors.primary,
            ),
            SizedBox(height: 12),
            Text(
              'Belum ada sumber perolehan pada filter ini.',
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
        key: const PageStorageKey<String>('inventory-acquisition-source-list'),
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
                    '${page.pagination.total} sumber ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final item = page.items[index];
          return _AcquisitionSourceCard(
            item: item,
            onEdit: mutating || !page.access.canManage
                ? null
                : () => onEdit(item),
            onDeactivate: mutating || !page.access.canManage || !item.active
                ? null
                : () => onDeactivate(item),
          );
        },
      ),
    );
  }
}

class _AcquisitionSourceCard extends StatelessWidget {
  const _AcquisitionSourceCard({
    required this.item,
    this.onEdit,
    this.onDeactivate,
  });

  final InventoryAcquisitionSource item;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('inventory-acquisition-source-${item.id}'),
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
              color: NusaColors.primary.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(
              Icons.account_balance_wallet_rounded,
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
                  item.code,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
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
                      label: item.active ? 'Aktif' : 'Nonaktif',
                      color: item.active
                          ? NusaColors.success
                          : NusaColors.textSecondary,
                    ),
                    _Badge(
                      label: '${item.assetUnitsCount} unit aset',
                      color: NusaColors.primary,
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (onEdit != null || onDeactivate != null)
            PopupMenuButton<String>(
              key: Key('inventory-acquisition-source-menu-${item.id}'),
              tooltip: 'Aksi sumber perolehan',
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

class _AcquisitionSourceError extends StatelessWidget {
  const _AcquisitionSourceError({required this.message, required this.onRetry});

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
      : 'Sumber perolehan belum dapat diproses.';
}
