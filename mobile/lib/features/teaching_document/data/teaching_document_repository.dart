import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/teaching_document/data/teaching_document_remote_data_source.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';

class TeachingDocumentRepository {
  TeachingDocumentRepository(this._remote);

  final TeachingDocumentRemoteDataSource _remote;

  Future<TeachingDocumentPage> fetch({
    int? academicYearId,
    required int semester,
  }) => _remote.fetch(academicYearId: academicYearId, semester: semester);

  Future<TeachingDocumentDetail> fetchDetail(int id) => _remote.fetchDetail(id);

  Future<void> create(TeachingDocumentFormValue value) => _remote.create(value);

  Future<void> update({
    required int id,
    required TeachingDocumentFormValue value,
  }) => _remote.update(id: id, value: value);
}

final teachingDocumentRepositoryProvider = Provider<TeachingDocumentRepository>(
  (ref) => TeachingDocumentRepository(
    ref.watch(teachingDocumentRemoteDataSourceProvider),
  ),
);
