import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_early_warning/data/student_early_warning_repository.dart';
import 'package:nusa/features/student_early_warning/domain/student_early_warning.dart';

class StudentEarlyWarningController
    extends AsyncNotifier<StudentEarlyWarningPage> {
  String _query = '';
  String _type = 'semua';
  String _level = 'semua';
  String _status = 'aktif';
  int? _academicYearId;
  int? _classId;
  Timer? _debounce;
  int _version = 0;

  @override
  Future<StudentEarlyWarningPage> build() {
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

  Future<void> filterType(String value) => _change(() => _type = value);
  Future<void> applyFilters({
    required String level,
    required String status,
    required int? academicYearId,
    required int? classId,
  }) => _change(() {
    _level = level;
    _status = status;
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

  Future<StudentWarningProcessResult> process() async {
    final result = await _guard(
      () => ref
          .read(studentEarlyWarningRepositoryProvider)
          .process(_academicYearId),
    );
    await refresh();
    return result;
  }

  Future<void> _change(void Function() operation) async {
    operation();
    await refresh();
  }

  Future<StudentEarlyWarningPage> _fetch(int page) => _guard(
    () => ref
        .read(studentEarlyWarningRepositoryProvider)
        .fetch(
          query: _query,
          type: _type,
          level: _level,
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

final studentEarlyWarningControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentEarlyWarningController,
      StudentEarlyWarningPage
    >(StudentEarlyWarningController.new);

final studentEarlyWarningDetailProvider = FutureProvider.autoDispose
    .family<StudentEarlyWarningDetail, int>((ref, id) async {
      try {
        return await ref
            .read(studentEarlyWarningRepositoryProvider)
            .fetchDetail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
