import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/exam_attendance/data/exam_attendance_repository.dart';
import 'package:nusa/features/exam_attendance/domain/exam_attendance.dart';

class ExamAttendanceController extends AsyncNotifier<ExamAttendanceDashboard> {
  @override
  Future<ExamAttendanceDashboard> build() => _fetch();

  Future<void> refresh() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<ExamAttendanceDashboard> _fetch() async {
    try {
      return await ref.read(examAttendanceRepositoryProvider).fetch();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final examAttendanceControllerProvider =
    AsyncNotifierProvider.autoDispose<
      ExamAttendanceController,
      ExamAttendanceDashboard
    >(ExamAttendanceController.new);

final examAttendanceDetailProvider = FutureProvider.autoDispose
    .family<ExamAttendanceDetail, int>((ref, roomId) async {
      try {
        return await ref
            .read(examAttendanceRepositoryProvider)
            .fetchDetail(roomId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

class ExamAttendanceActions {
  const ExamAttendanceActions(this.ref);

  final Ref ref;

  Future<ExamAttendanceScanResult> scan({
    required int roomId,
    required String rawValue,
  }) => _guard(
    () => ref
        .read(examAttendanceRepositoryProvider)
        .scan(roomId: roomId, rawValue: rawValue),
  );

  Future<ExamAttendanceDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? note,
  }) => _guard(
    () => ref
        .read(examAttendanceRepositoryProvider)
        .changeAttendance(
          roomId: roomId,
          participantId: participantId,
          status: status,
          note: note,
        ),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final examAttendanceActionsProvider = Provider(ExamAttendanceActions.new);
