import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/identity_photo/data/identity_photo_picker.dart';
import 'package:nusa/features/identity_photo/data/identity_photo_remote_data_source.dart';
import 'package:nusa/features/identity_photo/domain/identity_photo.dart';
import 'package:nusa/features/identity_photo/presentation/identity_photo_view.dart';

void main() {
  testWidgets('foto identitas siswa rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(
      tester,
      remote: _FakeIdentityPhotoRemoteDataSource(),
      picker: _FakeIdentityPhotoPicker(),
    );

    expect(find.text('Foto Identitas'), findsOneWidget);
    expect(find.byKey(const Key('identity-photo-student-tab')), findsOneWidget);
    expect(
      find.byKey(const Key('identity-photo-academic-year')),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.text('Siswa Belum Foto Mobile'),
      250,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Siswa Belum Foto Mobile'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin memilih galeri melihat pratinjau dan mengunggah foto', (
    tester,
  ) async {
    final remote = _FakeIdentityPhotoRemoteDataSource();
    final picker = _FakeIdentityPhotoPicker();
    await _pumpView(tester, remote: remote, picker: picker);

    final uploadButton = find.byKey(
      const Key('upload-identity-photo-siswa-11'),
    );
    await tester.scrollUntilVisible(
      uploadButton,
      260,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(uploadButton);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('identity-photo-source-gallery')));
    await tester.pumpAndSettle();

    expect(find.text('Gunakan foto ini?'), findsOneWidget);
    expect(find.textContaining('foto-mobile.png'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-identity-photo-upload')));
    await tester.pumpAndSettle();

    expect(picker.sources, [IdentityPhotoSource.gallery]);
    expect(remote.uploadCalls, 1);
    expect(remote.lastUploadedPersonId, 11);
    expect(
      find.text('Foto Siswa Belum Foto Mobile berhasil diperbarui.'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('tab pegawai menampilkan filter dan daftar pegawai', (
    tester,
  ) async {
    await _pumpView(
      tester,
      remote: _FakeIdentityPhotoRemoteDataSource(),
      picker: _FakeIdentityPhotoPicker(),
    );

    await tester.tap(find.byKey(const Key('identity-photo-employee-tab')));
    await tester.pumpAndSettle();

    expect(
      find.byKey(const Key('identity-photo-employee-type')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('identity-photo-employee-status')),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.text('Pegawai Belum Foto Mobile'),
      250,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Pegawai Belum Foto Mobile'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester, {
  required IdentityPhotoRemoteDataSource remote,
  required IdentityPhotoPicker picker,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        identityPhotoRemoteDataSourceProvider.overrideWithValue(remote),
        identityPhotoPickerProvider.overrideWithValue(picker),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const IdentityPhotoView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeIdentityPhotoPicker implements IdentityPhotoPicker {
  final sources = <IdentityPhotoSource>[];

  @override
  Future<IdentityPhotoPickedFile?> pick(IdentityPhotoSource source) async {
    sources.add(source);
    return IdentityPhotoPickedFile(
      name: 'foto-mobile.png',
      bytes: Uint8List.fromList(
        base64Decode(
          'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ),
      ),
    );
  }
}

final class _FakeIdentityPhotoRemoteDataSource
    implements IdentityPhotoRemoteDataSource {
  final List<IdentityPhotoPerson> _students = [
    const IdentityPhotoPerson(
      id: 11,
      name: 'Siswa Belum Foto Mobile',
      identity: 'NISN 99110011',
      detail: 'Absen 1 · NIS 11011',
      hasPhoto: false,
      active: true,
      gender: 'L',
    ),
    const IdentityPhotoPerson(
      id: 12,
      name: 'Siswa Sudah Foto Mobile',
      identity: 'NISN 99110012',
      detail: 'Absen 2 · NIS 11012',
      hasPhoto: true,
      active: true,
      gender: 'P',
    ),
  ];
  final List<IdentityPhotoPerson> _employees = [
    const IdentityPhotoPerson(
      id: 21,
      name: 'Pegawai Belum Foto Mobile',
      identity: 'NIP 197001012020011001',
      detail: 'Guru · Guru Mata Pelajaran',
      hasPhoto: false,
      active: true,
      gender: 'P',
    ),
  ];

  int uploadCalls = 0;
  int? lastUploadedPersonId;

  @override
  Future<IdentityPhotoPage> fetch({
    required String tab,
    int? academicYearId,
    int? classId,
    required String photoStatus,
    required String employeeStatus,
    required String employeeType,
    required String query,
    required int page,
    int perPage = 20,
  }) async {
    final source = tab == 'pegawai' ? _employees : _students;
    final normalized = query.toLowerCase();
    final items = source
        .where((item) {
          final matchesPhoto =
              photoStatus == 'semua' ||
              (photoStatus == 'sudah' && item.hasPhoto) ||
              (photoStatus == 'belum' && !item.hasPhoto);
          final matchesQuery =
              normalized.isEmpty ||
              item.name.toLowerCase().contains(normalized);
          return matchesPhoto && matchesQuery;
        })
        .toList(growable: false);
    final withPhoto = source.where((item) => item.hasPhoto).length;

    return IdentityPhotoPage(
      items: items,
      summary: IdentityPhotoSummary(
        total: source.length,
        withPhoto: withPhoto,
        withoutPhoto: source.length - withPhoto,
      ),
      academicYears: const [
        IdentityPhotoAcademicYear(id: 7, name: '2029/2030', active: true),
      ],
      classes: const [IdentityPhotoClass(id: 8, name: 'VIII.F Mobile')],
      employeeTypes: const ['Guru'],
      pagination: IdentityPhotoPagination(
        page: 1,
        total: items.length,
        hasNextPage: false,
      ),
      tab: tab,
      academicYearId: tab == 'siswa' ? academicYearId ?? 7 : null,
      classId: tab == 'siswa' ? classId ?? 8 : null,
      photoStatus: photoStatus,
      employeeStatus: employeeStatus,
      employeeType: employeeType,
      query: query,
      canManageStudents: true,
      canManageEmployees: true,
    );
  }

  @override
  Future<String> upload({
    required String tab,
    required int personId,
    required IdentityPhotoPickedFile file,
  }) async {
    uploadCalls++;
    lastUploadedPersonId = personId;
    final source = tab == 'pegawai' ? _employees : _students;
    final index = source.indexWhere((item) => item.id == personId);
    final current = source[index];
    source[index] = IdentityPhotoPerson(
      id: current.id,
      name: current.name,
      identity: current.identity,
      detail: current.detail,
      photoUrl: null,
      hasPhoto: true,
      gender: current.gender,
      active: current.active,
    );
    return 'https://nusa.test/storage/foto-mobile.png';
  }
}
