import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/worship_absence_settings/data/worship_absence_settings_remote_data_source.dart';
import 'package:nusa/features/worship_absence_settings/domain/worship_absence_settings.dart';

final class WorshipAbsenceSettingsRepository {
  WorshipAbsenceSettingsRepository(this._remote);

  final WorshipAbsenceSettingsRemoteDataSource _remote;

  Future<WorshipAbsenceSettingsPage> fetch() => _remote.fetch();

  Future<void> updateSettings(WorshipAbsenceSettingsValue value) =>
      _remote.updateSettings(value);

  Future<void> saveCompanion(WorshipCompanionAssignmentValue value) =>
      _remote.saveCompanion(value);

  Future<void> deactivateCompanion(int id) => _remote.deactivateCompanion(id);
}

final worshipAbsenceSettingsRepositoryProvider =
    Provider<WorshipAbsenceSettingsRepository>(
      (ref) => WorshipAbsenceSettingsRepository(
        ref.watch(worshipAbsenceSettingsRemoteDataSourceProvider),
      ),
    );
