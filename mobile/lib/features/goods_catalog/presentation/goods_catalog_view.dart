import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_catalog/application/goods_catalog_controller.dart';
import 'package:nusa/features/goods_catalog/domain/goods_catalog.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsCatalogView extends ConsumerStatefulWidget {
  const GoodsCatalogView({super.key});

  @override
  ConsumerState<GoodsCatalogView> createState() => _GoodsCatalogViewState();
}

class _GoodsCatalogViewState extends ConsumerState<GoodsCatalogView> {
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
    final state = ref.watch(goodsCatalogControllerProvider);
    final page = state.value;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Katalog Barang'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(goodsCatalogControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (page != null) _header(page, state.isLoading),
            Expanded(
              child: state.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => _CatalogError(
                  message: goodsCatalogMessage(error),
                  onRetry: ref
                      .read(goodsCatalogControllerProvider.notifier)
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

  Widget _header(GoodsCatalogPage page, bool loading) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
    child: Column(
      children: [
        _CatalogSummary(summary: page.summary),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: NusaTextField(
                fieldKey: const Key('goods-catalog-search'),
                controller: _search,
                hintText: 'Nama, kode, atau kategori',
                prefixIcon: Icons.search_rounded,
                enabled: !loading,
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
              isLabelVisible: page.filter.activeCount > 0,
              label: Text('${page.filter.activeCount}'),
              child: IconButton.filledTonal(
                key: const Key('goods-catalog-filter'),
                tooltip: 'Filter katalog',
                onPressed: loading ? null : () => _openFilter(page),
                icon: const Icon(Icons.tune_rounded),
              ),
            ),
          ],
        ),
      ],
    ),
  );

  Widget _content(GoodsCatalogPage page) => RefreshIndicator(
    onRefresh: ref.read(goodsCatalogControllerProvider.notifier).refresh,
    child: page.items.isEmpty
        ? ListView(
            key: const Key('goods-catalog-empty'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(28),
            children: const [
              SizedBox(height: 56),
              Icon(
                Icons.inventory_2_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada barang yang sesuai dengan pencarian atau filter.',
                textAlign: TextAlign.center,
              ),
            ],
          )
        : ListView.separated(
            key: const Key('goods-catalog-list'),
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
              return _CatalogCard(
                item: item,
                onTap: () => context.push('/katalog-barang/${item.id}'),
              );
            },
          ),
  );

  void _onSearch(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 700),
      () => ref.read(goodsCatalogControllerProvider.notifier).search(value),
    );
  }

  void _clearSearch() {
    _debounce?.cancel();
    _search.clear();
    setState(() {});
    ref.read(goodsCatalogControllerProvider.notifier).search('');
  }

  Future<void> _openFilter(GoodsCatalogPage page) async {
    final value = await showModalBottomSheet<GoodsCatalogFilter>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _CatalogFilterSheet(page: page),
    );
    if (value == null || !mounted) return;
    await ref.read(goodsCatalogControllerProvider.notifier).applyFilter(value);
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      await ref.read(goodsCatalogControllerProvider.notifier).loadMore();
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _CatalogSummary extends StatelessWidget {
  const _CatalogSummary({required this.summary});
  final GoodsCatalogSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('goods-catalog-summary'),
    padding: const EdgeInsets.fromLTRB(5, 12, 5, 10),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: .15),
          blurRadius: 14,
          offset: const Offset(0, 6),
        ),
      ],
    ),
    child: Column(
      children: [
        Row(
          children: [
            _SummaryItem(value: summary.activeGoods, label: 'Barang aktif'),
            _SummaryItem(value: summary.availableUnits, label: 'Unit tersedia'),
            _SummaryItem(value: summary.borrowedUnits, label: 'Dipinjam'),
          ],
        ),
        const SizedBox(height: 8),
        Container(height: 1, color: Colors.white.withValues(alpha: .12)),
        const SizedBox(height: 7),
        Text(
          '${summary.availableStock} barang stok tersedia · '
          '${summary.outOfStock} stok habis',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: Color(0xFFCADBED), fontSize: 10),
        ),
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

class _CatalogCard extends StatelessWidget {
  const _CatalogCard({required this.item, required this.onTap});
  final GoodsCatalogItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = item.available
        ? NusaColors.success
        : Theme.of(context).colorScheme.error;
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        key: Key('goods-catalog-${item.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: NusaColors.primary.withValues(alpha: .09),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  item.isAsset
                      ? Icons.devices_other_rounded
                      : Icons.inventory_2_rounded,
                  color: NusaColors.primary,
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
                            item.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: color.withValues(alpha: .1),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            item.available ? 'Tersedia' : 'Habis',
                            style: TextStyle(
                              color: color,
                              fontSize: 9,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${item.code} · ${item.category}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Icon(
                          Icons.check_circle_outline_rounded,
                          size: 15,
                          color: color,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            '${item.availableLabel} tersedia',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: color,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        Text(
                          item.typeLabel,
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 10,
                          ),
                        ),
                        const SizedBox(width: 2),
                        const Icon(
                          Icons.chevron_right_rounded,
                          size: 18,
                          color: NusaColors.textSecondary,
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

class _CatalogFilterSheet extends StatefulWidget {
  const _CatalogFilterSheet({required this.page});
  final GoodsCatalogPage page;

  @override
  State<_CatalogFilterSheet> createState() => _CatalogFilterSheetState();
}

class _CatalogFilterSheetState extends State<_CatalogFilterSheet> {
  late int _categoryId;
  late String _type;
  late String _availability;

  @override
  void initState() {
    super.initState();
    _categoryId = widget.page.filter.categoryId ?? 0;
    _type = widget.page.filter.type;
    _availability = widget.page.filter.availability;
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
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Filter Katalog',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                ),
              ),
              TextButton(
                onPressed: () => setState(() {
                  _categoryId = 0;
                  _type = 'semua';
                  _availability = 'semua';
                }),
                child: const Text('Reset'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          NusaDropdownField<int>(
            fieldKey: const Key('goods-catalog-category-filter'),
            value: _categoryId,
            options: [
              const NusaDropdownOption(value: 0, label: 'Semua kategori'),
              ...widget.page.categories.map(
                (item) => NusaDropdownOption(value: item.id, label: item.label),
              ),
            ],
            decoration: const InputDecoration(labelText: 'Kategori barang'),
            onChanged: (value) => setState(() => _categoryId = value ?? 0),
          ),
          const SizedBox(height: 12),
          NusaDropdownField<String>(
            fieldKey: const Key('goods-catalog-type-filter'),
            value: _type,
            options: widget.page.types
                .map(
                  (item) =>
                      NusaDropdownOption(value: item.value, label: item.label),
                )
                .toList(),
            decoration: const InputDecoration(labelText: 'Jenis barang'),
            onChanged: (value) => setState(() => _type = value ?? 'semua'),
          ),
          const SizedBox(height: 12),
          NusaDropdownField<String>(
            fieldKey: const Key('goods-catalog-availability-filter'),
            value: _availability,
            options: widget.page.availabilityOptions
                .map(
                  (item) =>
                      NusaDropdownOption(value: item.value, label: item.label),
                )
                .toList(),
            decoration: const InputDecoration(labelText: 'Ketersediaan'),
            onChanged: (value) =>
                setState(() => _availability = value ?? 'semua'),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            key: const Key('apply-goods-catalog-filter'),
            onPressed: () => Navigator.pop(
              context,
              GoodsCatalogFilter(
                query: widget.page.filter.query,
                categoryId: _categoryId == 0 ? null : _categoryId,
                type: _type,
                availability: _availability,
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

class _CatalogError extends StatelessWidget {
  const _CatalogError({required this.message, required this.onRetry});
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
