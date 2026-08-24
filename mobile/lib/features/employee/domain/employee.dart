class EmployeePage {
  const EmployeePage({
    required this.items,
    required this.counts,
    required this.types,
    required this.pagination,
    required this.query,
    required this.status,
    required this.type,
    required this.canManage,
  });

  factory EmployeePage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    return EmployeePage(
      items: _list(json['items'], EmployeeSummary.fromJson),
      counts: EmployeeCounts.fromJson(_map(json['ringkasan']) ?? const {}),
      types: (json['pilihan_jenis_pegawai'] as List<dynamic>? ?? const [])
          .whereType<String>()
          .toList(growable: false),
      pagination: EmployeePagination.fromJson(
        _map(json['paginasi']) ?? const {},
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      type: filter['jenis_pegawai'] as String? ?? 'semua',
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<EmployeeSummary> items;
  final EmployeeCounts counts;
  final List<String> types;
  final EmployeePagination pagination;
  final String query;
  final String status;
  final String type;
  final bool canManage;

  EmployeePage append(EmployeePage next) => EmployeePage(
    items: [...items, ...next.items],
    counts: next.counts,
    types: next.types,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
    type: next.type,
    canManage: next.canManage,
  );
}

class EmployeeCounts {
  const EmployeeCounts({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory EmployeeCounts.fromJson(Map<String, dynamic> json) => EmployeeCounts(
    total: _integer(json['total']),
    active: _integer(json['aktif']),
    inactive: _integer(json['nonaktif']),
  );

  final int total;
  final int active;
  final int inactive;
}

class EmployeePagination {
  const EmployeePagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory EmployeePagination.fromJson(Map<String, dynamic> json) =>
      EmployeePagination(
        page: _integer(json['halaman']),
        lastPage: _integer(json['halaman_terakhir']),
        perPage: _integer(json['per_halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasNextPage;
}

class EmployeeSummary {
  const EmployeeSummary({
    required this.id,
    required this.name,
    required this.active,
    this.nip,
    this.nuptk,
    this.photoUrl,
    this.gender,
    this.employmentStatus,
    this.employeeType,
    this.primaryPosition,
  });

  factory EmployeeSummary.fromJson(Map<String, dynamic> json) =>
      EmployeeSummary(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        nuptk: json['nuptk'] as String?,
        photoUrl: json['foto_url'] as String?,
        gender: json['jenis_kelamin'] as String?,
        employmentStatus: json['status_kepegawaian'] as String?,
        employeeType: json['jenis_pegawai'] as String?,
        primaryPosition: json['jabatan_utama'] as String?,
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String? nip;
  final String? nuptk;
  final String? photoUrl;
  final String? gender;
  final String? employmentStatus;
  final String? employeeType;
  final String? primaryPosition;
  final bool active;

  String get initials {
    final words = name.trim().split(RegExp(r'\s+'));
    if (words.isEmpty || words.first.isEmpty) return 'PG';
    return words.take(2).map((word) => word[0]).join().toUpperCase();
  }

  String get identityLabel {
    if (nip?.trim().isNotEmpty == true) return 'NIP $nip';
    if (nuptk?.trim().isNotEmpty == true) return 'NUPTK $nuptk';
    return 'Identitas belum lengkap';
  }

  String get roleLabel =>
      _firstText([primaryPosition, employeeType, employmentStatus]) ??
      'Jabatan belum diisi';
}

class EmployeeDetail {
  const EmployeeDetail({
    required this.summary,
    required this.account,
    required this.assignmentCounts,
    required this.canManage,
    this.nik,
    this.birthPlace,
    this.birthDate,
    this.address,
    this.email,
    this.phone,
    this.rank,
    this.workStartDate,
    this.dutyStartDate,
    this.salarySource,
    this.lastEducation,
    this.educationMajor,
    this.graduationYear,
    this.notes,
  });

  factory EmployeeDetail.fromJson(Map<String, dynamic> json) {
    final access = _map(json['hak_akses']) ?? const {};
    return EmployeeDetail(
      summary: EmployeeSummary.fromJson(json),
      nik: json['nik'] as String?,
      birthPlace: json['tempat_lahir'] as String?,
      birthDate: _date(json['tanggal_lahir']),
      address: json['alamat'] as String?,
      email: json['email'] as String?,
      phone: json['no_hp'] as String?,
      rank: json['golongan'] as String?,
      workStartDate: _date(json['tanggal_mulai_kerja']),
      dutyStartDate: _date(json['tanggal_mulai_bertugas']),
      salarySource: json['sumber_gaji'] as String?,
      lastEducation: json['pendidikan_terakhir'] as String?,
      educationMajor: json['jurusan_pendidikan'] as String?,
      graduationYear: _nullableInteger(json['tahun_lulus']),
      notes: json['keterangan'] as String?,
      account: EmployeeAccount.fromJson(_map(json['akun']) ?? const {}),
      assignmentCounts: EmployeeAssignmentCounts.fromJson(
        _map(json['ringkasan_penugasan']) ?? const {},
      ),
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final EmployeeSummary summary;
  final String? nik;
  final String? birthPlace;
  final DateTime? birthDate;
  final String? address;
  final String? email;
  final String? phone;
  final String? rank;
  final DateTime? workStartDate;
  final DateTime? dutyStartDate;
  final String? salarySource;
  final String? lastEducation;
  final String? educationMajor;
  final int? graduationYear;
  final String? notes;
  final EmployeeAccount account;
  final EmployeeAssignmentCounts assignmentCounts;
  final bool canManage;
}

class EmployeeAccount {
  const EmployeeAccount({
    required this.available,
    required this.active,
    this.username,
  });

  factory EmployeeAccount.fromJson(Map<String, dynamic> json) =>
      EmployeeAccount(
        available: json['tersedia'] as bool? ?? false,
        username: json['username'] as String?,
        active: json['aktif'] as bool? ?? false,
      );

  final bool available;
  final String? username;
  final bool active;
}

class EmployeeAssignmentCounts {
  const EmployeeAssignmentCounts({
    required this.activeHomeroomClasses,
    required this.activeSubjectAssignments,
  });

  factory EmployeeAssignmentCounts.fromJson(Map<String, dynamic> json) =>
      EmployeeAssignmentCounts(
        activeHomeroomClasses: _integer(json['kelas_wali_aktif']),
        activeSubjectAssignments: _integer(json['penugasan_mapel_aktif']),
      );

  final int activeHomeroomClasses;
  final int activeSubjectAssignments;
}

class EmployeeFormValue {
  const EmployeeFormValue({
    required this.name,
    required this.active,
    this.nip,
    this.nuptk,
    this.nik,
    this.gender,
    this.birthPlace,
    this.birthDate,
    this.address,
    this.email,
    this.phone,
    this.employmentStatus,
    this.rank,
    this.workStartDate,
    this.dutyStartDate,
    this.employeeType,
    this.primaryPosition,
    this.salarySource,
    this.lastEducation,
    this.educationMajor,
    this.graduationYear,
    this.notes,
  });

  factory EmployeeFormValue.fromDetail(EmployeeDetail detail) =>
      EmployeeFormValue(
        name: detail.summary.name,
        nip: detail.summary.nip,
        nuptk: detail.summary.nuptk,
        nik: detail.nik,
        gender: detail.summary.gender,
        birthPlace: detail.birthPlace,
        birthDate: detail.birthDate,
        address: detail.address,
        email: detail.email,
        phone: detail.phone,
        employmentStatus: detail.summary.employmentStatus,
        rank: detail.rank,
        workStartDate: detail.workStartDate,
        dutyStartDate: detail.dutyStartDate,
        employeeType: detail.summary.employeeType,
        primaryPosition: detail.summary.primaryPosition,
        salarySource: detail.salarySource,
        lastEducation: detail.lastEducation,
        educationMajor: detail.educationMajor,
        graduationYear: detail.graduationYear,
        notes: detail.notes,
        active: detail.summary.active,
      );

  final String name;
  final String? nip;
  final String? nuptk;
  final String? nik;
  final String? gender;
  final String? birthPlace;
  final DateTime? birthDate;
  final String? address;
  final String? email;
  final String? phone;
  final String? employmentStatus;
  final String? rank;
  final DateTime? workStartDate;
  final DateTime? dutyStartDate;
  final String? employeeType;
  final String? primaryPosition;
  final String? salarySource;
  final String? lastEducation;
  final String? educationMajor;
  final int? graduationYear;
  final String? notes;
  final bool active;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic>? _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value) : null;

String? _firstText(List<String?> values) {
  for (final value in values) {
    if (value?.trim().isNotEmpty == true) return value!.trim();
  }
  return null;
}
