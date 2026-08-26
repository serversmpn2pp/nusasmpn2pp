import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_component/domain/grade_component.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GradeComponentFormSheet extends StatefulWidget {
  const GradeComponentFormSheet({
    required this.assignments,
    this.existing,
    super.key,
  });

  final List<GradeComponentAssignment> assignments;
  final GradeComponent? existing;

  @override
  State<GradeComponentFormSheet> createState() =>
      _GradeComponentFormSheetState();
}

class _GradeComponentFormSheetState extends State<GradeComponentFormSheet> {
  late int? _assignmentId;
  late String _semester;
  late String _type;
  late bool _active;
  late DateTime? _assessmentDate;
  late final TextEditingController _nameController;
  late final TextEditingController _orderController;
  late final TextEditingController _notesController;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _assignmentId =
        existing?.assignment.id ??
        widget.assignments
            .where((item) => item.academicYear.active)
            .firstOrNull
            ?.id ??
        widget.assignments.firstOrNull?.id;
    _semester = existing?.semester ?? 'ganjil';
    _type = existing?.type ?? 'formatif';
    _active = existing?.active ?? true;
    _assessmentDate = existing?.assessmentDate == null
        ? null
        : DateTime.tryParse(existing!.assessmentDate!);
    _nameController = TextEditingController(text: existing?.name);
    _orderController = TextEditingController(text: '${existing?.order ?? 1}');
    _notesController = TextEditingController(text: existing?.notes);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _orderController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.92,
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
                    _editing ? 'Ubah Komponen Nilai' : 'Tambah Komponen Nilai',
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-grade-component-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('grade-component-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        Icons.info_outline_rounded,
                        color: NusaColors.primary,
                      ),
                      SizedBox(width: 9),
                      Expanded(
                        child: Text(
                          'Pilih penugasan guru, lalu tentukan komponen yang '
                          'akan muncul pada halaman input nilai siswa.',
                          style: TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 11,
                            height: 1.35,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                NusaDropdownField<int>(
                  fieldKey: const Key('grade-component-form-assignment'),
                  value: _assignmentId,
                  decoration: const InputDecoration(
                    labelText: 'Guru mata pelajaran',
                    prefixIcon: Icon(Icons.co_present_rounded),
                  ),
                  options: [
                    for (final assignment in widget.assignments)
                      NusaDropdownOption(
                        value: assignment.id,
                        label: assignment.label,
                      ),
                  ],
                  onChanged: (value) => setState(() => _assignmentId = value),
                ),
                const SizedBox(height: 11),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: NusaDropdownField<String>(
                        fieldKey: const Key('grade-component-form-semester'),
                        value: _semester,
                        decoration: const InputDecoration(
                          labelText: 'Semester',
                        ),
                        options: const [
                          NusaDropdownOption(value: 'ganjil', label: 'Ganjil'),
                          NusaDropdownOption(value: 'genap', label: 'Genap'),
                        ],
                        onChanged: (value) {
                          if (value != null) setState(() => _semester = value);
                        },
                      ),
                    ),
                    const SizedBox(width: 9),
                    Expanded(
                      child: NusaDropdownField<String>(
                        fieldKey: const Key('grade-component-form-type'),
                        value: _type,
                        decoration: const InputDecoration(labelText: 'Jenis'),
                        options: const [
                          NusaDropdownOption(
                            value: 'formatif',
                            label: 'Formatif',
                          ),
                          NusaDropdownOption(
                            value: 'sumatif',
                            label: 'Sumatif',
                          ),
                          NusaDropdownOption(value: 'sts', label: 'STS'),
                          NusaDropdownOption(
                            value: 'sas_saj',
                            label: 'SAS/SAJ',
                          ),
                        ],
                        onChanged: (value) {
                          if (value != null) setState(() => _type = value);
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 11),
                TextField(
                  key: const Key('grade-component-form-name'),
                  controller: _nameController,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Nama komponen',
                    hintText: 'Contoh: Kuis Aljabar 1',
                    prefixIcon: Icon(Icons.fact_check_outlined),
                  ),
                ),
                const SizedBox(height: 11),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: _dateField()),
                    const SizedBox(width: 9),
                    Expanded(
                      child: TextField(
                        key: const Key('grade-component-form-order'),
                        controller: _orderController,
                        keyboardType: TextInputType.number,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                        ],
                        decoration: const InputDecoration(
                          labelText: 'Urutan tampil',
                          prefixIcon: Icon(Icons.format_list_numbered_rounded),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                SwitchListTile.adaptive(
                  key: const Key('grade-component-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Komponen aktif'),
                  subtitle: const Text('Tersedia untuk input nilai siswa.'),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                TextField(
                  key: const Key('grade-component-form-notes'),
                  controller: _notesController,
                  minLines: 2,
                  maxLines: 4,
                  maxLength: 2000,
                  decoration: const InputDecoration(
                    labelText: 'Keterangan (opsional)',
                    prefixIcon: Icon(Icons.notes_rounded),
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: NusaColors.accent.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Text(
                    'STS dan SAS/SAJ aktif hanya boleh satu untuk setiap guru '
                    'mapel dan semester. Perubahan komponen mengembalikan '
                    'publikasi nilai menjadi draf.',
                    style: TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                      height: 1.35,
                    ),
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 10),
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
                key: const Key('save-grade-component'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Komponen'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Widget _dateField() => InkWell(
    key: const Key('grade-component-form-date'),
    borderRadius: BorderRadius.circular(14),
    onTap: _pickDate,
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: 'Tanggal',
        prefixIcon: const Icon(Icons.event_rounded),
        suffixIcon: _assessmentDate == null
            ? null
            : IconButton(
                tooltip: 'Hapus tanggal',
                onPressed: () => setState(() => _assessmentDate = null),
                icon: const Icon(Icons.close_rounded),
              ),
      ),
      child: Text(
        _assessmentDate == null ? 'Opsional' : _formatDate(_assessmentDate!),
        maxLines: 1,
      ),
    ),
  );

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final value = await showDatePicker(
      context: context,
      initialDate: _assessmentDate ?? now,
      firstDate: DateTime(now.year - 5),
      lastDate: DateTime(now.year + 5),
    );
    if (value != null && mounted) setState(() => _assessmentDate = value);
  }

  void _submit() {
    final name = _nameController.text.trim();
    final order = int.tryParse(_orderController.text.trim());
    if (_assignmentId == null) {
      setState(() => _error = 'Guru mata pelajaran wajib dipilih.');
      return;
    }
    if (name.isEmpty) {
      setState(() => _error = 'Nama komponen wajib diisi.');
      return;
    }
    if (order == null || order < 0 || order > 999) {
      setState(() => _error = 'Urutan tampil harus berada pada rentang 0–999.');
      return;
    }

    Navigator.pop(
      context,
      GradeComponentFormValue(
        assignmentId: _assignmentId!,
        semester: _semester,
        type: _type,
        name: name,
        assessmentDate: _assessmentDate == null
            ? null
            : _apiDate(_assessmentDate!),
        order: order,
        active: _active,
        notes: _notesController.text.trim().isEmpty
            ? null
            : _notesController.text.trim(),
      ),
    );
  }
}

String _apiDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-'
    '${value.day.toString().padLeft(2, '0')}';

String _formatDate(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-${value.year}';
