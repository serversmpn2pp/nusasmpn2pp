import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/question_package/data/question_package_repository.dart';
import 'package:nusa/features/question_package/domain/question_package.dart';

class QuestionPackageController extends AsyncNotifier<QuestionPackagePage> {
  String _query = '';
  int? _eventId;
  String _status = 'semua';
  int _version = 0;
  Timer? _debounce;

  @override
  Future<QuestionPackagePage> build() {
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

  Future<void> filterEvent(int? value) async {
    _eventId = value;
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    _status = value;
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

  Future<QuestionPackageDetail> save(
    int scheduleId,
    QuestionPackagePayload payload,
  ) async {
    final detail = await _guard(
      () =>
          ref.read(questionPackageRepositoryProvider).save(scheduleId, payload),
    );
    ref.invalidate(questionPackageDetailProvider(scheduleId));
    await refresh();
    return detail;
  }

  Future<QuestionPackagePage> _fetch(int page) => _guard(
    () => ref
        .read(questionPackageRepositoryProvider)
        .fetch(query: _query, eventId: _eventId, status: _status, page: page),
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

final questionPackageControllerProvider =
    AsyncNotifierProvider.autoDispose<
      QuestionPackageController,
      QuestionPackagePage
    >(QuestionPackageController.new);

final questionPackageDetailProvider = FutureProvider.autoDispose
    .family<QuestionPackageDetail, int>((ref, id) async {
      try {
        return await ref.read(questionPackageRepositoryProvider).detail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
