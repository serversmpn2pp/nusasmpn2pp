import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/school_class/data/school_class_repository.dart';
import 'package:nusa/features/school_class/domain/school_class.dart';

class SchoolClassListController extends AsyncNotifier<SchoolClassPage> {
  String _query = '';
  String _status = 'semua';
  int? _academicYearId;
  int _requestVersion = 0;

  @override
  Future<SchoolClassPage> build() => _fetch(page: 1);

  Future<void> search(String query) async {
    _query = query.trim();
    await _reload();
  }

  Future<void> filterStatus(String status) async {
    if (_status == status) {
      return;
    }

    _status = status;
    await _reload();
  }

  Future<void> filterAcademicYear(int? academicYearId) async {
    if (_academicYearId == academicYearId) {
      return;
    }

    _academicYearId = academicYearId;
    await _reload();
  }

  Future<void> refresh() => _reload();

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) {
      return;
    }

    final next = await _fetch(page: current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<void> _reload() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();

    try {
      final result = await _fetch(page: 1);
      if (version == _requestVersion) {
        state = AsyncData(result);
      }
    } catch (error, stackTrace) {
      if (version == _requestVersion) {
        state = AsyncError(error, stackTrace);
      }
    }
  }

  Future<SchoolClassPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(schoolClassRepositoryProvider)
          .fetchClasses(
            query: _query,
            status: _status,
            page: page,
            academicYearId: _academicYearId,
          );
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final schoolClassListControllerProvider =
    AsyncNotifierProvider.autoDispose<
      SchoolClassListController,
      SchoolClassPage
    >(SchoolClassListController.new);

final schoolClassDetailProvider = FutureProvider.autoDispose
    .family<SchoolClassDetail, int>((ref, id) async {
      try {
        return await ref.read(schoolClassRepositoryProvider).fetchClass(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final schoolClassMemberActionsProvider = Provider<SchoolClassMemberActions>(
  SchoolClassMemberActions.new,
);

final schoolClassScheduleActionsProvider = Provider<SchoolClassScheduleActions>(
  SchoolClassScheduleActions.new,
);

class SchoolClassScheduleActions {
  SchoolClassScheduleActions(this._ref);

  final Ref _ref;

  Future<ScheduleChoiceCatalog> fetchChoices({required int classId}) => _guard(
    () => _ref
        .read(schoolClassRepositoryProvider)
        .fetchScheduleChoices(classId: classId),
  );

  Future<void> updateSlot({
    required int classId,
    required int slotId,
    required String? scheduleChoice,
    String? notes,
  }) async {
    await _guard(
      () => _ref
          .read(schoolClassRepositoryProvider)
          .updateScheduleSlot(
            classId: classId,
            slotId: slotId,
            scheduleChoice: scheduleChoice,
            notes: notes,
          ),
    );
    _ref.invalidate(schoolClassDetailProvider(classId));
    _ref.invalidate(schoolClassListControllerProvider);
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

class SchoolClassMemberActions {
  SchoolClassMemberActions(this._ref);

  final Ref _ref;

  Future<SchoolClassCandidatePage> fetchCandidates({
    required int classId,
    String query = '',
  }) => _guard(
    () => _ref
        .read(schoolClassRepositoryProvider)
        .fetchCandidates(classId: classId, query: query),
  );

  Future<void> addMember({
    required int classId,
    required int studentId,
    DateTime? joinDate,
    String? notes,
  }) async {
    await _guard(
      () => _ref
          .read(schoolClassRepositoryProvider)
          .addMember(
            classId: classId,
            studentId: studentId,
            joinDate: joinDate,
            notes: notes,
          ),
    );
    _refresh(classId);
  }

  Future<void> updateMember({
    required int classId,
    required int memberId,
    DateTime? joinDate,
    String? notes,
  }) async {
    await _guard(
      () => _ref
          .read(schoolClassRepositoryProvider)
          .updateMember(
            classId: classId,
            memberId: memberId,
            joinDate: joinDate,
            notes: notes,
          ),
    );
    _refresh(classId);
  }

  Future<void> deleteMember({
    required int classId,
    required int memberId,
  }) async {
    await _guard(
      () => _ref
          .read(schoolClassRepositoryProvider)
          .deleteMember(classId: classId, memberId: memberId),
    );
    _refresh(classId);
  }

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  void _refresh(int classId) {
    _ref.invalidate(schoolClassDetailProvider(classId));
    _ref.invalidate(schoolClassListControllerProvider);
  }
}
