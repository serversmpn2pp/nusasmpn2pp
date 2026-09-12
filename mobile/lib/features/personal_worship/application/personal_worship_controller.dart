import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/personal_worship/data/personal_worship_repository.dart';
import 'package:nusa/features/personal_worship/domain/personal_worship.dart';

class PersonalWorshipController extends AsyncNotifier<PersonalWorshipPage> {
  int? _studentId;
  int? _academicYearId;
  String? _month;
  int _requestVersion = 0;

  @override
  Future<PersonalWorshipPage> build() => _fetch();

  Future<void> selectStudent(int? value) async {
    if (value == null || _studentId == value) return;
    _studentId = value;
    _academicYearId = null;
    await _reload();
  }

  Future<void> selectAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    await _reload();
  }

  Future<void> selectMonth(String value) async {
    if (_month == value) return;
    _month = value;
    await _reload();
  }

  Future<void> refresh() => _reload(initializeFromState: true);

  Future<void> _reload({bool initializeFromState = false}) async {
    if (initializeFromState) {
      final current = state.value;
      _studentId ??= current?.filter.studentId;
      _academicYearId ??= current?.filter.academicYearId;
      _month ??= current?.filter.month;
    }
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<PersonalWorshipPage> _fetch() => _guard(() async {
    final result = await ref
        .read(personalWorshipRepositoryProvider)
        .fetch(
          studentId: _studentId,
          academicYearId: _academicYearId,
          month: _month,
        );
    _studentId = result.filter.studentId;
    _academicYearId = result.filter.academicYearId;
    _month = result.filter.month;
    return result;
  });

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final personalWorshipControllerProvider =
    AsyncNotifierProvider.autoDispose<
      PersonalWorshipController,
      PersonalWorshipPage
    >(PersonalWorshipController.new);
