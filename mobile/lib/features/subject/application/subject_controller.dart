import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/subject/data/subject_repository.dart';
import 'package:nusa/features/subject/domain/subject.dart';

class SubjectController extends AsyncNotifier<SubjectPage> {
  String _query = '';
  String _status = 'semua';
  String _level = 'semua';
  int? _academicYearId;
  int _requestVersion = 0;

  @override
  Future<SubjectPage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> filterLevel(String value) async {
    if (_level == value) return;
    _level = value;
    await refresh();
  }

  Future<void> filterAcademicYear(int value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(page: 1);
      _academicYearId = result.academicYearId;
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

  Future<SubjectPage> _fetch({required int page}) async {
    try {
      final result = await ref
          .read(subjectRepositoryProvider)
          .fetch(
            query: _query,
            status: _status,
            level: _level,
            page: page,
            academicYearId: _academicYearId,
          );
      _academicYearId ??= result.academicYearId;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final subjectControllerProvider =
    AsyncNotifierProvider.autoDispose<SubjectController, SubjectPage>(
      SubjectController.new,
    );

final subjectReferenceProvider = FutureProvider.autoDispose((ref) async {
  try {
    return await ref.read(subjectRepositoryProvider).fetchReference();
  } on UnauthorizedException {
    await ref.read(authControllerProvider.notifier).logout();
    rethrow;
  }
});

final subjectActionsProvider = Provider<SubjectActions>(SubjectActions.new);

class SubjectActions {
  SubjectActions(this._ref);

  final Ref _ref;

  Future<void> create(SubjectFormValue value) => _guard(() async {
    await _ref.read(subjectRepositoryProvider).create(value);
    _ref.invalidate(subjectReferenceProvider);
  });

  Future<void> update({required int id, required SubjectFormValue value}) =>
      _guard(() async {
        await _ref.read(subjectRepositoryProvider).update(id: id, value: value);
        _ref.invalidate(subjectReferenceProvider);
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
