import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_request/application/goods_request_controller.dart';
import 'package:nusa/features/goods_request/domain/goods_request.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsRequestView extends ConsumerStatefulWidget {
  const GoodsRequestView({super.key});
  @override
  ConsumerState<GoodsRequestView> createState() => _GoodsRequestViewState();
}

class _GoodsRequestViewState extends ConsumerState<GoodsRequestView> {
  final _search = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(goodsRequestControllerProvider);
    final page = state.value;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengajuan Barang'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(goodsRequestControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (page != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _Summary(summary: page.summary),
                    const SizedBox(height: 9),
                    Row(
                      children: [
                        Expanded(
                          child: NusaTextField(
                            fieldKey: const Key('goods-request-search'),
                            controller: _search,
                            hintText: 'Nomor, pegawai, atau barang',
                            prefixIcon: Icons.search_rounded,
                            enabled: !state.isLoading,
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
                          isLabelVisible:
                              page.type != 'semua' || page.status != 'menunggu',
                          child: IconButton.filledTonal(
                            key: const Key('goods-request-filter'),
                            tooltip: 'Filter',
                            onPressed: state.isLoading
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
                error: (error, _) => _ErrorState(
                  message: goodsRequestMessage(error),
                  onRetry: ref
                      .read(goodsRequestControllerProvider.notifier)
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

  Widget _content(GoodsRequestPage page) => RefreshIndicator(
    onRefresh: ref.read(goodsRequestControllerProvider.notifier).refresh,
    child: page.items.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(28),
            children: const [
              SizedBox(height: 70),
              Icon(
                Icons.assignment_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Tidak ada pengajuan pada pilihan ini.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.separated(
            key: const Key('goods-request-list'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
            itemCount:
                page.items.length + (page.pagination.hasNextPage ? 1 : 0),
            separatorBuilder: (_, _) => const SizedBox(height: 9),
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
              final item = page.items[index];
              return _RequestCard(
                item: item,
                onTap: () => context.push('/pengajuan-barang/${item.id}'),
              );
            },
          ),
  );

  void _onSearch(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 450),
      () => ref.read(goodsRequestControllerProvider.notifier).search(value),
    );
  }

  void _clearSearch() {
    _debounce?.cancel();
    _search.clear();
    setState(() {});
    ref.read(goodsRequestControllerProvider.notifier).search('');
  }

  Future<void> _filter(GoodsRequestPage page) async {
    final result = await showModalBottomSheet<_Filters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _FilterSheet(page: page),
    );
    if (result == null || !mounted) return;
    await ref
        .read(goodsRequestControllerProvider.notifier)
        .applyFilters(type: result.type, status: result.status);
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      await ref.read(goodsRequestControllerProvider.notifier).loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({required this.item, required this.onTap});
  final GoodsRequest item;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    final color = requestStatusColor(context, item.status);
    return Card(
      child: InkWell(
        key: Key('goods-request-${item.id}'),
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
                  color: color.withValues(alpha: .1),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  item.type == 'peminjaman'
                      ? Icons.devices_other_outlined
                      : Icons.inventory_2_outlined,
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
                            item.employeeName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        const SizedBox(width: 6),
                        _StatusBadge(label: item.statusLabel, color: color),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      item.number,
                      style: const TextStyle(
                        fontSize: 10,
                        color: NusaColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      item.goodsName,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${goodsRequestNumber(item.quantity)} ${item.unit} · ${item.typeLabel}',
                            style: const TextStyle(
                              fontSize: 10,
                              color: NusaColors.textSecondary,
                            ),
                          ),
                        ),
                        Text(
                          'Butuh ${item.requiredDateLabel}',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            color: color,
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

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final GoodsRequestSummary summary;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        _SummaryItem(value: summary.total, label: 'Semua'),
        _SummaryItem(value: summary.pending, label: 'Menunggu'),
        _SummaryItem(value: summary.loans, label: 'Aset'),
        _SummaryItem(value: summary.consumables, label: 'Habis pakai'),
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

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.page});
  final GoodsRequestPage page;
  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late String _type;
  late String _status;
  @override
  void initState() {
    super.initState();
    _type = widget.page.type;
    _status = widget.page.status;
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      16,
      12,
      16,
      MediaQuery.viewInsetsOf(context).bottom + 18,
    ),
    child: SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Filter Pengajuan',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 16),
          NusaDropdownField<String>(
            fieldKey: const Key('goods-request-type-filter'),
            value: _type,
            options: widget.page.types
                .map(
                  (item) =>
                      NusaDropdownOption(value: item.value, label: item.label),
                )
                .toList(),
            decoration: const InputDecoration(labelText: 'Jenis pengajuan'),
            onChanged: (value) => setState(() => _type = value ?? 'semua'),
          ),
          const SizedBox(height: 12),
          NusaDropdownField<String>(
            fieldKey: const Key('goods-request-status-filter'),
            value: _status,
            options: widget.page.statuses
                .map(
                  (item) =>
                      NusaDropdownOption(value: item.value, label: item.label),
                )
                .toList(),
            decoration: const InputDecoration(labelText: 'Status'),
            onChanged: (value) => setState(() => _status = value ?? 'menunggu'),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            key: const Key('apply-goods-request-filter'),
            onPressed: () =>
                Navigator.pop(context, _Filters(type: _type, status: _status)),
            icon: const Icon(Icons.check_rounded),
            label: const Text('Terapkan Filter'),
          ),
        ],
      ),
    ),
  );
}

class _Filters {
  const _Filters({required this.type, required this.status});
  final String type;
  final String status;
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .1),
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

Color requestStatusColor(BuildContext context, String status) =>
    switch (status) {
      'dipenuhi' => NusaColors.success,
      'menunggu' => const Color(0xFFD88B00),
      'ditolak' => Theme.of(context).colorScheme.error,
      _ => NusaColors.textSecondary,
    };
String goodsRequestNumber(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value
          .toStringAsFixed(2)
          .replaceFirst(RegExp(r'0+$'), '')
          .replaceFirst(RegExp(r'\.$'), '');
String goodsRequestMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final values = error.errors.values.expand((item) => item);
    if (values.isNotEmpty) return values.first;
  }
  return error is AppException
      ? error.message
      : 'Pengajuan barang belum dapat diproses.';
}
