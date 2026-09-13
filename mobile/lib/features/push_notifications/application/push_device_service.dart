import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/storage/device_identity.dart';
import 'package:nusa/features/push_notifications/data/push_notification_remote_data_source.dart';

final firebaseMessagingAvailableProvider = Provider<bool>((ref) => false);

final class PushDeviceService {
  PushDeviceService(
    this._firebaseAvailable,
    this._remote,
    this._deviceIdentity,
  );

  final bool _firebaseAvailable;
  final PushNotificationRemoteDataSource _remote;
  final DeviceIdentity _deviceIdentity;
  String? _currentToken;

  bool get available => _firebaseAvailable;

  Future<void> synchronizeCurrentDevice({bool requestPermission = true}) async {
    if (!_firebaseAvailable) return;

    try {
      if (requestPermission) {
        final settings = await FirebaseMessaging.instance.requestPermission(
          alert: true,
          badge: true,
          sound: true,
        );
        if (settings.authorizationStatus == AuthorizationStatus.denied) return;
      }

      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.trim().isEmpty) return;

      await synchronizeToken(token);
    } catch (error, stackTrace) {
      debugPrint('Sinkronisasi push notification dilewati: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  Future<void> synchronizeToken(String token) async {
    if (!_firebaseAvailable || token.trim().isEmpty) return;

    final platform = switch (defaultTargetPlatform) {
      TargetPlatform.iOS => 'ios',
      _ => 'android',
    };

    await _remote.registerDevice(
      token: token,
      platform: platform,
      deviceName: await _deviceIdentity.readName(),
    );
    _currentToken = token;
  }

  Future<void> unregisterCurrentDevice() async {
    if (!_firebaseAvailable) return;

    try {
      final token =
          _currentToken ?? await FirebaseMessaging.instance.getToken();
      if (token != null && token.trim().isNotEmpty) {
        await _remote.unregisterDevice(token);
      }
    } catch (error, stackTrace) {
      // Logout lokal tidak boleh tertahan saat FCM atau server sedang bermasalah.
      debugPrint('Penonaktifan push notification dilewati: $error');
      debugPrintStack(stackTrace: stackTrace);
    } finally {
      try {
        // Putuskan token lokal juga agar akun lama tidak menerima push bila
        // penonaktifan ke server gagal karena perangkat sedang offline.
        await FirebaseMessaging.instance.deleteToken();
      } catch (_) {
        // Tidak memblokir logout saat layanan Firebase tidak tersedia.
      }
      _currentToken = null;
    }
  }
}

final pushDeviceServiceProvider = Provider<PushDeviceService>((ref) {
  return PushDeviceService(
    ref.watch(firebaseMessagingAvailableProvider),
    ref.watch(pushNotificationRemoteDataSourceProvider),
    ref.watch(deviceIdentityProvider),
  );
});
