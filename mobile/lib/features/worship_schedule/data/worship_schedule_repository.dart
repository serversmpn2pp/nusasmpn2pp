import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/worship_schedule/data/worship_schedule_remote_data_source.dart';
import 'package:nusa/features/worship_schedule/domain/worship_schedule.dart';

final class WorshipScheduleRepository {
  WorshipScheduleRepository(this._remote);

  final WorshipScheduleRemoteDataSource _remote;

  Future<WorshipSchedulePage> fetch({int? academicYearId, int? activityId}) =>
      _remote.fetch(academicYearId: academicYearId, activityId: activityId);

  Future<void> create(WorshipScheduleFormValue value) => _remote.create(value);

  Future<void> update({
    required int id,
    required WorshipScheduleFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final worshipScheduleRepositoryProvider = Provider<WorshipScheduleRepository>(
  (ref) => WorshipScheduleRepository(
    ref.watch(worshipScheduleRemoteDataSourceProvider),
  ),
);
