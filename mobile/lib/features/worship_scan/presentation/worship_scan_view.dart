import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_scan/application/worship_scan_controller.dart';
import 'package:nusa/features/worship_scan/domain/worship_scan.dart';
import 'package:nusa/features/worship_scan/presentation/widgets/worship_scan_camera.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

typedef WorshipScanCameraBuilder = Widget Function(
  ValueChanged<String> onDetected,
  bool processing,
);

class WorshipScanView extends ConsumerStatefulWidget {
  const WorshipScanView({this.cameraBuilder, super.key});

  final WorshipScanCameraBuilder? cameraBuilder;

  @override
  ConsumerState<WorshipScanView> createState() => _WorshipScanViewState();
}

class _WorshipScanViewState extends ConsumerState<WorshipScanView> {
  bool _processing = false;
  String? _lastRawValue;
  DateTime? _lastDetection;
  WorshipScanResult? _lastResult;

  @override
  Widget build(BuildContext context) {
    final dashboard = ref.watch(worshipScanControllerProvider);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Scan Ibadah Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui jadwal',
            onPressed: dashboard.isLoading || _processing
                ? null
                : () => ref
                      .read(worshipScanControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: dashboard.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ScanError(
            message: _errorMessage(error),
            onRetry: () =>
                ref.read(worshipScanControllerProvider.notifier).refresh(),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () =>
                ref.read(worshipScanControllerProvider.notifier).refresh(),
            child: ListView(
              key: const PageStorageKey<String>('worship-scan-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
              children: [
                _ScheduleCard(
                  dashboard: data,
                  processing: _processing,
                  onScheduleChanged: (value) {
                    if (value == null) return;
                    setState(() => _lastResult = null);
                    ref
                        .read(worshipScanControllerProvider.notifier)
                        .selectSchedule(value);
                  },
                ),
                const SizedBox(height: 14),
                if (data.scanOpen)
                  _buildCamera()
                else
                  _CameraUnavailable(status: data.scheduleStatus),
                if (_lastResult case final result?) ...[
                  const SizedBox(height: 14),
                  _ScanResultCard(result: result),
                ],
                const SizedBox(height: 22),
                NusaSectionTitle(
                  title: 'Presensi Terbaru',
                  actionLabel: '${data.todayCount} siswa hari ini',
                ),
                const SizedBox(height: 10),
                if (data.recentAttendances.isEmpty)
                  const _EmptyRecentAttendance()
                else
                  for (final attendance in data.recentAttendances) ...[
                    _AttendanceTile(attendance: attendance),
                    const SizedBox(height: 9),
                  ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCamera() {
    final customBuilder = widget.cameraBuilder;
    if (customBuilder != null) {
      return customBuilder(_handleDetection, _processing);
    }
    return WorshipScanCamera(
      onDetected: _handleDetection,
      processing: _processing,
    );
  }

  Future<void> _handleDetection(String rawValue) async {
    final value = rawValue.trim();
    if (_processing || value.isEmpty) return;

    final now = DateTime.now();
    if (_lastRawValue == value &&
        _lastDetection != null &&
        now.difference(_lastDetection!) < const Duration(seconds: 3)) {
      return;
    }
    _lastRawValue = value;
    _lastDetection = now;
    setState(() => _processing = true);

    try {
      final result = await ref
          .read(worshipScanControllerProvider.notifier)
          .submit(value);
      if (!mounted) return;
      setState(() => _lastResult = result);
      unawaited(
        result.success
            ? HapticFeedback.mediumImpact()
            : HapticFeedback.vibrate(),
      );
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _lastResult = WorshipScanResult(
          success: false,
          isNew: false,
          status: 'gangguan',
          message: _errorMessage(error),
          absencePeriodCompleted: false,
          serverTime: '',
          todayCount:
              ref.read(worshipScanControllerProvider).value?.todayCount ?? 0,
        ),
      );
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({
    required this.dashboard,
    required this.processing,
    required this.onScheduleChanged,
  });

  final WorshipScanDashboard dashboard;
  final bool processing;
  final ValueChanged<int?> onScheduleChanged;

  @override
  Widget build(BuildContext context) {
    final statusColor = dashboard.scanOpen
        ? NusaColors.success
        : NusaColors.textSecondary;
    final selected = dashboard.selectedSchedule;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: NusaColors.outline),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.06),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.qr_code_scanner_rounded,
                  color: NusaColors.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      dashboard.dateLabel,
                      style: const TextStyle(
                        color: NusaColors.textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      dashboard.academicYearName == null
                          ? 'Tahun pelajaran belum aktif'
                          : 'Tahun Pelajaran ${dashboard.academicYearName}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  dashboard.scheduleStatus.label,
                  style: TextStyle(
                    color: statusColor,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          if (dashboard.schedules.length > 1) ...[
            const SizedBox(height: 14),
            NusaDropdownField<int>(
              fieldKey: const Key('worship-scan-schedule-filter'),
              value: dashboard.selectedScheduleId,
              options: dashboard.schedules
                  .map(
                    (item) => NusaDropdownOption<int>(
                      value: item.id,
                      label: '${item.activity} · ${item.scanRange}',
                    ),
                  )
                  .toList(),
              decoration: const InputDecoration(
                labelText: 'Kegiatan ibadah',
                prefixIcon: Icon(Icons.self_improvement_rounded),
              ),
              enabled: !processing,
              onChanged: onScheduleChanged,
            ),
          ],
          const SizedBox(height: 13),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.schedule_rounded, size: 18, color: statusColor),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  selected == null
                      ? dashboard.scheduleStatus.message
                      : '${selected.activity} · Scan ${selected.scanRange}\n${dashboard.scheduleStatus.message}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 12,
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CameraUnavailable extends StatelessWidget {
  const _CameraUnavailable({required this.status});

  final WorshipScanScheduleStatus status;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('worship-scan-camera-unavailable'),
    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 34),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Column(
      children: [
        const Icon(
          Icons.no_photography_outlined,
          color: NusaColors.accent,
          size: 46,
        ),
        const SizedBox(height: 12),
        Text(
          status.label,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          status.message,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Colors.white70, height: 1.4),
        ),
      ],
    ),
  );
}

class _ScanResultCard extends StatelessWidget {
  const _ScanResultCard({required this.result});

  final WorshipScanResult result;

  @override
  Widget build(BuildContext context) {
    final color = result.success ? NusaColors.success : Colors.red.shade700;
    final student = result.student;
    return AnimatedContainer(
      key: const Key('worship-scan-result'),
      duration: const Duration(milliseconds: 220),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            child: Icon(
              result.success ? Icons.check_rounded : Icons.close_rounded,
              color: Colors.white,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  student?.name ??
                      (result.success ? 'Presensi diterima' : 'Scan gagal'),
                  style: TextStyle(color: color, fontWeight: FontWeight.w800),
                ),
                if (student != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    '${student.className} · NISN ${student.nisn}',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 12,
                    ),
                  ),
                ],
                const SizedBox(height: 5),
                Text(
                  result.message,
                  style: const TextStyle(fontSize: 12, height: 1.35),
                ),
                if (result.absencePeriodCompleted) ...[
                  const SizedBox(height: 7),
                  const Text(
                    'Periode berhalangan siswa otomatis diselesaikan.',
                    style: TextStyle(
                      color: NusaColors.primary,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AttendanceTile extends StatelessWidget {
  const _AttendanceTile({required this.attendance});

  final WorshipScanAttendance attendance;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        _StudentAvatar(
          name: attendance.studentName,
          photoUrl: attendance.photoUrl,
        ),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                attendance.studentName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 3),
              Text(
                '${attendance.className} · NISN ${attendance.nisn}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            const Icon(
              Icons.check_circle_rounded,
              color: NusaColors.success,
              size: 18,
            ),
            const SizedBox(height: 3),
            Text(
              attendance.scanTime.substring(
                0,
                attendance.scanTime.length.clamp(0, 5),
              ),
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ],
    ),
  );
}

class _StudentAvatar extends StatelessWidget {
  const _StudentAvatar({required this.name, this.photoUrl});

  final String name;
  final String? photoUrl;

  @override
  Widget build(BuildContext context) {
    final url = photoUrl;
    return CircleAvatar(
      radius: 22,
      backgroundColor: NusaColors.surfaceBlue,
      foregroundImage: url == null || url.isEmpty ? null : NetworkImage(url),
      child: Text(
        name.isEmpty ? '?' : name.substring(0, 1).toUpperCase(),
        style: const TextStyle(
          color: NusaColors.primary,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _EmptyRecentAttendance extends StatelessWidget {
  const _EmptyRecentAttendance();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: NusaColors.outline),
    ),
    child: const Column(
      children: [
        Icon(
          Icons.qr_code_2_rounded,
          color: NusaColors.textSecondary,
          size: 38,
        ),
        SizedBox(height: 8),
        Text(
          'Belum ada kartu yang dipindai pada kegiatan ini.',
          textAlign: TextAlign.center,
          style: TextStyle(color: NusaColors.textSecondary),
        ),
      ],
    ),
  );
}

class _ScanError extends StatelessWidget {
  const _ScanError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.camera_alt_outlined,
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

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Halaman scan ibadah belum dapat dimuat.';
