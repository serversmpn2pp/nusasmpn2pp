import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/asset_unit/domain/asset_unit.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class AssetUnitFormSheet extends StatefulWidget {
  const AssetUnitFormSheet({required this.page, this.existing, super.key});

  final AssetUnitPage page;
  final AssetUnit? existing;

  @override
  State<AssetUnitFormSheet> createState() => _AssetUnitFormSheetState();
}

class _AssetUnitFormSheetState extends State<AssetUnitFormSheet> {
  late final TextEditingController _quantityController;
  late final TextEditingController _serialController;
  late final TextEditingController _brandController;
  late final TextEditingController _modelController;
  late final TextEditingController _yearController;
  late final TextEditingController _priceController;
  late final TextEditingController _notesController;
  late final List<AssetGoods> _goods;
  late final List<AssetOption> _locations;
  late final List<AssetOption> _sources;
  late int _goodsId;
  late int _locationId;
  late int _sourceId;
  late String _condition;
  late String _unitStatus;
  late bool _active;
  DateTime? _acquisitionDate;
  String? _error;

  bool get _editing => widget.existing != null;
  int get _quantity => int.tryParse(_quantityController.text) ?? 1;
  int get _year => int.tryParse(_yearController.text) ?? DateTime.now().year;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _goods = _withGoods(widget.page.goods, existing?.goods);
    _locations = _withOption(widget.page.locations, existing?.location);
    _sources = _withOption(widget.page.sources, existing?.source);
    final activeGoods = _goods.where((item) => item.active).toList();
    _goodsId =
        existing?.goods.id ??
        (activeGoods.isNotEmpty ? activeGoods.first.id : 0);
    _locationId = existing?.location?.id ?? 0;
    _sourceId =
        existing?.source?.id ?? (_sources.isNotEmpty ? _sources.first.id : 0);
    _condition = existing?.condition ?? 'baik';
    _unitStatus = existing?.unitStatus ?? 'tersedia';
    _active = existing?.active ?? true;
    _acquisitionDate = existing?.acquisitionDate;
    _quantityController = TextEditingController(text: '1');
    _serialController = TextEditingController(text: existing?.serialNumber);
    _brandController = TextEditingController(text: existing?.brand);
    _modelController = TextEditingController(text: existing?.model);
    _yearController = TextEditingController(
      text: '${existing?.acquisitionYear ?? DateTime.now().year}',
    );
    _priceController = TextEditingController(
      text: existing?.acquisitionPrice == null
          ? ''
          : _number(existing!.acquisitionPrice!),
    );
    _notesController = TextEditingController(text: existing?.notes);
  }

  @override
  void dispose() {
    _quantityController.dispose();
    _serialController.dispose();
    _brandController.dispose();
    _modelController.dispose();
    _yearController.dispose();
    _priceController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.95).clamp(580.0, 860.0),
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
                    _editing ? 'Ubah Unit Aset' : 'Tambah Unit Aset',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-asset-unit-form'),
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
              key: const Key('asset-unit-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                _IdentityPreview(
                  inventoryCode: widget.existing?.inventoryCode,
                  officialNumber:
                      widget.existing?.officialAssetNumber ??
                      widget.page.assetNumber.preview(_year),
                ),
                const SizedBox(height: 16),
                const _FormSectionTitle(
                  title: 'Identitas aset',
                  subtitle: 'Pilih barang induk dan kondisi unit fisik',
                ),
                const SizedBox(height: 10),
                if (_editing)
                  InputDecorator(
                    decoration: const InputDecoration(
                      labelText: 'Barang',
                      prefixIcon: Icon(Icons.inventory_2_outlined),
                    ),
                    child: Text(widget.existing!.goods.label),
                  )
                else
                  NusaDropdownField<int>(
                    fieldKey: const Key('asset-unit-form-goods'),
                    value: _goodsId,
                    options: _goods
                        .where((item) => item.active)
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.id,
                            label: item.label,
                          ),
                        )
                        .toList(growable: false),
                    decoration: const InputDecoration(
                      labelText: 'Barang aset individual',
                      prefixIcon: Icon(Icons.inventory_2_outlined),
                      hintText: 'Pilih barang',
                    ),
                    onChanged: (value) {
                      if (value != null) setState(() => _goodsId = value);
                    },
                  ),
                if (!_editing) ...[
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('asset-unit-form-quantity'),
                    controller: _quantityController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    decoration: const InputDecoration(
                      labelText: 'Jumlah unit yang dibuat',
                      prefixIcon: Icon(Icons.copy_all_outlined),
                      helperText: 'Maksimal 100 unit dalam sekali simpan.',
                    ),
                    onChanged: (_) => setState(() {}),
                  ),
                ],
                const SizedBox(height: 12),
                NusaDropdownField<int>(
                  fieldKey: const Key('asset-unit-form-location'),
                  value: _locationId,
                  options: [
                    const NusaDropdownOption(
                      value: 0,
                      label: 'Ikuti lokasi awal barang',
                    ),
                    ..._locations.map(
                      (item) => NusaDropdownOption(
                        value: item.id,
                        label: item.label,
                        enabled: item.active || item.id == _locationId,
                      ),
                    ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Lokasi saat ini',
                    prefixIcon: Icon(Icons.location_on_outlined),
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _locationId = value);
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('asset-unit-form-serial'),
                  controller: _serialController,
                  enabled: _editing || _quantity <= 1,
                  maxLength: 120,
                  decoration: InputDecoration(
                    labelText: 'Nomor seri (opsional)',
                    prefixIcon: const Icon(Icons.pin_outlined),
                    helperText: !_editing && _quantity > 1
                        ? 'Isi nomor seri masing-masing unit melalui halaman ubah.'
                        : null,
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('asset-unit-form-brand'),
                  controller: _brandController,
                  maxLength: 120,
                  decoration: const InputDecoration(
                    labelText: 'Merek (opsional)',
                    hintText: 'Contoh: Epson',
                    prefixIcon: Icon(Icons.branding_watermark_outlined),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('asset-unit-form-model'),
                  controller: _modelController,
                  maxLength: 120,
                  decoration: const InputDecoration(
                    labelText: 'Tipe/model (opsional)',
                    hintText: 'Contoh: L3110',
                    prefixIcon: Icon(Icons.devices_other_outlined),
                  ),
                ),
                const SizedBox(height: 8),
                NusaDropdownField<String>(
                  fieldKey: const Key('asset-unit-form-condition'),
                  value: _condition,
                  options: widget.page.conditions
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.value,
                          label: item.label,
                        ),
                      )
                      .toList(growable: false),
                  decoration: const InputDecoration(
                    labelText: 'Kondisi',
                    prefixIcon: Icon(Icons.health_and_safety_outlined),
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _condition = value);
                  },
                ),
                const SizedBox(height: 12),
                NusaDropdownField<String>(
                  fieldKey: const Key('asset-unit-form-status'),
                  value: _unitStatus,
                  options: widget.page.statuses
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.value,
                          label: item.label,
                        ),
                      )
                      .toList(growable: false),
                  decoration: const InputDecoration(
                    labelText: 'Status unit',
                    prefixIcon: Icon(Icons.fact_check_outlined),
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _unitStatus = value);
                  },
                ),
                const SizedBox(height: 20),
                const _FormSectionTitle(
                  title: 'Perolehan dan catatan',
                  subtitle: 'Identitas resmi dibuat dari tahun perolehan',
                ),
                const SizedBox(height: 10),
                InkWell(
                  key: const Key('asset-unit-form-date'),
                  onTap: _pickDate,
                  borderRadius: BorderRadius.circular(14),
                  child: InputDecorator(
                    decoration: const InputDecoration(
                      labelText: 'Tanggal perolehan (opsional)',
                      prefixIcon: Icon(Icons.event_outlined),
                      suffixIcon: Icon(Icons.calendar_month_rounded),
                    ),
                    child: Text(
                      _acquisitionDate == null
                          ? 'Belum ditentukan'
                          : _dateLabel(_acquisitionDate!),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('asset-unit-form-year'),
                  controller: _yearController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [
                    FilteringTextInputFormatter.digitsOnly,
                    LengthLimitingTextInputFormatter(4),
                  ],
                  decoration: InputDecoration(
                    labelText: 'Tahun perolehan',
                    prefixIcon: const Icon(Icons.date_range_outlined),
                    helperText:
                        'Nomor aset: ${widget.page.assetNumber.preview(_year)}',
                  ),
                  onChanged: (_) => setState(() {}),
                ),
                const SizedBox(height: 12),
                NusaDropdownField<int>(
                  fieldKey: const Key('asset-unit-form-source'),
                  value: _sourceId,
                  options: [
                    if (_editing)
                      const NusaDropdownOption(
                        value: 0,
                        label: 'Belum ditentukan',
                      ),
                    ..._sources.map(
                      (item) => NusaDropdownOption(
                        value: item.id,
                        label: item.label,
                        enabled: item.active || item.id == _sourceId,
                      ),
                    ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Sumber perolehan',
                    prefixIcon: Icon(Icons.account_balance_wallet_outlined),
                    hintText: 'Pilih sumber',
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _sourceId = value);
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('asset-unit-form-price'),
                  controller: _priceController,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9,.]')),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Harga perolehan (opsional)',
                    hintText: 'Contoh: 4500000',
                    prefixIcon: Icon(Icons.payments_outlined),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('asset-unit-form-notes'),
                  controller: _notesController,
                  minLines: 3,
                  maxLines: 5,
                  maxLength: 5000,
                  decoration: const InputDecoration(
                    labelText: 'Keterangan (opsional)',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                SwitchListTile.adaptive(
                  key: const Key('asset-unit-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Unit aktif'),
                  subtitle: const Text(
                    'Unit aktif tersedia untuk pengelolaan inventaris.',
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
                key: const Key('save-asset-unit'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Unit Aset'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _pickDate() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _acquisitionDate ?? DateTime(_year),
      firstDate: DateTime(1900),
      lastDate: DateTime(2100, 12, 31),
    );
    if (date != null) {
      setState(() {
        _acquisitionDate = date;
        _yearController.text = '${date.year}';
      });
    }
  }

  void _submit() {
    final quantity = _editing ? 1 : int.tryParse(_quantityController.text);
    final year = int.tryParse(_yearController.text);
    final priceText = _priceController.text.trim().replaceAll(',', '.');
    final price = priceText.isEmpty ? null : double.tryParse(priceText);
    if (_goodsId == 0) {
      return _setError('Barang aset individual wajib dipilih.');
    }
    if (quantity == null || quantity < 1 || quantity > 100) {
      return _setError('Jumlah unit harus berada antara 1 sampai 100.');
    }
    if (quantity > 1 && _serialController.text.trim().isNotEmpty) {
      return _setError('Nomor seri hanya dapat diisi saat membuat satu unit.');
    }
    if (year == null || year < 1900 || year > 2100) {
      return _setError('Tahun perolehan harus berada antara 1900 dan 2100.');
    }
    if (_acquisitionDate != null && _acquisitionDate!.year != year) {
      return _setError('Tahun harus sama dengan tahun pada tanggal perolehan.');
    }
    if (!_editing && _sourceId == 0) {
      return _setError('Sumber perolehan wajib dipilih.');
    }
    if (priceText.isNotEmpty && (price == null || price < 0)) {
      return _setError('Harga perolehan harus berupa angka nol atau lebih.');
    }

    Navigator.pop(
      context,
      AssetUnitFormValue(
        goodsId: _goodsId,
        quantity: quantity,
        locationId: _locationId == 0 ? null : _locationId,
        serialNumber: _serialController.text.trim(),
        brand: _brandController.text.trim(),
        model: _modelController.text.trim(),
        condition: _condition,
        unitStatus: _unitStatus,
        acquisitionDate: _acquisitionDate,
        acquisitionYear: year,
        sourceId: _sourceId == 0 ? null : _sourceId,
        acquisitionPrice: price,
        notes: _notesController.text.trim(),
        active: _active,
      ),
    );
  }

  void _setError(String message) => setState(() => _error = message);
}

class _IdentityPreview extends StatelessWidget {
  const _IdentityPreview({this.inventoryCode, required this.officialNumber});

  final String? inventoryCode;
  final String officialNumber;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        const Icon(Icons.qr_code_2_rounded, color: NusaColors.accent, size: 34),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                inventoryCode ?? 'ID NUSA dibuat otomatis',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                'Nomor aset $officialNumber',
                maxLines: 2,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.75),
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

class _FormSectionTitle extends StatelessWidget {
  const _FormSectionTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
      const SizedBox(height: 2),
      Text(
        subtitle,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

List<AssetGoods> _withGoods(List<AssetGoods> items, AssetGoods? existing) {
  if (existing == null || items.any((item) => item.id == existing.id)) {
    return [...items];
  }
  return [...items, existing];
}

List<AssetOption> _withOption(List<AssetOption> items, AssetOption? existing) {
  if (existing == null || items.any((item) => item.id == existing.id)) {
    return [...items];
  }
  return [...items, existing];
}

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');

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
  return '${value.day} ${months[value.month - 1]} ${value.year}';
}
