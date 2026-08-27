class StudentPlacementPage {
  const StudentPlacementPage({
    required this.academicYears,
    required this.classes,
    required this.members,
    required this.availableStudents,
    required this.summary,
    required this.query,
    required this.canManage,
    required this.homeroomScope,
    this.selectedAcademicYearId,
    this.selectedClassId,
    this.selectedClass,
  });

  factory StudentPlacementPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final access = _map(json['hak_akses']);
    final selectedClass = _mapOrNull(json['kelas_dipilih']);
    return StudentPlacementPage(
      academicYears: _list(
        json['tahun_pelajaran'],
        StudentPlacementAcademicYear.fromJson,
      ),
      classes: _list(json['kelas'], StudentPlacementClass.fromJson),
      selectedClass: selectedClass == null
          ? null
          : StudentPlacementClass.fromJson(selectedClass),
      members: _list(json['anggota'], StudentPlacementMember.fromJson),
      availableStudents: _list(
        json['siswa_tersedia'],
        StudentPlacementStudent.fromJson,
      ),
      summary: StudentPlacementSummary.fromJson(_map(json['ringkasan'])),
      selectedAcademicYearId: _nullableInteger(filter['tahun_pelajaran_id']),
      selectedClassId: _nullableInteger(filter['kelas_id']),
      query: filter['cari'] as String? ?? '',
      canManage: access['dapat_kelola'] as bool? ?? false,
      homeroomScope: access['cakupan_wali_kelas'] as bool? ?? false,
    );
  }

  final List<StudentPlacementAcademicYear> academicYears;
  final List<StudentPlacementClass> classes;
  final StudentPlacementClass? selectedClass;
  final List<StudentPlacementMember> members;
  final List<StudentPlacementStudent> availableStudents;
  final StudentPlacementSummary summary;
  final int? selectedAcademicYearId;
  final int? selectedClassId;
  final String query;
  final bool canManage;
  final bool homeroomScope;
}

class StudentPlacementAcademicYear {
  const StudentPlacementAcademicYear({
    required this.id,
    required this.name,
    required this.active,
    required this.classCount,
  });

  factory StudentPlacementAcademicYear.fromJson(Map<String, dynamic> json) =>
      StudentPlacementAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
        classCount: _integer(json['jumlah_kelas']),
      );

  final int id;
  final String name;
  final bool active;
  final int classCount;
}

class StudentPlacementClass {
  const StudentPlacementClass({
    required this.id,
    required this.name,
    required this.active,
    required this.memberCount,
    this.level,
    this.capacity,
    this.remainingSeats,
    this.homeroomTeacher,
  });

  factory StudentPlacementClass.fromJson(Map<String, dynamic> json) =>
      StudentPlacementClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        level: _nullableInteger(json['tingkat']),
        active: json['aktif'] as bool? ?? false,
        capacity: _nullableInteger(json['kapasitas']),
        memberCount: _integer(json['jumlah_anggota']),
        remainingSeats: _nullableInteger(json['sisa_kursi']),
        homeroomTeacher: json['wali_kelas'] as String?,
      );

  final int id;
  final String name;
  final int? level;
  final bool active;
  final int? capacity;
  final int memberCount;
  final int? remainingSeats;
  final String? homeroomTeacher;
}

class StudentPlacementStudent {
  const StudentPlacementStudent({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
    this.gender,
    this.photoUrl,
    this.active = true,
  });

  factory StudentPlacementStudent.fromJson(Map<String, dynamic> json) =>
      StudentPlacementStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        gender: json['jenis_kelamin'] as String?,
        photoUrl: json['foto_url'] as String?,
        active: json['aktif'] as bool? ?? true,
      );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? gender;
  final String? photoUrl;
  final bool active;
}

class StudentPlacementMember {
  const StudentPlacementMember({
    required this.id,
    required this.status,
    required this.student,
    this.rollNumber,
    this.entryDate,
  });

  factory StudentPlacementMember.fromJson(Map<String, dynamic> json) =>
      StudentPlacementMember(
        id: _integer(json['id']),
        rollNumber: _nullableInteger(json['nomor_absen']),
        status: json['status'] as String? ?? '-',
        entryDate: json['tanggal_masuk'] as String?,
        student: StudentPlacementStudent.fromJson(_map(json['siswa'])),
      );

  final int id;
  final int? rollNumber;
  final String status;
  final String? entryDate;
  final StudentPlacementStudent student;
}

class StudentPlacementSummary {
  const StudentPlacementSummary({
    required this.activeStudents,
    required this.placed,
    required this.unplaced,
  });

  factory StudentPlacementSummary.fromJson(Map<String, dynamic> json) =>
      StudentPlacementSummary(
        activeStudents: _integer(json['siswa_aktif']),
        placed: _integer(json['ditempatkan']),
        unplaced: _integer(json['belum_ditempatkan']),
      );

  final int activeStudents;
  final int placed;
  final int unplaced;
}

class StudentPlacementFormValue {
  const StudentPlacementFormValue({
    required this.classId,
    required this.studentIds,
    this.entryDate,
    this.notes,
  });

  final int classId;
  final List<int> studentIds;
  final DateTime? entryDate;
  final String? notes;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

Map<String, dynamic>? _mapOrNull(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
