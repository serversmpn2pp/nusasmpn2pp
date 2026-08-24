import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/academic_year/data/academic_year_repository.dart';
import 'package:nusa/features/academic_year/domain/academic_year.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';

class AcademicYearController extends AsyncNotifier<AcademicYearPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<AcademicYearPage> build() => _fetch(page: 1);

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

  Future<AcademicYearPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(academicYearRepositoryProvider)
          .fetch(query: _query, status: _status, page: page);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final academicYearControllerProvider =
    AsyncNotifierProvider.autoDispose<AcademicYearController, AcademicYearPage>(
      AcademicYearController.new,
    );

final academicYearActionsProvider = Provider<AcademicYearActions>(
  AcademicYearActions.new,
);

class AcademicYearActions {
  AcademicYearActions(this._ref);

  final Ref _ref;

  Future<void> create(AcademicYearFormValue value) =>
      _guard(() => _ref.read(academicYearRepositoryProvider).create(value));

  Future<void> update({
    required int id,
    required AcademicYearFormValue value,
  }) => _guard(
    () =>
        _ref.read(academicYearRepositoryProvider).update(id: id, value: value),
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
