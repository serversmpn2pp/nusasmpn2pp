import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_card/data/student_card_remote_data_source.dart';
import 'package:nusa/features/student_card/domain/student_card.dart';

final class StudentCardRepository {
  const StudentCardRepository(this._remote);

  final StudentCardRemoteDataSource _remote;

  Future<StudentCardPage> fetch({
    int? academicYearId,
    int? classId,
    required String query,
    required int page,
  }) => _remote.fetch(
    academicYearId: academicYearId,
    classId: classId,
    query: query,
    page: page,
  );
}

final studentCardRepositoryProvider = Provider<StudentCardRepository>(
  (ref) =>
      StudentCardRepository(ref.watch(studentCardRemoteDataSourceProvider)),
);
