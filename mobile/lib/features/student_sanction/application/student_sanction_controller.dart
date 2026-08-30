import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_sanction/data/student_sanction_repository.dart';
import 'package:nusa/features/student_sanction/domain/student_sanction.dart';

class StudentSanctionController extends AsyncNotifier<StudentSanctionPage> {
  String _query = '';
  String _status = 'aktif';
  int? _academicYearId;
  int? _classId;
  Timer? _debounce;
  int _version = 0;

  @override
  Future<StudentSanctionPage> build() {
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

  Future<StudentSanctionPage> _fetch(int page) => _guard(
    () => ref
        .read(studentSanctionRepositoryProvider)
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

final studentSanctionControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentSanctionController,
      StudentSanctionPage
    >(StudentSanctionController.new);

final studentSanctionDetailProvider = FutureProvider.autoDispose
    .family<StudentSanctionDetail, int>((ref, id) async {
      try {
        return await ref
            .read(studentSanctionRepositoryProvider)
            .fetchDetail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final studentSanctionActionsProvider = Provider(StudentSanctionActions.new);

class StudentSanctionActions {
  StudentSanctionActions(this._ref);
  final Ref _ref;

  Future<StudentSanctionDetail> update(
    int id,
    StudentSanctionPayload payload,
  ) => _guard(
    () => _ref.read(studentSanctionRepositoryProvider).update(id, payload),
  );

  Future<StudentSanctionDetail> uploadEvidence({
    required int id,
    required List<SanctionPickedFile> files,
    required String? description,
  }) => _guard(
    () => _ref
        .read(studentSanctionRepositoryProvider)
        .uploadEvidence(id: id, files: files, description: description),
  );

  Future<StudentSanctionDetail> deleteEvidence(int evidenceId) => _guard(
    () =>
        _ref.read(studentSanctionRepositoryProvider).deleteEvidence(evidenceId),
  );

  Future<SanctionEvidenceDownload> downloadEvidence(
    SanctionEvidence evidence,
  ) => _guard(
    () =>
        _ref.read(studentSanctionRepositoryProvider).downloadEvidence(evidence),
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
