import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/late_point_setting/data/late_point_setting_remote_data_source.dart';
import 'package:nusa/features/late_point_setting/domain/late_point_setting.dart';

final class LatePointSettingRepository {
  LatePointSettingRepository(this._remote);

  final LatePointSettingRemoteDataSource _remote;

  Future<LatePointSettingPage> fetch({
    required String query,
    required String status,
  }) => _remote.fetch(query: query, status: status);

  Future<void> update({
    required int academicYearId,
    required LatePointSettingFormValue value,
  }) => _remote.update(academicYearId: academicYearId, value: value);
}

final latePointSettingRepositoryProvider = Provider<LatePointSettingRepository>(
  (ref) => LatePointSettingRepository(
    ref.watch(latePointSettingRemoteDataSourceProvider),
  ),
);
