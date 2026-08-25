class StudentAccountPage {
  const StudentAccountPage({
    required this.items,
    required this.counts,
    required this.classes,
    required this.pagination,
    required this.query,
    required this.status,
    required this.canManage,
    required this.canViewCredentials,
    this.academicYear,
    this.classId,
  });

  factory StudentAccountPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    final year = _map(json['tahun_pelajaran_aktif']);
    return StudentAccountPage(
      items: _list(json['items'], StudentAccountItem.fromJson),
      counts: StudentAccountCounts.fromJson(
        _map(json['ringkasan']) ?? const {},
      ),
      classes: _list(json['pilihan_kelas'], StudentAccountClass.fromJson),
      pagination: StudentAccountPagination.fromJson(
        _map(json['paginasi']) ?? const {},
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status_akun'] as String? ?? 'semua',
      classId: _nullableInteger(filter['kelas_id']),
      academicYear: year == null ? null : AcademicYearSummary.fromJson(year),
      canManage: access['dapat_kelola'] as bool? ?? false,
      canViewCredentials: access['dapat_melihat_kredensial'] as bool? ?? false,
    );
  }

  final List<StudentAccountItem> items;
  final StudentAccountCounts counts;
  final List<StudentAccountClass> classes;
  final StudentAccountPagination pagination;
  final String query;
  final String status;
  final int? classId;
  final AcademicYearSummary? academicYear;
  final bool canManage;
  final bool canViewCredentials;

  StudentAccountPage append(StudentAccountPage next) => StudentAccountPage(
    items: [...items, ...next.items],
    counts: next.counts,
    classes: next.classes,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
    classId: next.classId,
    academicYear: next.academicYear,
    canManage: next.canManage,
    canViewCredentials: next.canViewCredentials,
  );
}

class StudentAccountDetail {
  const StudentAccountDetail({
    required this.item,
    required this.canManage,
    required this.canViewCredentials,
    this.academicYear,
  });

  factory StudentAccountDetail.fromJson(Map<String, dynamic> json) {
    final access = _map(json['hak_akses']) ?? const {};
    final year = _map(json['tahun_pelajaran_aktif']);
    return StudentAccountDetail(
      item: StudentAccountItem.fromJson(json),
      academicYear: year == null ? null : AcademicYearSummary.fromJson(year),
      canManage: access['dapat_kelola'] as bool? ?? false,
      canViewCredentials: access['dapat_melihat_kredensial'] as bool? ?? false,
    );
  }

  final StudentAccountItem item;
  final AcademicYearSummary? academicYear;
  final bool canManage;
  final bool canViewCredentials;
}

class StudentAccountItem {
  const StudentAccountItem({
    required this.membership,
    required this.student,
    required this.status,
    required this.account,
  });

  factory StudentAccountItem.fromJson(Map<String, dynamic> json) =>
      StudentAccountItem(
        membership: StudentAccountMembership.fromJson(
          _map(json['anggota_kelas']) ?? const {},
        ),
        student: AccountStudent.fromJson(_map(json['siswa']) ?? const {}),
        status: json['status_akun'] as String? ?? 'belum',
        account: ManagedStudentAccount.fromJson(_map(json['akun']) ?? const {}),
      );

  final StudentAccountMembership membership;
  final AccountStudent student;
  final String status;
  final ManagedStudentAccount account;

  String get statusLabel => switch (status) {
    'aktif' => 'Akun Aktif',
    'nonaktif' => 'Akun Nonaktif',
    'tanpa_nisn' => 'NISN Kosong',
    _ => 'Belum Ada Akun',
  };
}

class AccountStudent {
  const AccountStudent({
    required this.id,
    required this.name,
    required this.active,
    this.nis,
    this.nisn,
    this.photoUrl,
  });

  factory AccountStudent.fromJson(Map<String, dynamic> json) => AccountStudent(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    nis: json['nis'] as String?,
    nisn: json['nisn'] as String?,
    photoUrl: json['foto_url'] as String?,
    active: json['aktif'] as bool? ?? false,
  );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? photoUrl;
  final bool active;

  String get initials {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2);
    final value = parts.map((part) => part[0]).join();
    return value.isEmpty ? 'SW' : value.toUpperCase();
  }
}

class StudentAccountMembership {
  const StudentAccountMembership({
    required this.id,
    required this.schoolClass,
    this.attendanceNumber,
  });

  factory StudentAccountMembership.fromJson(Map<String, dynamic> json) =>
      StudentAccountMembership(
        id: _integer(json['id']),
        attendanceNumber: _nullableInteger(json['nomor_absen']),
        schoolClass: StudentAccountClass.fromJson(
          _map(json['kelas']) ?? const {},
        ),
      );

  final int id;
  final int? attendanceNumber;
  final StudentAccountClass schoolClass;
}

class StudentAccountClass {
  const StudentAccountClass({
    required this.id,
    required this.name,
    required this.grade,
    this.activeStudentCount = 0,
  });

  factory StudentAccountClass.fromJson(Map<String, dynamic> json) =>
      StudentAccountClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        activeStudentCount: _integer(json['jumlah_siswa_aktif']),
      );

  final int id;
  final String name;
  final int grade;
  final int activeStudentCount;
}

class ManagedStudentAccount {
  const ManagedStudentAccount({
    required this.available,
    required this.active,
    required this.mustChangePassword,
    required this.initialPasswordAvailable,
    this.id,
    this.username,
    this.initialPassword,
    this.lastLoginAt,
  });

  factory ManagedStudentAccount.fromJson(Map<String, dynamic> json) =>
      ManagedStudentAccount(
        available: json['tersedia'] as bool? ?? false,
        id: _nullableInteger(json['id']),
        username: json['username'] as String?,
        active: json['aktif'] as bool? ?? false,
        mustChangePassword: json['wajib_ganti_kata_sandi'] as bool? ?? false,
        initialPasswordAvailable:
            json['kata_sandi_awal_tersedia'] as bool? ?? false,
        initialPassword: json['kata_sandi_awal'] as String?,
        lastLoginAt: _date(json['terakhir_login_pada']),
      );

  final bool available;
  final int? id;
  final String? username;
  final bool active;
  final bool mustChangePassword;
  final bool initialPasswordAvailable;
  final String? initialPassword;
  final DateTime? lastLoginAt;
}

class StudentAccountCounts {
  const StudentAccountCounts({
    required this.students,
    required this.withAccount,
    required this.withoutAccount,
    required this.withoutNisn,
  });

  factory StudentAccountCounts.fromJson(Map<String, dynamic> json) =>
      StudentAccountCounts(
        students: _integer(json['jumlah_siswa']),
        withAccount: _integer(json['sudah_akun']),
        withoutAccount: _integer(json['belum_akun']),
        withoutNisn: _integer(json['tanpa_nisn']),
      );

  final int students;
  final int withAccount;
  final int withoutAccount;
  final int withoutNisn;
}

class StudentAccountPagination {
  const StudentAccountPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory StudentAccountPagination.fromJson(Map<String, dynamic> json) =>
      StudentAccountPagination(
        page: _integer(json['halaman'], fallback: 1),
        lastPage: _integer(json['halaman_terakhir'], fallback: 1),
        perPage: _integer(json['per_halaman'], fallback: 15),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasNextPage;
}

class AcademicYearSummary {
  const AcademicYearSummary({required this.id, required this.name});

  factory AcademicYearSummary.fromJson(Map<String, dynamic> json) =>
      AcademicYearSummary(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class BulkStudentAccountResult {
  const BulkStudentAccountResult({
    required this.created,
    required this.skipped,
    required this.notes,
  });

  factory BulkStudentAccountResult.fromJson(Map<String, dynamic> json) =>
      BulkStudentAccountResult(
        created: _integer(json['dibuat']),
        skipped: _integer(json['dilewati']),
        notes: (json['catatan'] as List<dynamic>? ?? const [])
            .whereType<String>()
            .toList(growable: false),
      );

  final int created;
  final int skipped;
  final List<String> notes;
}

Map<String, dynamic>? _map(dynamic value) =>
    value is Map<String, dynamic> ? value : null;

List<T> _list<T>(dynamic value, T Function(Map<String, dynamic>) parser) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(parser)
        .toList(growable: false);

int _integer(dynamic value, {int fallback = 0}) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};

int? _nullableInteger(dynamic value) => value == null ? null : _integer(value);

DateTime? _date(dynamic value) =>
    value is String ? DateTime.tryParse(value) : null;
