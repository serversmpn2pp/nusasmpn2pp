import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class CentralExamCommitteeSheet extends StatefulWidget {
  const CentralExamCommitteeSheet({
    required this.references,
    this.existing,
    super.key,
  });
  final CentralExamPreparationReferences references;
  final CentralExamCommitteeMember? existing;

  @override
  State<CentralExamCommitteeSheet> createState() =>
      _CentralExamCommitteeSheetState();
}

class _CentralExamCommitteeSheetState extends State<CentralExamCommitteeSheet> {
  late final TextEditingController _notes;
  int? _employeeId;
  String _position = 'anggota';
  String? _error;

  @override
  void initState() {
    super.initState();
    _employeeId = widget.existing?.employeeId;
    _position =
        widget.existing?.position ??
        widget.references.committeePositions.firstOrNull?.code ??
        'anggota';
    _notes = TextEditingController(text: widget.existing?.notes);
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _SheetFrame(
    title: widget.existing == null ? 'Tambahkan Panitia' : 'Ubah Tugas Panitia',
    icon: Icons.groups_rounded,
    saveKey: const Key('central-exam-committee-save'),
    onSave: _submit,
    child: Column(
      children: [
        NusaDropdownField<int>(
          fieldKey: const Key('central-exam-committee-employee'),
          value: _employeeId,
          decoration: const InputDecoration(
            labelText: 'Pegawai',
            hintText: 'Pilih pegawai',
            prefixIcon: Icon(Icons.person_outline_rounded),
          ),
          options: [
            for (final item in widget.references.employees)
              NusaDropdownOption(value: item.id, label: item.label),
          ],
          enabled: widget.existing == null,
          onChanged: widget.existing == null
              ? (value) => setState(() => _employeeId = value)
              : null,
        ),
        const SizedBox(height: 10),
        NusaDropdownField<String>(
          fieldKey: const Key('central-exam-committee-position'),
          value: _position,
          decoration: const InputDecoration(
            labelText: 'Tugas',
            prefixIcon: Icon(Icons.badge_outlined),
          ),
          options: [
            for (final item in widget.references.committeePositions)
              NusaDropdownOption(value: item.code, label: item.label),
          ],
          onChanged: (value) {
            if (value != null) setState(() => _position = value);
          },
        ),
        const SizedBox(height: 10),
        TextField(
          key: const Key('central-exam-committee-notes'),
          controller: _notes,
          minLines: 2,
          maxLines: 4,
          maxLength: 500,
          decoration: const InputDecoration(
            labelText: 'Catatan (opsional)',
            alignLabelWithHint: true,
            prefixIcon: Icon(Icons.notes_rounded),
          ),
        ),
        if (_error != null) _FormError(_error!),
      ],
    ),
  );

  void _submit() {
    if (_employeeId == null) {
      setState(() => _error = 'Pegawai wajib dipilih.');
      return;
    }
    Navigator.pop(
      context,
      CentralExamCommitteeFormValue(
        employeeId: _employeeId!,
        position: _position,
        notes: _notes.text.trim(),
      ),
    );
  }
}

class CentralExamSessionSheet extends StatefulWidget {
  const CentralExamSessionSheet({this.existing, super.key});
  final CentralExamSession? existing;

  @override
  State<CentralExamSessionSheet> createState() =>
      _CentralExamSessionSheetState();
}

class _CentralExamSessionSheetState extends State<CentralExamSessionSheet> {
  late final TextEditingController _name;
  late final TextEditingController _notes;
  late TimeOfDay _startsAt;
  late TimeOfDay _endsAt;
  late bool _active;
  String? _error;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _name = TextEditingController(text: existing?.name);
    _notes = TextEditingController(text: existing?.notes);
    _startsAt =
        _parseTime(existing?.startsAt) ?? const TimeOfDay(hour: 7, minute: 30);
    _endsAt =
        _parseTime(existing?.endsAt) ?? const TimeOfDay(hour: 9, minute: 30);
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _SheetFrame(
    title: widget.existing == null ? 'Tambah Sesi Ujian' : 'Ubah Sesi Ujian',
    icon: Icons.schedule_rounded,
    saveKey: const Key('central-exam-session-save'),
    onSave: _submit,
    child: Column(
      children: [
        TextField(
          key: const Key('central-exam-session-name'),
          controller: _name,
          maxLength: 100,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(
            labelText: 'Nama sesi',
            hintText: 'Contoh: Sesi Pagi',
            prefixIcon: Icon(Icons.label_outline_rounded),
          ),
        ),
        const SizedBox(height: 4),
        Row(
          children: [
            Expanded(
              child: _TimeField(
                fieldKey: const Key('central-exam-session-start'),
                label: 'Mulai',
                value: _startsAt,
                onTap: () => _pickTime(true),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _TimeField(
                fieldKey: const Key('central-exam-session-end'),
                label: 'Selesai',
                value: _endsAt,
                onTap: () => _pickTime(false),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        TextField(
          key: const Key('central-exam-session-notes'),
          controller: _notes,
          maxLength: 500,
          decoration: const InputDecoration(
            labelText: 'Catatan (opsional)',
            prefixIcon: Icon(Icons.notes_rounded),
          ),
        ),
        SwitchListTile.adaptive(
          key: const Key('central-exam-session-active'),
          contentPadding: EdgeInsets.zero,
          title: const Text('Sesi aktif'),
          subtitle: const Text('Sesi aktif dapat dipakai pada jadwal ujian.'),
          value: _active,
          onChanged: (value) => setState(() => _active = value),
        ),
        if (_error != null) _FormError(_error!),
      ],
    ),
  );

  Future<void> _pickTime(bool start) async {
    final value = await showTimePicker(
      context: context,
      initialTime: start ? _startsAt : _endsAt,
      helpText: start ? 'Pilih waktu mulai' : 'Pilih waktu selesai',
    );
    if (value == null || !mounted) return;
    setState(() => start ? _startsAt = value : _endsAt = value);
  }

  void _submit() {
    if (_name.text.trim().isEmpty) {
      setState(() => _error = 'Nama sesi wajib diisi.');
      return;
    }
    if (_minutes(_endsAt) <= _minutes(_startsAt)) {
      setState(() => _error = 'Waktu selesai harus setelah waktu mulai.');
      return;
    }
    Navigator.pop(
      context,
      CentralExamSessionFormValue(
        name: _name.text.trim(),
        startsAt: _time(_startsAt),
        endsAt: _time(_endsAt),
        active: _active,
        notes: _notes.text.trim(),
      ),
    );
  }
}

class CentralExamRoomSheet extends StatefulWidget {
  const CentralExamRoomSheet({this.existing, super.key});
  final CentralExamRoom? existing;

  @override
  State<CentralExamRoomSheet> createState() => _CentralExamRoomSheetState();
}

class _CentralExamRoomSheetState extends State<CentralExamRoomSheet> {
  late final TextEditingController _name;
  late final TextEditingController _location;
  late final TextEditingController _capacity;
  late final TextEditingController _notes;
  late bool _active;
  String? _error;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _name = TextEditingController(text: existing?.name);
    _location = TextEditingController(text: existing?.location);
    _capacity = TextEditingController(text: '${existing?.capacity ?? 20}');
    _notes = TextEditingController(text: existing?.notes);
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _location.dispose();
    _capacity.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _SheetFrame(
    title: widget.existing == null ? 'Tambah Ruang Ujian' : 'Ubah Ruang Ujian',
    icon: Icons.meeting_room_rounded,
    saveKey: const Key('central-exam-room-save'),
    onSave: _submit,
    child: Column(
      children: [
        TextField(
          key: const Key('central-exam-room-name'),
          controller: _name,
          maxLength: 100,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(
            labelText: 'Nama ruang',
            hintText: 'Contoh: Ruang 1',
            prefixIcon: Icon(Icons.meeting_room_outlined),
          ),
        ),
        TextField(
          key: const Key('central-exam-room-location'),
          controller: _location,
          maxLength: 180,
          decoration: const InputDecoration(
            labelText: 'Lokasi (opsional)',
            hintText: 'Contoh: Kelas VII.A',
            prefixIcon: Icon(Icons.place_outlined),
          ),
        ),
        TextField(
          key: const Key('central-exam-room-capacity'),
          controller: _capacity,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: const InputDecoration(
            labelText: 'Kapasitas siswa',
            prefixIcon: Icon(Icons.event_seat_outlined),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          key: const Key('central-exam-room-notes'),
          controller: _notes,
          maxLength: 500,
          decoration: const InputDecoration(
            labelText: 'Catatan (opsional)',
            prefixIcon: Icon(Icons.notes_rounded),
          ),
        ),
        SwitchListTile.adaptive(
          key: const Key('central-exam-room-active'),
          contentPadding: EdgeInsets.zero,
          title: const Text('Ruang aktif'),
          subtitle: const Text(
            'Ruang aktif dapat dipakai untuk peserta ujian.',
          ),
          value: _active,
          onChanged: (value) => setState(() => _active = value),
        ),
        if (_error != null) _FormError(_error!),
      ],
    ),
  );

  void _submit() {
    final capacity = int.tryParse(_capacity.text);
    if (_name.text.trim().isEmpty) {
      setState(() => _error = 'Nama ruang wajib diisi.');
      return;
    }
    if (capacity == null || capacity < 1 || capacity > 100) {
      setState(() => _error = 'Kapasitas harus antara 1 sampai 100 siswa.');
      return;
    }
    Navigator.pop(
      context,
      CentralExamRoomFormValue(
        name: _name.text.trim(),
        location: _location.text.trim(),
        capacity: capacity,
        active: _active,
        notes: _notes.text.trim(),
      ),
    );
  }
}

class _SheetFrame extends StatelessWidget {
  const _SheetFrame({
    required this.title,
    required this.icon,
    required this.saveKey,
    required this.onSave,
    required this.child,
  });
  final String title;
  final IconData icon;
  final Key saveKey;
  final VoidCallback onSave;
  final Widget child;

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.78).clamp(480.0, 720.0),
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
            padding: const EdgeInsets.fromLTRB(16, 12, 8, 9),
            child: Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: NusaColors.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: NusaColors.primary, size: 21),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: const EdgeInsets.all(16),
              children: [child],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: saveKey,
                onPressed: onSave,
                icon: const Icon(Icons.save_outlined),
                label: const Text('Simpan'),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _TimeField extends StatelessWidget {
  const _TimeField({
    required this.fieldKey,
    required this.label,
    required this.value,
    required this.onTap,
  });
  final Key fieldKey;
  final String label;
  final TimeOfDay value;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
    key: fieldKey,
    borderRadius: BorderRadius.circular(14),
    onTap: onTap,
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: const Icon(Icons.schedule_outlined),
      ),
      child: Text(_time(value)),
    ),
  );
}

class _FormError extends StatelessWidget {
  const _FormError(this.message);
  final String message;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 8),
    child: Align(
      alignment: Alignment.centerLeft,
      child: Text(
        message,
        style: TextStyle(
          color: Theme.of(context).colorScheme.error,
          fontSize: 12,
        ),
      ),
    ),
  );
}

TimeOfDay? _parseTime(String? value) {
  final parts = value?.split(':');
  if (parts == null || parts.length < 2) return null;
  final hour = int.tryParse(parts[0]);
  final minute = int.tryParse(parts[1]);
  return hour == null || minute == null
      ? null
      : TimeOfDay(hour: hour, minute: minute);
}

int _minutes(TimeOfDay value) => value.hour * 60 + value.minute;
String _time(TimeOfDay value) =>
    '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
