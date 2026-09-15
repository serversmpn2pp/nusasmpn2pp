import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/storage/device_identity.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_exam/data/student_exam_remote_data_source.dart';
import 'package:nusa/features/student_exam/data/student_exam_file_picker.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_view.dart';

void main() {
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

  testWidgets('siswa membuka, autosave, dan menyelesaikan ujian native', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final remote = _FakeStudentExamRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentExamRemoteDataSourceProvider.overrideWithValue(remote),
          deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
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
    expect(tester.takeException(), isNull);

    await tester.enterText(
      find.byKey(const Key('student-exam-answer-102')),
      'Pertukaran terjadi di alveolus.',
    );
    await tester.pump(const Duration(milliseconds: 750));
    await tester.pump();
    expect(remote.savedQuestions, contains(102));

    await tester.tap(find.byKey(const Key('student-exam-finish')));
    await tester.pumpAndSettle();
    expect(find.text('Selesaikan ujian?'), findsOneWidget);
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
          deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
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
  });

  final bool locked;
  final bool matching;
  final bool upload;
  final bool allTypes;
  String? startToken;
  int finishCalls = 0;
  Object? lastAnswer;
  String? uploadedFileName;
  final List<int> savedQuestions = [];

  @override
  Future<StudentExamSecurityUpdate> securityEvent({
    required int participantId,
    required String event,
    required String device,
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
    return StudentExamSession.fromJson(
      allTypes
          ? _allTypesRunningJson()
          : _runningJson(withMatching: matching, withUpload: upload),
    );
  }

  @override
  Future<StudentExamSession> resume({
    required int participantId,
    required String device,
  }) async => StudentExamSession.fromJson(
    allTypes
        ? _allTypesRunningJson()
        : _runningJson(withMatching: matching, withUpload: upload),
  );

  @override
  Future<StudentExamSaveResult> saveAnswer({
    required int participantId,
    required int questionId,
    required Object? answer,
    required bool doubtful,
    required String device,
  }) async {
    savedQuestions.add(questionId);
    lastAnswer = answer;
    return StudentExamSaveResult.fromJson({
      'mode': 'tersimpan',
      'sisa_detik': 1790,
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
    return StudentExamFileSaveResult.fromJson({
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
}) => {
  'mode': 'pengerjaan',
  'waktu_server': '2026-09-05T08:00:00+07:00',
  'berakhir_pada': '2026-09-05T08:30:00+07:00',
  'sisa_detik': 1800,
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
