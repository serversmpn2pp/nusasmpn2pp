import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/teaching_assignment/data/teaching_assignment_repository.dart';
import 'package:nusa/features/teaching_assignment/domain/teaching_assignment.dart';

class TeachingAssignmentController
    extends AsyncNotifier<TeachingAssignmentPage> {
  String _query = '';
  String _status = 'semua';
  int? _academicYearId;
  int _requestVersion = 0;

  @override
  Future<TeachingAssignmentPage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> filterAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
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

  Future<TeachingAssignmentPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(teachingAssignmentRepositoryProvider)
          .fetch(
            query: _query,
            status: _status,
            page: page,
            academicYearId: _academicYearId,
          );
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final teachingAssignmentControllerProvider =
    AsyncNotifierProvider.autoDispose<
      TeachingAssignmentController,
      TeachingAssignmentPage
    >(TeachingAssignmentController.new);

final teachingAssignmentReferenceProvider = FutureProvider.autoDispose((
  ref,
) async {
  try {
    return await ref
        .read(teachingAssignmentRepositoryProvider)
        .fetchReference();
  } on UnauthorizedException {
    await ref.read(authControllerProvider.notifier).logout();
    rethrow;
  }
});

final teachingAssignmentActionsProvider = Provider<TeachingAssignmentActions>(
  TeachingAssignmentActions.new,
);

class TeachingAssignmentActions {
  TeachingAssignmentActions(this._ref);

  final Ref _ref;

  Future<void> create({
    required int academicYearId,
    required List<int> classIds,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  }) => _guard(() async {
    await _ref
        .read(teachingAssignmentRepositoryProvider)
        .create(
          academicYearId: academicYearId,
          classIds: classIds,
          subjectId: subjectId,
          employeeId: employeeId,
          assignmentType: assignmentType,
          active: active,
          notes: notes,
        );
    _refresh();
  });

  Future<void> update({
    required int id,
    required int academicYearId,
    required int classId,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  }) => _guard(() async {
    await _ref
        .read(teachingAssignmentRepositoryProvider)
        .update(
          id: id,
          academicYearId: academicYearId,
          classId: classId,
          subjectId: subjectId,
          employeeId: employeeId,
          assignmentType: assignmentType,
          active: active,
          notes: notes,
        );
    _refresh();
  });

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  void _refresh() {
    _ref.invalidate(teachingAssignmentControllerProvider);
    _ref.invalidate(teachingAssignmentReferenceProvider);
  }
}
