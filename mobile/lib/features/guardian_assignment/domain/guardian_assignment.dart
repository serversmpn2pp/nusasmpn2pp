class GuardianAssignmentPage {
  const GuardianAssignmentPage({
    required this.items,
    required this.summary,
    required this.options,
    required this.filter,
    required this.pagination,
    required this.access,
  });

  factory GuardianAssignmentPage.fromJson(Map<String, dynamic> json) =>
      GuardianAssignmentPage(
        items: _list(json['items'], GuardianAssignmentItem.fromJson),
        summary: GuardianAssignmentSummary.fromJson(_map(json['ringkasan'])),
        options: GuardianAssignmentOptions.fromJson(_map(json['pilihan'])),
        filter: GuardianAssignmentFilter.fromJson(_map(json['filter'])),
        pagination: GuardianAssignmentPagination.fromJson(
          _map(json['paginasi']),
        ),
        access: GuardianAssignmentAccess.fromJson(_map(json['hak_akses'])),
      );

  final List<GuardianAssignmentItem> items;
  final GuardianAssignmentSummary summary;
  final GuardianAssignmentOptions options;
  final GuardianAssignmentFilter filter;
  final GuardianAssignmentPagination pagination;
  final GuardianAssignmentAccess access;

  GuardianAssignmentPage append(GuardianAssignmentPage next) =>
      GuardianAssignmentPage(
        items: [...items, ...next.items],
        summary: next.summary,
        options: next.options,
        filter: next.filter,
        pagination: next.pagination,
        access: next.access,
      );
}

class GuardianAssignmentSummary {
  const GuardianAssignmentSummary({
    required this.activeStudents,
    required this.assignedStudents,
    required this.unassignedStudents,
    required this.activeGuardians,
  });
  factory GuardianAssignmentSummary.fromJson(Map<String, dynamic> json) =>
      GuardianAssignmentSummary(
        activeStudents: _integer(json['jumlah_siswa_aktif']),
        assignedStudents: _integer(json['jumlah_ditugaskan']),
        unassignedStudents: _integer(json['jumlah_belum_ditugaskan']),
        activeGuardians: _integer(json['jumlah_guru_wali']),
      );
  final int activeStudents;
  final int assignedStudents;
  final int unassignedStudents;
  final int activeGuardians;
}

class GuardianAssignmentItem {
  const GuardianAssignmentItem({
    required this.id,
    required this.student,
    required this.guardian,
    required this.startDate,
    required this.active,
    this.schoolClass,
    this.endDate,
    this.decreeNumber,
    this.note,
    this.createdBy,
  });
  factory GuardianAssignmentItem.fromJson(Map<String, dynamic> json) =>
      GuardianAssignmentItem(
        id: _integer(json['id']),
        student: GuardianPerson.fromJson(_map(json['siswa'])),
        schoolClass: _nullable(json['kelas'], GuardianSchoolClass.fromJson),
        guardian: GuardianEmployee.fromJson(_map(json['guru_wali'])),
        startDate: json['tanggal_mulai'] as String? ?? '',
        endDate: json['tanggal_selesai'] as String?,
        decreeNumber: json['nomor_sk'] as String?,
        note: json['catatan'] as String?,
        active: json['aktif'] as bool? ?? false,
        createdBy: json['dibuat_oleh'] as String?,
      );
  final int id;
  final GuardianPerson student;
  final GuardianSchoolClass? schoolClass;
  final GuardianEmployee guardian;
  final String startDate;
  final String? endDate;
  final String? decreeNumber;
  final String? note;
  final bool active;
  final String? createdBy;
}

class GuardianAssignmentOptions {
  const GuardianAssignmentOptions({
    required this.employees,
    required this.students,
    required this.classes,
  });
  factory GuardianAssignmentOptions.fromJson(Map<String, dynamic> json) =>
      GuardianAssignmentOptions(
        employees: _list(json['pegawai'], GuardianEmployee.fromJson),
        students: _list(json['siswa'], GuardianStudent.fromJson),
        classes: _list(json['kelas'], GuardianSchoolClass.fromJson),
      );
  final List<GuardianEmployee> employees;
  final List<GuardianStudent> students;
  final List<GuardianSchoolClass> classes;
}

class GuardianAssignmentFilter {
  const GuardianAssignmentFilter({required this.query, this.guardianId});
  factory GuardianAssignmentFilter.fromJson(Map<String, dynamic> json) =>
      GuardianAssignmentFilter(
        query: json['kata_kunci'] as String? ?? '',
        guardianId: _nullableInteger(json['guru_wali_pegawai_id']),
      );
  final String query;
  final int? guardianId;
}

class GuardianAssignmentPagination {
  const GuardianAssignmentPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });
  factory GuardianAssignmentPagination.fromJson(Map<String, dynamic> json) =>
      GuardianAssignmentPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int total;
  final bool hasNextPage;
}

class GuardianAssignmentAccess {
  const GuardianAssignmentAccess({required this.canManage});
  factory GuardianAssignmentAccess.fromJson(Map<String, dynamic> json) =>
      GuardianAssignmentAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
      );
  final bool canManage;
}

class GuardianStudent extends GuardianPerson {
  const GuardianStudent({
    required super.id,
    required super.name,
    super.nis,
    super.nisn,
    this.schoolClass,
    this.activeAssignment,
  });
  factory GuardianStudent.fromJson(Map<String, dynamic> json) =>
      GuardianStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        schoolClass: _nullable(json['kelas'], GuardianSchoolClass.fromJson),
        activeAssignment: _nullable(
          json['penugasan_aktif'],
          GuardianActiveAssignment.fromJson,
        ),
      );
  final GuardianSchoolClass? schoolClass;
  final GuardianActiveAssignment? activeAssignment;
}

class GuardianActiveAssignment {
  const GuardianActiveAssignment({required this.id, required this.guardian});
  factory GuardianActiveAssignment.fromJson(Map<String, dynamic> json) =>
      GuardianActiveAssignment(
        id: _integer(json['id']),
        guardian: GuardianEmployee.fromJson(_map(json['guru_wali'])),
      );
  final int id;
  final GuardianEmployee guardian;
}

class GuardianEmployee extends GuardianPerson {
  const GuardianEmployee({
    required super.id,
    required super.name,
    super.nip,
    this.position,
    this.activeStudentCount = 0,
  });
  factory GuardianEmployee.fromJson(Map<String, dynamic> json) =>
      GuardianEmployee(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        position: json['jabatan'] as String?,
        activeStudentCount: _integer(json['jumlah_siswa_aktif']),
      );
  final String? position;
  final int activeStudentCount;
}

class GuardianPerson {
  const GuardianPerson({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
    this.nip,
  });
  factory GuardianPerson.fromJson(Map<String, dynamic> json) => GuardianPerson(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    nis: json['nis'] as String?,
    nisn: json['nisn'] as String?,
    nip: json['nip'] as String?,
  );
  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? nip;
}

class GuardianSchoolClass {
  const GuardianSchoolClass({
    required this.id,
    required this.name,
    required this.grade,
  });
  factory GuardianSchoolClass.fromJson(Map<String, dynamic> json) =>
      GuardianSchoolClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
      );
  final int id;
  final String name;
  final int grade;
}

class GuardianAssignmentPayload {
  const GuardianAssignmentPayload({
    required this.guardianId,
    required this.studentIds,
    required this.startDate,
    this.decreeNumber,
    this.note,
  });
  final int guardianId;
  final List<int> studentIds;
  final String startDate;
  final String? decreeNumber;
  final String? note;

  Map<String, dynamic> toJson() => {
    'guru_wali_pegawai_id': guardianId,
    'siswa_ids': studentIds,
    'tanggal_mulai': startDate,
    'nomor_sk': ?decreeNumber,
    'catatan': ?note,
  };
}

class GuardianAssignmentResult {
  const GuardianAssignmentResult({
    required this.message,
    required this.created,
    required this.transferred,
    required this.unchanged,
  });
  factory GuardianAssignmentResult.fromJson(Map<String, dynamic> json) {
    final data = _map(json['data']);
    return GuardianAssignmentResult(
      message: json['message'] as String? ?? 'Penugasan berhasil disimpan.',
      created: _integer(data['baru']),
      transferred: _integer(data['dipindahkan']),
      unchanged: _integer(data['tetap']),
    );
  }
  final String message;
  final int created;
  final int transferred;
  final int unchanged;
}

class GuardianAssignmentMutation {
  const GuardianAssignmentMutation({required this.message, required this.item});
  final String message;
  final GuardianAssignmentItem item;
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
