import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/worship_scan/data/worship_scan_remote_data_source.dart';
import 'package:nusa/features/worship_scan/domain/worship_scan.dart';

final class WorshipScanRepository {
  WorshipScanRepository(this._remote);

  final WorshipScanRemoteDataSource _remote;

  Future<WorshipScanDashboard> fetch({int? scheduleId}) =>
      _remote.fetch(scheduleId: scheduleId);

  Future<WorshipScanResult> submit({
    required int scheduleId,
    required String rawValue,
  }) => _remote.submit(scheduleId: scheduleId, rawValue: rawValue);
}

final worshipScanRepositoryProvider = Provider<WorshipScanRepository>(
  (ref) =>
      WorshipScanRepository(ref.watch(worshipScanRemoteDataSourceProvider)),
);
