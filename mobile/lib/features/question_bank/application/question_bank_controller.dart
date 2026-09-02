import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/question_bank/data/question_bank_repository.dart';
import 'package:nusa/features/question_bank/domain/question_bank.dart';

class QuestionBankController extends AsyncNotifier<QuestionBankPage> {
  String _query = '';
  int? _subjectId;
  String _grade = 'semua';
  String _type = 'semua';
  String _status = 'semua';
  int _version = 0;
  Timer? _debounce;

  @override
  Future<QuestionBankPage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(1);
  }

  void search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      final next = value.trim();
      if (_query == next) return;
      _query = next;
      unawaited(refresh());
    });
  }

  Future<void> filterContext(String? value) async {
    if (value == null || value.isEmpty) {
      _subjectId = null;
      _grade = 'semua';
    } else {
      final parts = value.split('-');
      _subjectId = int.tryParse(parts.first);
      _grade = parts.length > 1 ? parts.last : 'semua';
    }
    await refresh();
  }

  Future<void> filterType(String value) async {
    _type = value;
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

  Future<void> archive(int id) async {
    await _guard(() => ref.read(questionBankRepositoryProvider).archive(id));
    ref.invalidate(questionBankDetailProvider(id));
    await refresh();
  }

  Future<BankQuestionDetail> save({
    int? id,
    required QuestionFormValue value,
  }) async {
    final repository = ref.read(questionBankRepositoryProvider);
    final saved = await _guard(
      () =>
          id == null ? repository.create(value) : repository.update(id, value),
    );
    ref.invalidate(questionBankDetailProvider(saved.id));
    await refresh();
    return saved;
  }

  Future<QuestionBankPage> _fetch(int page) => _guard(
    () => ref
        .read(questionBankRepositoryProvider)
        .fetch(
          query: _query,
          subjectId: _subjectId,
          grade: _grade,
          type: _type,
          status: _status,
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

final questionBankControllerProvider =
    AsyncNotifierProvider.autoDispose<QuestionBankController, QuestionBankPage>(
      QuestionBankController.new,
    );

final questionBankDetailProvider = FutureProvider.autoDispose
    .family<BankQuestionDetail, int>((ref, id) async {
      try {
        return await ref.read(questionBankRepositoryProvider).detail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
