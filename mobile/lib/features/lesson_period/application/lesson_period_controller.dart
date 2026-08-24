import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/lesson_period/data/lesson_period_repository.dart';
import 'package:nusa/features/lesson_period/domain/lesson_period.dart';

class LessonPeriodController extends AsyncNotifier<LessonPeriodCatalog> {
  String _day = 'semua';
  String _status = 'semua';

  @override
  Future<LessonPeriodCatalog> build() => _fetch();

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
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<LessonPeriodCatalog> _fetch() async {
    try {
      return await ref
          .read(lessonPeriodRepositoryProvider)
          .fetch(day: _day, status: _status);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final lessonPeriodControllerProvider =
    AsyncNotifierProvider.autoDispose<
      LessonPeriodController,
      LessonPeriodCatalog
    >(LessonPeriodController.new);

final lessonPeriodActionsProvider = Provider<LessonPeriodActions>(
  LessonPeriodActions.new,
);

class LessonPeriodActions {
  LessonPeriodActions(this._ref);

  final Ref _ref;

  Future<void> create({
    required List<String> days,
    required String insertionPosition,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  }) => _guard(() async {
    await _ref
        .read(lessonPeriodRepositoryProvider)
        .create(
          days: days,
          insertionPosition: insertionPosition,
          label: label,
          startTime: startTime,
          endTime: endTime,
          type: type,
          active: active,
          notes: notes,
        );
    _ref.invalidate(lessonPeriodControllerProvider);
  });

  Future<void> update({
    required int id,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  }) => _guard(() async {
    await _ref
        .read(lessonPeriodRepositoryProvider)
        .update(
          id: id,
          label: label,
          startTime: startTime,
          endTime: endTime,
          type: type,
          active: active,
          notes: notes,
        );
    _ref.invalidate(lessonPeriodControllerProvider);
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
