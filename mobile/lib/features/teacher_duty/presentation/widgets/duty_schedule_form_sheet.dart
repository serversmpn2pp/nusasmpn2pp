import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teacher_duty/application/teacher_duty_controller.dart';
import 'package:nusa/features/teacher_duty/domain/teacher_duty.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class DutyScheduleFormSheet extends ConsumerStatefulWidget {
  const DutyScheduleFormSheet({
    required this.reference,
    this.existing,
    super.key,
  });
  final DutyScheduleReference reference;
  final DutySchedule? existing;

  @override
  ConsumerState<DutyScheduleFormSheet> createState() =>
      _DutyScheduleFormSheetState();
}

class _DutyScheduleFormSheetState extends ConsumerState<DutyScheduleFormSheet> {
  late DutyScheduleReference _reference;
  late int? _yearId;
  late String? _day;
  late Set<int> _teacherIds;
  late bool _active;
  late final TextEditingController _notes;
  bool _loadingReference = false;

  @override
  void initState() {
    super.initState();
    _reference = widget.reference;
    _yearId = widget.existing?.academicYear.id ?? _reference.academicYearId;
    _day = widget.existing?.day;
    _teacherIds = {if (widget.existing != null) widget.existing!.teacher.id};
    _active = widget.existing?.active ?? true;
    _notes = TextEditingController(text: widget.existing?.notes);
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 16, 20, bottom + 20),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    widget.existing == null
                        ? 'Tambah Guru Piket'
                        : 'Ubah Jadwal Piket',
                    style: const TextStyle(
                      fontSize: 19,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const Text(
              'Guru yang dapat dipilih adalah pengampu mata pelajaran aktif.',
              style: TextStyle(color: NusaColors.textSecondary, fontSize: 12),
            ),
            const SizedBox(height: 16),
            NusaDropdownField<int>(
              fieldKey: const Key('duty-year'),
              value: _yearId,
              options: _reference.academicYears
                  .map(
                    (item) => NusaDropdownOption(
                      value: item.id,
                      label: '${item.name}${item.active ? ' · Aktif' : ''}',
                    ),
                  )
                  .toList(),
              decoration: const InputDecoration(
                labelText: 'Tahun pelajaran',
                prefixIcon: Icon(Icons.calendar_month_rounded),
              ),
              enabled: widget.existing == null && !_loadingReference,
              onChanged: widget.existing != null ? null : _changeYear,
            ),
            const SizedBox(height: 12),
            NusaDropdownField<String>(
              fieldKey: const Key('duty-day'),
              value: _day,
              options: _reference.days
                  .map(
                    (item) =>
                        NusaDropdownOption(value: item.code, label: item.label),
                  )
                  .toList(),
              decoration: const InputDecoration(
                labelText: 'Hari',
                prefixIcon: Icon(Icons.today_rounded),
              ),
              onChanged: (value) => setState(() => _day = value),
            ),
            const SizedBox(height: 14),
            Text(
              widget.existing == null
                  ? 'Pilih guru (${_teacherIds.length})'
                  : 'Guru piket',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            if (_loadingReference)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: CircularProgressIndicator(),
                ),
              )
            else
              Container(
                constraints: const BoxConstraints(maxHeight: 230),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: Border.all(color: NusaColors.outline),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: _reference.teachers.isEmpty
                    ? const Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'Belum ada guru pengampu aktif pada tahun ini.',
                        ),
                      )
                    : ListView.separated(
                        shrinkWrap: true,
                        itemCount: _reference.teachers.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, index) {
                          final teacher = _reference.teachers[index];
                          final selected = _teacherIds.contains(teacher.id);
                          return CheckboxListTile(
                            dense: true,
                            value: selected,
                            title: Text(
                              teacher.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            subtitle: teacher.employeeNumber == null
                                ? null
                                : Text(teacher.employeeNumber!),
                            onChanged: (value) => setState(() {
                              if (widget.existing != null) {
                                _teacherIds = {if (value == true) teacher.id};
                              } else if (value == true) {
                                _teacherIds.add(teacher.id);
                              } else {
                                _teacherIds.remove(teacher.id);
                              }
                            }),
                          );
                        },
                      ),
              ),
            const SizedBox(height: 12),
            TextField(
              controller: _notes,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Keterangan (opsional)',
                prefixIcon: Icon(Icons.notes_rounded),
              ),
            ),
            const SizedBox(height: 8),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text(
                'Jadwal aktif',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              value: _active,
              onChanged: (value) => setState(() => _active = value),
            ),
            const SizedBox(height: 10),
            NusaPrimaryButton(
              label: widget.existing == null
                  ? 'Simpan Jadwal'
                  : 'Simpan Perubahan',
              onPressed: _submit,
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _changeYear(int? value) async {
    if (value == null || value == _yearId) return;
    setState(() {
      _yearId = value;
      _teacherIds.clear();
      _loadingReference = true;
    });
    try {
      final result = await ref
          .read(teacherDutyActionsProvider)
          .reference(value);
      if (mounted) setState(() => _reference = result);
    } finally {
      if (mounted) setState(() => _loadingReference = false);
    }
  }

  void _submit() {
    if (_yearId == null || _day == null || _teacherIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pilih tahun, hari, dan sedikitnya satu guru.'),
        ),
      );
      return;
    }
    if (widget.existing != null && _teacherIds.length != 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih satu guru untuk jadwal ini.')),
      );
      return;
    }
    Navigator.pop(
      context,
      DutyScheduleFormValue(
        academicYearId: _yearId!,
        day: _day!,
        teacherIds: _teacherIds.toList(),
        active: _active,
        notes: _notes.text,
      ),
    );
  }
}

class DutyAttendanceFormSheet extends StatefulWidget {
  const DutyAttendanceFormSheet({required this.student, super.key});
  final MyDutyStudent student;
  @override
  State<DutyAttendanceFormSheet> createState() =>
      _DutyAttendanceFormSheetState();
}

class _DutyAttendanceFormSheetState extends State<DutyAttendanceFormSheet> {
  late String _status;
  late final TextEditingController _notes;
  @override
  void initState() {
    super.initState();
    _status = widget.student.status == 'izin' ? 'izin' : 'sakit';
    _notes = TextEditingController(text: widget.student.notes);
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 16, 20, bottom + 20),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Catat Kehadiran',
              style: Theme.of(context).textTheme.titleLarge
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(
              '${widget.student.name} · ${widget.student.schoolClass}',
              style: const TextStyle(color: NusaColors.textSecondary),
            ),
            const SizedBox(height: 16),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(
                  value: 'sakit',
                  label: Text('Sakit'),
                  icon: Icon(Icons.medical_services_outlined),
                ),
                ButtonSegment(
                  value: 'izin',
                  label: Text('Izin'),
                  icon: Icon(Icons.assignment_turned_in_outlined),
                ),
              ],
              selected: {_status},
              onSelectionChanged: (value) =>
                  setState(() => _status = value.first),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _notes,
              minLines: 3,
              maxLines: 5,
              autofocus: true,
              decoration: const InputDecoration(
                labelText: 'Alasan / keterangan',
                hintText: 'Contoh: Demam, informasi dari orang tua',
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Keterangan wajib diisi dan akan disimpan sebagai riwayat perubahan presensi.',
              style: TextStyle(fontSize: 11, color: NusaColors.textSecondary),
            ),
            const SizedBox(height: 18),
            NusaPrimaryButton(label: 'Simpan Kehadiran', onPressed: _submit),
          ],
        ),
      ),
    );
  }

  void _submit() {
    if (_notes.text.trim().length < 3) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Keterangan minimal 3 karakter.')),
      );
      return;
    }
    Navigator.pop(context, (_status, _notes.text.trim()));
  }
}
