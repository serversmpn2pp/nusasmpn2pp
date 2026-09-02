import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_repository.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment.dart';

class ClassAssessmentController extends AsyncNotifier<ClassAssessmentPage> {
  String _query = '';
  String _status = 'semua';
  int _version = 0;
  Timer? _debounce;

  @override
  Future<ClassAssessmentPage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(1);
  }

  void search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (_query == value.trim()) return;
      _query = value.trim();
      unawaited(refresh());
    });
  }

  Future<void> filterStatus(String status) async {
    _status = status;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final page = await _fetch(1);
      if (version == _version) state = AsyncData(page);
    } catch (error, stackTrace) {
      if (version == _version) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    state = AsyncData(
      current.append(await _fetch(current.pagination.page + 1)),
    );
  }

  Future<ClassAssessmentDetail> create(ClassAssessmentPayload payload) async {
    final result = await _guard(
      () => ref.read(classAssessmentRepositoryProvider).create(payload),
    );
    await refresh();
    return result;
  }

  Future<ClassAssessmentDetail> updateAssessment(
    int id,
    ClassAssessmentPayload payload,
  ) async {
    final result = await _guard(
      () => ref.read(classAssessmentRepositoryProvider).update(id, payload),
    );
    ref.invalidate(classAssessmentDetailProvider(id));
    await refresh();
    return result;
  }

  Future<void> deactivate(int id) async {
    await _guard(
      () => ref.read(classAssessmentRepositoryProvider).deactivate(id),
    );
    ref.invalidate(classAssessmentDetailProvider(id));
    await refresh();
  }

  Future<AssessmentQuestions> saveQuestions(
    int id,
    List<AssessmentQuestionPayload> questions,
  ) async {
    final result = await _guard(
      () => ref
          .read(classAssessmentRepositoryProvider)
          .saveQuestions(id, questions),
    );
    ref.invalidate(classAssessmentQuestionsProvider(id));
    ref.invalidate(classAssessmentDetailProvider(id));
    await refresh();
    return result;
  }

  Future<ClassAssessmentPage> _fetch(int page) => _guard(
    () => ref
        .read(classAssessmentRepositoryProvider)
        .fetch(query: _query, status: _status, page: page),
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

final classAssessmentControllerProvider =
    AsyncNotifierProvider.autoDispose<
      ClassAssessmentController,
      ClassAssessmentPage
    >(ClassAssessmentController.new);

final classAssessmentDetailProvider = FutureProvider.autoDispose
    .family<ClassAssessmentDetail, int>((ref, id) async {
      try {
        return await ref.read(classAssessmentRepositoryProvider).detail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final classAssessmentQuestionsProvider = FutureProvider.autoDispose
    .family<AssessmentQuestions, int>((ref, id) async {
      try {
        return await ref.read(classAssessmentRepositoryProvider).questions(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
