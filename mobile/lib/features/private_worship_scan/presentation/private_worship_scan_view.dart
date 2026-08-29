import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/private_worship_scan/application/private_worship_scan_controller.dart';
import 'package:nusa/features/private_worship_scan/domain/private_worship_scan.dart';
import 'package:nusa/features/worship_scan/domain/worship_scan.dart';
import 'package:nusa/features/worship_scan/presentation/widgets/worship_scan_camera.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

typedef PrivateWorshipCameraBuilder = Widget Function(
  ValueChanged<String> onDetected,
  bool processing,
);

class PrivateWorshipScanView extends ConsumerStatefulWidget {
  const PrivateWorshipScanView({this.cameraBuilder, super.key});

  final PrivateWorshipCameraBuilder? cameraBuilder;

  @override
  ConsumerState<PrivateWorshipScanView> createState() =>
      _PrivateWorshipScanViewState();
}

class _PrivateWorshipScanViewState
    extends ConsumerState<PrivateWorshipScanView> {
  bool _processing = false;
  String? _lastRawValue;
  DateTime? _lastDetection;
  PrivateWorshipScanResult? _result;
  Timer? _hideResultTimer;

  @override
  void dispose() {
    _hideResultTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final dashboard = ref.watch(privateWorshipScanControllerProvider);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Scan Berhalangan'),
        actions: [
          IconButton(
            tooltip: 'Perbarui jadwal',
            onPressed: dashboard.isLoading || _processing
                ? null
                : () {
                    _hideResult();
                    ref
                        .read(privateWorshipScanControllerProvider.notifier)
                        .refresh();
                  },
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: dashboard.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _PrivateScanError(
            message: _errorMessage(error),
            onRetry: () => ref
                .read(privateWorshipScanControllerProvider.notifier)
                .refresh(),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () async {
              _hideResult();
              await ref
                  .read(privateWorshipScanControllerProvider.notifier)
                  .refresh();
            },
            child: ListView(
              key: const PageStorageKey<String>('private-worship-scan-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
              children: [
                _PrivateModeCard(dashboard: data),
                const SizedBox(height: 12),
                _PrivateScheduleCard(
                  dashboard: data,
                  processing: _processing,
                  onScheduleChanged: (value) {
                    if (value == null) return;
                    _hideResult();
                    ref
                        .read(privateWorshipScanControllerProvider.notifier)
                        .selectSchedule(value);
                  },
                ),
                const SizedBox(height: 14),
                if (data.scanOpen)
                  _buildScannerPanel()
                else
                  _PrivateCameraUnavailable(status: data.scheduleStatus),
                const SizedBox(height: 14),
                _PrivacyGuidanceCard(
                  confirmationDayLimit: data.confirmationDayLimit,
                  settingsActive: data.settingsActive,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildScannerPanel() => Stack(
    children: [
      _buildCamera(),
      if (_result case final result?)
        Positioned.fill(child: _PrivateScanResultOverlay(result: result)),
    ],
  );

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
    if (_processing || _result != null || value.isEmpty) return;

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
          .read(privateWorshipScanControllerProvider.notifier)
          .submit(value);
      if (!mounted) return;
      setState(() => _result = result);
      unawaited(
        result.success
            ? HapticFeedback.mediumImpact()
            : HapticFeedback.vibrate(),
      );
      _scheduleResultHide(result.success);
    } catch (error) {
      if (!mounted) return;
      final count = ref
          .read(privateWorshipScanControllerProvider)
          .value
          ?.todayCount;
      setState(
        () => _result = PrivateWorshipScanResult(
          success: false,
          isNew: false,
          status: 'gangguan',
          message: _errorMessage(error),
          serverTime: '',
          todayCount: count ?? 0,
        ),
      );
      _scheduleResultHide(false);
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

  void _scheduleResultHide(bool success) {
    _hideResultTimer?.cancel();
    _hideResultTimer = Timer(Duration(seconds: success ? 5 : 3), _hideResult);
  }

  void _hideResult() {
    _hideResultTimer?.cancel();
    _hideResultTimer = null;
    if (mounted && _result != null) setState(() => _result = null);
  }
}

class _PrivateModeCard extends StatelessWidget {
  const _PrivateModeCard({required this.dashboard});

  final PrivateWorshipScanDashboard dashboard;

  @override
  Widget build(BuildContext context) {
    final scope = dashboard.classScope.map((item) => item.name).join(', ');
    return Container(
      key: const Key('private-worship-mode-card'),
      padding: const EdgeInsets.all(17),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primaryDark, NusaColors.primary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.18),
            blurRadius: 18,
            offset: const Offset(0, 8),
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
                  color: Colors.white.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.lock_person_rounded,
                  color: NusaColors.accent,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'MODE PRIVAT',
                      style: TextStyle(
                        color: NusaColors.accent,
                        fontSize: 11,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1.2,
                      ),
                    ),
                    SizedBox(height: 3),
                    Text(
                      'Pendamping Ibadah Siswi',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            dashboard.privacyMessage,
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 12,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 10),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              scope.isEmpty
                  ? 'Cakupan kelas belum tersedia'
                  : 'Cakupan: $scope',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PrivateScheduleCard extends StatelessWidget {
  const _PrivateScheduleCard({
    required this.dashboard,
    required this.processing,
    required this.onScheduleChanged,
  });

  final PrivateWorshipScanDashboard dashboard;
  final bool processing;
  final ValueChanged<int?> onScheduleChanged;

  @override
  Widget build(BuildContext context) {
    final selected = dashboard.selectedSchedule;
    final statusColor = dashboard.scanOpen
        ? NusaColors.success
        : NusaColors.textSecondary;
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      dashboard.dateLabel,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      dashboard.academicYearName == null
                          ? 'Tahun pelajaran belum aktif'
                          : 'Tahun Pelajaran ${dashboard.academicYearName}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
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
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          if (dashboard.schedules.length > 1) ...[
            const SizedBox(height: 13),
            NusaDropdownField<int>(
              fieldKey: const Key('private-worship-schedule-filter'),
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
          const SizedBox(height: 11),
          Row(
            children: [
              Icon(Icons.schedule_rounded, size: 18, color: statusColor),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  selected == null
                      ? dashboard.scheduleStatus.message
                      : '${selected.activity} · ${selected.scanRange}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 12,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                '${dashboard.todayCount} scan',
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PrivateScanResultOverlay extends StatelessWidget {
  const _PrivateScanResultOverlay({required this.result});

  final PrivateWorshipScanResult result;

  @override
  Widget build(BuildContext context) {
    final color = result.success ? NusaColors.success : Colors.red.shade700;
    final student = result.student;
    return ClipRRect(
      key: const Key('private-worship-scan-result'),
      borderRadius: BorderRadius.circular(20),
      child: ColoredBox(
        color: result.success
            ? const Color(0xFFF2FBF4)
            : const Color(0xFFFFF5F5),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(22),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                child: Icon(
                  result.success ? Icons.check_rounded : Icons.close_rounded,
                  color: Colors.white,
                  size: 34,
                ),
              ),
              const SizedBox(height: 13),
              Text(
                student?.name ??
                    (result.success ? 'Catatan diterima' : 'Scan ditolak'),
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: color,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (student != null) ...[
                const SizedBox(height: 5),
                Text(
                  '${student.className} · NISN ${student.nisn}',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 12,
                  ),
                ),
                if (student.dayNumber case final day?) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 11,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: NusaColors.primary.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      'Hari ke-$day',
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ],
              const SizedBox(height: 11),
              Text(
                result.message,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 12, height: 1.4),
              ),
              const SizedBox(height: 14),
              const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.visibility_off_rounded,
                    size: 16,
                    color: NusaColors.textSecondary,
                  ),
                  SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      'Identitas ini akan disembunyikan otomatis.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
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

class _PrivateCameraUnavailable extends StatelessWidget {
  const _PrivateCameraUnavailable({required this.status});

  final WorshipScanScheduleStatus status;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('private-worship-camera-unavailable'),
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
          Icons.lock_clock_rounded,
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

class _PrivacyGuidanceCard extends StatelessWidget {
  const _PrivacyGuidanceCard({
    required this.confirmationDayLimit,
    required this.settingsActive,
  });

  final int confirmationDayLimit;
  final bool settingsActive;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: NusaColors.primary.withValues(alpha: 0.12)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(
              Icons.privacy_tip_outlined,
              color: NusaColors.primary,
              size: 20,
            ),
            SizedBox(width: 8),
            Text(
              'Pedoman privasi',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
          ],
        ),
        const SizedBox(height: 10),
        const _GuidanceLine(
          text: 'Gunakan percakapan pribadi, tanpa pemeriksaan fisik.',
        ),
        const _GuidanceLine(
          text: 'Jangan membagikan identitas atau hasil scan ke grup umum.',
        ),
        _GuidanceLine(
          text: settingsActive
              ? 'Konfirmasi privat diperlukan setelah batas $confirmationDayLimit hari.'
              : 'Pengingat konfirmasi privat sedang dinonaktifkan.',
        ),
      ],
    ),
  );
}

class _GuidanceLine extends StatelessWidget {
  const _GuidanceLine({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(top: 5),
          child: Icon(Icons.circle, size: 5, color: NusaColors.textSecondary),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );
}

class _PrivateScanError extends StatelessWidget {
  const _PrivateScanError({required this.message, required this.onRetry});

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
            Icons.lock_person_outlined,
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
    : 'Halaman scan privat belum dapat dimuat.';
