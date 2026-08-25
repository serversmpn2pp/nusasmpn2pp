import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/login_activity/data/login_activity_remote_data_source.dart';
import 'package:nusa/features/login_activity/domain/login_activity.dart';

class LoginActivityRepository {
  LoginActivityRepository(this._remoteDataSource);

  final LoginActivityRemoteDataSource _remoteDataSource;

  Future<LoginActivityPage> fetchActivities({
    required String view,
    required String query,
    required String accountType,
    required String loginStatus,
    required String attemptStatus,
    required String device,
    required String? startDate,
    required String? endDate,
    required int page,
  }) => _remoteDataSource.fetchActivities(
    view: view,
    query: query,
    accountType: accountType,
    loginStatus: loginStatus,
    attemptStatus: attemptStatus,
    device: device,
    startDate: startDate,
    endDate: endDate,
    page: page,
  );

  Future<LoginAttemptDetail> fetchAttempt(int attemptId) =>
      _remoteDataSource.fetchAttempt(attemptId);
}

final loginActivityRepositoryProvider = Provider<LoginActivityRepository>(
  (ref) =>
      LoginActivityRepository(ref.watch(loginActivityRemoteDataSourceProvider)),
);
