import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/grade_recap/data/grade_recap_remote_data_source.dart';
import 'package:nusa/features/grade_recap/domain/grade_recap.dart';

class GradeRecapRepository {
  GradeRecapRepository(this._remote);

  final GradeRecapRemoteDataSource _remote;

  Future<GradeRecapPage> fetch({
    required int? assignmentId,
    required String semester,
  }) => _remote.fetch(assignmentId: assignmentId, semester: semester);
}

final gradeRecapRepositoryProvider = Provider<GradeRecapRepository>(
  (ref) => GradeRecapRepository(ref.watch(gradeRecapRemoteDataSourceProvider)),
);
