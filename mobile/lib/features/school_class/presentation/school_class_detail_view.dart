import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/school_class/application/school_class_controller.dart';
import 'package:nusa/features/school_class/domain/school_class.dart';
import 'package:nusa/features/school_class/presentation/widgets/school_class_components.dart';
import 'package:nusa/features/student/domain/student.dart';
import 'package:nusa/features/student/presentation/widgets/student_components.dart';

class SchoolClassDetailView extends ConsumerStatefulWidget {
  const SchoolClassDetailView({
    required this.classId,
    this.initialTab = 'ringkasan',
    super.key,
  });

  final int classId;
  final String initialTab;

  @override
  ConsumerState<SchoolClassDetailView> createState() =>
      _SchoolClassDetailViewState();
}

class _SchoolClassDetailViewState extends ConsumerState<SchoolClassDetailView> {
  late int _selectedTab;
  bool _mutating = false;

  @override
  void initState() {
    super.initState();
    _selectedTab = switch (widget.initialTab) {
      'anggota' => 1,
      'jadwal' => 2,
      _ => 0,
    };
  }

  Future<void> _refresh() async {
    ref.invalidate(schoolClassDetailProvider(widget.classId));
    await ref.read(schoolClassDetailProvider(widget.classId).future);
  }

  @override
  Widget build(BuildContext context) {
    final schoolClass = ref.watch(schoolClassDetailProvider(widget.classId));

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(title: const Text('Detail Kelas')),
      body: SafeArea(
        top: false,
        child: schoolClass.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailErrorState(
            message: _errorMessage(error),
            onRetry: _refresh,
          ),
          data: (detail) => Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
                child: _ClassHero(schoolClass: detail.summary),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: _ClassTabSelector(
                  selectedIndex: _selectedTab,
                  onSelected: (value) => setState(() => _selectedTab = value),
                ),
              ),
              const SizedBox(height: 8),
              Expanded(
                child: switch (_selectedTab) {
                  1 => _MembersTab(
                    detail: detail,
                    mutating: _mutating,
                    onRefresh: _refresh,
                    onAdd: () => _addMember(detail),
                    onEdit: (member) => _editMember(detail, member),
                    onDelete: (member) => _deleteMember(detail, member),
                  ),
                  2 => _ScheduleTab(
                    schedule: detail.schedule,
                    canView: detail.permissions.canViewSchedule,
                    canManage: detail.permissions.canManageSchedule,
                    mutating: _mutating,
                    onRefresh: _refresh,
                    onEdit: (slot) => _editScheduleSlot(detail, slot),
                  ),
                  _ => _OverviewTab(
                    detail: detail,
                    onRefresh: _refresh,
                    onOpenMembers: () => setState(() => _selectedTab = 1),
                    onOpenSchedule: () => setState(() => _selectedTab = 2),
                  ),
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _addMember(SchoolClassDetail detail) async {
    final student = await showModalBottomSheet<StudentSummary>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _CandidatePickerSheet(
        className: detail.summary.name,
        loadCandidates: (query) => ref
            .read(schoolClassMemberActionsProvider)
            .fetchCandidates(classId: widget.classId, query: query),
      ),
    );
    if (student == null || !mounted) return;

    final form = await showDialog<_MemberFormValue>(
      context: context,
      builder: (context) => _MemberFormDialog(
        title: 'Tambahkan ${student.name}',
        submitLabel: 'Tambahkan',
        initialDate: detail.summary.academicYear?.startDate,
      ),
    );
    if (form == null || !mounted) return;

    await _runMutation(
      successMessage: '${student.name} berhasil ditambahkan.',
      operation: () => ref
          .read(schoolClassMemberActionsProvider)
          .addMember(
            classId: widget.classId,
            studentId: student.id,
            joinDate: form.joinDate,
            notes: form.notes,
          ),
    );
  }

  Future<void> _editMember(
    SchoolClassDetail detail,
    SchoolClassMember member,
  ) async {
    final form = await showDialog<_MemberFormValue>(
      context: context,
      builder: (context) => _MemberFormDialog(
        title: 'Ubah data anggota',
        submitLabel: 'Simpan',
        initialDate: member.joinDate,
        initialNotes: member.notes,
      ),
    );
    if (form == null || !mounted) return;

    await _runMutation(
      successMessage: 'Data ${member.student.name} berhasil diperbarui.',
      operation: () => ref
          .read(schoolClassMemberActionsProvider)
          .updateMember(
            classId: widget.classId,
            memberId: member.id,
            joinDate: form.joinDate,
            notes: form.notes,
          ),
    );
  }

  Future<void> _deleteMember(
    SchoolClassDetail detail,
    SchoolClassMember member,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Keluarkan dari kelas?'),
        content: Text(
          '${member.student.name} akan dikeluarkan dari '
          '${detail.summary.name}. Nomor absen anggota lain akan diurutkan '
          'kembali secara otomatis.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('confirm-delete-class-member'),
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            child: const Text('Keluarkan'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    await _runMutation(
      successMessage: '${member.student.name} berhasil dikeluarkan.',
      operation: () => ref
          .read(schoolClassMemberActionsProvider)
          .deleteMember(classId: widget.classId, memberId: member.id),
    );
  }

  Future<void> _editScheduleSlot(
    SchoolClassDetail detail,
    ClassScheduleSlot slot,
  ) async {
    setState(() => _mutating = true);
    late ScheduleChoiceCatalog catalog;
    try {
      catalog = await ref
          .read(schoolClassScheduleActionsProvider)
          .fetchChoices(classId: widget.classId);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
      return;
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
    if (!mounted) return;

    final value = await showModalBottomSheet<_ScheduleSlotFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          _ScheduleSlotEditorSheet(slot: slot, catalog: catalog),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: value.scheduleChoice == null
          ? '${slot.label ?? 'Slot jadwal'} berhasil dikosongkan.'
          : '${slot.label ?? 'Slot jadwal'} berhasil diperbarui.',
      operation: () => ref
          .read(schoolClassScheduleActionsProvider)
          .updateSlot(
            classId: detail.summary.id,
            slotId: slot.id,
            scheduleChoice: value.scheduleChoice,
            notes: value.notes,
          ),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(schoolClassDetailProvider(widget.classId).future);
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(successMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }
}

class _ClassTabSelector extends StatelessWidget {
  const _ClassTabSelector({
    required this.selectedIndex,
    required this.onSelected,
  });

  final int selectedIndex;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          for (final tab in const [
            (0, 'Ringkasan', Icons.dashboard_outlined),
            (1, 'Anggota', Icons.groups_rounded),
            (2, 'Jadwal', Icons.calendar_month_rounded),
          ])
            Expanded(
              child: Material(
                color: selectedIndex == tab.$1
                    ? Colors.white
                    : Colors.transparent,
                borderRadius: BorderRadius.circular(11),
                child: InkWell(
                  key: Key('class-detail-tab-${tab.$1}'),
                  onTap: () => onSelected(tab.$1),
                  borderRadius: BorderRadius.circular(11),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 9),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          tab.$3,
                          size: 17,
                          color: selectedIndex == tab.$1
                              ? NusaColors.primary
                              : NusaColors.textSecondary,
                        ),
                        const SizedBox(width: 5),
                        Flexible(
                          child: Text(
                            tab.$2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: selectedIndex == tab.$1
                                  ? NusaColors.primary
                                  : NusaColors.textSecondary,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _OverviewTab extends StatelessWidget {
  const _OverviewTab({
    required this.detail,
    required this.onRefresh,
    required this.onOpenMembers,
    required this.onOpenSchedule,
  });

  final SchoolClassDetail detail;
  final Future<void> Function() onRefresh;
  final VoidCallback onOpenMembers;
  final VoidCallback onOpenSchedule;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        key: const PageStorageKey<String>('class-detail-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
        children: [
          _ClassMetrics(schoolClass: detail.summary),
          const SizedBox(height: 12),
          _HomeroomSection(teacher: detail.summary.homeroomTeacher),
          if (detail.notes?.trim().isNotEmpty == true) ...[
            const SizedBox(height: 12),
            _NotesCard(notes: detail.notes!),
          ],
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _OverviewActionCard(
                  icon: Icons.groups_rounded,
                  label: 'Anggota Kelas',
                  value: '${detail.activeMembers.length} siswa',
                  color: NusaColors.success,
                  onTap: onOpenMembers,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _OverviewActionCard(
                  icon: Icons.calendar_month_rounded,
                  label: 'Jadwal Kelas',
                  value: detail.permissions.canViewSchedule
                      ? '${detail.schedule?.filledCount ?? 0} slot'
                      : 'Tidak tersedia',
                  color: NusaColors.primary,
                  onTap: onOpenSchedule,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _OverviewActionCard extends StatelessWidget {
  const _OverviewActionCard({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            border: Border.all(color: NusaColors.outline),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: color),
              const SizedBox(height: 10),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                value,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MembersTab extends StatelessWidget {
  const _MembersTab({
    required this.detail,
    required this.mutating,
    required this.onRefresh,
    required this.onAdd,
    required this.onEdit,
    required this.onDelete,
  });

  final SchoolClassDetail detail;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final VoidCallback onAdd;
  final ValueChanged<SchoolClassMember> onEdit;
  final ValueChanged<SchoolClassMember> onDelete;

  @override
  Widget build(BuildContext context) {
    final members = detail.members;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        key: const PageStorageKey<String>('class-members-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Anggota Kelas',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      '${detail.activeMembers.length} siswa aktif',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
              if (detail.permissions.canManageMembers)
                FilledButton.icon(
                  key: const Key('add-class-member'),
                  onPressed: mutating ? null : onAdd,
                  icon: const Icon(Icons.person_add_alt_1_rounded, size: 18),
                  label: const Text('Tambah'),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(0, 42),
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          if (members.isEmpty)
            const _EmptyState(
              icon: Icons.group_off_outlined,
              message: 'Belum ada siswa di kelas ini.',
            )
          else
            for (var index = 0; index < members.length; index++) ...[
              _MemberCard(
                member: members[index],
                canManage: detail.permissions.canManageMembers && !mutating,
                onEdit: () => onEdit(members[index]),
                onDelete: () => onDelete(members[index]),
              ),
              if (index < members.length - 1) const SizedBox(height: 8),
            ],
        ],
      ),
    );
  }
}

class _MemberCard extends StatelessWidget {
  const _MemberCard({
    required this.member,
    required this.canManage,
    required this.onEdit,
    required this.onDelete,
  });

  final SchoolClassMember member;
  final bool canManage;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final active = member.membershipStatus == 'aktif';

    return Container(
      key: Key('class-member-${member.student.id}'),
      padding: const EdgeInsets.fromLTRB(11, 10, 5, 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Row(
        children: [
          Container(
            width: 31,
            height: 31,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              member.attendanceNumber?.toString() ?? '-',
              style: const TextStyle(
                color: NusaColors.primary,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: 9),
          StudentAvatar(student: member.student, size: 40),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  member.student.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  member.student.identityLabel,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                if (!active)
                  Text(
                    member.membershipStatusLabel,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 9.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
              ],
            ),
          ),
          if (canManage)
            PopupMenuButton<String>(
              key: Key('class-member-menu-${member.id}'),
              tooltip: 'Kelola anggota',
              onSelected: (value) {
                if (value == 'edit') onEdit();
                if (value == 'delete') onDelete();
              },
              itemBuilder: (context) => const [
                PopupMenuItem(
                  value: 'edit',
                  child: ListTile(
                    leading: Icon(Icons.edit_outlined),
                    title: Text('Ubah data'),
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
                PopupMenuItem(
                  value: 'delete',
                  child: ListTile(
                    leading: Icon(Icons.person_remove_outlined),
                    title: Text('Keluarkan'),
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
              ],
            )
          else
            const SizedBox(width: 8),
        ],
      ),
    );
  }
}

class _ScheduleTab extends StatefulWidget {
  const _ScheduleTab({
    required this.schedule,
    required this.canView,
    required this.canManage,
    required this.mutating,
    required this.onRefresh,
    required this.onEdit,
  });

  final SchoolClassSchedule? schedule;
  final bool canView;
  final bool canManage;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final ValueChanged<ClassScheduleSlot> onEdit;

  @override
  State<_ScheduleTab> createState() => _ScheduleTabState();
}

class _ScheduleTabState extends State<_ScheduleTab> {
  String? _selectedDayCode;

  ClassScheduleDay? get _selectedDay {
    final days = widget.schedule?.days ?? const <ClassScheduleDay>[];
    if (days.isEmpty) return null;
    final selectedCode = _selectedDayCode ?? widget.schedule?.todayCode;
    return days.where((day) => day.code == selectedCode).firstOrNull ??
        days.first;
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.canView) {
      return RefreshIndicator(
        onRefresh: widget.onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 40, 16, 28),
          children: const [
            _EmptyState(
              icon: Icons.lock_outline_rounded,
              message: 'Akun ini tidak memiliki izin melihat jadwal kelas.',
            ),
          ],
        ),
      );
    }

    final schedule = widget.schedule;
    final day = _selectedDay;
    if (schedule == null || schedule.days.isEmpty || day == null) {
      return RefreshIndicator(
        onRefresh: widget.onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 40, 16, 28),
          children: const [
            _EmptyState(
              icon: Icons.event_busy_outlined,
              message: 'Jam pelajaran belum disiapkan pada server.',
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: widget.onRefresh,
      child: ListView(
        key: const PageStorageKey<String>('class-schedule-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Jadwal Mingguan',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  '${schedule.filledCount} slot terisi',
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (widget.canManage) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: NusaColors.primary.withValues(alpha: 0.07),
                borderRadius: BorderRadius.circular(13),
                border: Border.all(
                  color: NusaColors.primary.withValues(alpha: 0.12),
                ),
              ),
              child: Row(
                children: [
                  if (widget.mutating)
                    const SizedBox.square(
                      dimension: 17,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  else
                    const Icon(
                      Icons.touch_app_outlined,
                      size: 18,
                      color: NusaColors.primary,
                    ),
                  const SizedBox(width: 9),
                  const Expanded(
                    child: Text(
                      'Ketuk slot pelajaran untuk mengisi atau mengubah jadwal.',
                      style: TextStyle(
                        color: NusaColors.primary,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
          ],
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (final item in schedule.days) ...[
                  ChoiceChip(
                    key: Key('schedule-day-${item.code}'),
                    label: Text(item.label),
                    selected: item.code == day.code,
                    showCheckmark: false,
                    onSelected: (_) =>
                        setState(() => _selectedDayCode = item.code),
                  ),
                  const SizedBox(width: 7),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),
          for (var index = 0; index < day.slots.length; index++) ...[
            _ScheduleSlotCard(
              slot: day.slots[index],
              onTap:
                  widget.canManage &&
                      day.slots[index].isLesson &&
                      !widget.mutating
                  ? () => widget.onEdit(day.slots[index])
                  : null,
            ),
            if (index < day.slots.length - 1) const SizedBox(height: 8),
          ],
        ],
      ),
    );
  }
}

class _ScheduleSlotCard extends StatelessWidget {
  const _ScheduleSlotCard({required this.slot, this.onTap});

  final ClassScheduleSlot slot;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final special = !slot.isLesson;
    final color = special
        ? NusaColors.accent
        : (slot.filled ? NusaColors.primary : NusaColors.textSecondary);

    return Material(
      key: Key('class-schedule-slot-${slot.id}'),
      color: special ? NusaColors.accent.withValues(alpha: 0.06) : Colors.white,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        key: Key('edit-schedule-slot-${slot.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: NusaColors.outline),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 4,
                height: 54,
                decoration: BoxDecoration(
                  color: color,
                  borderRadius: BorderRadius.circular(6),
                ),
              ),
              const SizedBox(width: 10),
              SizedBox(
                width: 66,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      slot.startTime,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      slot.endTime,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      slot.label ?? 'Jam ${slot.number}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 9,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      slot.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: slot.filled || special
                            ? NusaColors.textPrimary
                            : NusaColors.textSecondary,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      special
                          ? slot.typeLabel
                          : (slot.teacher?.name ?? 'Guru belum ditentukan'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
              if (onTap != null)
                const Padding(
                  padding: EdgeInsets.only(left: 6, top: 16),
                  child: Icon(
                    Icons.edit_outlined,
                    size: 17,
                    color: NusaColors.primary,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ScheduleSlotFormValue {
  const _ScheduleSlotFormValue({required this.scheduleChoice, this.notes});

  final String? scheduleChoice;
  final String? notes;
}

class _ScheduleSlotEditorSheet extends StatefulWidget {
  const _ScheduleSlotEditorSheet({required this.slot, required this.catalog});

  final ClassScheduleSlot slot;
  final ScheduleChoiceCatalog catalog;

  @override
  State<_ScheduleSlotEditorSheet> createState() =>
      _ScheduleSlotEditorSheetState();
}

class _ScheduleSlotEditorSheetState extends State<_ScheduleSlotEditorSheet> {
  late String? _selectedValue;
  late final TextEditingController _notesController;

  @override
  void initState() {
    super.initState();
    _selectedValue = widget.slot.scheduleChoice;
    _notesController = TextEditingController(text: widget.slot.notes);
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final assignments = widget.catalog.teacherAssignments;
    final activities = widget.catalog.activities;

    return AnimatedPadding(
      duration: const Duration(milliseconds: 160),
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.86,
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
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Ubah ${widget.slot.label ?? 'Slot Jadwal'}',
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          '${widget.slot.timeLabel} • pilih mapel atau kegiatan',
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Tutup',
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                children: [
                  _ScheduleChoiceTile(
                    key: const Key('schedule-choice-empty'),
                    selected: _selectedValue == null,
                    icon: Icons.event_busy_outlined,
                    title: 'Kosongkan slot',
                    subtitle: 'Hapus pelajaran atau kegiatan dari slot ini',
                    onTap: () => setState(() => _selectedValue = null),
                  ),
                  if (assignments.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    const _ScheduleChoiceSectionTitle(
                      icon: Icons.school_outlined,
                      label: 'Guru Mata Pelajaran',
                    ),
                    const SizedBox(height: 8),
                    for (final choice in assignments) ...[
                      _ScheduleChoiceTile(
                        key: Key(
                          'schedule-choice-${choice.value.replaceAll(':', '-')}',
                        ),
                        selected: _selectedValue == choice.value,
                        icon: Icons.menu_book_rounded,
                        title: choice.title,
                        subtitle: choice.subtitle,
                        onTap: () =>
                            setState(() => _selectedValue = choice.value),
                      ),
                      const SizedBox(height: 7),
                    ],
                  ],
                  if (activities.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    const _ScheduleChoiceSectionTitle(
                      icon: Icons.auto_awesome_outlined,
                      label: 'Kegiatan',
                    ),
                    const SizedBox(height: 8),
                    for (final choice in activities) ...[
                      _ScheduleChoiceTile(
                        key: Key(
                          'schedule-choice-${choice.value.replaceAll(':', '-')}',
                        ),
                        selected: _selectedValue == choice.value,
                        icon: Icons.groups_2_outlined,
                        title: choice.title,
                        subtitle: choice.subtitle,
                        onTap: () =>
                            setState(() => _selectedValue = choice.value),
                      ),
                      const SizedBox(height: 7),
                    ],
                  ],
                  if (widget.catalog.items.isEmpty) ...[
                    const SizedBox(height: 16),
                    const _EmptyState(
                      icon: Icons.assignment_ind_outlined,
                      message: 'Belum ada penugasan guru atau kegiatan yang tersedia untuk kelas ini.',
                    ),
                  ],
                  const SizedBox(height: 14),
                  TextField(
                    key: const Key('schedule-slot-notes'),
                    controller: _notesController,
                    minLines: 2,
                    maxLines: 3,
                    maxLength: 1000,
                    decoration: const InputDecoration(
                      labelText: 'Keterangan (opsional)',
                      hintText: 'Tambahkan catatan untuk slot ini',
                      alignLabelWithHint: true,
                      prefixIcon: Icon(Icons.notes_rounded),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  key: const Key('save-schedule-slot'),
                  onPressed: () => Navigator.pop(
                    context,
                    _ScheduleSlotFormValue(
                      scheduleChoice: _selectedValue,
                      notes: _notesController.text.trim().isEmpty
                          ? null
                          : _notesController.text.trim(),
                    ),
                  ),
                  icon: const Icon(Icons.save_outlined),
                  label: const Text('Simpan Jadwal'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ScheduleChoiceSectionTitle extends StatelessWidget {
  const _ScheduleChoiceSectionTitle({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 17, color: NusaColors.primary),
        const SizedBox(width: 7),
        Text(
          label,
          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
        ),
      ],
    );
  }
}

class _ScheduleChoiceTile extends StatelessWidget {
  const _ScheduleChoiceTile({
    required this.selected,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    super.key,
  });

  final bool selected;
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected
          ? NusaColors.primary.withValues(alpha: 0.08)
          : Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(11),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected ? NusaColors.primary : NusaColors.outline,
              width: selected ? 1.4 : 1,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: selected ? NusaColors.primary : NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(
                  icon,
                  size: 20,
                  color: selected ? Colors.white : NusaColors.primary,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(
                selected
                    ? Icons.check_circle_rounded
                    : Icons.radio_button_unchecked_rounded,
                color: selected ? NusaColors.primary : NusaColors.outline,
                size: 22,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CandidatePickerSheet extends StatefulWidget {
  const _CandidatePickerSheet({
    required this.className,
    required this.loadCandidates,
  });

  final String className;
  final Future<SchoolClassCandidatePage> Function(String query) loadCandidates;

  @override
  State<_CandidatePickerSheet> createState() => _CandidatePickerSheetState();
}

class _CandidatePickerSheetState extends State<_CandidatePickerSheet> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  late Future<SchoolClassCandidatePage> _request;

  @override
  void initState() {
    super.initState();
    _request = widget.loadCandidates('');
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      if (!mounted) return;
      setState(() => _request = widget.loadCandidates(value));
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedPadding(
      duration: const Duration(milliseconds: 160),
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.78,
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
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Pilih Siswa',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          'Tambahkan ke ${widget.className}',
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
              child: TextField(
                key: const Key('candidate-student-search'),
                controller: _searchController,
                autofocus: true,
                onChanged: _search,
                decoration: const InputDecoration(
                  hintText: 'Cari nama, NIS, atau NISN',
                  prefixIcon: Icon(Icons.search_rounded),
                ),
              ),
            ),
            const SizedBox(height: 10),
            Expanded(
              child: FutureBuilder<SchoolClassCandidatePage>(
                future: _request,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    return _InlineError(
                      message: _errorMessage(snapshot.error!),
                      onRetry: () => setState(
                        () => _request = widget.loadCandidates(
                          _searchController.text,
                        ),
                      ),
                    );
                  }
                  final page = snapshot.data!;
                  if (page.items.isEmpty) {
                    return const _EmptyState(
                      icon: Icons.person_search_outlined,
                      message: 'Tidak ada siswa tersedia pada pencarian ini.',
                    );
                  }

                  return ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 2, 16, 20),
                    itemCount: page.items.length,
                    separatorBuilder: (context, index) =>
                        const SizedBox(height: 7),
                    itemBuilder: (context, index) {
                      final student = page.items[index];
                      return Material(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        child: InkWell(
                          key: Key('candidate-student-${student.id}'),
                          onTap: () => Navigator.pop(context, student),
                          borderRadius: BorderRadius.circular(14),
                          child: Container(
                            padding: const EdgeInsets.all(11),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: NusaColors.outline),
                            ),
                            child: Row(
                              children: [
                                StudentAvatar(student: student, size: 42),
                                const SizedBox(width: 11),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        student.name,
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                      Text(
                                        student.identityLabel,
                                        style: const TextStyle(
                                          color: NusaColors.textSecondary,
                                          fontSize: 10.5,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const Icon(
                                  Icons.add_circle_outline_rounded,
                                  color: NusaColors.primary,
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberFormDialog extends StatefulWidget {
  const _MemberFormDialog({
    required this.title,
    required this.submitLabel,
    this.initialDate,
    this.initialNotes,
  });

  final String title;
  final String submitLabel;
  final DateTime? initialDate;
  final String? initialNotes;

  @override
  State<_MemberFormDialog> createState() => _MemberFormDialogState();
}

class _MemberFormDialogState extends State<_MemberFormDialog> {
  late final TextEditingController _notesController;
  DateTime? _joinDate;

  @override
  void initState() {
    super.initState();
    _joinDate = widget.initialDate;
    _notesController = TextEditingController(text: widget.initialNotes);
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Tanggal masuk',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 6),
            OutlinedButton.icon(
              key: const Key('member-join-date'),
              onPressed: _pickDate,
              icon: const Icon(Icons.calendar_today_outlined, size: 18),
              label: Text(
                _joinDate == null ? 'Pilih tanggal' : _formatDate(_joinDate),
              ),
            ),
            const SizedBox(height: 14),
            TextField(
              key: const Key('member-notes'),
              controller: _notesController,
              minLines: 2,
              maxLines: 4,
              maxLength: 1000,
              decoration: const InputDecoration(
                labelText: 'Keterangan (opsional)',
                alignLabelWithHint: true,
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Batal'),
        ),
        FilledButton(
          key: const Key('submit-member-form'),
          onPressed: () => Navigator.pop(
            context,
            _MemberFormValue(joinDate: _joinDate, notes: _notesController.text),
          ),
          child: Text(widget.submitLabel),
        ),
      ],
    );
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final value = await showDatePicker(
      context: context,
      initialDate: _joinDate ?? now,
      firstDate: DateTime(now.year - 3),
      lastDate: DateTime(now.year + 3),
    );
    if (value != null) setState(() => _joinDate = value);
  }
}

class _MemberFormValue {
  const _MemberFormValue({required this.joinDate, required this.notes});

  final DateTime? joinDate;
  final String notes;
}

class _ClassHero extends StatelessWidget {
  const _ClassHero({required this.schoolClass});

  final SchoolClassSummary schoolClass;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(19),
      ),
      child: Row(
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.13),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: NusaColors.accent, width: 1.5),
            ),
            child: const Icon(
              Icons.class_rounded,
              color: Colors.white,
              size: 29,
            ),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  schoolClass.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  schoolClass.academicYear?.name ?? 'Tahun pelajaran -',
                  style: const TextStyle(color: Colors.white70, fontSize: 11),
                ),
              ],
            ),
          ),
          SchoolClassStatusBadge(active: schoolClass.active),
        ],
      ),
    );
  }
}

class _ClassMetrics extends StatelessWidget {
  const _ClassMetrics({required this.schoolClass});

  final SchoolClassSummary schoolClass;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: _Metric(
                  label: 'Siswa Aktif',
                  value: '${schoolClass.activeStudentCount}',
                  icon: Icons.groups_rounded,
                ),
              ),
              Container(width: 1, height: 42, color: NusaColors.outline),
              Expanded(
                child: _Metric(
                  label: 'Kapasitas',
                  value: schoolClass.capacity?.toString() ?? '-',
                  icon: Icons.meeting_room_outlined,
                ),
              ),
              Container(width: 1, height: 42, color: NusaColors.outline),
              Expanded(
                child: _Metric(
                  label: 'Tersedia',
                  value: schoolClass.availableCapacity?.toString() ?? '-',
                  icon: Icons.event_seat_outlined,
                ),
              ),
            ],
          ),
          if (schoolClass.capacity != null) ...[
            const SizedBox(height: 13),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: schoolClass.capacityFraction,
                minHeight: 7,
                color: schoolClass.capacityFraction >= 0.9
                    ? NusaColors.accent
                    : NusaColors.primary,
                backgroundColor: NusaColors.surfaceBlue,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value, required this.icon});

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Icon(icon, color: NusaColors.primary, size: 20),
        const SizedBox(height: 3),
        Text(
          value,
          style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
        ),
        FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
            ),
          ),
        ),
      ],
    );
  }
}

class _HomeroomSection extends StatelessWidget {
  const _HomeroomSection({required this.teacher});

  final HomeroomTeacher? teacher;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(
              Icons.supervisor_account_rounded,
              color: NusaColors.primary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Wali Kelas',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                Text(
                  teacher?.name ?? 'Belum ditentukan',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (teacher?.nip?.isNotEmpty == true)
                  Text(
                    'NIP ${teacher!.nip}',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _NotesCard extends StatelessWidget {
  const _NotesCard({required this.notes});

  final String notes;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(15),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline_rounded, color: NusaColors.primary),
          const SizedBox(width: 10),
          Expanded(child: Text(notes, style: const TextStyle(fontSize: 12.5))),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Icon(icon, color: NusaColors.primary, size: 38),
          const SizedBox(height: 10),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: NusaColors.textSecondary),
          ),
        ],
      ),
    );
  }
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
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
}

class _DetailErrorState extends StatelessWidget {
  const _DetailErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.meeting_room_outlined,
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
}

String _formatDate(DateTime? value) {
  if (value == null) return '-';
  final day = value.day.toString().padLeft(2, '0');
  final month = value.month.toString().padLeft(2, '0');
  return '$day/$month/${value.year}';
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Data kelas belum dapat diproses.';
}
