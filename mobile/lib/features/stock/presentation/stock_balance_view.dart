import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/stock/application/stock_controller.dart';
import 'package:nusa/features/stock/domain/stock.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StockBalanceView extends ConsumerStatefulWidget {
  const StockBalanceView({super.key});

  @override
  ConsumerState<StockBalanceView> createState() => _StockBalanceViewState();
}

class _StockBalanceViewState extends ConsumerState<StockBalanceView> {
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
    final state = ref.watch(stockBalanceControllerProvider);
    final page = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Saldo Stok'),
        actions: [
          IconButton(
            tooltip: 'Buku mutasi',
            onPressed: () => context.push('/mutasi-stok'),
            icon: const Icon(Icons.swap_horiz_rounded),
          ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(stockBalanceControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: page?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-stock-movement-from-balance'),
              onPressed: () => context.push('/mutasi-stok?tambah=1'),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Catat Mutasi'),
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
                    _BalanceSummary(summary: page.summary),
                    const SizedBox(height: 9),
                    Row(
                      children: [
                        Expanded(
                          child: NusaTextField(
                            fieldKey: const Key('stock-balance-search'),
                            controller: _search,
                            hintText: 'Cari barang atau kode',
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
                          isLabelVisible: _filterCount(page) > 0,
                          label: Text('${_filterCount(page)}'),
                          child: IconButton.filledTonal(
                            key: const Key('stock-balance-filter'),
                            tooltip: 'Filter',
                            onPressed: state.isLoading
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
                      .read(stockBalanceControllerProvider.notifier)
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

  Widget _content(StockBalancePage page) => RefreshIndicator(
    onRefresh: ref.read(stockBalanceControllerProvider.notifier).refresh,
    child: page.items.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(28),
            children: const [
              SizedBox(height: 72),
              Icon(
                Icons.inventory_2_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada saldo stok yang sesuai.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.separated(
            key: const Key('stock-balance-list'),
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
              final item = page.items[index];
              return _BalanceCard(
                item: item,
                canManage: page.access.canManage,
                onHistory: () => _openMovement(item, false),
                onRecord: () => _openMovement(item, true),
              );
            },
          ),
  );

  void _onSearch(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 450),
      () => ref.read(stockBalanceControllerProvider.notifier).search(value),
    );
  }

  void _clearSearch() {
    _debounce?.cancel();
    _search.clear();
    setState(() {});
    ref.read(stockBalanceControllerProvider.notifier).search('');
  }

  void _openMovement(StockBalance item, bool record) {
    context.push(
      '/mutasi-stok?barang_id=${item.goods.id}'
      '&lokasi_barang_id=${item.location.id}'
      '${record ? '&tambah=1' : ''}',
    );
  }

  Future<void> _openFilters(StockBalancePage page) async {
    final result = await showModalBottomSheet<_BalanceFilters>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      builder: (context) => _BalanceFilterSheet(page: page),
    );
    if (result == null || !mounted) return;
    await ref
        .read(stockBalanceControllerProvider.notifier)
        .applyFilters(
          status: result.status,
          categoryId: result.categoryId,
          locationId: result.locationId,
        );
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      await ref.read(stockBalanceControllerProvider.notifier).loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _BalanceSummary extends StatelessWidget {
  const _BalanceSummary({required this.summary});
  final StockBalanceSummary summary;

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
        _SummaryItem(value: '${summary.rows}', label: 'Baris saldo'),
        _SummaryItem(value: '${summary.locations}', label: 'Lokasi'),
        _SummaryItem(value: '${summary.low}', label: 'Menipis'),
        _SummaryItem(value: '${summary.empty}', label: 'Habis'),
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

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({
    required this.item,
    required this.canManage,
    required this.onHistory,
    required this.onRecord,
  });
  final StockBalance item;
  final bool canManage;
  final VoidCallback onHistory;
  final VoidCallback onRecord;

  @override
  Widget build(BuildContext context) {
    final color = switch (item.status) {
      'habis' => Theme.of(context).colorScheme.error,
      'menipis' => const Color(0xFFE38A00),
      _ => NusaColors.success,
    };
    return Card(
      child: InkWell(
        key: Key('stock-balance-${item.id}'),
        onTap: onHistory,
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
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(Icons.inventory_2_outlined, color: color),
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
                        _StatusBadge(label: item.statusLabel, color: color),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${item.goods.code} · ${item.location.name}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 12,
                      runSpacing: 7,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text.rich(
                          TextSpan(
                            text: '${_number(item.quantity)} ',
                            style: TextStyle(
                              color: color,
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                            ),
                            children: [
                              TextSpan(
                                text: item.goods.unit,
                                style: const TextStyle(
                                  color: NusaColors.textSecondary,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Text(
                          'Minimum ${_number(item.minimum)}',
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 11,
                          ),
                        ),
                        if (canManage)
                          TextButton.icon(
                            onPressed: onRecord,
                            icon: const Icon(Icons.add_rounded, size: 17),
                            label: const Text('Catat'),
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

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w800),
    ),
  );
}

class _BalanceFilterSheet extends StatefulWidget {
  const _BalanceFilterSheet({required this.page});
  final StockBalancePage page;

  @override
  State<_BalanceFilterSheet> createState() => _BalanceFilterSheetState();
}

class _BalanceFilterSheetState extends State<_BalanceFilterSheet> {
  late String _status;
  int? _categoryId;
  int? _locationId;

  @override
  void initState() {
    super.initState();
    _status = widget.page.status;
    _categoryId = widget.page.categoryId;
    _locationId = widget.page.locationId;
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      16,
      12,
      16,
      16 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    child: SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Filter Saldo Stok',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 16),
          NusaDropdownField<String>(
            fieldKey: const Key('stock-balance-status-filter'),
            value: _status,
            options: widget.page.statusOptions
                .map(
                  (item) =>
                      NusaDropdownOption(value: item.value, label: item.label),
                )
                .toList(growable: false),
            decoration: const InputDecoration(labelText: 'Status stok'),
            onChanged: (value) => setState(() => _status = value ?? 'semua'),
          ),
          const SizedBox(height: 12),
          NusaDropdownField<int>(
            fieldKey: const Key('stock-balance-category-filter'),
            value: _categoryId ?? 0,
            options: [
              const NusaDropdownOption(value: 0, label: 'Semua kategori'),
              ...widget.page.categories.map(
                (item) => NusaDropdownOption(value: item.id, label: item.label),
              ),
            ],
            decoration: const InputDecoration(labelText: 'Kategori barang'),
            onChanged: (value) =>
                setState(() => _categoryId = value == 0 ? null : value),
          ),
          const SizedBox(height: 12),
          NusaDropdownField<int>(
            fieldKey: const Key('stock-balance-location-filter'),
            value: _locationId ?? 0,
            options: [
              const NusaDropdownOption(value: 0, label: 'Semua lokasi'),
              ...widget.page.locations.map(
                (item) => NusaDropdownOption(value: item.id, label: item.label),
              ),
            ],
            decoration: const InputDecoration(labelText: 'Lokasi stok'),
            onChanged: (value) =>
                setState(() => _locationId = value == 0 ? null : value),
          ),
          const SizedBox(height: 18),
          FilledButton.icon(
            key: const Key('apply-stock-balance-filter'),
            onPressed: () => Navigator.pop(
              context,
              _BalanceFilters(
                status: _status,
                categoryId: _categoryId,
                locationId: _locationId,
              ),
            ),
            icon: const Icon(Icons.check_rounded),
            label: const Text('Terapkan Filter'),
          ),
        ],
      ),
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

class _BalanceFilters {
  const _BalanceFilters({
    required this.status,
    this.categoryId,
    this.locationId,
  });
  final String status;
  final int? categoryId;
  final int? locationId;
}

int _filterCount(StockBalancePage page) =>
    (page.status == 'semua' ? 0 : 1) +
    (page.categoryId == null ? 0 : 1) +
    (page.locationId == null ? 0 : 1);

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');

String _errorMessage(Object error) =>
    error is AppException ? error.message : 'Saldo stok belum dapat dimuat.';
