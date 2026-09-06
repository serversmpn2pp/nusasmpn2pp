import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_label/application/inventory_label_controller.dart';
import 'package:nusa/features/inventory_label/application/inventory_label_document_service.dart';
import 'package:nusa/features/inventory_label/domain/inventory_label.dart';
import 'package:nusa/features/inventory_label/presentation/widgets/inventory_label_preview.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class InventoryLabelView extends ConsumerStatefulWidget {
  const InventoryLabelView({this.initialReceiptId, super.key});

  final int? initialReceiptId;

  @override
  ConsumerState<InventoryLabelView> createState() => _InventoryLabelViewState();
}

class _InventoryLabelViewState extends ConsumerState<InventoryLabelView> {
  Set<int> _selected = {};
  String? _pageFingerprint;
  String _sizeValue = 'sedang';
  int _copies = 1;
  bool _processing = false;
  bool _initialFilterApplied = false;

  @override
  void initState() {
    super.initState();
    if (widget.initialReceiptId != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted || _initialFilterApplied) return;
        _initialFilterApplied = true;
        ref
            .read(inventoryLabelControllerProvider.notifier)
            .apply(
              InventoryLabelFilters(
                type: '',
                receiptId: widget.initialReceiptId,
              ),
            );
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(inventoryLabelControllerProvider);
    final page = state.value;
    if (page != null) _synchronize(page);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Label Inventaris'),
        actions: [
          IconButton(
            tooltip: 'Muat ulang',
            onPressed: state.isLoading
                ? null
                : ref.read(inventoryLabelControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stackTrace) => _ErrorState(
          message: _errorMessage(error),
          onRetry: ref.read(inventoryLabelControllerProvider.notifier).refresh,
        ),
        data: _content,
      ),
      bottomNavigationBar: page == null
          ? null
          : _PrintBar(
              selected: _selected.length,
              copies: _copies,
              processing: _processing,
              onShare: _selected.isEmpty || _processing
                  ? null
                  : () => _generate(page, share: true),
              onPrint: _selected.isEmpty || _processing
                  ? null
                  : () => _generate(page, share: false),
            ),
    );
  }

  Widget _content(InventoryLabelPage page) {
    final size = _selectedSize(page);
    final selectedItems = page.items
        .where((item) => _selected.contains(item.id))
        .toList(growable: false);
    return RefreshIndicator(
      onRefresh: ref.read(inventoryLabelControllerProvider.notifier).refresh,
      child: CustomScrollView(
        key: const PageStorageKey<String>('inventory-label-page'),
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            sliver: SliverToBoxAdapter(child: _IntroCard(rules: page.rules)),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            sliver: SliverToBoxAdapter(
              child: _ConfigurationCard(
                page: page,
                sizeValue: _sizeValue,
                copies: _copies,
                onTypeChanged: (value) {
                  if (value == null || value == page.filters.type) return;
                  ref
                      .read(inventoryLabelControllerProvider.notifier)
                      .switchType(value);
                },
                onOpenFilters: () => _openFilters(page),
                onSizeChanged: (value) {
                  if (value != null) setState(() => _sizeValue = value);
                },
                onCopiesChanged: (value) => setState(() => _copies = value),
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            sliver: SliverToBoxAdapter(
              child: _SelectionHeader(
                selected: _selected.length,
                total: page.items.length,
                maximum: page.rules.maximumSelection,
                onAll: page.items.isEmpty ? null : () => _selectAll(page),
                onNone: _selected.isEmpty
                    ? null
                    : () => setState(_selected.clear),
              ),
            ),
          ),
          if (selectedItems.isNotEmpty)
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 2, 16, 12),
              sliver: SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Pratinjau label',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 8),
                    InventoryLabelPreview(
                      key: const Key('inventory-label-preview'),
                      item: selectedItems.first,
                      size: size,
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Ukuran ${size.label} · kertas A4 · skala cetak 100%',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          if (page.items.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: _EmptyState(),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 112),
              sliver: SliverList.separated(
                itemCount: page.items.length,
                separatorBuilder: (context, index) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final item = page.items[index];
                  final checked = _selected.contains(item.id);
                  return _ChoiceCard(
                    key: Key('inventory-label-item-${item.id}'),
                    item: item,
                    checked: checked,
                    onChanged: (value) => _toggle(page, item.id, value),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }

  void _synchronize(InventoryLabelPage page) {
    final fingerprint =
        '${page.filters.type}|${page.filters.receiptId}|'
        '${page.filters.acquisitionYear}|${page.filters.categoryId}|'
        '${page.filters.goodsId}|${page.filters.locationId}|'
        '${page.items.map((item) => item.id).join(',')}';
    if (_pageFingerprint == fingerprint) return;
    _pageFingerprint = fingerprint;
    _selected = page.items
        .take(page.rules.maximumSelection)
        .map((item) => item.id)
        .toSet();
    if (!page.sizes.any((size) => size.value == _sizeValue)) {
      _sizeValue = page.sizes.isEmpty ? 'sedang' : page.sizes.first.value;
    }
    _copies = _copies.clamp(1, page.rules.maximumCopies);
  }

  InventoryLabelSize _selectedSize(InventoryLabelPage page) {
    for (final size in page.sizes) {
      if (size.value == _sizeValue) return size;
    }
    return const InventoryLabelSize(
      value: 'sedang',
      label: '65 x 35 mm',
      widthMm: 65,
      heightMm: 35,
    );
  }

  void _selectAll(InventoryLabelPage page) {
    setState(() {
      _selected = page.items
          .take(page.rules.maximumSelection)
          .map((item) => item.id)
          .toSet();
    });
    if (page.items.length > page.rules.maximumSelection) {
      _showMessage(
        'Maksimal ${page.rules.maximumSelection} item dapat dicetak sekaligus.',
      );
    }
  }

  void _toggle(InventoryLabelPage page, int id, bool value) {
    if (value &&
        !_selected.contains(id) &&
        _selected.length >= page.rules.maximumSelection) {
      _showMessage(
        'Maksimal ${page.rules.maximumSelection} item dapat dipilih.',
      );
      return;
    }
    setState(() {
      if (value) {
        _selected.add(id);
      } else {
        _selected.remove(id);
      }
    });
  }

  Future<void> _openFilters(InventoryLabelPage page) async {
    final filters = await showModalBottomSheet<InventoryLabelFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _FilterSheet(page: page),
    );
    if (filters == null || !mounted) return;
    await ref.read(inventoryLabelControllerProvider.notifier).apply(filters);
  }

  Future<void> _generate(InventoryLabelPage page, {required bool share}) async {
    final items = page.items
        .where((item) => _selected.contains(item.id))
        .toList(growable: false);
    setState(() => _processing = true);
    try {
      final service = ref.read(inventoryLabelDocumentServiceProvider);
      final success = share
          ? await service.shareLabels(
              items: items,
              size: _selectedSize(page),
              rules: page.rules,
              copies: _copies,
            )
          : await service.printLabels(
              items: items,
              size: _selectedSize(page),
              rules: page.rules,
              copies: _copies,
            );
      if (mounted && success) {
        _showMessage(
          share
              ? 'PDF label siap dibagikan.'
              : 'Dokumen label dikirim ke layanan cetak.',
        );
      }
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _IntroCard extends StatelessWidget {
  const _IntroCard({required this.rules});

  final InventoryLabelPrintRules rules;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Icon(
            Icons.document_scanner_outlined,
            color: NusaColors.accent,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Label siap cetak',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                'Code 128 · ${rules.paperFormat} · margin '
                '${_cleanNumber(rules.marginMm)} mm · skala 100%',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.8),
                  fontSize: 10.5,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _ConfigurationCard extends StatelessWidget {
  const _ConfigurationCard({
    required this.page,
    required this.sizeValue,
    required this.copies,
    required this.onTypeChanged,
    required this.onOpenFilters,
    required this.onSizeChanged,
    required this.onCopiesChanged,
  });

  final InventoryLabelPage page;
  final String sizeValue;
  final int copies;
  final ValueChanged<String?> onTypeChanged;
  final VoidCallback onOpenFilters;
  final ValueChanged<String?> onSizeChanged;
  final ValueChanged<int> onCopiesChanged;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Pengaturan label',
            style: TextStyle(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          NusaDropdownField<String>(
            fieldKey: const Key('inventory-label-type'),
            value: page.filters.type,
            options: page.types
                .map(
                  (item) =>
                      NusaDropdownOption(value: item.value, label: item.label),
                )
                .toList(growable: false),
            decoration: const InputDecoration(
              labelText: 'Jenis label',
              prefixIcon: Icon(Icons.category_outlined),
            ),
            onChanged: onTypeChanged,
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              key: const Key('inventory-label-filter'),
              onPressed: onOpenFilters,
              icon: Badge(
                isLabelVisible: page.filters.activeCount > 0,
                label: Text('${page.filters.activeCount}'),
                child: const Icon(Icons.tune_rounded),
              ),
              label: const Text('Saring sumber label'),
            ),
          ),
          const SizedBox(height: 10),
          LayoutBuilder(
            builder: (context, constraints) {
              final sizeField = NusaDropdownField<String>(
                fieldKey: const Key('inventory-label-size'),
                value: sizeValue,
                options: page.sizes
                    .map(
                      (item) => NusaDropdownOption(
                        value: item.value,
                        label: item.label,
                      ),
                    )
                    .toList(growable: false),
                decoration: const InputDecoration(
                  labelText: 'Ukuran label',
                  prefixIcon: Icon(Icons.aspect_ratio_outlined),
                ),
                onChanged: onSizeChanged,
              );
              final copyField = _CopyControl(
                value: copies,
                maximum: page.rules.maximumCopies,
                onChanged: onCopiesChanged,
              );
              if (constraints.maxWidth < 390) {
                return Column(
                  children: [sizeField, const SizedBox(height: 10), copyField],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: sizeField),
                  const SizedBox(width: 10),
                  Expanded(child: copyField),
                ],
              );
            },
          ),
        ],
      ),
    ),
  );
}

class _CopyControl extends StatelessWidget {
  const _CopyControl({
    required this.value,
    required this.maximum,
    required this.onChanged,
  });

  final int value;
  final int maximum;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) => InputDecorator(
    decoration: const InputDecoration(
      labelText: 'Salinan',
      prefixIcon: Icon(Icons.copy_all_outlined),
    ),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        InkResponse(
          key: const Key('inventory-label-copy-minus'),
          onTap: value <= 1 ? null : () => onChanged(value - 1),
          child: Icon(
            Icons.remove_circle_outline,
            color: value <= 1 ? NusaColors.outline : NusaColors.primary,
          ),
        ),
        Text('$value', style: const TextStyle(fontWeight: FontWeight.w800)),
        InkResponse(
          key: const Key('inventory-label-copy-plus'),
          onTap: value >= maximum ? null : () => onChanged(value + 1),
          child: Icon(
            Icons.add_circle_outline,
            color: value >= maximum ? NusaColors.outline : NusaColors.primary,
          ),
        ),
      ],
    ),
  );
}

class _SelectionHeader extends StatelessWidget {
  const _SelectionHeader({
    required this.selected,
    required this.total,
    required this.maximum,
    required this.onAll,
    required this.onNone,
  });

  final int selected;
  final int total;
  final int maximum;
  final VoidCallback? onAll;
  final VoidCallback? onNone;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Pilih yang dicetak',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
            ),
            Text(
              '$selected dari $total dipilih · maksimal $maximum',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10.5,
              ),
            ),
          ],
        ),
      ),
      TextButton(onPressed: onNone, child: const Text('Pilih nol')),
      TextButton(onPressed: onAll, child: const Text('Semua')),
    ],
  );
}

class _ChoiceCard extends StatelessWidget {
  const _ChoiceCard({
    required this.item,
    required this.checked,
    required this.onChanged,
    super.key,
  });

  final InventoryLabelItem item;
  final bool checked;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: InkWell(
      onTap: () => onChanged(!checked),
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Checkbox(
              value: checked,
              onChanged: (value) => onChanged(value ?? false),
            ),
            const SizedBox(width: 4),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.isAsset ? item.goodsCode ?? item.name : item.name,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    item.summary,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    item.isAsset ? 'ID NUSA ${item.code}' : item.code,
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.barcode_reader,
              color: checked ? NusaColors.primary : NusaColors.outline,
            ),
          ],
        ),
      ),
    ),
  );
}

class _PrintBar extends StatelessWidget {
  const _PrintBar({
    required this.selected,
    required this.copies,
    required this.processing,
    required this.onShare,
    required this.onPrint,
  });

  final int selected;
  final int copies;
  final bool processing;
  final VoidCallback? onShare;
  final VoidCallback? onPrint;

  @override
  Widget build(BuildContext context) => SafeArea(
    top: false,
    child: Material(
      elevation: 12,
      color: Colors.white,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                key: const Key('share-inventory-label'),
                onPressed: onShare,
                icon: const Icon(Icons.share_outlined),
                label: const Text('Bagikan PDF'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: FilledButton.icon(
                key: const Key('print-inventory-label'),
                onPressed: onPrint,
                icon: processing
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.print_outlined),
                label: Text('$selected × $copies label'),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.page});

  final InventoryLabelPage page;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late final TextEditingController _yearController;
  late int _receiptId;
  late int _categoryId;
  late int _goodsId;
  late int _locationId;

  @override
  void initState() {
    super.initState();
    final filters = widget.page.filters;
    _receiptId = filters.receiptId ?? 0;
    _categoryId = filters.categoryId ?? 0;
    _goodsId = filters.goodsId ?? 0;
    _locationId = filters.locationId ?? 0;
    _yearController = TextEditingController(
      text: filters.acquisitionYear?.toString(),
    );
  }

  @override
  void dispose() {
    _yearController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    height: (MediaQuery.sizeOf(context).height * 0.88).clamp(520.0, 760.0),
    child: Column(
      children: [
        const SizedBox(height: 10),
        Container(
          width: 42,
          height: 4,
          decoration: BoxDecoration(
            color: NusaColors.outline,
            borderRadius: BorderRadius.circular(4),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 13, 8, 9),
          child: Row(
            children: [
              const Expanded(
                child: Text(
                  'Saring Sumber Label',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                ),
              ),
              IconButton(
                tooltip: 'Tutup',
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: ListView(
            key: const Key('inventory-label-filter-scroll'),
            padding: const EdgeInsets.all(16),
            children: [
              NusaDropdownField<int>(
                fieldKey: const Key('inventory-label-filter-receipt'),
                value: _receiptId,
                options: [
                  const NusaDropdownOption(value: 0, label: 'Semua transaksi'),
                  ...widget.page.receipts.map(
                    (item) =>
                        NusaDropdownOption(value: item.id, label: item.label),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Transaksi barang datang',
                  prefixIcon: Icon(Icons.local_shipping_outlined),
                ),
                onChanged: (value) {
                  if (value != null) {
                    setState(() {
                      _receiptId = value;
                      _goodsId = 0;
                    });
                  }
                },
              ),
              if (widget.page.filters.type == 'unit') ...[
                const SizedBox(height: 12),
                TextField(
                  key: const Key('inventory-label-filter-year'),
                  controller: _yearController,
                  keyboardType: TextInputType.number,
                  maxLength: 4,
                  decoration: const InputDecoration(
                    labelText: 'Tahun perolehan',
                    hintText: 'Semua tahun',
                    prefixIcon: Icon(Icons.date_range_outlined),
                  ),
                ),
              ],
              const SizedBox(height: 12),
              NusaDropdownField<int>(
                fieldKey: const Key('inventory-label-filter-category'),
                value: _categoryId,
                options: [
                  const NusaDropdownOption(value: 0, label: 'Semua kategori'),
                  ...widget.page.categories.map(
                    (item) =>
                        NusaDropdownOption(value: item.id, label: item.name),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Kategori',
                  prefixIcon: Icon(Icons.category_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setState(() => _categoryId = value);
                },
              ),
              const SizedBox(height: 12),
              NusaDropdownField<int>(
                fieldKey: const Key('inventory-label-filter-goods'),
                value: _goodsId,
                options: [
                  const NusaDropdownOption(value: 0, label: 'Semua barang'),
                  ...widget.page.goods.map(
                    (item) => NusaDropdownOption(
                      value: item.id,
                      label: item.displayLabel,
                    ),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Barang',
                  prefixIcon: Icon(Icons.inventory_2_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setState(() => _goodsId = value);
                },
              ),
              const SizedBox(height: 12),
              NusaDropdownField<int>(
                fieldKey: const Key('inventory-label-filter-location'),
                value: _locationId,
                options: [
                  const NusaDropdownOption(value: 0, label: 'Semua lokasi'),
                  ...widget.page.locations.map(
                    (item) =>
                        NusaDropdownOption(value: item.id, label: item.name),
                  ),
                ],
                decoration: const InputDecoration(
                  labelText: 'Lokasi',
                  prefixIcon: Icon(Icons.location_on_outlined),
                ),
                onChanged: (value) {
                  if (value != null) setState(() => _locationId = value);
                },
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.pop(
                    context,
                    InventoryLabelFilters(type: widget.page.filters.type),
                  ),
                  child: const Text('Reset'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton.icon(
                  key: const Key('apply-inventory-label-filter'),
                  onPressed: _apply,
                  icon: const Icon(Icons.check_rounded),
                  label: const Text('Tampilkan'),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  void _apply() {
    final yearText = _yearController.text.trim();
    final year = yearText.isEmpty ? null : int.tryParse(yearText);
    if (yearText.isNotEmpty && (year == null || year < 1900 || year > 2100)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tahun harus berada antara 1900–2100.')),
      );
      return;
    }
    Navigator.pop(
      context,
      InventoryLabelFilters(
        type: widget.page.filters.type,
        receiptId: _receiptId == 0 ? null : _receiptId,
        acquisitionYear: widget.page.filters.type == 'unit' ? year : null,
        categoryId: _categoryId == 0 ? null : _categoryId,
        goodsId: _goodsId == 0 ? null : _goodsId,
        locationId: _locationId == 0 ? null : _locationId,
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.all(36),
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.barcode_reader, size: 52, color: NusaColors.primary),
        SizedBox(height: 12),
        Text(
          'Tidak ada item yang sesuai dengan filter.',
          textAlign: TextAlign.center,
          style: TextStyle(color: NusaColors.textSecondary),
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
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 48),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
        ],
      ),
    ),
  );
}

String _cleanNumber(double value) => value == value.roundToDouble()
    ? value.round().toString()
    : value.toStringAsFixed(1);

String _errorMessage(Object error) => error is AppException
    ? error.message
    : error is ArgumentError
    ? error.message?.toString() ?? 'Dokumen label belum dapat dibuat.'
    : 'Label inventaris belum dapat diproses.';
