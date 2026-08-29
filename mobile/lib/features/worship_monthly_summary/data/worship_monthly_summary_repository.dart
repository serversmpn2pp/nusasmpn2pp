import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/worship_monthly_summary/data/worship_monthly_summary_remote_data_source.dart';
import 'package:nusa/features/worship_monthly_summary/domain/worship_monthly_summary.dart';

final class WorshipMonthlySummaryRepository {
  WorshipMonthlySummaryRepository(this._remote);

  final WorshipMonthlySummaryRemoteDataSource _remote;

  Future<WorshipMonthlySummaryPage> fetch({
    required String? month,
    required int? activityId,
    required int? classId,
  }) => _remote.fetch(month: month, activityId: activityId, classId: classId);
}

final worshipMonthlySummaryRepositoryProvider =
    Provider<WorshipMonthlySummaryRepository>(
      (ref) => WorshipMonthlySummaryRepository(
        ref.watch(worshipMonthlySummaryRemoteDataSourceProvider),
      ),
    );
