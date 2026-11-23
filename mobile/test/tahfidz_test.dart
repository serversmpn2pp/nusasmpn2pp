import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/tahfidz/data/tahfidz_repository.dart';
import 'package:nusa/features/tahfidz/domain/tahfidz.dart';
import 'package:nusa/features/tahfidz/presentation/tahfidz_document.dart';
import 'package:nusa/features/tahfidz/presentation/tahfidz_forms.dart';
import 'package:nusa/features/tahfidz/presentation/tahfidz_views.dart';

void main() {
  testWidgets(
    'Tahfidz pembimbing rapi di layar sempit dan alat koordinator tersembunyi',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 700));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await _pump(tester, _FakeTahfidz(), const TahfidzView());
      expect(find.text('Kelompok Al-Ikhlas'), findsOneWidget);
      expect(find.text('Tambah Kelompok'), findsNothing);
      expect(find.text('Target Rapor'), findsNothing);
      expect(find.text('Daftar Surah'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'retry setoran mempertahankan token dan input untuk mencegah percobaan ganda',
    (tester) async {
      final remote = _FakeTahfidz()..failFirstDeposit = true;
      await _pump(tester, remote, const TfMeetingView(1, 2));
      await tester.tap(find.text('Catat Setoran'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.byKey(const Key('tahfidz-field-anggota_tahfidz_id')),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Alya').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('tahfidz-field-nomor_surah')));
      await tester.pumpAndSettle();
      final anNas = find.text('114. An-Nas').last;
      await tester.ensureVisible(anNas);
      await tester.pumpAndSettle();
      await tester.tap(anNas);
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('tahfidz-field-nilai')),
        '88,5',
      );
      await tester.ensureVisible(find.text('Simpan'));
      await tester.tap(find.text('Simpan'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Koneksi terputus'), findsOneWidget);
      final token = remote.writes.single['data']['token_setoran'];
      expect(
        RegExp(
          r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
        ).hasMatch('$token'),
        isTrue,
      );
      await tester.ensureVisible(find.text('Simpan'));
      await tester.tap(find.text('Simpan'));
      await tester.pumpAndSettle();
      expect(remote.writes, hasLength(2));
      expect(remote.writes.last['data']['token_setoran'], token);
      expect(remote.writes.last['data']['nilai'], '88,5');
      expect(remote.writes.last['data']['anggota_tahfidz_id'], 7);
      expect(find.text('Catat Setoran Tahfidz'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'input kolektif memilih 37 surah, menjaga isian jauh, dan retry satu batch',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 650));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final remote = _FakeTahfidz()..failFirstDeposit = true;
      await _openCollective(tester, remote);
      await tester.tap(
        find.widgetWithText(CheckboxListTile, 'Pilih semua surah'),
      );
      await tester.pumpAndSettle();
      expect(find.text('37 dari 37 dipilih'), findsOneWidget);
      await tester.tap(find.text('Terapkan Pilihan'));
      await tester.pumpAndSettle();
      expect(find.text('37 surah dipilih'), findsOneWidget);

      final save = find.byKey(const Key('tahfidz-kolektif-simpan'));
      final first = find.byKey(const Key('tahfidz-kolektif-nilai-78'));
      final last = find.byKey(const Key('tahfidz-kolektif-nilai-114'));
      final scroll = find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .first;
      await tester.tap(save);
      await tester.pumpAndSettle();
      expect(
        find.text('Isi minimal satu nilai untuk disimpan.'),
        findsOneWidget,
      );
      expect(remote.writes, isEmpty);
      await tester.enterText(first, '101');
      await tester.scrollUntilVisible(last, 320, scrollable: scroll);
      await tester.enterText(last, '88,5');
      await tester.tap(save);
      await tester.pumpAndSettle();
      expect(find.textContaining('Periksa nilai An-Naba'), findsOneWidget);
      expect(remote.writes, isEmpty);
      await tester.scrollUntilVisible(first, -320, scrollable: scroll);
      await tester.enterText(first, '0');
      await tester.tap(save);
      await tester.pumpAndSettle();
      expect(find.textContaining('Koneksi terputus'), findsOneWidget);
      expect(find.text('2 dari 37 nilai terisi'), findsOneWidget);

      final write = remote.writes.single;
      final data = tfMap(write['data']);
      expect(write['path'], 'tahfidz/1/pertemuan/2/kolektif');
      expect(data['siswa'], 7);
      expect(data['surah'], [for (var n = 78; n <= 114; n++) n]);
      expect(data['nilai'], {
        '7': {'78': '0', '114': '88.5'},
      });
      expect(data['token_batch'], isNotEmpty);
      await tester.tap(save);
      await tester.pumpAndSettle();
      expect(remote.writes, hasLength(2));
      expect(remote.writes.last, write);
      expect(
        remote.reads.where((path) => path == 'tahfidz/1/pertemuan/2'),
        hasLength(2),
      );
      expect(find.text('2 setoran berhasil disimpan.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'input kolektif menerima pilihan sebagian dan tidak memindahkan draf ke siswa lain',
    (tester) async {
      final remote = _FakeTahfidz();
      await _openCollective(tester, remote);
      await tester.tap(find.byKey(const Key('tahfidz-kolektif-surah-78')));
      await tester.tap(find.byKey(const Key('tahfidz-kolektif-surah-79')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Terapkan Pilihan'));
      await tester.pumpAndSettle();
      expect(find.text('2 surah dipilih'), findsOneWidget);
      final first = find.byKey(const Key('tahfidz-kolektif-nilai-78'));
      final second = find.byKey(const Key('tahfidz-kolektif-nilai-79'));
      await tester.enterText(first, '95');
      final student = find.byKey(const Key('tahfidz-kolektif-siswa'));
      await tester.tap(student);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bima'));
      await tester.pumpAndSettle();
      expect(find.text('Ganti siswa?'), findsOneWidget);
      await tester.tap(find.text('Batal'));
      await tester.pumpAndSettle();
      expect(tester.widget<TextField>(first).controller!.text, '95');

      await tester.tap(student);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bima'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ganti siswa'));
      await tester.pumpAndSettle();
      expect(tester.widget<TextField>(first).controller!.text, isEmpty);
      await tester.enterText(second, '80');
      await tester.tap(find.byKey(const Key('tahfidz-kolektif-simpan')));
      await tester.pumpAndSettle();
      final data = tfMap(remote.writes.single['data']);
      expect(data['siswa'], 8);
      expect(data['surah'], [78, 79]);
      expect(data['nilai'], {
        '8': {'79': '80'},
      });
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('input kolektif tersembunyi pada pertemuan selesai', (
    tester,
  ) async {
    final remote = _FakeTahfidz()..meetingClosed = true;
    await _pump(tester, remote, const TfMeetingView(1, 2));
    expect(find.text('Input Kolektif per Siswa'), findsNothing);
    expect(find.text('Catat Setoran'), findsNothing);
  });

  testWidgets(
    'pencarian anggota mempertahankan pilihan siswa yang tidak terlihat',
    (tester) async {
      final remote = _FakeTahfidz();
      await _pump(tester, remote, const TfMembersView(1));
      await tester.enterText(find.byType(TextField), 'Bima');
      await tester.pumpAndSettle();
      expect(find.text('Alya'), findsNothing);
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Simpan 1 Anggota'));
      await tester.pumpAndSettle();
      expect(remote.writes.single['data']['siswa_ids'], [101]);
      expect(remote.writes.single['data']['daftar_lengkap'], isTrue);
      expect(remote.writes.single['data']['versi'], 3);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'perubahan anak mengabaikan respons terlambat milik anak sebelumnya',
    (tester) async {
      final remote = _FakeTahfidz()..pendingChild = Completer<TfData>();
      await _pump(
        tester,
        remote,
        const TahfidzAnakView(studentId: 101),
        settle: false,
      );
      await tester.pump();
      await _pump(tester, remote, const TahfidzAnakView(studentId: 202));
      expect(find.text('Bima'), findsOneWidget);
      expect(find.textContaining('99 / 37'), findsNothing);
      remote.pendingChild!.complete({..._parentData(101), 'sudah': 99});
      await tester.pumpAndSettle();
      expect(find.textContaining('99 / 37'), findsNothing);
      expect(find.text('Bima'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('orang tua membuka rapor lewat pemeriksaan API terbaru', (
    tester,
  ) async {
    final remote = _FakeTahfidz();
    await _pump(tester, remote, const TahfidzAnakView(studentId: 202));
    await tester.tap(find.text('Dokumen'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Buka Rapor'));
    await tester.tap(find.text('Buka Rapor'));
    await tester.pumpAndSettle();
    expect(remote.reads, contains('tahfidz-anak/rapor/9'));
    expect(find.text('Cetak / Simpan PDF'), findsNothing);
    expect(find.byIcon(Icons.print_outlined), findsNothing);
    expect(find.text('Bagikan Rapor ke Orang Tua'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Tahfidz Saya menampilkan progres dan riwayat sendiri tanpa pilihan anak atau dokumen orang tua',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 700));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final remote = _FakeTahfidz();
      await _pump(tester, remote, const TahfidzSayaView());
      expect(remote.reads, ['tahfidz-saya']);
      expect(remote.lastStudentQuery, {'riwayat': 1});
      expect(find.text('Tahfidz Saya'), findsOneWidget);
      expect(find.text('Progres Hafalan Saya'), findsOneWidget);
      expect(find.textContaining('Alya'), findsOneWidget);
      expect(find.textContaining('1 / 37'), findsOneWidget);
      expect(find.byKey(const Key('tahfidz-child')), findsNothing);
      expect(find.text('Dokumen'), findsNothing);
      expect(find.text('Bagikan Rapor ke Orang Tua'), findsNothing);
      await tester.ensureVisible(find.text('Riwayat'));
      await tester.tap(find.text('Riwayat'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.textContaining('Nilai 88,5'));
      expect(find.textContaining('Nilai 88,5'), findsOneWidget);
      expect(find.text('Catat Setoran'), findsNothing);
      expect(remote.writes, isEmpty);
      expect(tester.takeException(), isNull);
    },
  );

  test(
    'dokumen rapor dan sertifikat dapat dibuat sebagai PDF native',
    () async {
      final report = await buildTahfidzPdf({
        ..._report,
        'baris': [
          for (var i = 78; i <= 114; i++)
            {'surah': 'Surah $i', 'nilai': '88,5'},
        ],
      }, certificate: false);
      final certificate = await buildTahfidzPdf({
        'nama': 'Alya',
        'nomor': 'THF/2026/00001',
        'pembimbing': 'Guru Tahfidz',
        'kepala_sekolah': 'Kepala Sekolah',
        'acara': 'Wisuda',
        'tanggal': '2026-11-05',
        'lokasi': 'Sekolah',
      }, certificate: true);
      final group = await buildTahfidzPdf(
        {},
        certificate: false,
        reports: [
          _report,
          {..._report, 'nama': 'Alya'},
        ],
      );
      expect(ascii.decode(report.take(4).toList()), '%PDF');
      expect(ascii.decode(certificate.take(4).toList()), '%PDF');
      expect(ascii.decode(group.take(4).toList()), '%PDF');
    },
  );
}

Future<void> _pump(
  WidgetTester tester,
  TahfidzRepository repo,
  Widget page, {
  bool settle = true,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [tahfidzRepositoryProvider.overrideWithValue(repo)],
      child: MaterialApp(theme: AppTheme.light, home: page),
    ),
  );
  if (settle) await tester.pumpAndSettle();
}

Future<void> _openCollective(WidgetTester tester, _FakeTahfidz remote) async {
  await _pump(tester, remote, const TfMeetingView(1, 2));
  await tester.tap(find.text('Input Kolektif per Siswa'));
  await tester.pumpAndSettle();
  await tester.tap(find.byKey(const Key('tahfidz-kolektif-siswa')));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Alya'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Pilih Surah'));
  await tester.pumpAndSettle();
}

const _report = <String, dynamic>{
  'nama': 'Bima',
  'kelas': 'VII.A',
  'tahun': '2026/2027',
  'kelompok': 'Al-Ikhlas',
  'target': 1,
  'rata': '88,50',
  'jumlah': '88,50',
  'predikat': 'Baik',
  'tanggal': '2026-11-05',
  'pembimbing': 'Guru Tahfidz',
  'baris': [
    {'surah': 'An-Nas', 'nilai': '88,5'},
  ],
};
TfData _parentData(int id) => {
  'anak': [
    {'id': 101, 'nama': 'Alya'},
    {'id': 202, 'nama': 'Bima'},
  ],
  'siswa_id': id,
  'sudah': 1,
  'target': 37,
  'baris': [
    {'nomor': 114, 'nama': 'An-Nas', 'nilai': '88,5', 'tanggal': '2026-11-05'},
  ],
  'riwayat': [],
  'rapor': [
    {'id': 9, 'tahun': '2026/2027', 'versi': 1},
  ],
  'sertifikat': [],
};

class _FakeTahfidz extends TahfidzRepository {
  _FakeTahfidz() : super(Dio());
  final reads = <String>[];
  final writes = <TfData>[];
  bool failFirstDeposit = false;
  bool meetingClosed = false;
  Completer<TfData>? pendingChild;
  TfData? lastStudentQuery;
  @override
  Future<TfData> get(String path, [TfData? query]) async {
    reads.add(path);
    if (path == 'tahfidz-saya') {
      lastStudentQuery = query;
      return {
        'siswa': {'id': 101, 'nama': 'Alya'},
        'sudah': 1,
        'target': 37,
        'baris': [
          {
            'nomor': 114,
            'nama': 'An-Nas',
            'nilai': '88,5',
            'tanggal': '2026-11-05',
          },
        ],
        'riwayat': [
          {
            'id': 1,
            'surah': 'An-Nas',
            'nilai': '88,5',
            'tanggal': '2026-11-05',
          },
        ],
      };
    }
    if (path == 'tahfidz') {
      return {
        'kelola': false,
        'tahun_id': 1,
        'tahun': [
          {'id': 1, 'nama': '2026/2027'},
        ],
        'items': [
          {
            'id': 1,
            'nama': 'Kelompok Al-Ikhlas',
            'pembimbing': 'Guru Tahfidz',
            'jumlah_anggota': 2,
            'aktif': true,
          },
        ],
      };
    }
    if (path == 'tahfidz/1/pertemuan/2') {
      return {
        'kelompok': {'nama': 'Kelompok Al-Ikhlas'},
        'pertemuan': {
          'id': 2,
          'tanggal': '2026-11-05',
          'selesai': meetingClosed,
        },
        'catat': !meetingClosed,
        'catat_kolektif': !meetingClosed,
        'peserta': [
          {'id': 7, 'nama': 'Alya'},
          {'id': 8, 'nama': 'Bima'},
        ],
        'surah': [
          {'nomor': 78, 'nama': 'An-Naba'},
          for (var n = 79; n < 114; n++) {'nomor': n, 'nama': 'Surah $n'},
          {'nomor': 114, 'nama': 'An-Nas'},
        ],
        'items': [],
      };
    }
    if (path == 'tahfidz/1/anggota') {
      return {
        'kelompok': {'versi': 3},
        'terpilih': [101, 202],
        'items': [
          {'id': 101, 'nama': 'Alya', 'kelas': 'VII.A', 'dapat_dipilih': true},
          {'id': 202, 'nama': 'Bima', 'kelas': 'VIII.A', 'dapat_dipilih': true},
        ],
      };
    }
    if (path == 'tahfidz-anak') {
      final id = tfInt(query?['siswa']);
      if (id == 101 && pendingChild != null) return pendingChild!.future;
      return _parentData(id);
    }
    if (path == 'tahfidz-anak/rapor/9') return {'rapor': _report};
    throw StateError('Endpoint tidak diharapkan: $path');
  }

  @override
  Future<TfData> call(
    String method,
    String path, {
    TfData? data,
    TfData? query,
  }) async {
    writes.add({
      'method': method,
      'path': path,
      'data': {...?data},
    });
    if (failFirstDeposit && writes.length == 1) {
      throw const NetworkException('Koneksi terputus. Coba simpan lagi.');
    }
    if (path.endsWith('/kolektif')) {
      final batch = tfMap(data?['nilai']);
      return {'jumlah_setoran': tfMap(batch.values.single).length};
    }
    return {'id': 2};
  }
}
