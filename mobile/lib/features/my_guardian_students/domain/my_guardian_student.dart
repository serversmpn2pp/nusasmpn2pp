class MyGuardianStudentPage {
  const MyGuardianStudentPage({
    required this.items,
    required this.summary,
    required this.options,
    required this.filter,
    required this.pagination,
    required this.access,
    this.academicYear,
  });

  factory MyGuardianStudentPage.fromJson(Map<String, dynamic> json) =>
      MyGuardianStudentPage(
        items: _list(json['items'], MyGuardianStudentItem.fromJson),
        summary: MyGuardianStudentSummary.fromJson(_map(json['ringkasan'])),
        academicYear: _nullable(
          json['tahun_pelajaran'],
          MyGuardianAcademicYear.fromJson,
        ),
        options: MyGuardianStudentOptions.fromJson(_map(json['pilihan'])),
        filter: MyGuardianStudentFilter.fromJson(_map(json['filter'])),
        pagination: MyGuardianPagination.fromJson(_map(json['paginasi'])),
        access: MyGuardianStudentAccess.fromJson(_map(json['hak_akses'])),
      );

  final List<MyGuardianStudentItem> items;
  final MyGuardianStudentSummary summary;
  final MyGuardianAcademicYear? academicYear;
  final MyGuardianStudentOptions options;
  final MyGuardianStudentFilter filter;
  final MyGuardianPagination pagination;
  final MyGuardianStudentAccess access;

  MyGuardianStudentPage append(MyGuardianStudentPage next) =>
      MyGuardianStudentPage(
        items: [...items, ...next.items],
        summary: next.summary,
        academicYear: next.academicYear,
        options: next.options,
        filter: next.filter,
        pagination: next.pagination,
        access: next.access,
      );
}

class MyGuardianStudentSummary {
  const MyGuardianStudentSummary({
    required this.students,
    required this.classes,
    required this.male,
    required this.female,
    required this.withPoints,
  });

  factory MyGuardianStudentSummary.fromJson(Map<String, dynamic> json) =>
      MyGuardianStudentSummary(
        students: _integer(json['jumlah_siswa']),
        classes: _integer(json['jumlah_kelas']),
        male: _integer(json['laki_laki']),
        female: _integer(json['perempuan']),
        withPoints: _integer(json['memiliki_poin']),
      );

  final int students;
  final int classes;
  final int male;
  final int female;
  final int withPoints;
}

class MyGuardianStudentItem {
  const MyGuardianStudentItem({
    required this.id,
    required this.name,
    required this.genderLabel,
    required this.totalPoints,
    required this.reportCount,
    this.nis,
    this.nisn,
    this.photoUrl,
    this.gender,
    this.schoolClass,
    this.assignmentStartDate,
  });

  factory MyGuardianStudentItem.fromJson(Map<String, dynamic> json) =>
      MyGuardianStudentItem(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        photoUrl: json['foto_url'] as String?,
        gender: json['jenis_kelamin'] as String?,
        genderLabel: json['label_jenis_kelamin'] as String? ?? '-',
        schoolClass: _nullable(json['kelas'], MyGuardianSchoolClass.fromJson),
        totalPoints: _integer(json['total_poin']),
        reportCount: _integer(json['jumlah_laporan']),
        assignmentStartDate: json['tanggal_mulai_didampingi'] as String?,
      );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? photoUrl;
  final String? gender;
  final String genderLabel;
  final MyGuardianSchoolClass? schoolClass;
  final int totalPoints;
  final int reportCount;
  final String? assignmentStartDate;
}

class MyGuardianStudentOptions {
  const MyGuardianStudentOptions({required this.grades, required this.classes});

  factory MyGuardianStudentOptions.fromJson(Map<String, dynamic> json) =>
      MyGuardianStudentOptions(
        grades: _list(json['tingkat'], MyGuardianGradeOption.fromJson),
        classes: _list(json['kelas'], MyGuardianSchoolClass.fromJson),
      );

  final List<MyGuardianGradeOption> grades;
  final List<MyGuardianSchoolClass> classes;
}

class MyGuardianGradeOption {
  const MyGuardianGradeOption({required this.value, required this.label});
  factory MyGuardianGradeOption.fromJson(Map<String, dynamic> json) =>
      MyGuardianGradeOption(
        value: _integer(json['nilai']),
        label: json['label'] as String? ?? '-',
      );
  final int value;
  final String label;
}

class MyGuardianStudentFilter {
  const MyGuardianStudentFilter({
    required this.query,
    this.grade,
    this.classId,
  });
  factory MyGuardianStudentFilter.fromJson(Map<String, dynamic> json) =>
      MyGuardianStudentFilter(
        query: json['kata_kunci'] as String? ?? '',
        grade: _nullableInteger(json['tingkat']),
        classId: _nullableInteger(json['kelas_id']),
      );
  final String query;
  final int? grade;
  final int? classId;
}

class MyGuardianPagination {
  const MyGuardianPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });
  factory MyGuardianPagination.fromJson(Map<String, dynamic> json) =>
      MyGuardianPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int total;
  final bool hasNextPage;
}

class MyGuardianStudentDetail {
  const MyGuardianStudentDetail({
    required this.student,
    required this.assignment,
    required this.summary,
    required this.latestReports,
    required this.access,
    this.schoolClass,
    this.academicYear,
  });

  factory MyGuardianStudentDetail.fromJson(Map<String, dynamic> json) =>
      MyGuardianStudentDetail(
        student: MyGuardianStudentProfile.fromJson(_map(json['siswa'])),
        schoolClass: _nullable(json['kelas'], MyGuardianSchoolClass.fromJson),
        assignment: MyGuardianAssignment.fromJson(_map(json['penugasan'])),
        academicYear: _nullable(
          json['tahun_pelajaran'],
          MyGuardianAcademicYear.fromJson,
        ),
        summary: MyGuardianDetailSummary.fromJson(_map(json['ringkasan'])),
        latestReports: _list(
          json['laporan_terbaru'],
          MyGuardianStudentReport.fromJson,
        ),
        access: MyGuardianStudentAccess.fromJson(_map(json['hak_akses'])),
      );

  final MyGuardianStudentProfile student;
  final MyGuardianSchoolClass? schoolClass;
  final MyGuardianAssignment assignment;
  final MyGuardianAcademicYear? academicYear;
  final MyGuardianDetailSummary summary;
  final List<MyGuardianStudentReport> latestReports;
  final MyGuardianStudentAccess access;
}

class MyGuardianStudentProfile {
  const MyGuardianStudentProfile({
    required this.id,
    required this.name,
    required this.genderLabel,
    required this.active,
    required this.parentContact,
    this.nis,
    this.nisn,
    this.nik,
    this.photoUrl,
    this.gender,
    this.birthPlace,
    this.birthDate,
    this.religion,
    this.previousSchool,
    this.familyStatus,
    this.childNumber,
    this.address,
    this.note,
  });

  factory MyGuardianStudentProfile.fromJson(Map<String, dynamic> json) =>
      MyGuardianStudentProfile(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        nik: json['nik'] as String?,
        photoUrl: json['foto_url'] as String?,
        gender: json['jenis_kelamin'] as String?,
        genderLabel: json['label_jenis_kelamin'] as String? ?? '-',
        birthPlace: json['tempat_lahir'] as String?,
        birthDate: json['tanggal_lahir'] as String?,
        religion: json['agama'] as String?,
        previousSchool: json['sekolah_asal'] as String?,
        familyStatus: json['status_dalam_keluarga'] as String?,
        childNumber: _nullableInteger(json['anak_ke']),
        active: json['aktif'] as bool? ?? false,
        parentContact: MyGuardianParentContact.fromJson(
          _map(json['orang_tua_wali']),
        ),
        address: json['alamat'] as String?,
        note: json['keterangan'] as String?,
      );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? nik;
  final String? photoUrl;
  final String? gender;
  final String genderLabel;
  final String? birthPlace;
  final String? birthDate;
  final String? religion;
  final String? previousSchool;
  final String? familyStatus;
  final int? childNumber;
  final bool active;
  final MyGuardianParentContact parentContact;
  final String? address;
  final String? note;
}

class MyGuardianParentContact {
  const MyGuardianParentContact({
    this.fatherName,
    this.fatherPhone,
    this.fatherOccupation,
    this.motherName,
    this.motherPhone,
    this.motherOccupation,
    this.guardianName,
    this.guardianRelationship,
    this.guardianPhone,
    this.primaryAttendanceContact,
    this.primaryAttendanceContactLabel,
  });

  factory MyGuardianParentContact.fromJson(Map<String, dynamic> json) =>
      MyGuardianParentContact(
        fatherName: json['nama_ayah'] as String?,
        fatherPhone: json['nomor_wa_ayah'] as String?,
        fatherOccupation: json['pekerjaan_ayah'] as String?,
        motherName: json['nama_ibu'] as String?,
        motherPhone: json['nomor_wa_ibu'] as String?,
        motherOccupation: json['pekerjaan_ibu'] as String?,
        guardianName: json['nama_wali'] as String?,
        guardianRelationship: json['hubungan_wali'] as String?,
        guardianPhone: json['nomor_wa_wali'] as String?,
        primaryAttendanceContact: json['kontak_absensi_utama'] as String?,
        primaryAttendanceContactLabel:
            json['label_kontak_absensi_utama'] as String?,
      );

  final String? fatherName;
  final String? fatherPhone;
  final String? fatherOccupation;
  final String? motherName;
  final String? motherPhone;
  final String? motherOccupation;
  final String? guardianName;
  final String? guardianRelationship;
  final String? guardianPhone;
  final String? primaryAttendanceContact;
  final String? primaryAttendanceContactLabel;
}

class MyGuardianSchoolClass {
  const MyGuardianSchoolClass({
    required this.id,
    required this.name,
    required this.grade,
    this.attendanceNumber,
  });
  factory MyGuardianSchoolClass.fromJson(Map<String, dynamic> json) =>
      MyGuardianSchoolClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        attendanceNumber: _nullableInteger(json['nomor_absen']),
      );
  final int id;
  final String name;
  final int grade;
  final int? attendanceNumber;
}

class MyGuardianAssignment {
  const MyGuardianAssignment({
    required this.id,
    this.startDate,
    this.decreeNumber,
    this.note,
  });
  factory MyGuardianAssignment.fromJson(Map<String, dynamic> json) =>
      MyGuardianAssignment(
        id: _integer(json['id']),
        startDate: json['tanggal_mulai'] as String?,
        decreeNumber: json['nomor_sk'] as String?,
        note: json['catatan'] as String?,
      );
  final int id;
  final String? startDate;
  final String? decreeNumber;
  final String? note;
}

class MyGuardianAcademicYear {
  const MyGuardianAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });
  factory MyGuardianAcademicYear.fromJson(Map<String, dynamic> json) =>
      MyGuardianAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );
  final int id;
  final String name;
  final bool active;
}

class MyGuardianDetailSummary {
  const MyGuardianDetailSummary({
    required this.totalPoints,
    required this.reportCount,
  });
  factory MyGuardianDetailSummary.fromJson(Map<String, dynamic> json) =>
      MyGuardianDetailSummary(
        totalPoints: _integer(json['total_poin']),
        reportCount: _integer(json['jumlah_laporan']),
      );
  final int totalPoints;
  final int reportCount;
}

class MyGuardianStudentReport {
  const MyGuardianStudentReport({
    required this.id,
    required this.number,
    required this.type,
    required this.typeLabel,
    required this.status,
    required this.statusLabel,
    required this.points,
    this.date,
    this.category,
    this.schoolClass,
  });
  factory MyGuardianStudentReport.fromJson(Map<String, dynamic> json) =>
      MyGuardianStudentReport(
        id: _integer(json['id']),
        number: json['nomor'] as String? ?? '-',
        date: json['tanggal'] as String?,
        type: json['jenis'] as String? ?? '',
        typeLabel: json['label_jenis'] as String? ?? '-',
        category: json['kategori'] as String?,
        schoolClass: json['kelas'] as String?,
        status: json['status'] as String? ?? '',
        statusLabel: json['label_status'] as String? ?? '-',
        points: _integer(json['poin']),
      );
  final int id;
  final String number;
  final String? date;
  final String type;
  final String typeLabel;
  final String? category;
  final String? schoolClass;
  final String status;
  final String statusLabel;
  final int points;
}

class MyGuardianStudentAccess {
  const MyGuardianStudentAccess({required this.canViewPointRecap});
  factory MyGuardianStudentAccess.fromJson(Map<String, dynamic> json) =>
      MyGuardianStudentAccess(
        canViewPointRecap: json['dapat_melihat_rekap_poin'] as bool? ?? false,
      );
  final bool canViewPointRecap;
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};
List<T> _list<T>(dynamic value, T Function(Map<String, dynamic>) parser) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => parser(Map<String, dynamic>.from(item)))
          .toList()
    : <T>[];
T? _nullable<T>(dynamic value, T Function(Map<String, dynamic>) parser) =>
    value is Map ? parser(Map<String, dynamic>.from(value)) : null;
int _integer(dynamic value) => value is num ? value.toInt() : 0;
int? _nullableInteger(dynamic value) => value is num ? value.toInt() : null;
