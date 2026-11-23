import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_component/domain/grade_component.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GradeComponentFormSheet extends StatefulWidget {
  const GradeComponentFormSheet({
    required this.assignments,
    this.existing,
    this.collective = false,
    super.key,
  });

  final List<GradeComponentAssignment> assignments;
  final GradeComponent? existing;
  final bool collective;

  @override
  State<GradeComponentFormSheet> createState() =>
      _GradeComponentFormSheetState();
}

class _GradeComponentFormSheetState extends State<GradeComponentFormSheet> {
  late int? _assignmentId;
  int? _yearId;
  String? _subjectId;
  final _selectedAssignments = <int>{};
  late String _semester;
  late String _type;
  late bool _active;
  late DateTime? _assessmentDate;
  late final TextEditingController _nameController;
  late final TextEditingController _notesController;
  String? _error;

  bool get _editing => widget.existing != null;
  bool get _collective => widget.collective && !_editing;

  Map<String, List<GradeComponentAssignment>> get _subjects {
    final groups = <String, List<GradeComponentAssignment>>{};
    for (final assignment in widget.assignments) {
      if (assignment.academicYear.id == _yearId) {
        final key = '${assignment.subject.id}:${assignment.employee.id}';
        (groups[key] ??= []).add(assignment);
      }
    }
    return groups;
  }

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    final preferred =
        widget.assignments
            .where((item) => item.academicYear.active)
            .firstOrNull ??
        widget.assignments.firstOrNull;
    _assignmentId = existing?.assignment.id ?? preferred?.id;
    _yearId = preferred?.academicYear.id;
    if (_collective) _resetSubject();
    _semester = existing?.semester ?? 'ganjil';
    _type = existing?.type ?? 'formatif';
    _active = existing?.active ?? true;
    _assessmentDate = existing == null
        ? DateUtils.dateOnly(DateTime.now())
        : DateTime.tryParse(existing.assessmentDate ?? '');
    _nameController = TextEditingController(text: existing?.name);
    _notesController = TextEditingController(text: existing?.notes);
  }

  @override
  void dispose() {
    _nameController.dispose();
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
                if (_collective)
                  ..._classFields()
                else
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
                _dateField(),
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
              ],
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 6, 16, 0),
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-grade-component'),
                onPressed: _collective && _selectedAssignments.isEmpty
                    ? null
                    : _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Komponen'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _resetSubject() {
    final subjects = _subjects;
    _selectSubject(subjects.length == 1 ? subjects.keys.single : null);
  }

  void _selectSubject(String? value) {
    _subjectId = value;
    _selectedAssignments.clear();
    final classes = _subjects[value];
    if (classes?.length == 1) _selectedAssignments.add(classes!.single.id);
  }

  List<Widget> _classFields() {
    final years = {
      for (final item in widget.assignments)
        item.academicYear.id: item.academicYear,
    };
    final subjects = _subjects;
    final classes = [...?subjects[_subjectId]]
      ..sort((a, b) => a.schoolClass.name.compareTo(b.schoolClass.name));
    final showTeacher =
        subjects.values.map((items) => items.first.employee.id).toSet().length >
        1;
    final count = _selectedAssignments.length;

    return [
      NusaDropdownField<int>(
        fieldKey: const Key('grade-component-form-year'),
        value: _yearId,
        decoration: const InputDecoration(labelText: 'Tahun pelajaran'),
        options: [
          for (final year in years.values)
            NusaDropdownOption(
              value: year.id,
              label: '${year.name}${year.active ? ' (aktif)' : ''}',
            ),
        ],
        onChanged: (value) => setState(() {
          _yearId = value;
          _resetSubject();
        }),
      ),
      const SizedBox(height: 11),
      NusaDropdownField<String>(
        fieldKey: const Key('grade-component-form-subject'),
        value: _subjectId,
        decoration: const InputDecoration(labelText: 'Mata pelajaran'),
        options: [
          for (final entry in subjects.entries)
            NusaDropdownOption(
              value: entry.key,
              label:
                  '${entry.value.first.subject.name}${showTeacher ? ' - ${entry.value.first.employee.name}' : ''}',
            ),
        ],
        onChanged: (value) => setState(() => _selectSubject(value)),
      ),
      if (classes.isNotEmpty) ...[
        const SizedBox(height: 4),
        CheckboxListTile(
          key: const Key('grade-component-all-classes'),
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          dense: true,
          tristate: true,
          title: const Text('Pilih semua kelas yang diampu'),
          subtitle: Text('$count dari ${classes.length} kelas dipilih'),
          value: count == classes.length
              ? true
              : count == 0
              ? false
              : null,
          onChanged: (_) => setState(() {
            _selectedAssignments.clear();
            if (count != classes.length) {
              _selectedAssignments.addAll(classes.map((item) => item.id));
            }
          }),
        ),
        LayoutBuilder(
          builder: (context, constraints) => Wrap(
            spacing: 8,
            children: [
              for (final item in classes)
                SizedBox(
                  width: constraints.maxWidth < 280
                      ? constraints.maxWidth
                      : (constraints.maxWidth - 8) / 2,
                  child: CheckboxListTile(
                    key: Key('grade-component-class-${item.id}'),
                    title: Text(item.schoolClass.name),
                    dense: true,
                    visualDensity: VisualDensity.compact,
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    value: _selectedAssignments.contains(item.id),
                    onChanged: (value) => setState(() {
                      if (value == true) {
                        _selectedAssignments.add(item.id);
                      } else {
                        _selectedAssignments.remove(item.id);
                      }
                    }),
                  ),
                ),
            ],
          ),
        ),
      ],
    ];
  }

  Widget _dateField() => InkWell(
    key: const Key('grade-component-form-date'),
    borderRadius: BorderRadius.circular(14),
    onTap: _pickDate,
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: 'Tanggal penilaian',
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
    final assignmentIds = _collective
        ? _selectedAssignments.toList()
        : [?_assignmentId];
    if (assignmentIds.isEmpty) {
      setState(() => _error = 'Pilih minimal satu kelas.');
      return;
    }
    if (name.isEmpty) {
      setState(() => _error = 'Nama komponen wajib diisi.');
      return;
    }
    Navigator.pop(
      context,
      GradeComponentFormValue(
        assignmentIds: assignmentIds,
        semester: _semester,
        type: _type,
        name: name,
        assessmentDate: _assessmentDate == null
            ? null
            : _apiDate(_assessmentDate!),
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
