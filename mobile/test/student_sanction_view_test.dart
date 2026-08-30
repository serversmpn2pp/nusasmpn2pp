import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_sanction/data/student_sanction_file_services.dart';
import 'package:nusa/features/student_sanction/data/student_sanction_remote_data_source.dart';
import 'package:nusa/features/student_sanction/domain/student_sanction.dart';
import 'package:nusa/features/student_sanction/presentation/student_sanction_detail_view.dart';
import 'package:nusa/features/student_sanction/presentation/student_sanction_list_view.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

void main() {
  test('domain membaca sanksi, bukti privat, riwayat, dan hak akses', () {
    final page = StudentSanctionPage.fromJson(_pageJson());
    final detail = StudentSanctionDetail.fromJson(
      _detailJson(withEvidence: true),
    );

    expect(page.summary.overdue, 1);
    expect(page.items.single.rule.name, 'Pembinaan bersama orang tua');
    expect(detail.evidence.single.fileName, 'bukti-sanksi.pdf');
    expect(detail.history.single.nextStatus, 'diproses');
    expect(detail.access.canManage, isTrue);
  });

  testWidgets('daftar sanksi rapi pada layar Android sempit', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentSanctionRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentSanctionRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentSanctionListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Pelaksanaan Sanksi Siswa'), findsOneWidget);
    expect(find.text('Siswa Sanksi Native'), findsOneWidget);
    expect(find.text('Terlambat'), findsWidgets);
    expect(
      find.byKey(const Key('student-sanction-status-filter')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('hasil wajib dan penyelesaian memakai konfirmasi final', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentSanctionRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentSanctionRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentSanctionDetailView(sanctionId: 7),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('student-sanction-status')),
      350,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('student-sanction-status')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Selesai').last);
    await tester.scrollUntilVisible(
      find.byKey(const Key('student-sanction-submit')),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    tester
        .widget<NusaPrimaryButton>(
          find.byKey(const Key('student-sanction-submit')),
        )
        .onPressed
        ?.call();
    await tester.pumpAndSettle();

    expect(
      find.text('Hasil pelaksanaan wajib diisi sebelum sanksi diselesaikan.'),
      findsOneWidget,
    );
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const Key('student-sanction-result')),
      'Siswa melaksanakan pembinaan dan membuat komitmen perbaikan.',
    );
    tester
        .widget<NusaPrimaryButton>(
          find.byKey(const Key('student-sanction-submit')),
        )
        .onPressed
        ?.call();
    await tester.pumpAndSettle();
    expect(find.text('Selesaikan sanksi?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('student-sanction-confirm-submit')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(remote.lastPayload?.status, 'selesai');
    expect(tester.takeException(), isNull);
  });

  testWidgets('petugas dapat mengunggah bukti privat dari detail', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentSanctionRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentSanctionRemoteDataSourceProvider.overrideWithValue(remote),
          studentSanctionFilePickerProvider.overrideWithValue(
            _FakeStudentSanctionFilePicker(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentSanctionDetailView(sanctionId: 7),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('student-sanction-upload-evidence')),
      600,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('student-sanction-upload-evidence')));
    await tester.pumpAndSettle();
    expect(find.text('Unggah Bukti Privat'), findsOneWidget);
    await tester.enterText(
      find.byKey(const Key('student-sanction-evidence-description')),
      'Dokumentasi pembinaan siswa.',
    );
    await tester.tap(find.byKey(const Key('student-sanction-confirm-upload')));
    await tester.pumpAndSettle();

    expect(remote.uploadCalls, 1);
    expect(remote.lastDescription, 'Dokumentasi pembinaan siswa.');
    expect(remote.lastFiles.single.name, 'dokumentasi.jpg');
    expect(tester.takeException(), isNull);
  });
}

class _FakeStudentSanctionFilePicker implements StudentSanctionFilePicker {
  @override
  Future<List<SanctionPickedFile>> pick() async => [
    SanctionPickedFile(
      name: 'dokumentasi.jpg',
      bytes: Uint8List.fromList([1, 2, 3]),
    ),
  ];
}

class _FakeStudentSanctionRemoteDataSource
    implements StudentSanctionRemoteDataSource {
  int updateCalls = 0;
  int uploadCalls = 0;
  StudentSanctionPayload? lastPayload;
  String? lastDescription;
  List<SanctionPickedFile> lastFiles = const [];

  @override
  Future<StudentSanctionPage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async => StudentSanctionPage.fromJson(_pageJson());

  @override
  Future<StudentSanctionDetail> fetchDetail(int id) async =>
      StudentSanctionDetail.fromJson(_detailJson());

  @override
  Future<StudentSanctionDetail> update(
    int id,
    StudentSanctionPayload payload,
  ) async {
    updateCalls++;
    lastPayload = payload;
    return StudentSanctionDetail.fromJson(
      _detailJson(completed: payload.status == 'selesai'),
    );
  }

  @override
  Future<StudentSanctionDetail> uploadEvidence({
    required int id,
    required List<SanctionPickedFile> files,
    required String? description,
  }) async {
    uploadCalls++;
    lastFiles = files;
    lastDescription = description;
    return StudentSanctionDetail.fromJson(_detailJson(withEvidence: true));
  }

  @override
  Future<StudentSanctionDetail> deleteEvidence(int evidenceId) async =>
      StudentSanctionDetail.fromJson(_detailJson());

  @override
  Future<SanctionEvidenceDownload> downloadEvidence(
    SanctionEvidence evidence,
  ) async => SanctionEvidenceDownload(
    fileName: evidence.fileName,
    mimeType: evidence.mimeType ?? 'application/octet-stream',
    bytes: Uint8List.fromList([1, 2, 3]),
  );
}

const _statuses = [
  {'kode': 'aktif', 'label': 'Perlu Ditangani'},
  {'kode': 'semua', 'label': 'Semua Status'},
  {'kode': 'menunggu', 'label': 'Menunggu'},
  {'kode': 'diproses', 'label': 'Diproses'},
  {'kode': 'selesai', 'label': 'Selesai'},
  {'kode': 'dibatalkan', 'label': 'Dibatalkan'},
];

const _officers = [
  {'id': 2, 'nama': 'Guru BK Native', 'nip': '198101012026081001'},
];

Map<String, dynamic> _pageJson() => {
  'items': [_itemJson(overdue: true)],
  'ringkasan': {
    'aktif': 3,
    'menunggu': 2,
    'diproses': 1,
    'terlambat': 1,
    'selesai': 4,
  },
  'pilihan': {
    'status': _statuses,
    'tahun_pelajaran': [
      {'id': 1, 'nama': '2026/2027', 'aktif': true},
    ],
    'kelas': [
      {'id': 1, 'tahun_pelajaran_id': 1, 'nama': 'VIII.A', 'tingkat': 8},
    ],
  },
  'filter': {
    'kata_kunci': '',
    'status': 'aktif',
    'tahun_pelajaran_id': 1,
    'kelas_id': null,
  },
  'paginasi': {
    'halaman': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'cakupan_luas': true, 'dapat_kelola_umum': true},
};

Map<String, dynamic> _detailJson({
  bool completed = false,
  bool withEvidence = false,
}) => {
  'sanksi': _itemJson(completed: completed),
  'bukti': withEvidence
      ? [
          {
            'id': 9,
            'nama_file': 'bukti-sanksi.pdf',
            'tipe_file': 'application/pdf',
            'ukuran_ringkas': '120 KB',
            'keterangan': 'Dokumentasi pembinaan siswa.',
            'diunggah_oleh': 'Guru BK Native',
            'diunggah_pada': '2026-08-31T10:30:00+07:00',
          },
        ]
      : [],
  'riwayat': [
    {
      'id': 4,
      'jenis_kegiatan': 'status_diubah',
      'judul': 'Sanksi mulai diproses',
      'status_sebelum': 'menunggu',
      'label_status_sebelum': 'Menunggu',
      'status_sesudah': 'diproses',
      'label_status_sesudah': 'Diproses',
      'catatan': 'Orang tua telah dihubungi.',
      'pengguna': 'Admin NUSA',
      'terjadi_pada': '2026-08-31T09:00:00+07:00',
    },
  ],
  'pilihan_status': completed
      ? [
          {'kode': 'selesai', 'label': 'Selesai'},
        ]
      : [
          {'kode': 'diproses', 'label': 'Diproses'},
          {'kode': 'selesai', 'label': 'Selesai'},
          {'kode': 'dibatalkan', 'label': 'Dibatalkan'},
        ],
  'pegawai': _officers,
  'hak_akses': {
    'dapat_kelola': true,
    'dapat_unduh_bukti': true,
    'status_final': completed,
  },
};

Map<String, dynamic> _itemJson({
  bool completed = false,
  bool overdue = false,
}) => {
  'id': 7,
  'siswa': {
    'id': 1,
    'nama': 'Siswa Sanksi Native',
    'nis': 'NUSA-01',
    'nisn': '0088111001',
  },
  'kelas': {'id': 1, 'nama': 'VIII.A'},
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'aturan': {
    'id': 3,
    'nama': 'Pembinaan bersama orang tua',
    'batas_poin': 50,
    'deskripsi': 'Pertemuan siswa, orang tua, wali kelas, dan guru BK.',
  },
  'petugas': _officers.first,
  'poin_saat_terpicu': 55,
  'status': completed ? 'selesai' : 'diproses',
  'label_status': completed ? 'Selesai' : 'Diproses',
  'terpicu_pada': '2026-08-20T08:00:00+07:00',
  'batas_pelaksanaan': '2026-09-05',
  'terlambat': overdue,
  'jumlah_bukti': 0,
  'mulai_diproses_pada': '2026-08-21T09:00:00+07:00',
  'dilaksanakan_pada': completed ? '2026-08-31T11:00:00+07:00' : null,
  'catatan': 'Orang tua telah dihubungi.',
  'hasil_pelaksanaan': completed ? 'Siswa menyelesaikan pembinaan.' : null,
  'wali_kelas': {
    'id': 5,
    'nama': 'Wali Kelas VIII.A',
    'nip': '198202022026082002',
  },
  'guru_wali': {
    'id': 6,
    'nama': 'Guru Wali Native',
    'nip': '198303032026083003',
  },
  'diperbarui_oleh': 'Admin NUSA',
};
