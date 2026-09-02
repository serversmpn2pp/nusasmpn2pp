import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_point_recap/data/student_point_recap_remote_data_source.dart';
import 'package:nusa/features/student_point_recap/domain/student_point_recap.dart';

final class StudentPointRecapRepository {
  const StudentPointRecapRepository(this._remote);
  final StudentPointRecapRemoteDataSource _remote;

  Future<StudentPointRecapPage> fetch({
    required String query,
    required String attentionStatus,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) => _remote.fetch(
    query: query,
    attentionStatus: attentionStatus,
    academicYearId: academicYearId,
    classId: classId,
    page: page,
  );

  Future<StudentPointRecapDetail> fetchDetail(
    int studentId,
    int? academicYearId,
  ) => _remote.fetchDetail(studentId, academicYearId);
}

final studentPointRecapRepositoryProvider =
    Provider<StudentPointRecapRepository>(
      (ref) => StudentPointRecapRepository(
        ref.watch(studentPointRecapRemoteDataSourceProvider),
      ),
    );
