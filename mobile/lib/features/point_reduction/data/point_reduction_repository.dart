import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/point_reduction/data/point_reduction_remote_data_source.dart';
import 'package:nusa/features/point_reduction/domain/point_reduction.dart';

class PointReductionRepository {
  const PointReductionRepository(this._remote);
  final PointReductionRemoteDataSource _remote;

  Future<PointReductionPage> fetch({
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
  Future<PointReductionMutation> create(PointReductionCreatePayload payload) =>
      _remote.create(payload);
  Future<PointReductionMutation> decide({
    required int id,
    required String decision,
    required String? note,
  }) => _remote.decide(id: id, decision: decision, note: note);
  Future<ReductionEvidenceDownload> download(PointReductionItem item) =>
      _remote.download(item);
}

final pointReductionRepositoryProvider = Provider<PointReductionRepository>(
  (ref) => PointReductionRepository(
    ref.watch(pointReductionRemoteDataSourceProvider),
  ),
);
