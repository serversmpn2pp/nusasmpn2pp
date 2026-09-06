import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_loan/application/goods_loan_controller.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';
import 'package:nusa/features/goods_loan/presentation/goods_loan_view.dart';
import 'package:nusa/features/goods_loan/presentation/widgets/inventory_barcode_scanner_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsReturnView extends ConsumerStatefulWidget {
  const GoodsReturnView({super.key});
  @override
  ConsumerState<GoodsReturnView> createState() => _GoodsReturnViewState();
}

class _GoodsReturnViewState extends ConsumerState<GoodsReturnView> {
  final _search = TextEditingController();
  final _code = TextEditingController();
  Timer? _debounce;
  bool _identifying = false;
  bool _loadingMore = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(goodsReturnControllerProvider);
    final page = state.value;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengembalian Barang'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading || _identifying
                ? null
                : ref.read(goodsReturnControllerProvider.notifier).refresh,
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
                    _ReturnSummary(summary: page.summary),
                    const SizedBox(height: 10),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Scan atau masukkan kode AST',
                              style: TextStyle(fontWeight: FontWeight.w800),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Barang habis pakai tidak melalui proses pengembalian.',
                              style: TextStyle(
                                fontSize: 10,
                                color: NusaColors.textSecondary,
                              ),
                            ),
                            const SizedBox(height: 10),
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: NusaTextField(
                                    fieldKey: const Key('goods-return-code'),
                                    controller: _code,
                                    hintText: 'AST-2026-...',
                                    prefixIcon: Icons.qr_code_rounded,
                                    enabled: !_identifying,
                                    textInputAction: TextInputAction.done,
                                    onFieldSubmitted: (_) =>
                                        _identify(_code.text),
                                  ),
                                ),
                                const SizedBox(width: 7),
                                IconButton.filledTonal(
                                  key: const Key('scan-goods-return'),
                                  tooltip: 'Buka kamera',
                                  onPressed: _identifying ? null : _scan,
                                  icon: const Icon(
                                    Icons.qr_code_scanner_rounded,
                                  ),
                                ),
                                const SizedBox(width: 5),
                                IconButton.filled(
                                  key: const Key('identify-goods-return'),
                                  tooltip: 'Proses kode',
                                  onPressed: _identifying
                                      ? null
                                      : () => _identify(_code.text),
                                  icon: _identifying
                                      ? const SizedBox.square(
                                          dimension: 18,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            color: Colors.white,
                                          ),
                                        )
                                      : const Icon(Icons.arrow_forward_rounded),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('goods-return-search'),
                      controller: _search,
                      hintText: 'Cari peminjam, barang, atau nomor',
                      prefixIcon: Icons.search_rounded,
                      enabled: !state.isLoading && !_identifying,
                      onChanged: _onSearch,
                      suffixIcon: _search.text.isEmpty
                          ? null
                          : IconButton(
                              tooltip: 'Hapus pencarian',
                              onPressed: _clearSearch,
                              icon: const Icon(Icons.close_rounded),
                            ),
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
                      .read(goodsReturnControllerProvider.notifier)
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

  Widget _content(GoodsReturnPage page) => RefreshIndicator(
    onRefresh: ref.read(goodsReturnControllerProvider.notifier).refresh,
    child: page.items.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(28),
            children: const [
              SizedBox(height: 52),
              Icon(
                Icons.assignment_turned_in_outlined,
                size: 54,
                color: NusaColors.success,
              ),
              SizedBox(height: 12),
              Text(
                'Tidak ada barang yang menunggu pengembalian.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.separated(
            key: const Key('goods-return-list'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
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
                onTap: () => _openReturn(item.id),
              );
            },
          ),
  );

  void _onSearch(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 450),
      () => ref.read(goodsReturnControllerProvider.notifier).search(value),
    );
  }

  void _clearSearch() {
    _debounce?.cancel();
    _search.clear();
    setState(() {});
    ref.read(goodsReturnControllerProvider.notifier).search('');
  }

  Future<void> _scan() async {
    final code = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const InventoryBarcodeScannerSheet(
        title: 'Scan Barang Kembali',
        guide: 'Arahkan barcode internal AST ke dalam bingkai.',
      ),
    );
    if (code != null && mounted) {
      _code.text = code;
      await _identify(code);
    }
  }

  Future<void> _identify(String rawCode) async {
    final code = rawCode.trim();
    if (code.isEmpty) {
      _showError('Masukkan atau scan kode inventaris AST.');
      return;
    }
    setState(() => _identifying = true);
    try {
      final result = await ref
          .read(goodsLoanActionsProvider)
          .identifyReturn(code);
      if (!mounted) return;
      _code.clear();
      await _openReturn(result.loanId, detailId: result.detailId);
    } catch (error) {
      if (mounted) _showError(_message(error));
    } finally {
      if (mounted) setState(() => _identifying = false);
    }
  }

  Future<void> _openReturn(int loanId, {int? detailId}) async {
    await context.push(
      '/peminjaman-barang/$loanId?kembalikan=1${detailId == null ? '' : '&detail_id=$detailId'}',
    );
    if (mounted) {
      await ref.read(goodsReturnControllerProvider.notifier).refresh();
    }
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      await ref.read(goodsReturnControllerProvider.notifier).loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _showError(String value) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(value)));
}

class _ReturnSummary extends StatelessWidget {
  const _ReturnSummary({required this.summary});
  final GoodsReturnSummary summary;
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
        _SummaryItem(value: summary.active, label: 'Aktif'),
        _SummaryItem(value: summary.overdue, label: 'Terlambat'),
        _SummaryItem(value: summary.partial, label: 'Sebagian'),
        _SummaryItem(value: summary.dueSoon, label: '7 hari'),
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

String _message(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final values = error.errors.values.expand((item) => item);
    if (values.isNotEmpty) return values.first;
  }
  return error is AppException
      ? error.message
      : 'Pengembalian barang belum dapat diproses.';
}
