import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class CentralExamScheduleTab extends StatelessWidget {
  const CentralExamScheduleTab({
    required this.detail,
    required this.mutating,
    required this.onAdd,
    required this.onEdit,
    required this.onDelete,
    required this.onPackage,
    super.key,
  });

  final CentralExamPreparationDetail detail;
  final bool mutating;
  final VoidCallback? onAdd;
  final ValueChanged<CentralExamSchedule>? onEdit;
  final ValueChanged<CentralExamSchedule>? onDelete;
  final ValueChanged<CentralExamSchedule> onPackage;

  @override
  Widget build(BuildContext context) => ListView(
    key: const PageStorageKey('central-exam-schedules'),
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
    children: [
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Tahap 7 · Jadwal ujian',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
                SizedBox(height: 2),
                Text(
                  'Sesi dan kelas mengikuti pembagian peserta yang telah disiapkan.',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
              ],
            ),
          ),
          _CountPill(count: detail.scheduleStage.items.length),
        ],
      ),
      if (onAdd != null) ...[
        const SizedBox(height: 11),
        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            key: const Key('central-exam-schedule-add'),
            onPressed: mutating ? null : onAdd,
            icon: const Icon(Icons.add_rounded),
            label: const Text('Tambah Jadwal'),
          ),
        ),
      ],
      const SizedBox(height: 11),
      if (detail.scheduleStage.items.isEmpty)
        const _EmptySchedule()
      else
        for (final schedule in detail.scheduleStage.items)
          Padding(
            padding: const EdgeInsets.only(bottom: 9),
            child: _ScheduleCard(
              schedule: schedule,
              mutating: mutating,
              onEdit: onEdit == null ? null : () => onEdit!(schedule),
              onDelete: onDelete == null || !schedule.canDelete || mutating
                  ? null
                  : () => onDelete!(schedule),
              onPackage: () => onPackage(schedule),
            ),
          ),
      if (detail.scheduleStage.items.isNotEmpty) ...[
        const SizedBox(height: 3),
        const _NextStepNotice(),
      ],
    ],
  );
}

class CentralExamScheduleSheet extends StatefulWidget {
  const CentralExamScheduleSheet({
    required this.detail,
    this.existing,
    super.key,
  });

  final CentralExamPreparationDetail detail;
  final CentralExamSchedule? existing;

  @override
  State<CentralExamScheduleSheet> createState() =>
      _CentralExamScheduleSheetState();
}

class _CentralExamScheduleSheetState extends State<CentralExamScheduleSheet> {
  late DateTime _date;
  late int? _subjectId;
  late Set<int> _grades;
  late final TextEditingController _notes;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final event = widget.detail.event;
    _date = widget.existing?.date ?? event.startsOn ?? DateTime.now();
    _subjectId = widget.existing?.subjectId;
    _grades = widget.existing == null ? <int>{} : <int>{widget.existing!.grade};
    _notes = TextEditingController(text: widget.existing?.notes);
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final subjects = widget.detail.scheduleStage.subjects;
    final selectedSubject = subjects
        .where((item) => item.id == _subjectId)
        .firstOrNull;
    final readyGrades = widget.detail.participantStage.grades
        .where((item) => (item.assignment?.distributedCount ?? 0) > 0)
        .toList(growable: false);
    final subjectLocked =
        _editing && (widget.existing?.package?.questionCount ?? 0) > 0;

    return SafeArea(
      child: SizedBox(
        height: (MediaQuery.sizeOf(context).height * 0.84).clamp(510.0, 760.0),
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
                  const Icon(Icons.event_note_outlined),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      _editing ? 'Ubah Jadwal Ujian' : 'Tambah Jadwal Ujian',
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
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                padding: const EdgeInsets.all(16),
                children: [
                  _DateField(date: _date, onTap: _pickDate),
                  const SizedBox(height: 10),
                  NusaDropdownField<int>(
                    fieldKey: const Key('central-exam-schedule-subject'),
                    value: _subjectId,
                    enabled: !subjectLocked,
                    decoration: const InputDecoration(
                      labelText: 'Mata pelajaran',
                      hintText: 'Pilih mata pelajaran',
                      prefixIcon: Icon(Icons.menu_book_outlined),
                    ),
                    options: [
                      for (final item in subjects)
                        NusaDropdownOption(
                          value: item.id,
                          label: '${item.code} · ${item.name}',
                          enabled:
                              !_editing ||
                              item.grades.contains(widget.existing!.grade),
                        ),
                    ],
                    onChanged: subjectLocked
                        ? null
                        : (value) => setState(() {
                            _subjectId = value;
                            final available = subjects
                                .where((item) => item.id == value)
                                .firstOrNull
                                ?.grades;
                            if (available != null) {
                              _grades.removeWhere(
                                (grade) => !available.contains(grade),
                              );
                            }
                          }),
                  ),
                  if (subjectLocked) ...[
                    const SizedBox(height: 6),
                    const Text(
                      'Mata pelajaran dikunci karena paket sudah berisi soal.',
                      style: TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  const Text(
                    'Tingkat peserta',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    _editing ? 'Tingkat mengikuti jadwal yang sedang diubah.' : 'Satu mapel dapat dijadwalkan sekaligus untuk beberapa tingkat.',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                  const SizedBox(height: 5),
                  for (final grade in widget.detail.participantStage.grades)
                    Builder(
                      builder: (context) {
                        final assignment = grade.assignment;
                        final distributed =
                            (assignment?.distributedCount ?? 0) > 0;
                        final subjectAvailable =
                            selectedSubject?.grades.contains(grade.grade) ??
                            false;
                        final fixed =
                            _editing && widget.existing?.grade == grade.grade;
                        final enabled =
                            !_editing && distributed && subjectAvailable;
                        return CheckboxListTile(
                          key: Key(
                            'central-exam-schedule-grade-${grade.grade}',
                          ),
                          dense: true,
                          contentPadding: EdgeInsets.zero,
                          value: _grades.contains(grade.grade),
                          enabled: fixed || enabled,
                          title: Text('Tingkat ${grade.grade}'),
                          subtitle: Text(
                            !distributed
                                ? 'Selesaikan pembagian peserta tahap 6'
                                : !subjectAvailable
                                ? 'Mapel tidak diterapkan untuk tingkat ini'
                                : '${assignment!.sessionName} · ${assignment.distributedCount} peserta',
                          ),
                          onChanged: fixed
                              ? null
                              : enabled
                              ? (value) => setState(() {
                                  value == true
                                      ? _grades.add(grade.grade)
                                      : _grades.remove(grade.grade);
                                })
                              : null,
                        );
                      },
                    ),
                  if (readyGrades.isEmpty)
                    const _InlineNotice(
                      message: 'Belum ada tingkat yang siap. Bagi peserta pada tahap 6 terlebih dahulu.',
                    ),
                  const SizedBox(height: 10),
                  TextField(
                    key: const Key('central-exam-schedule-notes'),
                    controller: _notes,
                    maxLength: 500,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Catatan (opsional)',
                      hintText: 'Contoh: Hari pertama',
                      prefixIcon: Icon(Icons.notes_rounded),
                    ),
                  ),
                  if (_error != null)
                    Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontSize: 12,
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  key: const Key('central-exam-schedule-save'),
                  onPressed: _submit,
                  icon: const Icon(Icons.save_outlined),
                  label: Text(
                    _editing ? 'Simpan Perubahan' : 'Tambahkan Jadwal',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickDate() async {
    final first = widget.detail.event.startsOn ?? _date;
    final last = widget.detail.event.endsOn ?? first;
    final initial = _date.isBefore(first)
        ? first
        : _date.isAfter(last)
        ? last
        : _date;
    final value = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: first,
      lastDate: last,
      helpText: 'Pilih tanggal ujian',
    );
    if (value != null && mounted) setState(() => _date = value);
  }

  void _submit() {
    if (_subjectId == null) {
      setState(() => _error = 'Mata pelajaran wajib dipilih.');
      return;
    }
    if (_grades.isEmpty) {
      setState(() => _error = 'Pilih minimal satu tingkat peserta.');
      return;
    }
    Navigator.pop(
      context,
      CentralExamScheduleFormValue(
        date: _date,
        subjectId: _subjectId!,
        grades: _grades.toList(growable: false),
        notes: _notes.text.trim(),
      ),
    );
  }
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({
    required this.schedule,
    required this.mutating,
    required this.onEdit,
    required this.onDelete,
    required this.onPackage,
  });
  final CentralExamSchedule schedule;
  final bool mutating;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;
  final VoidCallback onPackage;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 46,
                padding: const EdgeInsets.symmetric(vertical: 7),
                decoration: BoxDecoration(
                  color: NusaColors.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Column(
                  children: [
                    Text(
                      _day(schedule.date),
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      _month(schedule.date),
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 8.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      schedule.subjectName,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Tingkat ${schedule.grade} · ${schedule.sessionName} · ${schedule.timeLabel}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
              _PackageStatus(schedule: schedule),
            ],
          ),
          const Divider(height: 20),
          _FactLine(
            icon: Icons.class_outlined,
            text: schedule.classNames.join(', '),
          ),
          _FactLine(
            icon: Icons.meeting_room_outlined,
            text:
                '${schedule.roomNames.join(', ')} · ${schedule.participantCount} peserta',
          ),
          if (schedule.notes?.trim().isNotEmpty == true)
            _FactLine(icon: Icons.notes_rounded, text: schedule.notes!),
          const SizedBox(height: 9),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onPackage,
                  icon: const Icon(Icons.inventory_2_outlined, size: 18),
                  label: const Text('Paket'),
                ),
              ),
              if (onEdit != null) ...[
                const SizedBox(width: 7),
                IconButton.outlined(
                  tooltip: 'Ubah jadwal',
                  onPressed: mutating ? null : onEdit,
                  icon: const Icon(Icons.edit_outlined),
                ),
              ],
              if (onDelete != null) ...[
                const SizedBox(width: 7),
                IconButton.outlined(
                  tooltip: 'Hapus jadwal',
                  onPressed: onDelete,
                  icon: const Icon(Icons.delete_outline_rounded),
                ),
              ],
            ],
          ),
        ],
      ),
    ),
  );
}

class _PackageStatus extends StatelessWidget {
  const _PackageStatus({required this.schedule});
  final CentralExamSchedule schedule;
  @override
  Widget build(BuildContext context) {
    final package = schedule.package;
    final ready = package != null;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
      decoration: BoxDecoration(
        color: (ready ? NusaColors.success : NusaColors.textSecondary)
            .withValues(alpha: 0.11),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        package == null ? 'Belum ada paket' : '${package.questionCount} soal',
        style: TextStyle(
          color: ready ? NusaColors.success : NusaColors.textSecondary,
          fontSize: 8.5,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _FactLine extends StatelessWidget {
  const _FactLine({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: NusaColors.primary),
        const SizedBox(width: 7),
        Expanded(child: Text(text, style: const TextStyle(fontSize: 10.5))),
      ],
    ),
  );
}

class _DateField extends StatelessWidget {
  const _DateField({required this.date, required this.onTap});
  final DateTime date;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
    key: const Key('central-exam-schedule-date'),
    borderRadius: BorderRadius.circular(14),
    onTap: onTap,
    child: InputDecorator(
      decoration: const InputDecoration(
        labelText: 'Tanggal ujian',
        prefixIcon: Icon(Icons.calendar_month_outlined),
        suffixIcon: Icon(Icons.arrow_drop_down_rounded),
      ),
      child: Text(_fullDate(date)),
    ),
  );
}

class _CountPill extends StatelessWidget {
  const _CountPill({required this.count});
  final int count;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: NusaColors.primary.withValues(alpha: 0.11),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      '$count jadwal',
      style: const TextStyle(
        color: NusaColors.primary,
        fontSize: 9,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _EmptySchedule extends StatelessWidget {
  const _EmptySchedule();
  @override
  Widget build(BuildContext context) => const Card(
    child: Padding(
      padding: EdgeInsets.all(24),
      child: Column(
        children: [
          Icon(
            Icons.event_busy_outlined,
            size: 42,
            color: NusaColors.textSecondary,
          ),
          SizedBox(height: 9),
          Text(
            'Belum ada jadwal. Selesaikan pembagian peserta, lalu tambahkan jadwal pertama.',
            textAlign: TextAlign.center,
          ),
        ],
      ),
    ),
  );
}

class _NextStepNotice extends StatelessWidget {
  const _NextStepNotice();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
    ),
    child: const Row(
      children: [
        Icon(Icons.arrow_forward_rounded, color: NusaColors.primary),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Jadwal yang sudah dibuat dapat dilanjutkan ke penyusunan Paket Soal.',
            style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _InlineNotice extends StatelessWidget {
  const _InlineNotice({required this.message});
  final String message;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: NusaColors.accent.withValues(alpha: 0.11),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(message, style: const TextStyle(fontSize: 10.5)),
  );
}

const _months = <String>[
  'JAN',
  'FEB',
  'MAR',
  'APR',
  'MEI',
  'JUN',
  'JUL',
  'AGU',
  'SEP',
  'OKT',
  'NOV',
  'DES',
];

String _day(DateTime? value) => value?.day.toString().padLeft(2, '0') ?? '--';
String _month(DateTime? value) =>
    value == null ? '---' : _months[value.month - 1];
String _fullDate(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
