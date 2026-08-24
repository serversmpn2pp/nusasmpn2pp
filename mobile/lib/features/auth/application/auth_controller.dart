import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/data/auth_repository.dart';
import 'package:nusa/features/auth/domain/auth_session.dart';

class AuthState {
  const AuthState({
    this.session,
    this.isSubmitting = false,
    this.errorMessage,
    this.fieldErrors = const {},
  });

  final AuthSession? session;
  final bool isSubmitting;
  final String? errorMessage;
  final Map<String, List<String>> fieldErrors;

  String? fieldError(String name) => fieldErrors[name]?.firstOrNull;
}

class AuthController extends AsyncNotifier<AuthState> {
  @override
  Future<AuthState> build() async {
    final session = await ref.read(authRepositoryProvider).restoreSession();

    return AuthState(session: session);
  }

  Future<void> login({
    required String username,
    required String password,
  }) async {
    state = const AsyncData(AuthState(isSubmitting: true));

    try {
      final session = await ref
          .read(authRepositoryProvider)
          .login(username: username.trim(), password: password);
      state = AsyncData(AuthState(session: session));
    } on AppException catch (exception) {
      state = AsyncData(
        AuthState(
          errorMessage: exception.message,
          fieldErrors: switch (exception) {
            ValidationException() => exception.errors,
            _ => const {},
          },
        ),
      );
    }
  }

  Future<void> ubahKataSandi({
    required String kataSandiLama,
    required String kataSandiBaru,
    required String konfirmasiKataSandiBaru,
  }) async {
    final currentSession = state.value?.session;

    if (currentSession == null) {
      return;
    }

    state = AsyncData(AuthState(session: currentSession, isSubmitting: true));

    try {
      final pengguna = await ref
          .read(authRepositoryProvider)
          .ubahKataSandi(
            kataSandiLama: kataSandiLama,
            kataSandiBaru: kataSandiBaru,
            konfirmasiKataSandiBaru: konfirmasiKataSandiBaru,
          );
      state = AsyncData(
        AuthState(session: currentSession.copyWith(pengguna: pengguna)),
      );
    } on UnauthorizedException catch (exception) {
      state = AsyncData(AuthState(errorMessage: exception.message));
    } on AppException catch (exception) {
      state = AsyncData(
        AuthState(
          session: currentSession,
          errorMessage: exception.message,
          fieldErrors: switch (exception) {
            ValidationException() => exception.errors,
            _ => const {},
          },
        ),
      );
    }
  }

  Future<void> logout() async {
    final currentSession = state.value?.session;

    if (currentSession != null) {
      state = AsyncData(AuthState(session: currentSession, isSubmitting: true));
    }

    await ref.read(authRepositoryProvider).logout();
    state = const AsyncData(AuthState());
  }
}

final authControllerProvider = AsyncNotifierProvider<AuthController, AuthState>(
  AuthController.new,
);
