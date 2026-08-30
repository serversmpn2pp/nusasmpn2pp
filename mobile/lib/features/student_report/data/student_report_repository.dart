import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_report/data/student_report_remote_data_source.dart';
import 'package:nusa/features/student_report/domain/student_report.dart';

final class StudentReportRepository {
  StudentReportRepository(this._remote);

  final StudentReportRemoteDataSource _remote;

  Future<StudentReportPage> fetch({
    required String query,
    required String status,
    required String level,
    required String type,
    required String verificationStatus,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) => _remote.fetch(
    query: query,
    status: status,
    level: level,
    type: type,
    verificationStatus: verificationStatus,
    academicYearId: academicYearId,
    classId: classId,
    page: page,
  );

  Future<StudentReportDetail> fetchDetail(int id) => _remote.fetchDetail(id);

  Future<StudentReportEvidenceDownload> downloadEvidence({
    required int id,
    required String fileName,
    required String mimeType,
  }) =>
      _remote.downloadEvidence(id: id, fileName: fileName, mimeType: mimeType);
}

final studentReportRepositoryProvider = Provider<StudentReportRepository>(
  (ref) =>
      StudentReportRepository(ref.watch(studentReportRemoteDataSourceProvider)),
);
