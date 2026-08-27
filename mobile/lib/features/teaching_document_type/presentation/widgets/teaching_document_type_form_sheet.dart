import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document_type/domain/teaching_document_type.dart';

class TeachingDocumentTypeFormSheet extends StatefulWidget {
  const TeachingDocumentTypeFormSheet({
    required this.nextOrder,
    this.existing,
    super.key,
  });

  final int nextOrder;
  final TeachingDocumentType? existing;

  @override
  State<TeachingDocumentTypeFormSheet> createState() =>
      _TeachingDocumentTypeFormSheetState();
}

class _TeachingDocumentTypeFormSheetState
    extends State<TeachingDocumentTypeFormSheet> {
  late final TextEditingController _nameController;
  late final TextEditingController _codeController;
  late final TextEditingController _descriptionController;
  late final TextEditingController _orderController;
  late bool _mandatory;
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
    _orderController = TextEditingController(
      text: '${existing?.order ?? widget.nextOrder}',
    );
    _mandatory = existing?.mandatory ?? true;
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _codeController.dispose();
    _descriptionController.dispose();
    _orderController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.82).clamp(500.0, 760.0),
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
                        ? 'Ubah Jenis Perangkat Ajar'
                        : 'Tambah Jenis Perangkat Ajar',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-teaching-document-type-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('teaching-document-type-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  key: const Key('teaching-document-type-form-name'),
                  controller: _nameController,
                  maxLength: 120,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Nama jenis',
                    hintText: 'Contoh: Modul Ajar',
                    prefixIcon: Icon(Icons.description_outlined),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('teaching-document-type-form-code'),
                  controller: _codeController,
                  maxLength: 40,
                  textCapitalization: TextCapitalization.characters,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp('[a-zA-Z0-9 _-]')),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Kode',
                    hintText: 'Contoh: MODUL_AJAR',
                    prefixIcon: Icon(Icons.qr_code_2_rounded),
                    helperText:
                        'Spasi dan tanda hubung otomatis menjadi garis bawah.',
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('teaching-document-type-form-description'),
                  controller: _descriptionController,
                  minLines: 2,
                  maxLines: 4,
                  maxLength: 2000,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Deskripsi (opsional)',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('teaching-document-type-form-order'),
                  controller: _orderController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Urutan tampil',
                    prefixIcon: Icon(Icons.format_list_numbered_rounded),
                  ),
                ),
                const SizedBox(height: 6),
                SwitchListTile.adaptive(
                  key: const Key('teaching-document-type-form-mandatory'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Wajib diunggah'),
                  subtitle: const Text(
                    'Guru akan melihat jenis ini sebagai dokumen wajib.',
                  ),
                  value: _mandatory,
                  onChanged: (value) => setState(() => _mandatory = value),
                ),
                SwitchListTile.adaptive(
                  key: const Key('teaching-document-type-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Jenis aktif'),
                  subtitle: const Text(
                    'Hanya jenis aktif yang tersedia pada formulir unggah.',
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
                key: const Key('save-teaching-document-type'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Jenis'),
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
    final order = int.tryParse(_orderController.text.trim());
    if (name.isEmpty) {
      setState(() => _error = 'Nama jenis perangkat ajar wajib diisi.');
      return;
    }
    if (code.isEmpty) {
      setState(() => _error = 'Kode jenis perangkat ajar wajib diisi.');
      return;
    }
    if (order == null || order < 0 || order > 999) {
      setState(() => _error = 'Urutan harus berupa angka 0 sampai 999.');
      return;
    }

    Navigator.pop(
      context,
      TeachingDocumentTypeFormValue(
        code: code,
        name: name,
        description: _descriptionController.text.trim(),
        mandatory: _mandatory,
        order: order,
        active: _active,
      ),
    );
  }
}
