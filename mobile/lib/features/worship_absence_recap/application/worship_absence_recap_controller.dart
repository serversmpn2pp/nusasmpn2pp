import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/worship_absence_recap/data/worship_absence_recap_repository.dart';
import 'package:nusa/features/worship_absence_recap/domain/worship_absence_recap.dart';

class WorshipAbsenceRecapController
    extends AsyncNotifier<WorshipAbsenceRecapPage> {
  String? _month;
  int? _classId;
  String _status = 'semua';
  String _query = '';
  int _requestVersion = 0;

  @override
  Future<WorshipAbsenceRecapPage> build() => _fetch(1);

  Future<void> applyFilters({
    required String month,
    required int? classId,
    required String status,
  }) async {
    if (_month == month && _classId == classId && _status == status) return;
    _month = month;
    _classId = classId;
    _status = status;
    await refresh();
  }

  Future<void> search(String value) async {
    final normalized = value.trim();
    if (_query == normalized) return;
    _query = normalized;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      _sync(result);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<WorshipAbsenceRecapPage> fetchForDocument() =>
      _fetch(1, exportAll: true);

  Future<WorshipAbsenceRecapPage> _fetch(
    int page, {
    bool exportAll = false,
  }) async {
    try {
      return await ref
          .read(worshipAbsenceRecapRepositoryProvider)
          .fetch(
            month: _month,
            classId: _classId,
            status: _status,
            query: _query,
            page: page,
            exportAll: exportAll,
          );
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  void _sync(WorshipAbsenceRecapPage page) {
    _month = page.month;
    _classId = page.filter.classId;
    _status = page.filter.status;
    _query = page.filter.query;
  }
}

final worshipAbsenceRecapControllerProvider =
    AsyncNotifierProvider.autoDispose<
      WorshipAbsenceRecapController,
      WorshipAbsenceRecapPage
    >(WorshipAbsenceRecapController.new);
