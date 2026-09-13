import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/app/app_keys.dart';
import 'package:nusa/app/router.dart';
import 'package:nusa/features/home/application/home_controller.dart';
import 'package:nusa/features/home/data/home_repository.dart';
import 'package:nusa/features/push_notifications/application/push_device_service.dart';

final class PushNotificationCoordinator {
  PushNotificationCoordinator(this._ref);

  final Ref _ref;
  StreamSubscription<RemoteMessage>? _foregroundSubscription;
  StreamSubscription<RemoteMessage>? _openedSubscription;
  StreamSubscription<String>? _tokenSubscription;
  RemoteMessage? _pendingMessage;
  bool _started = false;
  bool _authenticated = false;

  Future<void> start() async {
    if (_started || !_ref.read(pushDeviceServiceProvider).available) return;
    _started = true;

    _foregroundSubscription = FirebaseMessaging.onMessage.listen(
      _handleForegroundMessage,
    );
    _openedSubscription = FirebaseMessaging.onMessageOpenedApp.listen(
      _queueOrOpen,
    );
    _tokenSubscription = FirebaseMessaging.instance.onTokenRefresh.listen(
      _handleTokenRefresh,
    );

    final initialMessage = await FirebaseMessaging.instance.getInitialMessage();
    if (initialMessage != null) _queueOrOpen(initialMessage);
  }

  Future<void> authenticated() async {
    _authenticated = true;
    await start();
    await _ref.read(pushDeviceServiceProvider).synchronizeCurrentDevice();

    final pending = _pendingMessage;
    if (pending != null) {
      _pendingMessage = null;
      await _open(pending);
    }
  }

  void loggedOut() {
    _authenticated = false;
  }

  Future<void> dispose() async {
    await _foregroundSubscription?.cancel();
    await _openedSubscription?.cancel();
    await _tokenSubscription?.cancel();
  }

  void _queueOrOpen(RemoteMessage message) {
    if (!_authenticated) {
      _pendingMessage = message;
      return;
    }

    unawaited(_open(message));
  }

  Future<void> _handleTokenRefresh(String token) async {
    if (!_authenticated) return;

    try {
      await _ref.read(pushDeviceServiceProvider).synchronizeToken(token);
    } catch (_) {
      // Akan dicoba lagi ketika aplikasi dibuka atau token diperbarui berikutnya.
    }
  }

  void _handleForegroundMessage(RemoteMessage message) {
    _ref.invalidate(homeControllerProvider);

    final title = message.notification?.title ?? 'Notifikasi NUSA';
    final body = message.notification?.body;
    final destination = _destination(message);
    final messenger = nusaScaffoldMessengerKey.currentState;

    messenger
      ?..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(body == null || body.isEmpty ? title : '$title\n$body'),
          action: SnackBarAction(
            label: 'Buka',
            onPressed: () => unawaited(_openDestination(message, destination)),
          ),
        ),
      );
  }

  Future<void> _open(RemoteMessage message) {
    return _openDestination(message, _destination(message));
  }

  Future<void> _openDestination(
    RemoteMessage message,
    String destination,
  ) async {
    final notificationId = int.tryParse(message.data['notifikasi_id'] ?? '');

    if (notificationId != null) {
      try {
        await _ref
            .read(homeRepositoryProvider)
            .markNotificationRead(notificationId);
      } catch (_) {
        // Navigasi tetap dilanjutkan; status baca dapat disinkronkan kemudian.
      }
    }

    _ref.invalidate(homeControllerProvider);

    try {
      _ref.read(appRouterProvider).push(destination);
    } catch (_) {
      _ref.read(appRouterProvider).go('/beranda?tab=notifikasi');
    }
  }

  String _destination(RemoteMessage message) {
    final destination = message.data['tujuan']?.trim();

    if (destination == null ||
        destination.isEmpty ||
        !destination.startsWith('/') ||
        destination.startsWith('//')) {
      return '/beranda?tab=notifikasi';
    }

    return destination;
  }
}

final pushNotificationCoordinatorProvider =
    Provider<PushNotificationCoordinator>((ref) {
      final coordinator = PushNotificationCoordinator(ref);
      ref.onDispose(() => unawaited(coordinator.dispose()));
      return coordinator;
    });
