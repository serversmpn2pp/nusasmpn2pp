import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/cbt_center/data/cbt_center_repository.dart';
import 'package:nusa/features/cbt_center/domain/cbt_center.dart';

class CbtCenterController extends AsyncNotifier<CbtCenterData> {
  @override
  Future<CbtCenterData> build() => _fetch();

  Future<void> refresh() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<CbtCenterData> _fetch() async {
    try {
      return await ref.read(cbtCenterRepositoryProvider).fetch();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final cbtCenterControllerProvider =
    AsyncNotifierProvider.autoDispose<CbtCenterController, CbtCenterData>(
      CbtCenterController.new,
    );
