import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/grade_component/data/grade_component_repository.dart';
import 'package:nusa/features/grade_component/domain/grade_component.dart';

class GradeComponentController extends AsyncNotifier<GradeComponentPage> {
  String _search = '';
  int? _academicYearId;
  String _semester = 'semua';
  String _type = 'semua';
  String _status = 'semua';
  int _requestVersion = 0;
  Timer? _debounce;

  @override
  Future<GradeComponentPage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(page: 1);
  }

  void search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (_search == value.trim()) return;
      _search = value.trim();
      unawaited(refresh());
    });
  }

  Future<void> filterAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    await refresh();
  }

  Future<void> filterSemester(String value) async {
    if (_semester == value) return;
    _semester = value;
    await refresh();
  }

  Future<void> filterType(String value) async {
    if (_type == value) return;
    _type = value;
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> refresh() async {
    final current = state.value;
    _academicYearId ??= current?.filter.academicYearId;
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(page: 1);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(page: current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<GradeComponentPage> _fetch({required int page}) => _guard(
    () => ref
        .read(gradeComponentRepositoryProvider)
        .fetch(
          search: _search,
          academicYearId: _academicYearId,
          semester: _semester,
          type: _type,
          status: _status,
          page: page,
        ),
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

final gradeComponentControllerProvider =
    AsyncNotifierProvider.autoDispose<
      GradeComponentController,
      GradeComponentPage
    >(GradeComponentController.new);

final gradeComponentActionsProvider = Provider<GradeComponentActions>(
  GradeComponentActions.new,
);

class GradeComponentActions {
  GradeComponentActions(this._ref);

  final Ref _ref;

  Future<void> create(GradeComponentFormValue value) =>
      _guard(() => _ref.read(gradeComponentRepositoryProvider).create(value));

  Future<void> update({
    required int id,
    required GradeComponentFormValue value,
  }) => _guard(
    () => _ref
        .read(gradeComponentRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> deactivate(int id) =>
      _guard(() => _ref.read(gradeComponentRepositoryProvider).deactivate(id));

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
