import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/personal_worship/data/personal_worship_remote_data_source.dart';
import 'package:nusa/features/personal_worship/domain/personal_worship.dart';

class PersonalWorshipRepository {
  PersonalWorshipRepository(this._remote);

  final PersonalWorshipRemoteDataSource _remote;

  Future<PersonalWorshipPage> fetch({
    required int? studentId,
    required int? academicYearId,
    required String? month,
  }) => _remote.fetch(
    studentId: studentId,
    academicYearId: academicYearId,
    month: month,
  );
}

final personalWorshipRepositoryProvider = Provider<PersonalWorshipRepository>(
  (ref) => PersonalWorshipRepository(
    ref.watch(personalWorshipRemoteDataSourceProvider),
  ),
);
