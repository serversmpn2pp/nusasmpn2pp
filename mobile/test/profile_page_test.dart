import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/profile/data/my_profile_remote_data_source.dart';
import 'package:nusa/features/profile/domain/my_profile.dart';
import 'package:nusa/features/profile/presentation/profile_page.dart';

void main() {
  test('model profil membaca data anak dan sekolah', () {
    final profile = MyProfile.fromJson({
      'jenis': 'orang_tua',
      'nama': 'Ibu NUSA',
      'username': 'ORT-0011223344',
      'dapat_ubah_foto': false,
      'data': {'nama_lengkap': 'Ibu NUSA', 'nomor_wa': '081234567890'},
      'anak': [
        {
          'id': 7,
          'nama': 'Siswa NUSA',
          'nisn': '0011223344',
          'utama': true,
          'sekolah': {
            'kelas': 'VIII A',
            'nomor_absen': 12,
            'tahun_pelajaran': '2026/2027',
            'wali_kelas': 'Ibu Wali',
          },
        },
      ],
    });

    expect(profile.kind, MyProfileKind.parent);
    expect(profile.details.whatsAppNumber, '081234567890');
    expect(profile.children.single.name, 'Siswa NUSA');
    expect(profile.children.single.school?.className, 'VIII A');
  });

  testWidgets('profil menyembunyikan peran dan dapat membuka formulir edit', (
    tester,
  ) async {
    final remote = _FakeProfileRemoteDataSource(_employeeProfile());
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();

    expect(find.text('Guru NUSA'), findsOneWidget);
    expect(find.text('Peran'), findsNothing);
    expect(find.text('Administrator'), findsNothing);
    expect(find.text('Informasi Akun'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('profile-edit-button')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('profile-edit-button')));
    await tester.pumpAndSettle();

    expect(find.text('Edit Profil'), findsOneWidget);
    expect(find.byKey(const Key('profile-field-nama_lengkap')), findsOneWidget);
    expect(find.byKey(const Key('profile-field-nuptk')), findsOneWidget);
  });

  testWidgets('perubahan nama profil disimpan dan langsung ditampilkan', (
    tester,
  ) async {
    final remote = _FakeProfileRemoteDataSource(_employeeProfile());
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('profile-edit-button')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('profile-edit-button')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('profile-field-nama_lengkap')),
      'Guru NUSA Baru',
    );
    await tester.scrollUntilVisible(
      find.byKey(const Key('profile-save-button')),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('profile-save-button')));
    await tester.pumpAndSettle();

    expect(remote.lastPayload?['nama_lengkap'], 'Guru NUSA Baru');
    await tester.scrollUntilVisible(
      find.text('Guru NUSA Baru'),
      -400,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Guru NUSA Baru'), findsOneWidget);
    expect(find.text('Edit Profil'), findsNothing);
  });

  testWidgets('profil siswa hanya menyediakan alamat untuk diedit', (
    tester,
  ) async {
    final remote = _FakeProfileRemoteDataSource(_studentProfile());
    await tester.binding.setSurfaceSize(const Size(360, 640));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('VIII A'),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('VIII A'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('profile-edit-button')),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('profile-edit-button')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('profile-field-alamat')), findsOneWidget);
    expect(find.byKey(const Key('profile-field-nama_lengkap')), findsNothing);
    expect(find.byKey(const Key('profile-field-nisn')), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

Widget _app(_FakeProfileRemoteDataSource remote) {
  return ProviderScope(
    overrides: [myProfileRemoteDataSourceProvider.overrideWithValue(remote)],
    child: MaterialApp(
      theme: AppTheme.light,
      home: Scaffold(
        body: ProfilePage(
          user: _user(),
          onRefreshRelatedData: () async {},
          onChangePassword: () {},
          onLogout: () async {},
          isLoggingOut: false,
        ),
      ),
    ),
  );
}

Pengguna _user({String name = 'Guru NUSA'}) => Pengguna(
  id: 1,
  nama: name,
  username: '198501012010011001',
  jenisAkun: 'Pegawai',
  administrator: true,
  wajibGantiKataSandi: false,
  peran: const ['administrator', 'guru_mapel'],
  izin: const ['beranda.akses'],
);

MyProfile _employeeProfile({String name = 'Guru NUSA'}) => MyProfile(
  kind: MyProfileKind.employee,
  name: name,
  username: '198501012010011001',
  canChangePhoto: true,
  lastLoginAt: DateTime.utc(2026, 9, 7, 10, 30),
  details: const MyProfileDetails(
    fullName: 'Guru NUSA',
    nip: '198501012010011001',
    nuptk: '1234567890123456',
    gender: 'L',
    position: 'Guru Mata Pelajaran',
    employeeType: 'Guru',
    email: 'guru@nusa.sch.id',
    phone: '081234567890',
    address: 'Padang Panjang',
  ),
  children: const [],
);

MyProfile _studentProfile() => const MyProfile(
  kind: MyProfileKind.student,
  name: 'Siswa NUSA',
  username: '0011223344',
  canChangePhoto: false,
  details: MyProfileDetails(
    fullName: 'Siswa NUSA',
    nis: '260001',
    nisn: '0011223344',
    address: 'Padang Panjang',
  ),
  school: MyProfileSchool(
    className: 'VIII A',
    attendanceNumber: 12,
    academicYear: '2026/2027',
    homeroomTeacher: 'Ibu Wali',
  ),
  children: [],
);

class _FakeProfileRemoteDataSource implements MyProfileRemoteDataSource {
  _FakeProfileRemoteDataSource(this.profile);

  MyProfile profile;
  Map<String, dynamic>? lastPayload;

  @override
  Future<MyProfile> fetch() async => profile;

  @override
  Future<MyProfileUpdateResult> update(Map<String, dynamic> payload) async {
    lastPayload = payload;
    final name = payload['nama_lengkap'] as String? ?? profile.name;
    profile = _employeeProfile(name: name);
    return MyProfileUpdateResult(
      message: 'Profil berhasil diperbarui.',
      profile: profile,
      user: _user(name: name),
    );
  }

  @override
  Future<MyProfile> updatePhoto(MyProfilePhotoFile file) async => profile;
}
