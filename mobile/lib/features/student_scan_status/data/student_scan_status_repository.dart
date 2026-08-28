import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_scan_status/data/student_scan_status_remote_data_source.dart';
import 'package:nusa/features/student_scan_status/domain/student_scan_status.dart';

final class StudentScanStatusRepository {
  StudentScanStatusRepository(this._remote);

  final StudentScanStatusRemoteDataSource _remote;

  Future<StudentScanStatusDashboard> fetch({
    required int? classId,
    required String status,
    required String query,
  }) => _remote.fetch(classId: classId, status: status, query: query);
}

final studentScanStatusRepositoryProvider =
    Provider<StudentScanStatusRepository>(
      (ref) => StudentScanStatusRepository(
        ref.watch(studentScanStatusRemoteDataSourceProvider),
      ),
    );
