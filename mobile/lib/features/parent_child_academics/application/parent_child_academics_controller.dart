import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/parent_child_academics/data/parent_child_academics_repository.dart';
import 'package:nusa/features/parent_child_academics/domain/parent_child_academics.dart';

final parentChildAcademicsInitialTabProvider =
    Provider<ParentChildAcademicsTab>(
      (ref) => ParentChildAcademicsTab.schedule,
    );

final parentChildAcademicsInitialSemesterProvider = Provider<String>(
  (ref) => 'ganjil',
);

class ParentChildAcademicsController
    extends AsyncNotifier<ParentChildAcademicsPage> {
  ParentChildAcademicsTab _tab = ParentChildAcademicsTab.schedule;
  String _semester = 'ganjil';
  int? _studentId;
  int? _academicYearId;
  int _version = 0;

  @override
  Future<ParentChildAcademicsPage> build() {
    _tab = ref.watch(parentChildAcademicsInitialTabProvider);
    _semester = ref.watch(parentChildAcademicsInitialSemesterProvider);
    return _fetch();
  }

  Future<void> refresh() => _replace();

  Future<void> selectTab(ParentChildAcademicsTab tab) async {
    if (_tab == tab && state.value?.tab == tab) return;
    _tab = tab;
    await _replace();
  }

  Future<void> selectStudent(int studentId) async {
    if (_studentId == studentId &&
        state.value?.selectedStudentId == studentId) {
      return;
    }
    _studentId = studentId;
    _academicYearId = null;
    await _replace();
  }

  Future<void> selectAcademicYear(int? academicYearId) async {
    if (_academicYearId == academicYearId &&
        state.value?.grades.filter.academicYearId == academicYearId) {
      return;
    }
    _academicYearId = academicYearId;
    await _replace();
  }

  Future<void> selectSemester(String semester) async {
    if (_semester == semester &&
        state.value?.grades.filter.semester == semester) {
      return;
    }
    _semester = semester;
    await _replace();
  }

  Future<void> _replace() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _version) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _version) state = AsyncError(error, stackTrace);
    }
  }

  Future<ParentChildAcademicsPage> _fetch() => _guard(() async {
    final result = await ref
        .read(parentChildAcademicsRepositoryProvider)
        .fetch(
          tab: _tab,
          semester: _semester,
          studentId: _studentId,
          academicYearId: _academicYearId,
        );
    _tab = result.tab;
    _studentId = result.selectedStudentId;
    _academicYearId = result.grades.filter.academicYearId;
    _semester = result.grades.filter.semester;
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

final parentChildAcademicsControllerProvider =
    AsyncNotifierProvider.autoDispose<
      ParentChildAcademicsController,
      ParentChildAcademicsPage
    >(
      ParentChildAcademicsController.new,
      dependencies: [
        parentChildAcademicsInitialTabProvider,
        parentChildAcademicsInitialSemesterProvider,
      ],
    );
