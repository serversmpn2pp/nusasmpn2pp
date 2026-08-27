import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_placement/data/student_placement_repository.dart';
import 'package:nusa/features/student_placement/domain/student_placement.dart';

class StudentPlacementController extends AsyncNotifier<StudentPlacementPage> {
  int? _academicYearId;
  int? _classId;
  String _query = '';
  int _requestVersion = 0;

  @override
  Future<StudentPlacementPage> build() => _fetch();

  Future<void> selectAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    _classId = null;
    _query = '';
    await refresh();
  }

  Future<void> selectClass(int? value) async {
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
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<StudentPlacementPage> _fetch() async {
    try {
      final result = await ref
          .read(studentPlacementRepositoryProvider)
          .fetch(
            academicYearId: _academicYearId,
            classId: _classId,
            query: _query,
          );
      _academicYearId = result.selectedAcademicYearId;
      _classId = result.selectedClassId;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final studentPlacementControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentPlacementController,
      StudentPlacementPage
    >(StudentPlacementController.new);

final studentPlacementActionsProvider = Provider<StudentPlacementActions>(
  StudentPlacementActions.new,
);

class StudentPlacementActions {
  StudentPlacementActions(this._ref);

  final Ref _ref;

  Future<int> place(StudentPlacementFormValue value) async {
    try {
      return await _ref.read(studentPlacementRepositoryProvider).place(value);
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
