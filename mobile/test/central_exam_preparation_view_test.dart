import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_preparation/application/central_exam_preparation_controller.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:nusa/features/central_exam_preparation/presentation/central_exam_preparation_detail_view.dart';

void main() {
  test('domain membaca tahap persiapan ujian terpusat', () {
    final detail = CentralExamPreparationDetail.fromJson(_payload());

    expect(detail.event.name, 'SAS Ganjil 2026/2027');
    expect(detail.committee.single.positionLabel, 'Teknisi');
    expect(detail.sessions.single.timeLabel, '07:30 - 09:30');
    expect(detail.rooms.single.capacity, 20);
    expect(detail.participantStage.grades.first.activeStudentCount, 18);
    expect(detail.participantStage.grades.first.assignment?.totalCapacity, 20);
    expect(detail.scheduleStage.items.single.subjectName, 'Matematika');
    expect(detail.scheduleStage.subjects.single.grades, [7, 8, 9]);
    expect(detail.access.canManageMain, isTrue);
  });

  testWidgets('ruang kerja tahap 1-7 rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 740);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final detail = CentralExamPreparationDetail.fromJson(_payload());

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          centralExamPreparationDetailProvider(7)
              .overrideWith((ref) async => detail),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const CentralExamPreparationDetailView(eventId: 7),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('SAS Ganjil 2026/2027'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Kesiapan tahap 1–4'),
      180,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Kesiapan tahap 1–4'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.widgetWithText(Tab, 'Panitia'));
    await tester.pumpAndSettle();
    expect(find.text('Teknisi Ujian Mobile'), findsOneWidget);

    await tester.tap(find.widgetWithText(Tab, 'Sesi'));
    await tester.pumpAndSettle();
    expect(find.text('Sesi Pagi'), findsOneWidget);

    await tester.tap(find.widgetWithText(Tab, 'Ruang'));
    await tester.pumpAndSettle();
    expect(find.text('Ruang 1'), findsOneWidget);

    await tester.ensureVisible(find.widgetWithText(Tab, 'Penetapan'));
    await tester.tap(find.widgetWithText(Tab, 'Penetapan'));
    await tester.pumpAndSettle();
    expect(find.text('Tahap 5 · Penetapan ruang'), findsOneWidget);
    expect(find.text('Ubah Penetapan'), findsOneWidget);

    await tester.ensureVisible(find.widgetWithText(Tab, 'Peserta'));
    await tester.tap(find.widgetWithText(Tab, 'Peserta'));
    await tester.pumpAndSettle();
    expect(find.text('Tahap 6 · Pembagian peserta'), findsOneWidget);
    expect(find.text('Susun Ulang Otomatis'), findsOneWidget);

    await tester.ensureVisible(find.widgetWithText(Tab, 'Jadwal'));
    await tester.tap(find.widgetWithText(Tab, 'Jadwal'));
    await tester.pumpAndSettle();
    expect(find.text('Tahap 7 · Jadwal ujian'), findsOneWidget);
    expect(find.text('Matematika'), findsOneWidget);
    expect(find.text('Tambah Jadwal'), findsOneWidget);

    await tester.tap(find.text('Tambah Jadwal'));
    await tester.pumpAndSettle();
    expect(find.text('Tambah Jadwal Ujian'), findsOneWidget);
    expect(find.text('Tanggal ujian'), findsOneWidget);
    expect(find.text('Mata pelajaran'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Map<String, dynamic> _payload() => {
  'kegiatan': {
    'id': 7,
    'kode': 'UT-2026-001',
    'nama': 'SAS Ganjil 2026/2027',
    'jenis_ujian': 'Sumatif Akhir Semester',
    'jenis_ujian_cbt_id': 2,
    'tahun_pelajaran': '2026/2027',
    'tahun_pelajaran_id': 3,
    'semester': 'ganjil',
    'tanggal_mulai': '2026-12-01',
    'tanggal_selesai': '2026-12-08',
    'status': 'draft',
    'label_status': 'Persiapan',
    'jumlah_panitia': 1,
    'jumlah_sesi': 1,
    'jumlah_ruang': 1,
    'jumlah_jadwal': 0,
    'total_kapasitas': 20,
    'dapat_dihapus': true,
    'keterangan': 'Persiapan ujian semester ganjil.',
  },
  'panitia': [
    {
      'id': 8,
      'pegawai_id': 9,
      'nama': 'Teknisi Ujian Mobile',
      'nip': '19870009',
      'jabatan': 'teknisi',
      'label_jabatan': 'Teknisi',
      'catatan': 'Menangani perangkat.',
      'memiliki_akun': true,
    },
  ],
  'sesi': [
    {
      'id': 10,
      'kode': 'S01',
      'nama': 'Sesi Pagi',
      'waktu_mulai': '07:30',
      'waktu_selesai': '09:30',
      'label_waktu': '07:30 - 09:30',
      'aktif': true,
      'dapat_dihapus': true,
    },
  ],
  'ruang': [
    {
      'id': 11,
      'kode': 'R01',
      'nama': 'Ruang 1',
      'lokasi': 'Kelas VII.A',
      'kapasitas': 20,
      'aktif': true,
      'dapat_dihapus': true,
    },
  ],
  'tahap_peserta': {
    'tingkat': [
      {
        'tingkat': 7,
        'jumlah_siswa_aktif': 18,
        'kelas': [
          {'id': 21, 'nama': 'VII.A', 'jumlah_siswa_aktif': 18},
        ],
        'penetapan': {
          'id': 31,
          'sesi_id': 10,
          'nama_sesi': 'Sesi Pagi',
          'label_waktu': '07:30 - 09:30',
          'kelas_id': [21],
          'ruang_id': [11],
          'jumlah_peserta': 18,
          'jumlah_terbagi': 18,
          'total_kapasitas': 20,
          'jumlah_jadwal': 0,
          'dibangkitkan_pada': '2026-11-20T08:00:00+07:00',
          'dapat_dihapus': true,
        },
      },
      {
        'tingkat': 8,
        'jumlah_siswa_aktif': 0,
        'kelas': <Map<String, dynamic>>[],
        'penetapan': null,
      },
      {
        'tingkat': 9,
        'jumlah_siswa_aktif': 0,
        'kelas': <Map<String, dynamic>>[],
        'penetapan': null,
      },
    ],
    'penggunaan_ruang': [
      {'ruang_id': 11, 'sesi_id': 10, 'tingkat': 7},
    ],
  },
  'tahap_jadwal': {
    'items': [
      {
        'id': 41,
        'tanggal': '2026-12-01',
        'mata_pelajaran_id': 51,
        'mata_pelajaran': 'Matematika',
        'tingkat': 7,
        'nama_sesi': 'Sesi Pagi',
        'label_waktu': '07:30 - 09:30',
        'kelas': ['VII.A'],
        'ruang': ['Ruang 1'],
        'jumlah_peserta': 18,
        'status': 'draft',
        'label_status': 'Draft',
        'keterangan': 'Hari pertama',
        'terkunci': false,
        'paket': null,
        'dapat_dihapus': true,
      },
    ],
    'mata_pelajaran': [
      {
        'id': 51,
        'kode': 'MTK',
        'nama': 'Matematika',
        'tingkat': [7, 8, 9],
      },
    ],
  },
  'referensi': {
    'jenis_ujian': [
      {'id': 2, 'nama': 'Sumatif Akhir Semester'},
    ],
    'tahun_pelajaran': [
      {'id': 3, 'nama': '2026/2027', 'aktif': true},
    ],
    'status': [
      {'kode': 'draft', 'label': 'Persiapan'},
    ],
    'jabatan_panitia': [
      {'kode': 'teknisi', 'label': 'Teknisi'},
    ],
    'pegawai': [
      {
        'id': 9,
        'nama': 'Teknisi Ujian Mobile',
        'nip': '19870009',
        'memiliki_akun': true,
      },
    ],
  },
  'hak_akses': {'dapat_kelola_utama': true, 'dapat_kelola_persiapan': true},
};
