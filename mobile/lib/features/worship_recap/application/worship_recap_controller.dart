import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/worship_recap/data/worship_recap_repository.dart';
import 'package:nusa/features/worship_recap/domain/worship_recap.dart';

class WorshipRecapController extends AsyncNotifier<WorshipRecapPage> {
  String? _date;
  int? _activityId;
  int? _classId;
  String _status = 'semua';
  String _query = '';
  int _requestVersion = 0;

  @override
  Future<WorshipRecapPage> build() => _fetch(page: 1);

  Future<void> selectDate(String value) async {
    _date = value;
    await refresh();
  }

  Future<void> selectActivity(int value) async {
    if (_activityId == value) return;
    _activityId = value;
    await refresh();
  }

  Future<void> selectClass(int? value) async {
    if (_classId == value) return;
    _classId = value;
    _status = 'semua';
    _query = '';
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(page: 1);
      _sync(result);
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

  Future<WorshipRecapPage> _fetch({required int page}) async {
    try {
      final result = await ref
          .read(worshipRecapRepositoryProvider)
          .fetch(
            date: _date,
            activityId: _activityId,
            classId: _classId,
            status: _status,
            query: _query,
            page: page,
          );
      if (page == 1) _sync(result);
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  void _sync(WorshipRecapPage page) {
    _date = page.date;
    _activityId = page.selectedActivity?.id;
    _classId = page.selectedClassId;
    _status = page.filter.status;
    _query = page.filter.query;
  }
}

final worshipRecapControllerProvider =
    AsyncNotifierProvider.autoDispose<WorshipRecapController, WorshipRecapPage>(
      WorshipRecapController.new,
    );

final worshipCorrectionDetailProvider = FutureProvider.autoDispose
    .family<WorshipCorrectionDetail, WorshipCorrectionQuery>((
      ref,
      query,
    ) async {
      try {
        return await ref
            .read(worshipRecapRepositoryProvider)
            .fetchCorrection(query);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final worshipCorrectionActionsProvider = Provider<WorshipCorrectionActions>(
  WorshipCorrectionActions.new,
);

class WorshipCorrectionActions {
  WorshipCorrectionActions(this._ref);

  final Ref _ref;

  Future<WorshipCorrectionResult> update({
    required WorshipCorrectionQuery query,
    required String status,
    required String? time,
    required String reason,
  }) async {
    try {
      final result = await _ref
          .read(worshipRecapRepositoryProvider)
          .updateCorrection(
            query: query,
            status: status,
            time: time,
            reason: reason,
          );
      _ref.invalidate(worshipRecapControllerProvider);
      _ref.invalidate(worshipCorrectionDetailProvider(query));
      return result;
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
