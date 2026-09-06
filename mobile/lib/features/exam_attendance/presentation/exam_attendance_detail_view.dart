import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/exam_attendance/application/exam_attendance_controller.dart';
import 'package:nusa/features/exam_attendance/domain/exam_attendance.dart';
import 'package:nusa/features/exam_attendance/presentation/widgets/exam_attendance_camera.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

typedef ExamAttendanceCameraBuilder = Widget Function(
  ValueChanged<String> onDetected,
  bool processing,
);

class ExamAttendanceDetailView extends ConsumerStatefulWidget {
  const ExamAttendanceDetailView({
    required this.roomId,
    this.cameraBuilder,
    super.key,
  });

  final int roomId;
  final ExamAttendanceCameraBuilder? cameraBuilder;

  @override
  ConsumerState<ExamAttendanceDetailView> createState() =>
      _ExamAttendanceDetailViewState();
}

class _ExamAttendanceDetailViewState
    extends ConsumerState<ExamAttendanceDetailView> {
  final _manualController = TextEditingController();
  final _searchController = TextEditingController();
  bool _processing = false;
  String _query = '';
  String _status = 'semua';
  String? _lastRawValue;
  DateTime? _lastDetection;
  ExamAttendanceScanResult? _lastResult;

  @override
  void dispose() {
    _manualController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    ref.invalidate(examAttendanceDetailProvider(widget.roomId));
    await ref.read(examAttendanceDetailProvider(widget.roomId).future);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(examAttendanceDetailProvider(widget.roomId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Presensi Ruang'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading || _processing ? null : _refresh,
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
          data: (data) => RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              key: const PageStorageKey<String>('exam-attendance-detail'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 34),
              children: [
                _RoomHero(room: data.room),
                const SizedBox(height: 11),
                _SummaryGrid(summary: data.summary),
                const SizedBox(height: 18),
                const NusaSectionTitle(
                  title: 'Scan Kartu Pelajar',
                  actionLabel: 'Kamera HP',
                ),
                const SizedBox(height: 9),
                _camera(data.room.canChange),
                const SizedBox(height: 10),
                _ManualScanCard(
                  controller: _manualController,
                  processing: _processing,
                  enabled: data.room.canChange,
                  onSubmit: _submitManual,
                ),
                if (_lastResult case final result?) ...[
                  const SizedBox(height: 12),
                  _ScanResultCard(result: result),
                ],
                const SizedBox(height: 20),
                NusaSectionTitle(
                  title: 'Presensi Terbaru',
                  actionLabel: '${data.summary.present} hadir',
                ),
                const SizedBox(height: 9),
                if (data.recentAttendances.isEmpty)
                  const _EmptyCard(
                    icon: Icons.history_toggle_off_rounded,
                    message: 'Belum ada peserta yang tercatat hadir.',
                  )
                else
                  _RecentAttendance(items: data.recentAttendances),
                const SizedBox(height: 20),
                NusaSectionTitle(
                  title: 'Daftar Peserta Ruang',
                  actionLabel: '${data.participants.length} siswa',
                ),
                const SizedBox(height: 9),
                _ParticipantFilters(
                  controller: _searchController,
                  options: data.attendanceOptions,
                  status: _status,
                  onQueryChanged: (value) =>
                      setState(() => _query = value.trim().toLowerCase()),
                  onStatusChanged: (value) => setState(() => _status = value),
                ),
                const SizedBox(height: 10),
                if (_participants(data).isEmpty)
                  const _EmptyCard(
                    icon: Icons.manage_search_rounded,
                    message: 'Tidak ada peserta yang sesuai pencarian.',
                  )
                else
                  for (final participant in _participants(data)) ...[
                    _ParticipantCard(
                      participant: participant,
                      enabled: data.room.canChange && !_processing,
                      onEdit: () => _editAttendance(data, participant),
                    ),
                    const SizedBox(height: 9),
                  ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _camera(bool enabled) {
    if (!enabled) {
      return const _EmptyCard(
        icon: Icons.lock_outline_rounded,
        message: 'Presensi ruang ini tidak dapat diubah.',
      );
    }
    final customBuilder = widget.cameraBuilder;
    return customBuilder != null
        ? customBuilder(_handleDetection, _processing)
        : ExamAttendanceCamera(
            onDetected: _handleDetection,
            processing: _processing,
          );
  }

  List<ExamAttendanceParticipant> _participants(
    ExamAttendanceDetail data,
  ) => data.participants.where((participant) {
    final matchesQuery =
        _query.isEmpty ||
        participant.name.toLowerCase().contains(_query) ||
        (participant.nisn ?? '').contains(_query) ||
        participant.className.toLowerCase().contains(_query) ||
        (participant.participantNumber ?? '').toLowerCase().contains(_query);
    final matchesStatus = _status == 'semua' || participant.status == _status;
    return matchesQuery && matchesStatus;
  }).toList();

  Future<void> _submitManual() async {
    final value = _manualController.text.trim();
    if (value.isEmpty) {
      _snack('Masukkan NISN peserta terlebih dahulu.', error: true);
      return;
    }
    _manualController.clear();
    await _handleDetection(value, bypassDebounce: true);
  }

  Future<void> _handleDetection(
    String rawValue, {
    bool bypassDebounce = false,
  }) async {
    final value = rawValue.trim();
    if (_processing || value.isEmpty) return;

    final now = DateTime.now();
    if (!bypassDebounce &&
        _lastRawValue == value &&
        _lastDetection != null &&
        now.difference(_lastDetection!) < const Duration(seconds: 4)) {
      return;
    }
    _lastRawValue = value;
    _lastDetection = now;
    setState(() => _processing = true);

    try {
      final result = await ref
          .read(examAttendanceActionsProvider)
          .scan(roomId: widget.roomId, rawValue: value);
      if (!mounted) return;
      setState(() => _lastResult = result);
      unawaited(
        result.success
            ? HapticFeedback.mediumImpact()
            : HapticFeedback.vibrate(),
      );
      if (result.success) {
        ref.invalidate(examAttendanceDetailProvider(widget.roomId));
        ref.invalidate(examAttendanceControllerProvider);
        await ref.read(examAttendanceDetailProvider(widget.roomId).future);
      }
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _lastResult = ExamAttendanceScanResult(
          success: false,
          isNew: false,
          status: 'gangguan',
          message: _message(error),
          serverTime: '',
        ),
      );
      unawaited(HapticFeedback.vibrate());
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

  Future<void> _editAttendance(
    ExamAttendanceDetail data,
    ExamAttendanceParticipant participant,
  ) async {
    final result = await showModalBottomSheet<_AttendanceResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => _AttendanceSheet(
        participant: participant,
        options: data.attendanceOptions,
      ),
    );
    if (result == null || !mounted) return;

    setState(() => _processing = true);
    try {
      await ref
          .read(examAttendanceActionsProvider)
          .changeAttendance(
            roomId: widget.roomId,
            participantId: participant.id,
            status: result.status,
            note: result.note,
          );
      ref.invalidate(examAttendanceDetailProvider(widget.roomId));
      ref.invalidate(examAttendanceControllerProvider);
      await ref.read(examAttendanceDetailProvider(widget.roomId).future);
      if (mounted) _snack('Presensi ${participant.name} diperbarui.');
    } catch (error) {
      if (mounted) _snack(_message(error), error: true);
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

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

  final ExamAttendanceRoomDetail room;

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
          right: -20,
          bottom: -28,
          child: Icon(
            Icons.meeting_room_rounded,
            size: 124,
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
              '${room.code} · ${room.name}',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 20,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              room.activity,
              style: const TextStyle(
                color: NusaColors.accent,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 13,
              runSpacing: 7,
              children: [
                _HeroInfo(
                  icon: Icons.calendar_today_rounded,
                  text: room.dateLabel ?? '-',
                ),
                _HeroInfo(icon: Icons.schedule_rounded, text: room.time ?? '-'),
                _HeroInfo(icon: Icons.menu_book_rounded, text: room.subject),
                if (room.location?.isNotEmpty == true)
                  _HeroInfo(icon: Icons.place_outlined, text: room.location!),
              ],
            ),
          ],
        ),
      ],
    ),
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
      color: color.withValues(alpha: color == Colors.white ? 0.12 : 0.92),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: color.withValues(alpha: 0.5)),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: color == Colors.white ? Colors.white : NusaColors.primaryDark,
        fontSize: 9.5,
        fontWeight: FontWeight.w900,
      ),
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
      maxWidth: MediaQuery.sizeOf(context).width - 70,
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: Colors.white70, size: 14),
        const SizedBox(width: 5),
        Flexible(
          child: Text(
            text,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white70, fontSize: 10.5),
          ),
        ),
      ],
    ),
  );
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.summary});

  final ExamAttendanceSummary summary;

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
            label: 'Peserta',
            value: '${summary.participants}',
            icon: Icons.groups_rounded,
            color: NusaColors.primary,
          ),
          _Metric(
            width: width,
            label: 'Hadir',
            value: '${summary.present}',
            icon: Icons.how_to_reg_rounded,
            color: NusaColors.success,
          ),
          _Metric(
            width: width,
            label: 'Belum tercatat',
            value: '${summary.notRecorded}',
            icon: Icons.hourglass_empty_rounded,
            color: const Color(0xFFB57900),
          ),
          _Metric(
            width: width,
            label: 'Tidak hadir',
            value: '${summary.absent}',
            icon: Icons.person_off_outlined,
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
  Widget build(BuildContext context) => Container(
    width: width,
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        Container(
          width: 35,
          height: 35,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, color: color, size: 19),
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
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                label,
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
      ],
    ),
  );
}

class _ManualScanCard extends StatelessWidget {
  const _ManualScanCard({
    required this.controller,
    required this.processing,
    required this.enabled,
    required this.onSubmit,
  });

  final TextEditingController controller;
  final bool processing;
  final bool enabled;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Input NISN Manual',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 3),
          const Text(
            'Gunakan jika kamera bermasalah atau scanner eksternal terhubung.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
          ),
          const SizedBox(height: 11),
          TextField(
            key: const Key('exam-attendance-manual-nisn'),
            controller: controller,
            enabled: enabled && !processing,
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => onSubmit(),
            decoration: InputDecoration(
              hintText: 'Masukkan NISN peserta',
              prefixIcon: const Icon(Icons.badge_outlined),
              suffixIcon: IconButton(
                tooltip: 'Catat hadir',
                onPressed: enabled && !processing ? onSubmit : null,
                icon: const Icon(Icons.send_rounded),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _ScanResultCard extends StatelessWidget {
  const _ScanResultCard({required this.result});

  final ExamAttendanceScanResult result;

  @override
  Widget build(BuildContext context) {
    final known = result.success && !result.isNew;
    final color = result.success
        ? known
              ? const Color(0xFFB57900)
              : NusaColors.success
        : Colors.red.shade700;
    final student = result.student;
    return Container(
      key: const Key('exam-attendance-scan-result'),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: color.withValues(alpha: 0.28)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Avatar(name: student?.name ?? '?', photoUrl: student?.photoUrl),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  result.success
                      ? known
                            ? 'Sudah tercatat'
                            : 'Presensi berhasil'
                      : 'Presensi belum tercatat',
                  style: TextStyle(
                    color: color,
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  student?.name ?? 'QR tidak dikenali',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Wrap(
                  spacing: 8,
                  runSpacing: 3,
                  children: [
                    if (student?.className != null)
                      Text(
                        'Kelas ${student!.className}',
                        style: const TextStyle(fontSize: 10),
                      ),
                    if (student?.deskNumber != null)
                      Text(
                        'Meja ${student!.deskNumber}',
                        style: const TextStyle(fontSize: 10),
                      ),
                    if (result.serverTime.isNotEmpty)
                      Text(
                        'Pukul ${_shortTime(result.serverTime)}',
                        style: const TextStyle(fontSize: 10),
                      ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  result.message,
                  style: const TextStyle(fontSize: 11, height: 1.35),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RecentAttendance extends StatelessWidget {
  const _RecentAttendance({required this.items});

  final List<ExamAttendanceParticipant> items;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: Column(
      children: [
        for (var index = 0; index < items.length; index++) ...[
          _RecentTile(participant: items[index]),
          if (index != items.length - 1) const Divider(height: 1),
        ],
      ],
    ),
  );
}

class _RecentTile extends StatelessWidget {
  const _RecentTile({required this.participant});

  final ExamAttendanceParticipant participant;

  @override
  Widget build(BuildContext context) => ListTile(
    dense: true,
    leading: _Avatar(name: participant.name, photoUrl: participant.photoUrl),
    title: Text(
      participant.name,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
    ),
    subtitle: Text(
      'Meja ${participant.deskNumber ?? '-'} · ${participant.className}',
      style: const TextStyle(fontSize: 10),
    ),
    trailing: Text(
      _shortTime(participant.scanTime),
      style: const TextStyle(
        color: NusaColors.success,
        fontSize: 11,
        fontWeight: FontWeight.w900,
      ),
    ),
  );
}

class _ParticipantFilters extends StatelessWidget {
  const _ParticipantFilters({
    required this.controller,
    required this.options,
    required this.status,
    required this.onQueryChanged,
    required this.onStatusChanged,
  });

  final TextEditingController controller;
  final List<ExamAttendanceOption> options;
  final String status;
  final ValueChanged<String> onQueryChanged;
  final ValueChanged<String> onStatusChanged;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        children: [
          TextField(
            controller: controller,
            onChanged: onQueryChanged,
            decoration: const InputDecoration(
              hintText: 'Cari nama, NISN, atau nomor peserta',
              prefixIcon: Icon(Icons.search_rounded),
            ),
          ),
          const SizedBox(height: 9),
          NusaDropdownField<String>(
            fieldKey: const Key('exam-attendance-status-filter'),
            value: status,
            options: [
              const NusaDropdownOption(value: 'semua', label: 'Semua status'),
              ...options.map(
                (item) =>
                    NusaDropdownOption(value: item.code, label: item.label),
              ),
            ],
            decoration: const InputDecoration(
              labelText: 'Status kehadiran',
              prefixIcon: Icon(Icons.filter_alt_outlined),
            ),
            onChanged: (value) {
              if (value != null) onStatusChanged(value);
            },
          ),
        ],
      ),
    ),
  );
}

class _ParticipantCard extends StatelessWidget {
  const _ParticipantCard({
    required this.participant,
    required this.enabled,
    required this.onEdit,
  });

  final ExamAttendanceParticipant participant;
  final bool enabled;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(participant.status);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _Avatar(name: participant.name, photoUrl: participant.photoUrl),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          participant.name,
                          style: const TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: color.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Text(
                          participant.statusLabel,
                          style: TextStyle(
                            color: color,
                            fontSize: 9,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${participant.className} · NISN ${participant.nisn ?? '-'}',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10,
                    ),
                  ),
                  Text(
                    'Meja ${participant.deskNumber ?? '-'}${participant.participantNumber?.isNotEmpty == true ? ' · ${participant.participantNumber}' : ''}',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10,
                    ),
                  ),
                  if (participant.note?.isNotEmpty == true) ...[
                    const SizedBox(height: 5),
                    Text(
                      participant.note!,
                      style: const TextStyle(fontSize: 10.5, height: 1.35),
                    ),
                  ],
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    key: Key('exam-attendance-edit-${participant.id}'),
                    onPressed: enabled ? onEdit : null,
                    icon: const Icon(Icons.edit_calendar_outlined, size: 17),
                    label: const Text('Ubah Presensi'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.name, this.photoUrl});

  final String name;
  final String? photoUrl;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty ? '?' : name.trim()[0].toUpperCase();
    final url = photoUrl?.trim();
    return CircleAvatar(
      radius: 21,
      backgroundColor: NusaColors.surfaceBlue,
      foregroundImage: url?.isNotEmpty == true ? NetworkImage(url!) : null,
      child: Text(
        initial,
        style: const TextStyle(
          color: NusaColors.primary,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _AttendanceResult {
  const _AttendanceResult({required this.status, this.note});

  final String status;
  final String? note;
}

class _AttendanceSheet extends StatefulWidget {
  const _AttendanceSheet({required this.participant, required this.options});

  final ExamAttendanceParticipant participant;
  final List<ExamAttendanceOption> options;

  @override
  State<_AttendanceSheet> createState() => _AttendanceSheetState();
}

class _AttendanceSheetState extends State<_AttendanceSheet> {
  late String _status = widget.participant.status;
  late final TextEditingController _noteController = TextEditingController(
    text: widget.participant.note,
  );

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      0,
      20,
      MediaQuery.viewInsetsOf(context).bottom + 20,
    ),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Ubah Presensi Peserta',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          Text(
            '${widget.participant.name} · Meja ${widget.participant.deskNumber ?? '-'}',
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11.5,
            ),
          ),
          const SizedBox(height: 16),
          NusaDropdownField<String>(
            fieldKey: const Key('exam-attendance-edit-status'),
            value: _status,
            options: widget.options
                .map(
                  (item) =>
                      NusaDropdownOption(value: item.code, label: item.label),
                )
                .toList(),
            decoration: const InputDecoration(
              labelText: 'Status kehadiran',
              prefixIcon: Icon(Icons.fact_check_outlined),
            ),
            onChanged: (value) {
              if (value != null) setState(() => _status = value);
            },
          ),
          const SizedBox(height: 11),
          TextField(
            controller: _noteController,
            maxLines: 3,
            maxLength: 1000,
            decoration: const InputDecoration(
              labelText: 'Catatan (opsional)',
              alignLabelWithHint: true,
              prefixIcon: Icon(Icons.notes_rounded),
            ),
          ),
          const SizedBox(height: 8),
          FilledButton.icon(
            key: const Key('exam-attendance-save-status'),
            onPressed: () => Navigator.pop(
              context,
              _AttendanceResult(
                status: _status,
                note: _noteController.text.trim().isEmpty
                    ? null
                    : _noteController.text.trim(),
              ),
            ),
            icon: const Icon(Icons.save_outlined),
            label: const Text('Simpan Presensi'),
          ),
        ],
      ),
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
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          Icon(icon, size: 34, color: NusaColors.textSecondary),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: NusaColors.textSecondary),
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
          const Icon(Icons.cloud_off_rounded, size: 50),
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

Color _statusColor(String status) => switch (status) {
  'hadir' => NusaColors.success,
  'terlambat' => const Color(0xFFB57900),
  'sakit' || 'izin' => NusaColors.primaryLight,
  'alfa' => Colors.red.shade700,
  _ => NusaColors.textSecondary,
};

String _shortTime(String? value) {
  if (value == null || value.isEmpty) return '-';
  if (value.contains('T')) {
    final parsed = DateTime.tryParse(value)?.toLocal();
    if (parsed != null) {
      return '${parsed.hour.toString().padLeft(2, '0')}:${parsed.minute.toString().padLeft(2, '0')}';
    }
  }
  return value.length >= 5 ? value.substring(0, 5) : value;
}

String _message(Object error) => error is AppException
    ? error.message
    : 'Presensi ruang belum dapat diproses.';
