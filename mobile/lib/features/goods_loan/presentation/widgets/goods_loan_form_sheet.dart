import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';
import 'package:nusa/features/goods_loan/presentation/widgets/inventory_barcode_scanner_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsLoanFormSheet extends StatefulWidget {
  const GoodsLoanFormSheet({
    required this.page,
    required this.onIdentifyBorrower,
    required this.onIdentifyItem,
    super.key,
  });
  final GoodsLoanPage page;
  final Future<IdentifiedBorrower> Function(String code) onIdentifyBorrower;
  final Future<GoodsLoanAvailableItem> Function(String code) onIdentifyItem;

  @override
  State<GoodsLoanFormSheet> createState() => _GoodsLoanFormSheetState();
}

class _GoodsLoanFormSheetState extends State<GoodsLoanFormSheet> {
  final _quantity = TextEditingController(text: '1');
  final _notes = TextEditingController();
  final List<GoodsLoanLineValue> _lines = [];
  late String _borrowerType;
  int _borrowerId = 0;
  String _borrowerInput = 'manual';
  String _itemKey = '';
  late DateTime _date;
  DateTime? _plannedReturn;
  bool _processingScan = false;
  String? _error;

  List<GoodsLoanPersonOption> get _borrowers =>
      _borrowerType == 'siswa' ? widget.page.students : widget.page.employees;

  @override
  void initState() {
    super.initState();
    _borrowerType = widget.page.borrowerTypes.firstOrNull?.value ?? 'siswa';
    _borrowerId = _borrowers.firstOrNull?.id ?? 0;
    _itemKey = widget.page.availableItems.firstOrNull?.keyValue ?? '';
    final now = DateTime.now();
    _date = DateTime(now.year, now.month, now.day);
  }

  @override
  void dispose() {
    _quantity.dispose();
    _notes.dispose();
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
                    'Catat Peminjaman',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                  ),
                ),
                IconButton(
                  key: const Key('close-goods-loan-form'),
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
              key: const Key('goods-loan-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                const _StepTitle(number: '1', title: 'Pilih peminjam'),
                const SizedBox(height: 10),
                NusaDropdownField<String>(
                  fieldKey: const Key('goods-loan-form-borrower-type'),
                  value: _borrowerType,
                  options: widget.page.borrowerTypes
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.value,
                          label: item.label,
                        ),
                      )
                      .toList(),
                  decoration: const InputDecoration(
                    labelText: 'Jenis peminjam',
                    prefixIcon: Icon(Icons.person_outline_rounded),
                  ),
                  onChanged: (value) {
                    if (value == null) return;
                    setState(() {
                      _borrowerType = value;
                      _borrowerId = _borrowers.firstOrNull?.id ?? 0;
                      _borrowerInput = 'manual';
                    });
                  },
                ),
                const SizedBox(height: 12),
                NusaDropdownField<int>(
                  fieldKey: const Key('goods-loan-form-borrower'),
                  value: _borrowerId,
                  options: _borrowers
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.id,
                          label: item.label,
                        ),
                      )
                      .toList(),
                  decoration: const InputDecoration(
                    labelText: 'Peminjam',
                    prefixIcon: Icon(Icons.badge_outlined),
                  ),
                  onChanged: (value) {
                    if (value != null) {
                      setState(() {
                        _borrowerId = value;
                        _borrowerInput = 'manual';
                      });
                    }
                  },
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  key: const Key('scan-goods-loan-borrower'),
                  onPressed: _processingScan ? null : _scanBorrower,
                  icon: const Icon(Icons.qr_code_scanner_rounded),
                  label: Text(
                    _processingScan
                        ? 'Memeriksa kartu...'
                        : 'Scan Kartu Peminjam',
                  ),
                ),
                const SizedBox(height: 22),
                const _StepTitle(number: '2', title: 'Masukkan barang'),
                const SizedBox(height: 10),
                if (widget.page.availableItems.isEmpty)
                  const _HintCard(
                    text: 'Belum ada unit aset atau stok yang tersedia untuk dipinjam.',
                  )
                else ...[
                  NusaDropdownField<String>(
                    fieldKey: const Key('goods-loan-form-item'),
                    value: _itemKey,
                    options: widget.page.availableItems
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.keyValue,
                            label: '${item.label} · ${item.code}',
                          ),
                        )
                        .toList(),
                    decoration: const InputDecoration(
                      labelText: 'Barang tersedia',
                      prefixIcon: Icon(Icons.inventory_2_outlined),
                    ),
                    onChanged: (value) {
                      if (value != null) setState(() => _itemKey = value);
                    },
                  ),
                  const SizedBox(height: 12),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: TextField(
                          key: const Key('goods-loan-form-quantity'),
                          controller: _quantity,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'^\d{0,12}([.,]\d{0,2})?'),
                            ),
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Jumlah',
                            prefixIcon: Icon(Icons.numbers_rounded),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      SizedBox(
                        width: 108,
                        height: 56,
                        child: FilledButton.tonalIcon(
                          key: const Key('add-goods-loan-line'),
                          onPressed: _addManual,
                          icon: const Icon(Icons.add_rounded),
                          label: const Text('Tambah'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    key: const Key('scan-goods-loan-item'),
                    onPressed: _processingScan ? null : _scanItem,
                    icon: const Icon(Icons.qr_code_scanner_rounded),
                    label: const Text('Scan Barcode Barang'),
                  ),
                ],
                const SizedBox(height: 14),
                if (_lines.isEmpty)
                  const _HintCard(
                    text: 'Keranjang masih kosong. Tambahkan minimal satu barang.',
                  )
                else
                  ..._lines.asMap().entries.map(
                    (entry) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: _CartItem(
                        line: entry.value,
                        onRemove: () =>
                            setState(() => _lines.removeAt(entry.key)),
                      ),
                    ),
                  ),
                const SizedBox(height: 16),
                const _StepTitle(number: '3', title: 'Tanggal dan penyimpanan'),
                const SizedBox(height: 10),
                _DateField(
                  label: 'Tanggal peminjaman',
                  value: _date,
                  onTap: () => _selectDate(false),
                ),
                const SizedBox(height: 12),
                _DateField(
                  label: 'Rencana kembali (opsional)',
                  value: _plannedReturn,
                  onTap: () => _selectDate(true),
                  canClear: true,
                  onClear: () => setState(() => _plannedReturn = null),
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('goods-loan-form-notes'),
                  controller: _notes,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Catatan (opsional)',
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
                  key: const Key('save-goods-loan'),
                  onPressed: _submit,
                  icon: const Icon(Icons.save_outlined),
                  label: const Text('Simpan Peminjaman'),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );

  GoodsLoanAvailableItem? get _selectedItem => widget.page.availableItems
      .where((item) => item.keyValue == _itemKey)
      .firstOrNull;

  void _addManual() {
    final item = _selectedItem;
    final quantity = double.tryParse(_quantity.text.replaceAll(',', '.'));
    if (item == null ||
        quantity == null ||
        quantity <= 0 ||
        quantity > item.balance ||
        (item.type == 'unit' && quantity != 1)) {
      setState(
        () => _error = item?.type == 'unit'
            ? 'Jumlah unit aset harus 1.'
            : 'Jumlah harus lebih dari nol dan tidak melebihi stok tersedia.',
      );
      return;
    }
    _addLine(
      GoodsLoanLineValue(item: item, quantity: quantity, inputMethod: 'manual'),
    );
  }

  void _addLine(GoodsLoanLineValue line) {
    final existing = _lines.indexWhere(
      (item) => item.item.keyValue == line.item.keyValue,
    );
    if (existing >= 0) {
      if (line.item.type == 'unit') {
        setState(() => _error = 'Unit aset tersebut sudah ada di keranjang.');
        return;
      }
      final total = _lines[existing].quantity + line.quantity;
      if (total > line.item.balance) {
        setState(() => _error = 'Jumlah gabungan melebihi stok tersedia.');
        return;
      }
      _lines[existing] = GoodsLoanLineValue(
        item: line.item,
        quantity: total,
        inputMethod: _lines[existing].inputMethod == line.inputMethod
            ? line.inputMethod
            : 'campuran',
      );
    } else {
      _lines.add(line);
    }
    setState(() {
      _error = null;
      _quantity.text = '1';
    });
  }

  Future<String?> _scan(String title, String guide) =>
      showModalBottomSheet<String>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        builder: (context) =>
            InventoryBarcodeScannerSheet(title: title, guide: guide),
      );

  Future<void> _scanBorrower() async {
    final code = await _scan(
      'Scan Kartu Peminjam',
      'Arahkan QR kartu siswa atau pegawai ke dalam bingkai.',
    );
    if (code == null || !mounted) return;
    setState(() => _processingScan = true);
    try {
      final result = await widget.onIdentifyBorrower(code);
      if (!mounted) return;
      setState(() {
        _borrowerType = result.type;
        _borrowerId = result.id;
        _borrowerInput = 'scan';
        _error = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${result.name} berhasil dikenali.')),
      );
    } catch (error) {
      if (mounted) setState(() => _error = _message(error));
    } finally {
      if (mounted) setState(() => _processingScan = false);
    }
  }

  Future<void> _scanItem() async {
    final code = await _scan(
      'Scan Barcode Barang',
      'Arahkan barcode AST atau kode barang ke dalam bingkai.',
    );
    if (code == null || !mounted) return;
    setState(() => _processingScan = true);
    try {
      final item = await widget.onIdentifyItem(code);
      if (!mounted) return;
      _addLine(
        GoodsLoanLineValue(item: item, quantity: 1, inputMethod: 'scan'),
      );
    } catch (error) {
      if (mounted) setState(() => _error = _message(error));
    } finally {
      if (mounted) setState(() => _processingScan = false);
    }
  }

  Future<void> _selectDate(bool planned) async {
    final initial = planned ? (_plannedReturn ?? _date) : _date;
    final result = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (result != null) {
      setState(() {
        if (planned) {
          _plannedReturn = result;
        } else {
          _date = result;
        }
      });
    }
  }

  void _submit() {
    if (_borrowerId == 0) {
      setState(() => _error = 'Peminjam wajib dipilih.');
      return;
    }
    if (_lines.isEmpty) {
      setState(() => _error = 'Tambahkan minimal satu barang.');
      return;
    }
    if (_plannedReturn != null && _plannedReturn!.isBefore(_date)) {
      setState(
        () =>
            _error = 'Rencana kembali tidak boleh sebelum tanggal peminjaman.',
      );
      return;
    }
    Navigator.pop(
      context,
      GoodsLoanFormValue(
        borrowerType: _borrowerType,
        borrowerId: _borrowerId,
        borrowerInputMethod: _borrowerInput,
        date: _date,
        plannedReturn: _plannedReturn,
        notes: _notes.text,
        lines: List.unmodifiable(_lines),
      ),
    );
  }
}

class _StepTitle extends StatelessWidget {
  const _StepTitle({required this.number, required this.title});
  final String number;
  final String title;
  @override
  Widget build(BuildContext context) => Row(
    children: [
      CircleAvatar(
        radius: 14,
        backgroundColor: NusaColors.primary,
        foregroundColor: Colors.white,
        child: Text(
          number,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
        ),
      ),
      const SizedBox(width: 9),
      Expanded(
        child: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
      ),
    ],
  );
}

class _HintCard extends StatelessWidget {
  const _HintCard({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Text(
      text,
      textAlign: TextAlign.center,
      style: const TextStyle(fontSize: 12, color: NusaColors.textSecondary),
    ),
  );
}

class _CartItem extends StatelessWidget {
  const _CartItem({required this.line, required this.onRemove});
  final GoodsLoanLineValue line;
  final VoidCallback onRemove;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 5, 10),
      child: Row(
        children: [
          Icon(
            line.item.mustReturn
                ? Icons.assignment_return_outlined
                : Icons.inventory_2_outlined,
            color: line.item.mustReturn
                ? NusaColors.primary
                : const Color(0xFFE38A00),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  line.item.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                Text(
                  '${line.item.code} · ${_number(line.quantity)} ${line.item.unit} · ${line.item.mustReturn ? 'Wajib kembali' : 'Habis pakai'}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 10,
                    color: NusaColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Hapus',
            onPressed: onRemove,
            icon: const Icon(Icons.delete_outline_rounded),
          ),
        ],
      ),
    ),
  );
}

class _DateField extends StatelessWidget {
  const _DateField({
    required this.label,
    required this.value,
    required this.onTap,
    this.canClear = false,
    this.onClear,
  });
  final String label;
  final DateTime? value;
  final VoidCallback onTap;
  final bool canClear;
  final VoidCallback? onClear;
  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(14),
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: const Icon(Icons.calendar_today_outlined),
        suffixIcon: canClear && value != null
            ? IconButton(
                onPressed: onClear,
                icon: const Icon(Icons.close_rounded),
              )
            : const Icon(Icons.edit_calendar_outlined),
      ),
      child: Text(value == null ? 'Belum ditentukan' : _dateLabel(value!)),
    ),
  );
}

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');
String _dateLabel(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
String _message(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final values = error.errors.values.expand((item) => item);
    if (values.isNotEmpty) return values.first;
  }
  if (error is AppException) return error.message;
  if (error is FormatException) return error.message;
  return 'Data belum dapat diproses.';
}
