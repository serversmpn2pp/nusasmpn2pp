import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_schedule/domain/worship_schedule.dart';

class WorshipScheduleFormSheet extends StatefulWidget {
  const WorshipScheduleFormSheet({
    required this.page,
    this.initialDay,
    this.existing,
    super.key,
  });

  final WorshipSchedulePage page;
  final WorshipDay? initialDay;
  final WorshipSchedule? existing;

  @override
  State<WorshipScheduleFormSheet> createState() =>
      _WorshipScheduleFormSheetState();
}

class _WorshipScheduleFormSheetState extends State<WorshipScheduleFormSheet> {
  late final TextEditingController _scanStartController;
  late final TextEditingController _eventTimeController;
  late final TextEditingController _scanEndController;
  late final TextEditingController _notesController;
  late final Set<String> _selectedDays;
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _scanStartController = TextEditingController(
      text: existing?.scanStart ?? '11:45',
    );
    _eventTimeController = TextEditingController(
      text: existing?.eventTime ?? '12:15',
    );
    _scanEndController = TextEditingController(
      text: existing?.scanEnd ?? '13:15',
    );
    _notesController = TextEditingController(text: existing?.notes);
    _selectedDays = {
      if (existing != null) existing.day,
      if (existing == null && widget.initialDay != null)
        widget.initialDay!.code,
    };
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _scanStartController.dispose();
    _eventTimeController.dispose();
    _scanEndController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final activity = widget.page.selectedActivity;
    final academicYear = widget.page.selectedAcademicYear;

    return AnimatedPadding(
      duration: const Duration(milliseconds: 160),
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: (MediaQuery.sizeOf(context).height * 0.88).clamp(540.0, 820.0),
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
                          ? 'Ubah Jadwal ${widget.existing!.dayLabel}'
                          : 'Atur Jadwal Ibadah',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    key: const Key('close-worship-schedule-form'),
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                key: const Key('worship-schedule-form-scroll'),
                padding: const EdgeInsets.all(16),
                children: [
                  _ReferenceCard(
                    activity: activity?.name ?? '-',
                    academicYear: academicYear?.name ?? '-',
                  ),
                  const SizedBox(height: 16),
                  Text(
                    _editing ? 'Hari pelaksanaan' : 'Pilih hari pelaksanaan',
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  if (_editing)
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Chip(
                        avatar: const Icon(Icons.today_rounded, size: 17),
                        label: Text(widget.existing!.dayLabel),
                      ),
                    )
                  else
                    Wrap(
                      spacing: 7,
                      runSpacing: 7,
                      children: widget.page.days
                          .map(
                            (day) => FilterChip(
                              key: Key('worship-schedule-day-${day.code}'),
                              label: Text(day.label),
                              selected: _selectedDays.contains(day.code),
                              onSelected: (selected) => setState(() {
                                if (selected) {
                                  _selectedDays.add(day.code);
                                } else {
                                  _selectedDays.remove(day.code);
                                }
                              }),
                            ),
                          )
                          .toList(growable: false),
                    ),
                  if (!_editing) ...[
                    const SizedBox(height: 5),
                    const Text(
                      'Jadwal yang sudah ada pada hari terpilih akan diperbarui.',
                      style: TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  const Text(
                    'Waktu kegiatan',
                    style: TextStyle(
                      color: NusaColors.textPrimary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 9),
                  _TimeField(
                    fieldKey: const Key('worship-schedule-form-scan-start'),
                    controller: _scanStartController,
                    label: 'Mulai scan',
                    icon: Icons.qr_code_scanner_rounded,
                  ),
                  const SizedBox(height: 10),
                  _TimeField(
                    fieldKey: const Key('worship-schedule-form-event-time'),
                    controller: _eventTimeController,
                    label: 'Waktu pelaksanaan',
                    icon: Icons.schedule_rounded,
                  ),
                  const SizedBox(height: 10),
                  _TimeField(
                    fieldKey: const Key('worship-schedule-form-scan-end'),
                    controller: _scanEndController,
                    label: 'Batas akhir scan',
                    icon: Icons.timer_off_outlined,
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('worship-schedule-form-notes'),
                    controller: _notesController,
                    minLines: 2,
                    maxLines: 4,
                    maxLength: 1000,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: const InputDecoration(
                      labelText: 'Keterangan (opsional)',
                      hintText: 'Contoh: Mushalla sekolah',
                      alignLabelWithHint: true,
                      prefixIcon: Icon(Icons.place_outlined),
                    ),
                  ),
                  SwitchListTile.adaptive(
                    key: const Key('worship-schedule-form-active'),
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Jadwal aktif'),
                    subtitle: const Text(
                      'Jadwal aktif digunakan untuk menerima hasil scanner.',
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
                  key: const Key('save-worship-schedule'),
                  onPressed: _submit,
                  icon: const Icon(Icons.save_outlined),
                  label: Text(
                    _editing ? 'Simpan Perubahan' : 'Terapkan Jadwal',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _submit() {
    if (_selectedDays.isEmpty) {
      setState(() => _error = 'Pilih minimal satu hari pelaksanaan.');
      return;
    }
    final scanStart = _scanStartController.text.trim();
    final eventTime = _eventTimeController.text.trim();
    final scanEnd = _scanEndController.text.trim();
    final startMinutes = _timeToMinutes(scanStart);
    final eventMinutes = _timeToMinutes(eventTime);
    final endMinutes = _timeToMinutes(scanEnd);
    if (startMinutes == null || eventMinutes == null || endMinutes == null) {
      setState(() => _error = 'Gunakan format waktu HH:mm, misalnya 11:45.');
      return;
    }
    if (startMinutes > eventMinutes || eventMinutes > endMinutes) {
      setState(
        () => _error = 'Waktu pelaksanaan harus berada di antara mulai dan batas akhir scan.',
      );
      return;
    }

    Navigator.pop(
      context,
      WorshipScheduleFormValue(
        activityId: widget.page.selectedActivityId,
        academicYearId: widget.page.selectedAcademicYearId,
        days: _selectedDays.toList(growable: false),
        scanStart: scanStart,
        eventTime: eventTime,
        scanEnd: scanEnd,
        active: _active,
        notes: _notesController.text.trim(),
      ),
    );
  }
}

class _ReferenceCard extends StatelessWidget {
  const _ReferenceCard({required this.activity, required this.academicYear});

  final String activity;
  final String academicYear;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.primary.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      children: [
        const Icon(Icons.self_improvement_rounded, color: NusaColors.primary),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                activity,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 2),
              Text(
                academicYear,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _TimeField extends StatelessWidget {
  const _TimeField({
    required this.fieldKey,
    required this.controller,
    required this.label,
    required this.icon,
  });

  final Key fieldKey;
  final TextEditingController controller;
  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) => TextField(
    key: fieldKey,
    controller: controller,
    keyboardType: TextInputType.datetime,
    maxLength: 5,
    inputFormatters: [FilteringTextInputFormatter.allow(RegExp('[0-9:]'))],
    decoration: InputDecoration(
      labelText: label,
      hintText: 'HH:mm',
      counterText: '',
      prefixIcon: Icon(icon),
    ),
  );
}

int? _timeToMinutes(String value) {
  final match = RegExp(r'^(\d{2}):(\d{2})$').firstMatch(value);
  if (match == null) return null;
  final hour = int.parse(match.group(1)!);
  final minute = int.parse(match.group(2)!);
  if (hour > 23 || minute > 59) return null;
  return (hour * 60) + minute;
}
