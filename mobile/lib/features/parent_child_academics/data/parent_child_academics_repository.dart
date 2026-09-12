import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/parent_child_academics/data/parent_child_academics_remote_data_source.dart';
import 'package:nusa/features/parent_child_academics/domain/parent_child_academics.dart';

class ParentChildAcademicsRepository {
  const ParentChildAcademicsRepository(this._remote);

  final ParentChildAcademicsRemoteDataSource _remote;

  Future<ParentChildAcademicsPage> fetch({
    required ParentChildAcademicsTab tab,
    required String semester,
    int? studentId,
    int? academicYearId,
  }) => _remote.fetch(
    tab: tab,
    semester: semester,
    studentId: studentId,
    academicYearId: academicYearId,
  );
}

final parentChildAcademicsRepositoryProvider =
    Provider<ParentChildAcademicsRepository>(
      (ref) => ParentChildAcademicsRepository(
        ref.watch(parentChildAcademicsRemoteDataSourceProvider),
      ),
    );
