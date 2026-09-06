import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/facility_dashboard/data/facility_dashboard_repository.dart';
import 'package:nusa/features/facility_dashboard/domain/facility_dashboard.dart';

class FacilityDashboardController extends AsyncNotifier<FacilityDashboard> {
  @override
  Future<FacilityDashboard> build() => _fetch();

  Future<void> refresh() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<FacilityDashboard> _fetch() async {
    try {
      return await ref.read(facilityDashboardRepositoryProvider).fetch();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final facilityDashboardControllerProvider =
    AsyncNotifierProvider.autoDispose<
      FacilityDashboardController,
      FacilityDashboard
    >(FacilityDashboardController.new);
