import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/grade_weight_scheme/data/grade_weight_scheme_remote_data_source.dart';
import 'package:nusa/features/grade_weight_scheme/domain/grade_weight_scheme.dart';

class GradeWeightSchemeRepository {
  GradeWeightSchemeRepository(this._remote);

  final GradeWeightSchemeRemoteDataSource _remote;

  Future<GradeWeightSchemePage> fetch({
    required int? academicYearId,
    required String semester,
    required String grade,
    required String status,
    required int page,
  }) => _remote.fetch(
    academicYearId: academicYearId,
    semester: semester,
    grade: grade,
    status: status,
    page: page,
  );

  Future<void> create(GradeWeightSchemeFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required GradeWeightSchemeFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final gradeWeightSchemeRepositoryProvider =
    Provider<GradeWeightSchemeRepository>(
      (ref) => GradeWeightSchemeRepository(
        ref.watch(gradeWeightSchemeRemoteDataSourceProvider),
      ),
    );
