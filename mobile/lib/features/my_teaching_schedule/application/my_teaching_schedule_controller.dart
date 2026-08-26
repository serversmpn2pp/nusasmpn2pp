import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/my_teaching_schedule/data/my_teaching_schedule_repository.dart';
import 'package:nusa/features/my_teaching_schedule/domain/my_teaching_schedule.dart';

class MyTeachingScheduleController
    extends AsyncNotifier<MyTeachingSchedulePage> {
  int? _academicYearId;
  int _requestVersion = 0;

  @override
  Future<MyTeachingSchedulePage> build() => _fetch();

  Future<void> selectAcademicYear(int value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    await refresh();
  }

  Future<void> refresh() async {
    _academicYearId ??= state.value?.selectedAcademicYearId;
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<MyTeachingSchedulePage> _fetch() => _guard(
    () => ref
        .read(myTeachingScheduleRepositoryProvider)
        .fetch(academicYearId: _academicYearId),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final myTeachingScheduleControllerProvider =
    AsyncNotifierProvider.autoDispose<
      MyTeachingScheduleController,
      MyTeachingSchedulePage
    >(MyTeachingScheduleController.new);
