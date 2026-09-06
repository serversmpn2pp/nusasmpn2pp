import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_category/domain/inventory_category.dart';

class InventoryCategoryFormSheet extends StatefulWidget {
  const InventoryCategoryFormSheet({this.existing, super.key});

  final InventoryCategory? existing;

  @override
  State<InventoryCategoryFormSheet> createState() =>
      _InventoryCategoryFormSheetState();
}

class _InventoryCategoryFormSheetState
    extends State<InventoryCategoryFormSheet> {
  late final TextEditingController _nameController;
  late final TextEditingController _codeController;
  late final TextEditingController _descriptionController;
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
    _active = existing?.active ?? true;
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
      height: (MediaQuery.sizeOf(context).height * 0.76).clamp(470.0, 700.0),
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
                    _editing
                        ? 'Ubah Kategori Barang'
                        : 'Tambah Kategori Barang',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-inventory-category-form'),
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
              key: const Key('inventory-category-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  key: const Key('inventory-category-form-name'),
                  controller: _nameController,
                  maxLength: 120,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Nama kategori',
                    hintText: 'Contoh: Elektronik',
                    prefixIcon: Icon(Icons.category_outlined),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('inventory-category-form-code'),
                  controller: _codeController,
                  maxLength: 40,
                  textCapitalization: TextCapitalization.characters,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp('[a-zA-Z0-9 _-]')),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Kode kategori',
                    hintText: 'Contoh: ELEKTRONIK',
                    prefixIcon: Icon(Icons.qr_code_2_rounded),
                    helperText:
                        'Spasi dan tanda hubung otomatis menjadi garis bawah.',
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('inventory-category-form-description'),
                  controller: _descriptionController,
                  minLines: 3,
                  maxLines: 5,
                  maxLength: 2000,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Deskripsi (opsional)',
                    hintText: 'Jelaskan kelompok barang dalam kategori ini.',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                const SizedBox(height: 4),
                SwitchListTile.adaptive(
                  key: const Key('inventory-category-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Kategori aktif'),
                  subtitle: const Text(
                    'Kategori aktif dapat digunakan pada data barang.',
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
                key: const Key('save-inventory-category'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Kategori'),
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
      setState(() => _error = 'Nama kategori barang wajib diisi.');
      return;
    }
    if (code.isEmpty) {
      setState(() => _error = 'Kode kategori barang wajib diisi.');
      return;
    }

    Navigator.pop(
      context,
      InventoryCategoryFormValue(
        name: name,
        code: code,
        description: _descriptionController.text.trim(),
        active: _active,
      ),
    );
  }
}
