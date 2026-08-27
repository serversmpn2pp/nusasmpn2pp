class MyGradesPage {
  const MyGradesPage({
    required this.academicYears,
    required this.filter,
    required this.summary,
    required this.subjects,
    required this.finalGradeLabel,
    this.student,
    this.selectedAcademicYear,
    this.schoolClass,
    this.emptyMessage,
  });

  factory MyGradesPage.fromJson(Map<String, dynamic> json) => MyGradesPage(
    student: json['siswa'] is Map
        ? MyGradesStudent.fromJson(_map(json['siswa']))
        : null,
    academicYears: _list(
      json['tahun_pelajaran'],
      MyGradesAcademicYear.fromJson,
    ),
    selectedAcademicYear: json['tahun_pelajaran_dipilih'] is Map
        ? MyGradesAcademicYear.fromJson(_map(json['tahun_pelajaran_dipilih']))
        : null,
    schoolClass: json['kelas'] is Map
        ? MyGradesClass.fromJson(_map(json['kelas']))
        : null,
    filter: MyGradesFilter.fromJson(_map(json['filter'])),
    summary: MyGradesSummary.fromJson(_map(json['ringkasan'])),
    subjects: _list(json['mata_pelajaran'], MyGradesSubject.fromJson),
    finalGradeLabel: json['label_nilai_akhir'] as String? ?? 'SAS',
    emptyMessage: json['pesan_kosong'] as String?,
  );

  final MyGradesStudent? student;
  final List<MyGradesAcademicYear> academicYears;
  final MyGradesAcademicYear? selectedAcademicYear;
  final MyGradesClass? schoolClass;
  final MyGradesFilter filter;
  final MyGradesSummary summary;
  final List<MyGradesSubject> subjects;
  final String finalGradeLabel;
  final String? emptyMessage;
}

class MyGradesStudent {
  const MyGradesStudent({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
  });

  factory MyGradesStudent.fromJson(Map<String, dynamic> json) =>
      MyGradesStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
      );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
}

class MyGradesAcademicYear {
  const MyGradesAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory MyGradesAcademicYear.fromJson(Map<String, dynamic> json) =>
      MyGradesAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;

  String get label => active ? '$name · Aktif' : name;
}

class MyGradesClass {
  const MyGradesClass({
    required this.id,
    required this.name,
    required this.grade,
    required this.membershipStatus,
    this.attendanceNumber,
  });

  factory MyGradesClass.fromJson(Map<String, dynamic> json) => MyGradesClass(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    grade: _integer(json['tingkat']),
    attendanceNumber: _nullableInteger(json['nomor_absen']),
    membershipStatus: json['status_keanggotaan'] as String? ?? '-',
  );

  final int id;
  final String name;
  final int grade;
  final int? attendanceNumber;
  final String membershipStatus;
}

class MyGradesFilter {
  const MyGradesFilter({required this.semester, this.academicYearId});

  factory MyGradesFilter.fromJson(Map<String, dynamic> json) => MyGradesFilter(
    academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
    semester: json['semester'] as String? ?? 'ganjil',
  );

  final int? academicYearId;
  final String semester;
}

class MyGradesSummary {
  const MyGradesSummary({
    required this.subjectCount,
    required this.openCount,
    required this.surveyRequiredCount,
  });

  factory MyGradesSummary.fromJson(Map<String, dynamic> json) =>
      MyGradesSummary(
        subjectCount: _integer(json['mata_pelajaran']),
        openCount: _integer(json['nilai_terbuka']),
        surveyRequiredCount: _integer(json['survei_belum_diisi']),
      );

  final int subjectCount;
  final int openCount;
  final int surveyRequiredCount;
}

class MyGradesSubject {
  const MyGradesSubject({
    required this.assignmentId,
    required this.subjectId,
    required this.subjectName,
    required this.teacherName,
    required this.open,
    required this.surveyRequired,
    required this.surveySemester,
    required this.usesPredicate,
    required this.finalGradeLabel,
    required this.complete,
    required this.status,
    required this.categories,
    required this.components,
    this.subjectCode,
    this.publishedAt,
    this.publishedAtLabel,
    this.finalGrade,
    this.minimumGrade,
    this.passed,
  });

  factory MyGradesSubject.fromJson(Map<String, dynamic> json) {
    final subject = _map(json['mata_pelajaran']);
    final teacher = _map(json['guru']);
    final publication = _map(json['publikasi']);
    final survey = _map(json['survei']);
    return MyGradesSubject(
      assignmentId: _integer(json['guru_mata_pelajaran_id']),
      subjectId: _integer(subject['id']),
      subjectCode: subject['kode'] as String?,
      subjectName: subject['nama'] as String? ?? 'Mata pelajaran',
      teacherName: teacher['nama'] as String? ?? 'Guru belum dicantumkan',
      publishedAt: publication['dipublikasikan_pada'] as String?,
      publishedAtLabel: publication['dipublikasikan_pada_label'] as String?,
      open: json['terbuka'] as bool? ?? false,
      surveyRequired: survey['diperlukan'] as bool? ?? false,
      surveySemester: survey['semester'] as String? ?? 'ganjil',
      usesPredicate: json['menggunakan_predikat'] as bool? ?? false,
      finalGradeLabel: json['label_nilai_akhir'] as String? ?? 'SAS',
      finalGrade: _nullableDouble(json['nilai_akhir']),
      complete: json['lengkap'] as bool? ?? false,
      minimumGrade: _nullableDouble(json['kkm']),
      passed: json['tuntas'] as bool?,
      status: json['status'] as String? ?? '-',
      categories: _list(json['kategori'], MyGradeCategory.fromJson),
      components: _list(json['komponen'], MyGradeComponent.fromJson),
    );
  }

  final int assignmentId;
  final int subjectId;
  final String? subjectCode;
  final String subjectName;
  final String teacherName;
  final String? publishedAt;
  final String? publishedAtLabel;
  final bool open;
  final bool surveyRequired;
  final String surveySemester;
  final bool usesPredicate;
  final String finalGradeLabel;
  final double? finalGrade;
  final bool complete;
  final double? minimumGrade;
  final bool? passed;
  final String status;
  final List<MyGradeCategory> categories;
  final List<MyGradeComponent> components;
}

class MyGradeCategory {
  const MyGradeCategory({
    required this.code,
    required this.label,
    required this.filledCount,
    required this.targetCount,
    required this.weight,
    this.average,
  });

  factory MyGradeCategory.fromJson(Map<String, dynamic> json) =>
      MyGradeCategory(
        code: json['kode'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
        average: _nullableDouble(json['rata_rata']),
        filledCount: _integer(json['jumlah_terisi']),
        targetCount: _integer(json['jumlah_target']),
        weight: _integer(json['bobot']),
      );

  final String code;
  final String label;
  final double? average;
  final int filledCount;
  final int targetCount;
  final int weight;
}

class MyGradeComponent {
  const MyGradeComponent({
    required this.id,
    required this.name,
    required this.type,
    required this.typeLabel,
    this.date,
    this.dateLabel,
    this.score,
    this.predicate,
    this.predicateLabel,
    this.notes,
  });

  factory MyGradeComponent.fromJson(Map<String, dynamic> json) =>
      MyGradeComponent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        type: json['jenis'] as String? ?? '-',
        typeLabel: json['label_jenis'] as String? ?? '-',
        date: json['tanggal'] as String?,
        dateLabel: json['tanggal_label'] as String?,
        score: _nullableDouble(json['nilai']),
        predicate: json['predikat'] as String?,
        predicateLabel: json['predikat_label'] as String?,
        notes: json['catatan'] as String?,
      );

  final int id;
  final String name;
  final String type;
  final String typeLabel;
  final String? date;
  final String? dateLabel;
  final double? score;
  final String? predicate;
  final String? predicateLabel;
  final String? notes;
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
