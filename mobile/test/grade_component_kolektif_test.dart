import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/features/grade_component/data/grade_component_remote_data_source.dart';
import 'package:nusa/features/grade_component/presentation/grade_component_view.dart';

void main() {
  testWidgets('komponen nilai membuat satu komponen per kelas yang dipilih', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final requests = <RequestOptions>[];
    final dio = Dio(BaseOptions(baseUrl: 'https://nusa.test/api/v1/'));
    addTearDown(dio.close);
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (request, handler) {
          requests.add(request);
          handler.resolve(
            Response<Map<String, dynamic>>(
              requestOptions: request,
              statusCode: request.method == 'POST' ? 201 : 200,
              data: request.method == 'POST'
                  ? {
                      'pesan': 'Komponen dibuat untuk 1 kelas: VIII.B. Dilewati: VIII.A Bilingual (sudah ada).',
                    }
                  : {
                      'data': {
                        'items': [],
                        'filter': {
                          'urut_tanggal':
                              request.queryParameters['urut_tanggal'],
                        },
                        'paginasi': {'halaman': 1, 'halaman_terakhir': 1},
                        'hak_akses': {
                          'dapat_kelola': true,
                          'dapat_tambah_kolektif': true,
                        },
                        'guru_mata_pelajaran': [
                          _assignment(11, 'VIII.A Bilingual', 1, 'Informatika'),
                          _assignment(12, 'VIII.B', 1, 'Informatika'),
                          _assignment(13, 'VIII.C', 2, 'IPA'),
                          _assignment(
                            14,
                            'VII.A',
                            1,
                            'Informatika',
                            previousYear: true,
                          ),
                        ],
                      },
                    },
            ),
          );
        },
      ),
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          gradeComponentRemoteDataSourceProvider.overrideWithValue(
            DioGradeComponentRemoteDataSource(dio),
          ),
        ],
        child: const MaterialApp(home: GradeComponentView()),
      ),
    );
    await tester.pumpAndSettle();
    expect(requests.single.queryParameters['urut_tanggal'], 'terbaru');
    await tester.tap(find.byKey(const Key('grade-component-date-sort')));
    await tester.pumpAndSettle();
    await tester.tap(
      find.ancestor(
        of: find.text('Terlama dahulu'),
        matching: find.byType(CheckedPopupMenuItem<String>),
      ),
    );
    await tester.pumpAndSettle();
    expect(requests.last.queryParameters['urut_tanggal'], 'terlama');
    expect(requests.last.queryParameters['halaman'], 1);

    await tester.tap(find.byKey(const Key('add-grade-component')));
    await tester.pumpAndSettle();
    expect(find.text('Urutan tampil'), findsNothing);
    final subject = find.byKey(const Key('grade-component-form-subject'));
    await tester.tap(subject);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Informatika'));
    await tester.pumpAndSettle();

    final all = find.byKey(const Key('grade-component-all-classes'));
    final a = find.byKey(const Key('grade-component-class-11'));
    final b = find.byKey(const Key('grade-component-class-12'));
    expect(a, findsOneWidget);
    expect(b, findsOneWidget);
    expect(find.byKey(const Key('grade-component-class-13')), findsNothing);
    expect(find.byKey(const Key('grade-component-class-14')), findsNothing);
    await tester.tap(a);
    await tester.pumpAndSettle();
    expect(tester.widget<CheckboxListTile>(all).value, isNull);
    await tester.tap(all);
    await tester.pumpAndSettle();
    expect(tester.widget<CheckboxListTile>(a).value, isTrue);
    expect(tester.widget<CheckboxListTile>(b).value, isTrue);

    await tester.enterText(
      find.byKey(const Key('grade-component-form-name')),
      'Proyek akhir',
    );
    await tester.tap(find.byKey(const Key('save-grade-component')));
    await tester.pumpAndSettle();
    final saved = requests.singleWhere((request) => request.method == 'POST');
    expect(saved.path, 'komponen-nilai/kolektif');
    expect(saved.data['guru_mata_pelajaran_ids'], [11, 12]);
    expect(saved.data['nama'], 'Proyek akhir');
    expect(saved.data['urutan'], isNull);
    expect(
      saved.data['tanggal_penilaian'],
      matches(RegExp(r'^\d{4}-\d{2}-\d{2}$')),
    );
    expect(find.text('Hasil simpan komponen'), findsOneWidget);
    expect(find.textContaining('Dilewati: VIII.A Bilingual'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Map<String, dynamic> _assignment(
  int id,
  String className,
  int subjectId,
  String subjectName, {
  bool previousYear = false,
}) => {
  'id': id,
  'tahun_pelajaran': {
    'id': previousYear ? 4 : 5,
    'nama': previousYear ? '2025/2026' : '2026/2027',
    'aktif': !previousYear,
  },
  'kelas': {'id': id, 'nama': className, 'tingkat': 8},
  'mata_pelajaran': {'id': subjectId, 'nama': subjectName},
  'pegawai': {'id': 9, 'nama': 'Guru Uji'},
};
