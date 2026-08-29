import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/private_worship_scan/data/private_worship_scan_repository.dart';
import 'package:nusa/features/private_worship_scan/domain/private_worship_scan.dart';

class PrivateWorshipScanController
    extends AsyncNotifier<PrivateWorshipScanDashboard> {
  int? _scheduleId;
  int _requestVersion = 0;

  @override
  Future<PrivateWorshipScanDashboard> build() => _fetch();

  Future<void> selectSchedule(int scheduleId) async {
    if (_scheduleId == scheduleId) return;
    _scheduleId = scheduleId;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      _scheduleId = result.selectedScheduleId;
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<PrivateWorshipScanResult> submit(String rawValue) async {
    final dashboard = state.value;
    final scheduleId = dashboard?.selectedScheduleId;
    if (dashboard == null || scheduleId == null || !dashboard.scanOpen) {
      throw const NetworkException(
        'Scan privat belum dapat dilakukan karena jadwal belum aktif.',
      );
    }

    try {
      final result = await ref
          .read(privateWorshipScanRepositoryProvider)
          .submit(scheduleId: scheduleId, rawValue: rawValue);
      final current = state.value;
      if (current != null && current.selectedScheduleId == scheduleId) {
        state = AsyncData(current.withTodayCount(result.todayCount));
      }
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<PrivateWorshipScanDashboard> _fetch() async {
    try {
      final result = await ref
          .read(privateWorshipScanRepositoryProvider)
          .fetch(scheduleId: _scheduleId);
      _scheduleId ??= result.selectedScheduleId;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final privateWorshipScanControllerProvider =
    AsyncNotifierProvider.autoDispose<
      PrivateWorshipScanController,
      PrivateWorshipScanDashboard
    >(PrivateWorshipScanController.new);
