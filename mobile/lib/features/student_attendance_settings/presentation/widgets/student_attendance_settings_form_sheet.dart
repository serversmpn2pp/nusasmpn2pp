import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_settings/domain/student_attendance_settings.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentAttendanceSettingsFormSheet extends StatefulWidget {
  const StudentAttendanceSettingsFormSheet({
    required this.days,
    this.existing,
    super.key,
  });

  final List<AttendanceDay> days;
  final StudentAttendanceSetting? existing;

  @override
  State<StudentAttendanceSettingsFormSheet> createState() =>
      _StudentAttendanceSettingsFormSheetState();
}

class _StudentAttendanceSettingsFormSheetState
    extends State<StudentAttendanceSettingsFormSheet> {
  late final TextEditingController _notesController;
  late String _day;
  late TimeOfDay _checkInScanStart;
  late TimeOfDay _checkInTime;
  late TimeOfDay _checkInScanEnd;
  late TimeOfDay _checkOutScanStart;
  late TimeOfDay _checkOutTime;
  late TimeOfDay _checkOutScanEnd;
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _notesController = TextEditingController(text: existing?.notes);
    _day =
        existing?.day ??
        widget.days.where((day) => !day.configured).firstOrNull?.code ??
        widget.days.firstOrNull?.code ??
        '';
    _checkInScanStart = _parseTime(existing?.checkInScanStart ?? '06:00');
    _checkInTime = _parseTime(existing?.checkInTime ?? '07:00');
    _checkInScanEnd = _parseTime(existing?.checkInScanEnd ?? '07:30');
    _checkOutScanStart = _parseTime(existing?.checkOutScanStart ?? '14:00');
    _checkOutTime = _parseTime(existing?.checkOutTime ?? '14:10');
    _checkOutScanEnd = _parseTime(existing?.checkOutScanEnd ?? '15:00');
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.93).clamp(520.0, 840.0),
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
                        ? 'Ubah Pengaturan Presensi'
                        : 'Tambah Pengaturan Presensi',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-student-attendance-setting-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('student-attendance-setting-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                NusaDropdownField<String>(
                  fieldKey: const Key('student-attendance-setting-day'),
                  value: _day,
                  decoration: const InputDecoration(
                    labelText: 'Hari presensi',
                    prefixIcon: Icon(Icons.calendar_today_outlined),
                  ),
                  options: [
                    for (final day in widget.days)
                      NusaDropdownOption(
                        value: day.code,
                        label:
                            day.configured && day.code != widget.existing?.day
                            ? '${day.label} · sudah diatur'
                            : day.label,
                        enabled:
                            !day.configured || day.code == widget.existing?.day,
                      ),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => _day = value);
                  },
                ),
                const SizedBox(height: 8),
                const Text(
                  'Satu hari hanya dapat memiliki satu pengaturan presensi.',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                const SizedBox(height: 16),
                _TimeSection(
                  title: 'Jam Masuk',
                  icon: Icons.login_rounded,
                  color: NusaColors.success,
                  fields: [
                    _TimeFieldData(
                      key: const Key('attendance-check-in-scan-start'),
                      label: 'Mulai scan masuk',
                      value: _checkInScanStart,
                      onTap: () => _pickTime(_TimeTarget.checkInScanStart),
                    ),
                    _TimeFieldData(
                      key: const Key('attendance-check-in-time'),
                      label: 'Jam masuk resmi',
                      value: _checkInTime,
                      onTap: () => _pickTime(_TimeTarget.checkInTime),
                    ),
                    _TimeFieldData(
                      key: const Key('attendance-check-in-scan-end'),
                      label: 'Tutup scan masuk',
                      value: _checkInScanEnd,
                      onTap: () => _pickTime(_TimeTarget.checkInScanEnd),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _TimeSection(
                  title: 'Jam Pulang',
                  icon: Icons.logout_rounded,
                  color: NusaColors.primary,
                  fields: [
                    _TimeFieldData(
                      key: const Key('attendance-check-out-scan-start'),
                      label: 'Mulai scan pulang',
                      value: _checkOutScanStart,
                      onTap: () => _pickTime(_TimeTarget.checkOutScanStart),
                    ),
                    _TimeFieldData(
                      key: const Key('attendance-check-out-time'),
                      label: 'Jam pulang resmi',
                      value: _checkOutTime,
                      onTap: () => _pickTime(_TimeTarget.checkOutTime),
                    ),
                    _TimeFieldData(
                      key: const Key('attendance-check-out-scan-end'),
                      label: 'Tutup scan pulang',
                      value: _checkOutScanEnd,
                      onTap: () => _pickTime(_TimeTarget.checkOutScanEnd),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                SwitchListTile.adaptive(
                  key: const Key('student-attendance-setting-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Jadwal aktif'),
                  subtitle: const Text(
                    'Mesin scanner memakai jadwal aktif untuk hari ini.',
                  ),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                const SizedBox(height: 4),
                TextField(
                  key: const Key('student-attendance-setting-notes'),
                  controller: _notesController,
                  minLines: 2,
                  maxLines: 4,
                  maxLength: 2000,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Keterangan (opsional)',
                    hintText: 'Contoh: Jumat pulang lebih awal',
                    prefixIcon: Icon(Icons.notes_rounded),
                    alignLabelWithHint: true,
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 5),
                  Text(
                    _error!,
                    key: const Key('student-attendance-setting-form-error'),
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
                key: const Key('save-student-attendance-setting'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Jadwal'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _pickTime(_TimeTarget target) async {
    final value = await showTimePicker(
      context: context,
      initialTime: _timeFor(target),
      helpText: _labelFor(target),
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(alwaysUse24HourFormat: true),
        child: child!,
      ),
    );
    if (value == null || !mounted) return;
    setState(() {
      switch (target) {
        case _TimeTarget.checkInScanStart:
          _checkInScanStart = value;
        case _TimeTarget.checkInTime:
          _checkInTime = value;
        case _TimeTarget.checkInScanEnd:
          _checkInScanEnd = value;
        case _TimeTarget.checkOutScanStart:
          _checkOutScanStart = value;
        case _TimeTarget.checkOutTime:
          _checkOutTime = value;
        case _TimeTarget.checkOutScanEnd:
          _checkOutScanEnd = value;
      }
    });
  }

  TimeOfDay _timeFor(_TimeTarget target) => switch (target) {
    _TimeTarget.checkInScanStart => _checkInScanStart,
    _TimeTarget.checkInTime => _checkInTime,
    _TimeTarget.checkInScanEnd => _checkInScanEnd,
    _TimeTarget.checkOutScanStart => _checkOutScanStart,
    _TimeTarget.checkOutTime => _checkOutTime,
    _TimeTarget.checkOutScanEnd => _checkOutScanEnd,
  };

  String _labelFor(_TimeTarget target) => switch (target) {
    _TimeTarget.checkInScanStart => 'Pilih waktu mulai scan masuk',
    _TimeTarget.checkInTime => 'Pilih jam masuk resmi',
    _TimeTarget.checkInScanEnd => 'Pilih waktu tutup scan masuk',
    _TimeTarget.checkOutScanStart => 'Pilih waktu mulai scan pulang',
    _TimeTarget.checkOutTime => 'Pilih jam pulang resmi',
    _TimeTarget.checkOutScanEnd => 'Pilih waktu tutup scan pulang',
  };

  void _submit() {
    if (_day.isEmpty) {
      setState(() => _error = 'Hari presensi wajib dipilih.');
      return;
    }
    if (!_ordered(_checkInScanStart, _checkInTime, _checkInScanEnd)) {
      setState(
        () => _error = 'Jam masuk resmi harus berada di antara mulai dan tutup scan masuk.',
      );
      return;
    }
    if (!_ordered(_checkOutScanStart, _checkOutTime, _checkOutScanEnd)) {
      setState(
        () => _error = 'Jam pulang resmi harus berada di antara mulai dan tutup scan pulang.',
      );
      return;
    }

    Navigator.pop(
      context,
      StudentAttendanceSettingsFormValue(
        day: _day,
        checkInScanStart: _formatTime(_checkInScanStart),
        checkInTime: _formatTime(_checkInTime),
        checkInScanEnd: _formatTime(_checkInScanEnd),
        checkOutScanStart: _formatTime(_checkOutScanStart),
        checkOutTime: _formatTime(_checkOutTime),
        checkOutScanEnd: _formatTime(_checkOutScanEnd),
        active: _active,
        notes: _notesController.text.trim().isEmpty
            ? null
            : _notesController.text.trim(),
      ),
    );
  }
}

class _TimeSection extends StatelessWidget {
  const _TimeSection({
    required this.title,
    required this.icon,
    required this.color,
    required this.fields,
  });

  final String title;
  final IconData icon;
  final Color color;
  final List<_TimeFieldData> fields;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.055),
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: color.withValues(alpha: 0.12)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 19, color: color),
            const SizedBox(width: 7),
            Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
          ],
        ),
        const SizedBox(height: 11),
        for (var index = 0; index < fields.length; index++) ...[
          _TimePickerField(data: fields[index]),
          if (index < fields.length - 1) const SizedBox(height: 9),
        ],
      ],
    ),
  );
}

class _TimePickerField extends StatelessWidget {
  const _TimePickerField({required this.data});

  final _TimeFieldData data;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      key: data.key,
      onTap: data.onTap,
      borderRadius: BorderRadius.circular(14),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: data.label,
          prefixIcon: const Icon(Icons.schedule_rounded),
          suffixIcon: const Icon(Icons.chevron_right_rounded),
        ),
        child: Text(
          _formatTime(data.value),
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
    ),
  );
}

class _TimeFieldData {
  const _TimeFieldData({
    required this.key,
    required this.label,
    required this.value,
    required this.onTap,
  });

  final Key key;
  final String label;
  final TimeOfDay value;
  final VoidCallback onTap;
}

enum _TimeTarget {
  checkInScanStart,
  checkInTime,
  checkInScanEnd,
  checkOutScanStart,
  checkOutTime,
  checkOutScanEnd,
}

bool _ordered(TimeOfDay start, TimeOfDay official, TimeOfDay end) =>
    _minutes(start) <= _minutes(official) &&
    _minutes(official) <= _minutes(end);

int _minutes(TimeOfDay value) => value.hour * 60 + value.minute;

TimeOfDay _parseTime(String value) {
  final parts = value.split(':');
  return TimeOfDay(
    hour: int.tryParse(parts.firstOrNull ?? '') ?? 0,
    minute: int.tryParse(parts.elementAtOrNull(1) ?? '') ?? 0,
  );
}

String _formatTime(TimeOfDay value) =>
    '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
