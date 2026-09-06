import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsReturnFormSheet extends StatefulWidget {
  const GoodsReturnFormSheet({
    required this.detail,
    this.initialDetailId,
    super.key,
  });
  final GoodsLoanDetailResponse detail;
  final int? initialDetailId;

  @override
  State<GoodsReturnFormSheet> createState() => _GoodsReturnFormSheetState();
}

class _GoodsReturnFormSheetState extends State<GoodsReturnFormSheet> {
  final _notes = TextEditingController();
  final Map<int, TextEditingController> _quantities = {};
  final Map<int, String> _conditions = {};
  final Set<int> _selected = {};
  late DateTime _date;
  String? _error;

  List<GoodsLoanItem> get _outstanding => widget.detail.loan.items
      .where((item) => item.mustReturn && item.remaining > 0)
      .toList(growable: false);

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _date = DateTime(now.year, now.month, now.day);
    for (final item in _outstanding) {
      _quantities[item.id] = TextEditingController(
        text: item.assetUnitId != null ? '1' : _number(item.remaining),
      );
      _conditions[item.id] =
          widget.detail.conditions.firstOrNull?.value ?? 'baik';
    }
    if (widget.initialDetailId != null &&
        _outstanding.any((item) => item.id == widget.initialDetailId)) {
      _selected.add(widget.initialDetailId!);
    }
  }

  @override
  void dispose() {
    _notes.dispose();
    for (final item in _quantities.values) {
      item.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * .94).clamp(560.0, 880.0),
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
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Catat Pengembalian',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        widget.detail.loan.number,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  key: const Key('close-goods-return-form'),
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
              key: const Key('goods-return-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  padding: const EdgeInsets.all(13),
                  decoration: BoxDecoration(
                    color: NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.person_outline_rounded,
                        color: NusaColors.primary,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.detail.loan.borrowerName,
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text(
                              widget.detail.loan.borrowerIdentity,
                              style: const TextStyle(
                                fontSize: 11,
                                color: NusaColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                _DateField(value: _date, onTap: _selectDate),
                const SizedBox(height: 16),
                const Text(
                  'Pilih barang yang kembali',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 9),
                if (_outstanding.isEmpty)
                  const Text('Semua barang wajib kembali sudah diselesaikan.')
                else
                  ..._outstanding.map(_itemCard),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('goods-return-form-notes'),
                  controller: _notes,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Catatan pengembalian (opsional)',
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
                  key: const Key('save-goods-return'),
                  onPressed: _outstanding.isEmpty ? null : _submit,
                  icon: const Icon(Icons.assignment_return_rounded),
                  label: const Text('Simpan Pengembalian'),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );

  Widget _itemCard(GoodsLoanItem item) {
    final selected = _selected.contains(item.id);
    final asset = item.assetUnitId != null;
    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(11),
          child: Column(
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Checkbox(
                    key: Key('goods-return-select-${item.id}'),
                    value: selected,
                    onChanged: (value) => setState(() {
                      if (value == true) {
                        _selected.add(item.id);
                      } else {
                        _selected.remove(item.id);
                      }
                    }),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.goodsName,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          Text(
                            '${item.code} · ${item.location}\nSisa ${_number(item.remaining)} ${item.unit}',
                            style: const TextStyle(
                              fontSize: 11,
                              color: NusaColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              if (selected) ...[
                const SizedBox(height: 8),
                TextField(
                  key: Key('goods-return-quantity-${item.id}'),
                  controller: _quantities[item.id],
                  enabled: !asset,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(
                      RegExp(r'^\d{0,12}([.,]\d{0,2})?'),
                    ),
                  ],
                  decoration: InputDecoration(
                    labelText: 'Jumlah kembali (${item.unit})',
                    prefixIcon: const Icon(Icons.numbers_rounded),
                  ),
                ),
                if (asset) ...[
                  const SizedBox(height: 10),
                  NusaDropdownField<String>(
                    fieldKey: Key('goods-return-condition-${item.id}'),
                    value: _conditions[item.id],
                    options: widget.detail.conditions
                        .map(
                          (option) => NusaDropdownOption(
                            value: option.value,
                            label: option.label,
                          ),
                        )
                        .toList(),
                    decoration: const InputDecoration(
                      labelText: 'Kondisi saat kembali',
                      prefixIcon: Icon(Icons.health_and_safety_outlined),
                    ),
                    onChanged: (value) {
                      if (value != null) {
                        setState(() => _conditions[item.id] = value);
                      }
                    },
                  ),
                ],
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _selectDate() async {
    final result = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: widget.detail.loan.date ?? DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (result != null) setState(() => _date = result);
  }

  void _submit() {
    if (_selected.isEmpty) {
      setState(() => _error = 'Pilih minimal satu barang yang dikembalikan.');
      return;
    }
    final lines = <GoodsReturnLineValue>[];
    for (final item in _outstanding.where(
      (item) => _selected.contains(item.id),
    )) {
      final quantity = double.tryParse(
        (_quantities[item.id]?.text ?? '').replaceAll(',', '.'),
      );
      if (quantity == null ||
          quantity <= 0 ||
          quantity > item.remaining ||
          (item.assetUnitId != null && quantity != 1)) {
        setState(
          () => _error =
              'Jumlah pengembalian ${item.goodsName} tidak valid atau melebihi sisa.',
        );
        return;
      }
      final condition = item.assetUnitId == null ? null : _conditions[item.id];
      if (item.assetUnitId != null && condition == null) {
        setState(() => _error = 'Kondisi ${item.goodsName} wajib dipilih.');
        return;
      }
      lines.add(
        GoodsReturnLineValue(
          detailId: item.id,
          quantity: quantity,
          condition: condition,
          inputMethod: item.id == widget.initialDetailId ? 'scan' : 'manual',
        ),
      );
    }
    Navigator.pop(
      context,
      GoodsReturnFormValue(date: _date, notes: _notes.text, lines: lines),
    );
  }
}

class _DateField extends StatelessWidget {
  const _DateField({required this.value, required this.onTap});
  final DateTime value;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
    key: const Key('goods-return-form-date'),
    onTap: onTap,
    borderRadius: BorderRadius.circular(14),
    child: InputDecorator(
      decoration: const InputDecoration(
        labelText: 'Tanggal pengembalian',
        prefixIcon: Icon(Icons.calendar_today_outlined),
        suffixIcon: Icon(Icons.edit_calendar_outlined),
      ),
      child: Text(_date(value)),
    ),
  );
}

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');
String _date(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
