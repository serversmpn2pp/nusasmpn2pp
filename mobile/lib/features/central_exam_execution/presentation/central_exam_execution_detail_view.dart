import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_execution/application/central_exam_execution_controller.dart';
import 'package:nusa/features/central_exam_execution/domain/central_exam_execution.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class CentralExamExecutionDetailView extends ConsumerStatefulWidget {
  const CentralExamExecutionDetailView({required this.eventId, super.key});
  final int eventId;

  @override
  ConsumerState<CentralExamExecutionDetailView> createState() =>
      _CentralExamExecutionDetailViewState();
}

class _CentralExamExecutionDetailViewState
    extends ConsumerState<CentralExamExecutionDetailView> {
  final _search = TextEditingController();
  Timer? _timer;
  Timer? _searchDebounce;
  String _query = '';
  String _status = 'semua';
  int? _scheduleId;
  int? _roomId;
  int _page = 1;
  bool _autoRefresh = true;
  final Set<int> _unlocking = {};

  CentralExamExecutionRequest get _request => (
    eventId: widget.eventId,
    status: _status,
    scheduleId: _scheduleId,
    roomId: _roomId,
    query: _query,
    page: _page,
  );

  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _searchDebounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    if (!_autoRefresh) return;
    _timer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted) ref.invalidate(centralExamExecutionDetailProvider(_request));
    });
  }

  Future<void> _refresh() async {
    final request = _request;
    ref.invalidate(centralExamExecutionDetailProvider(request));
    await ref.read(centralExamExecutionDetailProvider(request).future);
  }

  @override
  Widget build(BuildContext context) {
    final request = _request;
    final state = ref.watch(centralExamExecutionDetailProvider(request));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pusat Pelaksanaan'),
        actions: [
          IconButton(
            tooltip: 'Nilai & hasil',
            onPressed: () =>
                context.push('/hasil-ujian-terpusat/${widget.eventId}'),
            icon: const Icon(Icons.fact_check_outlined),
          ),
          IconButton(
            key: const Key('central-exam-auto-refresh'),
            tooltip: _autoRefresh
                ? 'Matikan pembaruan otomatis'
                : 'Aktifkan pembaruan otomatis',
            onPressed: () {
              setState(() => _autoRefresh = !_autoRefresh);
              _startTimer();
            },
            icon: Icon(
              _autoRefresh ? Icons.sync_rounded : Icons.sync_disabled_rounded,
            ),
          ),
          IconButton(
            tooltip: 'Perbarui sekarang',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) =>
              _Error(message: _message(error), onRetry: _refresh),
          data: (data) => RefreshIndicator(
            onRefresh: _refresh,
            child: SingleChildScrollView(
              key: const PageStorageKey('central-exam-execution-detail'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _Hero(event: data.event),
                  const SizedBox(height: 10),
                  _LiveStatus(
                    autoRefresh: _autoRefresh,
                    generatedAt: data.generatedAt,
                  ),
                  const SizedBox(height: 10),
                  _SummaryGrid(summary: data.summary),
                  if (data.alerts.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    _SectionTitle(
                      title: 'Perlu Perhatian',
                      trailing: '${data.alerts.length} temuan',
                    ),
                    const SizedBox(height: 8),
                    _Alerts(
                      alerts: data.alerts,
                      canUnlock: data.capabilities.canUnlockSafeMode,
                      onUnlock: _unlockById,
                      onRoom: (id) => context.push('/tugas-pengawas-ujian/$id'),
                    ),
                  ],
                  const SizedBox(height: 16),
                  _SectionTitle(
                    title: 'Jadwal & Ruang',
                    trailing: '${data.schedules.length} jadwal',
                  ),
                  const SizedBox(height: 8),
                  if (data.schedules.isEmpty)
                    const _Empty('Jadwal ujian belum tersedia.')
                  else
                    for (final schedule in data.schedules) ...[
                      _ScheduleCard(
                        schedule: schedule,
                        employees: data.employees,
                        canManageSupervisors:
                            data.capabilities.canManageSupervisors,
                        onAssign: (room, role) =>
                            _assignSupervisor(data, schedule, room, role),
                        onRoom: (room) =>
                            context.push('/tugas-pengawas-ujian/${room.id}'),
                      ),
                      const SizedBox(height: 9),
                    ],
                  const SizedBox(height: 16),
                  _SectionTitle(
                    title: 'Monitoring Peserta',
                    trailing: '${data.participants.pagination.total} siswa',
                  ),
                  const SizedBox(height: 8),
                  _ParticipantFilters(
                    search: _search,
                    statuses: data.statuses,
                    schedules: data.schedules,
                    status: _status,
                    scheduleId: _scheduleId,
                    roomId: _roomId,
                    onSearch: _searchParticipants,
                    onStatus: (value) => setState(() {
                      _status = value;
                      _page = 1;
                    }),
                    onSchedule: (value) => setState(() {
                      _scheduleId = value;
                      _roomId = null;
                      _page = 1;
                    }),
                    onRoom: (value) => setState(() {
                      _roomId = value;
                      _page = 1;
                    }),
                  ),
                  const SizedBox(height: 9),
                  if (data.participants.items.isEmpty)
                    const _Empty('Tidak ada peserta yang sesuai dengan filter.')
                  else
                    for (final participant in data.participants.items) ...[
                      _ParticipantCard(
                        key: Key('central-exam-participant-${participant.id}'),
                        participant: participant,
                        unlocking: _unlocking.contains(participant.id),
                        onUnlock:
                            data.capabilities.canUnlockSafeMode &&
                                participant.canUnlockSafeMode
                            ? () => _unlockParticipant(participant)
                            : null,
                      ),
                      const SizedBox(height: 8),
                    ],
                  if (data.participants.pagination.lastPage > 1)
                    _Pagination(
                      page: data.participants.pagination.page,
                      lastPage: data.participants.pagination.lastPage,
                      onPrevious: _page > 1
                          ? () => setState(() => _page--)
                          : null,
                      onNext: data.participants.pagination.hasNextPage
                          ? () => setState(() => _page++)
                          : null,
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _searchParticipants(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      if (!mounted || _query == value.trim()) return;
      setState(() {
        _query = value.trim();
        _page = 1;
      });
    });
  }

  Future<void> _unlockById(int participantId) async {
    final data = await ref.read(
      centralExamExecutionDetailProvider(_request).future,
    );
    final participant = data.participants.items
        .where((item) => item.id == participantId)
        .firstOrNull;
    await _unlock(participantId, participant?.name ?? 'peserta ini');
  }

  Future<void> _unlockParticipant(CentralExamParticipant participant) async {
    await _unlock(participant.id, participant.name);
  }

  Future<void> _unlock(int participantId, String participantName) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Buka Mode Aman?'),
        content: Text(
          '$participantName dapat melanjutkan ujian. Riwayat keluar aplikasi tetap tersimpan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Buka Ujian'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _unlocking.add(participantId));
    try {
      await ref
          .read(centralExamExecutionActionsProvider)
          .unlockSafeMode(participantId);
      if (!mounted) return;
      _snack('Ujian $participantName sudah dibuka.');
      await _refresh();
    } catch (error) {
      if (mounted) _snack(_message(error, 'Ujian belum dapat dibuka.'));
    } finally {
      if (mounted) setState(() => _unlocking.remove(participantId));
    }
  }

  Future<void> _assignSupervisor(
    CentralExamExecutionDetail data,
    CentralExamSchedule schedule,
    CentralExamRoom room,
    String role,
  ) async {
    final current = role == 'utama'
        ? room.primarySupervisor
        : room.secondarySupervisor;
    final result =
        await showModalBottomSheet<({int employeeId, String reason})>(
          context: context,
          isScrollControlled: true,
          useSafeArea: true,
          builder: (context) => _AssignSupervisorSheet(
            role: role,
            room: room,
            current: current,
            employees: data.employees,
          ),
        );
    if (result == null || !mounted) return;
    try {
      final message = await ref
          .read(centralExamExecutionActionsProvider)
          .assignSupervisor(
            eventId: widget.eventId,
            scheduleId: schedule.id,
            sourceRoomId: room.sourceRoomId,
            role: role,
            employeeId: result.employeeId,
            reason: result.reason,
          );
      if (!mounted) return;
      _snack(message);
      await _refresh();
    } catch (error) {
      if (mounted) _snack(_message(error, 'Pengawas belum dapat diperbarui.'));
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _Hero extends StatelessWidget {
  const _Hero({required this.event});
  final CentralExamExecutionEvent event;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: NusaColors.accent.withValues(alpha: 0.18),
            borderRadius: BorderRadius.circular(15),
          ),
          child: const Icon(Icons.hub_rounded, color: NusaColors.accent),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                event.type ?? 'UJIAN TERPUSAT',
                style: const TextStyle(
                  color: NusaColors.accent,
                  fontSize: 9,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 0.7,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                event.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                '${event.academicYear ?? '-'} · Semester ${event.semester} · ${event.period}',
                style: const TextStyle(color: Colors.white70, fontSize: 9.5),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _LiveStatus extends StatelessWidget {
  const _LiveStatus({required this.autoRefresh, required this.generatedAt});
  final bool autoRefresh;
  final String? generatedAt;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
    decoration: BoxDecoration(
      color: autoRefresh ? NusaColors.successSurface : Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(
        color: autoRefresh
            ? NusaColors.success.withValues(alpha: 0.24)
            : NusaColors.outline,
      ),
    ),
    child: Row(
      children: [
        Icon(
          autoRefresh ? Icons.sensors_rounded : Icons.pause_circle_outline,
          color: autoRefresh ? NusaColors.success : NusaColors.textSecondary,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            autoRefresh
                ? 'Data server diperbarui otomatis setiap 15 detik.'
                : 'Pembaruan otomatis sedang dimatikan.',
            style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700),
          ),
        ),
        Text(
          _time(generatedAt),
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 9),
        ),
      ],
    ),
  );
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.summary});
  final CentralExamExecutionSummary summary;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final columns = constraints.maxWidth >= 520 ? 4 : 2;
      return GridView.count(
        crossAxisCount: columns,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 8,
        crossAxisSpacing: 8,
        childAspectRatio: columns == 4 ? 1.6 : 1.72,
        children: [
          _SummaryItem(
            'Total peserta',
            summary.total,
            Icons.groups_rounded,
            NusaColors.primary,
          ),
          _SummaryItem(
            'Sedang ujian',
            summary.working,
            Icons.edit_note_rounded,
            NusaColors.primaryLight,
          ),
          _SummaryItem(
            'Selesai',
            summary.finished,
            Icons.task_alt_rounded,
            NusaColors.success,
          ),
          _SummaryItem(
            'Mode Aman',
            summary.blocked,
            Icons.gpp_bad_rounded,
            Colors.red.shade700,
          ),
          _SummaryItem(
            'Belum hadir',
            summary.notPresent,
            Icons.person_off_outlined,
            NusaColors.textSecondary,
          ),
          _SummaryItem(
            'Hadir, belum mulai',
            summary.presentNotStarted,
            Icons.hourglass_top_rounded,
            const Color(0xFF9A7000),
          ),
          _SummaryItem(
            'Ruang berjalan',
            summary.runningRoomCount,
            Icons.meeting_room_rounded,
            NusaColors.primary,
          ),
          _SummaryItem(
            'Bukti diperiksa',
            summary.pendingEvidenceCount,
            Icons.fact_check_outlined,
            const Color(0xFF9A7000),
          ),
        ],
      );
    },
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem(this.label, this.value, this.icon, this.color);
  final String label;
  final int value;
  final IconData icon;
  final Color color;
  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(10),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 19, color: color),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$value',
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  label,
                  maxLines: 2,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 8.5,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _Alerts extends StatelessWidget {
  const _Alerts({
    required this.alerts,
    required this.canUnlock,
    required this.onUnlock,
    required this.onRoom,
  });
  final List<CentralExamAlert> alerts;
  final bool canUnlock;
  final ValueChanged<int> onUnlock;
  final ValueChanged<int> onRoom;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Column(
      children: [
        for (var index = 0; index < alerts.length; index++) ...[
          ListTile(
            dense: true,
            leading: Icon(
              _alertIcon(alerts[index].type),
              color: _alertColor(alerts[index].type),
            ),
            title: Text(
              alerts[index].title,
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900),
            ),
            subtitle: Text(
              alerts[index].description,
              style: const TextStyle(fontSize: 9.5),
            ),
            trailing:
                alerts[index].type == 'mode_aman' &&
                    canUnlock &&
                    alerts[index].participantId != null
                ? TextButton(
                    onPressed: () => onUnlock(alerts[index].participantId!),
                    child: const Text('Buka'),
                  )
                : alerts[index].roomId != null
                ? IconButton(
                    tooltip: 'Buka ruang',
                    onPressed: () => onRoom(alerts[index].roomId!),
                    icon: const Icon(Icons.chevron_right_rounded),
                  )
                : null,
          ),
          if (index < alerts.length - 1) const Divider(height: 1),
        ],
      ],
    ),
  );
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({
    required this.schedule,
    required this.employees,
    required this.canManageSupervisors,
    required this.onAssign,
    required this.onRoom,
  });
  final CentralExamSchedule schedule;
  final List<CentralExamEmployee> employees;
  final bool canManageSupervisors;
  final void Function(CentralExamRoom, String) onAssign;
  final ValueChanged<CentralExamRoom> onRoom;

  @override
  Widget build(BuildContext context) {
    final package = schedule.package;
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        key: Key('central-exam-schedule-${schedule.id}'),
        initiallyExpanded: schedule.rooms.any(
          (room) => room.status == 'berlangsung' || room.summary.blocked > 0,
        ),
        tilePadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 3),
        childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: NusaColors.primary.withValues(alpha: 0.09),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Icon(
            Icons.event_note_rounded,
            color: NusaColors.primary,
          ),
        ),
        title: Text(
          '${schedule.subject} · Tingkat ${schedule.grade}',
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
        ),
        subtitle: Text(
          '${_date(schedule.date)} · ${schedule.time} · ${schedule.session ?? '-'}',
          style: const TextStyle(fontSize: 9.5),
        ),
        children: [
          if (package == null)
            const _Empty('Paket soal belum diterbitkan untuk jadwal ini.')
          else ...[
            _TokenCard(package: package),
            const SizedBox(height: 9),
            if (schedule.rooms.isEmpty)
              const _Empty('Ruang operasional belum tersinkron.')
            else
              for (final room in schedule.rooms) ...[
                _RoomCard(
                  room: room,
                  canManage: canManageSupervisors && employees.isNotEmpty,
                  onAssign: (role) => onAssign(room, role),
                  onOpen: () => onRoom(room),
                ),
                const SizedBox(height: 7),
              ],
          ],
        ],
      ),
    );
  }
}

class _TokenCard extends StatelessWidget {
  const _TokenCard({required this.package});
  final CentralExamPackage package;
  @override
  Widget build(BuildContext context) {
    final token = package.requiresToken
        ? (package.token ?? '-')
        : 'Tanpa token';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: NusaColors.accent.withValues(alpha: 0.11),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: NusaColors.accent.withValues(alpha: 0.35)),
      ),
      child: Row(
        children: [
          const Icon(Icons.key_rounded, color: Color(0xFF9A7000)),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'TOKEN PESERTA',
                  style: TextStyle(
                    color: Color(0xFF806000),
                    fontSize: 8.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  token,
                  style: const TextStyle(
                    color: NusaColors.primaryDark,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 2,
                  ),
                ),
              ],
            ),
          ),
          if (package.requiresToken && package.token != null)
            IconButton(
              key: Key('copy-token-${package.id}'),
              tooltip: 'Salin token',
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: package.token!));
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Token ujian disalin.')),
                  );
                }
              },
              icon: const Icon(Icons.copy_rounded, size: 20),
            ),
        ],
      ),
    );
  }
}

class _RoomCard extends StatelessWidget {
  const _RoomCard({
    required this.room,
    required this.canManage,
    required this.onAssign,
    required this.onOpen,
  });
  final CentralExamRoom room;
  final bool canManage;
  final ValueChanged<String> onAssign;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(
        color: room.summary.blocked > 0
            ? Colors.red.shade200
            : NusaColors.outline,
      ),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(
              Icons.meeting_room_rounded,
              size: 19,
              color: NusaColors.primary,
            ),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                room.name,
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
            _StatusPill(room.statusLabel, _roomStatusColor(room.status)),
          ],
        ),
        const SizedBox(height: 7),
        _SupervisorRow(
          label: 'Utama',
          employee: room.primarySupervisor,
          canManage: canManage,
          onTap: () => onAssign('utama'),
        ),
        const SizedBox(height: 4),
        _SupervisorRow(
          label: 'Pendamping',
          employee: room.secondarySupervisor,
          canManage: canManage,
          onTap: () => onAssign('pendamping'),
        ),
        const Divider(height: 17),
        Wrap(
          spacing: 9,
          runSpacing: 5,
          children: [
            _TinyMetric('Total', room.summary.total, NusaColors.primary),
            _TinyMetric(
              'Mengerjakan',
              room.summary.working,
              NusaColors.primaryLight,
            ),
            _TinyMetric('Selesai', room.summary.finished, NusaColors.success),
            if (room.summary.blocked > 0)
              _TinyMetric(
                'Terblokir',
                room.summary.blocked,
                Colors.red.shade700,
              ),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: Text(
                'Bukti: ${room.evidenceStatusLabel}',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9.5,
                ),
              ),
            ),
            TextButton.icon(
              key: Key('open-central-exam-room-${room.id}'),
              onPressed: onOpen,
              icon: const Icon(Icons.open_in_new_rounded, size: 16),
              label: const Text('Detail Ruang'),
            ),
          ],
        ),
      ],
    ),
  );
}

class _SupervisorRow extends StatelessWidget {
  const _SupervisorRow({
    required this.label,
    required this.employee,
    required this.canManage,
    required this.onTap,
  });
  final String label;
  final CentralExamEmployee? employee;
  final bool canManage;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      SizedBox(
        width: 72,
        child: Text(
          label,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
      ),
      Expanded(
        child: Text(
          employee?.name ?? 'Belum ditentukan',
          style: TextStyle(
            fontSize: 10.5,
            fontWeight: FontWeight.w800,
            color: employee == null
                ? Colors.red.shade700
                : NusaColors.textPrimary,
          ),
        ),
      ),
      if (canManage)
        IconButton(
          visualDensity: VisualDensity.compact,
          tooltip: employee == null ? 'Tentukan pengawas' : 'Ganti pengawas',
          onPressed: onTap,
          icon: Icon(
            employee == null
                ? Icons.person_add_alt_1_rounded
                : Icons.swap_horiz_rounded,
            size: 18,
          ),
        ),
    ],
  );
}

class _ParticipantFilters extends StatelessWidget {
  const _ParticipantFilters({
    required this.search,
    required this.statuses,
    required this.schedules,
    required this.status,
    required this.scheduleId,
    required this.roomId,
    required this.onSearch,
    required this.onStatus,
    required this.onSchedule,
    required this.onRoom,
  });
  final TextEditingController search;
  final List<CentralExamOption> statuses;
  final List<CentralExamSchedule> schedules;
  final String status;
  final int? scheduleId;
  final int? roomId;
  final ValueChanged<String> onSearch;
  final ValueChanged<String> onStatus;
  final ValueChanged<int?> onSchedule;
  final ValueChanged<int?> onRoom;

  @override
  Widget build(BuildContext context) {
    final rooms = scheduleId == null
        ? schedules.expand((item) => item.rooms).toList()
        : schedules
              .where((item) => item.id == scheduleId)
              .expand((item) => item.rooms)
              .toList();
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(11),
        child: Column(
          children: [
            NusaTextField(
              fieldKey: const Key('central-exam-participant-search'),
              controller: search,
              hintText: 'Cari nama atau nomor peserta',
              prefixIcon: Icons.search_rounded,
              onChanged: onSearch,
            ),
            const SizedBox(height: 8),
            NusaDropdownField<String>(
              fieldKey: const Key('central-exam-participant-status'),
              value: status,
              decoration: const InputDecoration(labelText: 'Status peserta'),
              options: [
                for (final item in statuses)
                  NusaDropdownOption(value: item.code, label: item.label),
              ],
              onChanged: (value) {
                if (value != null) onStatus(value);
              },
            ),
            const SizedBox(height: 8),
            NusaDropdownField<int?>(
              fieldKey: const Key('central-exam-participant-schedule'),
              value: scheduleId,
              decoration: const InputDecoration(labelText: 'Jadwal'),
              options: [
                const NusaDropdownOption(value: null, label: 'Semua jadwal'),
                for (final item in schedules)
                  NusaDropdownOption(
                    value: item.id,
                    label: '${item.subject} · ${_date(item.date)} ${item.time}',
                  ),
              ],
              onChanged: onSchedule,
            ),
            const SizedBox(height: 8),
            NusaDropdownField<int?>(
              fieldKey: const Key('central-exam-participant-room'),
              value: roomId,
              decoration: const InputDecoration(labelText: 'Ruang'),
              options: [
                const NusaDropdownOption(value: null, label: 'Semua ruang'),
                for (final item in rooms)
                  NusaDropdownOption(value: item.id, label: item.name),
              ],
              onChanged: onRoom,
            ),
          ],
        ),
      ),
    );
  }
}

class _ParticipantCard extends StatelessWidget {
  const _ParticipantCard({
    required this.participant,
    required this.unlocking,
    this.onUnlock,
    super.key,
  });
  final CentralExamParticipant participant;
  final bool unlocking;
  final VoidCallback? onUnlock;

  @override
  Widget build(BuildContext context) {
    final color = _participantColor(participant.status);
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 39,
                  height: 39,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(Icons.person_rounded, color: color),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        participant.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        '${participant.className} · ${participant.room} · ${participant.subject}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 9.5,
                        ),
                      ),
                    ],
                  ),
                ),
                _StatusPill(participant.statusLabel, color),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 11,
              runSpacing: 5,
              children: [
                _Info(
                  Icons.confirmation_number_outlined,
                  participant.participantNumber,
                ),
                _Info(
                  Icons.save_rounded,
                  '${participant.savedAnswerCount} jawaban',
                ),
                if (participant.appSwitchCount > 0)
                  _Info(
                    Icons.mobile_off_rounded,
                    '${participant.appSwitchCount} pindah aplikasi',
                  ),
                if (participant.staleHeartbeat)
                  const _Info(
                    Icons.wifi_off_rounded,
                    'Koneksi perlu diperiksa',
                    color: Colors.red,
                  ),
              ],
            ),
            if (onUnlock != null) ...[
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  key: Key('unlock-central-exam-${participant.id}'),
                  onPressed: unlocking ? null : onUnlock,
                  icon: unlocking
                      ? const SizedBox.square(
                          dimension: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.lock_open_rounded),
                  label: const Text('Buka Mode Aman'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _AssignSupervisorSheet extends StatefulWidget {
  const _AssignSupervisorSheet({
    required this.role,
    required this.room,
    required this.current,
    required this.employees,
  });
  final String role;
  final CentralExamRoom room;
  final CentralExamEmployee? current;
  final List<CentralExamEmployee> employees;

  @override
  State<_AssignSupervisorSheet> createState() => _AssignSupervisorSheetState();
}

class _AssignSupervisorSheetState extends State<_AssignSupervisorSheet> {
  final _reason = TextEditingController();
  int? _employeeId;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final replacement = widget.current != null;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        18,
        14,
        18,
        MediaQuery.viewInsetsOf(context).bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: NusaColors.outline,
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
            ),
            const SizedBox(height: 14),
            Text(
              replacement
                  ? 'Ganti Pengawas ${_role(widget.role)}'
                  : 'Tentukan Pengawas ${_role(widget.role)}',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              '${widget.room.name}${replacement ? ' · saat ini ${widget.current!.name}' : ''}',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11,
              ),
            ),
            const SizedBox(height: 14),
            NusaDropdownField<int?>(
              fieldKey: const Key('central-exam-supervisor-employee'),
              value: _employeeId,
              decoration: const InputDecoration(
                labelText: 'Pegawai pengawas',
                prefixIcon: Icon(Icons.person_rounded),
              ),
              options: [
                const NusaDropdownOption(value: null, label: 'Pilih pegawai'),
                for (final employee in widget.employees)
                  NusaDropdownOption(value: employee.id, label: employee.name),
              ],
              onChanged: (value) => setState(() => _employeeId = value),
            ),
            if (replacement) ...[
              const SizedBox(height: 10),
              TextField(
                key: const Key('central-exam-supervisor-reason'),
                controller: _reason,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Alasan penggantian',
                  hintText: 'Minimal 5 karakter agar riwayat tugas jelas',
                ),
              ),
            ],
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                key: const Key('central-exam-supervisor-submit'),
                onPressed: _employeeId == null
                    ? null
                    : () {
                        if (replacement && _reason.text.trim().length < 5) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text(
                                'Alasan penggantian minimal 5 karakter.',
                              ),
                            ),
                          );
                          return;
                        }
                        Navigator.pop(context, (
                          employeeId: _employeeId!,
                          reason: _reason.text.trim(),
                        ));
                      },
                child: Text(
                  replacement ? 'Simpan Pergantian' : 'Tetapkan Pengawas',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Pagination extends StatelessWidget {
  const _Pagination({
    required this.page,
    required this.lastPage,
    this.onPrevious,
    this.onNext,
  });
  final int page;
  final int lastPage;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 10),
    child: Row(
      children: [
        OutlinedButton(
          onPressed: onPrevious,
          child: const Icon(Icons.chevron_left_rounded),
        ),
        Expanded(
          child: Text(
            'Halaman $page dari $lastPage',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
          ),
        ),
        OutlinedButton(
          onPressed: onNext,
          child: const Icon(Icons.chevron_right_rounded),
        ),
      ],
    ),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.trailing});
  final String title;
  final String trailing;
  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(
          title,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
        ),
      ),
      Text(
        trailing,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10),
      ),
    ],
  );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill(this.label, this.color);
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: color,
        fontSize: 8.5,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _TinyMetric extends StatelessWidget {
  const _TinyMetric(this.label, this.value, this.color);
  final String label;
  final int value;
  final Color color;
  @override
  Widget build(BuildContext context) => Text(
    '$label $value',
    style: TextStyle(color: color, fontSize: 9.5, fontWeight: FontWeight.w800),
  );
}

class _Info extends StatelessWidget {
  const _Info(this.icon, this.text, {this.color});
  final IconData icon;
  final String text;
  final Color? color;
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: color ?? NusaColors.textSecondary),
      const SizedBox(width: 4),
      Text(
        text,
        style: TextStyle(
          color: color ?? NusaColors.textSecondary,
          fontSize: 9.5,
        ),
      ),
    ],
  );
}

class _Empty extends StatelessWidget {
  const _Empty(this.message);
  final String message;
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Text(
      message,
      textAlign: TextAlign.center,
      style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
    ),
  );
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

String _message(
  Object error, [
  String fallback = 'Pusat pelaksanaan ujian belum dapat dimuat.',
]) => error is AppException ? error.message : fallback;
String _role(String value) => value == 'utama' ? 'Utama' : 'Pendamping';
String _time(String? value) {
  final date = value == null ? null : DateTime.tryParse(value)?.toLocal();
  return date == null
      ? '-'
      : '${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
}

String _date(String? value) {
  final date = value == null ? null : DateTime.tryParse(value);
  if (date == null) return '-';
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

Color _participantColor(String status) => switch (status) {
  'selesai' => NusaColors.success,
  'sedang_mengerjakan' => NusaColors.primaryLight,
  'terblokir' => Colors.red.shade700,
  'tidak_hadir' => Colors.red.shade700,
  'hadir_belum_mulai' => const Color(0xFF9A7000),
  _ => NusaColors.textSecondary,
};
Color _roomStatusColor(String status) => switch (status) {
  'berlangsung' => NusaColors.primaryLight,
  'selesai' => NusaColors.success,
  'nonaktif' => NusaColors.textSecondary,
  _ => const Color(0xFF9A7000),
};
IconData _alertIcon(String type) => switch (type) {
  'mode_aman' => Icons.gpp_bad_rounded,
  'heartbeat' => Icons.wifi_off_rounded,
  _ => Icons.person_off_rounded,
};
Color _alertColor(String type) => switch (type) {
  'mode_aman' || 'heartbeat' => Colors.red.shade700,
  _ => const Color(0xFF9A7000),
};
