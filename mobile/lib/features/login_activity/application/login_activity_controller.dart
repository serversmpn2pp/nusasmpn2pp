import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/login_activity/data/login_activity_repository.dart';
import 'package:nusa/features/login_activity/domain/login_activity.dart';

class LoginActivityController extends AsyncNotifier<LoginActivityPage> {
  String _view = 'pengguna';
  String _query = '';
  String _accountType = 'semua';
  String _loginStatus = 'semua';
  String _attemptStatus = 'semua';
  String _device = 'semua';
  String? _startDate;
  String? _endDate;
  int _requestVersion = 0;

  @override
  Future<LoginActivityPage> build() => _fetch(page: 1);

  Future<void> changeView(String value) async {
    if (_view == value) return;
    _view = value;
    await refresh();
  }

  Future<void> viewHistoryFor(String username) async {
    _view = 'riwayat';
    _query = username;
    _attemptStatus = 'semua';
    _device = 'semua';
    _startDate = null;
    _endDate = null;
    await refresh();
  }

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> filterAccountType(String value) async {
    if (_accountType == value) return;
    _accountType = value;
    await refresh();
  }

  Future<void> filterLoginStatus(String value) async {
    if (_loginStatus == value) return;
    _loginStatus = value;
    await refresh();
  }

  Future<void> filterAttemptStatus(String value) async {
    if (_attemptStatus == value) return;
    _attemptStatus = value;
    await refresh();
  }

  Future<void> filterDevice(String value) async {
    if (_device == value) return;
    _device = value;
    await refresh();
  }

  Future<void> filterDates({String? startDate, String? endDate}) async {
    _startDate = startDate;
    _endDate = endDate;
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

  Future<LoginActivityPage> _fetch({required int page}) => _guard(
    () => ref
        .read(loginActivityRepositoryProvider)
        .fetchActivities(
          view: _view,
          query: _query,
          accountType: _accountType,
          loginStatus: _loginStatus,
          attemptStatus: _attemptStatus,
          device: _device,
          startDate: _startDate,
          endDate: _endDate,
          page: page,
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

final loginActivityControllerProvider =
    AsyncNotifierProvider.autoDispose<
      LoginActivityController,
      LoginActivityPage
    >(LoginActivityController.new);

final loginAttemptDetailProvider = FutureProvider.autoDispose
    .family<LoginAttemptDetail, int>((ref, attemptId) async {
      try {
        return await ref
            .read(loginActivityRepositoryProvider)
            .fetchAttempt(attemptId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
