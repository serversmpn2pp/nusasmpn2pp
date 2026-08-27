import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/teaching_document/data/teaching_document_repository.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';

class TeachingDocumentController extends AsyncNotifier<TeachingDocumentPage> {
  int? _academicYearId;
  int _semester = 1;

  @override
  Future<TeachingDocumentPage> build() => _fetch();

  Future<void> filterAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    await refresh();
  }

  Future<void> filterSemester(int value) async {
    if (_semester == value) return;
    _semester = value;
    await refresh();
  }

  Future<void> refresh() async {
    final current = state.value;
    _academicYearId ??= current?.filter.academicYearId;
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<TeachingDocumentPage> _fetch() => _guard(
    () => ref
        .read(teachingDocumentRepositoryProvider)
        .fetch(academicYearId: _academicYearId, semester: _semester),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final teachingDocumentControllerProvider =
    AsyncNotifierProvider.autoDispose<
      TeachingDocumentController,
      TeachingDocumentPage
    >(TeachingDocumentController.new);

final teachingDocumentDetailProvider = FutureProvider.autoDispose
    .family<TeachingDocumentDetail, int>((ref, id) async {
      try {
        return await ref
            .read(teachingDocumentRepositoryProvider)
            .fetchDetail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final teachingDocumentActionsProvider = Provider<TeachingDocumentActions>(
  TeachingDocumentActions.new,
);

class TeachingDocumentActions {
  TeachingDocumentActions(this._ref);

  final Ref _ref;

  Future<void> create(TeachingDocumentFormValue value) =>
      _guard(() => _ref.read(teachingDocumentRepositoryProvider).create(value));

  Future<void> update({
    required int id,
    required TeachingDocumentFormValue value,
  }) => _guard(
    () => _ref
        .read(teachingDocumentRepositoryProvider)
        .update(id: id, value: value),
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
