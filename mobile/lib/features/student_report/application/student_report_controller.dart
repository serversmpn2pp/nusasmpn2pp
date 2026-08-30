import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_report/data/student_report_repository.dart';
import 'package:nusa/features/student_report/domain/student_report.dart';

class StudentReportController extends AsyncNotifier<StudentReportPage> {
  String _query = '';
  String _status = 'semua';
  String _level = 'semua';
  String _type = 'semua';
  String _verificationStatus = 'semua';
  int? _academicYearId;
  int? _classId;
  Timer? _debounce;
  int _requestVersion = 0;

  @override
  Future<StudentReportPage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(page: 1);
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

  Future<void> filterVerification(String value) => _update(() {
    _verificationStatus = value;
  });

  Future<void> applyFilters({
    required String status,
    required String level,
    required String type,
    required int? academicYearId,
    required int? classId,
  }) => _update(() {
    _status = status;
    _level = level;
    _type = type;
    _academicYearId = academicYearId;
    _classId = classId;
  });

  Future<void> resetFilters() => _update(() {
    _status = 'semua';
    _level = 'semua';
    _type = 'semua';
    _verificationStatus = 'semua';
    _academicYearId = null;
    _classId = null;
  });

  Future<void> _update(void Function() operation) async {
    operation();
    await refresh();
  }

  Future<void> refresh() async {
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

  Future<StudentReportPage> _fetch({required int page}) => _guard(
    () => ref
        .read(studentReportRepositoryProvider)
        .fetch(
          query: _query,
          status: _status,
          level: _level,
          type: _type,
          verificationStatus: _verificationStatus,
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

final studentReportControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentReportController,
      StudentReportPage
    >(StudentReportController.new);

final studentReportDetailProvider = FutureProvider.autoDispose
    .family<StudentReportDetail, int>((ref, id) async {
      try {
        return await ref.read(studentReportRepositoryProvider).fetchDetail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final studentReportActionsProvider = Provider<StudentReportActions>(
  StudentReportActions.new,
);

class StudentReportActions {
  StudentReportActions(this._ref);

  final Ref _ref;

  Future<StudentReportEvidenceDownload> downloadEvidence({
    required StudentReportEvidence evidence,
  }) async {
    try {
      return await _ref
          .read(studentReportRepositoryProvider)
          .downloadEvidence(
            id: evidence.id,
            fileName: evidence.fileName,
            mimeType: evidence.mimeType,
          );
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
