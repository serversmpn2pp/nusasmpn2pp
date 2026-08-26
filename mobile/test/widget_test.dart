import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/app/app.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/core/storage/device_identity.dart';
import 'package:nusa/core/storage/token_storage.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/academic_year/data/academic_year_remote_data_source.dart';
import 'package:nusa/features/academic_year/domain/academic_year.dart';
import 'package:nusa/features/auth/data/auth_remote_data_source.dart';
import 'package:nusa/features/auth/domain/auth_session.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/class_promotion/data/class_promotion_remote_data_source.dart';
import 'package:nusa/features/class_promotion/domain/class_promotion.dart'
    as class_promotion;
import 'package:nusa/features/employee/data/employee_remote_data_source.dart';
import 'package:nusa/features/employee/domain/employee.dart' as employee;
import 'package:nusa/features/employee_account/data/employee_account_remote_data_source.dart';
import 'package:nusa/features/employee_account/domain/employee_account.dart'
    as employee_account;
import 'package:nusa/features/home/data/home_remote_data_source.dart';
import 'package:nusa/features/home/domain/home_dashboard.dart';
import 'package:nusa/features/lesson_period/data/lesson_period_remote_data_source.dart';
import 'package:nusa/features/lesson_period/domain/lesson_period.dart';
import 'package:nusa/features/login_activity/data/login_activity_remote_data_source.dart';
import 'package:nusa/features/login_activity/domain/login_activity.dart'
    as login_activity;
import 'package:nusa/features/menu/data/menu_remote_data_source.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/my_teaching_schedule/data/my_teaching_schedule_remote_data_source.dart';
import 'package:nusa/features/my_teaching_schedule/domain/my_teaching_schedule.dart'
    as my_schedule;
import 'package:nusa/features/parent_account/data/parent_account_remote_data_source.dart';
import 'package:nusa/features/parent_account/domain/parent_account.dart'
    as parent_account;
import 'package:nusa/features/role_access/data/role_access_remote_data_source.dart';
import 'package:nusa/features/role_access/domain/role_access.dart'
    as role_access;
import 'package:nusa/features/school_class/data/school_class_remote_data_source.dart';
import 'package:nusa/features/school_class/domain/school_class.dart';
import 'package:nusa/features/student/data/student_remote_data_source.dart';
import 'package:nusa/features/student/domain/student.dart';
import 'package:nusa/features/student_account/data/student_account_remote_data_source.dart';
import 'package:nusa/features/student_account/domain/student_account.dart'
    as student_account;
import 'package:nusa/features/subject/data/subject_remote_data_source.dart';
import 'package:nusa/features/subject/domain/subject.dart';
import 'package:nusa/features/teaching_assignment/data/teaching_assignment_remote_data_source.dart';
import 'package:nusa/features/teaching_assignment/domain/teaching_assignment.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

void main() {
  testWidgets('dropdown NUSA menjaga lebar dan membatasi daftar panjang', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: Padding(
            padding: const EdgeInsets.all(24),
            child: Align(
              alignment: Alignment.topCenter,
              child: NusaDropdownField<int>(
                fieldKey: const Key('long-dropdown'),
                value: 0,
                decoration: const InputDecoration(
                  labelText: 'Pilihan panjang',
                  prefixIcon: Icon(Icons.list_rounded),
                ),
                options: [
                  for (var index = 0; index < 30; index++)
                    NusaDropdownOption(
                      value: index,
                      label: 'Pilihan ${index + 1}',
                    ),
                ],
                onChanged: (_) {},
              ),
            ),
          ),
        ),
      ),
    );

    final field = find.byKey(const Key('long-dropdown'));
    final fieldRect = tester.getRect(field);
    await tester.tap(field);
    await tester.pumpAndSettle();

    final popup = find
        .byWidgetPredicate(
          (widget) => widget is Material && widget.type == MaterialType.card,
        )
        .last;
    final popupRect = tester.getRect(popup);
    expect(popupRect.left, closeTo(fieldRect.left, 1));
    expect(popupRect.width, closeTo(fieldRect.width, 1));
    expect(popupRect.height, lessThanOrEqualTo(277));
    expect(
      find.descendant(of: popup, matching: find.byType(Scrollable)),
      findsOneWidget,
    );
  });

  testWidgets('splash NUSA tampil tanpa overflow pada layar sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(_buildTestApp(remote: _FakeAuthRemoteDataSource()));
    await tester.pump();

    expect(find.text('NUSA'), findsOneWidget);
    expect(find.text('SMP NEGERI 2 PADANG PANJANG'), findsOneWidget);
    expect(find.text('Memuat...'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pump(const Duration(milliseconds: 1000));
    expect(find.text('Memuat...'), findsOneWidget);

    await tester.pump(const Duration(milliseconds: 900));
    await tester.pumpAndSettle();
  });

  testWidgets('menampilkan formulir login NUSA saat belum memiliki sesi', (
    tester,
  ) async {
    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());

    expect(find.text('NUSA'), findsOneWidget);
    expect(find.text('Selamat Datang'), findsOneWidget);
    expect(find.text('Silakan masuk untuk melanjutkan'), findsOneWidget);
    expect(find.byKey(const Key('login-username')), findsOneWidget);
    expect(find.byKey(const Key('login-password')), findsOneWidget);
    expect(find.text('NIP / NISN / ORT-NISN'), findsOneWidget);
    expect(
      find.text('Lupa kata sandi? Silakan hubungi administrator sekolah.'),
      findsOneWidget,
    );
    expect(
      find.text('Tim Teknisi SMP Negeri 2 Padang Panjang'),
      findsOneWidget,
    );
    expect(find.text('Masuk'), findsOneWidget);
  });

  testWidgets('login berhasil menyimpan token dan membuka beranda', (
    tester,
  ) async {
    final storage = _MemoryTokenStorage();
    await _pumpApp(
      tester,
      remote: _FakeAuthRemoteDataSource(),
      storage: storage,
    );

    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('Selamat pagi,'), findsOneWidget);
    expect(find.text('Pengguna Mobile Uji'), findsOneWidget);
    expect(storage.token, 'token-uji');
  });

  testWidgets('app shell membuka tab notifikasi dan profil', (tester) async {
    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());

    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('bottom-nav-3')));
    await tester.pumpAndSettle();
    expect(find.text('Informasi pengujian'), findsOneWidget);

    await tester.tap(find.byKey(const Key('bottom-nav-4')));
    await tester.pumpAndSettle();
    expect(find.text('mobile.uji'), findsOneWidget);
    expect(find.text('Guru Mata Pelajaran'), findsOneWidget);
  });

  testWidgets('kelompok menu membuka halaman ikon dan tetap dapat dicari', (
    tester,
  ) async {
    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());

    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();

    expect(find.text('Menu Administrasi'), findsOneWidget);
    expect(find.text('Data Sekolah'), findsOneWidget);
    expect(find.text('13 menu sesuai hak akses akun Anda'), findsOneWidget);

    await tester.tap(find.byKey(const Key('menu-group-data-sekolah')));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('Menu Data Sekolah'), findsOneWidget);
    expect(find.byKey(const Key('menu-item-pegawai')), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();

    await tester.enterText(find.byKey(const Key('menu-search')), 'Pegawai');
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('menu-item-pegawai')), findsOneWidget);
    expect(find.byKey(const Key('menu-item-siswa')), findsNothing);

    await tester.tap(find.byKey(const Key('menu-item-pegawai')));
    await tester.pumpAndSettle();

    expect(find.text('Data Pegawai'), findsOneWidget);
    expect(find.byKey(const Key('employee-item-31')), findsOneWidget);
  });

  testWidgets('kategori beranda membuka sub-menu ikon pada layar sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    await tester.drag(
      find.byKey(const PageStorageKey<String>('home-dashboard-scroll')),
      const Offset(0, -430),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Data Sekolah'));
    await tester.pumpAndSettle();

    expect(find.text('Menu Data Sekolah'), findsOneWidget);
    expect(find.byKey(const Key('menu-item-pegawai')), findsOneWidget);
    expect(find.byKey(const Key('menu-item-siswa')), findsOneWidget);
    expect(find.byKey(const Key('menu-item-kelas')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('login tetap rapi pada layar Android sempit', (tester) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());

    expect(find.byKey(const Key('login-username')), findsOneWidget);
    expect(find.byKey(const Key('login-password')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('beranda dapat discroll tanpa overflow pada layar sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());

    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);

    await tester.drag(
      find.byKey(const PageStorageKey<String>('home-dashboard-scroll')),
      const Offset(0, -800),
    );
    await tester.pumpAndSettle();

    expect(find.text('Jadwal Hari Ini'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('akun dengan kata sandi awal diarahkan untuk menggantinya', (
    tester,
  ) async {
    await _pumpApp(
      tester,
      remote: _FakeAuthRemoteDataSource(wajibGantiKataSandi: true),
    );

    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('Amankan akun Anda'), findsOneWidget);
    expect(find.text('Simpan kata sandi'), findsOneWidget);
  });

  testWidgets('menu dinamis membuka daftar dan detail siswa', (tester) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());

    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('Pengumuman'), findsNothing);

    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-group-data-sekolah')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('menu-item-siswa')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-item-siswa')));
    await tester.pumpAndSettle();

    expect(find.text('Data Siswa'), findsOneWidget);
    expect(find.text('Alya Mobile'), findsOneWidget);
    expect(find.byKey(const Key('student-search')), findsOneWidget);

    await tester.tap(find.byKey(const Key('student-item-21')));
    await tester.pumpAndSettle();

    expect(find.text('Detail Siswa'), findsOneWidget);
    final detailScroll = find.descendant(
      of: find.byKey(const PageStorageKey<String>('student-detail-scroll')),
      matching: find.byType(Scrollable),
    );
    await tester.scrollUntilVisible(
      find.text('Ayah Alya'),
      300,
      scrollable: detailScroll,
    );
    await tester.pumpAndSettle();
    expect(find.text('Ayah Alya'), findsOneWidget);
    expect(find.text('VIII.A'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('kelas menampilkan jadwal dan dapat mengelola anggota', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());

    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-group-data-sekolah')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('menu-item-kelas')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-item-kelas')));
    await tester.pumpAndSettle();

    expect(find.text('Data Kelas'), findsOneWidget);
    expect(find.text('VIII.A'), findsOneWidget);
    expect(find.byKey(const Key('class-search')), findsOneWidget);

    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('class-year-filter'),
      optionLabel: '2026/2027 · Aktif',
    );

    await tester.tap(find.byKey(const Key('class-item-8')));
    await tester.pumpAndSettle();

    expect(find.text('Detail Kelas'), findsOneWidget);
    expect(find.text('Wali Kelas VIII A'), findsOneWidget);

    await tester.tap(find.byKey(const Key('class-detail-tab-1')));
    await tester.pumpAndSettle();
    expect(find.text('Alya Anggota'), findsOneWidget);

    await tester.tap(find.byKey(const Key('add-class-member')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('candidate-student-search')), findsOneWidget);
    expect(find.text('Bima Calon Anggota'), findsOneWidget);
    tester.testTextInput.hide();
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('candidate-student-22')));
    await tester.pumpAndSettle();
    expect(find.textContaining('Tambahkan Bima'), findsOneWidget);
    await tester.tap(find.byKey(const Key('submit-member-form')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('class-member-22')), findsOneWidget);

    await tester.tap(find.byKey(const Key('class-member-menu-32')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Keluarkan'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-delete-class-member')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('class-member-22')), findsNothing);

    await tester.tap(find.byKey(const Key('class-detail-tab-2')));
    await tester.pumpAndSettle();
    expect(find.text('Jadwal Mingguan'), findsOneWidget);
    expect(find.text('Matematika'), findsOneWidget);
    expect(find.text('Guru Matematika'), findsOneWidget);
    expect(find.text('Istirahat'), findsWidgets);

    await tester.ensureVisible(find.byKey(const Key('edit-schedule-slot-41')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('edit-schedule-slot-41')));
    await tester.pumpAndSettle();
    expect(find.text('Ubah Jam 1'), findsOneWidget);
    await tester.tap(find.byKey(const Key('schedule-choice-kegiatan-61')));
    await tester.ensureVisible(find.byKey(const Key('save-schedule-slot')));
    await tester.tap(find.byKey(const Key('save-schedule-slot')));
    await tester.pumpAndSettle();
    expect(find.text('Pramuka'), findsOneWidget);
    expect(find.text('Guru Matematika'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('menu Jadwal Pelajaran membuka kelas langsung pada tab jadwal', (
    tester,
  ) async {
    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-group-akademik')));
    await tester.pumpAndSettle();
    await tester.drag(
      find.byKey(const PageStorageKey<String>('menu-group-scroll-akademik')),
      const Offset(0, -300),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-item-jadwal-pelajaran')));
    await tester.pumpAndSettle();

    expect(find.text('Jadwal Pelajaran'), findsOneWidget);
    await tester.tap(find.byKey(const Key('class-item-8')));
    await tester.pumpAndSettle();

    expect(find.text('Jadwal Mingguan'), findsOneWidget);
    expect(find.text('Matematika'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('modul Jam Pelajaran membuka daftar dan menambah slot', (
    tester,
  ) async {
    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-group-akademik')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(
      find.byKey(const Key('menu-item-jam-pelajaran')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-item-jam-pelajaran')));
    await tester.pumpAndSettle();

    expect(find.text('Jam Pelajaran'), findsOneWidget);
    expect(find.byKey(const Key('lesson-period-41')), findsOneWidget);
    await tester.tap(find.byKey(const Key('add-lesson-period')));
    await tester.pumpAndSettle();
    expect(find.text('Tambah Jam Pelajaran'), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('period-form-position'),
      optionLabel: 'Di awal hari',
    );
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('period-form-type'),
      optionLabel: 'Istirahat',
    );
    await tester.ensureVisible(find.byKey(const Key('save-lesson-period')));
    await tester.tap(find.byKey(const Key('save-lesson-period')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('lesson-period-43')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('modul Guru Mata Pelajaran menambah penugasan kelas', (
    tester,
  ) async {
    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-group-akademik')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(
      find.byKey(const Key('menu-item-guru-mata-pelajaran')),
    );
    await tester.tap(find.byKey(const Key('menu-item-guru-mata-pelajaran')));
    await tester.pumpAndSettle();

    expect(find.text('Guru Mata Pelajaran'), findsOneWidget);
    expect(find.byKey(const Key('teaching-assignment-31')), findsOneWidget);

    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('teaching-assignment-year-filter'),
      optionLabel: '2026/2027 • Aktif',
    );

    await tester.tap(find.byKey(const Key('add-teaching-assignment')));
    await tester.pumpAndSettle();

    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('assignment-form-year'),
      optionLabel: '2026/2027',
    );
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('assignment-form-employee'),
      optionLabel: 'Guru Matematika Uji',
    );
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('assignment-form-subject'),
      optionLabel: 'Matematika Uji',
    );
    await tester.tap(find.byKey(const Key('assignment-class-8')));
    await tester.ensureVisible(find.byKey(const Key('assignment-form-type')));
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('assignment-form-type'),
      optionLabel: 'Pengampu',
    );
    await tester.ensureVisible(
      find.byKey(const Key('save-teaching-assignment')),
    );
    await tester.tap(find.byKey(const Key('save-teaching-assignment')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('teaching-assignment-32')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('modul Mata Pelajaran menambah mapel per tingkat', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-group-akademik')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-item-mata-pelajaran')));
    await tester.pumpAndSettle();

    expect(find.text('Mata Pelajaran'), findsOneWidget);
    expect(find.byKey(const Key('subject-11')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('subject-year-filter'),
      optionLabel: '2026/2027 • Aktif',
    );
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('subject-level-filter'),
      optionLabel: 'VIII',
    );

    await tester.tap(find.byKey(const Key('add-subject')));
    await tester.pumpAndSettle();
    expect(find.text('Tambah Mata Pelajaran'), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('subject-form-year'),
      optionLabel: '2026/2027 • Aktif',
    );
    await tester.enterText(
      find.byKey(const Key('subject-form-name')),
      'Bahasa Indonesia Mobile',
    );
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('subject-form-group'),
      optionLabel: 'Umum',
    );
    final formScroll = find.byKey(const Key('subject-form-scroll'));
    final level7Toggle = find.byKey(const Key('subject-level-7-active'));
    await tester.drag(formScroll, const Offset(0, -320));
    await tester.pumpAndSettle();
    await tester.tap(level7Toggle);
    await tester.pumpAndSettle();

    final level8Code = find.byKey(const Key('subject-level-8-code'));
    await tester.ensureVisible(level8Code);
    await tester.enterText(level8Code, 'BIND8');
    await tester.enterText(
      find.byKey(const Key('subject-level-8-score')),
      '75',
    );
    final level9Toggle = find.byKey(const Key('subject-level-9-active'));
    await tester.drag(formScroll, const Offset(0, -180));
    await tester.pumpAndSettle();
    await tester.tap(level9Toggle);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('save-subject')));
    await tester.tap(find.byKey(const Key('save-subject')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('subject-12')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('modul Tahun Pelajaran mengganti tahun aktif dengan konfirmasi', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-group-data-sekolah')));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('menu-item-tahun-pelajaran')),
      240,
      scrollable: find
          .descendant(
            of: find.byKey(
              const PageStorageKey<String>('menu-group-scroll-data-sekolah'),
            ),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    await tester.tap(find.byKey(const Key('menu-item-tahun-pelajaran')));
    await tester.pumpAndSettle();

    expect(find.text('Tahun Pelajaran'), findsOneWidget);
    expect(find.byKey(const Key('academic-year-5')), findsOneWidget);
    expect(find.text('Tahun pelajaran aktif'), findsOneWidget);

    await tester.tap(find.byKey(const Key('add-academic-year')));
    await tester.pumpAndSettle();
    expect(find.text('Tambah Tahun Pelajaran'), findsOneWidget);
    await tester.enterText(
      find.byKey(const Key('academic-year-form-name')),
      '2027/2028',
    );
    await tester.tap(find.byKey(const Key('academic-year-form-active')));
    await tester.pumpAndSettle();
    expect(
      find.textContaining('2026/2027 akan otomatis dinonaktifkan'),
      findsOneWidget,
    );
    await tester.tap(find.byKey(const Key('save-academic-year')));
    await tester.pumpAndSettle();

    expect(find.text('Ganti tahun aktif?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-academic-year-activation')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('academic-year-6')), findsOneWidget);
    expect(find.text('2027/2028'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('modul Data Pegawai menambah membuka detail dan mengubah data', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-group-data-sekolah')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-item-pegawai')));
    await tester.pumpAndSettle();

    expect(find.text('Data Pegawai'), findsOneWidget);
    expect(find.byKey(const Key('employee-item-31')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('employee-type-filter'),
      optionLabel: 'Guru',
    );
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('employee-type-filter'),
      optionLabel: 'Semua jenis pegawai',
    );

    await tester.tap(find.byKey(const Key('add-employee')));
    await tester.pumpAndSettle();
    expect(find.text('Tambah Pegawai'), findsWidgets);
    await tester.enterText(
      find.byKey(const Key('employee-form-name')),
      'Pegawai Baru Mobile',
    );
    await tester.enterText(
      find.byKey(const Key('employee-form-nip')),
      '198808252026081099',
    );
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('employee-form-gender'),
      optionLabel: 'Perempuan',
    );
    await tester.tap(find.byKey(const Key('save-employee')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('employee-item-32')), findsOneWidget);
    await tester.tap(find.byKey(const Key('employee-item-32')));
    await tester.pumpAndSettle();
    expect(find.text('Detail Pegawai'), findsOneWidget);
    expect(find.text('Pegawai Baru Mobile'), findsOneWidget);
    expect(find.text('198808252026081099'), findsOneWidget);

    await tester.tap(find.byKey(const Key('edit-employee')));
    await tester.pumpAndSettle();
    expect(find.text('Ubah Data Pegawai'), findsOneWidget);
    await tester.enterText(
      find.byKey(const Key('employee-form-name')),
      'Pegawai Mobile Revisi',
    );
    await tester.tap(find.byKey(const Key('save-employee')));
    await tester.pumpAndSettle();

    expect(find.text('Pegawai Mobile Revisi'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('modul Akun Pegawai mengelola akun role status dan kata sandi', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('menu-group-sistem')));
    await tester.tap(find.byKey(const Key('menu-group-sistem')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-item-akun-pegawai')));
    await tester.pumpAndSettle();

    expect(find.text('Akun Pegawai'), findsOneWidget);
    expect(find.byKey(const Key('employee-account-41')), findsOneWidget);
    expect(find.byKey(const Key('employee-account-42')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('employee-account-status-filter'),
      optionLabel: 'Belum punya akun',
    );
    expect(find.byKey(const Key('employee-account-42')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('employee-account-status-filter'),
      optionLabel: 'Semua status akun',
    );

    await tester.tap(find.byKey(const Key('employee-account-41')));
    await tester.pumpAndSettle();
    expect(find.text('Detail Akun Pegawai'), findsOneWidget);
    expect(find.text('198808252026081201'), findsWidgets);

    await tester.ensureVisible(
      find.byKey(const Key('edit-employee-account-roles')),
    );
    await tester.tap(find.byKey(const Key('edit-employee-account-roles')));
    await tester.pumpAndSettle();
    expect(find.text('Atur Role Akun'), findsOneWidget);
    await tester.tap(find.byKey(const Key('employee-role-3')));
    await tester.tap(find.byKey(const Key('save-employee-roles')));
    await tester.pumpAndSettle();
    expect(find.text('Koordinator Kurikulum'), findsOneWidget);

    await tester.ensureVisible(
      find.byKey(const Key('reset-employee-account-password')),
    );
    await tester.tap(find.byKey(const Key('reset-employee-account-password')));
    await tester.pumpAndSettle();
    expect(find.text('Reset kata sandi?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-reset-employee-password')));
    await tester.pumpAndSettle();
    expect(find.text('Wajib diganti saat login'), findsOneWidget);
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    await tester.ensureVisible(
      find.byKey(const Key('toggle-employee-account-status')),
    );
    await tester.tap(find.byKey(const Key('toggle-employee-account-status')));
    await tester.pumpAndSettle();
    expect(find.text('Nonaktifkan akun?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-toggle-employee-account')));
    await tester.pumpAndSettle();
    expect(find.text('Aktifkan Akun'), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('employee-account-42')));
    await tester.pumpAndSettle();
    expect(find.text('Akun Login Belum Tersedia'), findsOneWidget);
    await tester.tap(find.byKey(const Key('create-employee-account')));
    await tester.pumpAndSettle();
    expect(find.text('Buat akun pegawai?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-create-employee-account')));
    await tester.pumpAndSettle();
    expect(find.text('Informasi Login'), findsOneWidget);
    expect(find.text('198808252026081202'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('modul Akun Siswa mengelola akun per kelas secara native', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('menu-group-sistem')));
    await tester.tap(find.byKey(const Key('menu-group-sistem')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-item-akun-siswa')));
    await tester.pumpAndSettle();

    expect(find.text('Akun Siswa'), findsOneWidget);
    expect(find.byKey(const Key('student-account-51')), findsOneWidget);
    expect(find.byKey(const Key('student-account-52')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('student-account-status-filter'),
      optionLabel: 'Belum punya akun',
    );
    expect(find.byKey(const Key('student-account-52')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('student-account-status-filter'),
      optionLabel: 'Semua status akun',
    );
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('student-account-class-filter'),
      optionLabel: 'VII.A (2 siswa)',
    );
    expect(
      find.byKey(const Key('create-class-student-accounts')),
      findsOneWidget,
    );

    await tester.tap(find.byKey(const Key('student-account-51')));
    await tester.pumpAndSettle();
    expect(find.text('Detail Akun Siswa'), findsOneWidget);
    expect(find.text('0012345671'), findsWidgets);
    expect(find.text('12345678'), findsOneWidget);

    await tester.ensureVisible(
      find.byKey(const Key('reset-student-account-password')),
    );
    await tester.tap(find.byKey(const Key('reset-student-account-password')));
    await tester.pumpAndSettle();
    expect(find.text('Reset kata sandi?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-reset-student-password')));
    await tester.pumpAndSettle();
    expect(find.text('87654321'), findsOneWidget);
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    await tester.ensureVisible(
      find.byKey(const Key('toggle-student-account-status')),
    );
    await tester.tap(find.byKey(const Key('toggle-student-account-status')));
    await tester.pumpAndSettle();
    expect(find.text('Nonaktifkan akun?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-toggle-student-account')));
    await tester.pumpAndSettle();
    expect(find.text('Aktifkan Akun'), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-account-52')));
    await tester.pumpAndSettle();
    expect(find.text('Akun Login Belum Tersedia'), findsOneWidget);
    await tester.tap(find.byKey(const Key('create-student-account')));
    await tester.pumpAndSettle();
    expect(find.text('Buat akun siswa?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-create-student-account')));
    await tester.pumpAndSettle();
    expect(find.text('Informasi Login'), findsOneWidget);
    expect(find.text('11223344'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('modul Akun Orang Tua mengelola akun per kelas secara native', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('menu-group-sistem')));
    await tester.tap(find.byKey(const Key('menu-group-sistem')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(
      find.byKey(const Key('menu-item-akun-orang-tua')),
    );
    await tester.tap(find.byKey(const Key('menu-item-akun-orang-tua')));
    await tester.pumpAndSettle();

    expect(find.text('Akun Orang Tua'), findsOneWidget);
    expect(find.byKey(const Key('parent-account-61')), findsOneWidget);
    expect(find.byKey(const Key('parent-account-62')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('parent-account-status-filter'),
      optionLabel: 'Belum punya akun',
    );
    expect(find.byKey(const Key('parent-account-62')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('parent-account-status-filter'),
      optionLabel: 'Semua status akun',
    );
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('parent-account-class-filter'),
      optionLabel: 'VII.A (2 siswa)',
    );
    expect(
      find.byKey(const Key('create-class-parent-accounts')),
      findsOneWidget,
    );

    await tester.tap(find.byKey(const Key('parent-account-61')));
    await tester.pumpAndSettle();
    expect(find.text('Detail Akun Orang Tua'), findsOneWidget);
    expect(find.text('Bapak Akun Mobile'), findsWidgets);
    expect(find.text('Ibu Kontak Mobile'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('ORT-2012345671'), 250);
    await tester.pumpAndSettle();
    expect(find.text('ORT-2012345671'), findsOneWidget);
    expect(find.text('22334455'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('reset-parent-account-password')),
      250,
    );
    await tester.tap(find.byKey(const Key('reset-parent-account-password')));
    await tester.pumpAndSettle();
    expect(find.text('Reset kata sandi?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-reset-parent-password')));
    await tester.pumpAndSettle();
    expect(find.text('99887766'), findsOneWidget);
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('toggle-parent-account-status')),
      250,
    );
    await tester.tap(find.byKey(const Key('toggle-parent-account-status')));
    await tester.pumpAndSettle();
    expect(find.text('Nonaktifkan akun?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-toggle-parent-account')));
    await tester.pumpAndSettle();
    expect(find.text('Aktifkan Akun'), findsOneWidget);
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('parent-account-62')));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('create-parent-account')),
      250,
    );
    expect(find.text('Akun Login Belum Tersedia'), findsOneWidget);
    await tester.drag(find.byType(ListView).last, const Offset(0, -120));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('create-parent-account')));
    await tester.pumpAndSettle();
    expect(find.text('Buat akun orang tua?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-create-parent-account')));
    await tester.pumpAndSettle();
    expect(find.text('Informasi Login Orang Tua'), findsOneWidget);
    expect(find.text('ORT-2012345672'), findsWidgets);
    expect(find.text('55667788'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('modul Aktivitas Login memantau pengguna dan percobaan native', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('bottom-nav-2')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('menu-group-sistem')));
    await tester.tap(find.byKey(const Key('menu-group-sistem')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(
      find.byKey(const Key('menu-item-aktivitas-login')),
    );
    await tester.tap(find.byKey(const Key('menu-item-aktivitas-login')));
    await tester.pumpAndSettle();

    expect(find.text('Aktivitas Login'), findsOneWidget);
    expect(find.byKey(const Key('login-activity-user-71')), findsOneWidget);
    expect(find.byKey(const Key('login-activity-user-72')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('login-status-filter'),
      optionLabel: 'Belum pernah login',
    );
    expect(find.byKey(const Key('login-activity-user-72')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('login-status-filter'),
      optionLabel: 'Semua akun',
    );

    await tester.tap(find.byKey(const Key('login-activity-user-71')));
    await tester.pumpAndSettle();
    expect(find.text('Riwayat'), findsOneWidget);
    expect(find.byKey(const Key('login-attempt-901')), findsOneWidget);
    expect(find.byKey(const Key('login-attempt-902')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('login-attempt-status-filter'),
      optionLabel: 'Gagal',
    );
    expect(find.byKey(const Key('login-attempt-902')), findsOneWidget);
    await _expectDropdownMatchesField(
      tester,
      fieldKey: const Key('login-device-filter'),
      optionLabel: 'Windows',
    );

    await tester.tap(find.byKey(const Key('login-attempt-902')));
    await tester.pumpAndSettle();
    expect(find.text('Detail Aktivitas Login'), findsOneWidget);
    expect(find.text('Percobaan Login'), findsOneWidget);
    expect(find.text('Gagal'), findsWidgets);
    expect(find.text('10.10.10.22'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('copy-login-user-agent')),
      240,
    );
    expect(
      find.text('Mozilla/5.0 (Windows NT 10.0) Edge/151.0'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Jadwal Mengajar Saya hanya menampilkan jadwal akun guru per hari',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
      await tester.enterText(
        find.byKey(const Key('login-username')),
        'mobile.uji',
      );
      await tester.enterText(
        find.byKey(const Key('login-password')),
        'RahasiaNusa123',
      );
      await tester.tap(find.byKey(const Key('login-submit')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('bottom-nav-2')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('menu-group-akademik')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(
        find.byKey(const Key('menu-item-jadwal-mengajar-saya')),
      );
      await tester.tap(find.byKey(const Key('menu-item-jadwal-mengajar-saya')));
      await tester.pumpAndSettle();

      expect(find.text('Jadwal Mengajar Saya'), findsOneWidget);
      expect(find.text('Guru Mobile Uji'), findsOneWidget);
      expect(find.text('Matematika Mobile'), findsOneWidget);
      expect(find.text('VIII.A'), findsOneWidget);
      await _expectDropdownMatchesField(
        tester,
        fieldKey: const Key('my-teaching-year'),
        optionLabel: '2025/2026',
      );
      await tester.ensureVisible(find.byKey(const Key('teaching-day-selasa')));
      await tester.tap(find.byKey(const Key('teaching-day-selasa')));
      await tester.pumpAndSettle();

      expect(find.text('Bahasa Indonesia Lama'), findsOneWidget);
      expect(find.text('VII.A'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'modul Kenaikan Kelas memproses penempatan lintas tahun secara native',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
      await tester.enterText(
        find.byKey(const Key('login-username')),
        'mobile.uji',
      );
      await tester.enterText(
        find.byKey(const Key('login-password')),
        'RahasiaNusa123',
      );
      await tester.tap(find.byKey(const Key('login-submit')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('bottom-nav-2')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('menu-group-data-sekolah')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(
        find.byKey(const Key('menu-item-kenaikan-kelas')),
      );
      await tester.tap(find.byKey(const Key('menu-item-kenaikan-kelas')));
      await tester.pumpAndSettle();

      expect(find.text('Kenaikan Kelas'), findsOneWidget);
      await _expectDropdownMatchesField(
        tester,
        fieldKey: const Key('promotion-source-year'),
        optionLabel: '2025/2026',
      );
      await _expectDropdownMatchesField(
        tester,
        fieldKey: const Key('promotion-destination-year'),
        optionLabel: '2026/2027 · Aktif',
      );
      await _expectDropdownMatchesField(
        tester,
        fieldKey: const Key('promotion-source-class'),
        optionLabel: 'VII.A · 2 siswa',
      );

      expect(find.byKey(const Key('promotion-member-31')), findsOneWidget);
      await tester.scrollUntilVisible(
        find.byKey(const Key('promotion-member-32')),
        220,
        scrollable: find
            .descendant(
              of: find.byKey(
                const PageStorageKey<String>('class-promotion-scroll'),
              ),
              matching: find.byType(Scrollable),
            )
            .first,
      );
      expect(find.byKey(const Key('promotion-member-32')), findsOneWidget);
      await tester.drag(
        find.byKey(const PageStorageKey<String>('class-promotion-scroll')),
        const Offset(0, -180),
      );
      await tester.pumpAndSettle();
      await _expectDropdownMatchesField(
        tester,
        fieldKey: const Key('promotion-target-32'),
        optionLabel: 'Lewati (tidak diubah)',
      );
      await tester.ensureVisible(
        find.byKey(const Key('process-class-promotion')),
      );
      await tester.tap(find.byKey(const Key('process-class-promotion')));
      await tester.pumpAndSettle();
      expect(find.text('Proses kenaikan kelas?'), findsOneWidget);
      expect(find.textContaining('1 siswa akan ditempatkan'), findsOneWidget);
      await tester.tap(find.byKey(const Key('confirm-class-promotion')));
      await tester.pumpAndSettle();

      expect(find.text('Ringkasan Kenaikan Kelas'), findsOneWidget);
      expect(
        find.textContaining('1 dari 2 siswa berhasil ditempatkan'),
        findsOneWidget,
      );
      await tester.tap(find.byKey(const Key('close-promotion-result')));
      await tester.pumpAndSettle();
      expect(find.textContaining('Saat ini sudah di VIII.A'), findsNWidgets(2));
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'modul Role dan Hak Akses mengelola role dan izin secara native',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());
      await tester.enterText(
        find.byKey(const Key('login-username')),
        'mobile.uji',
      );
      await tester.enterText(
        find.byKey(const Key('login-password')),
        'RahasiaNusa123',
      );
      await tester.tap(find.byKey(const Key('login-submit')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('bottom-nav-2')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('menu-group-sistem')));
      await tester.tap(find.byKey(const Key('menu-group-sistem')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(
        find.byKey(const Key('menu-item-role-hak-akses')),
      );
      await tester.tap(find.byKey(const Key('menu-item-role-hak-akses')));
      await tester.pumpAndSettle();

      expect(find.text('Role & Hak Akses'), findsOneWidget);
      expect(find.byKey(const Key('role-1')), findsOneWidget);
      expect(find.byKey(const Key('role-2')), findsOneWidget);
      await _expectDropdownMatchesField(
        tester,
        fieldKey: const Key('role-status-filter'),
        optionLabel: 'Aktif',
      );

      await tester.tap(find.byKey(const Key('add-role')));
      await tester.pumpAndSettle();
      expect(find.text('Tambah Role'), findsWidgets);
      await tester.enterText(
        find.byKey(const Key('role-name')),
        'Koordinator Literasi',
      );
      await tester.ensureVisible(find.byKey(const Key('save-role')));
      await tester.tap(find.byKey(const Key('save-role')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('role-3')), findsOneWidget);
      await tester.ensureVisible(find.byKey(const Key('role-3')));
      await tester.drag(find.byType(ListView).last, const Offset(0, -180));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('role-3')));
      await tester.pumpAndSettle();
      expect(find.text('Detail Role'), findsOneWidget);
      expect(find.text('Koordinator Literasi'), findsOneWidget);

      await tester.tap(find.byKey(const Key('edit-role')));
      await tester.pumpAndSettle();
      expect(find.text('Ubah Role'), findsWidgets);
      await tester.enterText(
        find.byKey(const Key('role-name')),
        'Koordinator Literasi Digital',
      );
      await tester.ensureVisible(
        find.byKey(const Key('select-all-permissions')),
      );
      await tester.tap(find.byKey(const Key('select-all-permissions')));
      await tester.ensureVisible(find.byKey(const Key('save-role')));
      await tester.tap(find.byKey(const Key('save-role')));
      await tester.pumpAndSettle();
      expect(find.text('Koordinator Literasi Digital'), findsOneWidget);
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.byKey(const Key('deactivate-role')));
      await tester.tap(find.byKey(const Key('deactivate-role')));
      await tester.pumpAndSettle();
      expect(find.text('Nonaktifkan role?'), findsOneWidget);
      await tester.tap(find.byKey(const Key('confirm-deactivate-role')));
      await tester.pumpAndSettle();
      expect(find.text('Role Sudah Nonaktif'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}

Future<void> _expectDropdownMatchesField(
  WidgetTester tester, {
  required Key fieldKey,
  required String optionLabel,
}) async {
  final field = find.byKey(fieldKey);
  await tester.ensureVisible(field);
  final fieldRect = tester.getRect(field);

  await tester.tap(field);
  await tester.pumpAndSettle();

  final popup = find
      .byWidgetPredicate(
        (widget) => widget is Material && widget.type == MaterialType.card,
      )
      .last;
  final popupRect = tester.getRect(popup);
  expect(popupRect.left, closeTo(fieldRect.left, 1));
  expect(popupRect.width, closeTo(fieldRect.width, 1));

  await tester.tap(find.text(optionLabel).last);
  await tester.pumpAndSettle();
}

Future<void> _pumpApp(
  WidgetTester tester, {
  required AuthRemoteDataSource remote,
  _MemoryTokenStorage? storage,
}) async {
  await tester.pumpWidget(_buildTestApp(remote: remote, storage: storage));

  await tester.pumpAndSettle();
}

Widget _buildTestApp({
  required AuthRemoteDataSource remote,
  _MemoryTokenStorage? storage,
}) {
  return ProviderScope(
    overrides: [
      appConfigProvider.overrideWithValue(
        AppConfig(
          environment: AppEnvironment.development,
          apiBaseUri: Uri.parse('http://10.0.2.2:8000/api/v1/'),
        ),
      ),
      tokenStorageProvider.overrideWithValue(storage ?? _MemoryTokenStorage()),
      deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
      authRemoteDataSourceProvider.overrideWithValue(remote),
      homeRemoteDataSourceProvider.overrideWithValue(
        _FakeHomeRemoteDataSource(),
      ),
      menuRemoteDataSourceProvider.overrideWithValue(
        _FakeMenuRemoteDataSource(),
      ),
      studentRemoteDataSourceProvider.overrideWithValue(
        _FakeStudentRemoteDataSource(),
      ),
      schoolClassRemoteDataSourceProvider.overrideWithValue(
        _FakeSchoolClassRemoteDataSource(),
      ),
      lessonPeriodRemoteDataSourceProvider.overrideWithValue(
        _FakeLessonPeriodRemoteDataSource(),
      ),
      teachingAssignmentRemoteDataSourceProvider.overrideWithValue(
        _FakeTeachingAssignmentRemoteDataSource(),
      ),
      subjectRemoteDataSourceProvider.overrideWithValue(
        _FakeSubjectRemoteDataSource(),
      ),
      academicYearRemoteDataSourceProvider.overrideWithValue(
        _FakeAcademicYearRemoteDataSource(),
      ),
      employeeRemoteDataSourceProvider.overrideWithValue(
        _FakeEmployeeRemoteDataSource(),
      ),
      employeeAccountRemoteDataSourceProvider.overrideWithValue(
        _FakeEmployeeAccountRemoteDataSource(),
      ),
      studentAccountRemoteDataSourceProvider.overrideWithValue(
        _FakeStudentAccountRemoteDataSource(),
      ),
      parentAccountRemoteDataSourceProvider.overrideWithValue(
        _FakeParentAccountRemoteDataSource(),
      ),
      loginActivityRemoteDataSourceProvider.overrideWithValue(
        _FakeLoginActivityRemoteDataSource(),
      ),
      roleAccessRemoteDataSourceProvider.overrideWithValue(
        _FakeRoleAccessRemoteDataSource(),
      ),
      classPromotionRemoteDataSourceProvider.overrideWithValue(
        _FakeClassPromotionRemoteDataSource(),
      ),
      myTeachingScheduleRemoteDataSourceProvider.overrideWithValue(
        _FakeMyTeachingScheduleRemoteDataSource(),
      ),
    ],
    child: const NusaApp(),
  );
}

final class _FakeHomeRemoteDataSource implements HomeRemoteDataSource {
  @override
  Future<HomeDashboard> fetchDashboard() async {
    return HomeDashboard(
      generatedAt: DateTime(2026, 8, 24, 8, 15),
      greeting: 'Selamat pagi',
      dayName: 'Senin',
      dateLabel: '24 Agustus 2026',
      monthLabel: 'Agustus 2026',
      academicYear: '2026/2027',
      employee: const EmployeeSummary(
        name: 'Pengguna Mobile Uji',
        nip: '198808242026081001',
        position: 'Guru Mata Pelajaran',
        email: 'guru.mobile@example.test',
      ),
      attendance: const AttendanceSummary(
        today: TodayAttendance(
          recorded: true,
          statusLabel: 'Hadir',
          checkIn: '06:55',
          lateMinutes: 0,
          earlyLeaveMinutes: 0,
        ),
        month: MonthlyAttendance(
          total: 3,
          present: 2,
          sick: 1,
          permitted: 0,
          officialDuty: 0,
          leave: 0,
          absent: 0,
          late: 0,
          earlyLeave: 0,
        ),
      ),
      duty: null,
      guardianship: const GuardianshipSummary(
        classCount: 0,
        classStudentCount: 0,
        menteeCount: 0,
        classes: [],
      ),
      notifications: NotificationSummary(
        unreadCount: 1,
        items: [
          AppNotification(
            id: 1,
            type: 'informasi',
            typeLabel: 'Informasi',
            title: 'Informasi pengujian',
            message: 'App shell NUSA berhasil dimuat.',
            unread: true,
            createdAt: DateTime(2026, 8, 24, 8),
            relativeTime: '15 menit yang lalu',
          ),
        ],
      ),
    );
  }
}

final class _FakeMenuRemoteDataSource implements MenuRemoteDataSource {
  @override
  Future<MenuCatalog> fetchCatalog() async {
    return MenuCatalog(
      generatedAt: DateTime(2026, 8, 24, 8, 15),
      itemCount: 13,
      groups: const [
        MenuGroup(
          code: 'data-sekolah',
          label: 'Data Sekolah',
          description: 'Data induk sekolah.',
          icon: 'school',
          items: [
            MenuEntry(
              code: 'pegawai',
              label: 'Pegawai',
              description: 'Modul Pegawai NUSA.',
              initials: 'PG',
              subgroup: 'Pegawai',
              icon: null,
              status: 'tersedia',
              route: '/pegawai',
            ),
            MenuEntry(
              code: 'siswa',
              label: 'Siswa',
              description: 'Modul Siswa NUSA.',
              initials: 'SW',
              subgroup: 'Siswa dan Kelas',
              icon: null,
              status: 'tersedia',
              route: '/siswa',
            ),
            MenuEntry(
              code: 'kelas',
              label: 'Kelas',
              description: 'Modul Kelas NUSA.',
              initials: 'KL',
              subgroup: 'Siswa dan Kelas',
              icon: null,
              status: 'tersedia',
              route: '/kelas',
            ),
            MenuEntry(
              code: 'tahun-pelajaran',
              label: 'Tahun Pelajaran',
              description: 'Periode akademik dan tahun aktif.',
              initials: 'TP',
              subgroup: 'Data Sekolah',
              icon: null,
              status: 'tersedia',
              route: '/tahun-pelajaran',
            ),
            MenuEntry(
              code: 'kenaikan-kelas',
              label: 'Kenaikan Kelas',
              description: 'Penempatan siswa lintas tahun pelajaran.',
              initials: 'KK',
              subgroup: 'Siswa dan Kelas',
              icon: null,
              status: 'tersedia',
              route: '/kenaikan-kelas',
            ),
          ],
        ),
        MenuGroup(
          code: 'akademik',
          label: 'Akademik',
          description: 'Pembelajaran dan penilaian.',
          icon: 'academic',
          items: [
            MenuEntry(
              code: 'mata-pelajaran',
              label: 'Mata Pelajaran',
              description: 'Mapel per tingkat dan tahun.',
              initials: 'MP',
              subgroup: 'Pembelajaran',
              icon: null,
              status: 'tersedia',
              route: '/mata-pelajaran',
            ),
            MenuEntry(
              code: 'guru-mata-pelajaran',
              label: 'Guru Mata Pelajaran',
              description: 'Penugasan guru per kelas.',
              initials: 'GM',
              subgroup: 'Pembelajaran',
              icon: null,
              status: 'tersedia',
              route: '/guru-mata-pelajaran',
            ),
            MenuEntry(
              code: 'jadwal-mengajar-saya',
              label: 'Jadwal Mengajar Saya',
              description: 'Jadwal pribadi sesuai akun guru.',
              initials: 'JS',
              subgroup: 'Pembelajaran',
              icon: null,
              status: 'tersedia',
              route: '/jadwal-mengajar-saya',
            ),
            MenuEntry(
              code: 'jam-pelajaran',
              label: 'Jam Pelajaran',
              description: 'Slot jam mingguan.',
              initials: 'JM',
              subgroup: 'Pembelajaran',
              icon: null,
              status: 'tersedia',
              route: '/jam-pelajaran',
            ),
            MenuEntry(
              code: 'jadwal-pelajaran',
              label: 'Jadwal Pelajaran',
              description: 'Jadwal kelas NUSA.',
              initials: 'JP',
              subgroup: 'Pembelajaran',
              icon: null,
              status: 'tersedia',
              route: '/kelas?mode=jadwal',
            ),
          ],
        ),
        MenuGroup(
          code: 'sistem',
          label: 'Sistem',
          description: 'Akun dan keamanan akses.',
          icon: 'security',
          items: [
            MenuEntry(
              code: 'akun-pegawai',
              label: 'Akun Pegawai',
              description: 'Kelola akun login pegawai.',
              initials: 'AP',
              subgroup: 'Akun Pengguna',
              icon: null,
              status: 'tersedia',
              route: '/akun-pegawai',
            ),
            MenuEntry(
              code: 'akun-siswa',
              label: 'Akun Siswa',
              description: 'Kelola akun login siswa per kelas.',
              initials: 'AS',
              subgroup: 'Akun Pengguna',
              icon: null,
              status: 'tersedia',
              route: '/akun-siswa',
            ),
            MenuEntry(
              code: 'akun-orang-tua',
              label: 'Akun Orang Tua',
              description: 'Kelola akun login orang tua per kelas.',
              initials: 'AO',
              subgroup: 'Akun Pengguna',
              icon: null,
              status: 'tersedia',
              route: '/akun-orang-tua',
            ),
            MenuEntry(
              code: 'role-hak-akses',
              label: 'Role & Hak Akses',
              description: 'Kelola role dan izin akses.',
              initials: 'RA',
              subgroup: 'Keamanan Akses',
              icon: null,
              status: 'tersedia',
              route: '/role-hak-akses',
            ),
            MenuEntry(
              code: 'aktivitas-login',
              label: 'Aktivitas Login',
              description: 'Pantau login dan percobaan masuk akun NUSA.',
              initials: 'AL',
              subgroup: 'Keamanan Akses',
              icon: null,
              status: 'tersedia',
              route: '/aktivitas-login',
            ),
          ],
        ),
        MenuGroup(
          code: 'ujian-asesmen',
          label: 'Ujian & Asesmen',
          description: 'Pusat ujian.',
          icon: 'quiz',
          items: [
            MenuEntry(
              code: 'pusat-cbt',
              label: 'Pusat CBT',
              description: 'Modul Pusat CBT NUSA.',
              initials: 'CB',
              subgroup: 'CBT',
              icon: null,
              status: 'segera_hadir',
              route: null,
            ),
          ],
        ),
      ],
    );
  }
}

final class _FakeRoleAccessRemoteDataSource
    implements RoleAccessRemoteDataSource {
  final _permissionGroups = const [
    role_access.PermissionGroup(
      name: 'Akun',
      permissions: [
        role_access.RolePermission(
          id: 1,
          name: 'Lihat role',
          code: 'peran.lihat',
          description: 'Melihat daftar role.',
        ),
        role_access.RolePermission(
          id: 2,
          name: 'Kelola role dan izin',
          code: 'peran.kelola',
          description: 'Mengatur role dan izin.',
        ),
      ],
    ),
    role_access.PermissionGroup(
      name: 'Pegawai',
      permissions: [
        role_access.RolePermission(
          id: 3,
          name: 'Lihat pegawai',
          code: 'pegawai.lihat',
        ),
      ],
    ),
  ];

  final List<role_access.RoleAccess> _roles = [
    const role_access.RoleAccess(
      id: 1,
      name: 'Administrator',
      code: 'administrator',
      description: 'Akses penuh untuk mengelola NUSA.',
      system: true,
      active: true,
      permissionCount: 3,
      userCount: 1,
      permissionPercentage: 100,
      permissionIds: [1, 2, 3],
    ),
    const role_access.RoleAccess(
      id: 2,
      name: 'Pegawai',
      code: 'pegawai',
      description: 'Akses dasar pegawai.',
      system: true,
      active: true,
      permissionCount: 1,
      userCount: 8,
      permissionPercentage: 33,
      permissionIds: [3],
    ),
  ];

  @override
  Future<role_access.RoleAccessPage> fetch({
    required String query,
    required String status,
    required int page,
  }) async {
    final normalized = query.toLowerCase();
    final items = _roles
        .where(
          (role) =>
              (normalized.isEmpty ||
                  '${role.name} ${role.code} ${role.description ?? ''}'
                      .toLowerCase()
                      .contains(normalized)) &&
              (status == 'semua' ||
                  (status == 'aktif' && role.active) ||
                  (status == 'nonaktif' && !role.active)),
        )
        .toList(growable: false);
    return role_access.RoleAccessPage(
      items: items,
      summary: role_access.RoleAccessSummary(
        total: _roles.length,
        active: _roles.where((role) => role.active).length,
        system: _roles.where((role) => role.system).length,
        additional: _roles.where((role) => !role.system).length,
        activePermissions: 3,
        connectedUsers: 9,
      ),
      pagination: role_access.RoleAccessPagination(
        page: page,
        lastPage: 1,
        total: items.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      canManage: true,
    );
  }

  @override
  Future<role_access.RoleAccessReference> fetchReference() async =>
      role_access.RoleAccessReference(
        permissionGroups: _permissionGroups,
        permissionCount: 3,
        canManage: true,
      );

  @override
  Future<role_access.RoleAccessDetail> fetchDetail(int roleId) async =>
      role_access.RoleAccessDetail(
        role: _roles.firstWhere((role) => role.id == roleId),
        permissionGroups: _permissionGroups,
        canManage: true,
      );

  @override
  Future<int> create(role_access.RoleAccessFormValue value) async {
    final id = _roles.length + 1;
    final code = value.code?.trim().isNotEmpty == true
        ? value.code!.trim()
        : value.name
              .trim()
              .toLowerCase()
              .replaceAll(RegExp('[^a-z0-9]+'), '_')
              .replaceAll(RegExp(r'^_|_$'), '');
    _roles.add(
      role_access.RoleAccess(
        id: id,
        name: value.name.trim(),
        code: code,
        description: value.description,
        system: false,
        active: value.active,
        permissionCount: value.permissionIds.length,
        userCount: 0,
        permissionPercentage: (value.permissionIds.length / 3 * 100).round(),
        permissionIds: value.permissionIds,
      ),
    );
    return id;
  }

  @override
  Future<void> update({
    required int roleId,
    required role_access.RoleAccessFormValue value,
  }) async {
    final index = _roles.indexWhere((role) => role.id == roleId);
    final current = _roles[index];
    _roles[index] = role_access.RoleAccess(
      id: current.id,
      name: value.name.trim(),
      code: current.system ? current.code : value.code?.trim() ?? current.code,
      description: value.description,
      system: current.system,
      active: current.system ? true : value.active,
      permissionCount: value.permissionIds.length,
      userCount: current.userCount,
      permissionPercentage: (value.permissionIds.length / 3 * 100).round(),
      permissionIds: value.permissionIds,
    );
  }

  @override
  Future<void> deactivate(int roleId) async {
    final index = _roles.indexWhere((role) => role.id == roleId);
    final current = _roles[index];
    _roles[index] = role_access.RoleAccess(
      id: current.id,
      name: current.name,
      code: current.code,
      description: current.description,
      system: current.system,
      active: false,
      permissionCount: current.permissionCount,
      userCount: current.userCount,
      permissionPercentage: current.permissionPercentage,
      permissionIds: current.permissionIds,
    );
  }
}

final class _FakeStudentRemoteDataSource implements StudentRemoteDataSource {
  static const _summary = StudentSummary(
    id: 21,
    name: 'Alya Mobile',
    nis: '8123',
    nisn: '0012345678',
    gender: 'P',
    active: true,
    activeClass: StudentActiveClass(
      id: 8,
      name: 'VIII.A',
      level: 8,
      attendanceNumber: 1,
      academicYear: '2026/2027',
    ),
  );

  @override
  Future<StudentPage> fetchStudents({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    final matches =
        query.isEmpty ||
        _summary.name.toLowerCase().contains(query.toLowerCase());

    return StudentPage(
      items: matches ? const [_summary] : const [],
      counts: const StudentCounts(total: 1, active: 1, inactive: 0),
      pagination: StudentPagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: matches ? 1 : 0,
        hasNextPage: false,
      ),
      query: query,
      status: status,
    );
  }

  @override
  Future<StudentDetail> fetchStudent(int id) async {
    return const StudentDetail(
      summary: _summary,
      nik: '1374012345678901',
      birthPlace: 'Padang Panjang',
      religion: 'Islam',
      parents: StudentParents(
        fatherName: 'Ayah Alya',
        fatherPhone: '081234567890',
        motherName: 'Ibu Alya',
      ),
      address: 'Padang Panjang',
    );
  }
}

final class _FakeSchoolClassRemoteDataSource
    implements SchoolClassRemoteDataSource {
  static const _year = AcademicYear(id: 5, name: '2026/2027', active: true);
  static const _teacher = HomeroomTeacher(
    id: 7,
    name: 'Wali Kelas VIII A',
    nip: '198001012010011088',
    position: 'Wali Kelas',
  );
  static const _summary = SchoolClassSummary(
    id: 8,
    name: 'VIII.A',
    level: 8,
    capacity: 32,
    activeStudentCount: 1,
    availableCapacity: 31,
    active: true,
    academicYear: _year,
    homeroomTeacher: _teacher,
  );
  final List<SchoolClassMember> _members = [
    const SchoolClassMember(
      id: 31,
      attendanceNumber: 1,
      membershipStatus: 'aktif',
      student: StudentSummary(
        id: 21,
        name: 'Alya Anggota',
        nisn: '0012345678',
        gender: 'P',
        active: true,
      ),
    ),
  ];
  String? _scheduleChoice = 'guru:51';
  String? _scheduleNotes;

  @override
  Future<SchoolClassPage> fetchClasses({
    required String query,
    required String status,
    required int page,
    int? academicYearId,
    int perPage = 15,
  }) async {
    final matches =
        query.isEmpty ||
        _summary.name.toLowerCase().contains(query.toLowerCase()) ||
        _teacher.name.toLowerCase().contains(query.toLowerCase());

    return SchoolClassPage(
      items: matches ? const [_summary] : const [],
      counts: const SchoolClassCounts(total: 1, active: 1, inactive: 0),
      academicYears: const [_year],
      pagination: SchoolClassPagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: matches ? 1 : 0,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      academicYearId: academicYearId,
    );
  }

  @override
  Future<SchoolClassDetail> fetchClass(int id) async {
    final isTeacher = _scheduleChoice == 'guru:51';
    final isActivity = _scheduleChoice == 'kegiatan:61';
    return SchoolClassDetail(
      summary: _summary,
      notes: 'Kelas unggulan',
      members: List.unmodifiable(_members),
      permissions: const SchoolClassPermissions(
        canManageMembers: true,
        canViewSchedule: true,
        canManageSchedule: true,
      ),
      schedule: SchoolClassSchedule(
        todayCode: 'senin',
        filledCount: _scheduleChoice == null ? 0 : 1,
        days: [
          ClassScheduleDay(
            code: 'senin',
            label: 'Senin',
            slots: [
              ClassScheduleSlot(
                id: 41,
                number: 1,
                label: 'Jam 1',
                startTime: '07:30',
                endTime: '08:15',
                type: 'pelajaran',
                typeLabel: 'Pelajaran',
                filled: _scheduleChoice != null,
                scheduleChoice: _scheduleChoice,
                subject: isTeacher
                    ? const ScheduleSubject(id: 5, name: 'Matematika')
                    : (isActivity
                          ? const ScheduleSubject(
                              id: 61,
                              name: 'Pramuka',
                              group: 'Ekstrakurikuler',
                            )
                          : null),
                teacher: isTeacher
                    ? const ScheduleTeacher(id: 7, name: 'Guru Matematika')
                    : null,
                notes: _scheduleNotes,
              ),
              ClassScheduleSlot(
                id: 42,
                number: 2,
                label: 'Istirahat',
                startTime: '08:15',
                endTime: '08:30',
                type: 'istirahat',
                typeLabel: 'Istirahat',
                filled: false,
              ),
            ],
          ),
        ],
      ),
    );
  }

  @override
  Future<SchoolClassCandidatePage> fetchCandidates({
    required int classId,
    required String query,
  }) async {
    const student = StudentSummary(
      id: 22,
      name: 'Bima Calon Anggota',
      nis: '8124',
      nisn: '0012345679',
      gender: 'L',
      active: true,
    );
    final matches =
        query.isEmpty ||
        student.name.toLowerCase().contains(query.toLowerCase());
    return SchoolClassCandidatePage(
      items: matches ? const [student] : const [],
      query: query,
      count: matches ? 1 : 0,
      availableCapacity: 31,
    );
  }

  @override
  Future<void> addMember({
    required int classId,
    required int studentId,
    DateTime? joinDate,
    String? notes,
  }) async {
    _members.add(
      SchoolClassMember(
        id: 32,
        attendanceNumber: _members.length + 1,
        membershipStatus: 'aktif',
        joinDate: joinDate,
        notes: notes,
        student: const StudentSummary(
          id: 22,
          name: 'Bima Calon Anggota',
          nis: '8124',
          nisn: '0012345679',
          gender: 'L',
          active: true,
        ),
      ),
    );
  }

  @override
  Future<void> updateMember({
    required int classId,
    required int memberId,
    DateTime? joinDate,
    String? notes,
  }) async {
    final index = _members.indexWhere((member) => member.id == memberId);
    if (index < 0) return;
    final member = _members[index];
    _members[index] = SchoolClassMember(
      id: member.id,
      attendanceNumber: member.attendanceNumber,
      membershipStatus: member.membershipStatus,
      student: member.student,
      joinDate: joinDate,
      leaveDate: member.leaveDate,
      notes: notes,
    );
  }

  @override
  Future<void> deleteMember({
    required int classId,
    required int memberId,
  }) async {
    _members.removeWhere((member) => member.id == memberId);
  }

  @override
  Future<ScheduleChoiceCatalog> fetchScheduleChoices({
    required int classId,
  }) async {
    return const ScheduleChoiceCatalog(
      count: 2,
      items: [
        ScheduleChoice(
          value: 'guru:51',
          type: 'guru',
          title: 'Matematika',
          subtitle: 'Guru Matematika',
          subjectId: 5,
          employeeId: 7,
        ),
        ScheduleChoice(
          value: 'kegiatan:61',
          type: 'kegiatan',
          title: 'Pramuka',
          subtitle: 'Ekstrakurikuler',
          subjectId: 61,
        ),
      ],
    );
  }

  @override
  Future<void> updateScheduleSlot({
    required int classId,
    required int slotId,
    required String? scheduleChoice,
    String? notes,
  }) async {
    _scheduleChoice = scheduleChoice;
    _scheduleNotes = notes;
  }
}

final class _FakeLessonPeriodRemoteDataSource
    implements LessonPeriodRemoteDataSource {
  final List<LessonPeriod> _items = [
    const LessonPeriod(
      id: 41,
      day: 'senin',
      dayLabel: 'Senin',
      number: 1,
      label: 'Jam ke-1',
      startTime: '07:00',
      endTime: '07:40',
      type: 'pelajaran',
      typeLabel: 'Pelajaran',
      active: true,
      activeScheduleCount: 2,
    ),
    const LessonPeriod(
      id: 42,
      day: 'senin',
      dayLabel: 'Senin',
      number: 2,
      label: 'Istirahat',
      startTime: '07:40',
      endTime: '07:55',
      type: 'istirahat',
      typeLabel: 'Istirahat',
      active: true,
      activeScheduleCount: 0,
    ),
  ];

  static const _days = [
    CodeLabel(code: 'senin', label: 'Senin'),
    CodeLabel(code: 'selasa', label: 'Selasa'),
    CodeLabel(code: 'rabu', label: 'Rabu'),
    CodeLabel(code: 'kamis', label: 'Kamis'),
    CodeLabel(code: 'jumat', label: 'Jumat'),
  ];
  static const _types = [
    CodeLabel(code: 'pelajaran', label: 'Pelajaran'),
    CodeLabel(code: 'istirahat', label: 'Istirahat'),
    CodeLabel(code: 'upacara', label: 'Upacara'),
    CodeLabel(code: 'lainnya', label: 'Lainnya'),
  ];

  @override
  Future<LessonPeriodCatalog> fetch({
    required String day,
    required String status,
  }) async {
    final filtered = _items
        .where((item) {
          final dayMatches = day == 'semua' || item.day == day;
          final statusMatches =
              status == 'semua' ||
              (status == 'aktif' && item.active) ||
              (status == 'nonaktif' && !item.active);
          return dayMatches && statusMatches;
        })
        .toList(growable: false);
    return LessonPeriodCatalog(
      items: filtered,
      counts: LessonPeriodCounts(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      days: _days,
      types: _types,
      selectedDay: day,
      status: status,
    );
  }

  @override
  Future<void> create({
    required List<String> days,
    required String insertionPosition,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  }) async {
    _items.add(
      LessonPeriod(
        id: 43,
        day: days.first,
        dayLabel: _days.firstWhere((item) => item.code == days.first).label,
        number: 3,
        label: label,
        startTime: startTime,
        endTime: endTime,
        type: type,
        typeLabel: _types.firstWhere((item) => item.code == type).label,
        active: active,
        notes: notes,
        activeScheduleCount: 0,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  }) async {
    final index = _items.indexWhere((item) => item.id == id);
    if (index < 0) return;
    final current = _items[index];
    _items[index] = LessonPeriod(
      id: current.id,
      day: current.day,
      dayLabel: current.dayLabel,
      number: current.number,
      label: label,
      startTime: startTime,
      endTime: endTime,
      type: type,
      typeLabel: _types.firstWhere((item) => item.code == type).label,
      active: active,
      notes: notes,
      activeScheduleCount: current.activeScheduleCount,
    );
  }
}

final class _FakeTeachingAssignmentRemoteDataSource
    implements TeachingAssignmentRemoteDataSource {
  static const _year = AssignmentYearOption(
    id: 5,
    name: '2026/2027',
    active: true,
  );
  static const _class = AssignmentClassOption(
    id: 8,
    name: 'VIII.A',
    level: 8,
    academicYearId: 5,
    academicYearName: '2026/2027',
  );
  static const _employee = AssignmentEmployeeOption(
    id: 7,
    name: 'Guru Matematika Uji',
    nip: '198001012010011099',
  );
  static const _subject = AssignmentSubjectOption(
    id: 9,
    code: 'MTK-8',
    name: 'Matematika Uji',
    group: 'Wajib',
    availableClassIds: [8],
  );
  final List<TeachingAssignment> _items = [
    const TeachingAssignment(
      id: 31,
      academicYear: _year,
      schoolClass: _class,
      subject: _subject,
      employee: _employee,
      assignmentType: 'pengampu',
      assignmentTypeLabel: 'Pengampu',
      active: true,
    ),
  ];

  @override
  Future<TeachingAssignmentPage> fetch({
    required String query,
    required String status,
    required int page,
    int? academicYearId,
    int perPage = 15,
  }) async {
    final filtered = _items
        .where((item) {
          final queryMatches =
              query.isEmpty ||
              (item.employee?.name.toLowerCase().contains(
                    query.toLowerCase(),
                  ) ??
                  false) ||
              (item.subject?.name.toLowerCase().contains(query.toLowerCase()) ??
                  false);
          final statusMatches =
              status == 'semua' ||
              (status == 'aktif' && item.active) ||
              (status == 'nonaktif' && !item.active);
          final yearMatches =
              academicYearId == null || item.academicYear?.id == academicYearId;
          return queryMatches && statusMatches && yearMatches;
        })
        .toList(growable: false);
    return TeachingAssignmentPage(
      items: filtered,
      counts: AssignmentCounts(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      academicYears: const [_year],
      pagination: AssignmentPagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      academicYearId: academicYearId,
      canManage: true,
    );
  }

  @override
  Future<TeachingAssignmentReference> fetchReference() async {
    return const TeachingAssignmentReference(
      academicYears: [_year],
      classes: [_class],
      employees: [_employee],
      subjects: [_subject],
      assignmentTypes: [
        AssignmentTypeOption(code: 'pengampu', label: 'Pengampu'),
        AssignmentTypeOption(code: 'pendamping', label: 'Pendamping'),
        AssignmentTypeOption(code: 'koordinator', label: 'Koordinator'),
      ],
    );
  }

  @override
  Future<void> create({
    required int academicYearId,
    required List<int> classIds,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  }) async {
    _items.add(
      TeachingAssignment(
        id: 32,
        academicYear: _year,
        schoolClass: _class,
        subject: _subject,
        employee: _employee,
        assignmentType: assignmentType,
        assignmentTypeLabel: assignmentType == 'pengampu'
            ? 'Pengampu'
            : assignmentType,
        active: active,
        notes: notes,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required int academicYearId,
    required int classId,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  }) async {
    final index = _items.indexWhere((item) => item.id == id);
    if (index < 0) return;
    _items[index] = TeachingAssignment(
      id: id,
      academicYear: _year,
      schoolClass: _class,
      subject: _subject,
      employee: _employee,
      assignmentType: assignmentType,
      assignmentTypeLabel: assignmentType,
      active: active,
      notes: notes,
    );
  }
}

final class _FakeSubjectRemoteDataSource implements SubjectRemoteDataSource {
  static const _year = SubjectAcademicYear(
    id: 5,
    name: '2026/2027',
    active: true,
  );
  final List<Subject> _items = [
    const Subject(
      id: 11,
      name: 'Matematika Mobile',
      group: 'Umum',
      assessmentType: 'angka',
      assessmentTypeLabel: 'Angka (0-100)',
      order: 1,
      active: true,
      settings: [
        SubjectLevelSetting(
          level: 8,
          code: 'MTK8',
          minimumScore: 75,
          active: true,
        ),
      ],
    ),
  ];

  @override
  Future<SubjectPage> fetch({
    required String query,
    required String status,
    required String level,
    required int page,
    int? academicYearId,
    int perPage = 15,
  }) async {
    final filtered = _items
        .where((item) {
          final queryMatches =
              query.isEmpty ||
              item.name.toLowerCase().contains(query.toLowerCase()) ||
              item.settings.any(
                (setting) =>
                    setting.code.toLowerCase().contains(query.toLowerCase()),
              );
          final statusMatches =
              status == 'semua' ||
              (status == 'aktif' && item.active) ||
              (status == 'nonaktif' && !item.active);
          final levelMatches =
              level == 'semua' ||
              item.settings.any(
                (setting) =>
                    setting.level.toString() == level && setting.active,
              );
          return queryMatches && statusMatches && levelMatches;
        })
        .toList(growable: false);

    return SubjectPage(
      items: filtered,
      counts: SubjectCounts(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      academicYears: const [_year],
      pagination: SubjectPagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      level: level,
      academicYearId: academicYearId ?? _year.id,
      canManage: true,
    );
  }

  @override
  Future<SubjectReference> fetchReference() async {
    return const SubjectReference(
      academicYears: [_year],
      groups: [
        SubjectGroup(name: 'Umum', usesPredicate: false),
        SubjectGroup(name: 'Ekstrakurikuler', usesPredicate: true),
      ],
      levels: [
        SubjectLevel(value: 7, label: 'VII'),
        SubjectLevel(value: 8, label: 'VIII'),
        SubjectLevel(value: 9, label: 'IX'),
      ],
    );
  }

  @override
  Future<void> create(SubjectFormValue value) async {
    final predicate = value.group == 'Ekstrakurikuler';
    _items.add(
      Subject(
        id: 12,
        name: value.name,
        group: value.group,
        assessmentType: predicate ? 'predikat' : 'angka',
        assessmentTypeLabel: predicate
            ? 'Predikat (SB/B/C/K)'
            : 'Angka (0-100)',
        order: value.order,
        active: value.active,
        settings: value.settings,
        notes: value.notes,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required SubjectFormValue value,
  }) async {
    final index = _items.indexWhere((item) => item.id == id);
    if (index < 0) return;
    final predicate = value.group == 'Ekstrakurikuler';
    _items[index] = Subject(
      id: id,
      name: value.name,
      group: value.group,
      assessmentType: predicate ? 'predikat' : 'angka',
      assessmentTypeLabel: predicate ? 'Predikat (SB/B/C/K)' : 'Angka (0-100)',
      order: value.order,
      active: value.active,
      settings: value.settings,
      notes: value.notes,
    );
  }
}

final class _FakeEmployeeRemoteDataSource implements EmployeeRemoteDataSource {
  final List<employee.EmployeeSummary> _items = [
    const employee.EmployeeSummary(
      id: 31,
      name: 'Antonius Pegawai Mobile',
      nip: '198808252026081001',
      nuptk: '1234567890123456',
      gender: 'L',
      employmentStatus: 'PNS',
      employeeType: 'Guru',
      primaryPosition: 'Guru Matematika',
      active: true,
    ),
    const employee.EmployeeSummary(
      id: 30,
      name: 'Petugas Tata Usaha Mobile',
      nip: '198808252026081002',
      gender: 'P',
      employmentStatus: 'PPPK',
      employeeType: 'Tenaga Kependidikan',
      primaryPosition: 'Staf Tata Usaha',
      active: false,
    ),
  ];

  final Map<int, employee.EmployeeFormValue> _values = {
    31: employee.EmployeeFormValue(
      name: 'Antonius Pegawai Mobile',
      nip: '198808252026081001',
      nuptk: '1234567890123456',
      nik: '1374012508880001',
      gender: 'L',
      birthPlace: 'Padang Panjang',
      birthDate: DateTime(1988, 8, 25),
      address: 'Padang Panjang, Sumatera Barat',
      email: 'antonius.mobile@example.test',
      phone: '081234567890',
      employmentStatus: 'PNS',
      rank: 'III/d',
      workStartDate: DateTime(2012, 1, 1),
      dutyStartDate: DateTime(2020, 7, 1),
      employeeType: 'Guru',
      primaryPosition: 'Guru Matematika',
      salarySource: 'APBN',
      lastEducation: 'S1',
      educationMajor: 'Pendidikan Matematika',
      graduationYear: 2011,
      notes: 'Data pengujian mobile.',
      active: true,
    ),
    30: const employee.EmployeeFormValue(
      name: 'Petugas Tata Usaha Mobile',
      nip: '198808252026081002',
      gender: 'P',
      employmentStatus: 'PPPK',
      employeeType: 'Tenaga Kependidikan',
      primaryPosition: 'Staf Tata Usaha',
      active: false,
    ),
  };

  @override
  Future<employee.EmployeePage> fetchEmployees({
    required String query,
    required String status,
    required String type,
    required int page,
    int perPage = 15,
  }) async {
    final filtered = _items
        .where((item) {
          final normalized = query.toLowerCase();
          final queryMatches =
              query.isEmpty ||
              item.name.toLowerCase().contains(normalized) ||
              (item.nip?.contains(query) ?? false) ||
              (item.nuptk?.contains(query) ?? false) ||
              (item.primaryPosition?.toLowerCase().contains(normalized) ??
                  false);
          final statusMatches =
              status == 'semua' ||
              (status == 'aktif' && item.active) ||
              (status == 'nonaktif' && !item.active);
          final typeMatches = type == 'semua' || item.employeeType == type;
          return queryMatches && statusMatches && typeMatches;
        })
        .toList(growable: false);

    return employee.EmployeePage(
      items: filtered,
      counts: employee.EmployeeCounts(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      types: const ['Guru', 'Tenaga Kependidikan'],
      pagination: employee.EmployeePagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      type: type,
      canManage: true,
    );
  }

  @override
  Future<employee.EmployeeDetail> fetchEmployee(int id) async {
    final summary = _items.firstWhere((item) => item.id == id);
    final value = _values[id]!;
    return employee.EmployeeDetail(
      summary: summary,
      nik: value.nik,
      birthPlace: value.birthPlace,
      birthDate: value.birthDate,
      address: value.address,
      email: value.email,
      phone: value.phone,
      rank: value.rank,
      workStartDate: value.workStartDate,
      dutyStartDate: value.dutyStartDate,
      salarySource: value.salarySource,
      lastEducation: value.lastEducation,
      educationMajor: value.educationMajor,
      graduationYear: value.graduationYear,
      notes: value.notes,
      account: employee.EmployeeAccount(
        available: id == 31,
        username: id == 31 ? value.nip : null,
        active: id == 31,
      ),
      assignmentCounts: employee.EmployeeAssignmentCounts(
        activeHomeroomClasses: id == 31 ? 1 : 0,
        activeSubjectAssignments: id == 31 ? 3 : 0,
      ),
      canManage: true,
    );
  }

  @override
  Future<void> create(employee.EmployeeFormValue value) async {
    const id = 32;
    _values[id] = value;
    _items.insert(0, _summary(id, value));
  }

  @override
  Future<void> update({
    required int id,
    required employee.EmployeeFormValue value,
  }) async {
    final index = _items.indexWhere((item) => item.id == id);
    if (index < 0) return;
    final old = _items[index];
    _values[id] = value;
    _items[index] = _summary(id, value, photoUrl: old.photoUrl);
  }

  employee.EmployeeSummary _summary(
    int id,
    employee.EmployeeFormValue value, {
    String? photoUrl,
  }) => employee.EmployeeSummary(
    id: id,
    name: value.name,
    nip: value.nip,
    nuptk: value.nuptk,
    photoUrl: photoUrl,
    gender: value.gender,
    employmentStatus: value.employmentStatus,
    employeeType: value.employeeType,
    primaryPosition: value.primaryPosition,
    active: value.active,
  );
}

final class _FakeEmployeeAccountRemoteDataSource
    implements EmployeeAccountRemoteDataSource {
  static const _roles = [
    employee_account.EmployeeAccountRole(
      id: 1,
      code: 'pegawai',
      name: 'Pegawai',
      description: 'Role dasar akun pegawai.',
      system: true,
    ),
    employee_account.EmployeeAccountRole(
      id: 2,
      code: 'guru',
      name: 'Guru',
      description: 'Akses pembelajaran guru.',
      system: true,
    ),
    employee_account.EmployeeAccountRole(
      id: 3,
      code: 'koordinator_kurikulum',
      name: 'Koordinator Kurikulum',
      description: 'Akses tambahan kurikulum.',
      system: false,
    ),
  ];

  static const _employees = [
    employee_account.AccountEmployee(
      id: 41,
      name: 'Guru Akun Mobile',
      nip: '198808252026081201',
      primaryPosition: 'Guru Matematika',
      active: true,
    ),
    employee_account.AccountEmployee(
      id: 42,
      name: 'Pegawai Belum Akun Mobile',
      nip: '198808252026081202',
      primaryPosition: 'Staf Tata Usaha',
      active: true,
    ),
  ];

  final Map<int, employee_account.ManagedEmployeeAccount> _accounts = {
    41: employee_account.ManagedEmployeeAccount(
      available: true,
      id: 71,
      username: '198808252026081201',
      active: true,
      systemAccount: false,
      mustChangePassword: false,
      lastLoginAt: DateTime(2026, 8, 25, 7, 30),
      roles: [_roles[0], _roles[1]],
    ),
  };

  @override
  Future<employee_account.EmployeeAccountPage> fetchAccounts({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    final normalized = query.toLowerCase();
    final items = _employees
        .map(_item)
        .where((item) {
          final queryMatches =
              query.isEmpty ||
              item.employee.name.toLowerCase().contains(normalized) ||
              (item.employee.nip?.contains(query) ?? false) ||
              (item.account.username?.toLowerCase().contains(normalized) ??
                  false);
          final statusMatches = switch (status) {
            'sudah' => item.account.available,
            'belum' =>
              !item.account.available && item.employee.nip?.isNotEmpty == true,
            'tanpa_nip' => item.employee.nip?.isNotEmpty != true,
            _ => true,
          };
          return queryMatches && statusMatches;
        })
        .toList(growable: false);

    return employee_account.EmployeeAccountPage(
      items: items,
      counts: employee_account.EmployeeAccountCounts(
        activeEmployees: _employees.where((item) => item.active).length,
        withNip: _employees
            .where((item) => item.nip?.isNotEmpty == true)
            .length,
        accounts: _accounts.length,
        withoutAccount: _employees
            .where(
              (item) =>
                  item.active &&
                  item.nip?.isNotEmpty == true &&
                  !_accounts.containsKey(item.id),
            )
            .length,
      ),
      roles: _roles,
      pagination: employee_account.EmployeeAccountPagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: items.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      canManage: true,
    );
  }

  @override
  Future<employee_account.EmployeeAccountDetail> fetchAccount(
    int employeeId,
  ) async => employee_account.EmployeeAccountDetail(
    item: _item(_employees.firstWhere((employee) => employee.id == employeeId)),
    roles: _roles,
    canManage: true,
  );

  @override
  Future<void> createAccount(int employeeId) async {
    final employee = _employees.firstWhere((item) => item.id == employeeId);
    _accounts[employeeId] = employee_account.ManagedEmployeeAccount(
      available: true,
      id: 70 + employeeId,
      username: employee.nip?.replaceAll(' ', ''),
      active: employee.active,
      systemAccount: false,
      mustChangePassword: true,
      roles: [_roles[0]],
    );
  }

  @override
  Future<employee_account.BulkAccountResult> createAllAccounts() async {
    var created = 0;
    for (final employee in _employees) {
      if (employee.active &&
          employee.nip?.isNotEmpty == true &&
          !_accounts.containsKey(employee.id)) {
        await createAccount(employee.id);
        created++;
      }
    }
    return employee_account.BulkAccountResult(
      created: created,
      skipped: 0,
      notes: const [],
    );
  }

  @override
  Future<void> resetPassword(int employeeId) async {
    final current = _accounts[employeeId]!;
    _accounts[employeeId] = _copyAccount(current, mustChangePassword: true);
  }

  @override
  Future<void> updateStatus({
    required int employeeId,
    required bool active,
  }) async {
    final current = _accounts[employeeId]!;
    _accounts[employeeId] = _copyAccount(current, active: active);
  }

  @override
  Future<void> updateRoles({
    required int employeeId,
    required List<int> roleIds,
  }) async {
    final current = _accounts[employeeId]!;
    final selected = _roles
        .where((role) => role.isEmployeeBase || roleIds.contains(role.id))
        .toList(growable: false);
    _accounts[employeeId] = _copyAccount(current, roles: selected);
  }

  employee_account.EmployeeAccountItem _item(
    employee_account.AccountEmployee employee,
  ) {
    final account = _accounts[employee.id];
    return employee_account.EmployeeAccountItem(
      employee: employee,
      status: account == null
          ? employee.nip?.isNotEmpty == true
                ? 'belum'
                : 'tanpa_nip'
          : account.active
          ? 'aktif'
          : 'nonaktif',
      account:
          account ??
          const employee_account.ManagedEmployeeAccount(
            available: false,
            active: false,
            systemAccount: false,
            mustChangePassword: false,
            roles: [],
          ),
    );
  }

  employee_account.ManagedEmployeeAccount _copyAccount(
    employee_account.ManagedEmployeeAccount current, {
    bool? active,
    bool? mustChangePassword,
    List<employee_account.EmployeeAccountRole>? roles,
  }) => employee_account.ManagedEmployeeAccount(
    available: current.available,
    id: current.id,
    username: current.username,
    active: active ?? current.active,
    systemAccount: current.systemAccount,
    mustChangePassword: mustChangePassword ?? current.mustChangePassword,
    lastLoginAt: current.lastLoginAt,
    roles: roles ?? current.roles,
  );
}

final class _FakeStudentAccountRemoteDataSource
    implements StudentAccountRemoteDataSource {
  static const _schoolClass = student_account.StudentAccountClass(
    id: 21,
    name: 'VII.A',
    grade: 7,
    activeStudentCount: 2,
  );

  static const _students = [
    student_account.AccountStudent(
      id: 51,
      name: 'Siswa Akun Mobile',
      nis: '2600051',
      nisn: '0012345671',
      active: true,
    ),
    student_account.AccountStudent(
      id: 52,
      name: 'Siswa Belum Akun Mobile',
      nis: '2600052',
      nisn: '0012345672',
      active: true,
    ),
  ];

  final Map<int, student_account.ManagedStudentAccount> _accounts = {
    51: student_account.ManagedStudentAccount(
      available: true,
      id: 81,
      username: '0012345671',
      active: true,
      mustChangePassword: true,
      initialPasswordAvailable: true,
      initialPassword: '12345678',
      lastLoginAt: DateTime(2026, 8, 25, 8, 10),
    ),
  };

  @override
  Future<student_account.StudentAccountPage> fetchAccounts({
    required String query,
    required String status,
    required int? classId,
    required int page,
    int perPage = 15,
  }) async {
    final normalized = query.toLowerCase();
    final items = _students
        .map(_item)
        .where((item) {
          final queryMatches =
              query.isEmpty ||
              item.student.name.toLowerCase().contains(normalized) ||
              (item.student.nis?.contains(query) ?? false) ||
              (item.student.nisn?.contains(query) ?? false) ||
              (item.account.username?.contains(query) ?? false);
          final statusMatches = switch (status) {
            'sudah' => item.account.available,
            'belum' =>
              !item.account.available && item.student.nisn?.isNotEmpty == true,
            'tanpa_nisn' => item.student.nisn?.isNotEmpty != true,
            _ => true,
          };
          return queryMatches && statusMatches;
        })
        .toList(growable: false);

    return student_account.StudentAccountPage(
      items: items,
      counts: student_account.StudentAccountCounts(
        students: _students.length,
        withAccount: _accounts.length,
        withoutAccount: _students
            .where((student) => !_accounts.containsKey(student.id))
            .length,
        withoutNisn: 0,
      ),
      classes: const [_schoolClass],
      pagination: student_account.StudentAccountPagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: items.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      classId: classId,
      academicYear: const student_account.AcademicYearSummary(
        id: 5,
        name: '2026/2027',
      ),
      canManage: true,
      canViewCredentials: true,
    );
  }

  @override
  Future<student_account.StudentAccountDetail> fetchAccount(
    int studentId,
  ) async => student_account.StudentAccountDetail(
    item: _item(_students.firstWhere((student) => student.id == studentId)),
    academicYear: const student_account.AcademicYearSummary(
      id: 5,
      name: '2026/2027',
    ),
    canManage: true,
    canViewCredentials: true,
  );

  @override
  Future<void> createAccount(int studentId) async {
    final student = _students.firstWhere((item) => item.id == studentId);
    _accounts[studentId] = student_account.ManagedStudentAccount(
      available: true,
      id: 80 + studentId,
      username: student.nisn,
      active: student.active,
      mustChangePassword: true,
      initialPasswordAvailable: true,
      initialPassword: '11223344',
    );
  }

  @override
  Future<student_account.BulkStudentAccountResult> createClassAccounts(
    int classId,
  ) async {
    var created = 0;
    for (final student in _students) {
      if (student.nisn?.isNotEmpty == true &&
          !_accounts.containsKey(student.id)) {
        await createAccount(student.id);
        created++;
      }
    }
    return student_account.BulkStudentAccountResult(
      created: created,
      skipped: _students.length - created,
      notes: const [],
    );
  }

  @override
  Future<void> resetPassword(int studentId) async {
    final current = _accounts[studentId]!;
    _accounts[studentId] = _copyAccount(
      current,
      mustChangePassword: true,
      initialPassword: '87654321',
    );
  }

  @override
  Future<void> updateStatus({
    required int studentId,
    required bool active,
  }) async {
    final current = _accounts[studentId]!;
    _accounts[studentId] = _copyAccount(current, active: active);
  }

  student_account.StudentAccountItem _item(
    student_account.AccountStudent student,
  ) {
    final account = _accounts[student.id];
    return student_account.StudentAccountItem(
      membership: const student_account.StudentAccountMembership(
        id: 91,
        attendanceNumber: 1,
        schoolClass: _schoolClass,
      ),
      student: student,
      status: account == null
          ? student.nisn?.isNotEmpty == true
                ? 'belum'
                : 'tanpa_nisn'
          : account.active
          ? 'aktif'
          : 'nonaktif',
      account:
          account ??
          const student_account.ManagedStudentAccount(
            available: false,
            active: false,
            mustChangePassword: false,
            initialPasswordAvailable: false,
          ),
    );
  }

  student_account.ManagedStudentAccount _copyAccount(
    student_account.ManagedStudentAccount current, {
    bool? active,
    bool? mustChangePassword,
    String? initialPassword,
  }) => student_account.ManagedStudentAccount(
    available: current.available,
    id: current.id,
    username: current.username,
    active: active ?? current.active,
    mustChangePassword: mustChangePassword ?? current.mustChangePassword,
    initialPasswordAvailable: true,
    initialPassword: initialPassword ?? current.initialPassword,
    lastLoginAt: current.lastLoginAt,
  );
}

final class _FakeParentAccountRemoteDataSource
    implements ParentAccountRemoteDataSource {
  static const _schoolClass = parent_account.ParentAccountClass(
    id: 31,
    name: 'VII.A',
    grade: 7,
    activeStudentCount: 2,
  );

  static const _students = [
    parent_account.ParentAccountStudent(
      id: 61,
      name: 'Siswa Orang Tua Mobile',
      nis: '2600061',
      nisn: '2012345671',
      active: true,
    ),
    parent_account.ParentAccountStudent(
      id: 62,
      name: 'Siswa Belum Akun Orang Tua',
      nis: '2600062',
      nisn: '2012345672',
      active: true,
    ),
  ];

  final Map<int, parent_account.ManagedParentAccount> _accounts = {
    61: parent_account.ManagedParentAccount(
      available: true,
      id: 91,
      username: 'ORT-2012345671',
      active: true,
      mustChangePassword: true,
      initialPasswordAvailable: true,
      initialPassword: '22334455',
      lastLoginAt: DateTime(2026, 8, 25, 8, 20),
    ),
  };

  final Map<int, parent_account.ParentGuardian> _parents = {
    61: const parent_account.ParentGuardian(
      available: true,
      primary: true,
      id: 101,
      name: 'Bapak Akun Mobile',
      phone: '081234567891',
      relationship: 'ayah',
    ),
  };

  @override
  Future<parent_account.ParentAccountPage> fetchAccounts({
    required String query,
    required String status,
    required int? classId,
    required int page,
    int perPage = 15,
  }) async {
    final normalized = query.toLowerCase();
    final items = _students
        .map(_item)
        .where((item) {
          final queryMatches =
              query.isEmpty ||
              item.student.name.toLowerCase().contains(normalized) ||
              (item.student.nis?.contains(query) ?? false) ||
              (item.student.nisn?.contains(query) ?? false) ||
              (item.parent.name?.toLowerCase().contains(normalized) ?? false) ||
              (item.account.username?.toLowerCase().contains(normalized) ??
                  false);
          final statusMatches = switch (status) {
            'aktif' => item.account.available && item.account.active,
            'nonaktif' => item.account.available && !item.account.active,
            'belum' =>
              !item.account.available && item.student.nisn?.isNotEmpty == true,
            'tanpa_nisn' => item.student.nisn?.isNotEmpty != true,
            _ => true,
          };
          return queryMatches && statusMatches;
        })
        .toList(growable: false);

    return parent_account.ParentAccountPage(
      items: items,
      counts: parent_account.ParentAccountCounts(
        students: _students.length,
        activeAccounts: _accounts.values.where((item) => item.active).length,
        inactiveAccounts: _accounts.values.where((item) => !item.active).length,
        withoutAccount: _students
            .where((student) => !_accounts.containsKey(student.id))
            .length,
        withoutNisn: 0,
      ),
      classes: const [_schoolClass],
      pagination: parent_account.ParentAccountPagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: items.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      classId: classId,
      academicYear: const parent_account.ParentAcademicYear(
        id: 5,
        name: '2026/2027',
      ),
      canManage: true,
      canViewCredentials: true,
    );
  }

  @override
  Future<parent_account.ParentAccountDetail> fetchAccount(
    int studentId,
  ) async => parent_account.ParentAccountDetail(
    item: _item(
      _students.firstWhere((student) => student.id == studentId),
      includeContacts: true,
    ),
    academicYear: const parent_account.ParentAcademicYear(
      id: 5,
      name: '2026/2027',
    ),
    canManage: true,
    canViewCredentials: true,
  );

  @override
  Future<void> createAccount(int studentId) async {
    final student = _students.firstWhere((item) => item.id == studentId);
    _accounts[studentId] = parent_account.ManagedParentAccount(
      available: true,
      id: 90 + studentId,
      username: 'ORT-${student.nisn}',
      active: true,
      mustChangePassword: true,
      initialPasswordAvailable: true,
      initialPassword: '55667788',
    );
    _parents[studentId] = parent_account.ParentGuardian(
      available: true,
      primary: true,
      id: 100 + studentId,
      name: 'Orang Tua/Wali ${student.name}',
      relationship: 'wali',
    );
  }

  @override
  Future<parent_account.BulkParentAccountResult> createClassAccounts(
    int classId,
  ) async {
    var created = 0;
    for (final student in _students) {
      if (student.nisn?.isNotEmpty == true &&
          !_accounts.containsKey(student.id)) {
        await createAccount(student.id);
        created++;
      }
    }
    return parent_account.BulkParentAccountResult(
      created: created,
      skipped: _students.length - created,
      notes: const [],
    );
  }

  @override
  Future<void> resetPassword(int studentId) async {
    final current = _accounts[studentId]!;
    _accounts[studentId] = _copyAccount(
      current,
      mustChangePassword: true,
      initialPassword: '99887766',
    );
  }

  @override
  Future<void> updateStatus({
    required int studentId,
    required bool active,
  }) async {
    final current = _accounts[studentId]!;
    _accounts[studentId] = _copyAccount(current, active: active);
  }

  parent_account.ParentAccountItem _item(
    parent_account.ParentAccountStudent student, {
    bool includeContacts = false,
  }) {
    final account = _accounts[student.id];
    return parent_account.ParentAccountItem(
      membership: const parent_account.ParentAccountMembership(
        id: 111,
        attendanceNumber: 1,
        schoolClass: _schoolClass,
      ),
      student: student,
      parent:
          _parents[student.id] ??
          const parent_account.ParentGuardian(available: false, primary: false),
      familyContacts: includeContacts
          ? const [
              parent_account.ParentFamilyContact(
                code: 'ayah',
                label: 'Ayah',
                name: 'Bapak Akun Mobile',
                phone: '081234567891',
                primary: true,
              ),
              parent_account.ParentFamilyContact(
                code: 'ibu',
                label: 'Ibu',
                name: 'Ibu Kontak Mobile',
                phone: '081234567892',
                primary: false,
              ),
              parent_account.ParentFamilyContact(
                code: 'wali',
                label: 'Wali',
                primary: false,
              ),
            ]
          : const [],
      status: account == null
          ? student.nisn?.isNotEmpty == true
                ? 'belum'
                : 'tanpa_nisn'
          : account.active
          ? 'aktif'
          : 'nonaktif',
      account:
          account ??
          const parent_account.ManagedParentAccount(
            available: false,
            active: false,
            mustChangePassword: false,
            initialPasswordAvailable: false,
          ),
    );
  }

  parent_account.ManagedParentAccount _copyAccount(
    parent_account.ManagedParentAccount current, {
    bool? active,
    bool? mustChangePassword,
    String? initialPassword,
  }) => parent_account.ManagedParentAccount(
    available: current.available,
    id: current.id,
    username: current.username,
    active: active ?? current.active,
    mustChangePassword: mustChangePassword ?? current.mustChangePassword,
    initialPasswordAvailable: true,
    initialPassword: initialPassword ?? current.initialPassword,
    lastLoginAt: current.lastLoginAt,
  );
}

final class _FakeLoginActivityRemoteDataSource
    implements LoginActivityRemoteDataSource {
  static const _administratorType = login_activity.LoginAccountType(
    code: 'administrator',
    label: 'Administrator sistem',
  );
  static const _employeeType = login_activity.LoginAccountType(
    code: 'pegawai',
    label: 'Pegawai',
  );
  static const _administratorRole = login_activity.LoginActivityRole(
    id: 1,
    code: 'administrator',
    name: 'Administrator',
  );
  static const _employeeRole = login_activity.LoginActivityRole(
    id: 2,
    code: 'pegawai',
    name: 'Pegawai',
  );

  static final _users = [
    login_activity.LoginActivityUser(
      id: 71,
      name: 'Administrator Keamanan Mobile',
      username: 'administrator.mobile',
      accountType: _administratorType,
      roles: const [_administratorRole],
      active: true,
      lastLoginAt: DateTime(2026, 8, 26, 7, 15),
      lastDevice: 'Android - Chrome',
      successCount: 1,
      failureCount: 1,
    ),
    const login_activity.LoginActivityUser(
      id: 72,
      name: 'Pegawai Belum Login Mobile',
      username: 'pegawai.belum.login',
      accountType: _employeeType,
      roles: [_employeeRole],
      active: true,
      successCount: 0,
      failureCount: 0,
    ),
  ];

  static final _attempts = [
    login_activity.LoginAttempt(
      id: 901,
      username: 'administrator.mobile',
      success: true,
      ipAddress: '10.10.10.21',
      device: const login_activity.LoginDevice(
        code: 'android',
        label: 'Android - Chrome',
      ),
      time: DateTime(2026, 8, 26, 7, 15),
      user: const login_activity.LoginAttemptUser(
        id: 71,
        name: 'Administrator Keamanan Mobile',
        username: 'administrator.mobile',
        accountType: _administratorType,
        roles: [_administratorRole],
        active: true,
      ),
    ),
    login_activity.LoginAttempt(
      id: 902,
      username: 'administrator.mobile',
      success: false,
      ipAddress: '10.10.10.22',
      device: const login_activity.LoginDevice(
        code: 'windows',
        label: 'Windows - Edge',
      ),
      time: DateTime(2026, 8, 26, 7, 10),
      user: const login_activity.LoginAttemptUser(
        id: 71,
        name: 'Administrator Keamanan Mobile',
        username: 'administrator.mobile',
        accountType: _administratorType,
        roles: [_administratorRole],
        active: true,
      ),
    ),
    login_activity.LoginAttempt(
      id: 903,
      username: 'akun.tidak.dikenal',
      success: false,
      ipAddress: '10.10.10.90',
      device: const login_activity.LoginDevice(
        code: 'android',
        label: 'Android - Browser lain',
      ),
      time: DateTime(2026, 8, 26, 6, 50),
    ),
  ];

  @override
  Future<login_activity.LoginActivityPage> fetchActivities({
    required String view,
    required String query,
    required String accountType,
    required String loginStatus,
    required String attemptStatus,
    required String device,
    required String? startDate,
    required String? endDate,
    required int page,
    int perPage = 15,
  }) async {
    final normalized = query.toLowerCase();
    final users = view == 'pengguna'
        ? _users
              .where((user) {
                final queryMatches =
                    query.isEmpty ||
                    user.name.toLowerCase().contains(normalized) ||
                    user.username.toLowerCase().contains(normalized);
                final typeMatches =
                    accountType == 'semua' ||
                    user.accountType.code == accountType;
                final statusMatches = switch (loginStatus) {
                  'pernah' => user.lastLoginAt != null,
                  'belum' => user.lastLoginAt == null,
                  _ => true,
                };
                return queryMatches && typeMatches && statusMatches;
              })
              .toList(growable: false)
        : const <login_activity.LoginActivityUser>[];
    final start = DateTime.tryParse(startDate ?? '');
    final end = DateTime.tryParse(endDate ?? '');
    final attempts = view == 'riwayat'
        ? _attempts
              .where((attempt) {
                final queryMatches =
                    query.isEmpty ||
                    attempt.displayName.toLowerCase().contains(normalized) ||
                    attempt.username.toLowerCase().contains(normalized);
                final typeMatches =
                    accountType == 'semua' ||
                    attempt.user?.accountType.code == accountType;
                final statusMatches = switch (attemptStatus) {
                  'berhasil' => attempt.success,
                  'gagal' => !attempt.success,
                  _ => true,
                };
                final deviceMatches =
                    device == 'semua' || attempt.device.code == device;
                final dateMatches =
                    (start == null ||
                        attempt.time == null ||
                        !attempt.time!.isBefore(start)) &&
                    (end == null ||
                        attempt.time == null ||
                        attempt.time!.isBefore(
                          end.add(const Duration(days: 1)),
                        ));
                return queryMatches &&
                    typeMatches &&
                    statusMatches &&
                    deviceMatches &&
                    dateMatches;
              })
              .toList(growable: false)
        : const <login_activity.LoginAttempt>[];
    final total = view == 'pengguna' ? users.length : attempts.length;

    return login_activity.LoginActivityPage(
      users: users,
      attempts: attempts,
      summary: const login_activity.LoginActivitySummary(
        accounts: 2,
        loginsToday: 1,
        neverLoggedIn: 1,
        failuresToday: 2,
      ),
      filter: login_activity.LoginActivityFilter(
        view: view,
        query: query,
        accountType: accountType,
        loginStatus: loginStatus,
        attemptStatus: attemptStatus,
        device: device,
        startDate: startDate,
        endDate: endDate,
      ),
      pagination: login_activity.LoginActivityPagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: total,
        hasNextPage: false,
      ),
    );
  }

  @override
  Future<login_activity.LoginAttemptDetail> fetchAttempt(int attemptId) async {
    final attempt = _attempts.firstWhere((item) => item.id == attemptId);
    final userAgent = switch (attempt.device.code) {
      'windows' => 'Mozilla/5.0 (Windows NT 10.0) Edge/151.0',
      'android' => 'Mozilla/5.0 (Linux; Android 16) Chrome/151.0',
      _ => 'NUSA-Mobile/1.0',
    };
    return login_activity.LoginAttemptDetail(
      attempt: login_activity.LoginAttempt(
        id: attempt.id,
        username: attempt.username,
        success: attempt.success,
        ipAddress: attempt.ipAddress,
        device: login_activity.LoginDevice(
          code: attempt.device.code,
          label: attempt.device.label,
          userAgent: userAgent,
        ),
        time: attempt.time,
        user: attempt.user,
      ),
    );
  }
}

final class _FakeAcademicYearRemoteDataSource
    implements AcademicYearRemoteDataSource {
  final List<AcademicYearItem> _items = [
    AcademicYearItem(
      id: 5,
      name: '2026/2027',
      startDate: DateTime(2026, 7, 1),
      endDate: DateTime(2027, 6, 30),
      active: true,
      classCount: 9,
    ),
    AcademicYearItem(
      id: 4,
      name: '2025/2026',
      startDate: DateTime(2025, 7, 1),
      endDate: DateTime(2026, 6, 30),
      active: false,
      classCount: 9,
    ),
  ];

  @override
  Future<AcademicYearPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    final filtered = _items
        .where((item) {
          final queryMatches =
              query.isEmpty ||
              item.name.toLowerCase().contains(query.toLowerCase());
          final statusMatches =
              status == 'semua' ||
              (status == 'aktif' && item.active) ||
              (status == 'nonaktif' && !item.active);
          return queryMatches && statusMatches;
        })
        .toList(growable: false);
    final activeYear = _items.where((item) => item.active).firstOrNull;

    return AcademicYearPage(
      items: filtered,
      counts: AcademicYearCounts(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      activeYear: activeYear,
      pagination: AcademicYearPagination(
        page: page,
        lastPage: 1,
        perPage: perPage,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      canManage: true,
    );
  }

  @override
  Future<void> create(AcademicYearFormValue value) async {
    if (value.active) _deactivateAll();
    _items.insert(
      0,
      AcademicYearItem(
        id: 6,
        name: value.name,
        startDate: value.startDate,
        endDate: value.endDate,
        active: value.active,
        notes: value.notes,
        classCount: 0,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required AcademicYearFormValue value,
  }) async {
    final index = _items.indexWhere((item) => item.id == id);
    if (index < 0) return;
    if (value.active) _deactivateAll();
    _items[index] = AcademicYearItem(
      id: id,
      name: value.name,
      startDate: value.startDate,
      endDate: value.endDate,
      active: value.active,
      notes: value.notes,
      classCount: _items[index].classCount,
    );
  }

  void _deactivateAll() {
    for (var index = 0; index < _items.length; index++) {
      final item = _items[index];
      _items[index] = AcademicYearItem(
        id: item.id,
        name: item.name,
        startDate: item.startDate,
        endDate: item.endDate,
        active: false,
        notes: item.notes,
        classCount: item.classCount,
      );
    }
  }
}

final class _FakeMyTeachingScheduleRemoteDataSource
    implements MyTeachingScheduleRemoteDataSource {
  static const _years = [
    my_schedule.TeachingAcademicYear(id: 5, name: '2026/2027', active: true),
    my_schedule.TeachingAcademicYear(id: 4, name: '2025/2026', active: false),
  ];

  @override
  Future<my_schedule.MyTeachingSchedulePage> fetch({
    required int? academicYearId,
  }) async {
    final selectedYearId = academicYearId ?? 5;
    const activeSchedule = my_schedule.TeachingScheduleItem(
      id: 901,
      period: my_schedule.TeachingPeriod(
        id: 41,
        number: 1,
        label: 'Jam 1',
        start: '07:30',
        end: '08:15',
      ),
      subject: my_schedule.TeachingSubject(
        id: 11,
        code: 'MTK8',
        name: 'Matematika Mobile',
      ),
      schoolClass: my_schedule.TeachingClass(id: 8, name: 'VIII.A', grade: 8),
      ongoing: false,
    );
    const oldSchedule = my_schedule.TeachingScheduleItem(
      id: 902,
      period: my_schedule.TeachingPeriod(
        id: 42,
        number: 2,
        label: 'Jam 2',
        start: '08:20',
        end: '09:05',
      ),
      subject: my_schedule.TeachingSubject(
        id: 12,
        code: 'BIND7',
        name: 'Bahasa Indonesia Lama',
      ),
      schoolClass: my_schedule.TeachingClass(id: 7, name: 'VII.A', grade: 7),
      ongoing: false,
    );
    final active = selectedYearId == 5;
    final days = [
      _day(
        'senin',
        'Senin',
        active ? const [activeSchedule] : const [],
        today: true,
      ),
      _day('selasa', 'Selasa', active ? const [] : const [oldSchedule]),
      _day('rabu', 'Rabu', const []),
      _day('kamis', 'Kamis', const []),
      _day('jumat', 'Jumat', const []),
      _day('sabtu', 'Sabtu', const []),
    ];

    return my_schedule.MyTeachingSchedulePage(
      academicYears: _years,
      selectedAcademicYearId: selectedYearId,
      employee: const my_schedule.TeachingEmployee(
        id: 31,
        name: 'Guru Mobile Uji',
        nip: '198808242026081001',
        position: 'Guru Mata Pelajaran',
      ),
      linkedEmployee: true,
      todayCode: 'senin',
      serverTime: DateTime(2026, 8, 24, 6, 45),
      summary: const my_schedule.TeachingScheduleSummary(
        teachingPeriods: 1,
        classes: 1,
        subjects: 1,
        teachingDays: 1,
        todaySchedules: 1,
      ),
      days: days,
      warnings: const [],
    );
  }

  my_schedule.TeachingScheduleDay _day(
    String code,
    String label,
    List<my_schedule.TeachingScheduleItem> schedules, {
    bool today = false,
  }) => my_schedule.TeachingScheduleDay(
    code: code,
    label: label,
    today: today,
    count: schedules.length,
    schedules: schedules,
  );
}

final class _FakeClassPromotionRemoteDataSource
    implements ClassPromotionRemoteDataSource {
  final Map<int, int> _placements = {32: 8};

  static const _years = [
    class_promotion.PromotionAcademicYear(
      id: 5,
      name: '2026/2027',
      active: true,
      classCount: 2,
    ),
    class_promotion.PromotionAcademicYear(
      id: 4,
      name: '2025/2026',
      active: false,
      classCount: 1,
    ),
  ];
  static const _sourceClass = class_promotion.PromotionClass(
    id: 41,
    name: 'VII.A',
    grade: 7,
    studentCount: 2,
    capacity: 32,
    remainingCapacity: 30,
    active: true,
  );
  static const _destinationClasses = [
    class_promotion.PromotionClass(
      id: 8,
      name: 'VIII.A',
      grade: 8,
      studentCount: 1,
      capacity: 32,
      remainingCapacity: 31,
      active: true,
    ),
    class_promotion.PromotionClass(
      id: 9,
      name: 'VIII.B',
      grade: 8,
      studentCount: 0,
      capacity: 32,
      remainingCapacity: 32,
      active: true,
    ),
  ];

  @override
  Future<class_promotion.ClassPromotionPage> fetch({
    required int? sourceYearId,
    required int? destinationYearId,
    required int? sourceClassId,
  }) async {
    final effectiveSourceYearId = sourceYearId ?? 5;
    final hasSourceClass = effectiveSourceYearId == 4;
    final hasDestination = destinationYearId == 5;
    final selectedClass = hasSourceClass && sourceClassId == 41;
    final members = selectedClass && hasDestination
        ? [_member(31, 'Alya Promosi'), _member(32, 'Bima Promosi')]
        : const <class_promotion.PromotionMember>[];
    final alreadyPlaced = members
        .where((member) => member.currentPlacement != null)
        .length;

    return class_promotion.ClassPromotionPage(
      academicYears: _years,
      sourceClasses: hasSourceClass ? const [_sourceClass] : const [],
      destinationClasses: hasDestination ? _destinationClasses : const [],
      selectedSourceClass: selectedClass ? _sourceClass : null,
      members: members,
      summary: class_promotion.PromotionSummary(
        sourceStudents: members.length,
        alreadyPlaced: alreadyPlaced,
        notPlaced: members.length - alreadyPlaced,
        destinationClasses: hasDestination ? _destinationClasses.length : 0,
      ),
      filter: class_promotion.PromotionFilter(
        sourceYearId: effectiveSourceYearId,
        destinationYearId: destinationYearId,
        sourceClassId: selectedClass ? sourceClassId : null,
      ),
      suggestedDestinationClassId: selectedClass ? 8 : null,
      ready: selectedClass && hasDestination && members.isNotEmpty,
      warnings: destinationYearId == null
          ? const ['Pilih tahun pelajaran tujuan.']
          : sourceClassId == null
          ? const ['Pilih kelas asal untuk menampilkan siswa.']
          : const [],
    );
  }

  class_promotion.PromotionMember _member(int id, String name) {
    final targetClassId = _placements[id];
    final targetClass = targetClassId == null
        ? null
        : _destinationClasses.firstWhere((item) => item.id == targetClassId);
    return class_promotion.PromotionMember(
      id: id,
      attendanceNumber: id - 30,
      student: class_promotion.PromotionStudent(
        id: id + 100,
        name: name,
        nis: '202600$id',
        nisn: '00112233$id',
        gender: id.isOdd ? 'P' : 'L',
        active: true,
      ),
      currentPlacement: targetClass == null
          ? null
          : class_promotion.ExistingPromotionPlacement(
              membershipId: id + 200,
              schoolClass: targetClass,
            ),
      suggestedDestinationClassId: targetClassId ?? 8,
      initialNote: 'Penempatan massal',
    );
  }

  @override
  Future<class_promotion.PromotionResult> process({
    required int sourceYearId,
    required int destinationYearId,
    required int sourceClassId,
    required List<class_promotion.PromotionAssignment> assignments,
  }) async {
    var placed = 0;
    final notes = <String>[];
    for (final assignment in assignments) {
      final target = assignment.destinationClassId;
      if (target == null) {
        notes.add('Siswa #${assignment.memberId}: belum ditempatkan.');
        continue;
      }
      _placements[assignment.memberId] = target;
      placed++;
    }
    return class_promotion.PromotionResult(
      processed: assignments.length,
      placed: placed,
      skipped: assignments.length - placed,
      notes: notes,
    );
  }
}

final class _MemoryTokenStorage implements TokenStorage {
  String? token;

  @override
  Future<void> delete() async => token = null;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String token) async => this.token = token;
}

final class _FakeDeviceIdentity implements DeviceIdentity {
  @override
  Future<String> readName() async => 'NUSA Android TEST';
}

final class _FakeAuthRemoteDataSource implements AuthRemoteDataSource {
  _FakeAuthRemoteDataSource({this.wajibGantiKataSandi = false});

  final bool wajibGantiKataSandi;

  Pengguna get _pengguna => Pengguna(
    id: 2,
    nama: 'Pengguna Mobile Uji',
    username: 'mobile.uji',
    jenisAkun: 'Pegawai',
    administrator: false,
    wajibGantiKataSandi: wajibGantiKataSandi,
    peran: const ['pegawai'],
    izin: const ['beranda.akses'],
  );

  @override
  Future<AuthSession> login({
    required String username,
    required String password,
    required String deviceName,
  }) async {
    return AuthSession(token: 'token-uji', pengguna: _pengguna);
  }

  @override
  Future<void> logout() async {}

  @override
  Future<Pengguna> saya() async => _pengguna;

  @override
  Future<Pengguna> ubahKataSandi({
    required String kataSandiLama,
    required String kataSandiBaru,
    required String konfirmasiKataSandiBaru,
  }) async {
    return Pengguna(
      id: _pengguna.id,
      nama: _pengguna.nama,
      username: _pengguna.username,
      jenisAkun: _pengguna.jenisAkun,
      administrator: _pengguna.administrator,
      wajibGantiKataSandi: false,
      peran: _pengguna.peran,
      izin: _pengguna.izin,
    );
  }
}
