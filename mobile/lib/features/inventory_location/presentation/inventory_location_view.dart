import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_location/application/inventory_location_controller.dart';
import 'package:nusa/features/inventory_location/domain/inventory_location.dart';
import 'package:nusa/features/inventory_location/presentation/widgets/inventory_location_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class InventoryLocationView extends ConsumerStatefulWidget {
  const InventoryLocationView({super.key});

  @override
  ConsumerState<InventoryLocationView> createState() =>
      _InventoryLocationViewState();
}

class _InventoryLocationViewState extends ConsumerState<InventoryLocationView> {
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
    final locations = ref.watch(inventoryLocationControllerProvider);
    final current = locations.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Lokasi Barang'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: locations.isLoading || _mutating
                ? null
                : ref
                      .read(inventoryLocationControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-inventory-location'),
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
                    _LocationSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('inventory-location-search'),
                      controller: _searchController,
                      hintText: 'Cari nama, kode, atau penanggung jawab',
                      prefixIcon: Icons.search_rounded,
                      enabled: !locations.isLoading && !_mutating,
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
                    _LocationFilters(
                      page: current,
                      enabled: !locations.isLoading && !_mutating,
                      onTypeChanged: (value) => ref
                          .read(inventoryLocationControllerProvider.notifier)
                          .filterType(value),
                      onStatusChanged: (value) => ref
                          .read(inventoryLocationControllerProvider.notifier)
                          .filterStatus(value),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: locations.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _LocationError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(inventoryLocationControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _LocationResults(
                  page: page,
                  mutating: _mutating,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(inventoryLocationControllerProvider.notifier)
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
        ref.read(inventoryLocationControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(inventoryLocationControllerProvider.notifier).search('');
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(inventoryLocationControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm({InventoryLocation? existing}) async {
    final page = ref.read(inventoryLocationControllerProvider).value;
    if (page == null) return;
    final value = await showModalBottomSheet<InventoryLocationFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => InventoryLocationFormSheet(
        types: page.types,
        employees: page.employees,
        existing: existing,
      ),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Lokasi barang berhasil ditambahkan.'
          : 'Lokasi barang berhasil diperbarui.',
      operation: existing == null
          ? () => ref.read(inventoryLocationActionsProvider).create(value)
          : () => ref
                .read(inventoryLocationActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _confirmDeactivate(InventoryLocation item) async {
    final usageText = item.goodsCount == 0
        ? 'Lokasi tidak akan tersedia untuk barang baru.'
        : 'Lokasi telah digunakan oleh ${item.goodsCount} jenis barang. '
              'Seluruh data dan riwayat barang tetap tersimpan.';
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan lokasi?'),
            content: Text('${item.name} akan dinonaktifkan. $usageText'),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-inventory-location-deactivate'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage: 'Lokasi dinonaktifkan; riwayat barang tetap tersimpan.',
      operation: () =>
          ref.read(inventoryLocationActionsProvider).deactivate(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(inventoryLocationControllerProvider.notifier).refresh();
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

class _LocationSummary extends StatelessWidget {
  const _LocationSummary({required this.summary});

  final InventoryLocationSummary summary;

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
        _SummaryItem(label: 'Ada PJ', value: summary.withResponsibleEmployee),
      ],
    ),
  );
}

class _LocationFilters extends StatelessWidget {
  const _LocationFilters({
    required this.page,
    required this.enabled,
    required this.onTypeChanged,
    required this.onStatusChanged,
  });

  final InventoryLocationPage page;
  final bool enabled;
  final ValueChanged<String> onTypeChanged;
  final ValueChanged<String> onStatusChanged;

  @override
  Widget build(BuildContext context) {
    final typeField = NusaDropdownField<String>(
      fieldKey: const Key('inventory-location-type-filter'),
      value: page.type,
      options: [
        const NusaDropdownOption(value: 'semua', label: 'Semua jenis'),
        ...page.types.map(
          (type) => NusaDropdownOption(value: type.value, label: type.label),
        ),
      ],
      decoration: const InputDecoration(
        labelText: 'Jenis lokasi',
        prefixIcon: Icon(Icons.apartment_rounded),
      ),
      enabled: enabled,
      onChanged: (value) {
        if (value != null) onTypeChanged(value);
      },
    );
    final statusField = NusaDropdownField<String>(
      fieldKey: const Key('inventory-location-status-filter'),
      value: page.status,
      options: const [
        NusaDropdownOption(value: 'semua', label: 'Semua status'),
        NusaDropdownOption(value: 'aktif', label: 'Aktif'),
        NusaDropdownOption(value: 'nonaktif', label: 'Nonaktif'),
      ],
      decoration: const InputDecoration(
        labelText: 'Status lokasi',
        prefixIcon: Icon(Icons.toggle_on_outlined),
      ),
      enabled: enabled,
      onChanged: (value) {
        if (value != null) onStatusChanged(value);
      },
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 390) {
          return Column(
            children: [typeField, const SizedBox(height: 8), statusField],
          );
        }
        return Row(
          children: [
            Expanded(child: typeField),
            const SizedBox(width: 8),
            Expanded(child: statusField),
          ],
        );
      },
    );
  }
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

class _LocationResults extends StatelessWidget {
  const _LocationResults({
    required this.page,
    required this.mutating,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onEdit,
    required this.onDeactivate,
  });

  final InventoryLocationPage page;
  final bool mutating;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<InventoryLocation> onEdit;
  final ValueChanged<InventoryLocation> onDeactivate;

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
              Icons.location_on_outlined,
              size: 50,
              color: NusaColors.primary,
            ),
            SizedBox(height: 12),
            Text(
              'Belum ada lokasi barang pada filter ini.',
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
        key: const PageStorageKey<String>('inventory-location-list'),
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
                    '${page.pagination.total} lokasi ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final item = page.items[index];
          return _LocationCard(
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

class _LocationCard extends StatelessWidget {
  const _LocationCard({required this.item, this.onEdit, this.onDeactivate});

  final InventoryLocation item;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('inventory-location-${item.id}'),
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
            child: Icon(_locationIcon(item.type), color: NusaColors.primary),
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
                const SizedBox(height: 5),
                Row(
                  children: [
                    const Icon(
                      Icons.person_outline_rounded,
                      size: 14,
                      color: NusaColors.textSecondary,
                    ),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        item.responsibleEmployee?.name ??
                            'Belum ada penanggung jawab',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                    ),
                  ],
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
                    _Badge(label: item.typeLabel, color: NusaColors.primary),
                    _Badge(
                      label: '${item.goodsCount} jenis barang',
                      color: NusaColors.primaryLight,
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (onEdit != null || onDeactivate != null)
            PopupMenuButton<String>(
              key: Key('inventory-location-menu-${item.id}'),
              tooltip: 'Aksi lokasi barang',
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

IconData _locationIcon(String type) => switch (type) {
  'gudang' => Icons.warehouse_outlined,
  'ruangan' => Icons.meeting_room_outlined,
  'kelas' => Icons.school_outlined,
  _ => Icons.location_on_outlined,
};

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

class _LocationError extends StatelessWidget {
  const _LocationError({required this.message, required this.onRetry});

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
      : 'Lokasi barang belum dapat diproses.';
}
