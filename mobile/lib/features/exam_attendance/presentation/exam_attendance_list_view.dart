import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/exam_attendance/application/exam_attendance_controller.dart';
import 'package:nusa/features/exam_attendance/domain/exam_attendance.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class ExamAttendanceListView extends ConsumerWidget {
  const ExamAttendanceListView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(examAttendanceControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Presensi Ujian'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(examAttendanceControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: _message(error),
            onRetry: ref
                .read(examAttendanceControllerProvider.notifier)
                .refresh,
          ),
          data: (data) => RefreshIndicator(
            onRefresh: ref
                .read(examAttendanceControllerProvider.notifier)
                .refresh,
            child: ListView(
              key: const PageStorageKey<String>('exam-attendance-list'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
              children: [
                _Hero(data: data),
                const SizedBox(height: 18),
                NusaSectionTitle(
                  title: 'Ruang Ujian Hari Ini',
                  actionLabel: '${data.todayRooms.length} ruang',
                ),
                const SizedBox(height: 9),
                if (data.todayRooms.isEmpty)
                  const _EmptyCard(
                    icon: Icons.event_busy_outlined,
                    message: 'Belum ada ruang ujian terjadwal hari ini.',
                  )
                else
                  for (final room in data.todayRooms) ...[
                    _RoomCard(room: room, today: true),
                    const SizedBox(height: 10),
                  ],
                const SizedBox(height: 10),
                NusaSectionTitle(
                  title: 'Jadwal Ruang Lainnya',
                  actionLabel: '${data.otherRooms.length} ruang',
                ),
                const SizedBox(height: 9),
                if (data.otherRooms.isEmpty)
                  const _EmptyCard(
                    icon: Icons.meeting_room_outlined,
                    message: 'Belum ada jadwal ruang ujian lainnya.',
                  )
                else
                  for (final room in data.otherRooms) ...[
                    _RoomCard(room: room),
                    const SizedBox(height: 10),
                  ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.data});

  final ExamAttendanceDashboard data;

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
          right: -24,
          bottom: -30,
          child: Icon(
            Icons.qr_code_scanner_rounded,
            size: 126,
            color: Colors.white.withValues(alpha: 0.06),
          ),
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Presensi Peserta Ujian CBT',
              style: TextStyle(
                color: Colors.white,
                fontSize: 19,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              data.canManageAll
                  ? 'Pilih ruang, lalu pindai QR kartu pelajar peserta.'
                  : 'Ruang yang ditampilkan hanya ruang tugas Anda.',
              style: const TextStyle(
                color: Colors.white70,
                fontSize: 11.5,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: _HeroMetric(
                    label: 'Ruang',
                    value: '${data.summary.rooms}',
                  ),
                ),
                const _HeroDivider(),
                Expanded(
                  child: _HeroMetric(
                    label: 'Peserta',
                    value: '${data.summary.participants}',
                  ),
                ),
                const _HeroDivider(),
                Expanded(
                  child: _HeroMetric(
                    label: 'Hadir',
                    value: '${data.summary.present}',
                    highlighted: true,
                  ),
                ),
              ],
            ),
          ],
        ),
      ],
    ),
  );
}

class _HeroMetric extends StatelessWidget {
  const _HeroMetric({
    required this.label,
    required this.value,
    this.highlighted = false,
  });

  final String label;
  final String value;
  final bool highlighted;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Text(
        value,
        style: TextStyle(
          color: highlighted ? NusaColors.accent : Colors.white,
          fontSize: 20,
          fontWeight: FontWeight.w900,
        ),
      ),
      const SizedBox(height: 2),
      Text(label, style: const TextStyle(color: Colors.white70, fontSize: 10)),
    ],
  );
}

class _HeroDivider extends StatelessWidget {
  const _HeroDivider();

  @override
  Widget build(BuildContext context) =>
      Container(width: 1, height: 36, color: Colors.white24);
}

class _RoomCard extends StatelessWidget {
  const _RoomCard({required this.room, this.today = false});

  final ExamAttendanceRoom room;
  final bool today;

  @override
  Widget build(BuildContext context) {
    final complete = room.notRecorded == 0;
    final tone = complete ? NusaColors.success : const Color(0xFFB57900);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/presensi-ujian/${room.id}'),
        child: Padding(
          padding: const EdgeInsets.all(15),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 43,
                    height: 43,
                    decoration: BoxDecoration(
                      color: NusaColors.surfaceBlue,
                      borderRadius: BorderRadius.circular(13),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      room.code,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 10,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          room.name,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          room.activity,
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
                  const SizedBox(width: 7),
                  _Pill(
                    label: '${room.present}/${room.participants}',
                    color: tone,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                '${today ? room.dateLabel ?? 'Hari ini' : room.dateLabel ?? 'Jadwal belum ditentukan'}${room.time?.isNotEmpty == true ? ' · ${room.time}' : ''}',
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                '${room.subject}${room.session?.isNotEmpty == true ? ' · ${room.session}' : ''}',
                style: const TextStyle(fontSize: 11.5),
              ),
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(
                  value: room.participants == 0
                      ? 0
                      : room.present / room.participants,
                  minHeight: 7,
                  backgroundColor: NusaColors.outline,
                  color: complete ? NusaColors.success : NusaColors.primary,
                ),
              ),
              const SizedBox(height: 11),
              Wrap(
                spacing: 12,
                runSpacing: 6,
                children: [
                  _Fact(label: 'Belum tercatat', value: '${room.notRecorded}'),
                  _Fact(label: 'Tidak hadir', value: '${room.absent}'),
                  _Fact(label: 'Lokasi', value: room.location ?? '-'),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      room.primarySupervisor ?? 'Pengawas belum ditentukan',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Icon(
                    Icons.qr_code_scanner_rounded,
                    color: NusaColors.primary,
                    size: 20,
                  ),
                  const SizedBox(width: 3),
                  const Text(
                    'Buka',
                    style: TextStyle(
                      color: NusaColors.primary,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      '$label hadir',
      style: TextStyle(
        color: color,
        fontSize: 9.5,
        fontWeight: FontWeight.w900,
      ),
    ),
  );
}

class _Fact extends StatelessWidget {
  const _Fact({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Text.rich(
    TextSpan(
      style: const TextStyle(fontSize: 10.5),
      children: [
        TextSpan(
          text: '$label: ',
          style: const TextStyle(color: NusaColors.textSecondary),
        ),
        TextSpan(
          text: value,
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ],
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
      padding: const EdgeInsets.all(22),
      child: Column(
        children: [
          Icon(icon, color: NusaColors.textSecondary, size: 34),
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

String _message(Object error) => error is AppException
    ? error.message
    : 'Daftar ruang presensi ujian belum dapat dimuat.';
