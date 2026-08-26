class GradeEntryPage {
  const GradeEntryPage({
    required this.assignments,
    required this.components,
    required this.students,
    required this.summary,
    required this.publication,
    required this.filter,
    required this.assessmentMode,
    required this.predicateOptions,
    required this.canInput,
    this.selectedComponent,
  });

  factory GradeEntryPage.fromJson(Map<String, dynamic> json) => GradeEntryPage(
    assignments: _list(
      json['guru_mata_pelajaran'],
      GradeEntryAssignment.fromJson,
    ),
    components: _list(json['komponen_nilai'], GradeEntryComponent.fromJson),
    selectedComponent: json['komponen_dipilih'] is Map
        ? GradeEntryComponent.fromJson(_map(json['komponen_dipilih']))
        : null,
    students: _list(json['siswa'], GradeEntryStudent.fromJson),
    summary: GradeEntrySummary.fromJson(_map(json['ringkasan'])),
    publication: GradePublication.fromJson(_map(json['publikasi'])),
    filter: GradeEntryFilter.fromJson(_map(json['filter'])),
    assessmentMode: json['mode_penilaian'] as String? ?? 'angka',
    predicateOptions: (json['opsi_predikat'] as List<dynamic>? ?? const [])
        .map((item) => item.toString())
        .toList(growable: false),
    canInput: _map(json['hak_akses'])['dapat_input'] as bool? ?? false,
  );

  final List<GradeEntryAssignment> assignments;
  final List<GradeEntryComponent> components;
  final GradeEntryComponent? selectedComponent;
  final List<GradeEntryStudent> students;
  final GradeEntrySummary summary;
  final GradePublication publication;
  final GradeEntryFilter filter;
  final String assessmentMode;
  final List<String> predicateOptions;
  final bool canInput;

  bool get usesPredicate => assessmentMode == 'predikat';
}

class GradeEntryAssignment {
  const GradeEntryAssignment({
    required this.id,
    required this.academicYearId,
    required this.academicYearName,
    required this.activeAcademicYear,
    required this.classId,
    required this.className,
    required this.grade,
    required this.subjectId,
    required this.subjectName,
    required this.employeeId,
    required this.employeeName,
    required this.assessmentMode,
    this.subjectCode,
    this.employeeNip,
  });

  factory GradeEntryAssignment.fromJson(Map<String, dynamic> json) {
    final year = _map(json['tahun_pelajaran']);
    final schoolClass = _map(json['kelas']);
    final subject = _map(json['mata_pelajaran']);
    final employee = _map(json['pegawai']);
    return GradeEntryAssignment(
      id: _integer(json['id']),
      academicYearId: _integer(year['id']),
      academicYearName: year['nama'] as String? ?? '-',
      activeAcademicYear: year['aktif'] as bool? ?? false,
      classId: _integer(schoolClass['id']),
      className: schoolClass['nama'] as String? ?? '-',
      grade: _integer(schoolClass['tingkat']),
      subjectId: _integer(subject['id']),
      subjectCode: subject['kode'] as String?,
      subjectName: subject['nama'] as String? ?? '-',
      assessmentMode: subject['mode_penilaian'] as String? ?? 'angka',
      employeeId: _integer(employee['id']),
      employeeName: employee['nama'] as String? ?? '-',
      employeeNip: employee['nip'] as String?,
    );
  }

  final int id;
  final int academicYearId;
  final String academicYearName;
  final bool activeAcademicYear;
  final int classId;
  final String className;
  final int grade;
  final int subjectId;
  final String? subjectCode;
  final String subjectName;
  final String assessmentMode;
  final int employeeId;
  final String employeeName;
  final String? employeeNip;

  String get label =>
      '$academicYearName · $className · $subjectName · $employeeName';
}

class GradeEntryComponent {
  const GradeEntryComponent({
    required this.id,
    required this.assignmentId,
    required this.semester,
    required this.type,
    required this.typeLabel,
    required this.name,
    required this.order,
    this.assessmentDate,
    this.assessmentDateLabel,
  });

  factory GradeEntryComponent.fromJson(Map<String, dynamic> json) =>
      GradeEntryComponent(
        id: _integer(json['id']),
        assignmentId: _integer(json['guru_mata_pelajaran_id']),
        semester: json['semester'] as String? ?? 'ganjil',
        type: json['jenis_komponen'] as String? ?? 'formatif',
        typeLabel: json['jenis_label'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        assessmentDate: json['tanggal_penilaian'] as String?,
        assessmentDateLabel: json['tanggal_label'] as String?,
        order: _integer(json['urutan']),
      );

  final int id;
  final int assignmentId;
  final String semester;
  final String type;
  final String typeLabel;
  final String name;
  final String? assessmentDate;
  final String? assessmentDateLabel;
  final int order;

  String get label => '$typeLabel · $name';
}

class GradeEntryStudent {
  const GradeEntryStudent({
    required this.membershipId,
    required this.studentId,
    required this.name,
    this.attendanceNumber,
    this.nis,
    this.nisn,
    this.score,
    this.predicate,
    this.notes,
  });

  factory GradeEntryStudent.fromJson(Map<String, dynamic> json) {
    final student = _map(json['siswa']);
    return GradeEntryStudent(
      membershipId: _integer(json['anggota_kelas_id']),
      attendanceNumber: _nullableInteger(json['nomor_absen']),
      studentId: _integer(student['id']),
      name: student['nama'] as String? ?? '-',
      nis: student['nis'] as String?,
      nisn: student['nisn'] as String?,
      score: _nullableDouble(json['nilai']),
      predicate: json['predikat'] as String?,
      notes: json['catatan'] as String?,
    );
  }

  final int membershipId;
  final int? attendanceNumber;
  final int studentId;
  final String name;
  final String? nis;
  final String? nisn;
  final double? score;
  final String? predicate;
  final String? notes;
}

class GradeEntrySummary {
  const GradeEntrySummary({
    required this.studentCount,
    required this.filledCount,
    required this.emptyCount,
    this.average,
  });

  factory GradeEntrySummary.fromJson(Map<String, dynamic> json) =>
      GradeEntrySummary(
        studentCount: _integer(json['jumlah_siswa']),
        filledCount: _integer(json['jumlah_terisi']),
        emptyCount: _integer(json['jumlah_belum_terisi']),
        average: _nullableDouble(json['rata_rata']),
      );

  final int studentCount;
  final int filledCount;
  final int emptyCount;
  final double? average;
}

class GradePublication {
  const GradePublication({
    required this.status,
    required this.published,
    required this.componentCount,
    required this.valueCount,
    required this.targetCount,
    required this.canPublish,
    required this.canUnpublish,
    this.publishedAt,
    this.publishedAtLabel,
  });

  factory GradePublication.fromJson(Map<String, dynamic> json) =>
      GradePublication(
        status: json['status'] as String? ?? 'draf',
        published: json['dipublikasikan'] as bool? ?? false,
        publishedAt: json['dipublikasikan_pada'] as String?,
        publishedAtLabel: json['dipublikasikan_pada_label'] as String?,
        componentCount: _integer(json['jumlah_komponen']),
        valueCount: _integer(json['jumlah_nilai']),
        targetCount: _integer(json['target_nilai']),
        canPublish: json['dapat_dipublikasikan'] as bool? ?? false,
        canUnpublish: json['dapat_dijadikan_draf'] as bool? ?? false,
      );

  final String status;
  final bool published;
  final String? publishedAt;
  final String? publishedAtLabel;
  final int componentCount;
  final int valueCount;
  final int targetCount;
  final bool canPublish;
  final bool canUnpublish;
}

class GradeEntryFilter {
  const GradeEntryFilter({
    required this.semester,
    this.assignmentId,
    this.componentId,
  });

  factory GradeEntryFilter.fromJson(Map<String, dynamic> json) =>
      GradeEntryFilter(
        assignmentId: _nullableInteger(json['guru_mata_pelajaran_id']),
        semester: json['semester'] as String? ?? 'ganjil',
        componentId: _nullableInteger(json['komponen_nilai_id']),
      );

  final int? assignmentId;
  final String semester;
  final int? componentId;
}

class GradeEntryFormValue {
  const GradeEntryFormValue({
    required this.componentId,
    required this.scores,
    required this.predicates,
    required this.notes,
  });

  final int componentId;
  final Map<int, double?> scores;
  final Map<int, String?> predicates;
  final Map<int, String?> notes;

  Map<String, dynamic> toJson() => {
    'komponen_nilai_id': componentId,
    'nilai': scores.map((key, value) => MapEntry('$key', value)),
    'predikat': predicates.map((key, value) => MapEntry('$key', value)),
    'catatan': notes.map((key, value) => MapEntry('$key', value)),
  };
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
