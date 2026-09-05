import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/storage/device_identity.dart';
import 'package:nusa/features/student_exam/data/student_exam_remote_data_source.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

class StudentExamRepository {
  const StudentExamRepository(this._remote, this._deviceIdentity);

  final StudentExamRemoteDataSource _remote;
  final DeviceIdentity _deviceIdentity;

  Future<StudentExamSession> detail(int participantId) =>
      _remote.detail(participantId);

  Future<StudentExamSession> start(int participantId, String? token) async =>
      _remote.start(
        participantId: participantId,
        token: token,
        device: await _deviceIdentity.readName(),
      );

  Future<StudentExamSession> resume(int participantId) async => _remote.resume(
    participantId: participantId,
    device: await _deviceIdentity.readName(),
  );

  Future<StudentExamSaveResult> saveAnswer({
    required int participantId,
    required int questionId,
    required Object? answer,
    required bool doubtful,
  }) async => _remote.saveAnswer(
    participantId: participantId,
    questionId: questionId,
    answer: answer,
    doubtful: doubtful,
    device: await _deviceIdentity.readName(),
  );

  Future<StudentExamSession> finish(int participantId) async => _remote.finish(
    participantId: participantId,
    device: await _deviceIdentity.readName(),
  );

  Future<StudentExamSecurityUpdate> securityEvent(
    int participantId,
    String event,
  ) async => _remote.securityEvent(
    participantId: participantId,
    event: event,
    device: await _deviceIdentity.readName(),
  );
}

final studentExamRepositoryProvider = Provider<StudentExamRepository>(
  (ref) => StudentExamRepository(
    ref.watch(studentExamRemoteDataSourceProvider),
    ref.watch(deviceIdentityProvider),
  ),
);
