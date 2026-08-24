import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/menu/data/menu_repository.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

class MenuController extends AsyncNotifier<MenuCatalog> {
  @override
  Future<MenuCatalog> build() => _fetch();

  Future<void> refresh() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<MenuCatalog> _fetch() async {
    try {
      return await ref.read(menuRepositoryProvider).fetchCatalog();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final menuControllerProvider =
    AsyncNotifierProvider.autoDispose<MenuController, MenuCatalog>(
      MenuController.new,
    );
