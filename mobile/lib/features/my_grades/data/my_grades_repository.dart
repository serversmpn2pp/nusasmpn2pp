import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/my_grades/data/my_grades_remote_data_source.dart';
import 'package:nusa/features/my_grades/domain/my_grades.dart';

class MyGradesRepository {
  MyGradesRepository(this._remote);

  final MyGradesRemoteDataSource _remote;

  Future<MyGradesPage> fetch({
    required int? academicYearId,
    required String semester,
  }) => _remote.fetch(academicYearId: academicYearId, semester: semester);
}

final myGradesRepositoryProvider = Provider<MyGradesRepository>(
  (ref) => MyGradesRepository(ref.watch(myGradesRemoteDataSourceProvider)),
);
