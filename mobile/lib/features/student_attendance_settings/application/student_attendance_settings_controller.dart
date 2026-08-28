import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_attendance_settings/data/student_attendance_settings_repository.dart';
import 'package:nusa/features/student_attendance_settings/domain/student_attendance_settings.dart';

class StudentAttendanceSettingsController
    extends AsyncNotifier<StudentAttendanceSettingsCatalog> {
  String _day = 'semua';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<StudentAttendanceSettingsCatalog> build() => _fetch();

  Future<void> filterDay(String value) async {
    if (_day == value) return;
    _day = value;
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<StudentAttendanceSettingsCatalog> _fetch() async {
    try {
      return await ref
          .read(studentAttendanceSettingsRepositoryProvider)
          .fetch(day: _day, status: _status);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final studentAttendanceSettingsControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentAttendanceSettingsController,
      StudentAttendanceSettingsCatalog
    >(StudentAttendanceSettingsController.new);

final studentAttendanceSettingsActionsProvider =
    Provider<StudentAttendanceSettingsActions>(
      StudentAttendanceSettingsActions.new,
    );

class StudentAttendanceSettingsActions {
  StudentAttendanceSettingsActions(this._ref);

  final Ref _ref;

  Future<void> create(StudentAttendanceSettingsFormValue value) =>
      _guard(() async {
        await _ref
            .read(studentAttendanceSettingsRepositoryProvider)
            .create(value);
        _ref.invalidate(studentAttendanceSettingsControllerProvider);
      });

  Future<void> update({
    required int id,
    required StudentAttendanceSettingsFormValue value,
  }) => _guard(() async {
    await _ref
        .read(studentAttendanceSettingsRepositoryProvider)
        .update(id: id, value: value);
    _ref.invalidate(studentAttendanceSettingsControllerProvider);
  });

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
