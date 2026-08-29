import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/worship_scan/data/worship_scan_repository.dart';
import 'package:nusa/features/worship_scan/domain/worship_scan.dart';

class WorshipScanController extends AsyncNotifier<WorshipScanDashboard> {
  int? _scheduleId;
  int _requestVersion = 0;

  @override
  Future<WorshipScanDashboard> build() => _fetch();

  Future<void> selectSchedule(int scheduleId) async {
    if (_scheduleId == scheduleId) return;
    _scheduleId = scheduleId;
    await refresh();
  }

  Future<void> refresh({bool silent = false}) async {
    final version = ++_requestVersion;
    if (!silent) state = const AsyncLoading();
    try {
      final result = await _fetch();
      _scheduleId = result.selectedScheduleId;
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion && (!silent || !state.hasValue)) {
        state = AsyncError(error, stackTrace);
      }
    }
  }

  Future<WorshipScanResult> submit(String rawValue) async {
    final dashboard = state.value;
    final scheduleId = dashboard?.selectedScheduleId;
    if (dashboard == null || scheduleId == null || !dashboard.scanOpen) {
      throw const NetworkException(
        'Scan belum dapat dilakukan karena jadwal belum aktif.',
      );
    }

    try {
      final result = await ref
          .read(worshipScanRepositoryProvider)
          .submit(scheduleId: scheduleId, rawValue: rawValue);
      final current = state.value;
      if (current != null && current.selectedScheduleId == scheduleId) {
        state = AsyncData(current.applyResult(result));
      }
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<WorshipScanDashboard> _fetch() async {
    try {
      final result = await ref
          .read(worshipScanRepositoryProvider)
          .fetch(scheduleId: _scheduleId);
      _scheduleId ??= result.selectedScheduleId;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final worshipScanControllerProvider =
    AsyncNotifierProvider.autoDispose<
      WorshipScanController,
      WorshipScanDashboard
    >(WorshipScanController.new);
