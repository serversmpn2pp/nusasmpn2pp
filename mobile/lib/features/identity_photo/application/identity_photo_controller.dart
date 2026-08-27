import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/identity_photo/data/identity_photo_repository.dart';
import 'package:nusa/features/identity_photo/domain/identity_photo.dart';

class IdentityPhotoController extends AsyncNotifier<IdentityPhotoPage> {
  String _tab = 'siswa';
  int? _academicYearId;
  int? _classId;
  String _photoStatus = 'semua';
  String _employeeStatus = 'aktif';
  String _employeeType = '';
  String _query = '';
  int _requestVersion = 0;

  @override
  Future<IdentityPhotoPage> build() => _fetch(page: 1);

  Future<void> selectTab(String value) async {
    if (_tab == value) return;
    _tab = value;
    _query = '';
    await refresh();
  }

  Future<void> selectAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    _classId = null;
    _query = '';
    await refresh();
  }

  Future<void> selectClass(int? value) async {
    if (_classId == value) return;
    _classId = value;
    _query = '';
    await refresh();
  }

  Future<void> filterPhotoStatus(String value) async {
    if (_photoStatus == value) return;
    _photoStatus = value;
    await refresh();
  }

  Future<void> filterEmployeeStatus(String value) async {
    if (_employeeStatus == value) return;
    _employeeStatus = value;
    await refresh();
  }

  Future<void> filterEmployeeType(String value) async {
    if (_employeeType == value) return;
    _employeeType = value;
    await refresh();
  }

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(page: 1);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(page: current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<IdentityPhotoPage> _fetch({required int page}) async {
    try {
      final result = await ref
          .read(identityPhotoRepositoryProvider)
          .fetch(
            tab: _tab,
            academicYearId: _academicYearId,
            classId: _classId,
            photoStatus: _photoStatus,
            employeeStatus: _employeeStatus,
            employeeType: _employeeType,
            query: _query,
            page: page,
          );
      _tab = result.tab;
      _academicYearId = result.academicYearId;
      _classId = result.classId;
      _photoStatus = result.photoStatus;
      _employeeStatus = result.employeeStatus;
      _employeeType = result.employeeType;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final identityPhotoControllerProvider =
    AsyncNotifierProvider.autoDispose<IdentityPhotoController, IdentityPhotoPage>(
      IdentityPhotoController.new,
    );

final identityPhotoActionsProvider = Provider<IdentityPhotoActions>(
  IdentityPhotoActions.new,
);

class IdentityPhotoActions {
  IdentityPhotoActions(this._ref);

  final Ref _ref;

  Future<String> upload({
    required String tab,
    required int personId,
    required IdentityPhotoPickedFile file,
  }) async {
    try {
      return await _ref
          .read(identityPhotoRepositoryProvider)
          .upload(tab: tab, personId: personId, file: file);
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
