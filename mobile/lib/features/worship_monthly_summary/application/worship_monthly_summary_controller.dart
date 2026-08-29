import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/worship_monthly_summary/data/worship_monthly_summary_repository.dart';
import 'package:nusa/features/worship_monthly_summary/domain/worship_monthly_summary.dart';

class WorshipMonthlySummaryController
    extends AsyncNotifier<WorshipMonthlySummaryPage> {
  String? _month;
  int? _activityId;
  int? _classId;
  int _requestVersion = 0;

  @override
  Future<WorshipMonthlySummaryPage> build() => _fetch();

  Future<void> selectMonth(String value) async {
    if (_month == value) return;
    _month = value;
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
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      _sync(result);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<WorshipMonthlySummaryPage> _fetch() async {
    try {
      final result = await ref
          .read(worshipMonthlySummaryRepositoryProvider)
          .fetch(month: _month, activityId: _activityId, classId: _classId);
      _sync(result);
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  void _sync(WorshipMonthlySummaryPage page) {
    _month = page.month;
    _activityId = page.selectedActivity?.id;
    _classId = page.selectedClass?.id;
  }
}

final worshipMonthlySummaryControllerProvider =
    AsyncNotifierProvider.autoDispose<
      WorshipMonthlySummaryController,
      WorshipMonthlySummaryPage
    >(WorshipMonthlySummaryController.new);
