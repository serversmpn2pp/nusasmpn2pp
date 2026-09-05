import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/exam_supervision/application/exam_supervision_controller.dart';
import 'package:nusa/features/exam_supervision/data/exam_supervision_file_picker.dart';
import 'package:nusa/features/exam_supervision/domain/exam_supervision.dart';

class ExamSupervisionDetailView extends ConsumerStatefulWidget {
  const ExamSupervisionDetailView({required this.roomId, super.key});

  final int roomId;

  @override
  ConsumerState<ExamSupervisionDetailView> createState() =>
      _ExamSupervisionDetailViewState();
}

class _ExamSupervisionDetailViewState
    extends ConsumerState<ExamSupervisionDetailView> {
  final _searchController = TextEditingController();
  Timer? _timer;
  String _query = '';
  String _status = 'semua';
  bool _autoRefresh = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    if (!_autoRefresh) return;
    _timer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted && !_busy) {
        ref.invalidate(examSupervisionDetailProvider(widget.roomId));
      }
    });
  }

  Future<void> _refresh() async {
    ref.invalidate(examSupervisionDetailProvider(widget.roomId));
    await ref.read(examSupervisionDetailProvider(widget.roomId).future);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(examSupervisionDetailProvider(widget.roomId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Ruang Ujian'),
        actions: [
          IconButton(
            key: const Key('supervision-auto-refresh'),
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
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: _message(error, 'Tugas pengawas belum dapat dimuat.'),
            onRetry: _refresh,
          ),
          data: (data) => RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              key: const PageStorageKey<String>('exam-supervision-detail'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                _RoomHero(room: data.room),
                const SizedBox(height: 11),
                _LiveStrip(data: data, autoRefresh: _autoRefresh, busy: _busy),
                const SizedBox(height: 11),
                _RoomActions(
                  room: data.room,
                  capabilities: data.capabilities,
                  busy: _busy,
                  onStart: () => _changeRoomStatus(data, 'mulai'),
                  onFinish: () => _changeRoomStatus(data, 'selesai'),
                  onNotes: () => _editNotes(data),
                ),
                const SizedBox(height: 11),
                _SummaryGrid(summary: data.summary),
                const SizedBox(height: 18),
                const _SectionTitle(
                  title: 'Peserta Ruang',
                  subtitle: 'Presensi, perangkat, progres, dan Mode Aman',
                ),
                const SizedBox(height: 9),
                _ParticipantFilters(
                  controller: _searchController,
                  selectedStatus: _status,
                  onQuery: (value) => setState(() => _query = value.trim()),
                  onStatus: (value) => setState(() => _status = value),
                ),
                const SizedBox(height: 10),
                ..._participants(data).map(
                  (participant) => Padding(
                    padding: const EdgeInsets.only(bottom: 9),
                    child: _ParticipantCard(
                      participant: participant,
                      capabilities: data.capabilities,
                      busy: _busy,
                      onAttendance: () => _editAttendance(data, participant),
                      onResetDevice: () => _resetDevice(data, participant),
                      onUnlock: () => _unlock(data, participant),
                    ),
                  ),
                ),
                if (_participants(data).isEmpty)
                  const _EmptyCard(
                    icon: Icons.manage_search_rounded,
                    message: 'Tidak ada peserta yang sesuai pencarian.',
                  ),
                const SizedBox(height: 9),
                const _SectionTitle(
                  title: 'Bukti Ruang',
                  subtitle: 'Daftar hadir dan berita acara untuk panitia',
                ),
                const SizedBox(height: 9),
                _EvidenceSection(
                  data: data,
                  busy: _busy,
                  onUpload: (type) => _uploadEvidence(data, type),
                  onDelete: (evidence) => _deleteEvidence(data, evidence),
                  onSubmit: () => _submitEvidence(data),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  List<SupervisionParticipant> _participants(ExamSupervisionDetail data) {
    final query = _query.toLowerCase();
    return data.participants.where((participant) {
      final matchesQuery =
          query.isEmpty ||
          participant.name.toLowerCase().contains(query) ||
          (participant.nisn ?? '').contains(query) ||
          participant.className.toLowerCase().contains(query) ||
          (participant.participantNumber ?? '').toLowerCase().contains(query);
      final matchesStatus =
          _status == 'semua' ||
          participant.status == _status ||
          participant.attendance == _status;
      return matchesQuery && matchesStatus;
    }).toList();
  }

  Future<void> _changeRoomStatus(
    ExamSupervisionDetail data,
    String action,
  ) async {
    final finish = action == 'selesai';
    final confirmed = await _confirm(
      title: finish ? 'Selesaikan ruang?' : 'Mulai pelaksanaan?',
      message: finish
          ? 'Pastikan tidak ada peserta yang masih mengerjakan. Setelah selesai, presensi dan perangkat tidak dapat diubah.'
          : 'Waktu mulai aktual akan dicatat dari waktu server.',
      action: finish ? 'Selesaikan' : 'Mulai',
      dangerous: finish,
    );
    if (!confirmed) return;
    await _run(
      () => ref
          .read(examSupervisionActionsProvider)
          .changeRoomStatus(data.room.id, action),
      finish ? 'Ruang ujian selesai.' : 'Pelaksanaan ruang dimulai.',
    );
  }

  Future<void> _editNotes(ExamSupervisionDetail data) async {
    final result = await showModalBottomSheet<_NotesResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _NotesSheet(room: data.room),
    );
    if (result == null) return;
    await _run(
      () => ref
          .read(examSupervisionActionsProvider)
          .saveNotes(
            roomId: data.room.id,
            minutes: result.minutes,
            obstacles: result.obstacles,
            followUp: result.followUp,
            notes: result.notes,
          ),
      'Catatan ruang berhasil disimpan.',
    );
  }

  Future<void> _editAttendance(
    ExamSupervisionDetail data,
    SupervisionParticipant participant,
  ) async {
    final result = await showModalBottomSheet<_AttendanceResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _AttendanceSheet(
        participant: participant,
        options: data.attendanceOptions,
      ),
    );
    if (result == null) return;
    await _run(
      () => ref
          .read(examSupervisionActionsProvider)
          .changeAttendance(
            roomId: data.room.id,
            participantId: participant.id,
            status: result.status,
            notes: result.notes,
          ),
      'Presensi ${participant.name} diperbarui.',
    );
  }

  Future<void> _resetDevice(
    ExamSupervisionDetail data,
    SupervisionParticipant participant,
  ) async {
    final confirmed = await _confirm(
      title: 'Reset perangkat siswa?',
      message:
          '${participant.name} dapat masuk dari perangkat baru. Riwayat keamanan yang sudah tercatat tidak dihapus.',
      action: 'Reset Perangkat',
      dangerous: true,
    );
    if (!confirmed) return;
    await _run(
      () => ref
          .read(examSupervisionActionsProvider)
          .resetDevice(roomId: data.room.id, participantId: participant.id),
      'Ikatan perangkat ${participant.name} sudah direset.',
    );
  }

  Future<void> _unlock(
    ExamSupervisionDetail data,
    SupervisionParticipant participant,
  ) async {
    final confirmed = await _confirm(
      title: 'Buka Mode Aman?',
      message:
          '${participant.name} dapat melanjutkan ujian. Jumlah keluar aplikasi tetap tersimpan.',
      action: 'Buka Ujian',
    );
    if (!confirmed) return;
    await _run(() async {
      await ref
          .read(examSupervisionActionsProvider)
          .unlockSafeMode(participant.id);
      return data;
    }, 'Ujian ${participant.name} sudah dibuka.');
  }

  Future<void> _uploadEvidence(ExamSupervisionDetail data, String type) async {
    final source = await showModalBottomSheet<_EvidenceSource>(
      context: context,
      useSafeArea: true,
      builder: (context) => const _EvidenceSourceSheet(),
    );
    if (source == null || !mounted) return;
    try {
      final picker = ref.read(examSupervisionFilePickerProvider);
      final file = source == _EvidenceSource.camera
          ? await picker.camera()
          : await picker.file();
      if (file == null || !mounted) return;
      if (file.bytes.length > 10 * 1024 * 1024) {
        _snack('Ukuran berkas melebihi batas 10 MB.', error: true);
        return;
      }
      await _run(
        () => ref
            .read(examSupervisionActionsProvider)
            .uploadEvidence(roomId: data.room.id, type: type, file: file),
        'Bukti berhasil diunggah.',
      );
    } catch (error) {
      if (mounted) {
        _snack(_message(error, 'Berkas belum dapat dipilih.'), error: true);
      }
    }
  }

  Future<void> _deleteEvidence(
    ExamSupervisionDetail data,
    SupervisionEvidence evidence,
  ) async {
    final confirmed = await _confirm(
      title: 'Hapus bukti?',
      message: evidence.fileName,
      action: 'Hapus',
      dangerous: true,
    );
    if (!confirmed) return;
    await _run(
      () => ref
          .read(examSupervisionActionsProvider)
          .deleteEvidence(roomId: data.room.id, evidenceId: evidence.id),
      'Bukti berhasil dihapus.',
    );
  }

  Future<void> _submitEvidence(ExamSupervisionDetail data) async {
    final confirmed = await _confirm(
      title: 'Kirim bukti ke panitia?',
      message: 'Setelah dikirim, seluruh berkas dikunci sampai panitia mengembalikannya untuk diperbaiki.',
      action: 'Kirim Bukti',
    );
    if (!confirmed) return;
    await _run(
      () =>
          ref.read(examSupervisionActionsProvider).submitEvidence(data.room.id),
      'Bukti dikirim dan menunggu pemeriksaan panitia.',
    );
  }

  Future<void> _run(
    Future<ExamSupervisionDetail> Function() operation,
    String success,
  ) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await operation();
      if (!mounted) return;
      ref.invalidate(examSupervisionDetailProvider(widget.roomId));
      await ref.read(examSupervisionDetailProvider(widget.roomId).future);
      if (mounted) _snack(success);
    } catch (error) {
      if (mounted) {
        _snack(_message(error, 'Perubahan belum dapat disimpan.'), error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<bool> _confirm({
    required String title,
    required String message,
    required String action,
    bool dangerous = false,
  }) async =>
      await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Batal'),
            ),
            FilledButton(
              style: dangerous
                  ? FilledButton.styleFrom(backgroundColor: Colors.red.shade700)
                  : null,
              onPressed: () => Navigator.pop(context, true),
              child: Text(action),
            ),
          ],
        ),
      ) ??
      false;

  void _snack(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? Colors.red.shade700 : null,
      ),
    );
  }
}

class _RoomHero extends StatelessWidget {
  const _RoomHero({required this.room});

  final SupervisionRoom room;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.2),
          blurRadius: 20,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Stack(
      children: [
        Positioned(
          right: -18,
          bottom: -30,
          child: Icon(
            Icons.meeting_room_rounded,
            size: 126,
            color: Colors.white.withValues(alpha: 0.06),
          ),
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                _Pill(label: room.myRole, color: NusaColors.accent),
                _Pill(label: room.statusLabel, color: Colors.white),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              room.activity,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 19,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              '${room.subject}${room.level > 0 ? ' · Tingkat ${room.level}' : ''}',
              style: const TextStyle(
                color: NusaColors.accent,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 13),
            Wrap(
              spacing: 13,
              runSpacing: 8,
              children: [
                _HeroInfo(
                  icon: Icons.calendar_today_rounded,
                  text: _date(room.date),
                ),
                _HeroInfo(icon: Icons.schedule_rounded, text: room.time ?? '-'),
                _HeroInfo(
                  icon: Icons.room_rounded,
                  text: '${room.code} · ${room.name}',
                ),
                if (room.location != null)
                  _HeroInfo(icon: Icons.place_outlined, text: room.location!),
              ],
            ),
          ],
        ),
      ],
    ),
  );
}

class _HeroInfo extends StatelessWidget {
  const _HeroInfo({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => ConstrainedBox(
    constraints: BoxConstraints(
      maxWidth: MediaQuery.sizeOf(context).width - 68,
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: Colors.white70, size: 15),
        const SizedBox(width: 5),
        Flexible(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white70, fontSize: 10.5),
          ),
        ),
      ],
    ),
  );
}

class _LiveStrip extends StatelessWidget {
  const _LiveStrip({
    required this.data,
    required this.autoRefresh,
    required this.busy,
  });

  final ExamSupervisionDetail data;
  final bool autoRefresh;
  final bool busy;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.primary.withValues(alpha: 0.12)),
    ),
    child: Row(
      children: [
        Icon(
          busy ? Icons.hourglass_top_rounded : Icons.sensors_rounded,
          size: 19,
          color: busy ? const Color(0xFF9A7000) : NusaColors.success,
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            busy
                ? 'Menyimpan perubahan...'
                : autoRefresh
                ? 'Status peserta diperbarui otomatis setiap 15 detik'
                : 'Pembaruan otomatis dinonaktifkan',
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        if (data.generatedAt != null)
          Text(
            _clock(data.generatedAt),
            style: const TextStyle(
              color: NusaColors.primary,
              fontSize: 10,
              fontWeight: FontWeight.w800,
            ),
          ),
      ],
    ),
  );
}

class _RoomActions extends StatelessWidget {
  const _RoomActions({
    required this.room,
    required this.capabilities,
    required this.busy,
    required this.onStart,
    required this.onFinish,
    required this.onNotes,
  });

  final SupervisionRoom room;
  final SupervisionCapabilities capabilities;
  final bool busy;
  final VoidCallback onStart;
  final VoidCallback onFinish;
  final VoidCallback onNotes;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Kontrol Ruang',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              _TonePill(label: room.statusLabel, tone: room.status),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            room.status == 'selesai'
                ? 'Selesai ${_dateTime(room.actualEnd)}'
                : room.actualStart != null
                ? 'Dimulai ${_dateTime(room.actualStart)}'
                : 'Waktu aktual belum dicatat.',
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (capabilities.manageRoom &&
                  room.status != 'berlangsung' &&
                  room.status != 'selesai')
                FilledButton.icon(
                  key: const Key('supervision-start-room'),
                  onPressed: busy ? null : onStart,
                  icon: const Icon(Icons.play_arrow_rounded),
                  label: const Text('Mulai Ruang'),
                ),
              if (capabilities.manageRoom && room.status == 'berlangsung')
                FilledButton.icon(
                  key: const Key('supervision-finish-room'),
                  style: FilledButton.styleFrom(
                    backgroundColor: NusaColors.success,
                  ),
                  onPressed: busy ? null : onFinish,
                  icon: const Icon(Icons.task_alt_rounded),
                  label: const Text('Selesaikan'),
                ),
              OutlinedButton.icon(
                key: const Key('supervision-room-notes'),
                onPressed: busy ? null : onNotes,
                icon: const Icon(Icons.description_outlined),
                label: const Text('Catatan Ruang'),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.summary});

  final SupervisionSummary summary;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final width = (constraints.maxWidth - 9) / 2;
      return Wrap(
        spacing: 9,
        runSpacing: 9,
        children: [
          _Metric(
            width: width,
            label: 'Hadir / Total',
            value: '${summary.present}/${summary.total}',
            icon: Icons.how_to_reg_rounded,
            color: NusaColors.success,
          ),
          _Metric(
            width: width,
            label: 'Mengerjakan',
            value: '${summary.working}',
            icon: Icons.edit_note_rounded,
            color: NusaColors.primaryLight,
          ),
          _Metric(
            width: width,
            label: 'Selesai',
            value: '${summary.finished}',
            icon: Icons.task_alt_rounded,
            color: NusaColors.primary,
          ),
          _Metric(
            width: width,
            label: 'Ditahan / Pindah app',
            value: '${summary.blocked} / ${summary.appSwitches}',
            icon: Icons.gpp_bad_outlined,
            color: Colors.red.shade700,
          ),
        ],
      );
    },
  );
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.width,
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final double width;
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: width,
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Container(
              width: 37,
              height: 37,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    value,
                    style: TextStyle(
                      color: color,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  Text(
                    label,
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
          ],
        ),
      ),
    ),
  );
}

class _ParticipantFilters extends StatelessWidget {
  const _ParticipantFilters({
    required this.controller,
    required this.selectedStatus,
    required this.onQuery,
    required this.onStatus,
  });

  final TextEditingController controller;
  final String selectedStatus;
  final ValueChanged<String> onQuery;
  final ValueChanged<String> onStatus;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final narrow = constraints.maxWidth < 430;
          final search = TextField(
            controller: controller,
            onChanged: onQuery,
            decoration: const InputDecoration(
              labelText: 'Cari peserta',
              prefixIcon: Icon(Icons.search_rounded),
              isDense: true,
            ),
          );
          final filter = DropdownButtonFormField<String>(
            initialValue: selectedStatus,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Status',
              isDense: true,
            ),
            items: const [
              DropdownMenuItem(value: 'semua', child: Text('Semua status')),
              DropdownMenuItem(
                value: 'belum_absen',
                child: Text('Belum hadir'),
              ),
              DropdownMenuItem(value: 'hadir', child: Text('Hadir')),
              DropdownMenuItem(value: 'terlambat', child: Text('Terlambat')),
              DropdownMenuItem(
                value: 'sedang_mengerjakan',
                child: Text('Mengerjakan'),
              ),
              DropdownMenuItem(value: 'selesai', child: Text('Selesai')),
              DropdownMenuItem(value: 'terblokir', child: Text('Ditahan')),
            ],
            onChanged: (value) {
              if (value != null) onStatus(value);
            },
          );
          if (narrow) {
            return Column(
              children: [search, const SizedBox(height: 9), filter],
            );
          }
          return Row(
            children: [
              Expanded(flex: 3, child: search),
              const SizedBox(width: 9),
              Expanded(flex: 2, child: filter),
            ],
          );
        },
      ),
    ),
  );
}

class _ParticipantCard extends StatelessWidget {
  const _ParticipantCard({
    required this.participant,
    required this.capabilities,
    required this.busy,
    required this.onAttendance,
    required this.onResetDevice,
    required this.onUnlock,
  });

  final SupervisionParticipant participant;
  final SupervisionCapabilities capabilities;
  final bool busy;
  final VoidCallback onAttendance;
  final VoidCallback onResetDevice;
  final VoidCallback onUnlock;

  @override
  Widget build(BuildContext context) {
    final blocked = participant.status == 'terblokir';
    return Card(
      key: Key('supervision-participant-${participant.id}'),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: blocked
                        ? Colors.red.withValues(alpha: 0.09)
                        : NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(13),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    participant.deskNumber?.toString() ?? '-',
                    style: TextStyle(
                      color: blocked ? Colors.red.shade700 : NusaColors.primary,
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
                        participant.name,
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${participant.className} · ${participant.participantNumber ?? participant.nisn ?? '-'}',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                    ],
                  ),
                ),
                PopupMenuButton<String>(
                  enabled: !busy,
                  tooltip: 'Tindakan peserta',
                  onSelected: (value) {
                    if (value == 'attendance') onAttendance();
                    if (value == 'device') onResetDevice();
                    if (value == 'unlock') onUnlock();
                  },
                  itemBuilder: (context) => [
                    if (capabilities.changeAttendance)
                      const PopupMenuItem(
                        value: 'attendance',
                        child: ListTile(
                          leading: Icon(Icons.fact_check_outlined),
                          title: Text('Ubah presensi'),
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                    if (capabilities.resetDevice && participant.deviceBound)
                      const PopupMenuItem(
                        value: 'device',
                        child: ListTile(
                          leading: Icon(Icons.phonelink_erase_rounded),
                          title: Text('Reset perangkat'),
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                    if (capabilities.unlockSafeMode && blocked)
                      const PopupMenuItem(
                        value: 'unlock',
                        child: ListTile(
                          leading: Icon(Icons.lock_open_rounded),
                          title: Text('Buka Mode Aman'),
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                _TonePill(
                  label: participant.attendanceLabel,
                  tone: participant.attendance,
                ),
                _TonePill(
                  label: participant.statusLabel,
                  tone: participant.status,
                ),
                if (participant.deviceBound)
                  const _TonePill(label: 'Perangkat terikat', tone: 'biru'),
                if (participant.appSwitches > 0)
                  _TonePill(
                    label: '${participant.appSwitches}× pindah aplikasi',
                    tone: 'bahaya',
                  ),
              ],
            ),
            if (participant.status == 'sedang_mengerjakan' || blocked) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  const Icon(
                    Icons.save_outlined,
                    size: 15,
                    color: NusaColors.textSecondary,
                  ),
                  const SizedBox(width: 5),
                  Expanded(
                    child: Text(
                      '${participant.savedAnswers} jawaban tersimpan',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Text(
                      participant.lastHeartbeat == null
                          ? 'Belum ada heartbeat'
                          : 'Aktif ${_relative(participant.lastHeartbeat!)}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.end,
                      style: TextStyle(
                        color: _heartbeatColor(participant.lastHeartbeat),
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ],
            if (participant.attendanceNote?.isNotEmpty == true) ...[
              const SizedBox(height: 9),
              Text(
                participant.attendanceNote!,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                  fontStyle: FontStyle.italic,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _EvidenceSection extends StatelessWidget {
  const _EvidenceSection({
    required this.data,
    required this.busy,
    required this.onUpload,
    required this.onDelete,
    required this.onSubmit,
  });

  final ExamSupervisionDetail data;
  final bool busy;
  final ValueChanged<String> onUpload;
  final ValueChanged<SupervisionEvidence> onDelete;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final attendance = data.evidence
        .where((item) => item.type == 'daftar_hadir')
        .toList();
    final minutes = data.evidence
        .where((item) => item.type == 'berita_acara')
        .toList();
    final complete = attendance.isNotEmpty && minutes.isNotEmpty;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Kelengkapan Bukti',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
                _TonePill(
                  label: data.room.evidenceStatusLabel,
                  tone: data.room.evidenceStatus,
                ),
              ],
            ),
            if (data.room.reviewNote?.isNotEmpty == true) ...[
              const SizedBox(height: 10),
              _Notice(
                icon: Icons.warning_amber_rounded,
                message: data.room.reviewNote!,
                danger: true,
              ),
            ],
            const SizedBox(height: 12),
            _EvidenceGroup(
              title: 'Daftar hadir',
              description: 'Unggah seluruh halaman yang sudah ditandatangani.',
              type: 'daftar_hadir',
              files: attendance,
              canChange: data.capabilities.changeEvidence,
              busy: busy,
              onUpload: onUpload,
              onDelete: onDelete,
            ),
            const Divider(height: 25),
            _EvidenceGroup(
              title: 'Berita acara',
              description: 'Pastikan isian dan tanda tangan terlihat jelas.',
              type: 'berita_acara',
              files: minutes,
              canChange: data.capabilities.changeEvidence,
              busy: busy,
              onUpload: onUpload,
              onDelete: onDelete,
            ),
            if (data.capabilities.submitEvidence) ...[
              const SizedBox(height: 14),
              FilledButton.icon(
                key: const Key('supervision-submit-evidence'),
                onPressed: busy || !complete ? null : onSubmit,
                icon: const Icon(Icons.send_rounded),
                label: const Text('Kirim Bukti ke Panitia'),
              ),
              if (!complete)
                const Padding(
                  padding: EdgeInsets.only(top: 7),
                  child: Text(
                    'Minimal satu daftar hadir dan satu berita acara diperlukan.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10,
                    ),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _EvidenceGroup extends StatelessWidget {
  const _EvidenceGroup({
    required this.title,
    required this.description,
    required this.type,
    required this.files,
    required this.canChange,
    required this.busy,
    required this.onUpload,
    required this.onDelete,
  });

  final String title;
  final String description;
  final String type;
  final List<SupervisionEvidence> files;
  final bool canChange;
  final bool busy;
  final ValueChanged<String> onUpload;
  final ValueChanged<SupervisionEvidence> onDelete;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
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
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 2),
                Text(
                  description,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
              ],
            ),
          ),
          if (canChange)
            IconButton.filledTonal(
              key: Key('supervision-upload-$type'),
              tooltip: 'Tambah $title',
              onPressed: busy ? null : () => onUpload(type),
              icon: const Icon(Icons.add_a_photo_outlined),
            ),
        ],
      ),
      if (files.isEmpty)
        const Padding(
          padding: EdgeInsets.only(top: 9),
          child: Text(
            'Belum ada berkas.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
          ),
        )
      else
        for (final file in files)
          Container(
            margin: const EdgeInsets.only(top: 8),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: NusaColors.background,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: NusaColors.outline),
            ),
            child: Row(
              children: [
                Icon(
                  file.mimeType == 'application/pdf'
                      ? Icons.picture_as_pdf_outlined
                      : Icons.image_outlined,
                  color: NusaColors.primary,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        file.fileName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        '${file.sizeLabel}${file.uploadedBy == null ? '' : ' · ${file.uploadedBy}'}',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 9.5,
                        ),
                      ),
                    ],
                  ),
                ),
                if (canChange)
                  IconButton(
                    tooltip: 'Hapus bukti',
                    visualDensity: VisualDensity.compact,
                    onPressed: busy ? null : () => onDelete(file),
                    icon: Icon(
                      Icons.delete_outline_rounded,
                      color: Colors.red.shade700,
                    ),
                  ),
              ],
            ),
          ),
    ],
  );
}

class _AttendanceSheet extends StatefulWidget {
  const _AttendanceSheet({required this.participant, required this.options});

  final SupervisionParticipant participant;
  final List<AttendanceOption> options;

  @override
  State<_AttendanceSheet> createState() => _AttendanceSheetState();
}

class _AttendanceSheetState extends State<_AttendanceSheet> {
  late String _status = widget.participant.attendance;
  late final _notesController = TextEditingController(
    text: widget.participant.attendanceNote,
  );

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      18,
      20,
      20 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Presensi ${widget.participant.name}',
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            key: const Key('supervision-attendance-status'),
            initialValue: _status,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Status kehadiran'),
            items: widget.options
                .map(
                  (option) => DropdownMenuItem(
                    value: option.code,
                    child: Text(option.label),
                  ),
                )
                .toList(),
            onChanged: (value) {
              if (value != null) setState(() => _status = value);
            },
          ),
          const SizedBox(height: 11),
          TextField(
            controller: _notesController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Catatan (opsional)',
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 16),
          FilledButton(
            key: const Key('supervision-save-attendance'),
            onPressed: () => Navigator.pop(
              context,
              _AttendanceResult(
                status: _status,
                notes: _notesController.text.trim().isEmpty
                    ? null
                    : _notesController.text.trim(),
              ),
            ),
            child: const Text('Simpan Presensi'),
          ),
        ],
      ),
    ),
  );
}

class _NotesSheet extends StatefulWidget {
  const _NotesSheet({required this.room});

  final SupervisionRoom room;

  @override
  State<_NotesSheet> createState() => _NotesSheetState();
}

class _NotesSheetState extends State<_NotesSheet> {
  late final _minutes = TextEditingController(text: widget.room.minutes);
  late final _obstacles = TextEditingController(text: widget.room.obstacles);
  late final _followUp = TextEditingController(text: widget.room.followUp);
  late final _notes = TextEditingController(text: widget.room.notes);

  @override
  void dispose() {
    _minutes.dispose();
    _obstacles.dispose();
    _followUp.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      18,
      20,
      20 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Catatan Ruang',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 14),
          _TextArea(controller: _minutes, label: 'Berita acara pelaksanaan'),
          const SizedBox(height: 10),
          _TextArea(controller: _obstacles, label: 'Hambatan'),
          const SizedBox(height: 10),
          _TextArea(controller: _followUp, label: 'Tindak lanjut'),
          const SizedBox(height: 10),
          _TextArea(controller: _notes, label: 'Catatan tambahan'),
          const SizedBox(height: 16),
          FilledButton(
            key: const Key('supervision-save-notes'),
            onPressed: () => Navigator.pop(
              context,
              _NotesResult(
                minutes: _minutes.text.trim(),
                obstacles: _obstacles.text.trim(),
                followUp: _followUp.text.trim(),
                notes: _notes.text.trim(),
              ),
            ),
            child: const Text('Simpan Catatan'),
          ),
        ],
      ),
    ),
  );
}

class _TextArea extends StatelessWidget {
  const _TextArea({required this.controller, required this.label});

  final TextEditingController controller;
  final String label;

  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    minLines: 2,
    maxLines: 4,
    decoration: InputDecoration(labelText: label, alignLabelWithHint: true),
  );
}

class _EvidenceSourceSheet extends StatelessWidget {
  const _EvidenceSourceSheet();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(20, 16, 20, 22),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Tambah Bukti',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        ListTile(
          leading: const Icon(Icons.camera_alt_outlined),
          title: const Text('Ambil foto'),
          subtitle: const Text('Gunakan kamera HP'),
          onTap: () => Navigator.pop(context, _EvidenceSource.camera),
        ),
        ListTile(
          leading: const Icon(Icons.upload_file_rounded),
          title: const Text('Pilih berkas'),
          subtitle: const Text('JPG, PNG, WebP, atau PDF'),
          onTap: () => Navigator.pop(context, _EvidenceSource.file),
        ),
      ],
    ),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 2),
      Text(
        subtitle,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
      ),
    ],
  );
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: color.withValues(alpha: 0.75)),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: color,
        fontSize: 9.5,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _TonePill extends StatelessWidget {
  const _TonePill({required this.label, required this.tone});

  final String label;
  final String tone;

  @override
  Widget build(BuildContext context) {
    final color = _tone(tone);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 9.5,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({
    required this.icon,
    required this.message,
    this.danger = false,
  });

  final IconData icon;
  final String message;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? Colors.red.shade700 : NusaColors.primary;
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.16)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: color, fontSize: 10.5, height: 1.35),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          Icon(icon, color: NusaColors.textSecondary, size: 34),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
            ),
          ),
        ],
      ),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: NusaColors.textSecondary,
          ),
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

class _AttendanceResult {
  const _AttendanceResult({required this.status, required this.notes});
  final String status;
  final String? notes;
}

class _NotesResult {
  const _NotesResult({
    required this.minutes,
    required this.obstacles,
    required this.followUp,
    required this.notes,
  });
  final String minutes;
  final String obstacles;
  final String followUp;
  final String notes;
}

enum _EvidenceSource { camera, file }

Color _tone(String tone) => switch (tone) {
  'berlangsung' ||
  'hadir' ||
  'selesai' ||
  'valid' ||
  'siap_dikirim' => NusaColors.success,
  'terblokir' || 'alfa' || 'bahaya' || 'perlu_diulang' => Colors.red.shade700,
  'terlambat' ||
  'sakit' ||
  'izin' ||
  'menunggu_pemeriksaan' => const Color(0xFF9A7000),
  'belum_absen' || 'belum_hadir' || 'draft' => NusaColors.textSecondary,
  _ => NusaColors.primary,
};

Color _heartbeatColor(DateTime? value) {
  if (value == null) return NusaColors.textSecondary;
  final seconds = DateTime.now().difference(value).inSeconds;
  if (seconds <= 30) return NusaColors.success;
  if (seconds <= 90) return const Color(0xFF9A7000);
  return Colors.red.shade700;
}

String _date(String? value) {
  final date = value == null ? null : DateTime.tryParse(value);
  if (date == null) return value ?? '-';
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

String _dateTime(DateTime? value) => value == null
    ? '-'
    : '${_date(value.toIso8601String())}, ${_clock(value)} WIB';

String _clock(DateTime? value) => value == null
    ? '-'
    : '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';

String _relative(DateTime value) {
  final seconds = DateTime.now().difference(value).inSeconds;
  if (seconds < 10) return 'baru saja';
  if (seconds < 60) return '$seconds detik lalu';
  if (seconds < 3600) return '${seconds ~/ 60} menit lalu';
  return _clock(value);
}

String _message(Object error, String fallback) =>
    error is AppException ? error.message : fallback;
