import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/grade_entry/data/grade_entry_remote_data_source.dart';
import 'package:nusa/features/grade_entry/domain/grade_entry.dart';

class GradeEntryRepository {
  GradeEntryRepository(this._remote);

  final GradeEntryRemoteDataSource _remote;

  Future<GradeEntryPage> fetch({
    required int? assignmentId,
    required String semester,
    required int? componentId,
  }) => _remote.fetch(
    assignmentId: assignmentId,
    semester: semester,
    componentId: componentId,
  );

  Future<String> save(GradeEntryFormValue value) => _remote.save(value);

  Future<String> publish({
    required int assignmentId,
    required String semester,
  }) => _remote.publish(assignmentId: assignmentId, semester: semester);

  Future<String> unpublish({
    required int assignmentId,
    required String semester,
  }) => _remote.unpublish(assignmentId: assignmentId, semester: semester);
}

final gradeEntryRepositoryProvider = Provider<GradeEntryRepository>(
  (ref) => GradeEntryRepository(ref.watch(gradeEntryRemoteDataSourceProvider)),
);
