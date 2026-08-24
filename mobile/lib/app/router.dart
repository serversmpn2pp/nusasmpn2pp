import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/presentation/ganti_kata_sandi_view.dart';
import 'package:nusa/features/auth/presentation/login_view.dart';
import 'package:nusa/features/auth/presentation/startup_view.dart';
import 'package:nusa/features/home/presentation/home_view.dart';

abstract final class AppRoutes {
  static const startup = '/startup';
  static const login = '/login';
  static const gantiKataSandi = '/ganti-kata-sandi';
  static const home = '/beranda';
}

final appRouterProvider = Provider<GoRouter>((ref) {
  final authAsync = ref.watch(authControllerProvider);
  final router = GoRouter(
    initialLocation: AppRoutes.startup,
    redirect: (context, routerState) {
      final location = routerState.matchedLocation;

      if (authAsync.isLoading || authAsync.hasError) {
        return location == AppRoutes.startup ? null : AppRoutes.startup;
      }

      final auth = authAsync.value;
      final pengguna = auth?.session?.pengguna;

      if (pengguna == null) {
        return location == AppRoutes.login ? null : AppRoutes.login;
      }

      if (pengguna.wajibGantiKataSandi) {
        return location == AppRoutes.gantiKataSandi
            ? null
            : AppRoutes.gantiKataSandi;
      }

      if (location == AppRoutes.startup ||
          location == AppRoutes.login ||
          location == AppRoutes.gantiKataSandi) {
        return AppRoutes.home;
      }

      return null;
    },
    routes: [
      GoRoute(
        path: AppRoutes.startup,
        name: 'startup',
        builder: (context, state) => const StartupView(),
      ),
      GoRoute(
        path: AppRoutes.login,
        name: 'login',
        builder: (context, state) => const LoginView(),
      ),
      GoRoute(
        path: AppRoutes.gantiKataSandi,
        name: 'ganti-kata-sandi',
        builder: (context, state) => const GantiKataSandiView(),
      ),
      GoRoute(
        path: AppRoutes.home,
        name: 'home',
        builder: (context, state) => const HomeView(),
      ),
    ],
  );

  ref.onDispose(router.dispose);

  return router;
});
