class GradeRecapPage {
  const GradeRecapPage({
    required this.assignments,
    required this.categories,
    required this.students,
    required this.summary,
    required this.filter,
    required this.finalGradeLabel,
    required this.warnings,
    required this.canView,
    this.selectedAssignment,
    this.scheme,
  });

  factory GradeRecapPage.fromJson(Map<String, dynamic> json) {
    final selected = json['guru_mata_pelajaran_dipilih'];
    return GradeRecapPage(
      assignments: _list(
        json['guru_mata_pelajaran'],
        GradeRecapAssignment.fromJson,
      ),
      selectedAssignment: selected is Map
          ? GradeRecapAssignment.fromJson(Map<String, dynamic>.from(selected))
          : null,
      categories: _list(json['kategori'], GradeRecapCategory.fromJson),
      students: _list(json['siswa'], GradeRecapStudent.fromJson),
      summary: GradeRecapSummary.fromJson(_map(json['ringkasan'])),
      filter: GradeRecapFilter.fromJson(_map(json['filter'])),
      scheme: json['skema'] is Map
          ? GradeRecapScheme.fromJson(_map(json['skema']))
          : null,
      finalGradeLabel: json['label_nilai_akhir'] as String? ?? 'SAS/SAJ',
      warnings: (json['peringatan'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(growable: false),
      canView: _map(json['hak_akses'])['dapat_melihat'] as bool? ?? false,
    );
  }

  final List<GradeRecapAssignment> assignments;
  final GradeRecapAssignment? selectedAssignment;
  final GradeRecapScheme? scheme;
  final List<GradeRecapCategory> categories;
  final List<GradeRecapStudent> students;
  final GradeRecapSummary summary;
  final GradeRecapFilter filter;
  final String finalGradeLabel;
  final List<String> warnings;
  final bool canView;
}

class GradeRecapAssignment {
  const GradeRecapAssignment({
    required this.id,
    required this.academicYearName,
    required this.activeAcademicYear,
    required this.className,
    required this.grade,
    required this.subjectName,
    required this.employeeName,
    this.subjectCode,
    this.employeeNip,
  });

  factory GradeRecapAssignment.fromJson(Map<String, dynamic> json) {
    final year = _map(json['tahun_pelajaran']);
    final schoolClass = _map(json['kelas']);
    final subject = _map(json['mata_pelajaran']);
    final employee = _map(json['pegawai']);
    return GradeRecapAssignment(
      id: _integer(json['id']),
      academicYearName: year['nama'] as String? ?? '-',
      activeAcademicYear: year['aktif'] as bool? ?? false,
      className: schoolClass['nama'] as String? ?? '-',
      grade: _integer(schoolClass['tingkat']),
      subjectCode: subject['kode'] as String?,
      subjectName: subject['nama'] as String? ?? '-',
      employeeName: employee['nama'] as String? ?? '-',
      employeeNip: employee['nip'] as String?,
    );
  }

  final int id;
  final String academicYearName;
  final bool activeAcademicYear;
  final String className;
  final int grade;
  final String? subjectCode;
  final String subjectName;
  final String employeeName;
  final String? employeeNip;

  String get label =>
      '$academicYearName · $className · $subjectName · $employeeName';
}

class GradeRecapScheme {
  const GradeRecapScheme({
    required this.id,
    required this.gradeLabel,
    required this.finalGradeLabel,
    required this.weights,
  });

  factory GradeRecapScheme.fromJson(Map<String, dynamic> json) =>
      GradeRecapScheme(
        id: _integer(json['id']),
        gradeLabel: json['tingkat_label'] as String? ?? '-',
        finalGradeLabel: json['label_nilai_akhir'] as String? ?? 'SAS/SAJ',
        weights: _map(json['bobot'])
            .map((key, value) => MapEntry(key, _integer(value))),
      );

  final int id;
  final String gradeLabel;
  final String finalGradeLabel;
  final Map<String, int> weights;
}

class GradeRecapCategory {
  const GradeRecapCategory({
    required this.code,
    required this.label,
    required this.componentCount,
    required this.weight,
  });

  factory GradeRecapCategory.fromJson(Map<String, dynamic> json) =>
      GradeRecapCategory(
        code: json['kode'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
        componentCount: _integer(json['jumlah_komponen']),
        weight: _integer(json['bobot']),
      );

  final String code;
  final String label;
  final int componentCount;
  final int weight;
}

class GradeRecapStudent {
  const GradeRecapStudent({
    required this.membershipId,
    required this.studentId,
    required this.name,
    required this.categories,
    required this.complete,
    required this.status,
    this.attendanceNumber,
    this.nis,
    this.nisn,
    this.finalGrade,
  });

  factory GradeRecapStudent.fromJson(Map<String, dynamic> json) {
    final student = _map(json['siswa']);
    return GradeRecapStudent(
      membershipId: _integer(json['anggota_kelas_id']),
      attendanceNumber: _nullableInteger(json['nomor_absen']),
      studentId: _integer(student['id']),
      name: student['nama'] as String? ?? '-',
      nis: student['nis'] as String?,
      nisn: student['nisn'] as String?,
      categories: _map(json['kategori']).map(
        (key, value) =>
            MapEntry(key, GradeRecapCategoryResult.fromJson(_map(value))),
      ),
      finalGrade: _nullableDouble(json['nilai_akhir']),
      complete: json['lengkap'] as bool? ?? false,
      status: json['status'] as String? ?? '-',
    );
  }

  final int membershipId;
  final int? attendanceNumber;
  final int studentId;
  final String name;
  final String? nis;
  final String? nisn;
  final Map<String, GradeRecapCategoryResult> categories;
  final double? finalGrade;
  final bool complete;
  final String status;
}

class GradeRecapCategoryResult {
  const GradeRecapCategoryResult({
    required this.filled,
    required this.target,
    required this.weight,
    this.average,
  });

  factory GradeRecapCategoryResult.fromJson(Map<String, dynamic> json) =>
      GradeRecapCategoryResult(
        average: _nullableDouble(json['rata']),
        filled: _integer(json['terisi']),
        target: _integer(json['target']),
        weight: _integer(json['bobot']),
      );

  final double? average;
  final int filled;
  final int target;
  final int weight;
}

class GradeRecapSummary {
  const GradeRecapSummary({
    required this.studentCount,
    required this.completeCount,
    required this.incompleteCount,
    this.finalAverage,
  });

  factory GradeRecapSummary.fromJson(Map<String, dynamic> json) =>
      GradeRecapSummary(
        studentCount: _integer(json['jumlah_siswa']),
        completeCount: _integer(json['jumlah_lengkap']),
        incompleteCount: _integer(json['jumlah_belum_lengkap']),
        finalAverage: _nullableDouble(json['rata_rata_akhir']),
      );

  final int studentCount;
  final int completeCount;
  final int incompleteCount;
  final double? finalAverage;
}

class GradeRecapFilter {
  const GradeRecapFilter({required this.semester, this.assignmentId});

  factory GradeRecapFilter.fromJson(Map<String, dynamic> json) =>
      GradeRecapFilter(
        assignmentId: _nullableInteger(json['guru_mata_pelajaran_id']),
        semester: json['semester'] as String? ?? 'ganjil',
      );

  final int? assignmentId;
  final String semester;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);

double? _nullableDouble(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text),
  _ => null,
};
