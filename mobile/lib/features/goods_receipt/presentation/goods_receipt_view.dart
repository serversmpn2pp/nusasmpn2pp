import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_receipt/application/goods_receipt_controller.dart';
import 'package:nusa/features/goods_receipt/domain/goods_receipt.dart';
import 'package:nusa/features/goods_receipt/presentation/widgets/goods_receipt_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsReceiptView extends ConsumerStatefulWidget {
  const GoodsReceiptView({super.key});

  @override
  ConsumerState<GoodsReceiptView> createState() => _GoodsReceiptViewState();
}

class _GoodsReceiptViewState extends ConsumerState<GoodsReceiptView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _mutating = false;
  bool _loadingMore = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(goodsReceiptControllerProvider);
    final page = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Barang Datang'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading || _mutating
                ? null
                : ref.read(goodsReceiptControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: page?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-goods-receipt'),
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
                    _SummaryCard(summary: page.summary),
                    const SizedBox(height: 9),
                    Row(
                      children: [
                        Expanded(
                          child: NusaTextField(
                            fieldKey: const Key('goods-receipt-search'),
                            controller: _searchController,
                            hintText: 'Cari penerimaan',
                            prefixIcon: Icons.search_rounded,
                            enabled: !state.isLoading && !_mutating,
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
                          isLabelVisible: _activeFilterCount(page) > 0,
                          label: Text('${_activeFilterCount(page)}'),
                          child: IconButton.filledTonal(
                            key: const Key('goods-receipt-filter'),
                            tooltip: 'Filter',
                            onPressed: state.isLoading || _mutating
                                ? null
                                : () => _filter(page),
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
                      .read(goodsReceiptControllerProvider.notifier)
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

  Widget _content(GoodsReceiptPage page) => RefreshIndicator(
    onRefresh: ref.read(goodsReceiptControllerProvider.notifier).refresh,
    child: page.items.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(28),
            children: const [
              SizedBox(height: 70),
              Icon(
                Icons.move_to_inbox_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada barang datang yang sesuai.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.separated(
            key: const Key('goods-receipt-list'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 92),
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
              return _ReceiptCard(
                receipt: page.items[index],
                onTap: _mutating
                    ? null
                    : () => _openDetail(page.items[index].id),
              );
            },
          ),
  );

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 450),
      () => ref.read(goodsReceiptControllerProvider.notifier).search(value),
    );
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(goodsReceiptControllerProvider.notifier).search('');
  }

  Future<void> _filter(GoodsReceiptPage page) async {
    final filters = await showModalBottomSheet<_ReceiptFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _FilterSheet(page: page),
    );
    if (filters == null || !mounted) return;
    await ref
        .read(goodsReceiptControllerProvider.notifier)
        .applyFilters(
          sourceId: filters.sourceId,
          startDate: filters.startDate,
          endDate: filters.endDate,
        );
  }

  Future<void> _create(GoodsReceiptPage page) async {
    final value = await showModalBottomSheet<GoodsReceiptFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => GoodsReceiptFormSheet(
        sources: page.sources.where((item) => item.active).toList(),
        goods: page.goods,
        locations: page.locations,
        acquisitionMethods: page.acquisitionMethods,
        conditions: page.conditions,
      ),
    );
    if (value == null || !mounted) return;
    await _mutate(
      () => ref.read(goodsReceiptActionsProvider).create(value),
      success: 'Barang datang berhasil dicatat.',
    );
  }

  Future<void> _openDetail(int id) async {
    setState(() => _mutating = true);
    try {
      final result = await ref.read(goodsReceiptActionsProvider).detail(id);
      if (!mounted) return;
      final action = await showModalBottomSheet<_DetailAction>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        builder: (context) => _DetailSheet(
          receipt: result.receipt,
          canCancel: result.access.canCancel,
        ),
      );
      if (!mounted || action == null) return;
      if (action == _DetailAction.labels) {
        await context.push(
          '/label-inventaris?penerimaan_barang_id=${result.receipt.id}',
        );
      } else if (action == _DetailAction.cancel) {
        await _cancel(result.receipt);
      }
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _cancel(GoodsReceipt receipt) async {
    final reason = await showDialog<String>(
      context: context,
      builder: (context) => _CancelDialog(number: receipt.number),
    );
    if (reason == null || !mounted) return;
    await _mutate(
      () => ref
          .read(goodsReceiptActionsProvider)
          .cancel(id: receipt.id, reason: reason),
      success: 'Penerimaan dibatalkan; stok dan Unit Aset telah dikoreksi.',
    );
  }

  Future<void> _mutate(
    Future<Object?> Function() action, {
    required String success,
  }) async {
    setState(() => _mutating = true);
    try {
      await action();
      await ref.read(goodsReceiptControllerProvider.notifier).refresh();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(success)));
      }
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      await ref.read(goodsReceiptControllerProvider.notifier).loadMore();
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

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});

  final GoodsReceiptSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 12),
    child: Row(
      children: [
        _SummaryItem(value: summary.total, label: 'Total'),
        _SummaryItem(value: summary.today, label: 'Hari ini'),
        _SummaryItem(value: summary.assetUnitsCreated, label: 'Unit aset'),
        _SummaryItem(value: summary.stockKindsEntered, label: 'Stok masuk'),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.value, required this.label});

  final int value;
  final String label;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 17,
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

class _ReceiptCard extends StatelessWidget {
  const _ReceiptCard({required this.receipt, required this.onTap});

  final GoodsReceipt receipt;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final color = receipt.cancelled
        ? Theme.of(context).colorScheme.error
        : NusaColors.success;
    return Card(
      child: InkWell(
        key: Key('goods-receipt-${receipt.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  receipt.cancelled
                      ? Icons.block_rounded
                      : Icons.move_to_inbox_rounded,
                  color: color,
                ),
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
                            receipt.number,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        _StatusBadge(label: receipt.statusLabel, color: color),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${receipt.dateLabel} · ${receipt.source?.name ?? '-'} · ${receipt.acquisitionMethodLabel}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                        height: 1.35,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(
                          Icons.inventory_2_outlined,
                          size: 15,
                          color: NusaColors.primary,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${receipt.detailCount} jenis',
                          style: const TextStyle(fontSize: 11),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _currency(receipt.totalValue),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            textAlign: TextAlign.right,
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 3),
              const Icon(
                Icons.chevron_right_rounded,
                color: NusaColors.textSecondary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DetailSheet extends StatelessWidget {
  const _DetailSheet({required this.receipt, required this.canCancel});

  final GoodsReceipt receipt;
  final bool canCancel;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: (MediaQuery.sizeOf(context).height * 0.93).clamp(580.0, 900.0),
    child: Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 8, 8),
          child: Row(
            children: [
              const Expanded(
                child: Text(
                  'Rincian Barang Datang',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                ),
              ),
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: ListView(
            key: const Key('goods-receipt-detail-scroll'),
            padding: const EdgeInsets.all(16),
            children: [
              _DetailHeader(receipt: receipt),
              const SizedBox(height: 12),
              _InfoCard(receipt: receipt),
              if (receipt.cancelled) ...[
                const SizedBox(height: 12),
                _CancellationCard(receipt: receipt),
              ],
              const SizedBox(height: 18),
              const Text(
                'Barang yang Diterima',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              for (var index = 0; index < receipt.details.length; index++) ...[
                _DetailLine(index: index, detail: receipt.details[index]),
                if (index < receipt.details.length - 1)
                  const SizedBox(height: 9),
              ],
              const SizedBox(height: 16),
              OutlinedButton.icon(
                key: const Key('goods-receipt-labels'),
                onPressed: receipt.cancelled
                    ? null
                    : () => Navigator.pop(context, _DetailAction.labels),
                icon: const Icon(Icons.qr_code_2_rounded),
                label: const Text('Buka Label Penerimaan'),
              ),
              if (canCancel) ...[
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  key: const Key('cancel-goods-receipt'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Theme.of(context).colorScheme.error,
                  ),
                  onPressed: () => Navigator.pop(context, _DetailAction.cancel),
                  icon: const Icon(Icons.undo_rounded),
                  label: const Text('Batalkan Penerimaan'),
                ),
              ],
            ],
          ),
        ),
      ],
    ),
  );
}

class _DetailHeader extends StatelessWidget {
  const _DetailHeader({required this.receipt});
  final GoodsReceipt receipt;

  @override
  Widget build(BuildContext context) {
    final color = receipt.cancelled
        ? Theme.of(context).colorScheme.error
        : NusaColors.success;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          const CircleAvatar(
            backgroundColor: Colors.white12,
            child: Icon(Icons.move_to_inbox_rounded, color: Colors.white),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  receipt.number,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${receipt.dateLabel} · ${receipt.detailCount} jenis',
                  style: const TextStyle(
                    color: Color(0xFFCADBED),
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          _StatusBadge(label: receipt.statusLabel, color: color),
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.receipt});
  final GoodsReceipt receipt;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        children: [
          _InfoRow(label: 'Sumber', value: receipt.source?.name ?? '-'),
          _InfoRow(
            label: 'Cara perolehan',
            value: receipt.acquisitionMethodLabel,
          ),
          _InfoRow(
            label: 'Nomor dokumen',
            value: receipt.documentNumber ?? '-',
          ),
          _InfoRow(label: 'Asal / penyedia', value: receipt.origin ?? '-'),
          _InfoRow(label: 'Dicatat oleh', value: receipt.createdBy ?? '-'),
          _InfoRow(label: 'Nilai total', value: _currency(receipt.totalValue)),
          if (receipt.notes?.isNotEmpty == true)
            _InfoRow(label: 'Catatan', value: receipt.notes!),
        ],
      ),
    ),
  );
}

class _CancellationCard extends StatelessWidget {
  const _CancellationCard({required this.receipt});
  final GoodsReceipt receipt;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.errorContainer,
      borderRadius: BorderRadius.circular(16),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Penerimaan dibatalkan',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 5),
        Text(receipt.cancellationReason ?? '-'),
        const SizedBox(height: 5),
        Text(
          '${receipt.cancelledAtLabel ?? '-'} · ${receipt.cancelledBy ?? '-'}',
          style: const TextStyle(fontSize: 11),
        ),
      ],
    ),
  );
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({required this.index, required this.detail});
  final int index;
  final GoodsReceiptDetail detail;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 17,
                backgroundColor: NusaColors.surfaceBlue,
                child: Text(
                  '${index + 1}',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      detail.goods.name,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    Text(
                      '${detail.goods.code} · ${detail.goods.typeLabel}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const Divider(height: 20),
          _InfoRow(label: 'Lokasi', value: detail.location?.name ?? '-'),
          _InfoRow(
            label: 'Jumlah',
            value: '${_number(detail.quantity)} ${detail.goods.unit}',
          ),
          _InfoRow(
            label: 'Harga satuan',
            value: detail.unitPrice == null
                ? '-'
                : _currency(detail.unitPrice!),
          ),
          _InfoRow(label: 'Subtotal', value: _currency(detail.subtotal)),
          if (detail.goods.isAsset) ...[
            _InfoRow(
              label: 'Merek / tipe',
              value: _joined(detail.brand, detail.model),
            ),
            _InfoRow(label: 'Kondisi', value: detail.conditionLabel ?? '-'),
          ],
          if (detail.notes?.isNotEmpty == true)
            _InfoRow(label: 'Keterangan', value: detail.notes!),
          if (detail.assetUnits.isNotEmpty) ...[
            const Divider(height: 20),
            Text(
              '${detail.assetUnits.length} Unit Aset dibuat',
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            for (final unit in detail.assetUnits.take(8))
              Padding(
                padding: const EdgeInsets.only(bottom: 3),
                child: Text(
                  '${unit.goodsUnitCode} · ${unit.inventoryCode}',
                  style: const TextStyle(
                    fontFamily: 'monospace',
                    fontSize: 10.5,
                  ),
                ),
              ),
            if (detail.assetUnits.length > 8)
              Text(
                '+${detail.assetUnits.length - 8} unit lainnya',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                ),
              ),
          ] else if (!detail.goods.isAsset)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                detail.cancellationMutationId == null
                    ? 'Saldo stok telah ditambahkan.'
                    : 'Saldo stok telah dikoreksi karena pembatalan.',
                style: TextStyle(
                  color: detail.cancellationMutationId == null
                      ? NusaColors.success
                      : Theme.of(context).colorScheme.error,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
        ],
      ),
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 105,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800),
    ),
  );
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.page});
  final GoodsReceiptPage page;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late int _sourceId;
  DateTime? _startDate;
  DateTime? _endDate;
  String? _error;

  @override
  void initState() {
    super.initState();
    _sourceId = widget.page.sourceId ?? 0;
    _startDate = widget.page.startDate;
    _endDate = widget.page.endDate;
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 14, 16, 20),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'Filter Barang Datang',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
              ),
            ),
            TextButton(
              onPressed: () => setState(() {
                _sourceId = 0;
                _startDate = null;
                _endDate = null;
                _error = null;
              }),
              child: const Text('Reset'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        NusaDropdownField<int>(
          fieldKey: const Key('goods-receipt-source-filter'),
          value: _sourceId,
          options: [
            const NusaDropdownOption(value: 0, label: 'Semua sumber'),
            ...widget.page.sources.map(
              (item) => NusaDropdownOption(value: item.id, label: item.label),
            ),
          ],
          decoration: const InputDecoration(
            labelText: 'Sumber perolehan',
            prefixIcon: Icon(Icons.account_balance_outlined),
          ),
          onChanged: (value) {
            if (value != null) setState(() => _sourceId = value);
          },
        ),
        const SizedBox(height: 12),
        _OptionalDateField(
          label: 'Tanggal mulai',
          value: _startDate,
          onTap: () => _pickDate(start: true),
          onClear: _startDate == null
              ? null
              : () => setState(() => _startDate = null),
        ),
        const SizedBox(height: 12),
        _OptionalDateField(
          label: 'Tanggal selesai',
          value: _endDate,
          onTap: () => _pickDate(start: false),
          onClear: _endDate == null
              ? null
              : () => setState(() => _endDate = null),
        ),
        if (_error != null) ...[
          const SizedBox(height: 8),
          Text(
            _error!,
            style: TextStyle(
              color: Theme.of(context).colorScheme.error,
              fontSize: 12,
            ),
          ),
        ],
        const SizedBox(height: 16),
        FilledButton(
          key: const Key('apply-goods-receipt-filter'),
          onPressed: _apply,
          child: const Text('Terapkan Filter'),
        ),
      ],
    ),
  );

  Future<void> _pickDate({required bool start}) async {
    final initial = start ? _startDate : _endDate;
    final value = await showDatePicker(
      context: context,
      initialDate: initial ?? DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime.now(),
    );
    if (value != null && mounted) {
      setState(() {
        if (start) {
          _startDate = value;
        } else {
          _endDate = value;
        }
      });
    }
  }

  void _apply() {
    if (_startDate != null &&
        _endDate != null &&
        _endDate!.isBefore(_startDate!)) {
      setState(
        () => _error = 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
      );
      return;
    }
    Navigator.pop(
      context,
      _ReceiptFilters(
        sourceId: _sourceId == 0 ? null : _sourceId,
        startDate: _startDate,
        endDate: _endDate,
      ),
    );
  }
}

class _OptionalDateField extends StatelessWidget {
  const _OptionalDateField({
    required this.label,
    required this.value,
    required this.onTap,
    required this.onClear,
  });
  final String label;
  final DateTime? value;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(14),
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: const Icon(Icons.event_outlined),
        suffixIcon: onClear == null
            ? const Icon(Icons.calendar_month_rounded)
            : IconButton(
                tooltip: 'Hapus tanggal',
                onPressed: onClear,
                icon: const Icon(Icons.close_rounded),
              ),
      ),
      child: Text(value == null ? 'Semua tanggal' : _dateLabel(value!)),
    ),
  );
}

class _CancelDialog extends StatefulWidget {
  const _CancelDialog({required this.number});
  final String number;

  @override
  State<_CancelDialog> createState() => _CancelDialogState();
}

class _CancelDialogState extends State<_CancelDialog> {
  final _controller = TextEditingController();
  bool _confirmed = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Batalkan penerimaan?'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${widget.number} akan dikoreksi. Stok masuk dibalik dan Unit Aset yang belum digunakan dinonaktifkan.',
            style: const TextStyle(fontSize: 12, height: 1.4),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const Key('goods-receipt-cancel-reason'),
            controller: _controller,
            minLines: 3,
            maxLines: 5,
            maxLength: 1000,
            decoration: InputDecoration(
              labelText: 'Alasan pembatalan',
              alignLabelWithHint: true,
              errorText: _error,
            ),
          ),
          CheckboxListTile(
            key: const Key('goods-receipt-cancel-confirmation'),
            contentPadding: EdgeInsets.zero,
            value: _confirmed,
            onChanged: (value) => setState(() => _confirmed = value ?? false),
            title: const Text(
              'Saya sudah memastikan penerimaan yang dipilih benar.',
              style: TextStyle(fontSize: 11.5),
            ),
            controlAffinity: ListTileControlAffinity.leading,
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Kembali'),
      ),
      FilledButton(
        key: const Key('confirm-cancel-goods-receipt'),
        onPressed: _confirmed ? _submit : null,
        child: const Text('Batalkan'),
      ),
    ],
  );

  void _submit() {
    final reason = _controller.text.trim();
    if (reason.length < 10) {
      setState(() => _error = 'Alasan minimal 10 karakter.');
      return;
    }
    Navigator.pop(context, reason);
  }
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

enum _DetailAction { labels, cancel }

class _ReceiptFilters {
  const _ReceiptFilters({this.sourceId, this.startDate, this.endDate});
  final int? sourceId;
  final DateTime? startDate;
  final DateTime? endDate;
}

int _activeFilterCount(GoodsReceiptPage page) =>
    (page.sourceId == null ? 0 : 1) +
    (page.startDate == null ? 0 : 1) +
    (page.endDate == null ? 0 : 1);

String _currency(double value) {
  final whole = value.round();
  final digits = whole.toString();
  final buffer = StringBuffer();
  for (var index = 0; index < digits.length; index++) {
    if (index > 0 && (digits.length - index) % 3 == 0) buffer.write('.');
    buffer.write(digits[index]);
  }
  return 'Rp$buffer';
}

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');

String _joined(String? first, String? second) {
  final values = [
    first,
    second,
  ].where((value) => value?.trim().isNotEmpty == true);
  return values.isEmpty ? '-' : values.join(' · ');
}

String _dateLabel(DateTime value) {
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
  return '${value.day.toString().padLeft(2, '0')} ${months[value.month - 1]} ${value.year}';
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Barang datang belum dapat diproses.';
}
