import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/features/student_exam/data/exam_security_platform.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('id.sch.smpn2padangpanjang.nusa/exam_security');
  const platform = ExamSecurityPlatform();
  final messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  Map<String, dynamic> status({
    int api = 36,
    bool secure = true,
    bool overlay = true,
  }) => {
    'protocol': 1,
    'apiLevel': api,
    'active': true,
    'secureScreen': secure,
    'overlaysHidden': overlay,
    'multiWindow': false,
  };
  tearDown(() => messenger.setMockMethodCallHandler(channel, null));

  test(
    'pemasangan dan pemeriksaan native mengonfirmasi status proteksi',
    () async {
      final calls = <String>[];
      messenger.setMockMethodCallHandler(channel, (call) async {
        calls.add(call.method);
        return status();
      });
      await platform.enter(secureScreen: true, fullscreen: false);
      final report = await platform.inspect();
      expect(report.readyFor(requireSecureScreen: true), isTrue);
      expect(calls, ['setSecureScreen', 'getSecurityStatus']);
    },
  );
  test('MissingPlugin tidak dianggap sukses saat masuk ujian', () async {
    await expectLater(
      platform.enter(secureScreen: true, fullscreen: false),
      throwsA(isA<MissingPluginException>()),
    );
  });
  test('respons void dari kanal lama ditolak', () async {
    messenger.setMockMethodCallHandler(channel, (call) async => null);
    await expectLater(
      platform.enter(secureScreen: true, fullscreen: false),
      throwsA(isA<PlatformException>()),
    );
  });
  test(
    'flag secure yang tidak aktif ditolak meskipun pemanggilan kanal sukses',
    () async {
      messenger.setMockMethodCallHandler(
        channel,
        (call) async => status(secure: false),
      );
      await expectLater(
        platform.enter(secureScreen: true, fullscreen: false),
        throwsA(isA<PlatformException>()),
      );
    },
  );
  test('overlay Android 12 ke atas wajib terpasang', () async {
    messenger.setMockMethodCallHandler(
      channel,
      (call) async => status(overlay: false),
    );
    await expectLater(
      platform.enter(secureScreen: true, fullscreen: false),
      throwsA(isA<PlatformException>()),
    );
  });
  test(
    'Android lama tetap memverifikasi secure tanpa mengklaim dukungan overlay',
    () async {
      messenger.setMockMethodCallHandler(
        channel,
        (call) async => status(api: 30, overlay: false),
      );
      await platform.enter(secureScreen: true, fullscreen: false);
      expect((await platform.inspect()).apiLevel, 30);
    },
  );
  test('status malformed atau tidak dikenal ditolak', () {
    for (final invalid in [
      <String, dynamic>{},
      {...status(), 'protocol': 0},
      {...status(), 'secureScreen': 'true'},
    ]) {
      expect(
        () => ExamScreenStatus.fromNative(invalid),
        throwsA(isA<PlatformException>()),
      );
    }
  });
}
