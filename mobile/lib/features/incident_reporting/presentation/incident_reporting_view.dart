import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/incident_reporting/application/incident_reporting_controller.dart';
import 'package:nusa/features/incident_reporting/data/incident_evidence_picker.dart';
import 'package:nusa/features/incident_reporting/domain/incident_reporting.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class IncidentReportingView extends ConsumerStatefulWidget {
  const IncidentReportingView({super.key});

  @override
  ConsumerState<IncidentReportingView> createState() =>
      _IncidentReportingViewState();
}

class _IncidentReportingViewState extends ConsumerState<IncidentReportingView> {
  final _placeController = TextEditingController();
  final _chronologyController = TextEditingController();
  final _actionController = TextEditingController();
  final _evidenceDescriptionController = TextEditingController();
  final _selectedStudentIds = <int>{};
  final _evidence = <IncidentEvidenceFile>[];
  final _witnesses = <_WitnessDraft>[];
  DateTime? _date;
  TimeOfDay? _time;
  int? _academicYearId;
  int? _classId;
  bool _initialized = false;
  bool _submitting = false;

  @override
  void dispose() {
    _placeController.dispose();
    _chronologyController.dispose();
    _actionController.dispose();
    _evidenceDescriptionController.dispose();
    for (final witness in _witnesses) {
      witness.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reference = ref.watch(incidentReportingControllerProvider);
    if (reference.value case final data?) _initialize(data);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Laporkan Kejadian'),
        actions: [
          IconButton(
            tooltip: 'Muat ulang referensi',
            onPressed: reference.isLoading || _submitting
                ? null
                : ref
                      .read(incidentReportingControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: reference.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _IncidentError(
            message: _errorMessage(error),
            onRetry: ref
                .read(incidentReportingControllerProvider.notifier)
                .refresh,
          ),
          data: (data) => _buildForm(data),
        ),
      ),
    );
  }

  Widget _buildForm(IncidentReportReference data) => ListView(
    key: const Key('incident-report-form-scroll'),
    padding: const EdgeInsets.fromLTRB(16, 10, 16, 32),
    children: [
      Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [NusaColors.primary, NusaColors.primaryDark],
          ),
          borderRadius: BorderRadius.circular(17),
        ),
        child: const Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.campaign_rounded, color: NusaColors.accent),
            SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Catat fakta kejadian',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  SizedBox(height: 3),
                  Text(
                    'BK akan memeriksa setiap siswa secara terpisah sebelum pelanggaran atau poin ditetapkan.',
                    style: TextStyle(color: Colors.white70, fontSize: 11.5),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 11),
      _FormSection(
        icon: Icons.event_note_rounded,
        title: 'Data kejadian',
        child: Column(
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: _dateField()),
                const SizedBox(width: 8),
                Expanded(child: _timeField()),
              ],
            ),
            const SizedBox(height: 9),
            NusaTextField(
              fieldKey: const Key('incident-place'),
              controller: _placeController,
              labelText: 'Tempat kejadian',
              hintText: 'Contoh: koridor sekolah',
              prefixIcon: Icons.place_outlined,
              enabled: !_submitting,
            ),
            const SizedBox(height: 9),
            NusaDropdownField<int>(
              fieldKey: const Key('incident-academic-year'),
              value: _academicYearId,
              options: [
                for (final year in data.academicYears)
                  NusaDropdownOption(
                    value: year.id,
                    label: '${year.name}${year.active ? ' · Aktif' : ''}',
                  ),
              ],
              decoration: const InputDecoration(
                labelText: 'Tahun pelajaran',
                prefixIcon: Icon(Icons.calendar_month_outlined),
              ),
              enabled: !_submitting,
              onChanged: (value) => setState(() {
                _academicYearId = value;
                _classId = null;
                _selectedStudentIds.clear();
              }),
            ),
            const SizedBox(height: 9),
            NusaDropdownField<int?>(
              fieldKey: const Key('incident-class'),
              value: _classId,
              options: [
                const NusaDropdownOption<int?>(
                  value: null,
                  label: 'Semua kelas',
                ),
                for (final schoolClass in data.classes.where(
                  (item) =>
                      _academicYearId == null ||
                      item.academicYearId == _academicYearId,
                ))
                  NusaDropdownOption<int?>(
                    value: schoolClass.id,
                    label: schoolClass.name,
                  ),
              ],
              decoration: const InputDecoration(
                labelText: 'Filter kelas',
                prefixIcon: Icon(Icons.class_outlined),
              ),
              enabled: !_submitting,
              onChanged: (value) => setState(() {
                _classId = value;
                _selectedStudentIds.clear();
              }),
            ),
          ],
        ),
      ),
      const SizedBox(height: 11),
      _FormSection(
        icon: Icons.groups_rounded,
        title: 'Siswa terlapor',
        subtitle:
            '${_selectedStudentIds.length} dipilih · maksimal ${data.limits.maxStudents}',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            OutlinedButton.icon(
              key: const Key('select-incident-students'),
              onPressed: _submitting ? null : () => _pickStudents(data),
              icon: const Icon(Icons.person_search_rounded),
              label: Text(
                _selectedStudentIds.isEmpty
                    ? 'Pilih siswa'
                    : 'Ubah pilihan siswa',
              ),
            ),
            if (_selectedStudentIds.isNotEmpty) ...[
              const SizedBox(height: 9),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  for (final student in data.students.where(
                    (item) => _selectedStudentIds.contains(item.id),
                  ))
                    InputChip(
                      key: Key('selected-incident-student-${student.id}'),
                      label: Text(student.name),
                      onDeleted: _submitting
                          ? null
                          : () => setState(
                              () => _selectedStudentIds.remove(student.id),
                            ),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
      const SizedBox(height: 11),
      _FormSection(
        icon: Icons.description_outlined,
        title: 'Kronologi dan tindakan',
        child: Column(
          children: [
            TextField(
              key: const Key('incident-chronology'),
              controller: _chronologyController,
              enabled: !_submitting,
              minLines: 4,
              maxLines: 8,
              decoration: const InputDecoration(
                labelText: 'Kronologi faktual *',
                hintText: 'Tuliskan siapa, apa yang terjadi, serta fakta yang tersedia.',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 9),
            TextField(
              key: const Key('incident-initial-action'),
              controller: _actionController,
              enabled: !_submitting,
              minLines: 2,
              maxLines: 5,
              decoration: const InputDecoration(
                labelText: 'Tindakan awal',
                hintText: 'Tindakan yang sudah dilakukan saat kejadian.',
                alignLabelWithHint: true,
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 11),
      _FormSection(
        icon: Icons.visibility_outlined,
        title: 'Saksi awal',
        subtitle: 'Opsional · maksimal ${data.limits.maxWitnesses}',
        trailing: IconButton(
          key: const Key('add-incident-witness'),
          tooltip: 'Tambah saksi',
          onPressed:
              _submitting || _witnesses.length >= data.limits.maxWitnesses
              ? null
              : _addWitness,
          icon: const Icon(Icons.add_circle_outline_rounded),
        ),
        child: _witnesses.isEmpty
            ? const Text(
                'Belum ada saksi awal.',
                style: TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11.5,
                ),
              )
            : Column(
                children: [
                  for (var index = 0; index < _witnesses.length; index++) ...[
                    _WitnessEditor(
                      index: index,
                      witness: _witnesses[index],
                      enabled: !_submitting,
                      onRemove: () => _removeWitness(index),
                    ),
                    if (index < _witnesses.length - 1)
                      const SizedBox(height: 9),
                  ],
                ],
              ),
      ),
      const SizedBox(height: 11),
      _FormSection(
        icon: Icons.attach_file_rounded,
        title: 'Bukti pendukung',
        subtitle:
            'Foto/PDF · maksimal ${data.limits.maxEvidence} file, ${data.limits.maxEvidenceMb} MB per file',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            OutlinedButton.icon(
              key: const Key('pick-incident-evidence'),
              onPressed: _submitting ? null : () => _pickEvidence(data),
              icon: const Icon(Icons.upload_file_outlined),
              label: const Text('Pilih bukti'),
            ),
            if (_evidence.isNotEmpty) ...[
              const SizedBox(height: 8),
              for (var index = 0; index < _evidence.length; index++)
                ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    _evidence[index].name.toLowerCase().endsWith('.pdf')
                        ? Icons.picture_as_pdf_outlined
                        : Icons.image_outlined,
                    color: NusaColors.primary,
                  ),
                  title: Text(
                    _evidence[index].name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  subtitle: Text(_fileSize(_evidence[index].bytes.length)),
                  trailing: IconButton(
                    key: Key('remove-incident-evidence-$index'),
                    onPressed: _submitting
                        ? null
                        : () => setState(() => _evidence.removeAt(index)),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ),
              const SizedBox(height: 6),
              NusaTextField(
                fieldKey: const Key('incident-evidence-description'),
                controller: _evidenceDescriptionController,
                labelText: 'Keterangan bukti',
                hintText: 'Contoh: foto dari kamera koridor',
                prefixIcon: Icons.notes_rounded,
                enabled: !_submitting,
              ),
            ],
          ],
        ),
      ),
      const SizedBox(height: 15),
      FilledButton.icon(
        key: const Key('submit-incident-report'),
        onPressed: _submitting ? null : () => _submit(data),
        icon: _submitting
            ? const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.send_rounded),
        label: Text(_submitting ? 'Mengirim...' : 'Kirim ke BK'),
      ),
    ],
  );

  void _initialize(IncidentReportReference data) {
    if (_initialized) return;
    _initialized = true;
    _date = DateTime.tryParse(data.defaultDate) ?? DateTime.now();
    _academicYearId = data.defaultAcademicYearId;
  }

  Widget _dateField() => InkWell(
    key: const Key('incident-date'),
    onTap: _submitting ? null : _pickDate,
    borderRadius: BorderRadius.circular(14),
    child: InputDecorator(
      decoration: const InputDecoration(
        labelText: 'Tanggal *',
        prefixIcon: Icon(Icons.event_outlined),
      ),
      child: Text(_date == null ? '-' : _formatDate(_date!)),
    ),
  );

  Widget _timeField() => InkWell(
    key: const Key('incident-time'),
    onTap: _submitting ? null : _pickTime,
    borderRadius: BorderRadius.circular(14),
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: 'Waktu',
        prefixIcon: const Icon(Icons.schedule_outlined),
        suffixIcon: _time == null
            ? null
            : IconButton(
                tooltip: 'Hapus waktu',
                onPressed: () => setState(() => _time = null),
                icon: const Icon(Icons.close_rounded),
              ),
      ),
      child: Text(_time == null ? 'Opsional' : _time!.format(context)),
    ),
  );

  Future<void> _pickDate() async {
    final value = await showDatePicker(
      context: context,
      initialDate: _date ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 30)),
    );
    if (value != null && mounted) setState(() => _date = value);
  }

  Future<void> _pickTime() async {
    final value = await showTimePicker(
      context: context,
      initialTime: _time ?? TimeOfDay.now(),
    );
    if (value != null && mounted) setState(() => _time = value);
  }

  Future<void> _pickStudents(IncidentReportReference data) async {
    final value = await showModalBottomSheet<Set<int>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _StudentPickerSheet(
        students: data.students,
        academicYearId: _academicYearId,
        classId: _classId,
        selectedIds: _selectedStudentIds,
        maximum: data.limits.maxStudents,
      ),
    );
    if (value != null && mounted) {
      setState(() {
        _selectedStudentIds
          ..clear()
          ..addAll(value);
      });
    }
  }

  Future<void> _pickEvidence(IncidentReportReference data) async {
    try {
      final picked = await ref.read(incidentEvidencePickerProvider).pick();
      if (!mounted || picked.isEmpty) return;
      final combined = [..._evidence, ...picked];
      if (combined.length > data.limits.maxEvidence) {
        _showMessage('Maksimal ${data.limits.maxEvidence} file bukti.');
        return;
      }
      final maxBytes = data.limits.maxEvidenceMb * 1024 * 1024;
      final tooLarge = combined.where((item) => item.bytes.length > maxBytes);
      if (tooLarge.isNotEmpty) {
        _showMessage(
          '${tooLarge.first.name} melebihi ${data.limits.maxEvidenceMb} MB.',
        );
        return;
      }
      setState(() {
        _evidence
          ..clear()
          ..addAll(combined);
      });
    } catch (_) {
      if (mounted) _showMessage('Bukti belum dapat dipilih.');
    }
  }

  void _addWitness() => setState(() => _witnesses.add(_WitnessDraft()));

  void _removeWitness(int index) => setState(() {
    _witnesses.removeAt(index).dispose();
  });

  Future<void> _submit(IncidentReportReference data) async {
    if (_date == null) {
      _showMessage('Tanggal kejadian harus dipilih.');
      return;
    }
    if (_selectedStudentIds.isEmpty) {
      _showMessage('Pilih minimal satu siswa terlapor.');
      return;
    }
    if (_chronologyController.text.trim().isEmpty) {
      _showMessage('Kronologi faktual harus diisi.');
      return;
    }

    final witnesses = <IncidentWitnessValue>[];
    for (var index = 0; index < _witnesses.length; index++) {
      final draft = _witnesses[index];
      if (draft.name.text.trim().isEmpty ||
          draft.statement.text.trim().isEmpty) {
        _showMessage('Nama dan pernyataan saksi ${index + 1} harus lengkap.');
        return;
      }
      witnesses.add(
        IncidentWitnessValue(
          type: draft.type,
          name: draft.name.text.trim(),
          statement: draft.statement.text.trim(),
        ),
      );
    }

    setState(() => _submitting = true);
    try {
      final result = await ref
          .read(incidentReportingActionsProvider)
          .submit(
            IncidentReportFormValue(
              date: _isoDate(_date!),
              time: _time == null
                  ? null
                  : '${_time!.hour.toString().padLeft(2, '0')}:${_time!.minute.toString().padLeft(2, '0')}',
              place: _placeController.text.trim(),
              academicYearId: _academicYearId,
              classId: _classId,
              studentIds: _selectedStudentIds.toList(growable: false),
              chronology: _chronologyController.text.trim(),
              initialAction: _actionController.text.trim(),
              witnesses: witnesses,
              evidence: List.unmodifiable(_evidence),
              evidenceDescription: _evidenceDescriptionController.text.trim(),
            ),
          );
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Laporan terkirim'),
          content: Text(
            '${result.message}\n\nNomor: ${result.reports.map((item) => item.number).join(', ')}',
          ),
          actions: [
            FilledButton(
              key: const Key('close-incident-success'),
              onPressed: () => Navigator.pop(context),
              child: const Text('Selesai'),
            ),
          ],
        ),
      );
      if (mounted) _reset(data);
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _reset(IncidentReportReference data) => setState(() {
    _date = DateTime.tryParse(data.defaultDate) ?? DateTime.now();
    _time = null;
    _academicYearId = data.defaultAcademicYearId;
    _classId = null;
    _selectedStudentIds.clear();
    _placeController.clear();
    _chronologyController.clear();
    _actionController.clear();
    _evidenceDescriptionController.clear();
    _evidence.clear();
    for (final witness in _witnesses) {
      witness.dispose();
    }
    _witnesses.clear();
  });

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _StudentPickerSheet extends StatefulWidget {
  const _StudentPickerSheet({
    required this.students,
    required this.academicYearId,
    required this.classId,
    required this.selectedIds,
    required this.maximum,
  });

  final List<IncidentStudent> students;
  final int? academicYearId;
  final int? classId;
  final Set<int> selectedIds;
  final int maximum;

  @override
  State<_StudentPickerSheet> createState() => _StudentPickerSheetState();
}

class _StudentPickerSheetState extends State<_StudentPickerSheet> {
  late final Set<int> _selected = {...widget.selectedIds};
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = _searchController.text.trim().toLowerCase();
    final students = widget.students
        .where((student) {
          final matchesPlacement = student.belongsTo(
            academicYearId: widget.academicYearId,
            classId: widget.classId,
          );
          final haystack =
              '${student.name} ${student.studentNumber ?? ''} ${student.nationalStudentNumber ?? ''}'
                  .toLowerCase();
          return matchesPlacement &&
              (query.isEmpty || haystack.contains(query));
        })
        .toList(growable: false);

    return SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.86).clamp(520.0, 760.0),
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
            padding: const EdgeInsets.fromLTRB(16, 13, 8, 8),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Pilih Siswa Terlapor',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        '${_selected.length} dipilih',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: NusaTextField(
              fieldKey: const Key('incident-student-search'),
              controller: _searchController,
              hintText: 'Nama, NIS, atau NISN',
              prefixIcon: Icons.search_rounded,
              onChanged: (_) => setState(() {}),
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: students.isEmpty
                ? const Center(child: Text('Tidak ada siswa yang sesuai.'))
                : ListView.builder(
                    itemCount: students.length,
                    itemBuilder: (context, index) {
                      final student = students[index];
                      return CheckboxListTile(
                        key: Key('incident-student-option-${student.id}'),
                        value: _selected.contains(student.id),
                        onChanged: (checked) {
                          if (checked == true &&
                              _selected.length >= widget.maximum) {
                            return;
                          }
                          setState(() {
                            checked == true
                                ? _selected.add(student.id)
                                : _selected.remove(student.id);
                          });
                        },
                        title: Text(student.name),
                        subtitle: Text(
                          '${student.classLabel(academicYearId: widget.academicYearId)} · NISN ${student.nationalStudentNumber ?? '-'}',
                        ),
                      );
                    },
                  ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton(
                key: const Key('apply-incident-students'),
                onPressed: () => Navigator.pop(context, _selected),
                child: Text('Gunakan ${_selected.length} siswa'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WitnessEditor extends StatefulWidget {
  const _WitnessEditor({
    required this.index,
    required this.witness,
    required this.enabled,
    required this.onRemove,
  });

  final int index;
  final _WitnessDraft witness;
  final bool enabled;
  final VoidCallback onRemove;

  @override
  State<_WitnessEditor> createState() => _WitnessEditorState();
}

class _WitnessEditorState extends State<_WitnessEditor> {
  @override
  Widget build(BuildContext context) => Container(
    key: Key('incident-witness-${widget.index}'),
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: NusaColors.background,
      borderRadius: BorderRadius.circular(13),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Saksi ${widget.index + 1}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            IconButton(
              key: Key('remove-incident-witness-${widget.index}'),
              onPressed: widget.enabled ? widget.onRemove : null,
              icon: const Icon(Icons.delete_outline_rounded),
            ),
          ],
        ),
        NusaDropdownField<String>(
          fieldKey: Key('incident-witness-type-${widget.index}'),
          value: widget.witness.type,
          options: const [
            NusaDropdownOption(value: 'siswa', label: 'Siswa'),
            NusaDropdownOption(value: 'pegawai', label: 'Pegawai'),
            NusaDropdownOption(value: 'lainnya', label: 'Lainnya'),
          ],
          decoration: const InputDecoration(labelText: 'Jenis saksi'),
          enabled: widget.enabled,
          onChanged: (value) {
            if (value != null) setState(() => widget.witness.type = value);
          },
        ),
        const SizedBox(height: 8),
        NusaTextField(
          fieldKey: Key('incident-witness-name-${widget.index}'),
          controller: widget.witness.name,
          labelText: 'Nama saksi',
          hintText: 'Nama saksi',
          prefixIcon: Icons.person_outline_rounded,
          enabled: widget.enabled,
        ),
        const SizedBox(height: 8),
        TextField(
          key: Key('incident-witness-statement-${widget.index}'),
          controller: widget.witness.statement,
          enabled: widget.enabled,
          minLines: 2,
          maxLines: 4,
          decoration: const InputDecoration(
            labelText: 'Pernyataan singkat',
            alignLabelWithHint: true,
          ),
        ),
      ],
    ),
  );
}

class _FormSection extends StatelessWidget {
  const _FormSection({
    required this.icon,
    required this.title,
    required this.child,
    this.subtitle,
    this.trailing,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: NusaColors.primary, size: 21),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (subtitle != null)
                      Text(
                        subtitle!,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                  ],
                ),
              ),
              ?trailing,
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    ),
  );
}

class _IncidentError extends StatelessWidget {
  const _IncidentError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: NusaColors.primary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

class _WitnessDraft {
  String type = 'lainnya';
  final name = TextEditingController();
  final statement = TextEditingController();

  void dispose() {
    name.dispose();
    statement.dispose();
  }
}

String _formatDate(DateTime date) =>
    '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';

String _isoDate(DateTime date) =>
    '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

String _fileSize(int bytes) => bytes >= 1024 * 1024
    ? '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB'
    : '${(bytes / 1024).ceil()} KB';

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Laporan kejadian belum dapat dikirim.';
}
