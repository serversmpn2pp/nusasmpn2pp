import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/grade_recap/data/grade_recap_repository.dart';
import 'package:nusa/features/grade_recap/domain/grade_recap.dart';

class GradeRecapController extends AsyncNotifier<GradeRecapPage> {
  int? _assignmentId;
  String _semester = 'ganjil';
  int _requestVersion = 0;

  @override
  Future<GradeRecapPage> build() => _fetch();

  Future<void> selectAssignment(int? value) async {
    if (_assignmentId == value) return;
    _assignmentId = value;
    await _reload(initializeFromState: false);
  }

  Future<void> selectSemester(String value) async {
    if (_semester == value) return;
    _semester = value;
    await _reload(initializeFromState: false);
  }

  Future<void> refresh() => _reload(initializeFromState: true);

  Future<void> _reload({required bool initializeFromState}) async {
    if (initializeFromState) {
      final current = state.value;
      _assignmentId ??= current?.filter.assignmentId;
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

  Future<GradeRecapPage> _fetch() => _guard(() async {
    final result = await ref
        .read(gradeRecapRepositoryProvider)
        .fetch(assignmentId: _assignmentId, semester: _semester);
    _assignmentId = result.filter.assignmentId;
    _semester = result.filter.semester;
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

final gradeRecapControllerProvider =
    AsyncNotifierProvider.autoDispose<GradeRecapController, GradeRecapPage>(
      GradeRecapController.new,
    );
