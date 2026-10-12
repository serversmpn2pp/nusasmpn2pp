import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/sts_behavior/data/sts_behavior_remote_data_source.dart';
import 'package:nusa/features/sts_behavior/domain/sts_behavior.dart';
import 'package:nusa/features/sts_behavior/presentation/sts_behavior_view.dart';

void main() {
  test('payload retains original version/hash and includes only editable summaries', () {
    final student = _page().report!.students.first;
    expect(student.memberId, 71);
    final payload = student.reviewPayload(
      editedRows: {
        'laporan-9': {'kejadian': 'Ringkasan', 'tindakan': 'Tindak lanjut'},
      },
      note: ' Umum ',
    );
    expect(payload['versi'], 3);
    expect(payload['sidik_sumber'], 'a' * 64);
    expect(payload['catatan'], 'Umum');
    expect(payload.keys, ['versi', 'sidik_sumber', 'baris', 'catatan']);
    expect((payload['baris'] as Map)['laporan-9'], {
      'kejadian': 'Ringkasan',
      'tindakan': 'Tindak lanjut',
    });
    expect(_page().report!.contentPayload('poin_final'), {
      'isi_lampiran_perilaku': 'poin_final',
      'versi_isi_perilaku': 1,
      'versi_rapor': 4,
    });
  });

  testWidgets('list, server notices and read-only unavailable-period review', (
    tester,
  ) async {
    final remote = _Fake(_page(canReview: false));
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();
    expect(find.text('Periode belum berakhir.'), findsOneWidget);
    expect(find.byKey(const Key('sts-review-selected')), findsNothing);
    await _tap(tester, 'sts-student-71');
    expect(find.byKey(const Key('sts-save')), findsNothing);
    await _visible(tester, 'sts-incident-71-laporan-9');
    expect(
      tester
          .widget<TextField>(
            find.descendant(
              of: find.byKey(const Key('sts-incident-71-laporan-9')),
              matching: find.byType(TextField),
            ),
          )
          .readOnly,
      isTrue,
    );
    expect(remote.saved, isNull);
  });

  testWidgets(
    'single review requires confirmation, sends exact snapshot and refreshes',
    (tester) async {
      final remote = _Fake(_page());
      await tester.pumpWidget(_app(remote));
      await tester.pumpAndSettle();
      await _tap(tester, 'sts-student-71');
      await _visible(tester, 'sts-save');
      expect(
        tester
            .widget<FilledButton>(find.byKey(const Key('sts-save')))
            .onPressed,
        isNull,
      );
      await _visible(tester, 'sts-incident-71-laporan-9', delta: -250);
      await tester.enterText(
        find.byKey(const Key('sts-incident-71-laporan-9')),
        'Ringkasan diperiksa',
      );
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await _tap(tester, 'sts-confirm-71');
      await _tap(tester, 'sts-save');
      expect(remote.saved!['anggota_ids'], [71]);
      expect(remote.saved!['guru_bk_id'], 5);
      expect(remote.saved!['wakil_kesiswaan_id'], 8);
      final row = (remote.saved!['siswa'] as Map)['71'] as Map;
      expect(row['versi'], 3);
      expect(row['sidik_sumber'], 'a' * 64);
      expect((row['baris'] as Map)['laporan-9'], {
        'kejadian': 'Ringkasan diperiksa',
        'tindakan': 'Teguran lisan',
      });
      expect(remote.calls.length, 2);
      expect(find.text('Lampiran Perilaku STS'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('bulk requires confirmation separately for each student', (
    tester,
  ) async {
    final remote = _Fake(_page(twoStudents: true));
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pilih semua siswa'));
    await _tap(tester, 'sts-review-selected');
    await _tap(tester, 'sts-confirm-71');
    await _visible(tester, 'sts-save');
    expect(
      tester.widget<FilledButton>(find.byKey(const Key('sts-save'))).onPressed,
      isNull,
    );
    await _tap(tester, 'sts-confirm-72', delta: -200);
    await _tap(tester, 'sts-save');
    expect(remote.saved!['anggota_ids'], [71, 72]);
    expect((remote.saved!['siswa'] as Map).keys, ['71', '72']);
  });

  testWidgets('multiple signers are not silently auto-selected', (
    tester,
  ) async {
    final remote = _Fake(_page(multipleSigners: true));
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();
    await _tap(tester, 'sts-student-71');
    await _tap(tester, 'sts-confirm-71');
    await _visible(tester, 'sts-save');
    expect(
      tester.widget<FilledButton>(find.byKey(const Key('sts-save'))).onPressed,
      isNull,
    );
    expect(
      find.text('Pilih kedua penandatangan secara eksplisit.'),
      findsOneWidget,
    );
    expect(remote.saved, isNull);
  });

  testWidgets(
    'stale validation error never auto-reloads fingerprints or retries a save',
    (tester) async {
      final remote = _Fake(_page())
        ..saveError = const ValidationException('Sumber telah berubah.');
      await tester.pumpWidget(_app(remote));
      await tester.pumpAndSettle();
      await _tap(tester, 'sts-student-71');
      await _tap(tester, 'sts-confirm-71');
      await _tap(tester, 'sts-save');
      await _visible(tester, 'sts-save');
      expect(
        find.textContaining('Tidak ada penyimpanan otomatis.'),
        findsOneWidget,
      );
      expect(
        tester
            .widget<FilledButton>(find.byKey(const Key('sts-save')))
            .onPressed,
        isNull,
      );
      expect(remote.calls.length, 1);
      expect(remote.saveCalls, 1);
    },
  );

  testWidgets(
    'changing activity does not carry the old class into the request',
    (tester) async {
      final remote = _Fake(_page());
      await tester.pumpWidget(_app(remote));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('sts-activity')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('STS berikutnya').last);
      await tester.pumpAndSettle();
      expect(remote.calls.last, (activityId: 2, classId: null));
    },
  );

  testWidgets('load errors offer retry and empty scope does not offer save', (
    tester,
  ) async {
    final remote = _Fake(_page(empty: true))
      ..fetchError = const NetworkException('Koneksi belum tersedia.');
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();
    expect(find.text('Koneksi belum tersedia.'), findsOneWidget);
    remote.fetchError = null;
    await tester.tap(find.text('Coba lagi'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Belum ada kegiatan STS'), findsOneWidget);
    expect(find.byKey(const Key('sts-save')), findsNothing);
  });

  testWidgets('editing requires confirmation before leaving without saving', (
    tester,
  ) async {
    final remote = _Fake(_page());
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();
    await _tap(tester, 'sts-student-71');
    await _tap(tester, 'sts-confirm-71');
    await tester.tap(find.byIcon(Icons.arrow_back).last);
    await tester.pumpAndSettle();
    expect(
      find.text('Perubahan yang belum disimpan akan dibuang.'),
      findsOneWidget,
    );
    await tester.tap(find.text('Lanjutkan'));
    await tester.pumpAndSettle();
    expect(find.text('Periksa Lampiran STS'), findsOneWidget);
    expect(remote.saved, isNull);
  });

  testWidgets(
    'narrow screen with enlarged text has no overflow in list or review',
    (tester) async {
      tester.view.physicalSize = const Size(320, 720);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(_app(_Fake(_page()), scale: 1.5));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await _tap(tester, 'sts-student-71');
      await _visible(tester, 'sts-save');
      expect(tester.takeException(), isNull);
    },
  );
}

Widget _app(_Fake remote, {double scale = 1}) => ProviderScope(
  overrides: [stsBehaviorRemoteDataSourceProvider.overrideWithValue(remote)],
  child: MaterialApp(
    theme: AppTheme.light,
    builder: (context, child) => MediaQuery(
      data: MediaQuery.of(context)
          .copyWith(textScaler: TextScaler.linear(scale)),
      child: child!,
    ),
    home: const StsBehaviorView(),
  ),
);

Future<void> _visible(
  WidgetTester tester,
  String key, {
  double delta = 220,
}) async {
  await tester.scrollUntilVisible(
    find.byKey(Key(key)),
    delta,
    scrollable: find
        .descendant(
          of: find.byType(ListView).last,
          matching: find.byType(Scrollable),
        )
        .first,
    maxScrolls: 25,
  );
  await tester.ensureVisible(find.byKey(Key(key)));
  await tester.pumpAndSettle();
}

Future<void> _tap(WidgetTester tester, String key, {double delta = 220}) async {
  await _visible(tester, key, delta: delta);
  await tester.tap(find.byKey(Key(key)));
  await tester.pumpAndSettle();
}

class _Fake implements StsBehaviorRemoteDataSource {
  _Fake(this.page);
  final StsBehaviorPage page;
  final calls = <({int? activityId, int? classId})>[];
  Map<String, dynamic>? saved;
  Object? fetchError, saveError;
  int saveCalls = 0;
  @override
  Future<StsBehaviorPage> fetch({int? activityId, int? classId}) async {
    calls.add((activityId: activityId, classId: classId));
    if (fetchError != null) throw fetchError!;
    return page;
  }

  @override
  Future<void> saveContent(
    int activityId,
    int classId,
    Map<String, dynamic> data,
  ) async {
    saved = data;
  }

  @override
  Future<void> saveReviews(
    int activityId,
    int classId,
    Map<String, dynamic> data,
  ) async {
    saveCalls++;
    saved = data;
    if (saveError != null) throw saveError!;
  }
}

StsBehaviorPage _page({
  bool canReview = true,
  bool twoStudents = false,
  bool multipleSigners = false,
  bool empty = false,
}) => StsBehaviorPage.fromJson({
  'filter': {'kegiatan_id': empty ? null : 1, 'kelas_id': empty ? null : 7},
  'opsi': {
    'kegiatan': empty
        ? []
        : [
            {'id': 1, 'label': 'STS Semester I'},
            {'id': 2, 'label': 'STS berikutnya'},
          ],
    'kelas': empty
        ? []
        : [
            {'id': 7, 'label': 'VII.A'},
          ],
    'isi': [
      {'kode': 'semua_terverifikasi', 'label': 'Semua catatan terverifikasi'},
      {'kode': 'poin_final', 'label': 'Hanya pelanggaran berpoin final'},
    ],
  },
  'konteks': empty
      ? null
      : {
          'awal': '2026-08-01',
          'akhir': '2026-10-02',
          'tanggal_rapor': '2026-10-02',
          'versi_rapor': 4,
          'versi_isi_perilaku': 1,
          'isi': 'semua_terverifikasi',
          'pesan_kosong':
              'Tidak ada catatan pelanggaran terverifikasi pada periode ini.',
          'pesan': canReview ? [] : ['Periode belum berakhir.'],
          'dapat_simpan_isi': true,
          'dapat_periksa': canReview,
          'guru_bk': [
            {
              'id': 5,
              'label': 'Guru BK dengan nama panjang untuk pemeriksaan tampilan',
            },
            if (multipleSigners) {'id': 6, 'label': 'Guru BK kedua'},
          ],
          'wakil_kesiswaan': [
            {'id': 8, 'label': 'Wakil Kesiswaan Contoh'},
          ],
          'siswa': [
            for (final id in [71, if (twoStudents) 72])
              {
                'id': id,
                'nama': id == 71 ? 'Alya Contoh' : 'Bima Contoh',
                'nomor_absen': id - 70,
                'siap': false,
                'versi': 3,
                'sidik_sumber': 'a' * 64,
                'catatan': null,
                'guru_bk_id': null,
                'wakil_kesiswaan_id': null,
                'ringkasan': {
                  'jumlah_kejadian': id == 71 ? 1 : 0,
                  'poin_masuk': 15,
                  'poin_dikurangi': 0,
                  'saldo': 15,
                },
                'baris': id == 71
                    ? [
                        {
                          'kunci': 'laporan-9',
                          'tanggal': '2026-09-01',
                          'kejadian': 'Pelanggaran terverifikasi',
                          'tindakan': 'Teguran lisan',
                          'poin': 15,
                          'status': 'Disahkan',
                        },
                      ]
                    : [],
              },
          ],
        },
});
