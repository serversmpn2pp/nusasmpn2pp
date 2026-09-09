class BkGradeAssignmentPage {
  const BkGradeAssignmentPage({
    required this.academicYears,
    required this.levels,
    required this.teachers,
    required this.summary,
    required this.access,
    this.selectedAcademicYear,
  });

  factory BkGradeAssignmentPage.fromJson(Map<String, dynamic> json) =>
      BkGradeAssignmentPage(
        academicYears: _list(json['tahun_pelajaran'], BkAcademicYear.fromJson),
        selectedAcademicYear: _nullable(
          json['tahun_pelajaran_dipilih'],
          BkAcademicYear.fromJson,
        ),
        levels: _list(json['tingkat'], BkGradeLevel.fromJson),
        teachers: _list(json['guru_bk'], BkTeacher.fromJson),
        summary: BkGradeAssignmentSummary.fromJson(_map(json['ringkasan'])),
        access: BkGradeAssignmentAccess.fromJson(_map(json['hak_akses'])),
      );

  final List<BkAcademicYear> academicYears;
  final BkAcademicYear? selectedAcademicYear;
  final List<BkGradeLevel> levels;
  final List<BkTeacher> teachers;
  final BkGradeAssignmentSummary summary;
  final BkGradeAssignmentAccess access;
}

class BkAcademicYear {
  const BkAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory BkAcademicYear.fromJson(Map<String, dynamic> json) => BkAcademicYear(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    active: json['aktif'] as bool? ?? false,
  );

  final int id;
  final String name;
  final bool active;
}

class BkGradeLevel {
  const BkGradeLevel({
    required this.grade,
    required this.label,
    required this.assignments,
  });

  factory BkGradeLevel.fromJson(Map<String, dynamic> json) => BkGradeLevel(
    grade: _integer(json['tingkat']),
    label: json['label'] as String? ?? '-',
    assignments: _list(json['penugasan'], BkGradeAssignment.fromJson),
  );

  final int grade;
  final String label;
  final List<BkGradeAssignment> assignments;
}

class BkGradeAssignment {
  const BkGradeAssignment({
    required this.id,
    required this.grade,
    required this.teacher,
    required this.active,
    this.startDate,
    this.endDate,
  });

  factory BkGradeAssignment.fromJson(Map<String, dynamic> json) =>
      BkGradeAssignment(
        id: _integer(json['id']),
        grade: _integer(json['tingkat']),
        teacher: BkTeacher.fromJson(_map(json['guru_bk'])),
        startDate: json['tanggal_mulai'] as String?,
        endDate: json['tanggal_selesai'] as String?,
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final int grade;
  final BkTeacher teacher;
  final String? startDate;
  final String? endDate;
  final bool active;
}

class BkTeacher {
  const BkTeacher({
    required this.id,
    required this.name,
    required this.activeGrades,
    this.nip,
    this.position,
  });

  factory BkTeacher.fromJson(Map<String, dynamic> json) => BkTeacher(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    nip: json['nip'] as String?,
    position: json['jabatan'] as String?,
    activeGrades: _integers(json['tingkat_aktif']),
  );

  final int id;
  final String name;
  final String? nip;
  final String? position;
  final List<int> activeGrades;
}

class BkGradeAssignmentSummary {
  const BkGradeAssignmentSummary({
    required this.assignmentCount,
    required this.teacherCount,
    required this.filledLevelCount,
    required this.distributionActive,
  });

  factory BkGradeAssignmentSummary.fromJson(Map<String, dynamic> json) =>
      BkGradeAssignmentSummary(
        assignmentCount: _integer(json['jumlah_penugasan']),
        teacherCount: _integer(json['jumlah_guru_bk']),
        filledLevelCount: _integer(json['tingkat_terisi']),
        distributionActive: json['pembagian_aktif'] as bool? ?? false,
      );

  final int assignmentCount;
  final int teacherCount;
  final int filledLevelCount;
  final bool distributionActive;
}

class BkGradeAssignmentAccess {
  const BkGradeAssignmentAccess({required this.canManage});

  factory BkGradeAssignmentAccess.fromJson(Map<String, dynamic> json) =>
      BkGradeAssignmentAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
      );

  final bool canManage;
}

class BkGradeAssignmentPayload {
  const BkGradeAssignmentPayload({
    required this.academicYearId,
    required this.teacherId,
    required this.grades,
  });

  final int academicYearId;
  final int teacherId;
  final List<int> grades;

  Map<String, dynamic> toJson() => {
    'tahun_pelajaran_id': academicYearId,
    'pegawai_id': teacherId,
    'tingkat': grades,
  };
}

class BkGradeAssignmentMutation {
  const BkGradeAssignmentMutation({required this.message});

  factory BkGradeAssignmentMutation.fromJson(Map<String, dynamic> json) =>
      BkGradeAssignmentMutation(
        message: json['message'] as String? ?? 'Penugasan berhasil diproses.',
      );

  final String message;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

T? _nullable<T>(Object? value, T Function(Map<String, dynamic>) parser) =>
    value is Map ? parser(Map<String, dynamic>.from(value)) : null;

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) parser) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => parser(Map<String, dynamic>.from(item)))
        .toList(growable: false);

List<int> _integers(Object? value) => (value as List<dynamic>? ?? const [])
    .whereType<num>()
    .map((item) => item.toInt())
    .toList(growable: false);

int _integer(Object? value) => value is num ? value.toInt() : 0;
