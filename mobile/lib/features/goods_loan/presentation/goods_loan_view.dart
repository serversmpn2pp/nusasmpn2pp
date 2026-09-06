import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_loan/application/goods_loan_controller.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';
import 'package:nusa/features/goods_loan/presentation/widgets/goods_loan_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsLoanView extends ConsumerStatefulWidget {
  const GoodsLoanView({super.key});
  @override
  ConsumerState<GoodsLoanView> createState() => _GoodsLoanViewState();
}

class _GoodsLoanViewState extends ConsumerState<GoodsLoanView> {
  final _search = TextEditingController();
  Timer? _debounce;
  bool _processing = false;
  bool _loadingMore = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(goodsLoanControllerProvider);
    final page = state.value;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Peminjaman Barang'),
        actions: [
          IconButton(
            tooltip: 'Pengembalian',
            onPressed: () => context.push('/pengembalian-barang'),
            icon: const Icon(Icons.assignment_return_rounded),
          ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading || _processing
                ? null
                : ref.read(goodsLoanControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: page?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-goods-loan'),
              onPressed: _processing ? null : () => _create(page!),
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
                    _Summary(summary: page.summary),
                    const SizedBox(height: 9),
                    Row(
                      children: [
                        Expanded(
                          child: NusaTextField(
                            fieldKey: const Key('goods-loan-search'),
                            controller: _search,
                            hintText: 'Nomor atau nama peminjam',
                            prefixIcon: Icons.search_rounded,
                            enabled: !state.isLoading && !_processing,
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
                            key: const Key('goods-loan-filter'),
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
                error: (error, stack) => _ErrorState(
                  message: _message(error),
                  onRetry: ref
                      .read(goodsLoanControllerProvider.notifier)
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

  Widget _content(GoodsLoanPage page) => RefreshIndicator(
    onRefresh: ref.read(goodsLoanControllerProvider.notifier).refresh,
    child: page.items.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(28),
            children: const [
              SizedBox(height: 70),
              Icon(
                Icons.shopping_bag_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada peminjaman yang sesuai.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.separated(
            key: const Key('goods-loan-list'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
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
              return GoodsLoanCard(
                item: item,
                onTap: () => context.push('/peminjaman-barang/${item.id}'),
              );
            },
          ),
  );

  void _onSearch(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 450),
      () => ref.read(goodsLoanControllerProvider.notifier).search(value),
    );
  }

  void _clearSearch() {
    _debounce?.cancel();
    _search.clear();
    setState(() {});
    ref.read(goodsLoanControllerProvider.notifier).search('');
  }

  Future<void> _create(GoodsLoanPage page) async {
    final value = await showModalBottomSheet<GoodsLoanFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => GoodsLoanFormSheet(
        page: page,
        onIdentifyBorrower: (code) =>
            ref.read(goodsLoanActionsProvider).identifyBorrower(code: code),
        onIdentifyItem: (code) =>
            ref.read(goodsLoanActionsProvider).identifyItem(code: code),
      ),
    );
    if (value == null || !mounted) return;
    setState(() => _processing = true);
    try {
      await ref.read(goodsLoanActionsProvider).create(value);
      await ref.read(goodsLoanControllerProvider.notifier).refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Peminjaman barang berhasil dicatat.')),
        );
      }
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

  Future<void> _filter(GoodsLoanPage page) async {
    final result = await showModalBottomSheet<_Filters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _FilterSheet(page: page),
    );
    if (result == null || !mounted) return;
    await ref
        .read(goodsLoanControllerProvider.notifier)
        .applyFilters(
          borrowerType: result.borrowerType,
          status: result.status,
          startDate: result.startDate,
          endDate: result.endDate,
        );
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      await ref.read(goodsLoanControllerProvider.notifier).loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _showError(Object error) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(_message(error))));
}

class GoodsLoanCard extends StatelessWidget {
  const GoodsLoanCard({required this.item, required this.onTap, super.key});
  final GoodsLoan item;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    final color = item.overdue
        ? Theme.of(context).colorScheme.error
        : (item.status == 'selesai' ? NusaColors.success : NusaColors.primary);
    return Card(
      child: InkWell(
        key: Key('goods-loan-${item.id}'),
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
                  item.borrowerType == 'siswa'
                      ? Icons.school_outlined
                      : Icons.badge_outlined,
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
                            item.borrowerName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        const SizedBox(width: 6),
                        _Badge(label: item.statusLabel, color: color),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${item.number} · ${item.borrowerIdentity}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        color: NusaColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${item.dateLabel} · ${item.itemCount} barang',
                            style: const TextStyle(fontSize: 11),
                          ),
                        ),
                        Flexible(
                          child: Text(
                            item.monitoringLabel,
                            textAlign: TextAlign.right,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              color: color,
                            ),
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
  final GoodsLoanSummary summary;
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
        _SummaryItem(value: summary.total, label: 'Total'),
        _SummaryItem(value: summary.active, label: 'Aktif'),
        _SummaryItem(value: summary.finished, label: 'Selesai'),
        _SummaryItem(value: summary.today, label: 'Hari ini'),
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

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});
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

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.page});
  final GoodsLoanPage page;
  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late String _borrowerType;
  late String _status;
  DateTime? _start;
  DateTime? _end;
  @override
  void initState() {
    super.initState();
    _borrowerType = widget.page.borrowerType;
    _status = widget.page.status;
    _start = widget.page.startDate;
    _end = widget.page.endDate;
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 18),
    child: SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Filter Peminjaman',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 16),
          NusaDropdownField<String>(
            fieldKey: const Key('goods-loan-borrower-type-filter'),
            value: _borrowerType,
            options: [
              const NusaDropdownOption(value: 'semua', label: 'Semua peminjam'),
              ...widget.page.borrowerTypes.map(
                (item) =>
                    NusaDropdownOption(value: item.value, label: item.label),
              ),
            ],
            decoration: const InputDecoration(labelText: 'Jenis peminjam'),
            onChanged: (value) =>
                setState(() => _borrowerType = value ?? 'semua'),
          ),
          const SizedBox(height: 12),
          NusaDropdownField<String>(
            fieldKey: const Key('goods-loan-status-filter'),
            value: _status,
            options: [
              const NusaDropdownOption(value: 'semua', label: 'Semua status'),
              ...widget.page.statuses.map(
                (item) =>
                    NusaDropdownOption(value: item.value, label: item.label),
              ),
            ],
            decoration: const InputDecoration(labelText: 'Status'),
            onChanged: (value) => setState(() => _status = value ?? 'semua'),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: _pickRange,
            icon: const Icon(Icons.date_range_outlined),
            label: Text(
              _start == null || _end == null
                  ? 'Semua tanggal'
                  : '${_date(_start!)} – ${_date(_end!)}',
            ),
          ),
          if (_start != null)
            TextButton(
              onPressed: () => setState(() {
                _start = null;
                _end = null;
              }),
              child: const Text('Hapus rentang tanggal'),
            ),
          const SizedBox(height: 10),
          FilledButton.icon(
            key: const Key('apply-goods-loan-filter'),
            onPressed: () => Navigator.pop(
              context,
              _Filters(
                borrowerType: _borrowerType,
                status: _status,
                startDate: _start,
                endDate: _end,
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
    final result = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
      initialDateRange: _start != null && _end != null
          ? DateTimeRange(start: _start!, end: _end!)
          : null,
    );
    if (result != null) {
      setState(() {
        _start = result.start;
        _end = result.end;
      });
    }
  }
}

class _Filters {
  const _Filters({
    required this.borrowerType,
    required this.status,
    this.startDate,
    this.endDate,
  });
  final String borrowerType;
  final String status;
  final DateTime? startDate;
  final DateTime? endDate;
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

int _filterCount(GoodsLoanPage page) =>
    (page.borrowerType == 'semua' ? 0 : 1) +
    (page.status == 'semua' ? 0 : 1) +
    (page.startDate == null ? 0 : 1) +
    (page.endDate == null ? 0 : 1);
String _date(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
String _message(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final values = error.errors.values.expand((item) => item);
    if (values.isNotEmpty) return values.first;
  }
  return error is AppException
      ? error.message
      : 'Peminjaman barang belum dapat diproses.';
}
