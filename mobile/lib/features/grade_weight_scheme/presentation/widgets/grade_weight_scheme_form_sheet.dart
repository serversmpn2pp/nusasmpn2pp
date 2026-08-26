import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_weight_scheme/domain/grade_weight_scheme.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GradeWeightSchemeFormSheet extends StatefulWidget {
  const GradeWeightSchemeFormSheet({
    required this.academicYears,
    this.existing,
    super.key,
  });

  final List<SchemeAcademicYear> academicYears;
  final GradeWeightScheme? existing;

  @override
  State<GradeWeightSchemeFormSheet> createState() =>
      _GradeWeightSchemeFormSheetState();
}

class _GradeWeightSchemeFormSheetState
    extends State<GradeWeightSchemeFormSheet> {
  late int? _academicYearId;
  late String _semester;
  late int? _grade;
  late bool _active;
  late final TextEditingController _formativeController;
  late final TextEditingController _summativeController;
  late final TextEditingController _midtermController;
  late final TextEditingController _finalController;
  late final TextEditingController _notesController;
  String? _error;

  bool get _editing => widget.existing != null;

  int get _total =>
      _weight(_formativeController) +
      _weight(_summativeController) +
      _weight(_midtermController) +
      _weight(_finalController);

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _academicYearId =
        existing?.academicYear.id ??
        widget.academicYears.where((year) => year.active).firstOrNull?.id ??
        widget.academicYears.firstOrNull?.id;
    _semester = existing?.semester ?? 'ganjil';
    _grade = existing?.grade;
    _active = existing?.active ?? true;
    _formativeController = TextEditingController(
      text: '${existing?.formativeWeight ?? 35}',
    );
    _summativeController = TextEditingController(
      text: '${existing?.summativeWeight ?? 25}',
    );
    _midtermController = TextEditingController(
      text: '${existing?.midtermWeight ?? 15}',
    );
    _finalController = TextEditingController(
      text: '${existing?.finalWeight ?? 25}',
    );
    _notesController = TextEditingController(text: existing?.notes);
  }

  @override
  void dispose() {
    _formativeController.dispose();
    _summativeController.dispose();
    _midtermController.dispose();
    _finalController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.9,
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
                    _editing ? 'Ubah Skema Bobot' : 'Tambah Skema Bobot',
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-weight-scheme-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('weight-scheme-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                _TotalWeightCard(total: _total),
                const SizedBox(height: 16),
                NusaDropdownField<int>(
                  fieldKey: const Key('weight-scheme-form-year'),
                  value: _academicYearId,
                  decoration: const InputDecoration(
                    labelText: 'Tahun pelajaran',
                    prefixIcon: Icon(Icons.calendar_month_rounded),
                  ),
                  options: [
                    for (final year in widget.academicYears)
                      NusaDropdownOption<int>(
                        value: year.id,
                        label: '${year.name}${year.active ? ' · Aktif' : ''}',
                      ),
                  ],
                  onChanged: (value) => setState(() => _academicYearId = value),
                ),
                const SizedBox(height: 11),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: NusaDropdownField<String>(
                        fieldKey: const Key('weight-scheme-form-semester'),
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
                      child: NusaDropdownField<int?>(
                        fieldKey: const Key('weight-scheme-form-grade'),
                        value: _grade,
                        decoration: const InputDecoration(labelText: 'Tingkat'),
                        options: const [
                          NusaDropdownOption<int?>(value: null, label: 'Semua'),
                          NusaDropdownOption<int?>(value: 7, label: 'VII'),
                          NusaDropdownOption<int?>(value: 8, label: 'VIII'),
                          NusaDropdownOption<int?>(value: 9, label: 'IX'),
                        ],
                        onChanged: (value) => setState(() => _grade = value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                const Text(
                  'Bobot Nilai Rapor',
                  style: TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: _WeightField(
                        fieldKey: const Key('weight-scheme-form-formative'),
                        label: 'Formatif',
                        controller: _formativeController,
                        onChanged: _weightChanged,
                      ),
                    ),
                    const SizedBox(width: 9),
                    Expanded(
                      child: _WeightField(
                        fieldKey: const Key('weight-scheme-form-summative'),
                        label: 'Sumatif',
                        controller: _summativeController,
                        onChanged: _weightChanged,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 11),
                Row(
                  children: [
                    Expanded(
                      child: _WeightField(
                        fieldKey: const Key('weight-scheme-form-midterm'),
                        label: 'STS',
                        controller: _midtermController,
                        onChanged: _weightChanged,
                      ),
                    ),
                    const SizedBox(width: 9),
                    Expanded(
                      child: _WeightField(
                        fieldKey: const Key('weight-scheme-form-final'),
                        label: _grade == 9 ? 'SAJ' : 'SAS/SAJ',
                        controller: _finalController,
                        onChanged: _weightChanged,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 7),
                SwitchListTile.adaptive(
                  key: const Key('weight-scheme-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Skema aktif'),
                  subtitle: const Text(
                    'Dipakai dalam perhitungan nilai rapor.',
                  ),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                TextField(
                  key: const Key('weight-scheme-form-notes'),
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
                    color: NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Text(
                    'Menyimpan perubahan skema akan mengembalikan publikasi '
                    'nilai terkait menjadi draf agar dihitung ulang.',
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
                key: const Key('save-weight-scheme'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Skema'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _weightChanged(String value) {
    setState(() => _error = null);
  }

  int _weight(TextEditingController controller) =>
      int.tryParse(controller.text) ?? 0;

  void _submit() {
    if (_academicYearId == null) {
      setState(() => _error = 'Tahun pelajaran wajib dipilih.');
      return;
    }
    final values = [
      _weight(_formativeController),
      _weight(_summativeController),
      _weight(_midtermController),
      _weight(_finalController),
    ];
    if (values.any((value) => value < 0 || value > 100)) {
      setState(() => _error = 'Setiap bobot harus berada pada rentang 0–100%.');
      return;
    }
    if (_total != 100) {
      setState(
        () => _error = 'Total bobot harus tepat 100%. Saat ini $_total%.',
      );
      return;
    }

    Navigator.pop(
      context,
      GradeWeightSchemeFormValue(
        academicYearId: _academicYearId!,
        semester: _semester,
        grade: _grade,
        formativeWeight: values[0],
        summativeWeight: values[1],
        midtermWeight: values[2],
        finalWeight: values[3],
        active: _active,
        notes: _notesController.text.trim().isEmpty
            ? null
            : _notesController.text.trim(),
      ),
    );
  }
}

class _TotalWeightCard extends StatelessWidget {
  const _TotalWeightCard({required this.total});

  final int total;

  @override
  Widget build(BuildContext context) {
    final valid = total == 100;
    final color = valid ? NusaColors.success : NusaColors.accent;
    return AnimatedContainer(
      key: const Key('weight-scheme-total'),
      duration: const Duration(milliseconds: 180),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        border: Border.all(color: color.withValues(alpha: 0.35)),
        borderRadius: BorderRadius.circular(15),
      ),
      child: Row(
        children: [
          Icon(
            valid ? Icons.task_alt_rounded : Icons.warning_amber_rounded,
            color: valid ? NusaColors.success : NusaColors.textPrimary,
          ),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Total Bobot',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
          Text(
            '$total%',
            style: TextStyle(
              color: valid ? NusaColors.success : NusaColors.textPrimary,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _WeightField extends StatelessWidget {
  const _WeightField({
    required this.fieldKey,
    required this.label,
    required this.controller,
    required this.onChanged,
  });

  final Key fieldKey;
  final String label;
  final TextEditingController controller;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => TextField(
    key: fieldKey,
    controller: controller,
    keyboardType: TextInputType.number,
    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
    onChanged: onChanged,
    decoration: InputDecoration(labelText: '$label (%)', suffixText: '%'),
  );
}
