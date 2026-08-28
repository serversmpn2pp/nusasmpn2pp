import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/teacher_duty/data/teacher_duty_repository.dart';
import 'package:nusa/features/teacher_duty/domain/teacher_duty.dart';

class DutyScheduleController extends AsyncNotifier<DutyScheduleCatalog> {
  int? _academicYearId;
  String _day = 'semua';
  String _status = 'semua';
  String _query = '';
  @override
  Future<DutyScheduleCatalog> build() => _fetch();
  Future<DutyScheduleCatalog> _fetch() async {
    try {
      final result = await ref
          .read(teacherDutyRepositoryProvider)
          .schedules(
            academicYearId: _academicYearId,
            day: _day,
            status: _status,
            query: _query,
          );
      _academicYearId ??= result.academicYearId;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<void> refresh() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<void> filterYear(int? value) async {
    _academicYearId = value;
    await refresh();
  }

  Future<void> filterDay(String value) async {
    _day = value;
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    _status = value;
    await refresh();
  }

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }
}

final dutyScheduleControllerProvider =
    AsyncNotifierProvider.autoDispose<
      DutyScheduleController,
      DutyScheduleCatalog
    >(DutyScheduleController.new);

class MyDutyController extends AsyncNotifier<MyDutyDashboard> {
  int? _classId;
  String _status = 'semua';
  String _query = '';
  int _page = 1;
  bool _loadingMore = false;
  @override
  Future<MyDutyDashboard> build() => _fetch();
  Future<MyDutyDashboard> _fetch() async {
    try {
      return await ref
          .read(teacherDutyRepositoryProvider)
          .myDuty(
            classId: _classId,
            status: _status,
            query: _query,
            page: _page,
          );
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<void> refresh() async {
    _page = 1;
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<void> filterClass(int? value) async {
    _classId = value;
    _page = 1;
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    _status = value;
    _page = 1;
    await refresh();
  }

  Future<void> search(String value) async {
    _query = value.trim();
    _page = 1;
    await refresh();
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || _loadingMore) return;
    _loadingMore = true;
    _page = current.page + 1;
    try {
      final next = await _fetch();
      state = AsyncData(
        MyDutyDashboard(
          dateLabel: next.dateLabel,
          academicYear: next.academicYear,
          today: next.today,
          mySchedules: next.mySchedules,
          activeSubjectTeacher: next.activeSubjectTeacher,
          canRecordToday: next.canRecordToday,
          items: [...current.items, ...next.items],
          summary: next.summary,
          classes: next.classes,
          classId: next.classId,
          status: next.status,
          query: next.query,
          page: next.page,
          hasMore: next.hasMore,
        ),
      );
    } catch (error, stackTrace) {
      _page = current.page;
      state = AsyncError(error, stackTrace);
    } finally {
      _loadingMore = false;
    }
  }
}

final myDutyControllerProvider =
    AsyncNotifierProvider.autoDispose<MyDutyController, MyDutyDashboard>(
      MyDutyController.new,
    );

final teacherDutyActionsProvider = Provider<TeacherDutyActions>(
  TeacherDutyActions.new,
);

class TeacherDutyActions {
  TeacherDutyActions(this._ref);
  final Ref _ref;
  Future<DutyScheduleReference> reference([int? yearId]) =>
      _ref.read(teacherDutyRepositoryProvider).reference(yearId);
  Future<void> create(DutyScheduleFormValue value) => _guard(() async {
    await _ref.read(teacherDutyRepositoryProvider).create(value);
    _ref.invalidate(dutyScheduleControllerProvider);
  });
  Future<void> update(int id, DutyScheduleFormValue value) => _guard(() async {
    await _ref.read(teacherDutyRepositoryProvider).update(id, value);
    _ref.invalidate(dutyScheduleControllerProvider);
  });
  Future<void> delete(int id) => _guard(() async {
    await _ref.read(teacherDutyRepositoryProvider).delete(id);
    _ref.invalidate(dutyScheduleControllerProvider);
  });
  Future<void> record({
    required int classMemberId,
    required String status,
    required String notes,
  }) => _guard(() async {
    await _ref
        .read(teacherDutyRepositoryProvider)
        .record(classMemberId: classMemberId, status: status, notes: notes);
    _ref.invalidate(myDutyControllerProvider);
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
