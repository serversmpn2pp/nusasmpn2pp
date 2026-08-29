import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_attendance_settings/domain/employee_attendance_settings.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class EmployeeAttendanceSettingsFormSheet extends StatefulWidget {
  const EmployeeAttendanceSettingsFormSheet({
    required this.days,
    required this.scopes,
    required this.employeeTypes,
    required this.employees,
    this.existing,
    super.key,
  });

  final List<AttendanceReferenceOption> days;
  final List<AttendanceReferenceOption> scopes;
  final List<String> employeeTypes;
  final List<AttendanceEmployeeReference> employees;
  final EmployeeAttendanceSetting? existing;

  @override
  State<EmployeeAttendanceSettingsFormSheet> createState() =>
      _EmployeeAttendanceSettingsFormSheetState();
}

class _EmployeeAttendanceSettingsFormSheetState
    extends State<EmployeeAttendanceSettingsFormSheet> {
  late final TextEditingController _nameController;
  late final TextEditingController _notesController;
  late String _scope;
  late String _day;
  String? _employeeType;
  int? _employeeId;
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
    _nameController = TextEditingController(text: existing?.name);
    _notesController = TextEditingController(text: existing?.notes);
    _scope = existing?.scope ?? widget.scopes.firstOrNull?.code ?? 'semua';
    _day = existing?.day ?? widget.days.firstOrNull?.code ?? '';
    _employeeType = existing?.employeeType ?? widget.employeeTypes.firstOrNull;
    _employeeId = existing?.employeeId ?? widget.employees.firstOrNull?.id;
    _checkInScanStart = _parseTime(existing?.checkInScanStart ?? '06:30');
    _checkInTime = _parseTime(existing?.checkInTime ?? '07:15');
    _checkInScanEnd = _parseTime(existing?.checkInScanEnd ?? '08:00');
    _checkOutScanStart = _parseTime(existing?.checkOutScanStart ?? '14:00');
    _checkOutTime = _parseTime(existing?.checkOutTime ?? '14:15');
    _checkOutScanEnd = _parseTime(existing?.checkOutScanEnd ?? '16:00');
    _active = existing?.active ?? true;
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
      height: (MediaQuery.sizeOf(context).height * 0.94).clamp(540.0, 860.0),
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
            padding: const EdgeInsets.fromLTRB(16, 13, 8, 9),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _editing
                        ? 'Ubah Jadwal Presensi Pegawai'
                        : 'Tambah Jadwal Presensi Pegawai',
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-employee-attendance-setting-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('employee-attendance-setting-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  key: const Key('employee-attendance-setting-name'),
                  controller: _nameController,
                  textCapitalization: TextCapitalization.sentences,
                  maxLength: 120,
                  decoration: const InputDecoration(
                    labelText: 'Nama jadwal',
                    hintText: 'Contoh: Jadwal guru Senin',
                    prefixIcon: Icon(Icons.badge_outlined),
                  ),
                ),
                const SizedBox(height: 8),
                NusaDropdownField<String>(
                  fieldKey: const Key('employee-attendance-setting-scope'),
                  value: _scope,
                  decoration: const InputDecoration(
                    labelText: 'Cakupan',
                    prefixIcon: Icon(Icons.groups_2_outlined),
                  ),
                  options: [
                    for (final scope in widget.scopes)
                      NusaDropdownOption(value: scope.code, label: scope.label),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => _scope = value);
                  },
                ),
                if (_scope == 'jenis_pegawai') ...[
                  const SizedBox(height: 10),
                  NusaDropdownField<String>(
                    fieldKey: const Key(
                      'employee-attendance-setting-employee-type',
                    ),
                    value: _employeeType,
                    decoration: const InputDecoration(
                      labelText: 'Jenis pegawai',
                      prefixIcon: Icon(Icons.work_outline_rounded),
                    ),
                    options: [
                      for (final type in widget.employeeTypes)
                        NusaDropdownOption(value: type, label: type),
                    ],
                    onChanged: (value) => setState(() => _employeeType = value),
                  ),
                ],
                if (_scope == 'pegawai') ...[
                  const SizedBox(height: 10),
                  NusaDropdownField<int>(
                    fieldKey: const Key('employee-attendance-setting-employee'),
                    value: _employeeId,
                    decoration: const InputDecoration(
                      labelText: 'Pegawai tertentu',
                      prefixIcon: Icon(Icons.person_outline_rounded),
                    ),
                    options: [
                      for (final employee in widget.employees)
                        NusaDropdownOption(
                          value: employee.id,
                          label: employee.label,
                        ),
                    ],
                    menuMaxHeight: 330,
                    onChanged: (value) => setState(() => _employeeId = value),
                  ),
                ],
                const SizedBox(height: 10),
                NusaDropdownField<String>(
                  fieldKey: const Key('employee-attendance-setting-day'),
                  value: _day,
                  decoration: const InputDecoration(
                    labelText: 'Hari presensi',
                    prefixIcon: Icon(Icons.calendar_today_outlined),
                  ),
                  options: [
                    for (final day in widget.days)
                      NusaDropdownOption(value: day.code, label: day.label),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => _day = value);
                  },
                ),
                const SizedBox(height: 8),
                const Text(
                  'Prioritas jadwal: pegawai tertentu, jenis pegawai, lalu semua pegawai.',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 15),
                _TimeSection(
                  title: 'Jam Masuk',
                  icon: Icons.login_rounded,
                  color: NusaColors.success,
                  fields: [
                    _field(
                      const Key('employee-check-in-scan-start'),
                      'Mulai scan masuk',
                      _TimeTarget.checkInScanStart,
                    ),
                    _field(
                      const Key('employee-check-in-time'),
                      'Jam masuk resmi',
                      _TimeTarget.checkInTime,
                    ),
                    _field(
                      const Key('employee-check-in-scan-end'),
                      'Tutup scan masuk',
                      _TimeTarget.checkInScanEnd,
                    ),
                  ],
                ),
                const SizedBox(height: 13),
                _TimeSection(
                  title: 'Jam Pulang',
                  icon: Icons.logout_rounded,
                  color: NusaColors.primary,
                  fields: [
                    _field(
                      const Key('employee-check-out-scan-start'),
                      'Mulai scan pulang',
                      _TimeTarget.checkOutScanStart,
                    ),
                    _field(
                      const Key('employee-check-out-time'),
                      'Jam pulang resmi',
                      _TimeTarget.checkOutTime,
                    ),
                    _field(
                      const Key('employee-check-out-scan-end'),
                      'Tutup scan pulang',
                      _TimeTarget.checkOutScanEnd,
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                SwitchListTile.adaptive(
                  key: const Key('employee-attendance-setting-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Jadwal aktif'),
                  subtitle: const Text(
                    'Mesin scanner hanya memakai jadwal yang aktif.',
                  ),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                const SizedBox(height: 3),
                TextField(
                  key: const Key('employee-attendance-setting-notes'),
                  controller: _notesController,
                  minLines: 2,
                  maxLines: 4,
                  maxLength: 2000,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Keterangan (opsional)',
                    hintText: 'Contoh: Jadwal khusus guru piket',
                    prefixIcon: Icon(Icons.notes_rounded),
                    alignLabelWithHint: true,
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    _error!,
                    key: const Key('employee-attendance-setting-form-error'),
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
                key: const Key('save-employee-attendance-setting'),
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

  _TimeFieldData _field(Key key, String label, _TimeTarget target) =>
      _TimeFieldData(
        key: key,
        label: label,
        value: _timeFor(target),
        onTap: () => _pickTime(target),
      );

  Future<void> _pickTime(_TimeTarget target) async {
    final value = await showTimePicker(
      context: context,
      initialTime: _timeFor(target),
      helpText: 'Pilih ${_labelFor(target)}',
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
    _TimeTarget.checkInScanStart => 'waktu mulai scan masuk',
    _TimeTarget.checkInTime => 'jam masuk resmi',
    _TimeTarget.checkInScanEnd => 'waktu tutup scan masuk',
    _TimeTarget.checkOutScanStart => 'waktu mulai scan pulang',
    _TimeTarget.checkOutTime => 'jam pulang resmi',
    _TimeTarget.checkOutScanEnd => 'waktu tutup scan pulang',
  };

  void _submit() {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Nama jadwal wajib diisi.');
      return;
    }
    if (_day.isEmpty) {
      setState(() => _error = 'Hari presensi wajib dipilih.');
      return;
    }
    if (_scope == 'jenis_pegawai' && (_employeeType?.trim().isEmpty ?? true)) {
      setState(() => _error = 'Jenis pegawai wajib dipilih.');
      return;
    }
    if (_scope == 'pegawai' && _employeeId == null) {
      setState(() => _error = 'Pegawai wajib dipilih.');
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
      EmployeeAttendanceSettingsFormValue(
        name: name,
        scope: _scope,
        employeeType: _scope == 'jenis_pegawai' ? _employeeType : null,
        employeeId: _scope == 'pegawai' ? _employeeId : null,
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
        const SizedBox(height: 10),
        for (var index = 0; index < fields.length; index++) ...[
          _TimePickerField(data: fields[index]),
          if (index < fields.length - 1) const SizedBox(height: 8),
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
