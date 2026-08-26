import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/grade_entry/data/grade_entry_repository.dart';
import 'package:nusa/features/grade_entry/domain/grade_entry.dart';

class GradeEntryController extends AsyncNotifier<GradeEntryPage> {
  int? _assignmentId;
  String _semester = 'ganjil';
  int? _componentId;
  int _requestVersion = 0;

  @override
  Future<GradeEntryPage> build() => _fetch();

  Future<void> selectAssignment(int? value) async {
    if (_assignmentId == value) return;
    _assignmentId = value;
    _componentId = null;
    await _reload(initializeFromState: false);
  }

  Future<void> selectSemester(String value) async {
    if (_semester == value) return;
    _semester = value;
    _componentId = null;
    await _reload(initializeFromState: false);
  }

  Future<void> selectComponent(int? value) async {
    if (_componentId == value) return;
    _componentId = value;
    await _reload(initializeFromState: false);
  }

  Future<void> refresh() => _reload(initializeFromState: true);

  Future<void> _reload({required bool initializeFromState}) async {
    if (initializeFromState) {
      final current = state.value;
      _assignmentId ??= current?.filter.assignmentId;
      _componentId ??= current?.filter.componentId;
      _semester = current?.filter.semester ?? _semester;
    }
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<GradeEntryPage> _fetch() => _guard(() async {
    final result = await ref
        .read(gradeEntryRepositoryProvider)
        .fetch(
          assignmentId: _assignmentId,
          semester: _semester,
          componentId: _componentId,
        );
    _assignmentId = result.filter.assignmentId;
    _semester = result.filter.semester;
    _componentId = result.filter.componentId;
    return result;
  });

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final gradeEntryControllerProvider =
    AsyncNotifierProvider.autoDispose<GradeEntryController, GradeEntryPage>(
      GradeEntryController.new,
    );

final gradeEntryActionsProvider = Provider<GradeEntryActions>(
  GradeEntryActions.new,
);

class GradeEntryActions {
  GradeEntryActions(this._ref);

  final Ref _ref;

  Future<String> save(GradeEntryFormValue value) =>
      _guard(() => _ref.read(gradeEntryRepositoryProvider).save(value));

  Future<String> publish({
    required int assignmentId,
    required String semester,
  }) => _guard(
    () => _ref
        .read(gradeEntryRepositoryProvider)
        .publish(assignmentId: assignmentId, semester: semester),
  );

  Future<String> unpublish({
    required int assignmentId,
    required String semester,
  }) => _guard(
    () => _ref
        .read(gradeEntryRepositoryProvider)
        .unpublish(assignmentId: assignmentId, semester: semester),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
