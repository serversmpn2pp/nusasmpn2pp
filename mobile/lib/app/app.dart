import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/app/app_keys.dart';
import 'package:nusa/app/router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/core/security/password_change_gate.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/push_notifications/application/push_notification_coordinator.dart';

class NusaApp extends ConsumerStatefulWidget {
  const NusaApp({super.key});

  @override
  ConsumerState<NusaApp> createState() => _NusaAppState();
}

class _NusaAppState extends ConsumerState<NusaApp> {
  @override
  void initState() {
    super.initState();

    ref.listenManual(
      authControllerProvider,
      (_, _) => _syncPushSession(),
      fireImmediately: true,
    );
    ref.listenManual(passwordChangeGateProvider, (_, _) => _syncPushSession());

    unawaited(ref.read(pushNotificationCoordinatorProvider).start());
  }

  void _syncPushSession() {
    final authAsync = ref.read(authControllerProvider);
    final auth = authAsync.value;
    final user = auth?.session?.pengguna;
    final coordinator = ref.read(pushNotificationCoordinatorProvider);
    if (authAsync.isLoading ||
        authAsync.hasError ||
        user == null ||
        auth!.isSubmitting ||
        user.wajibGantiKataSandi ||
        ref.read(passwordChangeGateProvider)) {
      coordinator.loggedOut();
      return;
    }
    unawaited(coordinator.authenticated(user.id));
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(appRouterProvider);

    return MaterialApp.router(
      title: 'NUSA',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      scaffoldMessengerKey: nusaScaffoldMessengerKey,
      routerConfig: router,
    );
  }
}
