import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/private_worship_scan/data/private_worship_scan_remote_data_source.dart';
import 'package:nusa/features/private_worship_scan/domain/private_worship_scan.dart';

final class PrivateWorshipScanRepository {
  PrivateWorshipScanRepository(this._remote);

  final PrivateWorshipScanRemoteDataSource _remote;

  Future<PrivateWorshipScanDashboard> fetch({int? scheduleId}) =>
      _remote.fetch(scheduleId: scheduleId);

  Future<PrivateWorshipScanResult> submit({
    required int scheduleId,
    required String rawValue,
  }) => _remote.submit(scheduleId: scheduleId, rawValue: rawValue);
}

final privateWorshipScanRepositoryProvider =
    Provider<PrivateWorshipScanRepository>(
      (ref) => PrivateWorshipScanRepository(
        ref.watch(privateWorshipScanRemoteDataSourceProvider),
      ),
    );
