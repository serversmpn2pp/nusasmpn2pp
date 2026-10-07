import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class ExamSecurityPlatform {
  const ExamSecurityPlatform();

  static const _channel = MethodChannel(
    'id.sch.smpn2padangpanjang.nusa/exam_security',
  );

  Future<void> enter({
    required bool secureScreen,
    required bool fullscreen,
  }) async {
    await _setSecureScreen(secureScreen, active: true);
    if (fullscreen) {
      await SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    }
  }

  Future<void> leave() async {
    await _setSecureScreen(false, active: false);
    await SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  }

  Future<void> _setSecureScreen(bool enabled, {required bool active}) async {
    try {
      await _channel.invokeMethod<void>('setSecureScreen', {
        'enabled': enabled,
        'active': active,
      });
    } on MissingPluginException {
      // Widget test dan platform non-Android tidak menyediakan kanal ini.
    }
  }

  Future<bool> isMultiWindow() async {
    try {
      return await _channel.invokeMethod<bool>('isMultiWindow') ?? false;
    } on MissingPluginException {
      return false;
    }
  }
}

final examSecurityPlatformProvider = Provider<ExamSecurityPlatform>(
  (ref) => const ExamSecurityPlatform(),
);
