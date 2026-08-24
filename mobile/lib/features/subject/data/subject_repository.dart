import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/subject/data/subject_remote_data_source.dart';
import 'package:nusa/features/subject/domain/subject.dart';

final class SubjectRepository {
  SubjectRepository(this._remote);

  final SubjectRemoteDataSource _remote;

  Future<SubjectPage> fetch({
    required String query,
    required String status,
    required String level,
    required int page,
    int? academicYearId,
  }) => _remote.fetch(
    query: query,
    status: status,
    level: level,
    page: page,
    academicYearId: academicYearId,
  );

  Future<SubjectReference> fetchReference() => _remote.fetchReference();

  Future<void> create(SubjectFormValue value) => _remote.create(value);

  Future<void> update({required int id, required SubjectFormValue value}) =>
      _remote.update(id: id, value: value);
}

final subjectRepositoryProvider = Provider<SubjectRepository>(
  (ref) => SubjectRepository(ref.watch(subjectRemoteDataSourceProvider)),
);
