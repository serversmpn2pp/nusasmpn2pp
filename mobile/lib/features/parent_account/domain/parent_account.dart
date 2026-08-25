class ParentAccountPage {
  const ParentAccountPage({
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

  factory ParentAccountPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    final year = _map(json['tahun_pelajaran_aktif']);
    return ParentAccountPage(
      items: _list(json['items'], ParentAccountItem.fromJson),
      counts: ParentAccountCounts.fromJson(_map(json['ringkasan']) ?? const {}),
      classes: _list(json['pilihan_kelas'], ParentAccountClass.fromJson),
      pagination: ParentAccountPagination.fromJson(
        _map(json['paginasi']) ?? const {},
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status_akun'] as String? ?? 'semua',
      classId: _nullableInteger(filter['kelas_id']),
      academicYear: year == null ? null : ParentAcademicYear.fromJson(year),
      canManage: access['dapat_kelola'] as bool? ?? false,
      canViewCredentials: access['dapat_melihat_kredensial'] as bool? ?? false,
    );
  }

  final List<ParentAccountItem> items;
  final ParentAccountCounts counts;
  final List<ParentAccountClass> classes;
  final ParentAccountPagination pagination;
  final String query;
  final String status;
  final int? classId;
  final ParentAcademicYear? academicYear;
  final bool canManage;
  final bool canViewCredentials;

  ParentAccountPage append(ParentAccountPage next) => ParentAccountPage(
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

class ParentAccountDetail {
  const ParentAccountDetail({
    required this.item,
    required this.canManage,
    required this.canViewCredentials,
    this.academicYear,
  });

  factory ParentAccountDetail.fromJson(Map<String, dynamic> json) {
    final access = _map(json['hak_akses']) ?? const {};
    final year = _map(json['tahun_pelajaran_aktif']);
    return ParentAccountDetail(
      item: ParentAccountItem.fromJson(json),
      academicYear: year == null ? null : ParentAcademicYear.fromJson(year),
      canManage: access['dapat_kelola'] as bool? ?? false,
      canViewCredentials: access['dapat_melihat_kredensial'] as bool? ?? false,
    );
  }

  final ParentAccountItem item;
  final ParentAcademicYear? academicYear;
  final bool canManage;
  final bool canViewCredentials;
}

class ParentAccountItem {
  const ParentAccountItem({
    required this.membership,
    required this.student,
    required this.parent,
    required this.familyContacts,
    required this.status,
    required this.account,
  });

  factory ParentAccountItem.fromJson(Map<String, dynamic> json) =>
      ParentAccountItem(
        membership: ParentAccountMembership.fromJson(
          _map(json['anggota_kelas']) ?? const {},
        ),
        student: ParentAccountStudent.fromJson(_map(json['siswa']) ?? const {}),
        parent: ParentGuardian.fromJson(_map(json['orang_tua']) ?? const {}),
        familyContacts: _list(
          json['kontak_keluarga'],
          ParentFamilyContact.fromJson,
        ),
        status: json['status_akun'] as String? ?? 'belum',
        account: ManagedParentAccount.fromJson(_map(json['akun']) ?? const {}),
      );

  final ParentAccountMembership membership;
  final ParentAccountStudent student;
  final ParentGuardian parent;
  final List<ParentFamilyContact> familyContacts;
  final String status;
  final ManagedParentAccount account;

  String get statusLabel => switch (status) {
    'aktif' => 'Akun Aktif',
    'nonaktif' => 'Akun Nonaktif',
    'tanpa_nisn' => 'NISN Kosong',
    _ => 'Belum Ada Akun',
  };
}

class ParentAccountStudent {
  const ParentAccountStudent({
    required this.id,
    required this.name,
    required this.active,
    this.nis,
    this.nisn,
    this.photoUrl,
  });

  factory ParentAccountStudent.fromJson(Map<String, dynamic> json) =>
      ParentAccountStudent(
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

class ParentGuardian {
  const ParentGuardian({
    required this.available,
    required this.primary,
    this.id,
    this.name,
    this.phone,
    this.relationship,
  });

  factory ParentGuardian.fromJson(Map<String, dynamic> json) => ParentGuardian(
    available: json['tersedia'] as bool? ?? false,
    id: _nullableInteger(json['id']),
    name: json['nama'] as String?,
    phone: json['nomor_wa'] as String?,
    relationship: json['hubungan'] as String?,
    primary: json['utama'] as bool? ?? false,
  );

  final bool available;
  final int? id;
  final String? name;
  final String? phone;
  final String? relationship;
  final bool primary;

  String get relationshipLabel {
    final value = relationship?.trim();
    if (value == null || value.isEmpty) return 'Orang Tua/Wali';
    return value[0].toUpperCase() + value.substring(1);
  }
}

class ParentFamilyContact {
  const ParentFamilyContact({
    required this.code,
    required this.label,
    required this.primary,
    this.name,
    this.phone,
  });

  factory ParentFamilyContact.fromJson(Map<String, dynamic> json) =>
      ParentFamilyContact(
        code: json['kode'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
        name: json['nama'] as String?,
        phone: json['nomor_wa'] as String?,
        primary: json['utama'] as bool? ?? false,
      );

  final String code;
  final String label;
  final String? name;
  final String? phone;
  final bool primary;
}

class ParentAccountMembership {
  const ParentAccountMembership({
    required this.id,
    required this.schoolClass,
    this.attendanceNumber,
  });

  factory ParentAccountMembership.fromJson(Map<String, dynamic> json) =>
      ParentAccountMembership(
        id: _integer(json['id']),
        attendanceNumber: _nullableInteger(json['nomor_absen']),
        schoolClass: ParentAccountClass.fromJson(
          _map(json['kelas']) ?? const {},
        ),
      );

  final int id;
  final int? attendanceNumber;
  final ParentAccountClass schoolClass;
}

class ParentAccountClass {
  const ParentAccountClass({
    required this.id,
    required this.name,
    required this.grade,
    this.activeStudentCount = 0,
  });

  factory ParentAccountClass.fromJson(Map<String, dynamic> json) =>
      ParentAccountClass(
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

class ManagedParentAccount {
  const ManagedParentAccount({
    required this.available,
    required this.active,
    required this.mustChangePassword,
    required this.initialPasswordAvailable,
    this.id,
    this.username,
    this.initialPassword,
    this.lastLoginAt,
  });

  factory ManagedParentAccount.fromJson(Map<String, dynamic> json) =>
      ManagedParentAccount(
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

class ParentAccountCounts {
  const ParentAccountCounts({
    required this.students,
    required this.activeAccounts,
    required this.inactiveAccounts,
    required this.withoutAccount,
    required this.withoutNisn,
  });

  factory ParentAccountCounts.fromJson(Map<String, dynamic> json) =>
      ParentAccountCounts(
        students: _integer(json['jumlah_siswa']),
        activeAccounts: _integer(json['akun_aktif']),
        inactiveAccounts: _integer(json['akun_nonaktif']),
        withoutAccount: _integer(json['belum_akun']),
        withoutNisn: _integer(json['tanpa_nisn']),
      );

  final int students;
  final int activeAccounts;
  final int inactiveAccounts;
  final int withoutAccount;
  final int withoutNisn;
}

class ParentAccountPagination {
  const ParentAccountPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory ParentAccountPagination.fromJson(Map<String, dynamic> json) =>
      ParentAccountPagination(
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

class ParentAcademicYear {
  const ParentAcademicYear({required this.id, required this.name});

  factory ParentAcademicYear.fromJson(Map<String, dynamic> json) =>
      ParentAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class BulkParentAccountResult {
  const BulkParentAccountResult({
    required this.created,
    required this.skipped,
    required this.notes,
  });

  factory BulkParentAccountResult.fromJson(Map<String, dynamic> json) =>
      BulkParentAccountResult(
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
