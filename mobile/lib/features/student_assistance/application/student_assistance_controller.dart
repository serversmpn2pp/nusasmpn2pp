import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_assistance/data/student_assistance_repository.dart';
import 'package:nusa/features/student_assistance/domain/student_assistance.dart';

class StudentAssistanceController extends AsyncNotifier<StudentAssistancePage> {
  String _query = '';
  String _status = 'dalam_proses';
  int? _academicYearId;
  int? _classId;
  Timer? _debounce;
  int _version = 0;

  @override
  Future<StudentAssistancePage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(1);
  }

  void search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      final next = value.trim();
      if (next == _query) return;
      _query = next;
      unawaited(refresh());
    });
  }

  Future<void> filterStatus(String status) => _change(() => _status = status);

  Future<void> applyFilters({
    required int? academicYearId,
    required int? classId,
  }) => _change(() {
    _academicYearId = academicYearId;
    _classId = classId;
  });

  Future<void> resetFilters() => _change(() {
    _status = 'dalam_proses';
    _academicYearId = null;
    _classId = null;
  });

  Future<void> _change(void Function() operation) async {
    operation();
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      if (version == _version) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _version) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<StudentAssistancePage> _fetch(int page) => _guard(
    () => ref
        .read(studentAssistanceRepositoryProvider)
        .fetch(
          query: _query,
          status: _status,
          academicYearId: _academicYearId,
          classId: _classId,
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

final studentAssistanceControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentAssistanceController,
      StudentAssistancePage
    >(StudentAssistanceController.new);

final studentAssistanceDetailProvider = FutureProvider.autoDispose
    .family<StudentAssistanceDetail, int>((ref, id) async {
      try {
        return await ref
            .read(studentAssistanceRepositoryProvider)
            .fetchDetail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

typedef AssistanceReferenceQuery = ({
  String query,
  int? academicYearId,
  int? classId,
});

final studentAssistanceReferenceProvider = FutureProvider.autoDispose
    .family<StudentAssistanceReference, AssistanceReferenceQuery>((
      ref,
      query,
    ) async {
      try {
        return await ref
            .read(studentAssistanceRepositoryProvider)
            .fetchReference(
              query: query.query,
              academicYearId: query.academicYearId,
              classId: query.classId,
            );
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final studentAssistanceActionsProvider = Provider(StudentAssistanceActions.new);

class StudentAssistanceActions {
  StudentAssistanceActions(this._ref);
  final Ref _ref;

  Future<StudentAssistanceDetail> create(StudentAssistancePayload payload) =>
      _guard(
        () => _ref.read(studentAssistanceRepositoryProvider).create(payload),
      );

  Future<StudentAssistanceDetail> update(
    int id,
    StudentAssistancePayload payload,
  ) => _guard(
    () => _ref.read(studentAssistanceRepositoryProvider).update(id, payload),
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
