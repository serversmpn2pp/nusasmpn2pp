import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/central_exam_preparation/data/central_exam_preparation_repository.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';

class CentralExamPreparationController
    extends AsyncNotifier<CentralExamPreparationPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<CentralExamPreparationPage> build() => _fetch(1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) {
        state = AsyncError(error, stackTrace);
      }
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<CentralExamPreparationPage> _fetch(int page) async {
    try {
      return await ref
          .read(centralExamPreparationRepositoryProvider)
          .fetch(query: _query, status: _status, page: page);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final centralExamPreparationControllerProvider =
    AsyncNotifierProvider.autoDispose<
      CentralExamPreparationController,
      CentralExamPreparationPage
    >(CentralExamPreparationController.new);

final centralExamPreparationDetailProvider = FutureProvider.autoDispose
    .family<CentralExamPreparationDetail, int>((ref, eventId) async {
      try {
        return await ref
            .watch(centralExamPreparationRepositoryProvider)
            .detail(eventId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final centralExamPreparationActionsProvider =
    Provider<CentralExamPreparationActions>(CentralExamPreparationActions.new);

typedef CentralExamDistributionRequest = ({int eventId, int groupId});

final centralExamDistributionDetailProvider = FutureProvider.autoDispose
    .family<CentralExamDistributionDetail, CentralExamDistributionRequest>((
      ref,
      request,
    ) async {
      try {
        return await ref
            .watch(centralExamPreparationRepositoryProvider)
            .distributionDetail(request.eventId, request.groupId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

class CentralExamPreparationActions {
  CentralExamPreparationActions(this._ref);
  final Ref _ref;

  Future<int> createEvent(CentralExamEventFormValue value) async {
    final id = await _guard(
      () => _ref
          .read(centralExamPreparationRepositoryProvider)
          .createEvent(value),
    );
    _refreshList();
    return id;
  }

  Future<void> updateEvent(int id, CentralExamEventFormValue value) => _mutate(
    eventId: id,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .updateEvent(id, value),
  );

  Future<void> deleteEvent(int id) async {
    await _guard(
      () => _ref.read(centralExamPreparationRepositoryProvider).deleteEvent(id),
    );
    _refreshList();
  }

  Future<void> saveCommittee(
    int eventId,
    CentralExamCommitteeFormValue value,
  ) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .saveCommittee(eventId, value),
  );

  Future<void> deleteCommittee(int eventId, int memberId) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .deleteCommittee(eventId, memberId),
  );

  Future<void> createSession(int eventId, CentralExamSessionFormValue value) =>
      _mutate(
        eventId: eventId,
        operation: () => _ref
            .read(centralExamPreparationRepositoryProvider)
            .createSession(eventId, value),
      );

  Future<void> updateSession(
    int eventId,
    int sessionId,
    CentralExamSessionFormValue value,
  ) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .updateSession(eventId, sessionId, value),
  );

  Future<void> deleteSession(int eventId, int sessionId) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .deleteSession(eventId, sessionId),
  );

  Future<void> createRoom(int eventId, CentralExamRoomFormValue value) =>
      _mutate(
        eventId: eventId,
        operation: () => _ref
            .read(centralExamPreparationRepositoryProvider)
            .createRoom(eventId, value),
      );

  Future<void> updateRoom(
    int eventId,
    int roomId,
    CentralExamRoomFormValue value,
  ) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .updateRoom(eventId, roomId, value),
  );

  Future<void> deleteRoom(int eventId, int roomId) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .deleteRoom(eventId, roomId),
  );

  Future<void> saveRoomAssignment(
    int eventId,
    CentralExamRoomAssignmentFormValue value,
  ) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .saveRoomAssignment(eventId, value),
  );

  Future<void> generateParticipants(int eventId, int groupId) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .generateParticipants(eventId, groupId),
  );

  Future<void> deleteRoomAssignment(int eventId, int groupId) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .deleteRoomAssignment(eventId, groupId),
  );

  Future<void> createSchedule(
    int eventId,
    CentralExamScheduleFormValue value,
  ) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .createSchedule(eventId, value),
  );

  Future<void> updateSchedule(
    int eventId,
    int scheduleId,
    CentralExamScheduleFormValue value,
  ) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .updateSchedule(eventId, scheduleId, value),
  );

  Future<void> deleteSchedule(int eventId, int scheduleId) => _mutate(
    eventId: eventId,
    operation: () => _ref
        .read(centralExamPreparationRepositoryProvider)
        .deleteSchedule(eventId, scheduleId),
  );

  Future<void> _mutate({
    required int eventId,
    required Future<void> Function() operation,
  }) async {
    await _guard(operation);
    _ref.invalidate(centralExamPreparationDetailProvider(eventId));
    _refreshList();
  }

  void _refreshList() {
    _ref.invalidate(centralExamPreparationControllerProvider);
  }

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
