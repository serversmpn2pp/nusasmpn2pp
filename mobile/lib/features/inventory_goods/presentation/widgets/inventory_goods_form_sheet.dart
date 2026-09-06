import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_goods/domain/inventory_goods.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class InventoryGoodsFormSheet extends StatefulWidget {
  const InventoryGoodsFormSheet({
    required this.types,
    required this.categories,
    required this.units,
    required this.locations,
    this.existing,
    super.key,
  });

  final List<InventoryGoodsType> types;
  final List<InventoryGoodsOption> categories;
  final List<InventoryGoodsOption> units;
  final List<InventoryGoodsOption> locations;
  final InventoryGoods? existing;

  @override
  State<InventoryGoodsFormSheet> createState() =>
      _InventoryGoodsFormSheetState();
}

class _InventoryGoodsFormSheetState extends State<InventoryGoodsFormSheet> {
  late final TextEditingController _nameController;
  late final TextEditingController _codeController;
  late final TextEditingController _minimumController;
  late final TextEditingController _descriptionController;
  late final List<InventoryGoodsOption> _categories;
  late final List<InventoryGoodsOption> _units;
  late final List<InventoryGoodsOption> _locations;
  late String _type;
  late int _categoryId;
  late int _unitId;
  late int _locationId;
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;
  bool get _consumable => _type == 'habis_pakai';

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _nameController = TextEditingController(text: existing?.name);
    _codeController = TextEditingController(text: existing?.code);
    _minimumController = TextEditingController(
      text: existing == null ? '0' : _number(existing.minimumStock),
    );
    _descriptionController = TextEditingController(text: existing?.description);
    _type = existing?.type ?? 'tidak_habis_pakai';
    _categories = _withExisting(widget.categories, existing?.category);
    _units = _withExisting(widget.units, existing?.unit);
    _locations = _withExisting(widget.locations, existing?.location);
    _categoryId =
        existing?.category.id ??
        (_categories.isNotEmpty ? _categories.first.id : 0);
    _unitId = existing?.unit.id ?? (_units.isNotEmpty ? _units.first.id : 0);
    _locationId = existing?.location?.id ?? 0;
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _codeController.dispose();
    _minimumController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.94).clamp(560.0, 850.0),
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
                Expanded(
                  child: Text(
                    _editing
                        ? 'Ubah Inventaris Barang'
                        : 'Tambah Inventaris Barang',
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-inventory-goods-form'),
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
              key: const Key('inventory-goods-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                _GuideCard(consumable: _consumable),
                const SizedBox(height: 14),
                TextField(
                  key: const Key('inventory-goods-form-name'),
                  controller: _nameController,
                  maxLength: 150,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Nama barang',
                    hintText: 'Contoh: Laptop Chromebook',
                    prefixIcon: Icon(Icons.inventory_2_outlined),
                    helperText:
                        'Gunakan nama umum tanpa merek atau nomor unit.',
                  ),
                ),
                const SizedBox(height: 8),
                NusaDropdownField<String>(
                  fieldKey: const Key('inventory-goods-form-type'),
                  value: _type,
                  options: widget.types
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.value,
                          label: item.label,
                        ),
                      )
                      .toList(growable: false),
                  decoration: const InputDecoration(
                    labelText: 'Jenis barang',
                    prefixIcon: Icon(Icons.category_outlined),
                  ),
                  enabled: widget.existing?.typeCanChange ?? true,
                  onChanged: (value) {
                    if (value != null) setState(() => _type = value);
                  },
                ),
                if (_editing && widget.existing?.typeCanChange == false)
                  const Padding(
                    padding: EdgeInsets.only(top: 5),
                    child: Text(
                      'Jenis dikunci karena barang sudah memiliki unit, stok, atau riwayat transaksi.',
                      style: TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('inventory-goods-form-code'),
                  controller: _codeController,
                  readOnly: _consumable,
                  keyboardType: TextInputType.number,
                  maxLength: _consumable ? null : 14,
                  inputFormatters: _consumable
                      ? null
                      : [const _AssetCodeFormatter()],
                  decoration: InputDecoration(
                    labelText: 'Kode barang',
                    hintText: _consumable
                        ? 'Dibuat otomatis oleh NUSA'
                        : 'Contoh: 02.06.01.05.40',
                    prefixIcon: const Icon(Icons.qr_code_2_rounded),
                    helperText: _consumable
                        ? 'NUSA membuat kode berurutan seperti BHP-000001.'
                        : 'Ketik sepuluh angka; titik ditambahkan otomatis.',
                  ),
                ),
                const SizedBox(height: 8),
                NusaDropdownField<int>(
                  fieldKey: const Key('inventory-goods-form-category'),
                  value: _categoryId,
                  options: _categories
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.id,
                          label: item.label,
                        ),
                      )
                      .toList(growable: false),
                  decoration: const InputDecoration(
                    labelText: 'Kategori',
                    prefixIcon: Icon(Icons.sell_outlined),
                    hintText: 'Pilih kategori',
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _categoryId = value);
                  },
                ),
                const SizedBox(height: 12),
                NusaDropdownField<int>(
                  fieldKey: const Key('inventory-goods-form-unit'),
                  value: _unitId,
                  options: _units
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.id,
                          label: item.label,
                        ),
                      )
                      .toList(growable: false),
                  decoration: const InputDecoration(
                    labelText: 'Satuan',
                    prefixIcon: Icon(Icons.straighten_rounded),
                    hintText: 'Pilih satuan',
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _unitId = value);
                  },
                ),
                const SizedBox(height: 12),
                NusaDropdownField<int>(
                  fieldKey: const Key('inventory-goods-form-location'),
                  value: _locationId,
                  options: [
                    const NusaDropdownOption(
                      value: 0,
                      label: 'Belum ditentukan',
                    ),
                    ..._locations.map(
                      (item) =>
                          NusaDropdownOption(value: item.id, label: item.label),
                    ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Lokasi penyimpanan awal',
                    prefixIcon: Icon(Icons.location_on_outlined),
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _locationId = value);
                  },
                ),
                if (_consumable) ...[
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('inventory-goods-form-minimum-stock'),
                    controller: _minimumController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9,.]')),
                    ],
                    decoration: const InputDecoration(
                      labelText: 'Stok minimum',
                      hintText: 'Contoh: 10',
                      prefixIcon: Icon(Icons.low_priority_rounded),
                      helperText:
                          'Menjadi batas pengingat ketika stok menipis.',
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                TextField(
                  key: const Key('inventory-goods-form-description'),
                  controller: _descriptionController,
                  minLines: 3,
                  maxLines: 5,
                  maxLength: 5000,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Deskripsi (opsional)',
                    hintText: 'Spesifikasi atau keterangan penting barang.',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                SwitchListTile.adaptive(
                  key: const Key('inventory-goods-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Barang aktif'),
                  subtitle: const Text(
                    'Barang aktif tersedia pada transaksi inventaris.',
                  ),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-inventory-goods'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Barang'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    final name = _nameController.text.trim();
    final digits = _codeController.text.replaceAll(RegExp(r'\D'), '');
    final minimum = double.tryParse(
      _minimumController.text.trim().replaceAll(',', '.'),
    );
    if (name.isEmpty) return _setError('Nama barang wajib diisi.');
    if (!_consumable && digits.length != 10) {
      return _setError(
        'Kode barang harus terdiri dari lima kelompok dua angka.',
      );
    }
    if (_categoryId == 0) return _setError('Kategori barang wajib dipilih.');
    if (_unitId == 0) return _setError('Satuan barang wajib dipilih.');
    if (_consumable && (minimum == null || minimum < 0)) {
      return _setError('Stok minimum harus berupa angka nol atau lebih.');
    }

    Navigator.pop(
      context,
      InventoryGoodsFormValue(
        name: name,
        code: _consumable ? null : _codeController.text.trim(),
        categoryId: _categoryId,
        unitId: _unitId,
        locationId: _locationId == 0 ? null : _locationId,
        type: _type,
        minimumStock: _consumable ? minimum! : 0,
        description: _descriptionController.text.trim(),
        active: _active,
      ),
    );
  }

  void _setError(String message) => setState(() => _error = message);
}

class _GuideCard extends StatelessWidget {
  const _GuideCard({required this.consumable});

  final bool consumable;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          consumable ? Icons.layers_outlined : Icons.qr_code_scanner_rounded,
          color: NusaColors.primary,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            consumable
                ? 'Saldo dicatat per lokasi dan berkurang ketika digunakan.'
                : 'Setiap barang datang akan dibuatkan unit aset dan barcode tersendiri.',
            style: const TextStyle(fontSize: 11.5, height: 1.4),
          ),
        ),
      ],
    ),
  );
}

class _AssetCodeFormatter extends TextInputFormatter {
  const _AssetCodeFormatter();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final rawDigits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final digits = rawDigits.length > 10
        ? rawDigits.substring(0, 10)
        : rawDigits;
    final groups = <String>[];
    for (var index = 0; index < digits.length; index += 2) {
      final end = index + 2 < digits.length ? index + 2 : digits.length;
      groups.add(digits.substring(index, end));
    }
    final formatted = groups.join('.');
    return TextEditingValue(
      text: formatted,
      selection: TextSelection.collapsed(offset: formatted.length),
    );
  }
}

List<InventoryGoodsOption> _withExisting(
  List<InventoryGoodsOption> items,
  InventoryGoodsOption? existing,
) {
  if (existing == null || items.any((item) => item.id == existing.id)) {
    return [...items];
  }
  return [...items, existing];
}

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');
