import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/parent_child_exams/data/parent_child_exams_repository.dart';
import 'package:nusa/features/parent_child_exams/domain/parent_child_exams.dart';

class ParentChildExamsController extends AsyncNotifier<ParentChildExamsPage> {
  int? _studentId;
  int _requestVersion = 0;

  @override
  Future<ParentChildExamsPage> build() => _fetch();

  Future<void> refresh() => _replace();

  Future<void> selectStudent(int studentId) async {
    if (_studentId == studentId &&
        state.value?.selectedStudentId == studentId) {
      return;
    }
    _studentId = studentId;
    await _replace();
  }

  Future<void> _replace() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<ParentChildExamsPage> _fetch() async {
    try {
      final result = await ref
          .read(parentChildExamsRepositoryProvider)
          .fetch(studentId: _studentId);
      _studentId = result.selectedStudentId;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final parentChildExamsControllerProvider =
    AsyncNotifierProvider.autoDispose<
      ParentChildExamsController,
      ParentChildExamsPage
    >(ParentChildExamsController.new);
