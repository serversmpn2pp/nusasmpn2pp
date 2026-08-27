import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/teaching_document_type/data/teaching_document_type_repository.dart';
import 'package:nusa/features/teaching_document_type/domain/teaching_document_type.dart';

class TeachingDocumentTypeController
    extends AsyncNotifier<TeachingDocumentTypePage> {
  String _query = '';
  String _status = 'semua';
  String _requirement = 'semua';
  int _requestVersion = 0;

  @override
  Future<TeachingDocumentTypePage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> filterRequirement(String value) async {
    if (_requirement == value) return;
    _requirement = value;
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

  Future<TeachingDocumentTypePage> _fetch({required int page}) async {
    try {
      return await ref
          .read(teachingDocumentTypeRepositoryProvider)
          .fetch(
            query: _query,
            status: _status,
            requirement: _requirement,
            page: page,
          );
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final teachingDocumentTypeControllerProvider =
    AsyncNotifierProvider.autoDispose<
      TeachingDocumentTypeController,
      TeachingDocumentTypePage
    >(TeachingDocumentTypeController.new);

final teachingDocumentTypeActionsProvider =
    Provider<TeachingDocumentTypeActions>(TeachingDocumentTypeActions.new);

class TeachingDocumentTypeActions {
  TeachingDocumentTypeActions(this._ref);

  final Ref _ref;

  Future<void> create(TeachingDocumentTypeFormValue value) => _guard(
    () => _ref.read(teachingDocumentTypeRepositoryProvider).create(value),
  );

  Future<void> update({
    required int id,
    required TeachingDocumentTypeFormValue value,
  }) => _guard(
    () => _ref
        .read(teachingDocumentTypeRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> deactivate(int id) => _guard(
    () => _ref.read(teachingDocumentTypeRepositoryProvider).deactivate(id),
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
