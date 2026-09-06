import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_loan/application/goods_loan_controller.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';
import 'package:nusa/features/goods_loan/presentation/widgets/goods_return_form_sheet.dart';

class GoodsLoanDetailView extends ConsumerStatefulWidget {
  const GoodsLoanDetailView({
    required this.loanId,
    this.openReturn = false,
    this.initialDetailId,
    super.key,
  });
  final int loanId;
  final bool openReturn;
  final int? initialDetailId;

  @override
  ConsumerState<GoodsLoanDetailView> createState() =>
      _GoodsLoanDetailViewState();
}

class _GoodsLoanDetailViewState extends ConsumerState<GoodsLoanDetailView> {
  late Future<GoodsLoanDetailResponse> _future;
  bool _processing = false;
  bool _autoOpened = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<GoodsLoanDetailResponse> _load() =>
      ref.read(goodsLoanActionsProvider).detail(widget.loanId);

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Detail Peminjaman'),
      actions: [
        IconButton(
          tooltip: 'Perbarui',
          onPressed: _processing
              ? null
              : () => setState(() => _future = _load()),
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
    ),
    body: SafeArea(
      top: false,
      child: FutureBuilder<GoodsLoanDetailResponse>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorState(
              message: _message(snapshot.error!),
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final detail = snapshot.requireData;
          if (widget.openReturn && !_autoOpened && detail.access.canReturn) {
            _autoOpened = true;
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (mounted) _returnGoods(detail);
            });
          }
          return _content(detail);
        },
      ),
    ),
  );

  Widget _content(GoodsLoanDetailResponse detail) {
    final loan = detail.loan;
    final color = loan.overdue
        ? Theme.of(context).colorScheme.error
        : (loan.status == 'selesai' ? NusaColors.success : NusaColors.primary);
    return Stack(
      children: [
        RefreshIndicator(
          onRefresh: () async {
            final result = await _load();
            if (mounted) setState(() => _future = Future.value(result));
          },
          child: ListView(
            key: const Key('goods-loan-detail-scroll'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.fromLTRB(
              16,
              8,
              16,
              detail.access.canReturn ? 96 : 24,
            ),
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
                      loan.number,
                      style: const TextStyle(
                        color: NusaColors.accent,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      loan.borrowerName,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      '${loan.borrowerIdentity} · ${loan.borrowerTypeLabel}',
                      style: const TextStyle(
                        color: Color(0xFFCADBED),
                        fontSize: 11,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _Pill(
                          icon: Icons.event_outlined,
                          label: loan.dateLabel,
                        ),
                        _Pill(
                          icon: Icons.assignment_return_outlined,
                          label: loan.monitoringLabel,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              _InfoCard(
                children: [
                  _InfoRow(
                    label: 'Status',
                    value: loan.statusLabel,
                    color: color,
                  ),
                  _InfoRow(
                    label: 'Rencana kembali',
                    value: loan.plannedReturnLabel ?? '-',
                  ),
                  _InfoRow(
                    label: 'Dicatat oleh',
                    value: loan.createdBy ?? 'Sistem',
                  ),
                  if (loan.notes?.trim().isNotEmpty == true)
                    _InfoRow(label: 'Catatan', value: loan.notes!),
                ],
              ),
              const SizedBox(height: 18),
              Text(
                'Barang (${loan.items.length})',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 9),
              ...loan.items.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: 9),
                  child: _LoanItemCard(item: item),
                ),
              ),
              if (loan.returns.isNotEmpty) ...[
                const SizedBox(height: 10),
                Text(
                  'Riwayat Pengembalian (${loan.returns.length})',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 9),
                ...loan.returns.map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 9),
                    child: _ReturnHistoryCard(item: item),
                  ),
                ),
              ],
            ],
          ),
        ),
        if (detail.access.canReturn)
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: FilledButton.icon(
              key: const Key('return-goods-loan'),
              onPressed: _processing ? null : () => _returnGoods(detail),
              icon: _processing
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.assignment_return_rounded),
              label: const Text('Catat Pengembalian'),
            ),
          ),
      ],
    );
  }

  Future<void> _returnGoods(GoodsLoanDetailResponse detail) async {
    final value = await showModalBottomSheet<GoodsReturnFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => GoodsReturnFormSheet(
        detail: detail,
        initialDetailId: widget.initialDetailId,
      ),
    );
    if (value == null || !mounted) return;
    setState(() => _processing = true);
    try {
      final result = await ref
          .read(goodsLoanActionsProvider)
          .returnGoods(loanId: widget.loanId, value: value);
      ref.invalidate(goodsLoanControllerProvider);
      ref.invalidate(goodsReturnControllerProvider);
      if (mounted) {
        setState(() => _future = Future.value(result));
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Pengembalian barang berhasil dicatat.'),
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_message(error))));
      }
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }
}

class _LoanItemCard extends StatelessWidget {
  const _LoanItemCard({required this.item});
  final GoodsLoanItem item;
  @override
  Widget build(BuildContext context) {
    final done = !item.mustReturn || item.remaining <= 0;
    final color = done ? NusaColors.success : NusaColors.primary;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: color.withValues(alpha: .1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                item.assetUnitId != null
                    ? Icons.devices_other_outlined
                    : Icons.inventory_2_outlined,
                color: color,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.goodsName,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  Text(
                    '${item.code} · ${item.location}',
                    style: const TextStyle(
                      fontSize: 11,
                      color: NusaColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(
                    item.mustReturn
                        ? 'Dipinjam ${_number(item.quantity)} · Kembali ${_number(item.returned)} · Sisa ${_number(item.remaining)} ${item.unit}'
                        : '${_number(item.quantity)} ${item.unit} · Habis pakai, tidak dikembalikan',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: color,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ReturnHistoryCard extends StatelessWidget {
  const _ReturnHistoryCard({required this.item});
  final GoodsReturnHistory item;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  item.number,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              Text(
                item.dateLabel,
                style: const TextStyle(
                  fontSize: 10,
                  color: NusaColors.textSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          ...item.items.map(
            (line) => Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                '• ${line.goodsName}: ${_number(line.quantity)} ${line.unit}${line.conditionLabel == null ? '' : ' · ${line.conditionLabel}'}',
                style: const TextStyle(fontSize: 11),
              ),
            ),
          ),
          const SizedBox(height: 7),
          Text(
            'Dicatat oleh ${item.createdBy}',
            style: const TextStyle(
              fontSize: 10,
              color: NusaColors.textSecondary,
            ),
          ),
        ],
      ),
    ),
  );
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.children});
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(children: children),
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value, this.color});
  final String label;
  final String value;
  final Color? color;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 112,
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: NusaColors.textSecondary,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
        ),
      ],
    ),
  );
}

class _Pill extends StatelessWidget {
  const _Pill({required this.icon, required this.label});
  final IconData icon;
  final String label;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .12),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: Colors.white, size: 14),
        const SizedBox(width: 5),
        Text(
          label,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 10,
            fontWeight: FontWeight.w700,
          ),
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
      : 'Detail peminjaman belum dapat diproses.';
}
