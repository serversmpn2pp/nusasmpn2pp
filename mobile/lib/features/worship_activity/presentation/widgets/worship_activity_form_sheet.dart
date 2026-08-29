import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_activity/domain/worship_activity.dart';

class WorshipActivityFormSheet extends StatefulWidget {
  const WorshipActivityFormSheet({this.existing, super.key});

  final WorshipActivity? existing;

  @override
  State<WorshipActivityFormSheet> createState() =>
      _WorshipActivityFormSheetState();
}

class _WorshipActivityFormSheetState extends State<WorshipActivityFormSheet> {
  late final TextEditingController _nameController;
  late final TextEditingController _codeController;
  late final TextEditingController _notesController;
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _nameController = TextEditingController(text: existing?.name);
    _codeController = TextEditingController(text: existing?.code);
    _notesController = TextEditingController(text: existing?.notes);
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _codeController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.74).clamp(460.0, 680.0),
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
                        ? 'Ubah Kegiatan Ibadah'
                        : 'Tambah Kegiatan Ibadah',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-worship-activity-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('worship-activity-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  key: const Key('worship-activity-form-name'),
                  controller: _nameController,
                  maxLength: 150,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Nama kegiatan',
                    hintText: 'Contoh: Sholat Duhur Berjamaah',
                    prefixIcon: Icon(Icons.self_improvement_rounded),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('worship-activity-form-code'),
                  controller: _codeController,
                  maxLength: 50,
                  textCapitalization: TextCapitalization.none,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp('[a-zA-Z0-9 _-]')),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Kode',
                    hintText: 'Contoh: sholat_duhur',
                    prefixIcon: Icon(Icons.qr_code_2_rounded),
                    helperText:
                        'Spasi dan tanda hubung otomatis menjadi garis bawah.',
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('worship-activity-form-notes'),
                  controller: _notesController,
                  minLines: 2,
                  maxLines: 4,
                  maxLength: 1000,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Keterangan (opsional)',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                const SizedBox(height: 4),
                SwitchListTile.adaptive(
                  key: const Key('worship-activity-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Kegiatan aktif'),
                  subtitle: const Text(
                    'Kegiatan aktif dapat digunakan pada pengaturan jadwal.',
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
                key: const Key('save-worship-activity'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Kegiatan'),
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
      setState(() => _error = 'Nama kegiatan ibadah wajib diisi.');
      return;
    }
    if (code.isEmpty) {
      setState(() => _error = 'Kode kegiatan ibadah wajib diisi.');
      return;
    }

    Navigator.pop(
      context,
      WorshipActivityFormValue(
        code: code,
        name: name,
        active: _active,
        notes: _notesController.text.trim(),
      ),
    );
  }
}
