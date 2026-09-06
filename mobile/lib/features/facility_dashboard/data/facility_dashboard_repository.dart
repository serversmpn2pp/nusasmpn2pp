import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/facility_dashboard/data/facility_dashboard_remote_data_source.dart';
import 'package:nusa/features/facility_dashboard/domain/facility_dashboard.dart';

final class FacilityDashboardRepository {
  const FacilityDashboardRepository(this._remote);

  final FacilityDashboardRemoteDataSource _remote;

  Future<FacilityDashboard> fetch() => _remote.fetch();
}

final facilityDashboardRepositoryProvider =
    Provider<FacilityDashboardRepository>(
      (ref) => FacilityDashboardRepository(
        ref.watch(facilityDashboardRemoteDataSourceProvider),
      ),
    );
