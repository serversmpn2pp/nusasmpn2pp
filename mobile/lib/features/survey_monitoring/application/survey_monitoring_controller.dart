import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/survey_monitoring/data/survey_monitoring_repository.dart';
import 'package:nusa/features/survey_monitoring/domain/survey_monitoring.dart';

class SurveyMonitoringController extends AsyncNotifier<SurveyMonitoringPage> {
  int? _academicYearId;
  String _semester = DateTime.now().month >= 7 ? 'ganjil' : 'genap';
  String _status = 'semua';
  String _query = '';
  int _requestVersion = 0;

  @override
  Future<SurveyMonitoringPage> build() => _fetch(page: 1);

  Future<void> filterAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    await refresh();
  }

  Future<void> filterSemester(String value) async {
    if (_semester == value) return;
    _semester = value;
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
      _academicYearId = result.filter.academicYearId;
      _semester = result.filter.semester;
      _status = result.filter.status;
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

  Future<SurveyMonitoringPage> _fetch({required int page}) async {
    try {
      final result = await ref
          .read(surveyMonitoringRepositoryProvider)
          .fetch(
            academicYearId: _academicYearId,
            semester: _semester,
            status: _status,
            query: _query,
            page: page,
          );
      _academicYearId ??= result.filter.academicYearId;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final surveyMonitoringControllerProvider =
    AsyncNotifierProvider.autoDispose<
      SurveyMonitoringController,
      SurveyMonitoringPage
    >(SurveyMonitoringController.new);

typedef SurveyMonitoringDetailQuery = ({int assignmentId, String semester});

final surveyMonitoringDetailProvider = FutureProvider.autoDispose
    .family<SurveyMonitoringDetail, SurveyMonitoringDetailQuery>((
      ref,
      query,
    ) async {
      try {
        return await ref
            .read(surveyMonitoringRepositoryProvider)
            .fetchDetail(
              assignmentId: query.assignmentId,
              semester: query.semester,
            );
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
