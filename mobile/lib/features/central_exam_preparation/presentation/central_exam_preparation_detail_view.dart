import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_preparation/application/central_exam_preparation_controller.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:nusa/features/central_exam_preparation/presentation/widgets/central_exam_preparation_sheets.dart';
import 'package:nusa/features/central_exam_preparation/presentation/widgets/central_exam_participant_stages.dart';
import 'package:nusa/features/central_exam_preparation/presentation/widgets/central_exam_schedule_stage.dart';

class CentralExamPreparationDetailView extends ConsumerStatefulWidget {
  const CentralExamPreparationDetailView({required this.eventId, super.key});
  final int eventId;

  @override
  ConsumerState<CentralExamPreparationDetailView> createState() =>
      _CentralExamPreparationDetailViewState();
}

class _CentralExamPreparationDetailViewState
    extends ConsumerState<CentralExamPreparationDetailView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(
      centralExamPreparationDetailProvider(widget.eventId),
    );
    return DefaultTabController(
      length: 7,
      child: Scaffold(
        backgroundColor: NusaColors.background,
        appBar: AppBar(
          title: const Text('Persiapan Ujian'),
          actions: [
            if (state.value?.access.canManageMain == true)
              IconButton(
                tooltip: 'Ubah informasi',
                onPressed: _mutating
                    ? null
                    : () => _editEvent(state.requireValue),
                icon: const Icon(Icons.edit_outlined),
              ),
            IconButton(
              tooltip: 'Pelaksanaan',
              onPressed: () =>
                  context.push('/pelaksanaan-ujian-terpusat/${widget.eventId}'),
              icon: const Icon(Icons.monitor_heart_outlined),
            ),
            IconButton(
              tooltip: 'Perbarui',
              onPressed: state.isLoading ? null : _refresh,
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
        body: SafeArea(
          top: false,
          child: state.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, stackTrace) =>
                _ErrorState(message: _message(error), onRetry: _refresh),
            data: (detail) => Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                  child: _EventHeader(detail: detail),
                ),
                Container(
                  color: Colors.white,
                  child: const TabBar(
                    isScrollable: true,
                    tabAlignment: TabAlignment.start,
                    tabs: [
                      Tab(text: 'Informasi'),
                      Tab(text: 'Panitia'),
                      Tab(text: 'Sesi'),
                      Tab(text: 'Ruang'),
                      Tab(text: 'Penetapan'),
                      Tab(text: 'Peserta'),
                      Tab(text: 'Jadwal'),
                    ],
                  ),
                ),
                Expanded(
                  child: TabBarView(
                    children: [
                      _InformationTab(
                        detail: detail,
                        mutating: _mutating,
                        onEdit: detail.access.canManageMain
                            ? () => _editEvent(detail)
                            : null,
                        onDelete:
                            detail.access.canManageMain &&
                                detail.event.canDelete
                            ? () => _deleteEvent(detail.event)
                            : null,
                      ),
                      _CommitteeTab(
                        detail: detail,
                        mutating: _mutating,
                        onAdd: detail.access.canManageMain
                            ? () => _openCommittee(detail)
                            : null,
                        onEdit: detail.access.canManageMain
                            ? (item) => _openCommittee(detail, existing: item)
                            : null,
                        onDelete: detail.access.canManageMain
                            ? _deleteCommittee
                            : null,
                      ),
                      _SessionTab(
                        detail: detail,
                        mutating: _mutating,
                        onAdd: detail.access.canManagePreparation
                            ? () => _openSession()
                            : null,
                        onEdit: detail.access.canManagePreparation
                            ? (item) => _openSession(existing: item)
                            : null,
                        onDelete: detail.access.canManagePreparation
                            ? _deleteSession
                            : null,
                      ),
                      _RoomTab(
                        detail: detail,
                        mutating: _mutating,
                        onAdd: detail.access.canManagePreparation
                            ? () => _openRoom()
                            : null,
                        onEdit: detail.access.canManagePreparation
                            ? (item) => _openRoom(existing: item)
                            : null,
                        onDelete: detail.access.canManagePreparation
                            ? _deleteRoom
                            : null,
                      ),
                      CentralExamRoomAssignmentTab(
                        detail: detail,
                        mutating: _mutating,
                        onConfigure: detail.access.canManagePreparation
                            ? (grade) => _openRoomAssignment(detail, grade)
                            : null,
                      ),
                      CentralExamParticipantDistributionTab(
                        detail: detail,
                        mutating: _mutating,
                        onGenerate: detail.access.canManagePreparation
                            ? _generateParticipants
                            : null,
                        onDelete: detail.access.canManagePreparation
                            ? _deleteRoomAssignment
                            : null,
                        onView: _viewDistribution,
                      ),
                      CentralExamScheduleTab(
                        detail: detail,
                        mutating: _mutating,
                        onAdd: detail.access.canManagePreparation
                            ? () => _openSchedule(detail)
                            : null,
                        onEdit: detail.access.canManagePreparation
                            ? (schedule) =>
                                  _openSchedule(detail, existing: schedule)
                            : null,
                        onDelete: detail.access.canManagePreparation
                            ? _deleteSchedule
                            : null,
                        onPackage: _openPackage,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _refresh() {
    ref.invalidate(centralExamPreparationDetailProvider(widget.eventId));
  }

  Future<void> _editEvent(CentralExamPreparationDetail detail) async {
    final changed = await context.push<bool>(
      '/ujian-terpusat/${widget.eventId}/ubah',
    );
    if (changed == true) _refresh();
  }

  Future<void> _openCommittee(
    CentralExamPreparationDetail detail, {
    CentralExamCommitteeMember? existing,
  }) async {
    final value = await showModalBottomSheet<CentralExamCommitteeFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => CentralExamCommitteeSheet(
        references: detail.references,
        existing: existing,
      ),
    );
    if (value == null || !mounted) return;
    await _mutation(
      success: existing == null
          ? 'Panitia ujian berhasil ditambahkan.'
          : 'Tugas panitia berhasil diperbarui.',
      operation: () => ref
          .read(centralExamPreparationActionsProvider)
          .saveCommittee(widget.eventId, value),
    );
  }

  Future<void> _openSession({CentralExamSession? existing}) async {
    final value = await showModalBottomSheet<CentralExamSessionFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => CentralExamSessionSheet(existing: existing),
    );
    if (value == null || !mounted) return;
    final actions = ref.read(centralExamPreparationActionsProvider);
    await _mutation(
      success: existing == null
          ? 'Sesi ujian berhasil ditambahkan.'
          : 'Sesi ujian berhasil diperbarui.',
      operation: existing == null
          ? () => actions.createSession(widget.eventId, value)
          : () => actions.updateSession(widget.eventId, existing.id, value),
    );
  }

  Future<void> _openRoom({CentralExamRoom? existing}) async {
    final value = await showModalBottomSheet<CentralExamRoomFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => CentralExamRoomSheet(existing: existing),
    );
    if (value == null || !mounted) return;
    final actions = ref.read(centralExamPreparationActionsProvider);
    await _mutation(
      success: existing == null
          ? 'Ruang ujian berhasil ditambahkan.'
          : 'Ruang ujian berhasil diperbarui.',
      operation: existing == null
          ? () => actions.createRoom(widget.eventId, value)
          : () => actions.updateRoom(widget.eventId, existing.id, value),
    );
  }

  Future<void> _deleteEvent(CentralExamEvent event) async {
    if (!await _confirm(
      title: 'Hapus kegiatan persiapan?',
      message:
          '${event.name} beserta panitia, sesi, dan ruangnya akan dihapus permanen.',
    )) {
      return;
    }
    setState(() => _mutating = true);
    try {
      await ref
          .read(centralExamPreparationActionsProvider)
          .deleteEvent(widget.eventId);
      if (mounted) context.pop(true);
    } catch (error) {
      if (mounted) _showError(_message(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _deleteCommittee(CentralExamCommitteeMember item) async {
    if (!await _confirm(
      title: 'Hapus dari panitia?',
      message: '${item.name} akan dikeluarkan dari kepanitiaan ujian ini.',
    )) {
      return;
    }
    await _mutation(
      success: 'Penugasan panitia berhasil dihapus.',
      operation: () => ref
          .read(centralExamPreparationActionsProvider)
          .deleteCommittee(widget.eventId, item.id),
    );
  }

  Future<void> _deleteSession(CentralExamSession item) async {
    if (!await _confirm(
      title: 'Hapus sesi?',
      message: '${item.name} akan dihapus dari kegiatan ujian ini.',
    )) {
      return;
    }
    await _mutation(
      success: 'Sesi ujian berhasil dihapus.',
      operation: () => ref
          .read(centralExamPreparationActionsProvider)
          .deleteSession(widget.eventId, item.id),
    );
  }

  Future<void> _deleteRoom(CentralExamRoom item) async {
    if (!await _confirm(
      title: 'Hapus ruang?',
      message: '${item.name} akan dihapus dari kegiatan ujian ini.',
    )) {
      return;
    }
    await _mutation(
      success: 'Ruang ujian berhasil dihapus.',
      operation: () => ref
          .read(centralExamPreparationActionsProvider)
          .deleteRoom(widget.eventId, item.id),
    );
  }

  Future<void> _openRoomAssignment(
    CentralExamPreparationDetail detail,
    CentralExamGradePreparation grade,
  ) async {
    final value =
        await showModalBottomSheet<CentralExamRoomAssignmentFormValue>(
          context: context,
          isScrollControlled: true,
          useSafeArea: true,
          builder: (context) =>
              CentralExamRoomAssignmentSheet(detail: detail, grade: grade),
        );
    if (value == null || !mounted) return;
    await _mutation(
      success: 'Penetapan ruang tingkat ${grade.grade} berhasil disimpan.',
      operation: () => ref
          .read(centralExamPreparationActionsProvider)
          .saveRoomAssignment(widget.eventId, value),
    );
  }

  Future<void> _generateParticipants(CentralExamGradePreparation grade) async {
    final assignment = grade.assignment!;
    final regenerate = assignment.distributedCount > 0;
    if (!await _confirm(
      title: regenerate ? 'Susun ulang peserta?' : 'Bagi peserta otomatis?',
      message: regenerate
          ? 'Susunan peserta tingkat ${grade.grade} saat ini akan diganti berdasarkan kelas, nama, dan kapasitas ruang terbaru.'
          : '${grade.activeStudentCount} siswa tingkat ${grade.grade} akan ditempatkan otomatis ke ruang yang dipilih.',
      confirmLabel: regenerate ? 'Susun Ulang' : 'Bagi Peserta',
    )) {
      return;
    }
    await _mutation(
      success: 'Peserta tingkat ${grade.grade} berhasil dibagi otomatis.',
      operation: () => ref
          .read(centralExamPreparationActionsProvider)
          .generateParticipants(widget.eventId, assignment.id),
    );
  }

  Future<void> _deleteRoomAssignment(CentralExamGradePreparation grade) async {
    final assignment = grade.assignment!;
    if (!await _confirm(
      title: 'Kosongkan penetapan?',
      message:
          'Penetapan ruang dan susunan peserta tingkat ${grade.grade} akan dihapus.',
    )) {
      return;
    }
    await _mutation(
      success: 'Penetapan tingkat ${grade.grade} berhasil dikosongkan.',
      operation: () => ref
          .read(centralExamPreparationActionsProvider)
          .deleteRoomAssignment(widget.eventId, assignment.id),
    );
  }

  void _viewDistribution(CentralExamGradePreparation grade) {
    context.push(
      '/ujian-terpusat/${widget.eventId}/pembagian/${grade.assignment!.id}',
    );
  }

  Future<void> _openSchedule(
    CentralExamPreparationDetail detail, {
    CentralExamSchedule? existing,
  }) async {
    final value = await showModalBottomSheet<CentralExamScheduleFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          CentralExamScheduleSheet(detail: detail, existing: existing),
    );
    if (value == null || !mounted) return;
    final actions = ref.read(centralExamPreparationActionsProvider);
    await _mutation(
      success: existing == null
          ? 'Jadwal ujian berhasil ditambahkan.'
          : 'Jadwal ujian berhasil diperbarui.',
      operation: existing == null
          ? () => actions.createSchedule(widget.eventId, value)
          : () => actions.updateSchedule(widget.eventId, existing.id, value),
    );
  }

  Future<void> _deleteSchedule(CentralExamSchedule schedule) async {
    if (!await _confirm(
      title: 'Hapus jadwal ujian?',
      message:
          '${schedule.subjectName} tingkat ${schedule.grade} pada ${_date(schedule.date)} akan dihapus.',
    )) {
      return;
    }
    await _mutation(
      success: 'Jadwal ujian berhasil dihapus.',
      operation: () => ref
          .read(centralExamPreparationActionsProvider)
          .deleteSchedule(widget.eventId, schedule.id),
    );
  }

  void _openPackage(CentralExamSchedule schedule) {
    context.push('/paket-soal/${schedule.id}');
  }

  Future<void> _mutation({
    required String success,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      if (!mounted) return;
      _showMessage(success);
    } catch (error) {
      if (mounted) _showError(_message(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<bool> _confirm({
    required String title,
    required String message,
    String confirmLabel = 'Hapus',
  }) async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(Icons.warning_amber_rounded),
            title: Text(title),
            content: Text(message),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: Text(confirmLabel),
              ),
            ],
          ),
        ) ??
        false;
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  void _showError(String message) => _showMessage(message);
}

class _EventHeader extends StatelessWidget {
  const _EventHeader({required this.detail});
  final CentralExamPreparationDetail detail;

  @override
  Widget build(BuildContext context) {
    final event = detail.event;
    final ready = [
      event.committeeCount > 0,
      event.sessionCount > 0,
      event.roomCount > 0,
    ].where((value) => value).length;
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(19),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  event.examType.toUpperCase(),
                  style: const TextStyle(
                    color: NusaColors.accent,
                    fontSize: 9,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.7,
                  ),
                ),
              ),
              _StatusPill(
                label: event.statusLabel,
                color: _statusColor(event.status),
              ),
            ],
          ),
          const SizedBox(height: 5),
          Text(
            event.name,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 17,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            '${event.academicYear} · Semester ${_capital(event.semester)} · ${event.code}',
            style: const TextStyle(color: Colors.white70, fontSize: 10.5),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _HeaderMetric(label: 'Panitia', value: event.committeeCount),
              _HeaderMetric(label: 'Sesi', value: event.sessionCount),
              _HeaderMetric(label: 'Ruang', value: event.roomCount),
              _HeaderMetric(label: 'Kapasitas', value: event.totalCapacity),
            ],
          ),
          const SizedBox(height: 10),
          LinearProgressIndicator(
            value: ready / 3,
            minHeight: 5,
            borderRadius: BorderRadius.circular(8),
            backgroundColor: Colors.white24,
            color: ready == 3 ? NusaColors.success : NusaColors.accent,
          ),
        ],
      ),
    );
  }
}

class _HeaderMetric extends StatelessWidget {
  const _HeaderMetric({required this.label, required this.value});
  final String label;
  final int value;
  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 15,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          style: const TextStyle(color: Colors.white60, fontSize: 8.5),
        ),
      ],
    ),
  );
}

class _InformationTab extends StatelessWidget {
  const _InformationTab({
    required this.detail,
    required this.mutating,
    required this.onEdit,
    required this.onDelete,
  });
  final CentralExamPreparationDetail detail;
  final bool mutating;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final event = detail.event;
    return ListView(
      key: const PageStorageKey('central-exam-information'),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 26),
      children: [
        _SectionCard(
          title: 'Informasi Ujian Terpusat',
          child: Column(
            children: [
              _Fact(label: 'Jenis ujian', value: event.examType),
              _Fact(label: 'Tahun pelajaran', value: event.academicYear),
              _Fact(label: 'Semester', value: _capital(event.semester)),
              _Fact(
                label: 'Periode',
                value: '${_date(event.startsOn)} sampai ${_date(event.endsOn)}',
              ),
              _Fact(label: 'Status', value: event.statusLabel),
            ],
          ),
        ),
        if (event.notes?.trim().isNotEmpty == true) ...[
          const SizedBox(height: 10),
          _SectionCard(
            title: 'Catatan panitia',
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(event.notes!, style: const TextStyle(height: 1.5)),
            ),
          ),
        ],
        const SizedBox(height: 10),
        _SectionCard(
          title: 'Kesiapan tahap 1–4',
          child: Column(
            children: [
              const _ReadinessRow(step: 1, title: 'Informasi', ready: true),
              _ReadinessRow(
                step: 2,
                title: 'Panitia',
                ready: event.committeeCount > 0,
              ),
              _ReadinessRow(
                step: 3,
                title: 'Sesi',
                ready: event.sessionCount > 0,
              ),
              _ReadinessRow(
                step: 4,
                title: 'Ruang',
                ready: event.roomCount > 0,
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        if (onEdit != null)
          FilledButton.icon(
            onPressed: mutating ? null : onEdit,
            icon: const Icon(Icons.edit_outlined),
            label: const Text('Ubah Informasi'),
          ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () => DefaultTabController.of(context).animateTo(4),
          icon: const Icon(Icons.arrow_forward_rounded),
          label: const Text('Lanjut ke Penetapan Ruang'),
        ),
        if (onDelete != null) ...[
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: mutating ? null : onDelete,
            icon: const Icon(Icons.delete_outline_rounded),
            label: const Text('Hapus Kegiatan Persiapan'),
          ),
        ],
      ],
    );
  }
}

class _CommitteeTab extends StatelessWidget {
  const _CommitteeTab({
    required this.detail,
    required this.mutating,
    required this.onAdd,
    required this.onEdit,
    required this.onDelete,
  });
  final CentralExamPreparationDetail detail;
  final bool mutating;
  final VoidCallback? onAdd;
  final ValueChanged<CentralExamCommitteeMember>? onEdit;
  final ValueChanged<CentralExamCommitteeMember>? onDelete;

  @override
  Widget build(BuildContext context) => _StageList<CentralExamCommitteeMember>(
    pageKey: 'central-exam-committee',
    title: 'Tahap 2 · Panitia ujian',
    subtitle: 'Pegawai yang ditugaskan memperoleh akses Panitia Ujian di NUSA.',
    countLabel: '${detail.committee.length} orang',
    addLabel: 'Tambah Panitia',
    onAdd: mutating ? null : onAdd,
    items: detail.committee,
    emptyIcon: Icons.groups_outlined,
    emptyMessage: 'Panitia ujian belum ditentukan.',
    itemBuilder: (item) => _ItemCard(
      code: _initials(item.name),
      title: item.name,
      subtitle:
          '${item.positionLabel} · ${item.employeeNumber ?? 'Tanpa NIP'}${item.hasAccount ? '' : ' · akun belum tersedia'}',
      notes: item.notes,
      active: true,
      onEdit: onEdit == null || mutating ? null : () => onEdit!(item),
      onDelete: onDelete == null || mutating ? null : () => onDelete!(item),
    ),
  );
}

class _SessionTab extends StatelessWidget {
  const _SessionTab({
    required this.detail,
    required this.mutating,
    required this.onAdd,
    required this.onEdit,
    required this.onDelete,
  });
  final CentralExamPreparationDetail detail;
  final bool mutating;
  final VoidCallback? onAdd;
  final ValueChanged<CentralExamSession>? onEdit;
  final ValueChanged<CentralExamSession>? onDelete;

  @override
  Widget build(BuildContext context) => _StageList<CentralExamSession>(
    pageKey: 'central-exam-sessions',
    title: 'Tahap 3 · Sesi ujian',
    subtitle: 'Pembagian waktu ujian dalam satu hari.',
    countLabel: '${detail.sessions.length} sesi',
    addLabel: 'Tambah Sesi',
    onAdd: mutating ? null : onAdd,
    items: detail.sessions,
    emptyIcon: Icons.schedule_outlined,
    emptyMessage: 'Sesi ujian belum ditentukan.',
    itemBuilder: (item) => _ItemCard(
      code: item.code,
      title: item.name,
      subtitle: item.timeLabel,
      notes: item.notes,
      active: item.active,
      onEdit: onEdit == null || mutating ? null : () => onEdit!(item),
      onDelete: onDelete == null || mutating || !item.canDelete
          ? null
          : () => onDelete!(item),
    ),
  );
}

class _RoomTab extends StatelessWidget {
  const _RoomTab({
    required this.detail,
    required this.mutating,
    required this.onAdd,
    required this.onEdit,
    required this.onDelete,
  });
  final CentralExamPreparationDetail detail;
  final bool mutating;
  final VoidCallback? onAdd;
  final ValueChanged<CentralExamRoom>? onEdit;
  final ValueChanged<CentralExamRoom>? onDelete;

  @override
  Widget build(BuildContext context) => _StageList<CentralExamRoom>(
    pageKey: 'central-exam-rooms',
    title: 'Tahap 4 · Ruang ujian',
    subtitle: 'Ruang dipakai untuk seluruh rangkaian ujian.',
    countLabel: '${detail.rooms.length} ruang',
    addLabel: 'Tambah Ruang',
    onAdd: mutating ? null : onAdd,
    items: detail.rooms,
    emptyIcon: Icons.meeting_room_outlined,
    emptyMessage: 'Ruang ujian belum ditentukan.',
    itemBuilder: (item) => _ItemCard(
      code: item.code,
      title: item.name,
      subtitle:
          '${item.location?.trim().isNotEmpty == true ? item.location : 'Lokasi belum diisi'} · Kapasitas ${item.capacity} siswa',
      notes: item.notes,
      active: item.active,
      onEdit: onEdit == null || mutating ? null : () => onEdit!(item),
      onDelete: onDelete == null || mutating || !item.canDelete
          ? null
          : () => onDelete!(item),
    ),
  );
}

class _StageList<T> extends StatelessWidget {
  const _StageList({
    required this.pageKey,
    required this.title,
    required this.subtitle,
    required this.countLabel,
    required this.addLabel,
    required this.onAdd,
    required this.items,
    required this.emptyIcon,
    required this.emptyMessage,
    required this.itemBuilder,
  });
  final String pageKey;
  final String title;
  final String subtitle;
  final String countLabel;
  final String addLabel;
  final VoidCallback? onAdd;
  final List<T> items;
  final IconData emptyIcon;
  final String emptyMessage;
  final Widget Function(T item) itemBuilder;

  @override
  Widget build(BuildContext context) => ListView(
    key: PageStorageKey(pageKey),
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
    children: [
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
              ],
            ),
          ),
          _StatusPill(label: countLabel, color: NusaColors.primary),
        ],
      ),
      if (onAdd != null) ...[
        const SizedBox(height: 11),
        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            onPressed: onAdd,
            icon: const Icon(Icons.add_rounded),
            label: Text(addLabel),
          ),
        ),
      ],
      const SizedBox(height: 11),
      if (items.isEmpty)
        _EmptyCard(icon: emptyIcon, message: emptyMessage)
      else
        for (final item in items)
          Padding(
            padding: const EdgeInsets.only(bottom: 9),
            child: itemBuilder(item),
          ),
    ],
  );
}

class _ItemCard extends StatelessWidget {
  const _ItemCard({
    required this.code,
    required this.title,
    required this.subtitle,
    required this.active,
    required this.onEdit,
    required this.onDelete,
    this.notes,
  });
  final String code;
  final String title;
  final String subtitle;
  final String? notes;
  final bool active;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: NusaColors.primary.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Text(
              code,
              maxLines: 1,
              style: const TextStyle(
                color: NusaColors.primary,
                fontSize: 10,
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
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                if (notes?.trim().isNotEmpty == true) ...[
                  const SizedBox(height: 5),
                  Text(notes!, style: const TextStyle(fontSize: 10.5)),
                ],
                const SizedBox(height: 5),
                Text(
                  active ? 'Aktif' : 'Nonaktif',
                  style: TextStyle(
                    color: active ? NusaColors.success : Colors.grey,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          if (onEdit != null || onDelete != null)
            PopupMenuButton<String>(
              tooltip: 'Tindakan',
              onSelected: (value) =>
                  value == 'edit' ? onEdit?.call() : onDelete?.call(),
              itemBuilder: (context) => [
                if (onEdit != null)
                  const PopupMenuItem(value: 'edit', child: Text('Ubah')),
                if (onDelete != null)
                  const PopupMenuItem(value: 'delete', child: Text('Hapus')),
              ],
            ),
        ],
      ),
    ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child});
  final String title;
  final Widget child;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          child,
        ],
      ),
    ),
  );
}

class _Fact extends StatelessWidget {
  const _Fact({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 118,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _ReadinessRow extends StatelessWidget {
  const _ReadinessRow({
    required this.step,
    required this.title,
    required this.ready,
  });
  final int step;
  final String title;
  final bool ready;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        CircleAvatar(
          radius: 14,
          backgroundColor: (ready ? NusaColors.success : NusaColors.outline)
              .withValues(alpha: ready ? 0.14 : 1),
          child: ready
              ? const Icon(
                  Icons.check_rounded,
                  size: 17,
                  color: NusaColors.success,
                )
              : Text('$step', style: const TextStyle(fontSize: 10)),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        Text(
          ready ? 'Sudah diisi' : 'Belum diisi',
          style: TextStyle(
            color: ready ? NusaColors.success : NusaColors.textSecondary,
            fontSize: 9.5,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800),
    ),
  );
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.icon, required this.message});
  final IconData icon;
  final String message;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          Icon(icon, size: 42, color: NusaColors.textSecondary),
          const SizedBox(height: 9),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 52),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
        ],
      ),
    ),
  );
}

Color _statusColor(String status) => switch (status) {
  'aktif' => NusaColors.success,
  'selesai' => NusaColors.primaryLight,
  'nonaktif' => Colors.grey,
  _ => const Color(0xFFE59A00),
};
String _capital(String value) => value.isEmpty
    ? '-'
    : '${value.substring(0, 1).toUpperCase()}${value.substring(1)}';
String _date(DateTime? value) => value == null
    ? '-'
    : '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
String _initials(String value) {
  final words = value
      .trim()
      .split(RegExp(r'\s+'))
      .where((item) => item.isNotEmpty);
  return words.take(2).map((item) => item.substring(0, 1).toUpperCase()).join();
}

String _message(Object error) => error is AppException
    ? error.message
    : 'Persiapan Ujian Terpusat belum dapat diproses.';
