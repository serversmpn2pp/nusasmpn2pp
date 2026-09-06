import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class CentralExamRoomAssignmentTab extends StatelessWidget {
  const CentralExamRoomAssignmentTab({
    required this.detail,
    required this.mutating,
    required this.onConfigure,
    super.key,
  });

  final CentralExamPreparationDetail detail;
  final bool mutating;
  final ValueChanged<CentralExamGradePreparation>? onConfigure;

  @override
  Widget build(BuildContext context) => ListView(
    key: const PageStorageKey('central-exam-room-assignment'),
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
    children: [
      const _StageHeading(
        title: 'Tahap 5 · Penetapan ruang',
        subtitle: 'Pilih kelas, sesi, dan ruang untuk setiap tingkat sebelum peserta dibagi.',
      ),
      const SizedBox(height: 11),
      if (detail.sessions.where((item) => item.active).isEmpty ||
          detail.rooms.where((item) => item.active).isEmpty)
        const _NoticeCard(
          icon: Icons.info_outline_rounded,
          message: 'Siapkan minimal satu sesi aktif dan satu ruang aktif pada tahap sebelumnya.',
        ),
      for (final grade in detail.participantStage.grades) ...[
        const SizedBox(height: 9),
        _AssignmentCard(
          detail: detail,
          grade: grade,
          onConfigure: onConfigure == null || mutating
              ? null
              : () => onConfigure!(grade),
        ),
      ],
    ],
  );
}

class CentralExamParticipantDistributionTab extends StatelessWidget {
  const CentralExamParticipantDistributionTab({
    required this.detail,
    required this.mutating,
    required this.onGenerate,
    required this.onDelete,
    required this.onView,
    super.key,
  });

  final CentralExamPreparationDetail detail;
  final bool mutating;
  final ValueChanged<CentralExamGradePreparation>? onGenerate;
  final ValueChanged<CentralExamGradePreparation>? onDelete;
  final ValueChanged<CentralExamGradePreparation> onView;

  @override
  Widget build(BuildContext context) {
    final configured = detail.participantStage.grades
        .where((item) => item.assignment != null)
        .toList(growable: false);
    return ListView(
      key: const PageStorageKey('central-exam-participant-distribution'),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      children: [
        const _StageHeading(
          title: 'Tahap 6 · Pembagian peserta',
          subtitle: 'Susun siswa otomatis berdasarkan urutan kelas dan nama, lalu tempatkan sesuai kapasitas ruang.',
        ),
        const SizedBox(height: 11),
        if (configured.isEmpty)
          const _NoticeCard(
            icon: Icons.groups_outlined,
            message:
                'Belum ada penetapan ruang. Lengkapi tahap 5 terlebih dahulu.',
          )
        else
          for (final grade in configured)
            Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: _DistributionCard(
                detail: detail,
                grade: grade,
                mutating: mutating,
                onGenerate: onGenerate == null
                    ? null
                    : () => onGenerate!(grade),
                onDelete:
                    onDelete == null || grade.assignment?.canDelete != true
                    ? null
                    : () => onDelete!(grade),
                onView: grade.assignment!.distributedCount > 0
                    ? () => onView(grade)
                    : null,
              ),
            ),
      ],
    );
  }
}

class CentralExamRoomAssignmentSheet extends StatefulWidget {
  const CentralExamRoomAssignmentSheet({
    required this.detail,
    required this.grade,
    super.key,
  });

  final CentralExamPreparationDetail detail;
  final CentralExamGradePreparation grade;

  @override
  State<CentralExamRoomAssignmentSheet> createState() =>
      _CentralExamRoomAssignmentSheetState();
}

class _CentralExamRoomAssignmentSheetState
    extends State<CentralExamRoomAssignmentSheet> {
  late int? _sessionId;
  late Set<int> _classIds;
  late Set<int> _roomIds;
  String? _error;

  @override
  void initState() {
    super.initState();
    final assignment = widget.grade.assignment;
    final activeSessions = widget.detail.sessions
        .where((item) => item.active)
        .toList(growable: false);
    _sessionId = assignment?.sessionId ?? activeSessions.firstOrNull?.id;
    _classIds =
        assignment?.classIds.toSet() ??
        widget.grade.classes.map((item) => item.id).toSet();
    _roomIds = assignment?.roomIds.toSet() ?? <int>{};
  }

  @override
  Widget build(BuildContext context) {
    final activeSessions = widget.detail.sessions
        .where((item) => item.active)
        .toList(growable: false);
    final activeRooms = widget.detail.rooms
        .where((item) => item.active)
        .toList(growable: false);
    final studentCount = widget.grade.classes
        .where((item) => _classIds.contains(item.id))
        .fold(0, (sum, item) => sum + item.activeStudentCount);
    final capacity = activeRooms
        .where((item) => _roomIds.contains(item.id))
        .fold(0, (sum, item) => sum + item.capacity);
    final enough = capacity >= studentCount && studentCount > 0;

    return SafeArea(
      child: SizedBox(
        height: (MediaQuery.sizeOf(context).height * 0.86).clamp(520.0, 790.0),
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
                  const Icon(Icons.account_tree_outlined),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Penetapan Tingkat ${widget.grade.grade}',
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
                padding: const EdgeInsets.all(16),
                children: [
                  NusaDropdownField<int>(
                    fieldKey: const Key('central-exam-assignment-session'),
                    value: _sessionId,
                    decoration: const InputDecoration(
                      labelText: 'Sesi ujian',
                      prefixIcon: Icon(Icons.schedule_outlined),
                    ),
                    options: [
                      for (final item in activeSessions)
                        NusaDropdownOption(
                          value: item.id,
                          label: '${item.name} · ${item.timeLabel}',
                        ),
                    ],
                    onChanged: (value) => setState(() {
                      _sessionId = value;
                      _roomIds.removeWhere(_roomConflicts);
                    }),
                  ),
                  const SizedBox(height: 14),
                  _SelectionHeading(
                    title: 'Kelas peserta',
                    value: '$studentCount siswa dipilih',
                  ),
                  const SizedBox(height: 6),
                  for (final item in widget.grade.classes)
                    CheckboxListTile(
                      key: Key('central-exam-assignment-class-${item.id}'),
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      value: _classIds.contains(item.id),
                      title: Text(item.name),
                      subtitle: Text('${item.activeStudentCount} siswa aktif'),
                      onChanged: (value) => setState(() {
                        value == true
                            ? _classIds.add(item.id)
                            : _classIds.remove(item.id);
                      }),
                    ),
                  const Divider(height: 22),
                  _SelectionHeading(
                    title: 'Ruang ujian',
                    value: '$capacity kursi dipilih',
                  ),
                  const SizedBox(height: 6),
                  for (final item in activeRooms)
                    Builder(
                      builder: (context) {
                        final conflict = _roomConflicts(item.id);
                        return CheckboxListTile(
                          key: Key('central-exam-assignment-room-${item.id}'),
                          dense: true,
                          contentPadding: EdgeInsets.zero,
                          value: _roomIds.contains(item.id),
                          title: Text('${item.code} · ${item.name}'),
                          subtitle: Text(
                            conflict
                                ? 'Sudah dipakai tingkat lain pada sesi ini'
                                : 'Kapasitas ${item.capacity} siswa',
                          ),
                          enabled: !conflict,
                          onChanged: conflict
                              ? null
                              : (value) => setState(() {
                                  value == true
                                      ? _roomIds.add(item.id)
                                      : _roomIds.remove(item.id);
                                }),
                        );
                      },
                    ),
                  const SizedBox(height: 10),
                  _CapacitySummary(
                    studentCount: studentCount,
                    capacity: capacity,
                    enough: enough,
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
                  key: const Key('central-exam-assignment-save'),
                  onPressed: _submit,
                  icon: const Icon(Icons.save_outlined),
                  label: const Text('Simpan Penetapan'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _roomConflicts(int roomId) =>
      widget.detail.participantStage.roomUsages.any(
        (usage) =>
            usage.roomId == roomId &&
            usage.sessionId == _sessionId &&
            usage.grade != widget.grade.grade,
      );

  void _submit() {
    if (_sessionId == null) {
      setState(() => _error = 'Sesi ujian wajib dipilih.');
      return;
    }
    if (_classIds.isEmpty) {
      setState(() => _error = 'Pilih minimal satu kelas.');
      return;
    }
    if (_roomIds.isEmpty) {
      setState(() => _error = 'Pilih minimal satu ruang.');
      return;
    }
    Navigator.pop(
      context,
      CentralExamRoomAssignmentFormValue(
        grade: widget.grade.grade,
        sessionId: _sessionId!,
        classIds: _classIds.toList(growable: false),
        roomIds: _roomIds.toList(growable: false),
      ),
    );
  }
}

class _AssignmentCard extends StatelessWidget {
  const _AssignmentCard({
    required this.detail,
    required this.grade,
    required this.onConfigure,
  });

  final CentralExamPreparationDetail detail;
  final CentralExamGradePreparation grade;
  final VoidCallback? onConfigure;

  @override
  Widget build(BuildContext context) {
    final assignment = grade.assignment;
    final classNames = grade.classes
        .where((item) => assignment?.classIds.contains(item.id) == true)
        .map((item) => item.name)
        .join(', ');
    final roomNames = detail.rooms
        .where((item) => assignment?.roomIds.contains(item.id) == true)
        .map((item) => item.code)
        .join(', ');
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: NusaColors.primary.withValues(alpha: 0.1),
                  child: Text(
                    '${grade.grade}',
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Tingkat ${grade.grade}',
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        '${grade.classes.length} kelas · ${grade.activeStudentCount} siswa aktif',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                    ],
                  ),
                ),
                _MiniStatus(ready: assignment != null),
              ],
            ),
            if (assignment != null) ...[
              const Divider(height: 20),
              _CompactFact(
                icon: Icons.schedule_outlined,
                text: '${assignment.sessionName} · ${assignment.timeLabel}',
              ),
              _CompactFact(
                icon: Icons.class_outlined,
                text: classNames.isEmpty ? '-' : classNames,
              ),
              _CompactFact(
                icon: Icons.meeting_room_outlined,
                text: '$roomNames · kapasitas ${assignment.totalCapacity}',
              ),
            ],
            const SizedBox(height: 11),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: grade.classes.isEmpty ? null : onConfigure,
                icon: Icon(
                  assignment == null ? Icons.add_rounded : Icons.edit_outlined,
                ),
                label: Text(
                  assignment == null ? 'Atur Penetapan' : 'Ubah Penetapan',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DistributionCard extends StatelessWidget {
  const _DistributionCard({
    required this.detail,
    required this.grade,
    required this.mutating,
    required this.onGenerate,
    required this.onDelete,
    required this.onView,
  });

  final CentralExamPreparationDetail detail;
  final CentralExamGradePreparation grade;
  final bool mutating;
  final VoidCallback? onGenerate;
  final VoidCallback? onDelete;
  final VoidCallback? onView;

  @override
  Widget build(BuildContext context) {
    final assignment = grade.assignment!;
    final roomNames = detail.rooms
        .where((item) => assignment.roomIds.contains(item.id))
        .map((item) => item.code)
        .join(', ');
    final generated = assignment.distributedCount > 0;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Peserta Tingkat ${grade.grade}',
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
                _MiniStatus(ready: generated),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              '${assignment.sessionName} · $roomNames',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10.5,
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                _Metric(value: assignment.distributedCount, label: 'Terbagi'),
                _Metric(value: assignment.totalCapacity, label: 'Kapasitas'),
                _Metric(value: assignment.scheduleCount, label: 'Jadwal'),
              ],
            ),
            const SizedBox(height: 11),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: mutating ? null : onGenerate,
                icon: const Icon(Icons.auto_awesome_rounded),
                label: Text(
                  generated ? 'Susun Ulang Otomatis' : 'Bagi Peserta Otomatis',
                ),
              ),
            ),
            if (onView != null) ...[
              const SizedBox(height: 7),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: onView,
                  icon: const Icon(Icons.format_list_numbered_rounded),
                  label: const Text('Lihat Pembagian'),
                ),
              ),
            ],
            if (onDelete != null) ...[
              const SizedBox(height: 3),
              Center(
                child: TextButton.icon(
                  onPressed: mutating ? null : onDelete,
                  icon: const Icon(Icons.delete_outline_rounded),
                  label: const Text('Kosongkan Penetapan'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StageHeading extends StatelessWidget {
  const _StageHeading({required this.title, required this.subtitle});
  final String title;
  final String subtitle;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      const SizedBox(height: 2),
      Text(
        subtitle,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

class _NoticeCard extends StatelessWidget {
  const _NoticeCard({required this.icon, required this.message});
  final IconData icon;
  final String message;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          Icon(icon, size: 38, color: NusaColors.primary),
          const SizedBox(height: 8),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

class _MiniStatus extends StatelessWidget {
  const _MiniStatus({required this.ready});
  final bool ready;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: (ready ? NusaColors.success : NusaColors.textSecondary).withValues(
        alpha: 0.11,
      ),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      ready ? 'Siap' : 'Belum',
      style: TextStyle(
        color: ready ? NusaColors.success : NusaColors.textSecondary,
        fontSize: 9,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _CompactFact extends StatelessWidget {
  const _CompactFact({required this.icon, required this.text});
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
        Expanded(child: Text(text, style: const TextStyle(fontSize: 11))),
      ],
    ),
  );
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});
  final int value;
  final String label;
  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      margin: const EdgeInsets.symmetric(horizontal: 2),
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Text('$value', style: const TextStyle(fontWeight: FontWeight.w900)),
          Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 8.5,
            ),
          ),
        ],
      ),
    ),
  );
}

class _SelectionHeading extends StatelessWidget {
  const _SelectionHeading({required this.title, required this.value});
  final String title;
  final String value;
  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      Text(
        value,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10),
      ),
    ],
  );
}

class _CapacitySummary extends StatelessWidget {
  const _CapacitySummary({
    required this.studentCount,
    required this.capacity,
    required this.enough,
  });
  final int studentCount;
  final int capacity;
  final bool enough;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: (enough ? NusaColors.success : NusaColors.accent).withValues(
        alpha: 0.1,
      ),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      children: [
        Icon(
          enough ? Icons.check_circle_outline : Icons.info_outline_rounded,
          color: enough ? NusaColors.success : const Color(0xFFB78500),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            '$studentCount siswa · $capacity kursi. '
            '${enough ? 'Kapasitas mencukupi.' : 'Pastikan kapasitas mencukupi sebelum membagi peserta.'}',
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}
