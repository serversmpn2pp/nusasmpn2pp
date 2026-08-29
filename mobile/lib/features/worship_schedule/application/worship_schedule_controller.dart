import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/worship_schedule/data/worship_schedule_repository.dart';
import 'package:nusa/features/worship_schedule/domain/worship_schedule.dart';

class WorshipScheduleController extends AsyncNotifier<WorshipSchedulePage> {
  int? _academicYearId;
  int? _activityId;
  int _requestVersion = 0;

  @override
  Future<WorshipSchedulePage> build() => _fetch();

  Future<void> selectAcademicYear(int value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    await refresh();
  }

  Future<void> selectActivity(int value) async {
    if (_activityId == value) return;
    _activityId = value;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      _academicYearId = result.selectedAcademicYearId;
      _activityId = result.selectedActivityId;
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<WorshipSchedulePage> _fetch() async {
    try {
      final result = await ref
          .read(worshipScheduleRepositoryProvider)
          .fetch(academicYearId: _academicYearId, activityId: _activityId);
      _academicYearId ??= result.selectedAcademicYearId;
      _activityId ??= result.selectedActivityId;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final worshipScheduleControllerProvider =
    AsyncNotifierProvider.autoDispose<
      WorshipScheduleController,
      WorshipSchedulePage
    >(WorshipScheduleController.new);

final worshipScheduleActionsProvider = Provider<WorshipScheduleActions>(
  WorshipScheduleActions.new,
);

class WorshipScheduleActions {
  WorshipScheduleActions(this._ref);

  final Ref _ref;

  Future<void> create(WorshipScheduleFormValue value) =>
      _guard(() => _ref.read(worshipScheduleRepositoryProvider).create(value));

  Future<void> update({
    required int id,
    required WorshipScheduleFormValue value,
  }) => _guard(
    () => _ref
        .read(worshipScheduleRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> deactivate(int id) =>
      _guard(() => _ref.read(worshipScheduleRepositoryProvider).deactivate(id));

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
