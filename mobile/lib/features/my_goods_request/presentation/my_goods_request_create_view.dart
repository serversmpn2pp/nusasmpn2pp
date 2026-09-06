import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_request/presentation/goods_request_view.dart';
import 'package:nusa/features/my_goods_request/application/my_goods_request_controller.dart';
import 'package:nusa/features/my_goods_request/domain/my_goods_request.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class MyGoodsRequestCreateView extends ConsumerStatefulWidget {
  const MyGoodsRequestCreateView({super.key});
  @override
  ConsumerState<MyGoodsRequestCreateView> createState() =>
      _MyGoodsRequestCreateViewState();
}

class _MyGoodsRequestCreateViewState
    extends ConsumerState<MyGoodsRequestCreateView> {
  final _search = TextEditingController();
  final _quantity = TextEditingController(text: '1');
  final _purpose = TextEditingController();
  late Future<MyGoodsCatalogPage> _future;
  Timer? _debounce;
  MyGoodsCatalogItem? _selected;
  DateTime _requiredDate = DateUtils.dateOnly(DateTime.now());
  DateTime _plannedReturn = DateUtils.dateOnly(
    DateTime.now().add(const Duration(days: 7)),
  );
  bool _loadingMore = false;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _future = _loadCatalog();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _quantity.dispose();
    _purpose.dispose();
    super.dispose();
  }

  Future<MyGoodsCatalogPage> _loadCatalog({String query = '', int page = 1}) =>
      ref.read(myGoodsRequestActionsProvider).catalog(query: query, page: page);

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      leading: _selected == null
          ? null
          : IconButton(
              tooltip: 'Kembali ke katalog',
              onPressed: () => setState(() {
                _selected = null;
                _error = null;
              }),
              icon: const Icon(Icons.arrow_back_rounded),
            ),
      title: Text(_selected == null ? 'Pilih Barang' : 'Buat Pengajuan'),
      actions: _selected == null
          ? [
              IconButton(
                tooltip: 'Perbarui',
                onPressed: () =>
                    setState(() => _future = _loadCatalog(query: _search.text)),
                icon: const Icon(Icons.refresh_rounded),
              ),
            ]
          : null,
    ),
    body: SafeArea(
      top: false,
      child: _selected == null ? _catalog() : _form(_selected!),
    ),
  );

  Widget _catalog() => Column(
    children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Katalog Barang Sekolah',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 3),
            const Text(
              'Pilih barang yang akan dipinjam atau diminta.',
              style: TextStyle(fontSize: 11, color: NusaColors.textSecondary),
            ),
            const SizedBox(height: 10),
            NusaTextField(
              fieldKey: const Key('my-goods-catalog-search'),
              controller: _search,
              hintText: 'Nama, kode, atau kategori',
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
          ],
        ),
      ),
      Expanded(
        child: FutureBuilder<MyGoodsCatalogPage>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return _Error(
                message: goodsRequestMessage(snapshot.error!),
                onRetry: () =>
                    setState(() => _future = _loadCatalog(query: _search.text)),
              );
            }
            final page = snapshot.requireData;
            if (page.items.isEmpty) {
              return const Center(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: Text(
                    'Barang tidak ditemukan.',
                    textAlign: TextAlign.center,
                  ),
                ),
              );
            }
            return ListView.separated(
              key: const Key('my-goods-catalog-list'),
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
              itemCount:
                  page.items.length + (page.pagination.hasNextPage ? 1 : 0),
              separatorBuilder: (_, _) => const SizedBox(height: 9),
              itemBuilder: (context, index) {
                if (index == page.items.length) {
                  return Center(
                    child: TextButton.icon(
                      onPressed: _loadingMore ? null : () => _loadMore(page),
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
                return _CatalogCard(
                  item: item,
                  onTap: item.available
                      ? () => setState(() {
                          _selected = item;
                          _quantity.text = '1';
                          _error = null;
                        })
                      : null,
                );
              },
            );
          },
        ),
      ),
    ],
  );

  Widget _form(MyGoodsCatalogItem item) => ListView(
    key: const Key('my-goods-request-form-scroll'),
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
    children: [
      Container(
        padding: const EdgeInsets.all(15),
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
              item.category,
              style: const TextStyle(
                color: NusaColors.accent,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              item.name,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 19,
                fontWeight: FontWeight.w900,
              ),
            ),
            Text(
              '${item.code} · ${item.serviceLabel}',
              style: const TextStyle(color: Color(0xFFCADBED), fontSize: 10),
            ),
            const SizedBox(height: 10),
            Text(
              'Tersedia ${goodsRequestNumber(item.availableQuantity)} ${item.unit}',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 14),
      Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Rincian Pengajuan',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 14),
              TextField(
                key: const Key('my-goods-request-quantity'),
                controller: _quantity,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')),
                ],
                decoration: InputDecoration(
                  labelText: 'Jumlah',
                  suffixText: item.unit,
                ),
              ),
              const SizedBox(height: 12),
              _DateButton(
                key: const Key('my-goods-request-required-date'),
                label: 'Tanggal dibutuhkan',
                value: _requiredDate,
                onTap: _pickRequiredDate,
              ),
              if (item.mustReturn) ...[
                const SizedBox(height: 12),
                _DateButton(
                  key: const Key('my-goods-request-return-date'),
                  label: 'Rencana kembali',
                  value: _plannedReturn,
                  onTap: _pickReturnDate,
                ),
              ],
              const SizedBox(height: 12),
              TextField(
                key: const Key('my-goods-request-purpose'),
                controller: _purpose,
                minLines: 3,
                maxLines: 5,
                maxLength: 1000,
                decoration: const InputDecoration(
                  labelText: 'Tujuan penggunaan',
                  hintText: 'Contoh: Pembelajaran Informatika kelas VIII.A',
                ),
              ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12,
                    ),
                  ),
                ),
              NusaPrimaryButton(
                key: const Key('submit-my-goods-request'),
                label: 'Kirim Pengajuan',
                loading: _saving,
                onPressed: _saving ? null : () => _submit(item),
              ),
            ],
          ),
        ),
      ),
    ],
  );

  void _onSearch(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) setState(() => _future = _loadCatalog(query: value.trim()));
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _search.clear();
    setState(() => _future = _loadCatalog());
  }

  Future<void> _loadMore(MyGoodsCatalogPage page) async {
    setState(() => _loadingMore = true);
    try {
      final next = await _loadCatalog(
        query: _search.text,
        page: page.pagination.page + 1,
      );
      if (mounted) setState(() => _future = Future.value(page.append(next)));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(goodsRequestMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _pickRequiredDate() async {
    final now = DateUtils.dateOnly(DateTime.now());
    final value = await showDatePicker(
      context: context,
      firstDate: now,
      lastDate: now.add(const Duration(days: 3650)),
      initialDate: _requiredDate.isBefore(now) ? now : _requiredDate,
    );
    if (value != null) {
      setState(() {
        _requiredDate = value;
        if (_plannedReturn.isBefore(value)) {
          _plannedReturn = value.add(const Duration(days: 7));
        }
      });
    }
  }

  Future<void> _pickReturnDate() async {
    final value = await showDatePicker(
      context: context,
      firstDate: _requiredDate,
      lastDate: _requiredDate.add(const Duration(days: 3650)),
      initialDate: _plannedReturn.isBefore(_requiredDate)
          ? _requiredDate
          : _plannedReturn,
    );
    if (value != null) setState(() => _plannedReturn = value);
  }

  Future<void> _submit(MyGoodsCatalogItem item) async {
    final quantity = double.tryParse(
      _quantity.text.trim().replaceAll(',', '.'),
    );
    if (quantity == null || quantity <= 0) {
      setState(() => _error = 'Jumlah harus lebih dari 0.');
      return;
    }
    if (item.mustReturn && quantity != quantity.roundToDouble()) {
      setState(
        () => _error = 'Jumlah barang pinjaman harus berupa bilangan bulat.',
      );
      return;
    }
    if (quantity > item.availableQuantity) {
      setState(() => _error = 'Jumlah melebihi ketersediaan saat ini.');
      return;
    }
    if (_purpose.text.trim().length < 5) {
      setState(() => _error = 'Tujuan penggunaan minimal 5 karakter.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final result = await ref
          .read(myGoodsRequestActionsProvider)
          .create(
            MyGoodsRequestFormValue(
              goodsId: item.id,
              quantity: quantity,
              requiredDate: _requiredDate,
              plannedReturn: item.mustReturn ? _plannedReturn : null,
              purpose: _purpose.text,
            ),
          );
      ref.invalidate(myGoodsRequestControllerProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Pengajuan berhasil dikirim kepada petugas inventaris.',
            ),
          ),
        );
        context.pushReplacement('/pengajuan-saya/${result.request.id}');
      }
    } catch (error) {
      if (mounted) setState(() => _error = goodsRequestMessage(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _CatalogCard extends StatelessWidget {
  const _CatalogCard({required this.item, required this.onTap});
  final MyGoodsCatalogItem item;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      key: Key('my-goods-catalog-${item.id}'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Opacity(
        opacity: item.available ? 1 : .58,
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            children: [
              Container(
                width: 43,
                height: 43,
                decoration: BoxDecoration(
                  color: NusaColors.primary.withValues(alpha: .1),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  item.mustReturn
                      ? Icons.devices_other_outlined
                      : Icons.inventory_2_outlined,
                  color: NusaColors.primary,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    Text(
                      '${item.category} · ${item.code}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 10,
                        color: NusaColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      item.available
                          ? 'Tersedia ${goodsRequestNumber(item.availableQuantity)} ${item.unit}'
                          : 'Belum tersedia',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: item.available
                            ? NusaColors.success
                            : NusaColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              Icon(
                item.available
                    ? Icons.chevron_right_rounded
                    : Icons.block_rounded,
                color: NusaColors.textSecondary,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _DateButton extends StatelessWidget {
  const _DateButton({
    required super.key,
    required this.label,
    required this.value,
    required this.onTap,
  });
  final String label;
  final DateTime value;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => OutlinedButton.icon(
    onPressed: onTap,
    icon: const Icon(Icons.calendar_today_outlined),
    label: Row(
      children: [
        Expanded(child: Text(label)),
        Text(_date(value), style: const TextStyle(fontWeight: FontWeight.w800)),
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

String _date(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
