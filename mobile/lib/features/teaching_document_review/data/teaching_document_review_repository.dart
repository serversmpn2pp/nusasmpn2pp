import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/teaching_document_review/data/teaching_document_review_remote_data_source.dart';
import 'package:nusa/features/teaching_document_review/domain/teaching_document_review.dart';

class TeachingDocumentReviewRepository {
  TeachingDocumentReviewRepository(this._remote);

  final TeachingDocumentReviewRemoteDataSource _remote;

  Future<TeachingDocumentReviewPage> fetch({
    required String query,
    required int? academicYearId,
    required int semester,
    required String completeness,
    required String documentStatus,
    required int page,
  }) => _remote.fetch(
    query: query,
    academicYearId: academicYearId,
    semester: semester,
    completeness: completeness,
    documentStatus: documentStatus,
    page: page,
  );

  Future<TeachingDocumentTeacherDetail> fetchTeacher(
    TeachingDocumentTeacherQuery query,
  ) => _remote.fetchTeacher(query);

  Future<TeachingDocumentReviewDetail> fetchDocument(int id) =>
      _remote.fetchDocument(id);

  Future<TeachingDocumentDownload> download({
    required int id,
    required String fileName,
  }) => _remote.download(id: id, fileName: fileName);

  Future<void> review({
    required int id,
    required TeachingDocumentReviewValue value,
  }) => _remote.review(id: id, value: value);
}

final teachingDocumentReviewRepositoryProvider =
    Provider<TeachingDocumentReviewRepository>(
      (ref) => TeachingDocumentReviewRepository(
        ref.watch(teachingDocumentReviewRemoteDataSourceProvider),
      ),
    );
