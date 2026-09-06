import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';
import 'package:nusa/features/goods_loan_recap/application/goods_loan_recap_controller.dart';
import 'package:nusa/features/goods_loan_recap/application/goods_loan_recap_document_service.dart';
import 'package:nusa/features/goods_loan_recap/domain/goods_loan_recap.dart';
import 'package:nusa/features/goods_loan_recap/presentation/widgets/goods_loan_recap_filter_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsLoanRecapView extends ConsumerStatefulWidget {
  const GoodsLoanRecapView({super.key});

  @override
  ConsumerState<GoodsLoanRecapView> createState() => _GoodsLoanRecapViewState();
}

class _GoodsLoanRecapViewState extends ConsumerState<GoodsLoanRecapView> {
  final _search = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;
  bool _exporting = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(goodsLoanRecapControllerProvider);
    final page = state.value;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rekap Peminjaman'),
        actions: [
          IconButton(
            key: const Key('copy-overdue-goods-loan'),
            tooltip: 'Salin daftar terlambat',
            onPressed: page == null || _exporting
                ? null
                : () => _showOverdue(page),
            icon: const Icon(Icons.content_copy_rounded),
          ),
          PopupMenuButton<_DocumentAction>(
            key: const Key('goods-loan-recap-document-menu'),
            enabled: page != null && !_exporting,
            tooltip: 'Cetak atau bagikan rekap',
            onSelected: _document,
            itemBuilder: (_) => const [
              PopupMenuItem(
                value: _DocumentAction.print,
                child: ListTile(
                  leading: Icon(Icons.print_outlined),
                  title: Text('Cetak rekap'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
              PopupMenuItem(
                value: _DocumentAction.share,
                child: ListTile(
                  leading: Icon(Icons.share_outlined),
                  title: Text('Bagikan PDF'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
            ],
            icon: _exporting
                ? const SizedBox.square(
                    dimension: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.picture_as_pdf_outlined),
          ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading || _exporting
                ? null
                : ref.read(goodsLoanRecapControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stack) => _ErrorState(
            message: _message(error),
            onRetry: ref
                .read(goodsLoanRecapControllerProvider.notifier)
                .refresh,
          ),
          data: _content,
        ),
      ),
    );
  }

  Widget _content(GoodsLoanRecapPage page) => RefreshIndicator(
    onRefresh: ref.read(goodsLoanRecapControllerProvider.notifier).refresh,
    child: CustomScrollView(
      key: const Key('goods-loan-recap-scroll'),
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _RecapSummary(summary: page.summary),
                const SizedBox(height: 12),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: NusaTextField(
                        fieldKey: const Key('goods-loan-recap-search'),
                        controller: _search,
                        hintText: 'Nomor, nama, identitas, barang',
                        prefixIcon: Icons.search_rounded,
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
                    SizedBox(
                      width: 58,
                      height: 56,
                      child: Badge(
                        isLabelVisible: page.filter.activeCount > 0,
                        label: Text('${page.filter.activeCount}'),
                        child: IconButton.filledTonal(
                          key: const Key('goods-loan-recap-filter'),
                          tooltip: 'Filter rekap',
                          onPressed: () => _filter(page),
                          icon: const Icon(Icons.tune_rounded),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        _statusLabel(page),
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    Text(
                      '${page.pagination.total} transaksi',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                const Text(
                  'Urutan mengutamakan rencana kembali terdekat.',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
              ],
            ),
          ),
        ),
        if (page.items.isEmpty)
          const SliverFillRemaining(hasScrollBody: false, child: _EmptyState())
        else
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
            sliver: SliverList.separated(
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
                final loan = page.items[index];
                return _RecapCard(
                  loan: loan,
                  canReturn: page.access.canReturn,
                  onDetail: () => _openDetail(loan.id),
                  onReturn: loan.active
                      ? () => _openDetail(loan.id, returnGoods: true)
                      : null,
                );
              },
            ),
          ),
      ],
    ),
  );

  void _onSearch(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 450),
      () => ref.read(goodsLoanRecapControllerProvider.notifier).search(value),
    );
  }

  void _clearSearch() {
    _debounce?.cancel();
    _search.clear();
    setState(() {});
    ref.read(goodsLoanRecapControllerProvider.notifier).search('');
  }

  Future<void> _filter(GoodsLoanRecapPage page) async {
    final result = await showModalBottomSheet<GoodsLoanRecapFilter>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => GoodsLoanRecapFilterSheet(page: page),
    );
    if (result == null || !mounted) return;
    _search.text = result.query;
    await ref.read(goodsLoanRecapControllerProvider.notifier).apply(result);
  }

  Future<void> _openDetail(int id, {bool returnGoods = false}) async {
    await context.push(
      '/peminjaman-barang/$id${returnGoods ? '?kembalikan=1' : ''}',
    );
    if (mounted) {
      await ref.read(goodsLoanRecapControllerProvider.notifier).refresh();
    }
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      await ref.read(goodsLoanRecapControllerProvider.notifier).loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _document(_DocumentAction action) async {
    setState(() => _exporting = true);
    try {
      final page = await ref
          .read(goodsLoanRecapControllerProvider.notifier)
          .document();
      final service = ref.read(goodsLoanRecapDocumentServiceProvider);
      final success = action == _DocumentAction.print
          ? await service.printReport(page)
          : await service.shareReport(page);
      if (mounted && success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              action == _DocumentAction.print
                  ? 'Rekap dikirim ke layanan cetak.'
                  : 'PDF rekap siap dibagikan.',
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  Future<void> _showOverdue(GoodsLoanRecapPage page) =>
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        builder: (_) => _OverdueSheet(report: page.overdueReport),
      );

  void _showError(Object error) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(_message(error))));
}

enum _DocumentAction { print, share }

class _RecapSummary extends StatelessWidget {
  const _RecapSummary({required this.summary});
  final GoodsLoanRecapSummary summary;

  @override
  Widget build(BuildContext context) => GridView.count(
    physics: const NeverScrollableScrollPhysics(),
    shrinkWrap: true,
    crossAxisCount: 2,
    crossAxisSpacing: 8,
    mainAxisSpacing: 8,
    childAspectRatio: 2.15,
    children: [
      _SummaryTile(
        label: 'Masih dipinjam',
        value: summary.active,
        icon: Icons.inventory_2_outlined,
        color: NusaColors.primary,
      ),
      _SummaryTile(
        label: 'Terlambat',
        value: summary.overdue,
        icon: Icons.warning_amber_rounded,
        color: Theme.of(context).colorScheme.error,
      ),
      _SummaryTile(
        label: 'Jatuh tempo 7 hari',
        value: summary.dueSoon,
        icon: Icons.event_busy_outlined,
        color: const Color(0xFFD28A00),
      ),
      _SummaryTile(
        label: 'Tanpa rencana',
        value: summary.withoutPlan,
        icon: Icons.event_note_outlined,
        color: NusaColors.textSecondary,
      ),
    ],
  );
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });
  final String label;
  final int value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .08),
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: color.withValues(alpha: .2)),
    ),
    child: Row(
      children: [
        Icon(icon, color: color, size: 24),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '$value',
                style: TextStyle(
                  color: color,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 9),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _RecapCard extends StatelessWidget {
  const _RecapCard({
    required this.loan,
    required this.canReturn,
    required this.onDetail,
    this.onReturn,
  });
  final GoodsLoan loan;
  final bool canReturn;
  final VoidCallback onDetail;
  final VoidCallback? onReturn;

  @override
  Widget build(BuildContext context) {
    final color = loan.overdue
        ? Theme.of(context).colorScheme.error
        : (loan.status == 'selesai' ? NusaColors.success : NusaColors.primary);
    final outstanding = loan.items
        .where((item) => item.mustReturn && item.remaining > 0)
        .toList(growable: false);
    return Card(
      key: Key('goods-loan-recap-${loan.id}'),
      color: loan.overdue
          ? Theme.of(context).colorScheme.errorContainer.withValues(alpha: .18)
          : null,
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: .1),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(
                    loan.borrowerType == 'siswa'
                        ? Icons.school_outlined
                        : Icons.badge_outlined,
                    color: color,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        loan.borrowerName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      Text(
                        '${loan.number} · ${loan.borrowerIdentity}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 10,
                          color: NusaColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                _StatusBadge(label: loan.monitoringLabel, color: color),
              ],
            ),
            const SizedBox(height: 11),
            _FactRow(label: 'Dipinjam', value: loan.dateLabel),
            _FactRow(
              label: 'Rencana kembali',
              value: loan.plannedReturnLabel ?? '-',
            ),
            const SizedBox(height: 10),
            Text(
              outstanding.isEmpty
                  ? 'Tidak ada barang yang perlu kembali.'
                  : 'Barang belum kembali',
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
            ),
            if (outstanding.isNotEmpty) ...[
              const SizedBox(height: 5),
              ...outstanding
                  .take(3)
                  .map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 3),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 5,
                            height: 5,
                            margin: const EdgeInsets.only(top: 5, right: 7),
                            decoration: BoxDecoration(
                              color: color,
                              shape: BoxShape.circle,
                            ),
                          ),
                          Expanded(
                            child: Text(
                              '${item.goodsName} · ${_number(item.remaining)} ${item.unit}',
                              style: const TextStyle(fontSize: 11),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              if (outstanding.length > 3)
                Text(
                  '+${outstanding.length - 3} barang lainnya',
                  style: const TextStyle(
                    fontSize: 10,
                    color: NusaColors.textSecondary,
                  ),
                ),
            ],
            const SizedBox(height: 11),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: onDetail,
                    icon: const Icon(Icons.visibility_outlined),
                    label: const Text('Detail'),
                  ),
                ),
                if (canReturn && onReturn != null) ...[
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton.tonalIcon(
                      onPressed: onReturn,
                      icon: const Icon(Icons.assignment_return_rounded),
                      label: const Text('Kembalikan'),
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _FactRow extends StatelessWidget {
  const _FactRow({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 3),
    child: Row(
      children: [
        SizedBox(
          width: 106,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
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
  Widget build(BuildContext context) => ConstrainedBox(
    constraints: const BoxConstraints(maxWidth: 96),
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        textAlign: TextAlign.center,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: color,
          fontSize: 9,
          fontWeight: FontWeight.w800,
        ),
      ),
    ),
  );
}

class _OverdueSheet extends StatefulWidget {
  const _OverdueSheet({required this.report});
  final GoodsLoanOverdueReport report;
  @override
  State<_OverdueSheet> createState() => _OverdueSheetState();
}

class _OverdueSheetState extends State<_OverdueSheet> {
  bool _copied = false;
  @override
  Widget build(BuildContext context) => SafeArea(
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * .82).clamp(420, 760),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Daftar Barang Terlambat',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        '${widget.report.count} transaksi sesuai filter',
                        style: const TextStyle(
                          fontSize: 11,
                          color: NusaColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Tutup',
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Expanded(
              child: Container(
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: NusaColors.outline),
                ),
                child: SingleChildScrollView(
                  child: SelectableText(
                    widget.report.text,
                    key: const Key('goods-loan-overdue-text'),
                    style: const TextStyle(fontSize: 12, height: 1.45),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              key: const Key('copy-goods-loan-overdue-text'),
              onPressed: _copy,
              icon: Icon(_copied ? Icons.check_rounded : Icons.copy_rounded),
              label: Text(_copied ? 'Daftar berhasil disalin' : 'Salin daftar'),
            ),
          ],
        ),
      ),
    ),
  );

  Future<void> _copy() async {
    await Clipboard.setData(ClipboardData(text: widget.report.text));
    if (mounted) setState(() => _copied = true);
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();
  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.fact_check_outlined, size: 54, color: NusaColors.primary),
          SizedBox(height: 12),
          Text(
            'Belum ada transaksi pada pilihan rekap ini.',
            textAlign: TextAlign.center,
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

String _statusLabel(GoodsLoanRecapPage page) =>
    page.monitoringStatuses
        .where((item) => item.value == page.filter.monitoringStatus)
        .firstOrNull
        ?.label ??
    page.filter.monitoringStatus;
String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');
String _message(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final values = error.errors.values.expand((item) => item);
    if (values.isNotEmpty) return values.first;
  }
  return error is AppException
      ? error.message
      : 'Rekap peminjaman belum dapat dimuat.';
}
