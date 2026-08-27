import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/teaching_document_type/data/teaching_document_type_remote_data_source.dart';
import 'package:nusa/features/teaching_document_type/domain/teaching_document_type.dart';

final class TeachingDocumentTypeRepository {
  TeachingDocumentTypeRepository(this._remote);

  final TeachingDocumentTypeRemoteDataSource _remote;

  Future<TeachingDocumentTypePage> fetch({
    required String query,
    required String status,
    required String requirement,
    required int page,
  }) => _remote.fetch(
    query: query,
    status: status,
    requirement: requirement,
    page: page,
  );

  Future<void> create(TeachingDocumentTypeFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required TeachingDocumentTypeFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final teachingDocumentTypeRepositoryProvider =
    Provider<TeachingDocumentTypeRepository>(
      (ref) => TeachingDocumentTypeRepository(
        ref.watch(teachingDocumentTypeRemoteDataSourceProvider),
      ),
    );
