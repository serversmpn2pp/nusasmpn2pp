import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_violation_type/domain/student_violation_type.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentViolationTypeFormSheet extends StatefulWidget {
  const StudentViolationTypeFormSheet({
    required this.references,
    this.existing,
    super.key,
  });

  final StudentViolationTypeReferences references;
  final StudentViolationType? existing;

  @override
  State<StudentViolationTypeFormSheet> createState() =>
      _StudentViolationTypeFormSheetState();
}

class _StudentViolationTypeFormSheetState
    extends State<StudentViolationTypeFormSheet> {
  late final TextEditingController _codeController;
  late final TextEditingController _nameController;
  late final TextEditingController _pointsController;
  late final TextEditingController _orderController;
  late String _level;
  late int _categoryId;
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _codeController = TextEditingController(text: existing?.code);
    _nameController = TextEditingController(text: existing?.name);
    _pointsController = TextEditingController(
      text: existing == null ? '' : '${existing.points}',
    );
    _orderController = TextEditingController(
      text: existing == null ? '0' : '${existing.order}',
    );
    _level =
        existing?.level ??
        (widget.references.levels.isEmpty
            ? 'ringan'
            : widget.references.levels.first.code);
    _categoryId = existing?.category?.id ?? 0;
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _codeController.dispose();
    _nameController.dispose();
    _pointsController.dispose();
    _orderController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.88).clamp(560.0, 790.0),
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
                        ? 'Ubah Jenis Pelanggaran'
                        : 'Tambah Jenis Pelanggaran',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-violation-type-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('violation-type-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  key: const Key('violation-type-form-code'),
                  controller: _codeController,
                  maxLength: 20,
                  textCapitalization: TextCapitalization.characters,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp('[a-zA-Z0-9 _-]')),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Kode pelanggaran',
                    hintText: 'Contoh: R025',
                    prefixIcon: Icon(Icons.qr_code_2_rounded),
                    helperText: 'Kode akan dirapikan menjadi huruf besar dan garis bawah.',
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  key: const Key('violation-type-form-name'),
                  controller: _nameController,
                  minLines: 3,
                  maxLines: 5,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Jenis pelanggaran',
                    hintText: 'Tuliskan perilaku yang termasuk pelanggaran.',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.gavel_rounded),
                  ),
                ),
                const SizedBox(height: 12),
                NusaDropdownField<int>(
                  fieldKey: const Key('violation-type-form-category'),
                  value: _categoryId,
                  options: [
                    const NusaDropdownOption(value: 0, label: 'Tanpa kategori'),
                    for (final category in widget.references.categories)
                      NusaDropdownOption(
                        value: category.id,
                        label:
                            '${category.name}${category.active ? '' : ' (Nonaktif)'}',
                      ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Kategori pembinaan',
                    prefixIcon: Icon(Icons.category_outlined),
                  ),
                  onChanged: (value) {
                    if (value != null) setState(() => _categoryId = value);
                  },
                ),
                const SizedBox(height: 12),
                LayoutBuilder(
                  builder: (context, constraints) {
                    final level = NusaDropdownField<String>(
                      fieldKey: const Key('violation-type-form-level'),
                      value: _level,
                      options: [
                        for (final item in widget.references.levels)
                          NusaDropdownOption(
                            value: item.code,
                            label: item.label,
                          ),
                      ],
                      decoration: const InputDecoration(
                        labelText: 'Tingkat',
                        prefixIcon: Icon(Icons.signal_cellular_alt_rounded),
                      ),
                      onChanged: (value) {
                        if (value != null) setState(() => _level = value);
                      },
                    );
                    final points = TextField(
                      key: const Key('violation-type-form-points'),
                      controller: _pointsController,
                      keyboardType: TextInputType.number,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      decoration: const InputDecoration(
                        labelText: 'Poin',
                        prefixIcon: Icon(Icons.stars_rounded),
                      ),
                    );
                    if (constraints.maxWidth < 340) {
                      return Column(
                        children: [level, const SizedBox(height: 12), points],
                      );
                    }
                    return Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(child: level),
                        const SizedBox(width: 10),
                        Expanded(child: points),
                      ],
                    );
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('violation-type-form-order'),
                  controller: _orderController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Urutan tampil',
                    prefixIcon: Icon(Icons.format_list_numbered_rounded),
                    helperText: 'Nilai lebih kecil tampil lebih dahulu.',
                  ),
                ),
                SwitchListTile.adaptive(
                  key: const Key('violation-type-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Jenis pelanggaran aktif'),
                  subtitle: const Text(
                    'Hanya data aktif yang dapat dipilih pada laporan baru.',
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
                key: const Key('save-violation-type'),
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
    final code = _codeController.text.trim();
    final name = _nameController.text.trim();
    final points = int.tryParse(_pointsController.text);
    final order = int.tryParse(_orderController.text) ?? 0;
    if (code.isEmpty) {
      setState(() => _error = 'Kode pelanggaran wajib diisi.');
      return;
    }
    if (name.isEmpty) {
      setState(() => _error = 'Jenis pelanggaran wajib diisi.');
      return;
    }
    if (points == null || points < 1 || points > 1000) {
      setState(() => _error = 'Poin harus berupa angka antara 1 sampai 1000.');
      return;
    }
    if (order < 0 || order > 9999) {
      setState(() => _error = 'Urutan harus berada antara 0 sampai 9999.');
      return;
    }

    Navigator.pop(
      context,
      StudentViolationTypeFormValue(
        code: code,
        name: name,
        level: _level,
        points: points,
        order: order,
        active: _active,
        categoryId: _categoryId == 0 ? null : _categoryId,
      ),
    );
  }
}
