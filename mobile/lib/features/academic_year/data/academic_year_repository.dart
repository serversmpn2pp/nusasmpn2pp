import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/academic_year/data/academic_year_remote_data_source.dart';
import 'package:nusa/features/academic_year/domain/academic_year.dart';

final class AcademicYearRepository {
  AcademicYearRepository(this._remote);

  final AcademicYearRemoteDataSource _remote;

  Future<AcademicYearPage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);

  Future<void> create(AcademicYearFormValue value) => _remote.create(value);

  Future<void> update({
    required int id,
    required AcademicYearFormValue value,
  }) => _remote.update(id: id, value: value);
}

final academicYearRepositoryProvider = Provider<AcademicYearRepository>(
  (ref) =>
      AcademicYearRepository(ref.watch(academicYearRemoteDataSourceProvider)),
);
