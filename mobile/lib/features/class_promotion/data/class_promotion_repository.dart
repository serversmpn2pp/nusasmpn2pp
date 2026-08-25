import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/class_promotion/data/class_promotion_remote_data_source.dart';
import 'package:nusa/features/class_promotion/domain/class_promotion.dart';

class ClassPromotionRepository {
  ClassPromotionRepository(this._remote);

  final ClassPromotionRemoteDataSource _remote;

  Future<ClassPromotionPage> fetch({
    required int? sourceYearId,
    required int? destinationYearId,
    required int? sourceClassId,
  }) => _remote.fetch(
    sourceYearId: sourceYearId,
    destinationYearId: destinationYearId,
    sourceClassId: sourceClassId,
  );

  Future<PromotionResult> process({
    required int sourceYearId,
    required int destinationYearId,
    required int sourceClassId,
    required List<PromotionAssignment> assignments,
  }) => _remote.process(
    sourceYearId: sourceYearId,
    destinationYearId: destinationYearId,
    sourceClassId: sourceClassId,
    assignments: assignments,
  );
}

final classPromotionRepositoryProvider = Provider<ClassPromotionRepository>(
  (ref) => ClassPromotionRepository(
    ref.watch(classPromotionRemoteDataSourceProvider),
  ),
);
