import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/school_class/data/school_class_remote_data_source.dart';
import 'package:nusa/features/school_class/domain/school_class.dart';

final class SchoolClassRepository {
  SchoolClassRepository(this._remote);

  final SchoolClassRemoteDataSource _remote;

  Future<SchoolClassPage> fetchClasses({
    required String query,
    required String status,
    required int page,
    int? academicYearId,
  }) {
    return _remote.fetchClasses(
      query: query,
      status: status,
      page: page,
      academicYearId: academicYearId,
    );
  }

  Future<SchoolClassDetail> fetchClass(int id) => _remote.fetchClass(id);

  Future<SchoolClassCandidatePage> fetchCandidates({
    required int classId,
    String query = '',
  }) => _remote.fetchCandidates(classId: classId, query: query);

  Future<void> addMember({
    required int classId,
    required int studentId,
    DateTime? joinDate,
    String? notes,
  }) => _remote.addMember(
    classId: classId,
    studentId: studentId,
    joinDate: joinDate,
    notes: notes,
  );

  Future<void> updateMember({
    required int classId,
    required int memberId,
    DateTime? joinDate,
    String? notes,
  }) => _remote.updateMember(
    classId: classId,
    memberId: memberId,
    joinDate: joinDate,
    notes: notes,
  );

  Future<void> deleteMember({required int classId, required int memberId}) =>
      _remote.deleteMember(classId: classId, memberId: memberId);

  Future<ScheduleChoiceCatalog> fetchScheduleChoices({required int classId}) =>
      _remote.fetchScheduleChoices(classId: classId);

  Future<void> updateScheduleSlot({
    required int classId,
    required int slotId,
    required String? scheduleChoice,
    String? notes,
  }) => _remote.updateScheduleSlot(
    classId: classId,
    slotId: slotId,
    scheduleChoice: scheduleChoice,
    notes: notes,
  );
}

final schoolClassRepositoryProvider = Provider<SchoolClassRepository>((ref) {
  return SchoolClassRepository(ref.watch(schoolClassRemoteDataSourceProvider));
});
