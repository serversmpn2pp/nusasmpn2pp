import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_scan_status/data/student_scan_status_repository.dart';
import 'package:nusa/features/student_scan_status/domain/student_scan_status.dart';

class StudentScanStatusController
    extends AsyncNotifier<StudentScanStatusDashboard> {
  int? _classId;
  String _status = 'semua';
  String _query = '';
  int _requestVersion = 0;

  @override
  Future<StudentScanStatusDashboard> build() => _fetch();

  Future<void> filterClass(int? value) async {
    if (_classId == value) return;
    _classId = value;
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> search(String value) async {
    final query = value.trim();
    if (_query == query) return;
    _query = query;
    await refresh();
  }

  Future<void> refresh({bool silent = false}) async {
    if (silent && state.isLoading) return;
    final version = ++_requestVersion;
    final previous = state.value;
    if (!silent) state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version != _requestVersion) return;
      if (silent && previous != null) {
        state = AsyncData(previous);
      } else {
        state = AsyncError(error, stackTrace);
      }
    }
  }

  Future<StudentScanStatusDashboard> _fetch() async {
    try {
      return await ref
          .read(studentScanStatusRepositoryProvider)
          .fetch(classId: _classId, status: _status, query: _query);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final studentScanStatusControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentScanStatusController,
      StudentScanStatusDashboard
    >(StudentScanStatusController.new);
