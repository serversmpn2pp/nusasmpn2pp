import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class ExamScreenStatus {
  const ExamScreenStatus({
    required this.apiLevel,
    required this.active,
    required this.secureScreen,
    required this.overlaysHidden,
    required this.multiWindow,
  });

  factory ExamScreenStatus.fromNative(Map<String, dynamic>? value) {
    if (value == null ||
        value['protocol'] != 1 ||
        value['apiLevel'] is! int ||
        (value['apiLevel'] as int) < 24 ||
        value['active'] is! bool ||
        value['secureScreen'] is! bool ||
        value['overlaysHidden'] is! bool ||
        value['multiWindow'] is! bool) {
      throw PlatformException(
        code: 'SCREEN_STATUS_UNAVAILABLE',
        message: 'Status proteksi layar tidak dapat dipastikan.',
      );
    }
    return ExamScreenStatus(
      apiLevel: value['apiLevel'] as int,
      active: value['active'] as bool,
      secureScreen: value['secureScreen'] as bool,
      overlaysHidden: value['overlaysHidden'] as bool,
      multiWindow: value['multiWindow'] as bool,
    );
  }

  final int apiLevel;
  final bool active;
  final bool secureScreen;
  final bool overlaysHidden;
  final bool multiWindow;

  bool readyFor({required bool requireSecureScreen}) =>
      active &&
      (!requireSecureScreen || secureScreen) &&
      (apiLevel < 31 || overlaysHidden);
}

class ExamSecurityPlatform {
  const ExamSecurityPlatform();

  static const _channel = MethodChannel(
    'id.sch.smpn2padangpanjang.nusa/exam_security',
  );

  Future<void> enter({
    required bool secureScreen,
    required bool fullscreen,
  }) async {
    final report = await _setSecureScreen(secureScreen, active: true);
    if (!report.readyFor(requireSecureScreen: secureScreen)) {
      throw PlatformException(
        code: 'SCREEN_PROTECTION_INACTIVE',
        message: 'Proteksi layar belum aktif.',
      );
    }
    if (fullscreen) {
      await SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    }
  }

  Future<void> leave() async {
    try {
      await _setSecureScreen(false, active: false);
    } on MissingPluginException {
      // Cleanup may run outside Android; entering a protected exam never ignores this.
    }
    await SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  }

  Future<ExamScreenStatus> _setSecureScreen(
    bool enabled, {
    required bool active,
  }) async {
    return ExamScreenStatus.fromNative(
      await _channel.invokeMapMethod<String, dynamic>('setSecureScreen', {
        'enabled': enabled,
        'active': active,
      }),
    );
  }

  Future<ExamScreenStatus> inspect() async => ExamScreenStatus.fromNative(
    await _channel.invokeMapMethod<String, dynamic>('getSecurityStatus'),
  );

  Future<bool> isMultiWindow() async => (await inspect()).multiWindow;
}

final examSecurityPlatformProvider = Provider<ExamSecurityPlatform>(
  (ref) => const ExamSecurityPlatform(),
);
