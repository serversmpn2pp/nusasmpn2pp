import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/early_warning_setting/data/early_warning_setting_remote_data_source.dart';
import 'package:nusa/features/early_warning_setting/domain/early_warning_setting.dart';

final class EarlyWarningSettingRepository {
  EarlyWarningSettingRepository(this._remote);

  final EarlyWarningSettingRemoteDataSource _remote;

  Future<EarlyWarningSettingPage> fetch({
    required String query,
    required String status,
  }) => _remote.fetch(query: query, status: status);

  Future<void> update({
    required int academicYearId,
    required EarlyWarningSettingFormValue value,
  }) => _remote.update(academicYearId: academicYearId, value: value);
}

final earlyWarningSettingRepositoryProvider =
    Provider<EarlyWarningSettingRepository>(
      (ref) => EarlyWarningSettingRepository(
        ref.watch(earlyWarningSettingRemoteDataSourceProvider),
      ),
    );
