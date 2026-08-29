import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/worship_recap/data/worship_recap_remote_data_source.dart';
import 'package:nusa/features/worship_recap/domain/worship_recap.dart';

final class WorshipRecapRepository {
  WorshipRecapRepository(this._remote);

  final WorshipRecapRemoteDataSource _remote;

  Future<WorshipRecapPage> fetch({
    required String? date,
    required int? activityId,
    required int? classId,
    required String status,
    required String query,
    required int page,
  }) => _remote.fetch(
    date: date,
    activityId: activityId,
    classId: classId,
    status: status,
    query: query,
    page: page,
  );

  Future<WorshipCorrectionDetail> fetchCorrection(
    WorshipCorrectionQuery query,
  ) => _remote.fetchCorrection(query);

  Future<WorshipCorrectionResult> updateCorrection({
    required WorshipCorrectionQuery query,
    required String status,
    required String? time,
    required String reason,
  }) => _remote.updateCorrection(
    query: query,
    status: status,
    time: time,
    reason: reason,
  );
}

final worshipRecapRepositoryProvider = Provider<WorshipRecapRepository>(
  (ref) =>
      WorshipRecapRepository(ref.watch(worshipRecapRemoteDataSourceProvider)),
);
