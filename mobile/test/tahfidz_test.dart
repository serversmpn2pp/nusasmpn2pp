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
      await tester.tap(find.text('114. An-Nas').last);
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
  Completer<TfData>? pendingChild;
  @override
  Future<TfData> get(String path, [TfData? query]) async {
    reads.add(path);
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
        'pertemuan': {'id': 2, 'tanggal': '2026-11-05', 'selesai': false},
        'catat': true,
        'peserta': [
          {'id': 7, 'nama': 'Alya'},
        ],
        'surah': [
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
    return {'id': 2};
  }
}
