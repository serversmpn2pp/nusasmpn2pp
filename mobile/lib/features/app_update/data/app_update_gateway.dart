import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

abstract interface class AppUpdateGateway {
  Future<int?> availableVersion();
  Future<bool> openStore();
}

class PlayAppUpdateGateway implements AppUpdateGateway {
  static const channel = MethodChannel(
    'id.sch.smpn2padangpanjang.nusa/app_updates',
  );

  @override
  Future<int?> availableVersion() async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) return null;
    try {
      final version = await channel.invokeMethod<int>('checkForUpdate');
      return version != null && version > 0 ? version : null;
    } catch (_) {
      return null;
    }
  }

  @override
  Future<bool> openStore() async {
    try {
      return await channel.invokeMethod<bool>('openPlayStore') ?? false;
    } catch (_) {
      return false;
    }
  }
}

final appUpdateGatewayProvider = Provider<AppUpdateGateway>(
  (ref) => PlayAppUpdateGateway(),
);
