import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/stock/application/stock_controller.dart';
import 'package:nusa/features/stock/domain/stock.dart';
import 'package:nusa/features/stock/presentation/widgets/stock_movement_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StockMovementView extends ConsumerStatefulWidget {
  const StockMovementView({
    this.initialGoodsId,
    this.initialLocationId,
    this.openForm = false,
    super.key,
  });

  final int? initialGoodsId;
  final int? initialLocationId;
  final bool openForm;

  @override
  ConsumerState<StockMovementView> createState() => _StockMovementViewState();
}

class _StockMovementViewState extends ConsumerState<StockMovementView> {
  final _search = TextEditingController();
  Timer? _debounce;
  bool _mutating = false;
  bool _loadingMore = false;
  bool _autoFormStarted = false;
  late bool _initialFiltersPending;

  @override
  void initState() {
    super.initState();
    _initialFiltersPending =
        widget.initialGoodsId != null || widget.initialLocationId != null;
    if (_initialFiltersPending) {
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        await ref
            .read(stockMovementControllerProvider.notifier)
            .applyFilters(
              type: 'semua',
              goodsId: widget.initialGoodsId,
              locationId: widget.initialLocationId,
              startDate: null,
              endDate: null,
            );
        if (mounted) setState(() => _initialFiltersPending = false);
      });
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(stockMovementControllerProvider);
    final page = state.value;
    if (widget.openForm &&
        !_initialFiltersPending &&
        !_autoFormStarted &&
        page != null &&
        page.access.canManage) {
      _autoFormStarted = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _create(page);
      });
    }
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Mutasi Stok'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading || _mutating
                ? null
                : ref.read(stockMovementControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: page?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-stock-movement'),
              onPressed: _mutating ? null : () => _create(page!),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Catat'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (page != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _MovementSummary(summary: page.summary),
                    const SizedBox(height: 9),
                    Row(
                      children: [
                        Expanded(
                          child: NusaTextField(
                            fieldKey: const Key('stock-movement-search'),
                            controller: _search,
                            hintText: 'Cari barang atau referensi',
                            prefixIcon: Icons.search_rounded,
                            enabled: !state.isLoading && !_mutating,
                            onChanged: _onSearch,
                            suffixIcon: _search.text.isEmpty
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
                          isLabelVisible: _filterCount(page) > 0,
                          label: Text('${_filterCount(page)}'),
                          child: IconButton.filledTonal(
                            key: const Key('stock-movement-filter'),
                            tooltip: 'Filter',
                            onPressed: state.isLoading || _mutating
                                ? null
                                : () => _openFilters(page),
                            icon: const Icon(Icons.tune_rounded),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            Expanded(
              child: state.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _ErrorState(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(stockMovementControllerProvider.notifier)
                      .refresh,
                ),
                data: _content,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _content(StockMovementPage page) => RefreshIndicator(
    onRefresh: ref.read(stockMovementControllerProvider.notifier).refresh,
    child: page.items.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(28),
            children: const [
              SizedBox(height: 72),
              Icon(
                Icons.swap_horiz_rounded,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada mutasi stok yang sesuai.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.separated(
            key: const Key('stock-movement-list'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
            itemCount:
                page.items.length + (page.pagination.hasNextPage ? 1 : 0),
            separatorBuilder: (context, index) => const SizedBox(height: 9),
            itemBuilder: (context, index) {
              if (index == page.items.length) {
                return Center(
                  child: TextButton.icon(
                    onPressed: _loadingMore ? null : _loadMore,
                    icon: _loadingMore
                        ? const SizedBox.square(
                            dimension: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.expand_more_rounded),
                    label: const Text('Muat lebih banyak'),
                  ),
                );
              }
              return _MovementCard(
                item: page.items[index],
                onTap: _mutating
                    ? null
                    : () => _openDetail(page.items[index].id),
              );
            },
          ),
  );

  void _onSearch(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 450),
      () => ref.read(stockMovementControllerProvider.notifier).search(value),
    );
  }

  void _clearSearch() {
    _debounce?.cancel();
    _search.clear();
    setState(() {});
    ref.read(stockMovementControllerProvider.notifier).search('');
  }

  Future<void> _create(StockMovementPage page) async {
    final value = await showModalBottomSheet<StockMovementFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => StockMovementFormSheet(
        page: page,
        initialGoodsId: widget.initialGoodsId ?? page.goodsId,
        initialLocationId: widget.initialLocationId ?? page.locationId,
      ),
    );
    if (value == null || !mounted) return;
    setState(() => _mutating = true);
    try {
      await ref.read(stockActionsProvider).create(value);
      await ref.read(stockMovementControllerProvider.notifier).refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Mutasi stok berhasil dicatat.')),
        );
      }
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _openDetail(int id) async {
    setState(() => _mutating = true);
    try {
      final item = await ref.read(stockActionsProvider).detail(id);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        builder: (context) => _MovementDetail(item: item),
      );
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _openFilters(StockMovementPage page) async {
    final result = await showModalBottomSheet<_MovementFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _MovementFilterSheet(page: page),
    );
    if (result == null || !mounted) return;
    await ref
        .read(stockMovementControllerProvider.notifier)
        .applyFilters(
          type: result.type,
          goodsId: result.goodsId,
          locationId: result.locationId,
          startDate: result.startDate,
          endDate: result.endDate,
        );
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      await ref.read(stockMovementControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _showError(Object error) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(_errorMessage(error))));
}

class _MovementSummary extends StatelessWidget {
  const _MovementSummary({required this.summary});
  final StockMovementSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 12),
    child: Row(
      children: [
        _SummaryItem(value: '${summary.total}', label: 'Total'),
        _SummaryItem(value: '${summary.today}', label: 'Hari ini'),
        _SummaryItem(value: _number(summary.inToday), label: 'Masuk'),
        _SummaryItem(value: _number(summary.outToday), label: 'Keluar'),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.value, required this.label});
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: Color(0xFFCADBED), fontSize: 9),
        ),
      ],
    ),
  );
}

class _MovementCard extends StatelessWidget {
  const _MovementCard({required this.item, required this.onTap});
  final StockMovement item;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final color = _movementColor(item, context);
    return Card(
      child: InkWell(
        key: Key('stock-movement-${item.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 43,
                height: 43,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(_movementIcon(item.type), color: color),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            item.goods.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        const SizedBox(width: 7),
                        Text(
                          '${item.change > 0 ? '+' : ''}${_number(item.change)}',
                          style: TextStyle(
                            color: color,
                            fontSize: 16,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${item.categoryLabel} · ${item.location.name}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${item.dateLabel}${item.reference?.trim().isNotEmpty == true ? ' · ${item.reference}' : ''}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 11),
                          ),
                        ),
                        Text(
                          '${_number(item.before)} → ${_number(item.after)} ${item.goods.unit}',
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MovementDetail extends StatelessWidget {
  const _MovementDetail({required this.item});
  final StockMovement item;

  @override
  Widget build(BuildContext context) {
    final color = _movementColor(item, context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
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
              'Detail Mutasi Stok',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Row(
                children: [
                  Icon(_movementIcon(item.type), color: color, size: 32),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.typeLabel,
                          style: TextStyle(
                            color: color,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          '${item.change > 0 ? '+' : ''}${_number(item.change)} ${item.goods.unit}',
                          style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _DetailRow(
              label: 'Barang',
              value: '${item.goods.name} · ${item.goods.code}',
            ),
            _DetailRow(label: 'Lokasi', value: item.location.name),
            _DetailRow(label: 'Kategori', value: item.categoryLabel),
            _DetailRow(label: 'Tanggal', value: item.dateLabel),
            _DetailRow(
              label: 'Perubahan saldo',
              value:
                  '${_number(item.before)} → ${_number(item.after)} ${item.goods.unit}',
            ),
            _DetailRow(label: 'Referensi', value: item.reference ?? '-'),
            _DetailRow(label: 'Dicatat oleh', value: item.createdBy),
            if (item.notes?.trim().isNotEmpty == true)
              _DetailRow(label: 'Keterangan', value: item.notes!),
            const SizedBox(height: 8),
            const Text(
              'Catatan ini merupakan jejak audit dan tidak dapat diubah atau dihapus.',
              style: TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11,
                height: 1.4,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 112,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 12,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _MovementFilterSheet extends StatefulWidget {
  const _MovementFilterSheet({required this.page});
  final StockMovementPage page;

  @override
  State<_MovementFilterSheet> createState() => _MovementFilterSheetState();
}

class _MovementFilterSheetState extends State<_MovementFilterSheet> {
  late String _type;
  int? _goodsId;
  int? _locationId;
  DateTime? _startDate;
  DateTime? _endDate;

  @override
  void initState() {
    super.initState();
    _type = widget.page.type;
    _goodsId = widget.page.goodsId;
    _locationId = widget.page.locationId;
    _startDate = widget.page.startDate;
    _endDate = widget.page.endDate;
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 18),
    child: SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Filter Mutasi Stok',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 16),
          NusaDropdownField<String>(
            fieldKey: const Key('stock-movement-type-filter'),
            value: _type,
            options: [
              const NusaDropdownOption(value: 'semua', label: 'Semua jenis'),
              ...widget.page.typeOptions.map(
                (item) =>
                    NusaDropdownOption(value: item.value, label: item.label),
              ),
            ],
            decoration: const InputDecoration(labelText: 'Jenis mutasi'),
            onChanged: (value) => setState(() => _type = value ?? 'semua'),
          ),
          const SizedBox(height: 12),
          NusaDropdownField<int>(
            fieldKey: const Key('stock-movement-goods-filter'),
            value: _goodsId ?? 0,
            options: [
              const NusaDropdownOption(value: 0, label: 'Semua barang'),
              ...widget.page.goods.map(
                (item) => NusaDropdownOption(value: item.id, label: item.label),
              ),
            ],
            decoration: const InputDecoration(labelText: 'Barang'),
            onChanged: (value) =>
                setState(() => _goodsId = value == 0 ? null : value),
          ),
          const SizedBox(height: 12),
          NusaDropdownField<int>(
            fieldKey: const Key('stock-movement-location-filter'),
            value: _locationId ?? 0,
            options: [
              const NusaDropdownOption(value: 0, label: 'Semua lokasi'),
              ...widget.page.locations.map(
                (item) => NusaDropdownOption(value: item.id, label: item.label),
              ),
            ],
            decoration: const InputDecoration(labelText: 'Lokasi'),
            onChanged: (value) =>
                setState(() => _locationId = value == 0 ? null : value),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            key: const Key('stock-movement-date-filter'),
            onPressed: _pickRange,
            icon: const Icon(Icons.date_range_outlined),
            label: Text(
              _startDate == null || _endDate == null
                  ? 'Semua tanggal'
                  : '${_shortDate(_startDate!)} – ${_shortDate(_endDate!)}',
            ),
          ),
          if (_startDate != null || _endDate != null)
            TextButton(
              onPressed: () => setState(() {
                _startDate = null;
                _endDate = null;
              }),
              child: const Text('Hapus rentang tanggal'),
            ),
          const SizedBox(height: 10),
          FilledButton.icon(
            key: const Key('apply-stock-movement-filter'),
            onPressed: () => Navigator.pop(
              context,
              _MovementFilters(
                type: _type,
                goodsId: _goodsId,
                locationId: _locationId,
                startDate: _startDate,
                endDate: _endDate,
              ),
            ),
            icon: const Icon(Icons.check_rounded),
            label: const Text('Terapkan Filter'),
          ),
        ],
      ),
    ),
  );

  Future<void> _pickRange() async {
    final now = DateTime.now();
    final result = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2000),
      lastDate: now.add(const Duration(days: 3650)),
      initialDateRange: _startDate != null && _endDate != null
          ? DateTimeRange(start: _startDate!, end: _endDate!)
          : null,
    );
    if (result != null) {
      setState(() {
        _startDate = result.start;
        _endDate = result.end;
      });
    }
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48),
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

class _MovementFilters {
  const _MovementFilters({
    required this.type,
    this.goodsId,
    this.locationId,
    this.startDate,
    this.endDate,
  });
  final String type;
  final int? goodsId;
  final int? locationId;
  final DateTime? startDate;
  final DateTime? endDate;
}

int _filterCount(StockMovementPage page) =>
    (page.type == 'semua' ? 0 : 1) +
    (page.goodsId == null ? 0 : 1) +
    (page.locationId == null ? 0 : 1) +
    (page.startDate == null ? 0 : 1) +
    (page.endDate == null ? 0 : 1);

IconData _movementIcon(String type) => switch (type) {
  'masuk' => Icons.south_west_rounded,
  'keluar' => Icons.north_east_rounded,
  _ => Icons.tune_rounded,
};

Color _movementColor(StockMovement item, BuildContext context) =>
    switch (item.type) {
      'masuk' => NusaColors.success,
      'keluar' => Theme.of(context).colorScheme.error,
      _ => const Color(0xFFE38A00),
    };

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');

String _shortDate(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/'
    '${value.month.toString().padLeft(2, '0')}/${value.year}';

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Mutasi stok belum dapat diproses.';
}
