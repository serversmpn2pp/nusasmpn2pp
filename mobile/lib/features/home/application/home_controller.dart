import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/home/data/home_repository.dart';
import 'package:nusa/features/home/domain/home_dashboard.dart';

class HomeController extends AsyncNotifier<HomeDashboard> {
  @override
  Future<HomeDashboard> build() => _fetch();

  Future<void> refresh() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<HomeDashboard> _fetch() async {
    try {
      return await ref.read(homeRepositoryProvider).fetchDashboard();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final homeControllerProvider =
    AsyncNotifierProvider.autoDispose<HomeController, HomeDashboard>(
      HomeController.new,
    );
