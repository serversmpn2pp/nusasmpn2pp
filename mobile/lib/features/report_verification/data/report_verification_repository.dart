import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/report_verification/data/report_verification_remote_data_source.dart';
import 'package:nusa/features/report_verification/domain/report_verification.dart';

final class ReportVerificationRepository {
  const ReportVerificationRepository(this._remote);

  final ReportVerificationRemoteDataSource _remote;

  Future<ReportVerificationPage> fetch({
    required String query,
    required String queue,
    required int page,
  }) => _remote.fetch(query: query, queue: queue, page: page);

  Future<ReportVerificationDetail> fetchDetail(int reportId) =>
      _remote.fetchDetail(reportId);

  Future<ReportVerificationMutation> review({
    required int reportId,
    required String result,
    required List<int> violationIds,
    required String? note,
  }) => _remote.review(
    reportId: reportId,
    result: result,
    violationIds: violationIds,
    note: note,
  );

  Future<ReportVerificationMutation> approve({
    required int reportId,
    required String decision,
    required String? note,
  }) => _remote.approve(
    reportId: reportId,
    decision: decision,
    note: note,
  );
}

final reportVerificationRepositoryProvider =
    Provider<ReportVerificationRepository>(
      (ref) => ReportVerificationRepository(
        ref.watch(reportVerificationRemoteDataSourceProvider),
      ),
    );
