import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/late_point_setting/data/late_point_setting_repository.dart';
import 'package:nusa/features/late_point_setting/domain/late_point_setting.dart';

class LatePointSettingController extends AsyncNotifier<LatePointSettingPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<LatePointSettingPage> build() => _fetch();

  Future<void> search(String value) async {
    _query = value.trim();
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

  Future<LatePointSettingPage> _fetch() async {
    try {
      return await ref
          .read(latePointSettingRepositoryProvider)
          .fetch(query: _query, status: _status);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final latePointSettingControllerProvider =
    AsyncNotifierProvider.autoDispose<
      LatePointSettingController,
      LatePointSettingPage
    >(LatePointSettingController.new);

final latePointSettingActionsProvider = Provider<LatePointSettingActions>(
  LatePointSettingActions.new,
);

class LatePointSettingActions {
  LatePointSettingActions(this._ref);

  final Ref _ref;

  Future<void> update({
    required int academicYearId,
    required LatePointSettingFormValue value,
  }) async {
    try {
      await _ref
          .read(latePointSettingRepositoryProvider)
          .update(academicYearId: academicYearId, value: value);
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
