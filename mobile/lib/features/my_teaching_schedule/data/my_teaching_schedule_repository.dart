import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/my_teaching_schedule/data/my_teaching_schedule_remote_data_source.dart';
import 'package:nusa/features/my_teaching_schedule/domain/my_teaching_schedule.dart';

class MyTeachingScheduleRepository {
  MyTeachingScheduleRepository(this._remote);

  final MyTeachingScheduleRemoteDataSource _remote;

  Future<MyTeachingSchedulePage> fetch({required int? academicYearId}) =>
      _remote.fetch(academicYearId: academicYearId);
}

final myTeachingScheduleRepositoryProvider =
    Provider<MyTeachingScheduleRepository>(
      (ref) => MyTeachingScheduleRepository(
        ref.watch(myTeachingScheduleRemoteDataSourceProvider),
      ),
    );
