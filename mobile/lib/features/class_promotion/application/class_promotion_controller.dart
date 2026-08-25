import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/class_promotion/data/class_promotion_repository.dart';
import 'package:nusa/features/class_promotion/domain/class_promotion.dart';

class ClassPromotionController extends AsyncNotifier<ClassPromotionPage> {
  int? _sourceYearId;
  int? _destinationYearId;
  int? _sourceClassId;
  int _requestVersion = 0;

  @override
  Future<ClassPromotionPage> build() => _fetch();

  Future<void> selectSourceYear(int value) async {
    _sourceYearId = value;
    if (_destinationYearId == value) _destinationYearId = null;
    _sourceClassId = null;
    await refresh();
  }

  Future<void> selectDestinationYear(int? value) async {
    _sourceYearId ??= state.value?.filter.sourceYearId;
    _destinationYearId = value;
    await refresh();
  }

  Future<void> selectSourceClass(int? value) async {
    _sourceYearId ??= state.value?.filter.sourceYearId;
    _destinationYearId ??= state.value?.filter.destinationYearId;
    _sourceClassId = value;
    await refresh();
  }

  Future<void> refresh() async {
    final current = state.value;
    _sourceYearId ??= current?.filter.sourceYearId;
    _destinationYearId ??= current?.filter.destinationYearId;
    _sourceClassId ??= current?.filter.sourceClassId;
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<PromotionResult> process(List<PromotionAssignment> assignments) async {
    final current = state.value;
    final sourceYearId = current?.filter.sourceYearId;
    final destinationYearId = current?.filter.destinationYearId;
    final sourceClassId = current?.filter.sourceClassId;
    if (sourceYearId == null ||
        destinationYearId == null ||
        sourceClassId == null) {
      throw const ValidationException(
        'Pilih tahun asal, tahun tujuan, dan kelas asal terlebih dahulu.',
      );
    }

    final result = await _guard(
      () => ref
          .read(classPromotionRepositoryProvider)
          .process(
            sourceYearId: sourceYearId,
            destinationYearId: destinationYearId,
            sourceClassId: sourceClassId,
            assignments: assignments,
          ),
    );
    final refreshed = await _fetch();
    state = AsyncData(refreshed);
    return result;
  }

  Future<ClassPromotionPage> _fetch() => _guard(
    () => ref
        .read(classPromotionRepositoryProvider)
        .fetch(
          sourceYearId: _sourceYearId,
          destinationYearId: _destinationYearId,
          sourceClassId: _sourceClassId,
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

final classPromotionControllerProvider =
    AsyncNotifierProvider.autoDispose<
      ClassPromotionController,
      ClassPromotionPage
    >(ClassPromotionController.new);
