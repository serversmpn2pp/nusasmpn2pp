import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/violation_process_deadline/data/violation_process_deadline_remote_data_source.dart';
import 'package:nusa/features/violation_process_deadline/domain/violation_process_deadline.dart';

final class ViolationProcessDeadlineRepository {
  ViolationProcessDeadlineRepository(this._remote);

  final ViolationProcessDeadlineRemoteDataSource _remote;

  Future<ViolationProcessDeadlinePage> fetch({
    required String query,
    required String status,
  }) => _remote.fetch(query: query, status: status);

  Future<void> update({
    required int academicYearId,
    required ViolationProcessDeadlineFormValue value,
  }) => _remote.update(academicYearId: academicYearId, value: value);
}

final violationProcessDeadlineRepositoryProvider =
    Provider<ViolationProcessDeadlineRepository>(
      (ref) => ViolationProcessDeadlineRepository(
        ref.watch(violationProcessDeadlineRemoteDataSourceProvider),
      ),
    );
