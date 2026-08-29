import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/employee_attendance_settings/data/employee_attendance_settings_repository.dart';
import 'package:nusa/features/employee_attendance_settings/domain/employee_attendance_settings.dart';

class EmployeeAttendanceSettingsController
    extends AsyncNotifier<EmployeeAttendanceSettingsCatalog> {
  String _query = '';
  String _day = 'semua';
  String _scope = 'semua_cakupan';
  String _status = 'semua_status';
  int _requestVersion = 0;
  Timer? _searchTimer;

  @override
  Future<EmployeeAttendanceSettingsCatalog> build() {
    ref.onDispose(() => _searchTimer?.cancel());
    return _fetch();
  }

  void search(String value) {
    _query = value;
    _searchTimer?.cancel();
    _searchTimer = Timer(const Duration(milliseconds: 350), refresh);
  }

  Future<void> filterDay(String value) async {
    if (_day == value) return;
    _day = value;
    await refresh();
  }

  Future<void> filterScope(String value) async {
    if (_scope == value) return;
    _scope = value;
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

  Future<EmployeeAttendanceSettingsCatalog> _fetch() async {
    try {
      return await ref
          .read(employeeAttendanceSettingsRepositoryProvider)
          .fetch(query: _query, day: _day, scope: _scope, status: _status);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final employeeAttendanceSettingsControllerProvider =
    AsyncNotifierProvider.autoDispose<
      EmployeeAttendanceSettingsController,
      EmployeeAttendanceSettingsCatalog
    >(EmployeeAttendanceSettingsController.new);

final employeeAttendanceSettingsActionsProvider =
    Provider<EmployeeAttendanceSettingsActions>(
      EmployeeAttendanceSettingsActions.new,
    );

class EmployeeAttendanceSettingsActions {
  EmployeeAttendanceSettingsActions(this._ref);

  final Ref _ref;

  Future<void> create(EmployeeAttendanceSettingsFormValue value) =>
      _guard(() async {
        await _ref
            .read(employeeAttendanceSettingsRepositoryProvider)
            .create(value);
        _ref.invalidate(employeeAttendanceSettingsControllerProvider);
      });

  Future<void> update({
    required int id,
    required EmployeeAttendanceSettingsFormValue value,
  }) => _guard(() async {
    await _ref
        .read(employeeAttendanceSettingsRepositoryProvider)
        .update(id: id, value: value);
    _ref.invalidate(employeeAttendanceSettingsControllerProvider);
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
