import 'dart:typed_data';

class IncidentReportReference {
  const IncidentReportReference({
    required this.defaultDate,
    required this.limits,
    required this.academicYears,
    required this.classes,
    required this.students,
    this.defaultAcademicYearId,
  });

  factory IncidentReportReference.fromJson(Map<String, dynamic> json) {
    final defaults = _map(json['nilai_awal']);
    return IncidentReportReference(
      defaultDate: defaults['tanggal_kejadian'] as String? ?? '',
      defaultAcademicYearId: _nullableInteger(defaults['tahun_pelajaran_id']),
      limits: IncidentReportLimits.fromJson(_map(json['batas'])),
      academicYears: _list(
        json['tahun_pelajaran'],
        IncidentAcademicYear.fromJson,
      ),
      classes: _list(json['kelas'], IncidentClass.fromJson),
      students: _list(json['siswa'], IncidentStudent.fromJson),
    );
  }

  final String defaultDate;
  final int? defaultAcademicYearId;
  final IncidentReportLimits limits;
  final List<IncidentAcademicYear> academicYears;
  final List<IncidentClass> classes;
  final List<IncidentStudent> students;
}

class IncidentReportLimits {
  const IncidentReportLimits({
    required this.maxStudents,
    required this.maxWitnesses,
    required this.maxEvidence,
    required this.maxEvidenceMb,
  });

  factory IncidentReportLimits.fromJson(Map<String, dynamic> json) =>
      IncidentReportLimits(
        maxStudents: _integer(json['maksimal_siswa']),
        maxWitnesses: _integer(json['maksimal_saksi']),
        maxEvidence: _integer(json['maksimal_bukti']),
        maxEvidenceMb: _integer(json['maksimal_bukti_mb']),
      );

  final int maxStudents;
  final int maxWitnesses;
  final int maxEvidence;
  final int maxEvidenceMb;
}

class IncidentAcademicYear {
  const IncidentAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory IncidentAcademicYear.fromJson(Map<String, dynamic> json) =>
      IncidentAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class IncidentClass {
  const IncidentClass({
    required this.id,
    required this.academicYearId,
    required this.name,
    required this.grade,
  });

  factory IncidentClass.fromJson(Map<String, dynamic> json) => IncidentClass(
    id: _integer(json['id']),
    academicYearId: _integer(json['tahun_pelajaran_id']),
    name: json['nama'] as String? ?? '-',
    grade: _integer(json['tingkat']),
  );

  final int id;
  final int academicYearId;
  final String name;
  final int grade;
}

class IncidentStudent {
  const IncidentStudent({
    required this.id,
    required this.name,
    required this.placements,
    this.studentNumber,
    this.nationalStudentNumber,
  });

  factory IncidentStudent.fromJson(Map<String, dynamic> json) =>
      IncidentStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        studentNumber: json['nis'] as String?,
        nationalStudentNumber: json['nisn'] as String?,
        placements: _list(json['penempatan'], IncidentPlacement.fromJson),
      );

  final int id;
  final String name;
  final String? studentNumber;
  final String? nationalStudentNumber;
  final List<IncidentPlacement> placements;

  bool belongsTo({int? academicYearId, int? classId}) => placements.any(
    (item) =>
        (academicYearId == null || item.academicYearId == academicYearId) &&
        (classId == null || item.classId == classId),
  );

  String classLabel({int? academicYearId}) =>
      placements
          .where(
            (item) =>
                academicYearId == null || item.academicYearId == academicYearId,
          )
          .firstOrNull
          ?.className ??
      'Belum ditempatkan';
}

class IncidentPlacement {
  const IncidentPlacement({
    required this.academicYearId,
    required this.classId,
    required this.className,
  });

  factory IncidentPlacement.fromJson(Map<String, dynamic> json) =>
      IncidentPlacement(
        academicYearId: _integer(json['tahun_pelajaran_id']),
        classId: _integer(json['kelas_id']),
        className: json['kelas'] as String? ?? '-',
      );

  final int academicYearId;
  final int classId;
  final String className;
}

class IncidentWitnessValue {
  const IncidentWitnessValue({
    required this.type,
    required this.name,
    required this.statement,
  });

  final String type;
  final String name;
  final String statement;
}

class IncidentEvidenceFile {
  const IncidentEvidenceFile({required this.name, required this.bytes});

  final String name;
  final Uint8List bytes;
}

class IncidentReportFormValue {
  const IncidentReportFormValue({
    required this.date,
    required this.studentIds,
    required this.chronology,
    required this.witnesses,
    required this.evidence,
    this.time,
    this.place,
    this.academicYearId,
    this.classId,
    this.initialAction,
    this.evidenceDescription,
  });

  final String date;
  final String? time;
  final String? place;
  final int? academicYearId;
  final int? classId;
  final List<int> studentIds;
  final String chronology;
  final String? initialAction;
  final List<IncidentWitnessValue> witnesses;
  final List<IncidentEvidenceFile> evidence;
  final String? evidenceDescription;
}

class IncidentReportResult {
  const IncidentReportResult({
    required this.message,
    required this.reportCount,
    required this.reports,
  });

  factory IncidentReportResult.fromJson(Map<String, dynamic> json) {
    final data = _map(json['data']);
    return IncidentReportResult(
      message: json['pesan'] as String? ?? 'Laporan berhasil dikirim.',
      reportCount: _integer(data['jumlah_laporan']),
      reports: _list(data['laporan'], IncidentCreatedReport.fromJson),
    );
  }

  final String message;
  final int reportCount;
  final List<IncidentCreatedReport> reports;
}

class IncidentCreatedReport {
  const IncidentCreatedReport({required this.id, required this.number});

  factory IncidentCreatedReport.fromJson(Map<String, dynamic> json) =>
      IncidentCreatedReport(
        id: _integer(json['id']),
        number: json['nomor_laporan'] as String? ?? '-',
      );

  final int id;
  final String number;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => convert(Map<String, dynamic>.from(item)))
          .toList(growable: false)
    : <T>[];

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
