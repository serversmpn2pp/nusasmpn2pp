import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/point_reduction/data/point_reduction_repository.dart';
import 'package:nusa/features/point_reduction/domain/point_reduction.dart';

class PointReductionController extends AsyncNotifier<PointReductionPage> {
  String _query = '';
  String _status = 'semua';
  int? _academicYearId;
  int? _classId;
  Timer? _debounce;
  int _version = 0;

  @override
  Future<PointReductionPage> build() {
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

  Future<void> filterStatus(String value) => _change(() => _status = value);

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

  Future<PointReductionMutation> create(
    PointReductionCreatePayload payload,
  ) async {
    final result = await _guard(
      () => ref.read(pointReductionRepositoryProvider).create(payload),
    );
    await refresh();
    return result;
  }

  Future<PointReductionMutation> decide({
    required int id,
    required String decision,
    required String? note,
  }) async {
    final result = await _guard(
      () => ref
          .read(pointReductionRepositoryProvider)
          .decide(id: id, decision: decision, note: note),
    );
    await refresh();
    return result;
  }

  Future<ReductionEvidenceDownload> download(PointReductionItem item) =>
      _guard(() => ref.read(pointReductionRepositoryProvider).download(item));

  Future<void> _change(void Function() operation) async {
    operation();
    await refresh();
  }

  Future<PointReductionPage> _fetch(int page) => _guard(
    () => ref
        .read(pointReductionRepositoryProvider)
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

final pointReductionControllerProvider =
    AsyncNotifierProvider.autoDispose<
      PointReductionController,
      PointReductionPage
    >(PointReductionController.new);
