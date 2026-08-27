import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/teaching_document_review/data/teaching_document_review_repository.dart';
import 'package:nusa/features/teaching_document_review/domain/teaching_document_review.dart';

class TeachingDocumentReviewController
    extends AsyncNotifier<TeachingDocumentReviewPage> {
  String _query = '';
  int? _academicYearId;
  int _semester = 1;
  String _completeness = 'semua';
  String _documentStatus = 'semua';
  Timer? _debounce;
  int _requestVersion = 0;

  @override
  Future<TeachingDocumentReviewPage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(page: 1);
  }

  void search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (_query == value.trim()) return;
      _query = value.trim();
      unawaited(refresh());
    });
  }

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

  Future<void> filterCompleteness(String value) async {
    if (_completeness == value) return;
    _completeness = value;
    await refresh();
  }

  Future<void> filterDocumentStatus(String value) async {
    if (_documentStatus == value) return;
    _documentStatus = value;
    await refresh();
  }

  Future<void> refresh() async {
    final current = state.value;
    _academicYearId ??= current?.filter.academicYearId;
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

  Future<TeachingDocumentReviewPage> _fetch({required int page}) => _guard(
    () => ref
        .read(teachingDocumentReviewRepositoryProvider)
        .fetch(
          query: _query,
          academicYearId: _academicYearId,
          semester: _semester,
          completeness: _completeness,
          documentStatus: _documentStatus,
          page: page,
        ),
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

final teachingDocumentReviewControllerProvider =
    AsyncNotifierProvider.autoDispose<
      TeachingDocumentReviewController,
      TeachingDocumentReviewPage
    >(TeachingDocumentReviewController.new);

final teachingDocumentTeacherDetailProvider = FutureProvider.autoDispose
    .family<TeachingDocumentTeacherDetail, TeachingDocumentTeacherQuery>((
      ref,
      query,
    ) async {
      try {
        return await ref
            .read(teachingDocumentReviewRepositoryProvider)
            .fetchTeacher(query);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final teachingDocumentReviewDetailProvider = FutureProvider.autoDispose
    .family<TeachingDocumentReviewDetail, int>((ref, id) async {
      try {
        return await ref
            .read(teachingDocumentReviewRepositoryProvider)
            .fetchDocument(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final teachingDocumentReviewActionsProvider =
    Provider<TeachingDocumentReviewActions>(TeachingDocumentReviewActions.new);

class TeachingDocumentReviewActions {
  TeachingDocumentReviewActions(this._ref);

  final Ref _ref;

  Future<TeachingDocumentDownload> download({
    required int id,
    required String fileName,
  }) => _guard(
    () => _ref
        .read(teachingDocumentReviewRepositoryProvider)
        .download(id: id, fileName: fileName),
  );

  Future<void> review({
    required int id,
    required TeachingDocumentReviewValue value,
  }) => _guard(
    () => _ref
        .read(teachingDocumentReviewRepositoryProvider)
        .review(id: id, value: value),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
