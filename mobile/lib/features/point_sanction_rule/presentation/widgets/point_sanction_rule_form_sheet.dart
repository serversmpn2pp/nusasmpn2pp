import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/point_sanction_rule/domain/point_sanction_rule.dart';

class PointSanctionRuleFormSheet extends StatefulWidget {
  const PointSanctionRuleFormSheet({this.existing, super.key});

  final PointSanctionRule? existing;

  @override
  State<PointSanctionRuleFormSheet> createState() =>
      _PointSanctionRuleFormSheetState();
}

class _PointSanctionRuleFormSheetState
    extends State<PointSanctionRuleFormSheet> {
  late final TextEditingController _thresholdController;
  late final TextEditingController _nameController;
  late final TextEditingController _descriptionController;
  late final TextEditingController _orderController;
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _thresholdController = TextEditingController(
      text: existing == null ? '' : '${existing.pointThreshold}',
    );
    _nameController = TextEditingController(text: existing?.name);
    _descriptionController = TextEditingController(text: existing?.description);
    _orderController = TextEditingController(
      text: existing == null ? '0' : '${existing.order}',
    );
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _thresholdController.dispose();
    _nameController.dispose();
    _descriptionController.dispose();
    _orderController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.8).clamp(520.0, 720.0),
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
                    _editing ? 'Ubah Aturan Sanksi' : 'Tambah Aturan Sanksi',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-sanction-rule-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('sanction-rule-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  key: const Key('sanction-rule-form-threshold'),
                  controller: _thresholdController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Ambang poin',
                    hintText: 'Contoh: 50',
                    prefixIcon: Icon(Icons.flag_rounded),
                    suffixText: 'poin',
                    helperText: 'Sanksi terpicu saat total poin pertama kali mencapai ambang.',
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const Key('sanction-rule-form-name'),
                  controller: _nameController,
                  maxLength: 120,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Nama sanksi',
                    hintText: 'Contoh: Pemanggilan Orang Tua',
                    prefixIcon: Icon(Icons.policy_rounded),
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  key: const Key('sanction-rule-form-description'),
                  controller: _descriptionController,
                  minLines: 3,
                  maxLines: 5,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Penjelasan tindakan',
                    hintText: 'Jelaskan tindak lanjut yang harus dilaksanakan.',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('sanction-rule-form-order'),
                  controller: _orderController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Urutan administrasi',
                    prefixIcon: Icon(Icons.format_list_numbered_rounded),
                    helperText:
                        'Daftar utama tetap disusun berdasarkan ambang poin.',
                  ),
                ),
                SwitchListTile.adaptive(
                  key: const Key('sanction-rule-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Aturan aktif'),
                  subtitle: const Text(
                    'Aturan aktif dapat membentuk sanksi baru secara otomatis.',
                  ),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                if (_editing && widget.existing!.triggeredCount > 0)
                  Container(
                    margin: const EdgeInsets.only(top: 4),
                    padding: const EdgeInsets.all(11),
                    decoration: BoxDecoration(
                      color: NusaColors.accent.withValues(alpha: 0.13),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      'Aturan ini telah memicu ${widget.existing!.triggeredCount} sanksi. '
                      'Sanksi tersebut dan poin saat terpicu tetap tersimpan.',
                      style: const TextStyle(fontSize: 11.5, height: 1.35),
                    ),
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
                key: const Key('save-sanction-rule'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Aturan'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    final threshold = int.tryParse(_thresholdController.text);
    final name = _nameController.text.trim();
    final description = _descriptionController.text.trim();
    final order = int.tryParse(_orderController.text) ?? 0;
    if (threshold == null || threshold < 1 || threshold > 10000) {
      setState(
        () => _error = 'Ambang harus berupa angka antara 1 sampai 10.000.',
      );
      return;
    }
    if (name.isEmpty) {
      setState(() => _error = 'Nama sanksi wajib diisi.');
      return;
    }
    if (description.isEmpty) {
      setState(() => _error = 'Penjelasan tindakan wajib diisi.');
      return;
    }
    if (order < 0 || order > 9999) {
      setState(() => _error = 'Urutan harus berada antara 0 sampai 9999.');
      return;
    }

    Navigator.pop(
      context,
      PointSanctionRuleFormValue(
        pointThreshold: threshold,
        name: name,
        description: description,
        order: order,
        active: _active,
      ),
    );
  }
}
