import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/push_notifications/application/push_notification_runtime.dart';
import 'package:nusa/features/push_notifications/data/push_notification_remote_data_source.dart';

final class PushNotificationCoordinator {
  PushNotificationCoordinator(this._runtime, this._remote);

  final PushNotificationRuntime _runtime;
  final PushNotificationRemoteDataSource _remote;
  StreamSubscription<RemoteMessage>? _foregroundSubscription;
  StreamSubscription<RemoteMessage>? _openedSubscription;
  StreamSubscription<String>? _tokenSubscription;
  Future<void>? _startFuture;
  RemoteMessage? _pendingMessage;
  int? _userId;
  int _sessionVersion = 0;
  bool _disposed = false;
  final _opening = <(int, int)>{};

  Future<void> start() => _startFuture ??= _start();

  Future<void> _start() async {
    if (!_runtime.available || _disposed) return;
    _foregroundSubscription = _runtime.foregroundMessages.listen(
      (message) => unawaited(_handleForegroundMessage(message)),
    );
    _openedSubscription = _runtime.openedMessages.listen(_queueOrOpen);
    _tokenSubscription = _runtime.tokenRefreshes.listen(
      (token) => unawaited(_handleTokenRefresh(token)),
    );
    try {
      final message = await _runtime.initialMessage();
      if (!_disposed && message != null) _queueOrOpen(message);
    } catch (_) {
      // No unverified navigation if Firebase cannot provide its initial message.
    }
  }

  Future<void> authenticated(int userId) async {
    if (_disposed || userId <= 0) return;
    if (_userId != userId) {
      if (_userId != null) {
        _pendingMessage = null;
        _clearNotifications();
      }
      _userId = userId;
      _sessionVersion++;
    }
    final version = _sessionVersion;
    await start();
    if (!_current(version)) return;
    try {
      await _runtime.synchronizeDevice();
    } catch (_) {
      // Registration failure does not replace server ownership verification.
    }
    if (!_current(version)) return;
    final pending = _pendingMessage;
    _pendingMessage = null;
    if (pending != null) await _open(pending);
  }

  void loggedOut() {
    _userId = null;
    _pendingMessage = null;
    _sessionVersion++;
    _clearNotifications();
  }

  void _clearNotifications() {
    _runtime.hideForegroundNotification();
    if (_runtime.available) unawaited(_clearDisplayedNotifications());
  }

  Future<void> _clearDisplayedNotifications() async {
    try {
      await _runtime.clearDisplayedNotifications();
    } catch (_) {
      // Offline/older Android builds must still be able to log out safely.
    }
  }

  Future<void> dispose() async {
    _disposed = true;
    _pendingMessage = null;
    _userId = null;
    _sessionVersion++;
    await _foregroundSubscription?.cancel();
    await _openedSubscription?.cancel();
    await _tokenSubscription?.cancel();
  }

  bool _current(int version) =>
      !_disposed && _userId != null && _sessionVersion == version;

  void _queueOrOpen(RemoteMessage message) {
    if (_disposed) return;
    if (_userId == null) {
      // Cold start is deferred, but ownership will be checked by the API.
      _pendingMessage = message;
      return;
    }
    unawaited(_open(message));
  }

  Future<void> _handleTokenRefresh(String token) async {
    if (_userId == null || _disposed) return;
    try {
      await _runtime.synchronizeToken(token);
    } catch (_) {
      // Retry through the existing device registration flow.
    }
  }

  int? _notificationId(RemoteMessage message) =>
      int.tryParse(message.data['notifikasi_id']?.toString() ?? '');

  Future<PushNotificationTarget?> _verify(
    RemoteMessage message,
    int version,
  ) async {
    if (!_current(version)) return null;
    final id = _notificationId(message);
    if (id == null || id <= 0) return null;
    try {
      final target = await _remote.inspectNotification(id);
      return _current(version) && target.id == id ? target : null;
    } catch (_) {
      // Fail closed for 401/403/404/428, offline, and malformed responses.
      return null;
    }
  }

  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    final version = _sessionVersion;
    if (await _verify(message, version) == null || !_current(version)) return;
    _runtime.refreshInbox();
    _runtime.showNotification(() async {
      if (_current(version)) await _open(message);
    });
  }

  Future<void> _open(RemoteMessage message) async {
    final version = _sessionVersion;
    final id = _notificationId(message);
    if (id == null || !_current(version)) return;
    final key = (version, id);
    if (!_opening.add(key)) return;
    try {
      final target = await _verify(message, version);
      if (target == null || !_current(version)) return;
      await _remote.markNotificationRead(target.id);
      if (!_current(version)) return;
      _runtime.refreshInbox();
      // The server's verified route wins; payload 'tujuan' is never trusted.
      _runtime.openDestination(_destination(target.destination));
    } catch (_) {
      // A rejected read must not continue to navigation or mark another account.
    } finally {
      _opening.remove(key);
    }
  }

  String _destination(String? value) {
    final destination = value?.trim();
    final uri = Uri.tryParse(destination ?? '');
    if (destination == null ||
        !destination.startsWith('/') ||
        destination.startsWith('//') ||
        destination.contains('\\') ||
        uri == null ||
        uri.hasScheme ||
        uri.hasAuthority) {
      return '/beranda?tab=notifikasi';
    }
    return destination;
  }
}

final pushNotificationCoordinatorProvider =
    Provider<PushNotificationCoordinator>((ref) {
      final coordinator = PushNotificationCoordinator(
        ref.read(pushNotificationRuntimeProvider),
        ref.read(pushNotificationRemoteDataSourceProvider),
      );
      ref.onDispose(() => unawaited(coordinator.dispose()));
      return coordinator;
    });
