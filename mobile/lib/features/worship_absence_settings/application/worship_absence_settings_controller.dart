import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/worship_absence_settings/data/worship_absence_settings_repository.dart';
import 'package:nusa/features/worship_absence_settings/domain/worship_absence_settings.dart';

class WorshipAbsenceSettingsController
    extends AsyncNotifier<WorshipAbsenceSettingsPage> {
  @override
  Future<WorshipAbsenceSettingsPage> build() => _fetch();

  Future<void> refresh() async {
    state = const AsyncLoading();
    try {
      state = AsyncData(await _fetch());
    } catch (error, stackTrace) {
      state = AsyncError(error, stackTrace);
    }
  }

  Future<WorshipAbsenceSettingsPage> _fetch() async {
    try {
      return await ref.read(worshipAbsenceSettingsRepositoryProvider).fetch();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final worshipAbsenceSettingsControllerProvider =
    AsyncNotifierProvider.autoDispose<
      WorshipAbsenceSettingsController,
      WorshipAbsenceSettingsPage
    >(WorshipAbsenceSettingsController.new);

final worshipAbsenceSettingsActionsProvider =
    Provider<WorshipAbsenceSettingsActions>(WorshipAbsenceSettingsActions.new);

class WorshipAbsenceSettingsActions {
  WorshipAbsenceSettingsActions(this._ref);

  final Ref _ref;

  Future<void> updateSettings(WorshipAbsenceSettingsValue value) => _guard(
    () => _ref
        .read(worshipAbsenceSettingsRepositoryProvider)
        .updateSettings(value),
  );

  Future<void> saveCompanion(WorshipCompanionAssignmentValue value) => _guard(
    () => _ref
        .read(worshipAbsenceSettingsRepositoryProvider)
        .saveCompanion(value),
  );

  Future<void> deactivateCompanion(int id) => _guard(
    () => _ref
        .read(worshipAbsenceSettingsRepositoryProvider)
        .deactivateCompanion(id),
  );

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
