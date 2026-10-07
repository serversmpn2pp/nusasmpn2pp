import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/app/app_keys.dart';
import 'package:nusa/app/router.dart';
import 'package:nusa/features/home/application/home_controller.dart';
import 'package:nusa/features/push_notifications/application/push_device_service.dart';
import 'package:nusa/features/push_notifications/data/push_notification_remote_data_source.dart';

/// Platform/UI boundary: tests do not need a live Firebase project.
abstract interface class PushNotificationRuntime {
  bool get available;
  Stream<RemoteMessage> get foregroundMessages;
  Stream<RemoteMessage> get openedMessages;
  Stream<String> get tokenRefreshes;
  Future<RemoteMessage?> initialMessage();
  Future<void> synchronizeDevice();
  Future<void> synchronizeToken(String token);
  Future<void> clearDisplayedNotifications();
  void hideForegroundNotification();
  void showNotification(
    PushNotificationTarget target,
    Future<void> Function() onOpen,
  );
  void refreshInbox();
  void openDestination(String destination);
}

final class NusaPushNotificationRuntime implements PushNotificationRuntime {
  NusaPushNotificationRuntime(this._ref);
  final Ref _ref;
  static const _channel = MethodChannel(
    'id.sch.smpn2padangpanjang.nusa/push_notifications',
  );

  @override
  bool get available => _ref.read(pushDeviceServiceProvider).available;
  @override
  Stream<RemoteMessage> get foregroundMessages => FirebaseMessaging.onMessage;
  @override
  Stream<RemoteMessage> get openedMessages =>
      FirebaseMessaging.onMessageOpenedApp;
  @override
  Stream<String> get tokenRefreshes =>
      FirebaseMessaging.instance.onTokenRefresh;
  @override
  Future<RemoteMessage?> initialMessage() =>
      FirebaseMessaging.instance.getInitialMessage();
  @override
  Future<void> synchronizeDevice() =>
      _ref.read(pushDeviceServiceProvider).synchronizeCurrentDevice();
  @override
  Future<void> synchronizeToken(String token) =>
      _ref.read(pushDeviceServiceProvider).synchronizeToken(token);

  @override
  Future<void> clearDisplayedNotifications() async {
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      await _channel.invokeMethod<void>('clearNotifications');
    }
  }

  @override
  void hideForegroundNotification() {
    nusaScaffoldMessengerKey.currentState?.hideCurrentSnackBar();
  }

  @override
  void showNotification(
    PushNotificationTarget target,
    Future<void> Function() onOpen,
  ) {
    // Only the authenticated API supplies text; never render raw FCM title/body.
    final title = target.title?.trim();
    final body = target.body?.trim();
    nusaScaffoldMessengerKey.currentState
      ?..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (title != null && title.isNotEmpty)
                Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              Text(
                body == null || body.isEmpty
                    ? 'Ada pembaruan di NUSA. Buka aplikasi untuk melihat detail.'
                    : body,
                maxLines: 4,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
          action: SnackBarAction(
            label: 'Buka',
            onPressed: () => unawaited(onOpen()),
          ),
        ),
      );
  }

  @override
  void refreshInbox() => _ref.invalidate(homeControllerProvider);
  @override
  void openDestination(String destination) => unawaited(_navigate(destination));

  Future<void> _navigate(String destination) async {
    try {
      await _ref.read(appRouterProvider).push<void>(destination);
    } catch (_) {
      // Keep the current page; never fall back to an unverified payload route.
    }
  }
}

final pushNotificationRuntimeProvider = Provider<PushNotificationRuntime>(
  NusaPushNotificationRuntime.new,
);
