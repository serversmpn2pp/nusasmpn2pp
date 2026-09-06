import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_request/domain/goods_request.dart';
import 'package:nusa/features/goods_request/presentation/goods_request_view.dart';
import 'package:nusa/features/my_goods_request/application/my_goods_request_controller.dart';
import 'package:nusa/features/my_goods_request/domain/my_goods_request.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class MyGoodsRequestView extends ConsumerStatefulWidget {
  const MyGoodsRequestView({super.key});
  @override
  ConsumerState<MyGoodsRequestView> createState() => _MyGoodsRequestViewState();
}

class _MyGoodsRequestViewState extends ConsumerState<MyGoodsRequestView> {
  bool _loadingMore = false;
  @override
  Widget build(BuildContext context) {
    final state = ref.watch(myGoodsRequestControllerProvider);
    final page = state.value;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengajuan Saya'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(myGoodsRequestControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        key: const Key('add-my-goods-request'),
        onPressed: () => context.push('/pengajuan-saya/tambah'),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Ajukan'),
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
                        const Expanded(
                          child: Text(
                            'Riwayat permintaan dan peminjaman Anda',
                            style: TextStyle(
                              fontSize: 12,
                              color: NusaColors.textSecondary,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Badge(
                          isLabelVisible: page.status != 'semua',
                          child: IconButton.filledTonal(
                            key: const Key('my-goods-request-filter'),
                            tooltip: 'Filter status',
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
                      .read(myGoodsRequestControllerProvider.notifier)
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

  Widget _content(MyGoodsRequestPage page) => RefreshIndicator(
    onRefresh: ref.read(myGoodsRequestControllerProvider.notifier).refresh,
    child: page.items.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(28),
            children: const [
              SizedBox(height: 70),
              Icon(
                Icons.request_page_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text('Belum ada pengajuan barang.', textAlign: TextAlign.center),
            ],
          )
        : ListView.separated(
            key: const Key('my-goods-request-list'),
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
              return _RequestCard(
                item: item,
                onTap: () => context.push('/pengajuan-saya/${item.id}'),
              );
            },
          ),
  );

  Future<void> _filter(MyGoodsRequestPage page) async {
    final result = await showModalBottomSheet<String>(
      context: context,
      useSafeArea: true,
      builder: (_) => _StatusFilter(page: page),
    );
    if (result != null && mounted) {
      await ref.read(myGoodsRequestControllerProvider.notifier).filter(result);
    }
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      await ref.read(myGoodsRequestControllerProvider.notifier).loadMore();
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
        key: Key('my-goods-request-${item.id}'),
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
                            item.goodsName,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        const SizedBox(width: 6),
                        _Badge(label: item.statusLabel, color: color),
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
                      '${goodsRequestNumber(item.quantity)} ${item.unit} · Butuh ${item.requiredDateLabel}',
                      style: const TextStyle(fontSize: 11),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      item.typeLabel,
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
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final MyGoodsRequestSummary summary;
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
        _SummaryItem(value: summary.fulfilled, label: 'Dipenuhi'),
        _SummaryItem(value: summary.finished, label: 'Selesai'),
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

class _StatusFilter extends StatefulWidget {
  const _StatusFilter({required this.page});
  final MyGoodsRequestPage page;
  @override
  State<_StatusFilter> createState() => _StatusFilterState();
}

class _StatusFilterState extends State<_StatusFilter> {
  late String _status;
  @override
  void initState() {
    super.initState();
    _status = widget.page.status;
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 18),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Filter Status',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 16),
        NusaDropdownField<String>(
          fieldKey: const Key('my-goods-request-status-filter'),
          value: _status,
          options: widget.page.statuses
              .map(
                (item) =>
                    NusaDropdownOption(value: item.value, label: item.label),
              )
              .toList(),
          decoration: const InputDecoration(labelText: 'Status pengajuan'),
          onChanged: (value) => setState(() => _status = value ?? 'semua'),
        ),
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: () => Navigator.pop(context, _status),
          icon: const Icon(Icons.check_rounded),
          label: const Text('Terapkan Filter'),
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
