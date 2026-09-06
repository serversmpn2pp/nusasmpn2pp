import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/stock/domain/stock.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StockMovementFormSheet extends StatefulWidget {
  const StockMovementFormSheet({
    required this.page,
    this.initialGoodsId,
    this.initialLocationId,
    super.key,
  });

  final StockMovementPage page;
  final int? initialGoodsId;
  final int? initialLocationId;

  @override
  State<StockMovementFormSheet> createState() => _StockMovementFormSheetState();
}

class _StockMovementFormSheetState extends State<StockMovementFormSheet> {
  final _quantity = TextEditingController();
  final _reference = TextEditingController();
  final _notes = TextEditingController();
  late final List<StockGoods> _goods;
  late final List<StockOption> _locations;
  late int _goodsId;
  late int _locationId;
  late String _type;
  late String _category;
  late DateTime _date;
  String? _error;

  @override
  void initState() {
    super.initState();
    _goods = widget.page.goods.where((item) => item.active).toList();
    _locations = widget.page.locations.where((item) => item.active).toList();
    _goodsId = _goods.any((item) => item.id == widget.initialGoodsId)
        ? widget.initialGoodsId!
        : (_goods.firstOrNull?.id ?? 0);
    _locationId = _locations.any((item) => item.id == widget.initialLocationId)
        ? widget.initialLocationId!
        : (_locations.firstOrNull?.id ?? 0);
    _type = widget.page.typeOptions.firstOrNull?.value ?? 'masuk';
    _category = _categories.firstOrNull?.value ?? '';
    final now = DateTime.now();
    _date = DateTime(now.year, now.month, now.day);
  }

  @override
  void dispose() {
    _quantity.dispose();
    _reference.dispose();
    _notes.dispose();
    super.dispose();
  }

  List<StockValueOption> get _categories {
    final allowed = widget.page.categoriesByType[_type] ?? const [];
    return widget.page.categoryOptions
        .where((item) => allowed.contains(item.value))
        .toList(growable: false);
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.93).clamp(560.0, 860.0),
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
            padding: const EdgeInsets.fromLTRB(16, 12, 8, 8),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Catat Mutasi Stok',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                  ),
                ),
                IconButton(
                  key: const Key('close-stock-movement-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('stock-movement-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                const _GuideCard(),
                const SizedBox(height: 14),
                if (_goods.isEmpty || _locations.isEmpty)
                  const _UnavailableCard()
                else ...[
                  NusaDropdownField<int>(
                    fieldKey: const Key('stock-movement-form-goods'),
                    value: _goodsId,
                    options: _goods
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.id,
                            label: item.label,
                          ),
                        )
                        .toList(growable: false),
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
                    fieldKey: const Key('stock-movement-form-location'),
                    value: _locationId,
                    options: _locations
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.id,
                            label: item.label,
                          ),
                        )
                        .toList(growable: false),
                    decoration: const InputDecoration(
                      labelText: 'Lokasi stok',
                      prefixIcon: Icon(Icons.place_outlined),
                    ),
                    onChanged: (value) {
                      if (value != null) setState(() => _locationId = value);
                    },
                  ),
                  const SizedBox(height: 12),
                  NusaDropdownField<String>(
                    fieldKey: const Key('stock-movement-form-type'),
                    value: _type,
                    options: widget.page.typeOptions
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.value,
                            label: item.label,
                          ),
                        )
                        .toList(growable: false),
                    decoration: const InputDecoration(
                      labelText: 'Jenis mutasi',
                      prefixIcon: Icon(Icons.swap_horiz_rounded),
                    ),
                    onChanged: (value) {
                      if (value == null) return;
                      setState(() {
                        _type = value;
                        _category = _categories.firstOrNull?.value ?? '';
                        _error = null;
                      });
                    },
                  ),
                  const SizedBox(height: 12),
                  NusaDropdownField<String>(
                    fieldKey: const Key('stock-movement-form-category'),
                    value: _category,
                    options: _categories
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.value,
                            label: item.label,
                          ),
                        )
                        .toList(growable: false),
                    decoration: const InputDecoration(
                      labelText: 'Kategori mutasi',
                      prefixIcon: Icon(Icons.category_outlined),
                    ),
                    onChanged: (value) {
                      if (value != null) setState(() => _category = value);
                    },
                  ),
                  const SizedBox(height: 12),
                  _DateField(value: _date, onTap: _selectDate),
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('stock-movement-form-quantity'),
                    controller: _quantity,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(
                        RegExp(r'^\d{0,12}([.,]\d{0,2})?'),
                      ),
                    ],
                    decoration: InputDecoration(
                      labelText: _type == 'penyesuaian'
                          ? 'Saldo fisik terbaru'
                          : 'Jumlah',
                      prefixIcon: const Icon(Icons.numbers_rounded),
                      helperText: _type == 'penyesuaian'
                          ? 'Nilai ini menjadi saldo akhir, bukan selisih.'
                          : null,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('stock-movement-form-reference'),
                    controller: _reference,
                    maxLength: 120,
                    decoration: const InputDecoration(
                      labelText: 'Referensi (opsional)',
                      hintText: 'Nomor dokumen atau sumber transaksi',
                      prefixIcon: Icon(Icons.receipt_long_outlined),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('stock-movement-form-notes'),
                    controller: _notes,
                    minLines: 2,
                    maxLines: 4,
                    decoration: const InputDecoration(
                      labelText: 'Keterangan (opsional)',
                      prefixIcon: Icon(Icons.notes_rounded),
                      alignLabelWithHint: true,
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontSize: 12,
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  FilledButton.icon(
                    key: const Key('save-stock-movement'),
                    onPressed: _submit,
                    icon: const Icon(Icons.save_outlined),
                    label: const Text('Simpan Mutasi'),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _selectDate() async {
    final value = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (value != null) setState(() => _date = value);
  }

  void _submit() {
    final parsed = double.tryParse(_quantity.text.replaceAll(',', '.'));
    if (parsed == null ||
        parsed < 0 ||
        (_type != 'penyesuaian' && parsed == 0)) {
      setState(
        () => _error = _type == 'penyesuaian'
            ? 'Saldo fisik harus berupa angka nol atau lebih.'
            : 'Jumlah harus lebih besar dari nol.',
      );
      return;
    }
    if (_goodsId == 0 || _locationId == 0 || _category.isEmpty) {
      setState(
        () => _error = 'Barang, lokasi, jenis, dan kategori wajib dipilih.',
      );
      return;
    }
    Navigator.pop(
      context,
      StockMovementFormValue(
        goodsId: _goodsId,
        locationId: _locationId,
        type: _type,
        category: _category,
        date: _date,
        quantity: parsed,
        reference: _reference.text,
        notes: _notes.text,
      ),
    );
  }
}

class _GuideCard extends StatelessWidget {
  const _GuideCard();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.info_outline_rounded, color: NusaColors.primary, size: 21),
        SizedBox(width: 10),
        Expanded(
          child: Text(
            'Mutasi tersimpan sebagai jejak audit dan tidak diubah atau dihapus. Jika ada kesalahan, catat penyesuaian baru berdasarkan hasil cek fisik.',
            style: TextStyle(fontSize: 12, height: 1.4),
          ),
        ),
      ],
    ),
  );
}

class _UnavailableCard extends StatelessWidget {
  const _UnavailableCard();

  @override
  Widget build(BuildContext context) => const Card(
    child: Padding(
      padding: EdgeInsets.all(18),
      child: Text(
        'Belum ada barang berbasis stok atau lokasi aktif. Lengkapi data induk terlebih dahulu.',
        textAlign: TextAlign.center,
      ),
    ),
  );
}

class _DateField extends StatelessWidget {
  const _DateField({required this.value, required this.onTap});
  final DateTime value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    key: const Key('stock-movement-form-date'),
    onTap: onTap,
    borderRadius: BorderRadius.circular(14),
    child: InputDecorator(
      decoration: const InputDecoration(
        labelText: 'Tanggal mutasi',
        prefixIcon: Icon(Icons.calendar_today_outlined),
        suffixIcon: Icon(Icons.edit_calendar_outlined),
      ),
      child: Text(_dateLabel(value)),
    ),
  );
}

String _dateLabel(DateTime value) {
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${value.day.toString().padLeft(2, '0')} ${months[value.month - 1]} ${value.year}';
}
