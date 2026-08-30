import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_sanction/data/student_sanction_remote_data_source.dart';
import 'package:nusa/features/student_sanction/domain/student_sanction.dart';

class StudentSanctionRepository {
  const StudentSanctionRepository(this._remote);
  final StudentSanctionRemoteDataSource _remote;
  Future<StudentSanctionPage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) => _remote.fetch(
    query: query,
    status: status,
    academicYearId: academicYearId,
    classId: classId,
    page: page,
  );
  Future<StudentSanctionDetail> fetchDetail(int id) => _remote.fetchDetail(id);
  Future<StudentSanctionDetail> update(
    int id,
    StudentSanctionPayload payload,
  ) => _remote.update(id, payload);
  Future<StudentSanctionDetail> uploadEvidence({
    required int id,
    required List<SanctionPickedFile> files,
    required String? description,
  }) => _remote.uploadEvidence(id: id, files: files, description: description);
  Future<StudentSanctionDetail> deleteEvidence(int id) =>
      _remote.deleteEvidence(id);
  Future<SanctionEvidenceDownload> downloadEvidence(
    SanctionEvidence evidence,
  ) => _remote.downloadEvidence(evidence);
}

final studentSanctionRepositoryProvider = Provider<StudentSanctionRepository>(
  (ref) => StudentSanctionRepository(
    ref.watch(studentSanctionRemoteDataSourceProvider),
  ),
);
