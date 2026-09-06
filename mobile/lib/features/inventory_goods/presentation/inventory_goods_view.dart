import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_goods/application/inventory_goods_controller.dart';
import 'package:nusa/features/inventory_goods/domain/inventory_goods.dart';
import 'package:nusa/features/inventory_goods/presentation/widgets/inventory_goods_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class InventoryGoodsView extends ConsumerStatefulWidget {
  const InventoryGoodsView({super.key});

  @override
  ConsumerState<InventoryGoodsView> createState() => _InventoryGoodsViewState();
}

class _InventoryGoodsViewState extends ConsumerState<InventoryGoodsView> {
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
    final goods = ref.watch(inventoryGoodsControllerProvider);
    final current = goods.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Inventaris Barang'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: goods.isLoading || _mutating
                ? null
                : ref.read(inventoryGoodsControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-inventory-goods'),
              onPressed: _mutating ? null : () => _openForm(current!),
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
                    _GoodsSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    Row(
                      children: [
                        Expanded(
                          child: NusaTextField(
                            fieldKey: const Key('inventory-goods-search'),
                            controller: _searchController,
                            hintText: 'Nama, kode, atau deskripsi',
                            prefixIcon: Icons.search_rounded,
                            enabled: !goods.isLoading && !_mutating,
                            onChanged: _search,
                            suffixIcon: _searchController.text.isEmpty
                                ? null
                                : IconButton(
                                    tooltip: 'Hapus pencarian',
                                    onPressed: _clearSearch,
                                    icon: const Icon(Icons.close_rounded),
                                  ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Badge(
                          isLabelVisible: _activeFilterCount(current) > 0,
                          label: Text('${_activeFilterCount(current)}'),
                          child: IconButton.filledTonal(
                            key: const Key('inventory-goods-filter'),
                            tooltip: 'Filter inventaris',
                            onPressed: goods.isLoading || _mutating
                                ? null
                                : () => _openFilters(current),
                            icon: const Icon(Icons.tune_rounded),
                          ),
                        ),
                      ],
                    ),
                    if (_activeFilterCount(current) > 0) ...[
                      const SizedBox(height: 7),
                      _ActiveFilters(page: current),
                    ],
                  ],
                ),
              ),
            Expanded(
              child: goods.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _GoodsError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(inventoryGoodsControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _GoodsResults(
                  page: page,
                  mutating: _mutating,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(inventoryGoodsControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onOpen: (item) => _openDetails(page, item),
                  onEdit: (item) => _openForm(page, existing: item),
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
        ref.read(inventoryGoodsControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(inventoryGoodsControllerProvider.notifier).search('');
  }

  Future<void> _openFilters(InventoryGoodsPage page) async {
    var status = page.status;
    var type = page.type;
    var categoryId = page.categoryId ?? 0;
    final value = await showModalBottomSheet<_GoodsFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: NusaColors.outline,
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Filter Inventaris',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 14),
              NusaDropdownField<String>(
                fieldKey: const Key('inventory-goods-filter-status'),
                value: status,
                options: const [
                  NusaDropdownOption(value: 'semua', label: 'Semua status'),
                  NusaDropdownOption(value: 'aktif', label: 'Aktif'),
                  NusaDropdownOption(value: 'nonaktif', label: 'Nonaktif'),
                ],
                decoration: const InputDecoration(
                  labelText: 'Status',
                  prefixIcon: Icon(Icons.toggle_on_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setSheetState(() => status = value);
                },
              ),
              const SizedBox(height: 11),
              NusaDropdownField<String>(
                fieldKey: const Key('inventory-goods-filter-type'),
                value: type,
                options: [
                  const NusaDropdownOption(
                    value: 'semua',
                    label: 'Semua jenis barang',
                  ),
                  ...page.types.map(
                    (item) => NusaDropdownOption(
                      value: item.value,
                      label: item.label,
                    ),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Jenis barang',
                  prefixIcon: Icon(Icons.category_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setSheetState(() => type = value);
                },
              ),
              const SizedBox(height: 11),
              NusaDropdownField<int>(
                fieldKey: const Key('inventory-goods-filter-category'),
                value: categoryId,
                options: [
                  const NusaDropdownOption(value: 0, label: 'Semua kategori'),
                  ...page.categories.map(
                    (item) =>
                        NusaDropdownOption(value: item.id, label: item.label),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Kategori',
                  prefixIcon: Icon(Icons.sell_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setSheetState(() => categoryId = value);
                },
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(
                        context,
                        const _GoodsFilters(status: 'semua', type: 'semua'),
                      ),
                      child: const Text('Reset'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton(
                      key: const Key('apply-inventory-goods-filter'),
                      onPressed: () => Navigator.pop(
                        context,
                        _GoodsFilters(
                          status: status,
                          type: type,
                          categoryId: categoryId == 0 ? null : categoryId,
                        ),
                      ),
                      child: const Text('Terapkan'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
    if (value == null || !mounted) return;
    await ref
        .read(inventoryGoodsControllerProvider.notifier)
        .applyFilters(
          status: value.status,
          type: value.type,
          categoryId: value.categoryId,
        );
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(inventoryGoodsControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm(
    InventoryGoodsPage page, {
    InventoryGoods? existing,
  }) async {
    if (page.categories.isEmpty || page.units.isEmpty) {
      _showError(
        const ValidationException(
          'Isi kategori dan satuan aktif terlebih dahulu sebelum menyimpan barang.',
        ),
      );
      return;
    }
    final value = await showModalBottomSheet<InventoryGoodsFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => InventoryGoodsFormSheet(
        types: page.types,
        categories: page.categories,
        units: page.units,
        locations: page.locations,
        existing: existing,
      ),
    );
    if (value == null || !mounted) return;
    await _runMutation(
      successMessage: existing == null
          ? 'Barang berhasil ditambahkan.'
          : 'Barang berhasil diperbarui.',
      operation: existing == null
          ? () => ref.read(inventoryGoodsActionsProvider).create(value)
          : () => ref
                .read(inventoryGoodsActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _openDetails(
    InventoryGoodsPage page,
    InventoryGoods item,
  ) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          _GoodsDetailSheet(item: item, canManage: page.access.canManage),
    );
    if (!mounted) return;
    if (action == 'edit') await _openForm(page, existing: item);
    if (action == 'deactivate') await _confirmDeactivate(item);
  }

  Future<void> _confirmDeactivate(InventoryGoods item) async {
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan barang?'),
            content: Text(
              '${item.name} tidak akan tersedia pada transaksi baru. '
              'Seluruh unit, saldo, dan riwayat tetap tersimpan.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-inventory-goods-deactivate'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;
    await _runMutation(
      successMessage: 'Barang dinonaktifkan; seluruh riwayat tetap tersimpan.',
      operation: () =>
          ref.read(inventoryGoodsActionsProvider).deactivate(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(inventoryGoodsControllerProvider.notifier).refresh();
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

class _GoodsSummary extends StatelessWidget {
  const _GoodsSummary({required this.summary});

  final InventoryGoodsSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 12),
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
        _SummaryItem(label: 'Aset', value: summary.nonConsumable),
        _SummaryItem(label: 'Habis pakai', value: summary.consumable),
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
            fontSize: 19,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 8.5,
          ),
        ),
      ],
    ),
  );
}

class _ActiveFilters extends StatelessWidget {
  const _ActiveFilters({required this.page});

  final InventoryGoodsPage page;

  @override
  Widget build(BuildContext context) {
    final labels = <String>[];
    if (page.status != 'semua') {
      labels.add(page.status == 'aktif' ? 'Aktif' : 'Nonaktif');
    }
    if (page.type != 'semua') {
      labels.add(_typeLabel(page.types, page.type));
    }
    if (page.categoryId != null) {
      labels.add(_categoryLabel(page.categories, page.categoryId!));
    }
    return Align(
      alignment: Alignment.centerLeft,
      child: Wrap(
        spacing: 6,
        runSpacing: 5,
        children: labels
            .map(
              (label) => Chip(
                visualDensity: VisualDensity.compact,
                label: Text(label, style: const TextStyle(fontSize: 9.5)),
              ),
            )
            .toList(),
      ),
    );
  }
}

class _GoodsResults extends StatelessWidget {
  const _GoodsResults({
    required this.page,
    required this.mutating,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
    required this.onEdit,
    required this.onDeactivate,
  });

  final InventoryGoodsPage page;
  final bool mutating;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<InventoryGoods> onOpen;
  final ValueChanged<InventoryGoods> onEdit;
  final ValueChanged<InventoryGoods> onDeactivate;

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
              Icons.inventory_2_outlined,
              size: 50,
              color: NusaColors.primary,
            ),
            SizedBox(height: 12),
            Text(
              'Belum ada inventaris barang pada filter ini.',
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
        key: const PageStorageKey<String>('inventory-goods-list'),
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
                    '${page.pagination.total} barang ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }
          final item = page.items[index];
          return _GoodsCard(
            item: item,
            onOpen: () => onOpen(item),
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

class _GoodsCard extends StatelessWidget {
  const _GoodsCard({
    required this.item,
    required this.onOpen,
    this.onEdit,
    this.onDeactivate,
  });

  final InventoryGoods item;
  final VoidCallback onOpen;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('inventory-goods-${item.id}'),
    child: InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color:
                    (item.isConsumable ? NusaColors.accent : NusaColors.primary)
                        .withValues(alpha: 0.11),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Icon(
                item.isConsumable
                    ? Icons.layers_outlined
                    : Icons.qr_code_scanner_rounded,
                color: item.isConsumable
                    ? const Color(0xFFB57900)
                    : NusaColors.primary,
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
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    item.code,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    '${item.category.name} · ${item.location?.name ?? 'Tanpa lokasi awal'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 10.5),
                  ),
                  const SizedBox(height: 7),
                  Wrap(
                    spacing: 6,
                    runSpacing: 5,
                    children: [
                      _Badge(label: item.typeLabel, color: NusaColors.primary),
                      _Badge(
                        label: item.quantitySummary,
                        color:
                            item.isConsumable &&
                                item.stockBalance <= item.minimumStock
                            ? const Color(0xFFB45309)
                            : NusaColors.success,
                      ),
                      if (!item.active)
                        const _Badge(
                          label: 'Nonaktif',
                          color: NusaColors.textSecondary,
                        ),
                    ],
                  ),
                ],
              ),
            ),
            if (onEdit != null || onDeactivate != null)
              PopupMenuButton<String>(
                key: Key('inventory-goods-menu-${item.id}'),
                tooltip: 'Aksi inventaris barang',
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
    ),
  );
}

class _GoodsDetailSheet extends StatelessWidget {
  const _GoodsDetailSheet({required this.item, required this.canManage});

  final InventoryGoods item;
  final bool canManage;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: (MediaQuery.sizeOf(context).height * 0.82).clamp(500.0, 760.0),
    child: Column(
      children: [
        const SizedBox(height: 10),
        Container(
          width: 42,
          height: 4,
          decoration: BoxDecoration(
            color: NusaColors.outline,
            borderRadius: BorderRadius.circular(4),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 13, 8, 9),
          child: Row(
            children: [
              const Expanded(
                child: Text(
                  'Detail Inventaris Barang',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                ),
              ),
              IconButton(
                tooltip: 'Tutup',
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [NusaColors.primary, NusaColors.primaryDark],
                  ),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.code,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.72),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      item.name,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 10),
                    _Badge(
                      label: item.active ? 'Aktif' : 'Nonaktif',
                      color: item.active ? NusaColors.success : Colors.white70,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Card(
                margin: EdgeInsets.zero,
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    children: [
                      _DetailRow(label: 'Kategori', value: item.category.name),
                      _DetailRow(label: 'Jenis', value: item.typeLabel),
                      _DetailRow(
                        label: 'Pengelolaan',
                        value: item.managementTypeLabel,
                      ),
                      _DetailRow(label: 'Satuan', value: item.unit.name),
                      _DetailRow(
                        label: 'Lokasi awal',
                        value: item.location?.name ?? 'Belum ditentukan',
                      ),
                      _DetailRow(
                        label: item.isConsumable ? 'Saldo stok' : 'Unit aset',
                        value: item.quantitySummary,
                      ),
                      if (item.isConsumable)
                        _DetailRow(
                          label: 'Stok minimum',
                          value:
                              '${_number(item.minimumStock)} ${item.unit.name}',
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              const Text(
                'Deskripsi',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 5),
              Text(
                item.description?.isNotEmpty == true
                    ? item.description!
                    : 'Tidak ada deskripsi.',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 12,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Text(
                  item.isConsumable
                      ? 'Tahap berikutnya: saldo diperbarui melalui barang datang dan mutasi stok.'
                      : 'Tahap berikutnya: setiap aset dicatat sebagai unit dengan barcode tersendiri.',
                  style: const TextStyle(fontSize: 11.5, height: 1.4),
                ),
              ),
            ],
          ),
        ),
        if (canManage)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: Row(
              children: [
                if (item.active) ...[
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(context, 'deactivate'),
                      child: const Text('Nonaktifkan'),
                    ),
                  ),
                  const SizedBox(width: 10),
                ],
                Expanded(
                  child: FilledButton.icon(
                    key: const Key('edit-inventory-goods-from-detail'),
                    onPressed: () => Navigator.pop(context, 'edit'),
                    icon: const Icon(Icons.edit_outlined),
                    label: const Text('Ubah'),
                  ),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 100,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700),
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
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.11),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800),
    ),
  );
}

class _GoodsError extends StatelessWidget {
  const _GoodsError({required this.message, required this.onRetry});

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

class _GoodsFilters {
  const _GoodsFilters({
    required this.status,
    required this.type,
    this.categoryId,
  });

  final String status;
  final String type;
  final int? categoryId;
}

int _activeFilterCount(InventoryGoodsPage page) =>
    (page.status == 'semua' ? 0 : 1) +
    (page.type == 'semua' ? 0 : 1) +
    (page.categoryId == null ? 0 : 1);

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');

String _typeLabel(List<InventoryGoodsType> items, String value) {
  for (final item in items) {
    if (item.value == value) return item.label;
  }
  return value;
}

String _categoryLabel(List<InventoryGoodsOption> items, int id) {
  for (final item in items) {
    if (item.id == id) return item.name;
  }
  return 'Kategori';
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Inventaris barang belum dapat diproses.';
}
