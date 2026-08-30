import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/early_warning_setting/data/early_warning_setting_repository.dart';
import 'package:nusa/features/early_warning_setting/domain/early_warning_setting.dart';

class EarlyWarningSettingController
    extends AsyncNotifier<EarlyWarningSettingPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<EarlyWarningSettingPage> build() => _fetch();

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

  Future<EarlyWarningSettingPage> _fetch() async {
    try {
      return await ref
          .read(earlyWarningSettingRepositoryProvider)
          .fetch(query: _query, status: _status);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final earlyWarningSettingControllerProvider =
    AsyncNotifierProvider.autoDispose<
      EarlyWarningSettingController,
      EarlyWarningSettingPage
    >(EarlyWarningSettingController.new);

final earlyWarningSettingActionsProvider = Provider<EarlyWarningSettingActions>(
  EarlyWarningSettingActions.new,
);

class EarlyWarningSettingActions {
  EarlyWarningSettingActions(this._ref);

  final Ref _ref;

  Future<void> update({
    required int academicYearId,
    required EarlyWarningSettingFormValue value,
  }) async {
    try {
      await _ref
          .read(earlyWarningSettingRepositoryProvider)
          .update(academicYearId: academicYearId, value: value);
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
