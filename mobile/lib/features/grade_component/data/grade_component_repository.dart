import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/grade_component/data/grade_component_remote_data_source.dart';
import 'package:nusa/features/grade_component/domain/grade_component.dart';

class GradeComponentRepository {
  GradeComponentRepository(this._remote);

  final GradeComponentRemoteDataSource _remote;

  Future<GradeComponentPage> fetch({
    required String search,
    required int? academicYearId,
    required String semester,
    required String type,
    required String status,
    required String dateOrder,
    required int page,
  }) => _remote.fetch(
    search: search,
    academicYearId: academicYearId,
    semester: semester,
    type: type,
    status: status,
    dateOrder: dateOrder,
    page: page,
  );

  Future<String> create(
    GradeComponentFormValue value, {
    required bool collective,
  }) => _remote.create(value, collective: collective);

  Future<void> update({
    required int id,
    required GradeComponentFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final gradeComponentRepositoryProvider = Provider<GradeComponentRepository>(
  (ref) => GradeComponentRepository(
    ref.watch(gradeComponentRemoteDataSourceProvider),
  ),
);
