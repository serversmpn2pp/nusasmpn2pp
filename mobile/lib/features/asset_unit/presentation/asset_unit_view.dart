import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/asset_unit/application/asset_unit_controller.dart';
import 'package:nusa/features/asset_unit/domain/asset_unit.dart';
import 'package:nusa/features/asset_unit/presentation/widgets/asset_unit_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class AssetUnitView extends ConsumerStatefulWidget {
  const AssetUnitView({super.key});

  @override
  ConsumerState<AssetUnitView> createState() => _AssetUnitViewState();
}

class _AssetUnitViewState extends ConsumerState<AssetUnitView> {
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
    final units = ref.watch(assetUnitControllerProvider);
    final current = units.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Unit Aset'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: units.isLoading || _mutating
                ? null
                : ref.read(assetUnitControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-asset-unit'),
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
                    _AssetSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    Row(
                      children: [
                        Expanded(
                          child: NusaTextField(
                            fieldKey: const Key('asset-unit-search'),
                            controller: _searchController,
                            hintText: 'Kode, barang, merek, atau nomor seri',
                            prefixIcon: Icons.search_rounded,
                            enabled: !units.isLoading && !_mutating,
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
                          isLabelVisible: _filterCount(current) > 0,
                          label: Text('${_filterCount(current)}'),
                          child: IconButton.filledTonal(
                            key: const Key('asset-unit-filter'),
                            tooltip: 'Filter unit aset',
                            onPressed: units.isLoading || _mutating
                                ? null
                                : () => _openFilters(current),
                            icon: const Icon(Icons.tune_rounded),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            Expanded(
              child: units.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _ErrorState(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(assetUnitControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _AssetResults(
                  page: page,
                  mutating: _mutating,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(assetUnitControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onOpen: (item) => _openDetail(page, item),
                  onEdit: (item) => _edit(page, item.id),
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
      if (mounted) ref.read(assetUnitControllerProvider.notifier).search(value);
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(assetUnitControllerProvider.notifier).search('');
  }

  Future<void> _openFilters(AssetUnitPage page) async {
    final filters = await showModalBottomSheet<_AssetFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _AssetFilterSheet(page: page),
    );
    if (filters == null || !mounted) return;
    await ref
        .read(assetUnitControllerProvider.notifier)
        .applyFilters(
          dataStatus: filters.dataStatus,
          condition: filters.condition,
          unitStatus: filters.unitStatus,
          goodsId: filters.goodsId,
          locationId: filters.locationId,
        );
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(assetUnitControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm(AssetUnitPage page, {AssetUnit? existing}) async {
    if (page.goods.where((item) => item.active).isEmpty && existing == null) {
      _showError(
        const ValidationException(
          'Tambahkan barang aset individual aktif terlebih dahulu.',
        ),
      );
      return;
    }
    final value = await showModalBottomSheet<AssetUnitFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => AssetUnitFormSheet(page: page, existing: existing),
    );
    if (value == null || !mounted) return;
    await _runMutation(
      successMessage: existing == null
          ? '${value.quantity} unit aset berhasil ditambahkan.'
          : 'Unit aset berhasil diperbarui.',
      operation: existing == null
          ? () => ref.read(assetUnitActionsProvider).create(value)
          : () => ref
                .read(assetUnitActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _edit(AssetUnitPage page, int id) async {
    final unit = await _fetchDetail(id);
    if (unit != null && mounted) await _openForm(page, existing: unit);
  }

  Future<void> _openDetail(AssetUnitPage page, AssetUnit item) async {
    final unit = await _fetchDetail(item.id);
    if (unit == null || !mounted) return;
    final action = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          _AssetDetailSheet(unit: unit, canManage: page.access.canManage),
    );
    if (!mounted) return;
    if (action == 'edit') await _openForm(page, existing: unit);
    if (action == 'deactivate') await _confirmDeactivate(unit);
  }

  Future<AssetUnit?> _fetchDetail(int id) async {
    setState(() => _mutating = true);
    try {
      return await ref.read(assetUnitActionsProvider).detail(id);
    } catch (error) {
      if (mounted) _showError(error);
      return null;
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _confirmDeactivate(AssetUnit unit) async {
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan unit aset?'),
            content: Text(
              '${unit.goods.name} (${unit.inventoryCode}) tidak akan tersedia '
              'untuk transaksi baru. Riwayatnya tetap tersimpan.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-asset-unit-deactivate'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;
    await _runMutation(
      successMessage: 'Unit aset dinonaktifkan; riwayat tetap tersimpan.',
      operation: () => ref.read(assetUnitActionsProvider).deactivate(unit.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(assetUnitControllerProvider.notifier).refresh();
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

class _AssetSummary extends StatelessWidget {
  const _AssetSummary({required this.summary});

  final AssetUnitSummary summary;

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
        _SummaryItem(label: 'Tersedia', value: summary.available),
        _SummaryItem(label: 'Perhatian', value: summary.needsAttention),
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

class _AssetResults extends StatelessWidget {
  const _AssetResults({
    required this.page,
    required this.mutating,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
    required this.onEdit,
    required this.onDeactivate,
  });

  final AssetUnitPage page;
  final bool mutating;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<AssetUnit> onOpen;
  final ValueChanged<AssetUnit> onEdit;
  final ValueChanged<AssetUnit> onDeactivate;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: const [
            Icon(Icons.qr_code_2_rounded, size: 50, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada unit aset pada filter ini.',
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
        key: const PageStorageKey<String>('asset-unit-list'),
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
                    '${page.pagination.total} unit ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }
          final item = page.items[index];
          return _AssetCard(
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

class _AssetCard extends StatelessWidget {
  const _AssetCard({
    required this.item,
    required this.onOpen,
    this.onEdit,
    this.onDeactivate,
  });

  final AssetUnit item;
  final VoidCallback onOpen;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(item.unitStatus);
    return Card(
      key: Key('asset-unit-${item.id}'),
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
                  color: statusColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(Icons.qr_code_2_rounded, color: statusColor),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.goods.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      item.goodsUnitCode,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      'ID NUSA ${item.inventoryCode}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 9.5,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${item.location?.name ?? 'Tanpa lokasi'} · '
                      '${item.brandModel.isEmpty ? 'Merek/tipe belum diisi' : item.brandModel}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 10.5),
                    ),
                    const SizedBox(height: 7),
                    Wrap(
                      spacing: 6,
                      runSpacing: 5,
                      children: [
                        _Badge(label: item.unitStatusLabel, color: statusColor),
                        _Badge(
                          label: item.conditionLabel,
                          color: _conditionColor(item.condition),
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
                  key: Key('asset-unit-menu-${item.id}'),
                  tooltip: 'Aksi unit aset',
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
}

class _AssetFilterSheet extends StatefulWidget {
  const _AssetFilterSheet({required this.page});

  final AssetUnitPage page;

  @override
  State<_AssetFilterSheet> createState() => _AssetFilterSheetState();
}

class _AssetFilterSheetState extends State<_AssetFilterSheet> {
  late String _dataStatus;
  late String _condition;
  late String _unitStatus;
  late int _goodsId;
  late int _locationId;

  @override
  void initState() {
    super.initState();
    _dataStatus = widget.page.dataStatus;
    _condition = widget.page.condition;
    _unitStatus = widget.page.unitStatus;
    _goodsId = widget.page.goodsId ?? 0;
    _locationId = widget.page.locationId ?? 0;
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    height: (MediaQuery.sizeOf(context).height * 0.82).clamp(490.0, 720.0),
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
        const Padding(
          padding: EdgeInsets.fromLTRB(16, 14, 16, 8),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Filter Unit Aset',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
            ),
          ),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            children: [
              NusaDropdownField<int>(
                fieldKey: const Key('asset-unit-filter-goods'),
                value: _goodsId,
                options: [
                  const NusaDropdownOption(value: 0, label: 'Semua barang'),
                  ...widget.page.goods.map(
                    (item) =>
                        NusaDropdownOption(value: item.id, label: item.label),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Barang',
                  prefixIcon: Icon(Icons.inventory_2_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setState(() => _goodsId = value);
                },
              ),
              const SizedBox(height: 11),
              NusaDropdownField<int>(
                fieldKey: const Key('asset-unit-filter-location'),
                value: _locationId,
                options: [
                  const NusaDropdownOption(value: 0, label: 'Semua lokasi'),
                  ...widget.page.locations.map(
                    (item) =>
                        NusaDropdownOption(value: item.id, label: item.label),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Lokasi',
                  prefixIcon: Icon(Icons.location_on_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setState(() => _locationId = value);
                },
              ),
              const SizedBox(height: 11),
              NusaDropdownField<String>(
                fieldKey: const Key('asset-unit-filter-condition'),
                value: _condition,
                options: [
                  const NusaDropdownOption(
                    value: 'semua',
                    label: 'Semua kondisi',
                  ),
                  ...widget.page.conditions.map(
                    (item) => NusaDropdownOption(
                      value: item.value,
                      label: item.label,
                    ),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Kondisi',
                  prefixIcon: Icon(Icons.health_and_safety_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setState(() => _condition = value);
                },
              ),
              const SizedBox(height: 11),
              NusaDropdownField<String>(
                fieldKey: const Key('asset-unit-filter-unit-status'),
                value: _unitStatus,
                options: [
                  const NusaDropdownOption(
                    value: 'semua',
                    label: 'Semua status unit',
                  ),
                  ...widget.page.statuses.map(
                    (item) => NusaDropdownOption(
                      value: item.value,
                      label: item.label,
                    ),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Status unit',
                  prefixIcon: Icon(Icons.fact_check_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setState(() => _unitStatus = value);
                },
              ),
              const SizedBox(height: 11),
              NusaDropdownField<String>(
                fieldKey: const Key('asset-unit-filter-data-status'),
                value: _dataStatus,
                options: const [
                  NusaDropdownOption(value: 'semua', label: 'Semua data'),
                  NusaDropdownOption(value: 'aktif', label: 'Aktif'),
                  NusaDropdownOption(value: 'nonaktif', label: 'Nonaktif'),
                ],
                decoration: const InputDecoration(
                  labelText: 'Status data',
                  prefixIcon: Icon(Icons.toggle_on_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setState(() => _dataStatus = value);
                },
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.pop(
                    context,
                    const _AssetFilters(
                      dataStatus: 'semua',
                      condition: 'semua',
                      unitStatus: 'semua',
                    ),
                  ),
                  child: const Text('Reset'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton(
                  key: const Key('apply-asset-unit-filter'),
                  onPressed: () => Navigator.pop(
                    context,
                    _AssetFilters(
                      dataStatus: _dataStatus,
                      condition: _condition,
                      unitStatus: _unitStatus,
                      goodsId: _goodsId == 0 ? null : _goodsId,
                      locationId: _locationId == 0 ? null : _locationId,
                    ),
                  ),
                  child: const Text('Terapkan'),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _AssetDetailSheet extends StatelessWidget {
  const _AssetDetailSheet({required this.unit, required this.canManage});

  final AssetUnit unit;
  final bool canManage;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: (MediaQuery.sizeOf(context).height * 0.92).clamp(560.0, 820.0),
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
                  'Detail Unit Aset',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
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
            key: const Key('asset-unit-detail-scroll'),
            padding: const EdgeInsets.all(16),
            children: [
              _DetailHero(unit: unit),
              if (unit.activeLoan != null) ...[
                const SizedBox(height: 12),
                _LoanCard(loan: unit.activeLoan!),
              ],
              const SizedBox(height: 14),
              _DetailSection(
                title: 'Identitas dan kondisi',
                rows: [
                  ('Barang', unit.goods.name),
                  ('Kode master', unit.goods.code),
                  ('Kategori', unit.goods.category),
                  ('Nomor seri', unit.serialNumber ?? '-'),
                  (
                    'Merek/tipe',
                    unit.brandModel.isEmpty ? '-' : unit.brandModel,
                  ),
                  ('Lokasi', unit.location?.name ?? '-'),
                  ('Kondisi', unit.conditionLabel),
                  ('Status unit', unit.unitStatusLabel),
                ],
              ),
              const SizedBox(height: 12),
              _DetailSection(
                title: 'Perolehan dan catatan',
                rows: [
                  ('Tanggal', _dateLabelNullable(unit.acquisitionDate)),
                  ('Tahun', unit.acquisitionYear?.toString() ?? '-'),
                  ('Sumber', unit.sourceName),
                  ('Harga', _currency(unit.acquisitionPrice)),
                  ('Keterangan', unit.notes ?? '-'),
                ],
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Riwayat Aset',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  _Badge(
                    label: '${unit.history.length} peristiwa',
                    color: NusaColors.primary,
                  ),
                ],
              ),
              const SizedBox(height: 9),
              if (unit.history.isEmpty)
                const Text('Belum ada riwayat aset.')
              else
                ...unit.history.map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 9),
                    child: _HistoryCard(item: item),
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
                if (unit.active) ...[
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
                    key: const Key('edit-asset-unit-from-detail'),
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

class _DetailHero extends StatelessWidget {
  const _DetailHero({required this.unit});

  final AssetUnit unit;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        Container(
          width: 54,
          height: 54,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(15),
          ),
          child: const Icon(Icons.qr_code_2_rounded, color: NusaColors.accent),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                unit.goods.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                unit.goodsUnitCode,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.78),
                  fontSize: 11,
                ),
              ),
              Text(
                'ID NUSA ${unit.inventoryCode}',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.68),
                  fontSize: 10,
                ),
              ),
              if (unit.officialAssetNumber != null)
                Text(
                  'Aset ${unit.officialAssetNumber}',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.68),
                    fontSize: 9.5,
                  ),
                ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _LoanCard extends StatelessWidget {
  const _LoanCard({required this.loan});

  final AssetActiveLoan loan;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF7E6),
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.accent.withValues(alpha: 0.45)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.person_pin_circle_outlined, color: Color(0xFFB57900)),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Sedang dipinjam oleh ${loan.borrower}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 3),
              Text(
                '${loan.number} · ${loan.identity}',
                style: const TextStyle(fontSize: 10.5),
              ),
              Text(
                loan.monitoring,
                style: const TextStyle(
                  color: Color(0xFFB57900),
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _DetailSection extends StatelessWidget {
  const _DetailSection({required this.title, required this.rows});

  final String title;
  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          ...rows.map(
            (row) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 5),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 92,
                    child: Text(
                      row.$1,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      row.$2,
                      textAlign: TextAlign.right,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.item});

  final AssetHistory item;

  @override
  Widget build(BuildContext context) {
    final color = switch (item.type) {
      'pengembalian' => NusaColors.success,
      'peminjaman' => const Color(0xFFB57900),
      _ => NusaColors.primary,
    };
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 9,
              height: 9,
              margin: const EdgeInsets.only(top: 5),
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          item.title,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                      Text(
                        _dateLabelNullable(item.date),
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 9,
                        ),
                      ),
                    ],
                  ),
                  if (item.description.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(
                      item.description,
                      style: const TextStyle(fontSize: 10.5, height: 1.35),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
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

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

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

class _AssetFilters {
  const _AssetFilters({
    required this.dataStatus,
    required this.condition,
    required this.unitStatus,
    this.goodsId,
    this.locationId,
  });

  final String dataStatus;
  final String condition;
  final String unitStatus;
  final int? goodsId;
  final int? locationId;
}

int _filterCount(AssetUnitPage page) =>
    (page.dataStatus == 'semua' ? 0 : 1) +
    (page.condition == 'semua' ? 0 : 1) +
    (page.unitStatus == 'semua' ? 0 : 1) +
    (page.goodsId == null ? 0 : 1) +
    (page.locationId == null ? 0 : 1);

Color _statusColor(String status) => switch (status) {
  'tersedia' => NusaColors.success,
  'dipinjam' => const Color(0xFF2563EB),
  'dalam_perbaikan' => const Color(0xFFB57900),
  'hilang' || 'dihapuskan' => Colors.red.shade700,
  _ => NusaColors.textSecondary,
};

Color _conditionColor(String condition) => switch (condition) {
  'baik' => NusaColors.success,
  'rusak_ringan' => const Color(0xFFB57900),
  'rusak_berat' => Colors.red.shade700,
  _ => NusaColors.textSecondary,
};

String _currency(double? value) {
  if (value == null) return '-';
  final digits = value.round().toString();
  final buffer = StringBuffer();
  for (var index = 0; index < digits.length; index++) {
    if (index > 0 && (digits.length - index) % 3 == 0) buffer.write('.');
    buffer.write(digits[index]);
  }
  return 'Rp $buffer';
}

String _dateLabelNullable(DateTime? value) {
  if (value == null) return '-';
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${value.day} ${months[value.month - 1]} ${value.year}';
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Unit aset belum dapat diproses.';
}
