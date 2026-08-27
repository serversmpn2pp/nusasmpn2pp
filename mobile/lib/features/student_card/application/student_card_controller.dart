import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_card/data/student_card_repository.dart';
import 'package:nusa/features/student_card/domain/student_card.dart';

class StudentCardController extends AsyncNotifier<StudentCardPage> {
  int? _academicYearId;
  int? _classId;
  String _query = '';
  int _requestVersion = 0;

  @override
  Future<StudentCardPage> build() => _fetch(page: 1);

  Future<void> selectAcademicYear(int value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    _classId = null;
    _query = '';
    await refresh();
  }

  Future<void> selectClass(int value) async {
    if (_classId == value) return;
    _classId = value;
    _query = '';
    await refresh();
  }

  Future<void> search(String value) async {
    _query = value.trim();
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

  Future<StudentCardPage> _fetch({required int page}) async {
    try {
      final result = await ref
          .read(studentCardRepositoryProvider)
          .fetch(
            academicYearId: _academicYearId,
            classId: _classId,
            query: _query,
            page: page,
          );
      _academicYearId = result.selectedAcademicYearId;
      _classId = result.selectedClassId;
      _query = result.query;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final studentCardControllerProvider =
    AsyncNotifierProvider.autoDispose<StudentCardController, StudentCardPage>(
      StudentCardController.new,
    );
