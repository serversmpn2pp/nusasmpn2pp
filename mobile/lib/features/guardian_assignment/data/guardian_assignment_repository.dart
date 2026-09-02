import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/guardian_assignment/data/guardian_assignment_remote_data_source.dart';
import 'package:nusa/features/guardian_assignment/domain/guardian_assignment.dart';

class GuardianAssignmentRepository {
  const GuardianAssignmentRepository(this._remote);
  final GuardianAssignmentRemoteDataSource _remote;

  Future<GuardianAssignmentPage> fetch({
    required String query,
    required int? guardianId,
    required int page,
  }) => _remote.fetch(query: query, guardianId: guardianId, page: page);
  Future<GuardianAssignmentResult> create(GuardianAssignmentPayload payload) =>
      _remote.create(payload);
  Future<GuardianAssignmentMutation> end(int id) => _remote.end(id);
}

final guardianAssignmentRepositoryProvider =
    Provider<GuardianAssignmentRepository>(
      (ref) => GuardianAssignmentRepository(
        ref.watch(guardianAssignmentRemoteDataSourceProvider),
      ),
    );
