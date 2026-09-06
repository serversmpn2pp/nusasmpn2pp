import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_receipt/domain/goods_receipt.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsReceiptFormSheet extends StatefulWidget {
  const GoodsReceiptFormSheet({
    required this.sources,
    required this.goods,
    required this.locations,
    required this.acquisitionMethods,
    required this.conditions,
    super.key,
  });

  final List<GoodsReceiptOption> sources;
  final List<GoodsReceiptGoods> goods;
  final List<GoodsReceiptOption> locations;
  final List<GoodsReceiptValueOption> acquisitionMethods;
  final List<GoodsReceiptValueOption> conditions;

  @override
  State<GoodsReceiptFormSheet> createState() => _GoodsReceiptFormSheetState();
}

class _GoodsReceiptFormSheetState extends State<GoodsReceiptFormSheet> {
  final _documentController = TextEditingController();
  final _originController = TextEditingController();
  final _notesController = TextEditingController();
  final List<GoodsReceiptLineValue> _lines = [];
  late final String _storageToken;
  late DateTime _date;
  late int _sourceId;
  late String _method;
  String? _error;

  @override
  void initState() {
    super.initState();
    _storageToken = _uuidV4();
    final now = DateTime.now();
    _date = DateTime(now.year, now.month, now.day);
    _sourceId =
        widget.sources.where((item) => item.active).firstOrNull?.id ?? 0;
    _method = widget.acquisitionMethods.firstOrNull?.value ?? 'pembelian';
  }

  @override
  void dispose() {
    _documentController.dispose();
    _originController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.95).clamp(580.0, 900.0),
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
                    'Catat Barang Datang',
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                  ),
                ),
                IconButton(
                  key: const Key('close-goods-receipt-form'),
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
              key: const Key('goods-receipt-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                const _GuideCard(),
                const SizedBox(height: 14),
                _DateField(value: _date, onTap: _selectDate),
                const SizedBox(height: 12),
                NusaDropdownField<int>(
                  fieldKey: const Key('goods-receipt-form-source'),
                  value: _sourceId,
                  options: widget.sources
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.id,
                          label: item.label,
                          enabled: item.active,
                        ),
                      )
                      .toList(growable: false),
                  decoration: const InputDecoration(
                    labelText: 'Sumber perolehan',
                    prefixIcon: Icon(Icons.account_balance_outlined),
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _sourceId = value);
                  },
                ),
                const SizedBox(height: 12),
                NusaDropdownField<String>(
                  fieldKey: const Key('goods-receipt-form-method'),
                  value: _method,
                  options: widget.acquisitionMethods
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.value,
                          label: item.label,
                        ),
                      )
                      .toList(growable: false),
                  decoration: const InputDecoration(
                    labelText: 'Cara perolehan',
                    prefixIcon: Icon(Icons.handshake_outlined),
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _method = value);
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('goods-receipt-form-document'),
                  controller: _documentController,
                  maxLength: 120,
                  decoration: const InputDecoration(
                    labelText: 'Nomor dokumen (opsional)',
                    hintText: 'BAST, faktur, atau surat hibah',
                    prefixIcon: Icon(Icons.description_outlined),
                  ),
                ),
                const SizedBox(height: 4),
                TextField(
                  key: const Key('goods-receipt-form-origin'),
                  controller: _originController,
                  maxLength: 160,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Asal / penyedia (opsional)',
                    hintText: 'Contoh: CV Maju Bersama',
                    prefixIcon: Icon(Icons.local_shipping_outlined),
                  ),
                ),
                const SizedBox(height: 4),
                TextField(
                  key: const Key('goods-receipt-form-notes'),
                  controller: _notesController,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Catatan umum (opsional)',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Rincian Barang',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    TextButton.icon(
                      key: const Key('add-goods-receipt-line'),
                      onPressed: _lines.length >= 50 ? null : _addLine,
                      icon: const Icon(Icons.add_rounded),
                      label: const Text('Tambah'),
                    ),
                  ],
                ),
                if (_lines.isEmpty)
                  const _EmptyLines()
                else
                  for (var index = 0; index < _lines.length; index++) ...[
                    _LineCard(
                      index: index,
                      value: _lines[index],
                      goods: _findGoods(_lines[index].goodsId),
                      location: _findLocation(_lines[index].locationId),
                      onEdit: () => _editLine(index),
                      onDelete: () => setState(() => _lines.removeAt(index)),
                    ),
                    if (index < _lines.length - 1) const SizedBox(height: 8),
                  ],
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
                const SizedBox(height: 10),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-goods-receipt'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(
                  _lines.isEmpty
                      ? 'Simpan Penerimaan'
                      : 'Simpan ${_lines.length} Rincian',
                ),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _selectDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2000),
      lastDate: DateTime.now(),
      helpText: 'Tanggal penerimaan',
    );
    if (selected != null && mounted) setState(() => _date = selected);
  }

  Future<void> _addLine() async {
    final value = await showModalBottomSheet<GoodsReceiptLineValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _LineFormSheet(
        goods: widget.goods,
        locations: widget.locations,
        conditions: widget.conditions,
      ),
    );
    if (value != null && mounted) setState(() => _lines.add(value));
  }

  Future<void> _editLine(int index) async {
    final value = await showModalBottomSheet<GoodsReceiptLineValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _LineFormSheet(
        goods: widget.goods,
        locations: widget.locations,
        conditions: widget.conditions,
        existing: _lines[index],
      ),
    );
    if (value != null && mounted) setState(() => _lines[index] = value);
  }

  void _submit() {
    if (_sourceId == 0) return _setError('Sumber perolehan wajib dipilih.');
    if (_lines.isEmpty) {
      return _setError('Tambahkan minimal satu barang yang diterima.');
    }
    Navigator.pop(
      context,
      GoodsReceiptFormValue(
        storageToken: _storageToken,
        date: _date,
        sourceId: _sourceId,
        acquisitionMethod: _method,
        documentNumber: _documentController.text,
        origin: _originController.text,
        notes: _notesController.text,
        lines: List.unmodifiable(_lines),
      ),
    );
  }

  GoodsReceiptGoods _findGoods(int id) =>
      widget.goods.firstWhere((item) => item.id == id);
  GoodsReceiptOption _findLocation(int id) =>
      widget.locations.firstWhere((item) => item.id == id);
  void _setError(String message) => setState(() => _error = message);
}

class _LineFormSheet extends StatefulWidget {
  const _LineFormSheet({
    required this.goods,
    required this.locations,
    required this.conditions,
    this.existing,
  });

  final List<GoodsReceiptGoods> goods;
  final List<GoodsReceiptOption> locations;
  final List<GoodsReceiptValueOption> conditions;
  final GoodsReceiptLineValue? existing;

  @override
  State<_LineFormSheet> createState() => _LineFormSheetState();
}

class _LineFormSheetState extends State<_LineFormSheet> {
  final _quantityController = TextEditingController();
  final _priceController = TextEditingController();
  final _brandController = TextEditingController();
  final _modelController = TextEditingController();
  final _notesController = TextEditingController();
  late int _goodsId;
  late int _locationId;
  late String _condition;
  String? _error;

  GoodsReceiptGoods? get _selectedGoods =>
      widget.goods.where((item) => item.id == _goodsId).firstOrNull;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _goodsId = existing?.goodsId ?? widget.goods.firstOrNull?.id ?? 0;
    _locationId = existing?.locationId ?? widget.locations.firstOrNull?.id ?? 0;
    _condition =
        existing?.condition ?? widget.conditions.firstOrNull?.value ?? 'baik';
    _quantityController.text = existing == null
        ? '1'
        : _number(existing.quantity);
    _priceController.text = existing?.unitPrice == null
        ? ''
        : _number(existing!.unitPrice!);
    _brandController.text = existing?.brand ?? '';
    _modelController.text = existing?.model ?? '';
    _notesController.text = existing?.notes ?? '';
  }

  @override
  void dispose() {
    _quantityController.dispose();
    _priceController.dispose();
    _brandController.dispose();
    _modelController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isAsset = _selectedGoods?.isAsset ?? false;
    return AnimatedPadding(
      duration: const Duration(milliseconds: 160),
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: (MediaQuery.sizeOf(context).height * 0.88).clamp(520.0, 780.0),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 8, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      widget.existing == null
                          ? 'Tambah Rincian'
                          : 'Ubah Rincian',
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  NusaDropdownField<int>(
                    fieldKey: const Key('goods-receipt-line-goods'),
                    value: _goodsId,
                    options: widget.goods
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.id,
                            label: '${item.name} · ${item.code}',
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
                  if (_selectedGoods != null) ...[
                    const SizedBox(height: 7),
                    Text(
                      '${_selectedGoods!.typeLabel} · satuan ${_selectedGoods!.unit}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  NusaDropdownField<int>(
                    fieldKey: const Key('goods-receipt-line-location'),
                    value: _locationId,
                    options: widget.locations
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.id,
                            label: item.label,
                          ),
                        )
                        .toList(growable: false),
                    decoration: const InputDecoration(
                      labelText: 'Lokasi penyimpanan',
                      prefixIcon: Icon(Icons.location_on_outlined),
                    ),
                    onChanged: (value) {
                      if (value != null) setState(() => _locationId = value);
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('goods-receipt-line-quantity'),
                    controller: _quantityController,
                    keyboardType: TextInputType.numberWithOptions(
                      decimal: !isAsset,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(
                        isAsset ? RegExp(r'[0-9]') : RegExp(r'[0-9,.]'),
                      ),
                    ],
                    decoration: InputDecoration(
                      labelText: 'Jumlah (${_selectedGoods?.unit ?? 'unit'})',
                      prefixIcon: const Icon(Icons.numbers_rounded),
                      helperText: isAsset
                          ? 'Harus unit utuh, maksimal 200 per rincian.'
                          : 'Boleh menggunakan angka desimal.',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('goods-receipt-line-price'),
                    controller: _priceController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9,.]')),
                    ],
                    decoration: const InputDecoration(
                      labelText: 'Harga satuan (opsional)',
                      hintText: 'Contoh: 3500000',
                      prefixIcon: Icon(Icons.payments_outlined),
                    ),
                  ),
                  if (isAsset) ...[
                    const SizedBox(height: 12),
                    TextField(
                      controller: _brandController,
                      maxLength: 120,
                      decoration: const InputDecoration(
                        labelText: 'Merek (opsional)',
                        prefixIcon: Icon(Icons.branding_watermark_outlined),
                      ),
                    ),
                    const SizedBox(height: 4),
                    TextField(
                      controller: _modelController,
                      maxLength: 120,
                      decoration: const InputDecoration(
                        labelText: 'Tipe / model (opsional)',
                        prefixIcon: Icon(Icons.memory_outlined),
                      ),
                    ),
                    const SizedBox(height: 4),
                    NusaDropdownField<String>(
                      fieldKey: const Key('goods-receipt-line-condition'),
                      value: _condition,
                      options: widget.conditions
                          .map(
                            (item) => NusaDropdownOption(
                              value: item.value,
                              label: item.label,
                            ),
                          )
                          .toList(growable: false),
                      decoration: const InputDecoration(
                        labelText: 'Kondisi awal',
                        prefixIcon: Icon(Icons.fact_check_outlined),
                      ),
                      onChanged: (value) {
                        if (value != null) setState(() => _condition = value);
                      },
                    ),
                  ],
                  const SizedBox(height: 12),
                  TextField(
                    controller: _notesController,
                    minLines: 2,
                    maxLines: 4,
                    maxLength: 1000,
                    decoration: const InputDecoration(
                      labelText: 'Keterangan rincian (opsional)',
                      alignLabelWithHint: true,
                      prefixIcon: Icon(Icons.notes_rounded),
                    ),
                  ),
                  if (_error != null)
                    Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontSize: 12,
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  key: const Key('save-goods-receipt-line'),
                  onPressed: _submit,
                  icon: const Icon(Icons.check_rounded),
                  label: const Text('Gunakan Rincian'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _submit() {
    final goods = _selectedGoods;
    final quantity = _decimal(_quantityController.text);
    final priceText = _priceController.text.trim();
    final price = priceText.isEmpty ? null : _decimal(priceText);
    if (goods == null) return _setError('Barang wajib dipilih.');
    if (_locationId == 0) return _setError('Lokasi wajib dipilih.');
    if (quantity == null || quantity <= 0) {
      return _setError('Jumlah harus berupa angka lebih dari nol.');
    }
    if (goods.isAsset && quantity != quantity.roundToDouble()) {
      return _setError('Jumlah aset harus berupa unit utuh tanpa desimal.');
    }
    if (goods.isAsset && quantity > 200) {
      return _setError('Maksimal 200 unit aset dalam satu rincian.');
    }
    if (priceText.isNotEmpty && (price == null || price < 0)) {
      return _setError('Harga satuan harus berupa angka nol atau lebih.');
    }
    Navigator.pop(
      context,
      GoodsReceiptLineValue(
        goodsId: goods.id,
        locationId: _locationId,
        quantity: quantity,
        unitPrice: price,
        brand: goods.isAsset ? _brandController.text : null,
        model: goods.isAsset ? _modelController.text : null,
        condition: goods.isAsset ? _condition : null,
        notes: _notesController.text,
      ),
    );
  }

  void _setError(String message) => setState(() => _error = message);
}

class _DateField extends StatelessWidget {
  const _DateField({required this.value, required this.onTap});

  final DateTime value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    key: const Key('goods-receipt-form-date'),
    onTap: onTap,
    borderRadius: BorderRadius.circular(14),
    child: InputDecorator(
      decoration: const InputDecoration(
        labelText: 'Tanggal penerimaan',
        prefixIcon: Icon(Icons.event_outlined),
        suffixIcon: Icon(Icons.calendar_month_rounded),
      ),
      child: Text(_dateLabel(value)),
    ),
  );
}

class _GuideCard extends StatelessWidget {
  const _GuideCard();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.auto_awesome_rounded, color: NusaColors.primary),
        SizedBox(width: 10),
        Expanded(
          child: Text(
            'NUSA otomatis menambah saldo barang habis pakai dan membuat identitas setiap Unit Aset.',
            style: TextStyle(fontSize: 11.5, height: 1.4),
          ),
        ),
      ],
    ),
  );
}

class _EmptyLines extends StatelessWidget {
  const _EmptyLines();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: NusaColors.outline),
    ),
    child: const Column(
      children: [
        Icon(Icons.move_to_inbox_outlined, color: NusaColors.textSecondary),
        SizedBox(height: 7),
        Text('Belum ada rincian barang.', style: TextStyle(fontSize: 12)),
      ],
    ),
  );
}

class _LineCard extends StatelessWidget {
  const _LineCard({
    required this.index,
    required this.value,
    required this.goods,
    required this.location,
    required this.onEdit,
    required this.onDelete,
  });

  final int index;
  final GoodsReceiptLineValue value;
  final GoodsReceiptGoods goods;
  final GoodsReceiptOption location;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      onTap: onEdit,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(13, 11, 6, 11),
        child: Row(
          children: [
            CircleAvatar(
              radius: 19,
              backgroundColor: NusaColors.surfaceBlue,
              child: Text(
                '${index + 1}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    goods.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${_number(value.quantity)} ${goods.unit} · ${location.name}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'Hapus rincian',
              onPressed: onDelete,
              icon: const Icon(Icons.delete_outline_rounded, size: 20),
            ),
          ],
        ),
      ),
    ),
  );
}

double? _decimal(String value) =>
    double.tryParse(value.trim().replaceAll(',', '.'));

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
  return '${value.day.toString().padLeft(2, '0')} ${months[value.month - 1]} ${value.year}';
}

String _uuidV4() {
  final random = Random.secure();
  final bytes = List<int>.generate(16, (_) => random.nextInt(256));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  final hex = bytes
      .map((item) => item.toRadixString(16).padLeft(2, '0'))
      .join();
  return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
      '${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
}
