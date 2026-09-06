import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_location/domain/inventory_location.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class InventoryLocationFormSheet extends StatefulWidget {
  const InventoryLocationFormSheet({
    required this.types,
    required this.employees,
    this.existing,
    super.key,
  });

  final List<InventoryLocationType> types;
  final List<InventoryLocationEmployee> employees;
  final InventoryLocation? existing;

  @override
  State<InventoryLocationFormSheet> createState() =>
      _InventoryLocationFormSheetState();
}

class _InventoryLocationFormSheetState
    extends State<InventoryLocationFormSheet> {
  late final TextEditingController _nameController;
  late final TextEditingController _codeController;
  late final TextEditingController _descriptionController;
  late final List<InventoryLocationEmployee> _employees;
  late String _type;
  int? _responsibleEmployeeId;
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _nameController = TextEditingController(text: existing?.name);
    _codeController = TextEditingController(text: existing?.code);
    _descriptionController = TextEditingController(text: existing?.description);
    _type =
        existing?.type ??
        (widget.types.isNotEmpty ? widget.types.first.value : 'gudang');
    _responsibleEmployeeId = existing?.responsibleEmployee?.id;
    _active = existing?.active ?? true;
    _employees = [...widget.employees];
    final responsible = existing?.responsibleEmployee;
    if (responsible != null &&
        _employees.every((employee) => employee.id != responsible.id)) {
      _employees.add(responsible);
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _codeController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.9).clamp(540.0, 800.0),
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
            padding: const EdgeInsets.fromLTRB(16, 14, 8, 10),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _editing ? 'Ubah Lokasi Barang' : 'Tambah Lokasi Barang',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-inventory-location-form'),
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
              key: const Key('inventory-location-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  key: const Key('inventory-location-form-name'),
                  controller: _nameController,
                  maxLength: 120,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Nama lokasi',
                    hintText: 'Contoh: Laboratorium Informatika',
                    prefixIcon: Icon(Icons.location_on_outlined),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('inventory-location-form-code'),
                  controller: _codeController,
                  maxLength: 40,
                  textCapitalization: TextCapitalization.characters,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp('[a-zA-Z0-9 _-]')),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Kode lokasi',
                    hintText: 'Contoh: LAB_INF',
                    prefixIcon: Icon(Icons.qr_code_2_rounded),
                    helperText:
                        'Spasi dan tanda hubung otomatis menjadi garis bawah.',
                  ),
                ),
                const SizedBox(height: 8),
                NusaDropdownField<String>(
                  fieldKey: const Key('inventory-location-form-type'),
                  value: _type,
                  options: widget.types
                      .map(
                        (type) => NusaDropdownOption(
                          value: type.value,
                          label: type.label,
                        ),
                      )
                      .toList(growable: false),
                  decoration: const InputDecoration(
                    labelText: 'Jenis lokasi',
                    prefixIcon: Icon(Icons.apartment_rounded),
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _type = value);
                  },
                ),
                const SizedBox(height: 12),
                NusaDropdownField<int?>(
                  fieldKey: const Key(
                    'inventory-location-form-responsible-employee',
                  ),
                  value: _responsibleEmployeeId,
                  options: [
                    const NusaDropdownOption<int?>(
                      value: null,
                      label: 'Belum ditentukan',
                    ),
                    ..._employees.map(
                      (employee) => NusaDropdownOption<int?>(
                        value: employee.id,
                        label: employee.optionLabel,
                      ),
                    ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Penanggung jawab',
                    prefixIcon: Icon(Icons.badge_outlined),
                  ),
                  onChanged: (value) =>
                      setState(() => _responsibleEmployeeId = value),
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('inventory-location-form-description'),
                  controller: _descriptionController,
                  minLines: 3,
                  maxLines: 5,
                  maxLength: 2000,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Deskripsi (opsional)',
                    hintText: 'Tuliskan keterangan lokasi jika diperlukan.',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                const SizedBox(height: 4),
                SwitchListTile.adaptive(
                  key: const Key('inventory-location-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Lokasi aktif'),
                  subtitle: const Text(
                    'Lokasi aktif dapat dipilih sebagai tempat penyimpanan.',
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
                key: const Key('save-inventory-location'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Lokasi'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    final name = _nameController.text.trim();
    final code = _codeController.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Nama lokasi barang wajib diisi.');
      return;
    }
    if (code.isEmpty) {
      setState(() => _error = 'Kode lokasi barang wajib diisi.');
      return;
    }
    if (_type.isEmpty) {
      setState(() => _error = 'Jenis lokasi wajib dipilih.');
      return;
    }

    Navigator.pop(
      context,
      InventoryLocationFormValue(
        name: name,
        code: code,
        type: _type,
        responsibleEmployeeId: _responsibleEmployeeId,
        description: _descriptionController.text.trim(),
        active: _active,
      ),
    );
  }
}
