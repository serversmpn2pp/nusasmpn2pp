import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/features/student_exam/application/exam_navigation_guard.dart';
import 'package:nusa/core/storage/device_identity.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_exam/data/student_exam_remote_data_source.dart';
import 'package:nusa/features/student_exam/data/student_exam_file_picker.dart';
import 'package:nusa/features/student_exam/data/exam_security_journal.dart';
import 'package:nusa/features/student_exam/data/exam_answer_draft_store.dart';
import 'package:nusa/features/student_exam/data/exam_security_platform.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_view.dart';

Future<void> _openSecurityExam(
  WidgetTester tester,
  _SecurityRemote remote,
  _JournalMemoryStorage storage, {
  StudentExamFilePicker? picker,
  ExamSecurityPlatform? platform,
  GoRouter? router,
  ExamNavigationGuard? navigationGuard,
  ExamAnswerDraftStore? drafts,
}) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        studentExamRemoteDataSourceProvider.overrideWithValue(remote),
        examAnswerDraftStoreProvider.overrideWithValue(
          drafts ??
              ExamAnswerDraftStore(
                _JournalMemoryStorage(),
                () async => 'test-session',
              ),
        ),
        if (navigationGuard != null)
          examNavigationGuardProvider.overrideWithValue(navigationGuard),
        deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
        examSecurityJournalProvider.overrideWithValue(
          ExamSecurityJournal(storage, () async => 'test-session'),
        ),
        examSecurityPlatformProvider.overrideWithValue(
          platform ?? _FakeExamPlatform(),
        ),
        if (picker != null)
          studentExamFilePickerProvider.overrideWithValue(picker),
      ],
      child: router != null
          ? MaterialApp.router(theme: AppTheme.light, routerConfig: router)
          : MaterialApp(
              theme: AppTheme.light,
              home: const StudentExamView(participantId: 31),
            ),
    ),
  );
  await tester.pumpAndSettle();
  await tester.enterText(find.byKey(const Key('student-exam-token')), 'MULAI1');
  await tester.ensureVisible(find.byKey(const Key('student-exam-open')));
  await tester.tap(find.byKey(const Key('student-exam-open')));
  await tester.pumpAndSettle();
}

class _SecurityRemote extends _FakeStudentExamRemoteDataSource {
  _SecurityRemote({super.upload, this.lockOnLeave = true});
  final bool lockOnLeave;
  bool serverLocked = false;
  bool answerOffline = false;
  bool offline = false;
  bool completed = false;
  bool holdRecovery = false;
  bool loseAnswerResponseOnce = false;
  Completer<void>? answerGate;
  final events = <(String, Map<String, dynamic>)>[];
  @override
  Future<StudentExamFilePermitResult> filePermit({
    required int participantId,
    required String device,
    int? questionId,
    String? requestId,
    String? permitId,
    String? outcome,
  }) async {
    final result = await super.filePermit(
      participantId: participantId,
      device: device,
      questionId: questionId,
      requestId: requestId,
      permitId: permitId,
      outcome: outcome,
    );
    if (outcome == 'masa_habis') serverLocked = true;
    return StudentExamFilePermitResult(
      accepted: result.accepted,
      id: result.id,
      questionId: result.questionId,
      remainingSeconds: result.remainingSeconds,
      update: StudentExamSecurityUpdate.fromJson({
        'mode': serverLocked ? 'ditahan' : 'pengerjaan',
        'keamanan': security,
      }),
    );
  }

  Map<String, dynamic> get security => {
    ..._securityJson(),
    'aktif': true,
    'catat_pindah_aplikasi': true,
    'tindakan': 'tahan',
    'ditahan': serverLocked,
    'alasan_tahanan': serverLocked ? 'keluar_pengerjaan' : null,
    'pesan_tahanan': serverLocked
        ? 'Sesi ditahan; hubungi pengawas. Jawaban tersimpan tetap ada.'
        : null,
  };
  @override
  Future<StudentExamSession> start({
    required int participantId,
    required String? token,
    required String device,
  }) async => (await super.start(
    participantId: participantId,
    token: token,
    device: device,
  )).copyWith(security: StudentExamSecurity.fromJson(security));
  @override
  Future<StudentExamSecurityUpdate> securityEvent({
    required int participantId,
    required String event,
    required String device,
    Map<String, dynamic> metadata = const {},
  }) async {
    events.add((event, metadata));
    if (offline) throw Exception('offline');
    if (event == 'keluar_pengerjaan' && lockOnLeave) serverLocked = true;
    if (holdRecovery && event == 'pemulihan') serverLocked = true;
    return StudentExamSecurityUpdate.fromJson({
      'mode': completed
          ? 'selesai'
          : serverLocked
          ? 'ditahan'
          : 'pengerjaan',
      'keamanan': security,
    });
  }

  @override
  Future<StudentExamSession> resume({
    required int participantId,
    required String device,
  }) async => completed
      ? StudentExamSession.fromJson(_completedJson())
      : (await super.resume(
          participantId: participantId,
          device: device,
        )).copyWith(
          mode: serverLocked ? 'ditahan' : 'pengerjaan',
          security: StudentExamSecurity.fromJson(security),
        );

  @override
  Future<StudentExamSession> detail(int participantId) async => serverLocked
      ? StudentExamSession.fromJson(_lockedJson())
            .copyWith(security: StudentExamSecurity.fromJson(security))
      : (await super.detail(participantId))
            .copyWith(security: StudentExamSecurity.fromJson(security));

  @override
  Future<StudentExamSaveResult> saveAnswer({
    required int participantId,
    required int questionId,
    required Object? answer,
    required bool doubtful,
    required String device,
    String? serverVersion,
  }) async {
    if (answerOffline) throw Exception('offline');
    if (answerGate != null) await answerGate!.future;
    final result = await super.saveAnswer(
      participantId: participantId,
      questionId: questionId,
      answer: answer,
      doubtful: doubtful,
      device: device,
      serverVersion: serverVersion,
    );
    if (loseAnswerResponseOnce) {
      loseAnswerResponseOnce = false;
      throw const NetworkException('Respons hilang setelah server menyimpan');
    }
    return result;
  }
}

GoRouter _securityRouter(ExamNavigationGuard guard) => GoRouter(
  initialLocation: '/ujian-saya/31',
  onEnter: (context, current, next, router) =>
      guard.allows(next.uri.path) ? const Allow() : const Block.stop(),
  routes: [
    GoRoute(
      path: '/',
      builder: (_, state) => const Scaffold(body: Text('Daftar ujian dummy')),
      routes: [
        GoRoute(
          path: 'ujian-saya/:id',
          builder: (_, state) => const StudentExamView(participantId: 31),
        ),
        GoRoute(
          path: 'tujuan-notifikasi',
          builder: (_, state) =>
              const Scaffold(body: Text('Tujuan notifikasi dummy')),
        ),
      ],
    ),
  ],
);

class _JournalMemoryStorage implements ExamJournalStorage {
  final values = <String, String>{};
  bool failing = false;
  @override
  Future<String?> read(String key) async {
    if (failing) throw StateError('Storage unavailable');
    return values[key];
  }

  @override
  Future<void> write(String key, String value) async {
    if (failing) throw StateError('Storage unavailable');
    values[key] = value;
  }
}

class _WaitingPicker implements StudentExamFilePicker {
  final result = Completer<StudentExamPickedFile?>();
  int calls = 0;
  @override
  Future<StudentExamPickedFile?> pick() {
    calls++;
    return result.future;
  }
}

Future<void> _tapUpload(WidgetTester tester) async {
  for (var i = 0; i < 2; i++) {
    await tester.tap(find.byKey(const Key('student-exam-next')));
    await tester.pumpAndSettle();
  }
  await tester.ensureVisible(find.byKey(const Key('student-exam-upload-104')));
  await tester.tap(find.byKey(const Key('student-exam-upload-104')));
  await tester.pumpAndSettle();
}

class _FakeExamPlatform extends ExamSecurityPlatform {
  bool multiWindow = false;
  bool fail = false;
  bool verified = true;
  int enterCalls = 0;
  Completer<void>? waiting;
  @override
  Future<void> enter({
    required bool secureScreen,
    required bool fullscreen,
  }) async {
    enterCalls++;
    if (waiting != null) await waiting!.future;
    if (fail) throw StateError('Proteksi native gagal');
  }

  @override
  Future<void> leave() async {}
  @override
  Future<bool> isMultiWindow() async => multiWindow;
  @override
  Future<ExamScreenStatus> inspect() async => ExamScreenStatus(
    apiLevel: 36,
    active: verified,
    secureScreen: verified,
    overlaysHidden: verified,
    multiWindow: multiWindow,
  );
}

void main() {
  setUp(
    () => FlutterSecureStorage.setMockInitialValues({
      'nusa.auth_token': 'test-draft-token',
    }),
  );
  testWidgets(
    'dua kehilangan fokus cepat tetap dicatat dua interval meskipun proteksi belum selesai',
    (tester) async {
      final remote = _SecurityRemote();
      final platform = _FakeExamPlatform();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        platform: platform,
      );
      final waiting = Completer<void>();
      platform.waiting = waiting;
      for (var i = 0; i < 2; i++) {
        tester.binding.handleAppLifecycleStateChanged(
          AppLifecycleState.inactive,
        );
        tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
        tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
        tester.binding.handleAppLifecycleStateChanged(
          AppLifecycleState.resumed,
        );
      }
      waiting.complete();
      await tester.pumpAndSettle();
      final events = remote.events
          .where((e) => e.$1 == 'keluar' || e.$1 == 'kembali')
          .toList();
      expect(events.map((e) => e.$1), [
        'keluar',
        'kembali',
        'keluar',
        'kembali',
      ]);
      expect(
        events
            .where((e) => e.$1 == 'keluar')
            .map((e) => e.$2['kejadian_id'])
            .toSet(),
        hasLength(2),
      );
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'draft bertahan sesudah halaman dihentikan dan baru dikirim setelah pengawas membuka',
    (tester) async {
      final remote = _SecurityRemote()..answerOffline = true;
      final journal = _JournalMemoryStorage();
      final memory = _JournalMemoryStorage();
      await _openSecurityExam(
        tester,
        remote,
        journal,
        drafts: ExamAnswerDraftStore(memory, () async => 'test-session'),
      );
      await tester.tap(find.text('Paru-paru'));
      await tester.pumpAndSettle();
      expect(find.text('Draft di HP'), findsWidgets);
      await tester.pumpWidget(const SizedBox());
      await tester.pump();
      remote.holdRecovery = true;
      await _openSecurityExam(
        tester,
        remote,
        journal,
        drafts: ExamAnswerDraftStore(memory, () async => 'test-session'),
      );
      expect(find.byKey(const Key('student-exam-locked')), findsOneWidget);
      expect(remote.savedQuestions, isEmpty);
      remote.serverLocked = false;
      remote.answerOffline = false;
      await tester.tap(find.byKey(const Key('student-exam-check-lock')));
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(remote.lastAnswer, ['A']);
      expect(remote.savedQuestions, [101]);
      expect(
        memory.values.values.any(
          (value) => value.contains('"answer":{"1":"A"}'),
        ),
        isFalse,
      );
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'respons autosave hilang tidak membuat draft menimpa server saat dibuka ulang',
    (tester) async {
      final remote = _SecurityRemote()..loseAnswerResponseOnce = true;
      final memory = _JournalMemoryStorage();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        drafts: ExamAnswerDraftStore(memory, () async => 'test-session'),
      );
      await tester.tap(find.text('Paru-paru'));
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(remote.savedQuestions, [101]);
      await tester.pumpWidget(const SizedBox());
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        drafts: ExamAnswerDraftStore(memory, () async => 'test-session'),
      );
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(remote.savedQuestions, [101]);
      expect(find.byKey(const Key('student-exam-draft-gate')), findsNothing);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'draft berbeda versi tidak dikirim otomatis sampai siswa memilih pemulihan',
    (tester) async {
      final remote = _SecurityRemote()..answerOffline = true;
      final memory = _JournalMemoryStorage();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        drafts: ExamAnswerDraftStore(memory, () async => 'test-session'),
      );
      await tester.tap(find.text('Paru-paru'));
      await tester.pumpAndSettle();
      await tester.pumpWidget(const SizedBox());
      remote.serverVersions[101] = 'changed-on-server';
      remote.serverAnswers[101] = {'1': 'B'};
      remote.answerOffline = false;
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        drafts: ExamAnswerDraftStore(memory, () async => 'test-session'),
      );
      expect(find.text('Jawaban server sudah berubah'), findsOneWidget);
      expect(remote.savedQuestions, isEmpty);
      await tester.tap(find.text('Pulihkan Draft HP'));
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(remote.lastAnswer, ['A']);
      expect(find.byKey(const Key('student-exam-draft-gate')), findsNothing);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'penyimpanan draft gagal tidak mengaku tersimpan dan dapat dipulihkan tanpa kehilangan masukan',
    (tester) async {
      final remote = _SecurityRemote();
      final memory = _JournalMemoryStorage();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        drafts: ExamAnswerDraftStore(memory, () async => 'test-session'),
      );
      memory.failing = true;
      await tester.tap(find.text('Paru-paru'));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('student-exam-draft-gate')), findsOneWidget);
      expect(remote.savedQuestions, isEmpty);
      memory.failing = false;
      await tester.tap(find.text('Periksa Penyimpanan Lagi'));
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(remote.lastAnswer, ['A']);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'ack autosave lama tidak menghapus jawaban yang berubah saat request berjalan',
    (tester) async {
      final remote = _SecurityRemote();
      final memory = _JournalMemoryStorage();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        drafts: ExamAnswerDraftStore(memory, () async => 'test-session'),
      );
      final gate = Completer<void>();
      remote.answerGate = gate;
      await tester.tap(find.text('Paru-paru'));
      await tester.pump(const Duration(seconds: 1));
      await tester.pump();
      await tester.tap(find.text('Lambung'));
      await tester.pump();
      remote.answerGate = null;
      gate.complete();
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(remote.savedQuestions, [101, 101]);
      expect(remote.lastAnswer, ['B']);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets('timeout proteksi tidak memulai ujian atau menampilkan soal', (
    tester,
  ) async {
    final remote = _SecurityRemote();
    final waiting = Completer<void>();
    final platform = _FakeExamPlatform()..waiting = waiting;
    await _openSecurityExam(
      tester,
      remote,
      _JournalMemoryStorage(),
      platform: platform,
    );
    expect(remote.startToken, isNull);
    expect(
      find.byKey(const Key('student-exam-protection-gate')),
      findsOneWidget,
    );
    expect(find.byType(CircularProgressIndicator), findsNothing);
    expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
    waiting.complete();
    await tester.pumpAndSettle();
    expect(remote.startToken, isNull);
    expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
    await tester.pumpWidget(const SizedBox());
  });

  test(
    'protokol izin berkas lama, hilang, atau sisa waktu tidak sah ditolak',
    () {
      final valid = <String, dynamic>{
        'protocol': 1,
        'diterima': true,
        'mode': 'pengerjaan',
        'keamanan': _securityJson(),
        'izin': {
          'id': '68dd745e-1e10-4bdf-8513-c7ff9a2f226c',
          'soal_ujian_cbt_id': 104,
          'sisa_detik': 60,
        },
      };
      expect(StudentExamFilePermitResult.fromJson(valid).remainingSeconds, 60);
      for (final invalid in [
        <String, dynamic>{},
        {...valid, 'protocol': 0},
        {...valid, 'izin': null},
        {...valid, 'keamanan': null},
        {
          ...valid,
          'izin': {...valid['izin'] as Map, 'sisa_detik': 999},
        },
      ]) {
        expect(
          () => StudentExamFilePermitResult.fromJson(invalid),
          throwsFormatException,
        );
      }
    },
  );

  testWidgets(
    'kembali ke aplikasi menyembunyikan soal sampai pemeriksaan native selesai',
    (tester) async {
      final remote = _SecurityRemote();
      final platform = _FakeExamPlatform();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        platform: platform,
      );
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      await tester.pump();
      platform.waiting = Completer<void>();
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pump();
      expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
      expect(
        find.byKey(const Key('student-exam-protection-gate')),
        findsOneWidget,
      );
      platform.waiting!.complete();
      await tester.pumpAndSettle();
      expect(find.text('Organ pernapasan manusia adalah ....'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'izin gagal tidak membuka pemilih dan retry memakai ID permintaan yang sama',
    (tester) async {
      final remote = _SecurityRemote(upload: true)..permitStartOffline = true;
      final picker = _WaitingPicker();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        picker: picker,
      );
      await _tapUpload(tester);
      expect(picker.calls, 0);
      expect(
        find.byKey(const Key('student-exam-file-permit-gate')),
        findsOneWidget,
      );
      final id = remote.permitRequests.first['request'];
      remote.permitStartOffline = false;
      await tester.tap(
        find.byKey(const Key('student-exam-reconcile-file-permit')),
      );
      await tester.pumpAndSettle();
      expect(remote.permitRequests[1]['request'], id);
      expect(remote.permitRequests.last['outcome'], 'dibatalkan');
      expect(picker.calls, 0);
      expect(
        find.byKey(const Key('student-exam-file-permit-gate')),
        findsNothing,
      );
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'izin tidak tertutup saat offline tetap menyembunyikan soal dan tidak mengunggah',
    (tester) async {
      final remote = _SecurityRemote(upload: true);
      final picker = _WaitingPicker();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        picker: picker,
      );
      await _tapUpload(tester);
      remote.permitCloseOffline = true;
      picker.result.complete(
        StudentExamPickedFile(name: 'jawaban.pdf', bytes: Uint8List(10)),
      );
      await tester.pumpAndSettle();
      expect(remote.uploadedFileName, isNull);
      expect(
        find.byKey(const Key('student-exam-file-permit-gate')),
        findsOneWidget,
      );
      final id = remote.permitRequests.last['permit'];
      remote.permitCloseOffline = false;
      await tester.tap(
        find.byKey(const Key('student-exam-reconcile-file-permit')),
      );
      await tester.pumpAndSettle();
      expect(remote.permitRequests.last['permit'], id);
      expect(
        find.byKey(const Key('student-exam-file-permit-gate')),
        findsNothing,
      );
      expect(remote.uploadedFileName, isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'picker yang terlalu lama dicabut izinnya tanpa membuka pemilih kedua atau mengunggah',
    (tester) async {
      final remote = _SecurityRemote(upload: true);
      final picker = _WaitingPicker();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        picker: picker,
      );
      await _tapUpload(tester);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      await tester.pump(const Duration(seconds: 60));
      await tester.pump();
      expect(remote.permitRequests.last['outcome'], 'masa_habis');
      expect(picker.calls, 1);
      expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
      picker.result.complete(
        StudentExamPickedFile(name: 'terlambat.pdf', bytes: Uint8List(10)),
      );
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pumpAndSettle();
      expect(remote.uploadedFileName, isNull);
      expect(remote.serverLocked, isTrue);
      expect(remote.events.any((e) => e.$1 == 'keluar'), isFalse);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'kegagalan proteksi ditutup sebelum permintaan mulai ujian dan dapat diperiksa ulang',
    (tester) async {
      final remote = _SecurityRemote();
      final platform = _FakeExamPlatform()..fail = true;
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        platform: platform,
      );
      expect(remote.startToken, isNull);
      expect(
        find.byKey(const Key('student-exam-protection-gate')),
        findsOneWidget,
      );
      expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
      expect(
        remote.events.where((e) => e.$2['pemicu'] == 'proteksi-layar-gagal'),
        isEmpty,
      );
      platform.fail = false;
      await tester.tap(find.byKey(const Key('student-exam-retry-protection')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('student-exam-open')));
      await tester.tap(find.byKey(const Key('student-exam-open')));
      await tester.pumpAndSettle();
      expect(find.text('Organ pernapasan manusia adalah ....'), findsOneWidget);
      expect(remote.startToken, 'MULAI1');
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets('kanal proteksi yang hilang tidak mengizinkan soal tampil', (
    tester,
  ) async {
    final remote = _SecurityRemote();
    await _openSecurityExam(
      tester,
      remote,
      _JournalMemoryStorage(),
      platform: const ExamSecurityPlatform(),
    );
    expect(remote.startToken, isNull);
    expect(
      find.byKey(const Key('student-exam-protection-gate')),
      findsOneWidget,
    );
    expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets(
    'status proteksi yang hilang saat ujian menyembunyikan soal tanpa menambah kejadian',
    (tester) async {
      final remote = _SecurityRemote();
      final platform = _FakeExamPlatform();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        platform: platform,
      );
      platform.verified = false;
      await tester.pump(const Duration(seconds: 3));
      await tester.pumpAndSettle();
      expect(
        find.byKey(const Key('student-exam-protection-gate')),
        findsOneWidget,
      );
      expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
      expect(
        remote.events.where((e) => e.$2['pemicu'] == 'proteksi-layar-gagal'),
        hasLength(1),
      );
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'Simpan & Keluar menunggu penahanan server dan membuka ulang hanya halaman ditahan',
    (tester) async {
      final remote = _SecurityRemote();
      final storage = _JournalMemoryStorage();
      final guard = ExamNavigationGuard();
      final router = _securityRouter(guard);
      addTearDown(router.dispose);
      await _openSecurityExam(
        tester,
        remote,
        storage,
        router: router,
        navigationGuard: guard,
      );
      expect(guard.active, isTrue);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Untuk melanjutkan, minta pengawas'),
        findsOneWidget,
      );
      await tester.tap(find.text('Simpan & Keluar'));
      await tester.pumpAndSettle();
      expect(remote.serverLocked, isTrue);
      expect(
        remote.events.where((e) => e.$1 == 'keluar_pengerjaan'),
        hasLength(1),
      );
      expect(find.text('Daftar ujian dummy'), findsOneWidget);
      expect(guard.active, isFalse);
      router.push<void>('/ujian-saya/31');
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('student-exam-locked')), findsOneWidget);
      expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'koneksi gagal saat keluar mempertahankan halaman dan UUID retry',
    (tester) async {
      final remote = _SecurityRemote();
      final guard = ExamNavigationGuard();
      final router = _securityRouter(guard);
      addTearDown(router.dispose);
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        router: router,
        navigationGuard: guard,
      );
      remote.offline = true;
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Simpan & Keluar'));
      await tester.pumpAndSettle();
      expect(find.text('Organ pernapasan manusia adalah ....'), findsOneWidget);
      expect(guard.active, isTrue);
      final first = remote.events.lastWhere((e) => e.$1 == 'keluar_pengerjaan');
      remote.offline = false;
      await tester.pump(const Duration(seconds: 15));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('student-exam-locked')), findsOneWidget);
      final retry = remote.events.lastWhere((e) => e.$1 == 'keluar_pengerjaan');
      expect(retry.$2['kejadian_id'], first.$2['kejadian_id']);
      expect(retry.$2['dikirim_ulang'], isTrue);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'server yang tidak mengonfirmasi tahan tidak mengizinkan keluar ujian ketat',
    (tester) async {
      final remote = _SecurityRemote(lockOnLeave: false);
      final guard = ExamNavigationGuard();
      final router = _securityRouter(guard);
      addTearDown(router.dispose);
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        router: router,
        navigationGuard: guard,
      );
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Simpan & Keluar'));
      await tester.pumpAndSettle();
      expect(find.text('Organ pernapasan manusia adalah ....'), findsOneWidget);
      expect(guard.active, isTrue);
      expect(find.textContaining('Server belum mengonfirmasi'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'navigasi internal go dan push notifikasi tidak menutup pengawasan',
    (tester) async {
      final remote = _SecurityRemote();
      final guard = ExamNavigationGuard();
      final router = _securityRouter(guard);
      addTearDown(router.dispose);
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        router: router,
        navigationGuard: guard,
      );
      router.go('/tujuan-notifikasi');
      await tester.pumpAndSettle();
      router.push<void>('/tujuan-notifikasi');
      await tester.pumpAndSettle();
      expect(find.text('Tujuan notifikasi dummy'), findsNothing);
      expect(find.text('Organ pernapasan manusia adalah ....'), findsOneWidget);
      expect(guard.active, isTrue);
      await tester.pump(const Duration(seconds: 15));
      await tester.pumpAndSettle();
      expect(
        remote.events.where((e) => e.$1 == 'heartbeat').length,
        greaterThanOrEqualTo(2),
      );
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'draft yang gagal terkirim dipertahankan selama ditahan lalu disimpan setelah dibuka',
    (tester) async {
      final remote = _SecurityRemote();
      await _openSecurityExam(tester, remote, _JournalMemoryStorage());
      remote.answerOffline = true;
      await tester.tap(find.text('Paru-paru'));
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      remote.serverLocked = true;
      await tester.pump(const Duration(seconds: 15));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('student-exam-locked')), findsOneWidget);
      remote.serverLocked = false;
      remote.answerOffline = false;
      await tester.tap(find.byKey(const Key('student-exam-check-lock')));
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(remote.lastAnswer, ['A']);
      expect(find.text('Organ pernapasan manusia adalah ....'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'status selesai dari server mengganti halaman meskipun timer lokal masih berjalan',
    (tester) async {
      final remote = _SecurityRemote();
      await _openSecurityExam(tester, remote, _JournalMemoryStorage());
      remote.completed = true;
      await tester.pump(const Duration(seconds: 15));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('student-exam-completed')), findsOneWidget);
      expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'kejadian offline dikirim ulang otomatis tanpa refresh halaman ujian',
    (tester) async {
      final remote = _SecurityRemote();
      final storage = _JournalMemoryStorage();
      await _openSecurityExam(tester, remote, storage);
      remote.offline = true;
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      await tester.pumpAndSettle();
      expect(
        find.byKey(const Key('student-exam-security-pending')),
        findsOneWidget,
      );
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pumpAndSettle();
      remote.offline = false;
      await tester.pump(const Duration(seconds: 15));
      await tester.pumpAndSettle();
      final exits = remote.events.where((e) => e.$1 == 'keluar').toList();
      expect(exits.length, greaterThanOrEqualTo(2));
      expect(exits.first.$2['kejadian_id'], exits.last.$2['kejadian_id']);
      expect(exits.last.$2['dikirim_ulang'], isTrue);
      expect(remote.events.any((e) => e.$1 == 'kembali'), isTrue);
      expect(
        find.byKey(const Key('student-exam-security-pending')),
        findsNothing,
      );
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'pemilih berkas sah dicatat mulai dan selesai tanpa pengecualian pemulihan buatan client',
    (tester) async {
      final remote = _SecurityRemote(upload: true);
      final picker = _WaitingPicker();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        picker: picker,
      );
      for (var i = 0; i < 2; i++) {
        await tester.tap(find.byKey(const Key('student-exam-next')));
        await tester.pumpAndSettle();
      }
      await tester.ensureVisible(
        find.byKey(const Key('student-exam-upload-104')),
      );
      await tester.tap(find.byKey(const Key('student-exam-upload-104')));
      await tester.pumpAndSettle();
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      await tester.pump();
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      picker.result.complete(null);
      await tester.pumpAndSettle();
      expect(remote.permitRequests, hasLength(2));
      expect(remote.permitRequests.first['question'], 104);
      expect(remote.permitRequests.last['outcome'], 'dibatalkan');
      expect(
        remote.events.any((e) => e.$2['pemicu'] == 'pemilih-berkas'),
        isFalse,
      );
      expect(remote.events.any((e) => e.$1 == 'keluar'), isFalse);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'layar terbagi menyembunyikan soal dan mengirim catatan peninjauan',
    (tester) async {
      final remote = _SecurityRemote();
      final platform = _FakeExamPlatform();
      await _openSecurityExam(
        tester,
        remote,
        _JournalMemoryStorage(),
        platform: platform,
      );
      platform.multiWindow = true;
      await tester.pump(const Duration(seconds: 3));
      await tester.pumpAndSettle();
      expect(find.text('Organ pernapasan manusia adalah ....'), findsNothing);
      expect(
        find.text('Tutup layar terbagi untuk melanjutkan ujian.'),
        findsOneWidget,
      );
      expect(
        remote.events.any((e) => e.$2['pemicu'] == 'layar-terbagi'),
        isTrue,
      );
      platform.multiWindow = false;
      await tester.pump(const Duration(seconds: 3));
      await tester.pumpAndSettle();
      expect(find.text('Organ pernapasan manusia adalah ....'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    },
  );
  test('domain ujian siswa membaca soal tanpa membutuhkan kunci jawaban', () {
    final session = StudentExamSession.fromJson(_runningJson());

    expect(session.isRunning, isTrue);
    expect(session.questions, hasLength(2));
    expect(session.questions.first.options.first.text, 'Paru-paru');
    expect(session.questions.last.type, 'uraian');
    expect(session.remainingSeconds, 1800);
  });

  test('label tampilan pilihan dapat berbeda dari kode jawaban internal', () {
    final option = StudentExamOption.fromJson({
      'kode': 'D',
      'label': 'A',
      'teks': 'Paru-paru',
    });

    expect(option.label, 'A');
    expect(option.code, 'D');
  });

  test('soal berbutir baru lengkap setelah semua baris dijawab', () {
    final matching = StudentExamSession.fromJson(
      _runningJson(withMatching: true),
    ).questions.last;

    expect(matching.copyWith(answer: {'1': 'Hertz'}).isAnswered, isFalse);
    expect(
      matching.copyWith(answer: {'1': 'Hertz', '2': 'Sekon'}).isAnswered,
      isTrue,
    );
  });

  testWidgets('siswa membuka, autosave, dan menyelesaikan ujian native', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final remote = _FakeStudentExamRemoteDataSource(
      remainingSeconds: 910,
      remainingSecondsAfterThirdSave: 900,
    );
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentExamRemoteDataSourceProvider.overrideWithValue(remote),
          deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
          appConfigProvider.overrideWithValue(AppConfig.fromEnvironment()),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentExamView(participantId: 31),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('student-exam-confirmation')), findsOneWidget);
    expect(find.text('Asesmen IPA Native'), findsOneWidget);

    await tester.enterText(
      find.byKey(const Key('student-exam-token')),
      'MULAI1',
    );
    await tester.drag(
      find.byKey(const Key('student-exam-confirmation')),
      const Offset(0, -250),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-exam-open')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 20));

    expect(remote.startToken, 'MULAI1');
    expect(find.text('Organ pernapasan manusia adalah ....'), findsOneWidget);
    expect(find.byKey(const Key('student-exam-timer')), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('Paru-paru'));
    await tester.pump(const Duration(milliseconds: 750));
    await tester.pump();
    expect(remote.savedQuestions, contains(101));
    expect(remote.lastAnswer, ['A']);
    expect(tester.takeException(), isNull);

    await tester.tap(find.byKey(const Key('student-exam-next')));
    await tester.pumpAndSettle();
    expect(find.text('Jelaskan proses pertukaran oksigen.'), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(find.byKey(const Key('student-exam-finish')))
          .onPressed,
      isNull,
    );
    expect(tester.takeException(), isNull);

    await tester.enterText(
      find.byKey(const Key('student-exam-answer-102')),
      'Pertukaran terjadi di alveolus.',
    );
    await tester.pump(const Duration(milliseconds: 750));
    await tester.pump();
    expect(remote.savedQuestions, contains(102));
    expect(
      tester
          .widget<FilledButton>(find.byKey(const Key('student-exam-finish')))
          .onPressed,
      isNull,
    );

    await tester.ensureVisible(find.text('Tandai ragu-ragu'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tandai ragu-ragu'));
    await tester.pump(const Duration(milliseconds: 750));
    await tester.pump();
    await tester.tap(find.text('Tandai ragu-ragu'));
    await tester.pump(const Duration(milliseconds: 750));
    await tester.pump();
    expect(
      tester
          .widget<FilledButton>(find.byKey(const Key('student-exam-finish')))
          .onPressed,
      isNotNull,
    );

    await tester.tap(find.byKey(const Key('student-exam-finish')));
    await tester.pumpAndSettle();
    expect(find.text('Kumpulkan ujian?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('student-exam-confirm-finish')));
    await tester.pumpAndSettle();

    expect(remote.finishCalls, 1);
    expect(find.byKey(const Key('student-exam-completed')), findsOneWidget);
    expect(find.text('Ujian telah selesai'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('ujian yang ditahan menunggu pengawas lalu dapat dilanjutkan', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final remote = _FakeStudentExamRemoteDataSource(locked: true);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentExamRemoteDataSourceProvider.overrideWithValue(remote),
          examSecurityPlatformProvider.overrideWithValue(_FakeExamPlatform()),
          appConfigProvider.overrideWithValue(AppConfig.fromEnvironment()),
          deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
          examSecurityJournalProvider.overrideWithValue(
            ExamSecurityJournal(
              _JournalMemoryStorage(),
              () async => 'locked-test',
            ),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentExamView(participantId: 31),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('student-exam-locked')), findsOneWidget);
    expect(find.text('Ujian sementara ditahan'), findsOneWidget);
    expect(find.textContaining('3 kejadian'), findsOneWidget);

    await tester.tap(find.byKey(const Key('student-exam-check-lock')));
    await tester.pumpAndSettle();

    expect(
      find.byKey(const Key('student-exam-question-pages')),
      findsOneWidget,
    );
    expect(find.textContaining('Ujian sudah dibuka'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('soal menjodohkan menampilkan pilihan pasangan', (tester) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final remote = _FakeStudentExamRemoteDataSource(matching: true);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentExamRemoteDataSourceProvider.overrideWithValue(remote),
          deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
          appConfigProvider.overrideWithValue(AppConfig.fromEnvironment()),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentExamView(participantId: 31),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const Key('student-exam-token')),
      'MULAI1',
    );
    await tester.drag(
      find.byKey(const Key('student-exam-confirmation')),
      const Offset(0, -250),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-exam-open')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-exam-next')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-exam-next')));
    await tester.pumpAndSettle();

    expect(find.text('Pilihan pasangan'), findsOneWidget);
    expect(find.text('A. Sekon'), findsOneWidget);
    expect(find.text('B. Hertz'), findsOneWidget);
    expect(find.text('C. Newton'), findsOneWidget);
    expect(find.textContaining('Frekuensi'), findsOneWidget);
    expect(find.textContaining('Periode'), findsOneWidget);

    final dropdown = find.byKey(const Key('student-exam-match-103-1'));
    await tester.ensureVisible(dropdown);
    await tester.tap(dropdown);
    await tester.pumpAndSettle();
    await tester.tap(find.text('B. Hertz').last);
    await tester.pump(const Duration(milliseconds: 750));

    expect(remote.lastAnswer, {'1': 'Hertz'});
    expect(tester.takeException(), isNull);
  });

  testWidgets('soal upload membuka pemilih dan menyimpan berkas', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final remote = _FakeStudentExamRemoteDataSource(upload: true);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentExamRemoteDataSourceProvider.overrideWithValue(remote),
          studentExamFilePickerProvider.overrideWithValue(
            _FakeStudentExamFilePicker(),
          ),
          appConfigProvider.overrideWithValue(AppConfig.fromEnvironment()),
          deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentExamView(participantId: 31),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const Key('student-exam-token')),
      'MULAI1',
    );
    await tester.drag(
      find.byKey(const Key('student-exam-confirmation')),
      const Offset(0, -250),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-exam-open')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-exam-next')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-exam-next')));
    await tester.pumpAndSettle();

    final uploadButton = find.byKey(const Key('student-exam-upload-104'));
    expect(uploadButton, findsOneWidget);
    await tester.ensureVisible(uploadButton);
    await tester.tap(uploadButton);
    await tester.pumpAndSettle();

    expect(find.text('laporan-siswa.pdf'), findsOneWidget);
    expect(remote.uploadedFileName, 'laporan-siswa.pdf');
    expect(tester.takeException(), isNull);
  });

  testWidgets('semua jenis soal memiliki masukan yang sesuai untuk siswa', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final remote = _FakeStudentExamRemoteDataSource(allTypes: true);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentExamRemoteDataSourceProvider.overrideWithValue(remote),
          deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
          appConfigProvider.overrideWithValue(AppConfig.fromEnvironment()),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentExamView(participantId: 31),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const Key('student-exam-token')),
      'MULAI1',
    );
    await tester.drag(
      find.byKey(const Key('student-exam-confirmation')),
      const Offset(0, -250),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-exam-open')));
    await tester.pumpAndSettle();

    Future<void> next() async {
      await tester.tap(find.byKey(const Key('student-exam-next')));
      await tester.pumpAndSettle();
    }

    expect(find.text('A'), findsOneWidget);
    expect(find.text('D'), findsNothing);
    await tester.tap(find.text('Paru-paru'));
    await tester.pump(const Duration(milliseconds: 750));
    expect(remote.lastAnswer, ['D']);

    await next();
    expect(find.text('Pilihan Ganda Kompleks'), findsOneWidget);
    expect(find.text('Jantung'), findsOneWidget);

    await next();
    expect(find.text('Benar'), findsOneWidget);
    expect(find.text('Salah'), findsOneWidget);

    await next();
    expect(find.text('Pilihan pasangan'), findsOneWidget);
    expect(find.byKey(const Key('student-exam-match-204-1')), findsOneWidget);

    await next();
    expect(find.byKey(const Key('student-exam-answer-205')), findsOneWidget);

    await next();
    expect(find.byKey(const Key('student-exam-answer-206')), findsOneWidget);

    await next();
    expect(find.byKey(const Key('student-exam-formula')), findsOneWidget);
    expect(find.text(r'\frac{1}{2}'), findsNothing);
    expect(find.text('Tabel nilai'), findsOneWidget);
    expect(find.byKey(const Key('student-exam-answer-207')), findsOneWidget);

    await next();
    expect(find.byKey(const Key('student-exam-upload-208')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _FakeDeviceIdentity implements DeviceIdentity {
  @override
  Future<String> readName() async => 'NUSA Android Test';
}

class _FakeStudentExamFilePicker implements StudentExamFilePicker {
  @override
  Future<StudentExamPickedFile?> pick() async => StudentExamPickedFile(
    name: 'laporan-siswa.pdf',
    bytes: Uint8List.fromList([1, 2, 3]),
  );
}

class _FakeStudentExamRemoteDataSource implements StudentExamRemoteDataSource {
  _FakeStudentExamRemoteDataSource({
    this.locked = false,
    this.matching = false,
    this.upload = false,
    this.allTypes = false,
    this.remainingSeconds = 1800,
    this.remainingSecondsAfterThirdSave,
  });

  final bool locked;
  final bool matching;
  final bool upload;
  final bool allTypes;
  final int remainingSeconds;
  final int? remainingSecondsAfterThirdSave;
  String? startToken;
  int finishCalls = 0;
  Object? lastAnswer;
  String? uploadedFileName;
  final List<int> savedQuestions = [];
  final serverAnswers = <int, Object?>{};
  final serverVersions = <int, String>{};
  final serverDoubtful = <int, bool>{};
  StudentExamSession fakeSession(Map<String, dynamic> json) =>
      StudentExamSession.fromJson({
        ...json,
        'peserta': {
          ...json['peserta'] as Map,
          'waktu_mulai': '2026-09-05T08:00:00+07:00',
        },
        'soal': [
          for (final q in json['soal'] as List? ?? [])
            {
              ...q as Map,
              'versi_jawaban': serverVersions[q['id']] ?? 'v0',
              if (serverAnswers.containsKey(q['id']))
                'jawaban': serverAnswers[q['id']],
              if (serverDoubtful.containsKey(q['id']))
                'ragu': serverDoubtful[q['id']],
            },
        ],
      });
  final permitRequests = <Map<String, Object?>>[];
  bool permitStartOffline = false;
  bool permitCloseOffline = false;
  @override
  Future<StudentExamFilePermitResult> filePermit({
    required int participantId,
    required String device,
    int? questionId,
    String? requestId,
    String? permitId,
    String? outcome,
  }) async {
    permitRequests.add({
      'question': questionId,
      'request': requestId,
      'permit': permitId,
      'outcome': outcome,
    });
    if ((permitId == null && permitStartOffline) ||
        (permitId != null && permitCloseOffline)) {
      throw Exception('offline');
    }
    return StudentExamFilePermitResult.fromJson({
      'protocol': 1,
      'diterima': outcome != 'masa_habis',
      'mode': 'pengerjaan',
      'keamanan': _securityJson(),
      'izin': {
        'id': permitId ?? '68dd745e-1e10-4bdf-8513-c7ff9a2f226c',
        'soal_ujian_cbt_id': questionId ?? 104,
        'sisa_detik': permitId == null ? 60 : 0,
      },
    });
  }

  @override
  Future<StudentExamSecurityUpdate> securityEvent({
    required int participantId,
    required String event,
    required String device,
    Map<String, dynamic> metadata = const {},
  }) async => StudentExamSecurityUpdate.fromJson({
    'mode': 'pengerjaan',
    'kejadian_dihitung': false,
    'durasi_kejadian_detik': 0,
    'keamanan': _securityJson(),
  });

  @override
  Future<StudentExamSession> detail(int participantId) async =>
      StudentExamSession.fromJson(locked ? _lockedJson() : _confirmationJson());

  @override
  Future<StudentExamSession> start({
    required int participantId,
    required String? token,
    required String device,
  }) async {
    startToken = token;
    return fakeSession(
      allTypes
          ? _allTypesRunningJson()
          : _runningJson(
              withMatching: matching,
              withUpload: upload,
              remainingSeconds: remainingSeconds,
            ),
    );
  }

  @override
  Future<StudentExamSession> resume({
    required int participantId,
    required String device,
  }) async => fakeSession(
    allTypes
        ? _allTypesRunningJson()
        : _runningJson(
            withMatching: matching,
            withUpload: upload,
            remainingSeconds: remainingSeconds,
          ),
  );

  @override
  Future<StudentExamSaveResult> saveAnswer({
    required int participantId,
    required int questionId,
    required Object? answer,
    required bool doubtful,
    required String device,
    String? serverVersion,
  }) async {
    if (serverVersion != null &&
        serverVersion != (serverVersions[questionId] ?? 'v0')) {
      throw const ValidationException(
        'Versi berubah',
        errors: {
          'versi_jawaban': ['Versi berubah'],
        },
      );
    }
    savedQuestions.add(questionId);
    lastAnswer = answer;
    serverAnswers[questionId] = answer is List
        ? {for (var i = 0; i < answer.length; i++) '${i + 1}': answer[i]}
        : answer;
    serverDoubtful[questionId] = doubtful;
    serverVersions[questionId] = 'v${savedQuestions.length}';
    return StudentExamSaveResult.fromJson({
      'versi_jawaban': serverVersions[questionId],
      'mode': 'tersimpan',
      'sisa_detik': savedQuestions.length >= 3
          ? remainingSecondsAfterThirdSave ?? remainingSeconds
          : remainingSeconds,
    });
  }

  @override
  Future<StudentExamFileSaveResult> uploadAnswerFile({
    required int participantId,
    required int questionId,
    required StudentExamPickedFile file,
    required bool doubtful,
    required String device,
  }) async {
    uploadedFileName = file.name;
    serverAnswers[questionId] = {'berkas': file.name};
    serverVersions[questionId] = 'file-v1';
    return StudentExamFileSaveResult.fromJson({
      'versi_jawaban': 'file-v1',
      'berkas': {'nama': file.name, 'ukuran_label': '1,0 KB'},
      'sisa_detik': 1780,
    });
  }

  @override
  Future<StudentExamSession> finish({
    required int participantId,
    required String device,
  }) async {
    finishCalls++;
    return StudentExamSession.fromJson(_completedJson());
  }
}

Map<String, dynamic> _confirmationJson() => {
  'mode': 'konfirmasi',
  'waktu_server': '2026-09-05T08:00:00+07:00',
  'peserta': _participantJson(status: 'aktif'),
  'ujian': _examJson(),
  'kemajuan': {'jumlah_soal': 2, 'terjawab': 0, 'belum_dijawab': 2, 'ragu': 0},
  'memerlukan_token': true,
  'dapat_dimulai': true,
};

Map<String, dynamic> _runningJson({
  bool withMatching = false,
  bool withUpload = false,
  int remainingSeconds = 1800,
}) => {
  'mode': 'pengerjaan',
  'waktu_server': '2026-09-05T08:00:00+07:00',
  'berakhir_pada': '2026-09-05T08:30:00+07:00',
  'sisa_detik': remainingSeconds,
  'peserta': _participantJson(status: 'sedang_mengerjakan'),
  'ujian': _examJson(),
  'kemajuan': {
    'jumlah_soal': withMatching || withUpload ? 3 : 2,
    'terjawab': 0,
    'belum_dijawab': withMatching || withUpload ? 3 : 2,
    'ragu': 0,
  },
  'soal': [
    {
      'id': 101,
      'nomor': 1,
      'jenis': 'pilihan_ganda',
      'label_jenis': 'Pilihan Ganda',
      'stimulus': null,
      'pertanyaan': 'Organ pernapasan manusia adalah ....',
      'media': {'gambar': null, 'tabel': null, 'rumus': null},
      'pilihan': [
        {'kode': 'A', 'teks': 'Paru-paru'},
        {'kode': 'B', 'teks': 'Lambung'},
      ],
      'pernyataan': [],
      'pasangan': [],
      'jawaban': {},
      'ragu': false,
    },
    {
      'id': 102,
      'nomor': 2,
      'jenis': 'uraian',
      'label_jenis': 'Uraian',
      'stimulus': null,
      'pertanyaan': 'Jelaskan proses pertukaran oksigen.',
      'media': {'gambar': null, 'tabel': null, 'rumus': null},
      'pilihan': [],
      'pernyataan': [],
      'pasangan': [],
      'jawaban': {},
      'ragu': false,
    },
    if (withMatching)
      {
        'id': 103,
        'nomor': 3,
        'jenis': 'menjodohkan',
        'label_jenis': 'Menjodohkan',
        'stimulus': null,
        'pertanyaan': 'Jodohkan besaran dengan satuannya.',
        'media': {'gambar': null, 'tabel': null, 'rumus': null},
        'pilihan': [
          {'kode': 'A', 'teks': 'Sekon'},
          {'kode': 'B', 'teks': 'Hertz'},
          {'kode': 'C', 'teks': 'Newton'},
        ],
        'pernyataan': [],
        'pasangan': [
          {'nomor': '1', 'kiri': 'Frekuensi'},
          {'nomor': '2', 'kiri': 'Periode'},
        ],
        'jawaban': {},
        'ragu': false,
      },
    if (withUpload)
      {
        'id': 104,
        'nomor': 3,
        'jenis': 'upload_file',
        'label_jenis': 'Upload File',
        'stimulus': null,
        'pertanyaan': 'Unggah laporan praktikum.',
        'media': {'gambar': null, 'tabel': null, 'rumus': null},
        'pilihan': [],
        'pernyataan': [],
        'pasangan': [],
        'jawaban': {},
        'berkas_jawaban': null,
        'ragu': false,
      },
  ],
  'keamanan': _securityJson(),
};

Map<String, dynamic> _allTypesRunningJson() => {
  'mode': 'pengerjaan',
  'waktu_server': '2026-09-05T08:00:00+07:00',
  'berakhir_pada': '2026-09-05T08:30:00+07:00',
  'sisa_detik': 1800,
  'peserta': _participantJson(status: 'sedang_mengerjakan'),
  'ujian': _examJson(),
  'kemajuan': {'jumlah_soal': 8, 'terjawab': 0, 'belum_dijawab': 8, 'ragu': 0},
  'soal': [
    {
      'id': 201,
      'nomor': 1,
      'jenis': 'pilihan_ganda',
      'label_jenis': 'Pilihan Ganda',
      'stimulus': null,
      'pertanyaan': 'Organ pernapasan manusia adalah ....',
      'media': {'gambar': null, 'tabel': null, 'rumus': null},
      'pilihan': [
        {'kode': 'D', 'label': 'A', 'teks': 'Paru-paru'},
        {'kode': 'B', 'label': 'B', 'teks': 'Lambung'},
      ],
      'pernyataan': [],
      'pasangan': [],
      'jawaban': {},
      'ragu': false,
    },
    {
      'id': 202,
      'nomor': 2,
      'jenis': 'pilihan_ganda_kompleks',
      'label_jenis': 'Pilihan Ganda Kompleks',
      'stimulus': null,
      'pertanyaan': 'Pilih organ dalam sistem peredaran darah.',
      'media': {'gambar': null, 'tabel': null, 'rumus': null},
      'pilihan': [
        {'kode': 'C', 'label': 'A', 'teks': 'Jantung'},
        {'kode': 'A', 'label': 'B', 'teks': 'Pembuluh darah'},
      ],
      'pernyataan': [],
      'pasangan': [],
      'jawaban': {},
      'ragu': false,
    },
    {
      'id': 203,
      'nomor': 3,
      'jenis': 'benar_salah',
      'label_jenis': 'Benar atau Salah',
      'stimulus': null,
      'pertanyaan': 'Tentukan nilai tiap pernyataan.',
      'media': {'gambar': null, 'tabel': null, 'rumus': null},
      'pilihan': [],
      'pernyataan': [
        {'nomor': '1', 'teks': 'Dua adalah bilangan genap.'},
      ],
      'pasangan': [],
      'jawaban': {},
      'ragu': false,
    },
    {
      'id': 204,
      'nomor': 4,
      'jenis': 'menjodohkan',
      'label_jenis': 'Menjodohkan',
      'stimulus': null,
      'pertanyaan': 'Jodohkan besaran dengan satuannya.',
      'media': {'gambar': null, 'tabel': null, 'rumus': null},
      'pilihan': [
        {'kode': 'B', 'label': 'A', 'teks': 'Hertz'},
        {'kode': 'A', 'label': 'B', 'teks': 'Sekon'},
      ],
      'pernyataan': [],
      'pasangan': [
        {'nomor': '1', 'kiri': 'Frekuensi'},
      ],
      'jawaban': {},
      'ragu': false,
    },
    {
      'id': 205,
      'nomor': 5,
      'jenis': 'isian_singkat',
      'label_jenis': 'Isian Singkat',
      'stimulus': null,
      'pertanyaan': 'Satuan frekuensi adalah ....',
      'media': {'gambar': null, 'tabel': null, 'rumus': null},
      'pilihan': [],
      'pernyataan': [],
      'pasangan': [],
      'jawaban': {},
      'ragu': false,
    },
    {
      'id': 206,
      'nomor': 6,
      'jenis': 'uraian',
      'label_jenis': 'Uraian',
      'stimulus': null,
      'pertanyaan': 'Jelaskan proses pertukaran oksigen.',
      'media': {'gambar': null, 'tabel': null, 'rumus': null},
      'pilihan': [],
      'pernyataan': [],
      'pasangan': [],
      'jawaban': {},
      'ragu': false,
    },
    {
      'id': 207,
      'nomor': 7,
      'jenis': 'numerik',
      'label_jenis': 'Numerik',
      'stimulus': null,
      'pertanyaan': 'Tuliskan hasil perhitungan.',
      'media': {
        'gambar': null,
        'tabel': {
          'judul': 'Tabel nilai',
          'baris': [
            ['Besaran', 'Nilai'],
            ['x', '2'],
          ],
        },
        'rumus': {
          'latex': r'\frac{1}{2}',
          'keterangan': 'Pecahan satu per dua',
        },
      },
      'pilihan': [],
      'pernyataan': [],
      'pasangan': [],
      'jawaban': {},
      'ragu': false,
    },
    {
      'id': 208,
      'nomor': 8,
      'jenis': 'upload_file',
      'label_jenis': 'Upload File',
      'stimulus': null,
      'pertanyaan': 'Unggah laporan praktikum.',
      'media': {'gambar': null, 'tabel': null, 'rumus': null},
      'pilihan': [],
      'pernyataan': [],
      'pasangan': [],
      'jawaban': {},
      'berkas_jawaban': null,
      'ragu': false,
    },
  ],
  'keamanan': _securityJson(),
};

Map<String, dynamic> _completedJson() => {
  'mode': 'selesai',
  'waktu_server': '2026-09-05T08:10:00+07:00',
  'peserta': _participantJson(status: 'selesai'),
  'ujian': _examJson(),
  'kemajuan': {'jumlah_soal': 2, 'terjawab': 2, 'belum_dijawab': 0, 'ragu': 0},
  'hasil': {
    'ditampilkan': false,
    'menunggu_koreksi': true,
    'nilai': null,
    'kkm': 75,
    'tuntas': null,
  },
};

Map<String, dynamic> _lockedJson() => {
  'mode': 'ditahan',
  'waktu_server': '2026-09-05T08:10:00+07:00',
  'berakhir_pada': '2026-09-05T08:25:00+07:00',
  'sisa_detik': 900,
  'peserta': _participantJson(status: 'terblokir'),
  'ujian': _examJson(),
  'kemajuan': {'jumlah_soal': 2, 'terjawab': 1, 'belum_dijawab': 1, 'ragu': 0},
  'keamanan': {
    ..._securityJson(),
    'aktif': true,
    'catat_pindah_aplikasi': true,
    'layar_aman': true,
    'wajib_fullscreen': true,
    'tindakan': 'tahan',
    'jumlah_kejadian': 3,
    'sisa_kejadian': 0,
    'durasi_total_detik': 18,
    'ditahan': true,
  },
};

Map<String, dynamic> _participantJson({required String status}) => {
  'id': 31,
  'nomor_peserta': 'NATIVE-001',
  'status': status,
  'label_status': status == 'selesai' ? 'Selesai' : 'Aktif',
  'nama': 'Siswa NUSA',
  'nisn': '0099000001',
  'kelas': 'VIII.A',
  'ruang': 'Ruang 1',
  'nomor_meja': 7,
  'sesi': 'Sesi Pagi',
};

Map<String, dynamic> _examJson() => {
  'id': 9,
  'nama': 'Asesmen IPA Native',
  'kode': 'ASESMEN-IPA-NATIVE',
  'jenis': 'Sumatif',
  'mata_pelajaran': 'Ilmu Pengetahuan Alam',
  'tahun_pelajaran': '2026/2027',
  'semester': 'ganjil',
  'durasi_menit': 30,
  'jumlah_soal': 2,
  'petunjuk': 'Kerjakan dengan teliti.',
  'tampilkan_hasil': true,
  'batasi_satu_perangkat': true,
};

Map<String, dynamic> _securityJson() => {
  'aktif': false,
  'catat_pindah_aplikasi': false,
  'layar_aman': false,
  'wajib_fullscreen': false,
  'toleransi_detik': 3,
  'batas_kejadian': 3,
  'tindakan': 'catat',
  'jumlah_kejadian': 0,
  'sisa_kejadian': 3,
  'durasi_total_detik': 0,
  'ditahan': false,
};
