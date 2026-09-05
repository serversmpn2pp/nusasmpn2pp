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
    await _setSecureScreen(secureScreen);
    if (fullscreen) {
      await SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    }
  }

  Future<void> leave() async {
    await _setSecureScreen(false);
    await SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  }

  Future<void> _setSecureScreen(bool enabled) async {
    try {
      await _channel.invokeMethod<void>('setSecureScreen', {
        'enabled': enabled,
      });
    } on MissingPluginException {
      // Widget test dan platform non-Android tidak menyediakan kanal ini.
    }
  }
}

final examSecurityPlatformProvider = Provider<ExamSecurityPlatform>(
  (ref) => const ExamSecurityPlatform(),
);
