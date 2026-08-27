import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/my_grades/data/my_grades_repository.dart';
import 'package:nusa/features/my_grades/domain/my_grades.dart';

class MyGradesController extends AsyncNotifier<MyGradesPage> {
  int? _academicYearId;
  String _semester = 'ganjil';
  int _requestVersion = 0;

  @override
  Future<MyGradesPage> build() => _fetch();

  Future<void> selectAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
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
      _academicYearId ??= current?.filter.academicYearId;
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

  Future<MyGradesPage> _fetch() => _guard(() async {
    final result = await ref
        .read(myGradesRepositoryProvider)
        .fetch(academicYearId: _academicYearId, semester: _semester);
    _academicYearId = result.filter.academicYearId;
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

final myGradesControllerProvider =
    AsyncNotifierProvider.autoDispose<MyGradesController, MyGradesPage>(
      MyGradesController.new,
    );
