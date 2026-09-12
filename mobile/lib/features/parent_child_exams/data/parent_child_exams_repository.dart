import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/parent_child_exams/data/parent_child_exams_remote_data_source.dart';
import 'package:nusa/features/parent_child_exams/domain/parent_child_exams.dart';

class ParentChildExamsRepository {
  const ParentChildExamsRepository(this._remote);

  final ParentChildExamsRemoteDataSource _remote;

  Future<ParentChildExamsPage> fetch({int? studentId}) =>
      _remote.fetch(studentId: studentId);
}

final parentChildExamsRepositoryProvider = Provider<ParentChildExamsRepository>(
  (ref) => ParentChildExamsRepository(
    ref.watch(parentChildExamsRemoteDataSourceProvider),
  ),
);
