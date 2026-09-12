import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/worship_absence_recap/data/worship_absence_recap_remote_data_source.dart';
import 'package:nusa/features/worship_absence_recap/domain/worship_absence_recap.dart';

final class WorshipAbsenceRecapRepository {
  WorshipAbsenceRecapRepository(this._remote);
  final WorshipAbsenceRecapRemoteDataSource _remote;

  Future<WorshipAbsenceRecapPage> fetch({
    required String? month,
    required int? classId,
    required String status,
    required String query,
    required int page,
    bool exportAll = false,
  }) => _remote.fetch(
    month: month,
    classId: classId,
    status: status,
    query: query,
    page: page,
    exportAll: exportAll,
  );
}

final worshipAbsenceRecapRepositoryProvider =
    Provider<WorshipAbsenceRecapRepository>(
      (ref) => WorshipAbsenceRecapRepository(
        ref.watch(worshipAbsenceRecapRemoteDataSourceProvider),
      ),
    );
