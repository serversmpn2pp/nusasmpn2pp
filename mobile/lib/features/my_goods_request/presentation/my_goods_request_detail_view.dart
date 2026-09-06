import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_request/presentation/goods_request_view.dart';
import 'package:nusa/features/my_goods_request/application/my_goods_request_controller.dart';
import 'package:nusa/features/my_goods_request/domain/my_goods_request.dart';

class MyGoodsRequestDetailView extends ConsumerStatefulWidget {
  const MyGoodsRequestDetailView({required this.requestId, super.key});
  final int requestId;
  @override
  ConsumerState<MyGoodsRequestDetailView> createState() =>
      _MyGoodsRequestDetailViewState();
}

class _MyGoodsRequestDetailViewState
    extends ConsumerState<MyGoodsRequestDetailView> {
  late Future<MyGoodsRequestDetail> _future;
  bool _processing = false;
  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<MyGoodsRequestDetail> _load() =>
      ref.read(myGoodsRequestActionsProvider).detail(widget.requestId);
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Detail Pengajuan Saya'),
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
      child: FutureBuilder<MyGoodsRequestDetail>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _Error(
              message: goodsRequestMessage(snapshot.error!),
              onRetry: () => setState(() => _future = _load()),
            );
          }
          return _content(snapshot.requireData);
        },
      ),
    ),
  );

  Widget _content(MyGoodsRequestDetail detail) {
    final item = detail.request;
    final color = requestStatusColor(context, item.status);
    return Stack(
      children: [
        RefreshIndicator(
          onRefresh: () async {
            final value = await _load();
            if (mounted) setState(() => _future = Future.value(value));
          },
          child: ListView(
            key: const Key('my-goods-request-detail-scroll'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.fromLTRB(16, 8, 16, detail.canCancel ? 92 : 24),
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
                      item.number,
                      style: const TextStyle(
                        color: NusaColors.accent,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      item.goodsName,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      '${item.goodsCode} · ${item.category ?? '-'}',
                      style: const TextStyle(
                        color: Color(0xFFCADBED),
                        fontSize: 11,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: .12),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        item.statusLabel,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              _Card(
                title: 'Informasi Pengajuan',
                children: [
                  _Row(label: 'Jenis', value: item.typeLabel),
                  _Row(
                    label: 'Jumlah',
                    value: '${goodsRequestNumber(item.quantity)} ${item.unit}',
                  ),
                  _Row(label: 'Status', value: item.statusLabel, color: color),
                  _Row(
                    label: 'Tanggal pengajuan',
                    value: item.submissionDateLabel,
                  ),
                  _Row(
                    label: 'Tanggal dibutuhkan',
                    value: item.requiredDateLabel,
                  ),
                  if (item.type == 'peminjaman')
                    _Row(
                      label: 'Rencana kembali',
                      value: item.plannedReturnLabel ?? '-',
                    ),
                  _Row(label: 'Tujuan penggunaan', value: item.purpose ?? '-'),
                ],
              ),
              const SizedBox(height: 12),
              _Card(
                title: 'Tanggapan Petugas',
                children: [
                  _Row(
                    label: 'Catatan',
                    value: item.officerNotes?.trim().isNotEmpty == true
                        ? item.officerNotes!
                        : 'Belum ada catatan.',
                  ),
                  if (item.loanNumber != null)
                    _Row(label: 'Nomor transaksi', value: item.loanNumber!),
                ],
              ),
            ],
          ),
        ),
        if (detail.canCancel)
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: FilledButton.icon(
              key: const Key('cancel-my-goods-request'),
              style: FilledButton.styleFrom(
                backgroundColor: Theme.of(context).colorScheme.error,
              ),
              onPressed: _processing ? null : _cancel,
              icon: const Icon(Icons.close_rounded),
              label: const Text('Batalkan Pengajuan'),
            ),
          ),
      ],
    );
  }

  Future<void> _cancel() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Batalkan pengajuan?'),
        content: const Text(
          'Pengajuan yang dibatalkan tidak dapat dikirim kembali.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Tidak'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Ya, Batalkan'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _processing = true);
    try {
      final value = await ref
          .read(myGoodsRequestActionsProvider)
          .cancel(widget.requestId);
      ref.invalidate(myGoodsRequestControllerProvider);
      if (mounted) {
        setState(() => _future = Future.value(value));
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pengajuan berhasil dibatalkan.')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(goodsRequestMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.title, required this.children});
  final String title;
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          ...children,
        ],
      ),
    ),
  );
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value, this.color});
  final String label;
  final String value;
  final Color? color;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 118,
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
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
        ),
      ],
    ),
  );
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.onRetry});
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
