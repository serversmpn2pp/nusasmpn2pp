import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_guidance_category/data/student_guidance_category_repository.dart';
import 'package:nusa/features/student_guidance_category/domain/student_guidance_category.dart';

class StudentGuidanceCategoryController
    extends AsyncNotifier<StudentGuidanceCategoryPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<StudentGuidanceCategoryPage> build() => _fetch(page: 1);

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

  Future<StudentGuidanceCategoryPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(studentGuidanceCategoryRepositoryProvider)
          .fetch(query: _query, status: _status, page: page);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final studentGuidanceCategoryControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentGuidanceCategoryController,
      StudentGuidanceCategoryPage
    >(StudentGuidanceCategoryController.new);

final studentGuidanceCategoryActionsProvider =
    Provider<StudentGuidanceCategoryActions>(
      StudentGuidanceCategoryActions.new,
    );

class StudentGuidanceCategoryActions {
  StudentGuidanceCategoryActions(this._ref);

  final Ref _ref;

  Future<void> create(StudentGuidanceCategoryFormValue value) => _guard(
    () => _ref.read(studentGuidanceCategoryRepositoryProvider).create(value),
  );

  Future<void> update({
    required int id,
    required StudentGuidanceCategoryFormValue value,
  }) => _guard(
    () => _ref
        .read(studentGuidanceCategoryRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> deactivate(int id) => _guard(
    () => _ref.read(studentGuidanceCategoryRepositoryProvider).deactivate(id),
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
