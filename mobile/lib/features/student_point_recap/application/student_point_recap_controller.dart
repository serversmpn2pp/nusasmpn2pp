import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_point_recap/data/student_point_recap_repository.dart';
import 'package:nusa/features/student_point_recap/domain/student_point_recap.dart';

class StudentPointRecapController extends AsyncNotifier<StudentPointRecapPage> {
  String _query = '';
  String _attentionStatus = 'semua';
  int? _academicYearId;
  int? _classId;
  Timer? _debounce;
  int _version = 0;

  @override
  Future<StudentPointRecapPage> build() {
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

  Future<void> filterAttention(String value) =>
      _change(() => _attentionStatus = value);

  Future<void> applyFilters({
    required int? academicYearId,
    required int? classId,
  }) => _change(() {
    _academicYearId = academicYearId;
    _classId = classId;
  });

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

  Future<void> _change(void Function() operation) async {
    operation();
    await refresh();
  }

  Future<StudentPointRecapPage> _fetch(int page) => _guard(
    () => ref
        .read(studentPointRecapRepositoryProvider)
        .fetch(
          query: _query,
          attentionStatus: _attentionStatus,
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

final studentPointRecapControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentPointRecapController,
      StudentPointRecapPage
    >(StudentPointRecapController.new);

typedef StudentPointDetailQuery = ({int studentId, int? academicYearId});

final studentPointRecapDetailProvider = FutureProvider.autoDispose
    .family<StudentPointRecapDetail, StudentPointDetailQuery>((
      ref,
      query,
    ) async {
      try {
        return await ref
            .read(studentPointRecapRepositoryProvider)
            .fetchDetail(query.studentId, query.academicYearId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
