import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/app/router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/domain/auth_session.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/student_exam/application/exam_navigation_guard.dart';
import 'package:nusa/features/student_exam/application/student_exam_controller.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

void main() {
  test('guard hanya dapat dilepas pemilik yang benar', () {
    final guard = ExamNavigationGuard();
    final oldOwner = Object();
    final newOwner = Object();
    guard.protect(31, oldOwner);
    guard.protect(32, newOwner);
    guard.release(oldOwner);
    expect(guard.active, isTrue);
    expect(guard.allows('/ujian-saya/32'), isTrue);
    for (final path in [
      '/ujian-saya/31',
      '/ujian-saya/32/lain',
      '/menu',
      '/',
    ]) {
      expect(guard.allows(path), isFalse);
    }
    guard.release(newOwner);
    expect(guard.allows('/menu'), isTrue);
  });

  testWidgets(
    'router aplikasi sebenarnya menolak go dan push selama guard aktif',
    (tester) async {
      final container = ProviderContainer(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.fromEnvironment()),
          authControllerProvider.overrideWith(_StudentAuth.new),
          splashGateProvider.overrideWith((ref) async {}),
          studentExamProvider(31)
              .overrideWith((ref) async => _confirmation(31)),
          studentExamProvider(32)
              .overrideWith((ref) async => _confirmation(32)),
        ],
      );
      addTearDown(container.dispose);
      await container.read(authControllerProvider.future);
      await container.read(splashGateProvider.future);
      final router = container.read(appRouterProvider);
      router.go('/ujian-saya/31');
      await tester.pumpWidget(
        UncontrolledProviderScope(
          container: container,
          child: MaterialApp.router(
            theme: AppTheme.light,
            routerConfig: router,
          ),
        ),
      );
      await tester.pumpAndSettle();
      final guard = container.read(examNavigationGuardProvider);
      final owner = Object();
      guard.protect(31, owner);
      router.go('/ujian-saya/32');
      await tester.pumpAndSettle();
      router.push<void>('/ujian-saya/32');
      await tester.pumpAndSettle();
      expect(router.routeInformationProvider.value.uri.path, '/ujian-saya/31');
      expect(find.text('Ujian dummy 32'), findsNothing);
      guard.release(owner);
      router.go('/ujian-saya/32');
      await tester.pumpAndSettle();
      expect(router.routeInformationProvider.value.uri.path, '/ujian-saya/32');
      expect(find.text('Ujian dummy 32'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
}

StudentExamSession _confirmation(int id) => StudentExamSession.fromJson({
  'mode': 'konfirmasi',
  'peserta': {'id': id, 'nama': 'Siswa Dummy'},
  'ujian': {'id': id, 'nama': 'Ujian dummy $id'},
});

class _StudentAuth extends AuthController {
  @override
  Future<AuthState> build() async => const AuthState(
    session: AuthSession(
      token: 'synthetic-test-token',
      pengguna: Pengguna(
        id: 1,
        nama: 'Siswa Dummy',
        username: 'siswa.dummy',
        jenisAkun: 'siswa',
        administrator: false,
        wajibGantiKataSandi: false,
        peran: ['siswa'],
        izin: [],
      ),
    ),
  );
}
