class StudentCaseProgressPage {
  const StudentCaseProgressPage({
    required this.summary,
    required this.items,
    required this.pagination,
    this.student,
    this.activeAcademicYear,
  });

  factory StudentCaseProgressPage.fromJson(Map<String, dynamic> json) =>
      StudentCaseProgressPage(
        student: _nullable(json['siswa'], StudentCasePerson.fromJson),
        activeAcademicYear: _nullable(
          json['tahun_pelajaran_aktif'],
          StudentCaseReference.fromJson,
        ),
        summary: StudentCaseSummary.fromJson(_map(json['ringkasan'])),
        items: _list(json['items'], StudentCaseItem.fromJson),
        pagination: StudentCasePagination.fromJson(_map(json['paginasi'])),
      );

  final StudentCasePerson? student;
  final StudentCaseReference? activeAcademicYear;
  final StudentCaseSummary summary;
  final List<StudentCaseItem> items;
  final StudentCasePagination pagination;

  StudentCaseProgressPage append(StudentCaseProgressPage next) =>
      StudentCaseProgressPage(
        student: next.student,
        activeAcademicYear: next.activeAcademicYear,
        summary: next.summary,
        items: [...items, ...next.items],
        pagination: next.pagination,
      );
}

class StudentCaseSummary {
  const StudentCaseSummary({
    required this.all,
    required this.inProgress,
    required this.guidance,
    required this.officialPoints,
  });

  factory StudentCaseSummary.fromJson(Map<String, dynamic> json) =>
      StudentCaseSummary(
        all: _integer(json['semua']),
        inProgress: _integer(json['diproses']),
        guidance: _integer(json['pembinaan']),
        officialPoints: _integer(json['poin_resmi']),
      );

  final int all;
  final int inProgress;
  final int guidance;
  final int officialPoints;
}

class StudentCaseItem {
  const StudentCaseItem({
    required this.id,
    required this.number,
    required this.title,
    required this.status,
    this.incidentDate,
    this.place,
    this.schoolClass,
    this.academicYear,
  });

  factory StudentCaseItem.fromJson(Map<String, dynamic> json) =>
      StudentCaseItem(
        id: _integer(json['id']),
        number: json['nomor_laporan'] as String? ?? '-',
        title: json['judul'] as String? ?? 'Laporan kejadian siswa',
        incidentDate: json['tanggal_kejadian'] as String?,
        place: json['tempat_kejadian'] as String?,
        schoolClass: _nullable(json['kelas'], StudentCaseReference.fromJson),
        academicYear: _nullable(
          json['tahun_pelajaran'],
          StudentCaseReference.fromJson,
        ),
        status: StudentCaseStatus.fromJson(_map(json['status'])),
      );

  final int id;
  final String number;
  final String title;
  final String? incidentDate;
  final String? place;
  final StudentCaseReference? schoolClass;
  final StudentCaseReference? academicYear;
  final StudentCaseStatus status;
}

class StudentCaseStatus {
  const StudentCaseStatus({
    required this.label,
    required this.description,
    required this.color,
    required this.step,
    required this.finalized,
    required this.handlingStatus,
  });

  factory StudentCaseStatus.fromJson(Map<String, dynamic> json) =>
      StudentCaseStatus(
        label: json['label'] as String? ?? 'Sedang ditangani sekolah',
        description: json['deskripsi'] as String? ?? '',
        color: json['warna'] as String? ?? 'info',
        step: _integer(json['langkah']),
        finalized: json['final'] as bool? ?? false,
        handlingStatus: json['status_penanganan'] as String? ?? '-',
      );

  final String label;
  final String description;
  final String color;
  final int step;
  final bool finalized;
  final String handlingStatus;
}

class StudentCaseDetail {
  const StudentCaseDetail({
    required this.student,
    required this.report,
    required this.stages,
    required this.decision,
    required this.timeline,
    required this.followUps,
    required this.privacy,
  });

  factory StudentCaseDetail.fromJson(Map<String, dynamic> json) =>
      StudentCaseDetail(
        student: StudentCasePerson.fromJson(_map(json['siswa'])),
        report: StudentCaseReport.fromJson(_map(json['laporan'])),
        stages: _list(json['tahapan'], StudentCaseStage.fromJson),
        decision: StudentCaseDecision.fromJson(_map(json['keputusan'])),
        timeline: _list(json['linimasa'], StudentCaseTimeline.fromJson),
        followUps: _list(json['tindak_lanjut'], StudentCaseFollowUp.fromJson),
        privacy: json['privasi'] as String? ?? '',
      );

  final StudentCasePerson student;
  final StudentCaseReport report;
  final List<StudentCaseStage> stages;
  final StudentCaseDecision decision;
  final List<StudentCaseTimeline> timeline;
  final List<StudentCaseFollowUp> followUps;
  final String privacy;
}

class StudentCaseReport {
  const StudentCaseReport({
    required this.item,
    required this.type,
    required this.typeLabel,
    required this.source,
    required this.chronology,
    this.incidentTime,
  });

  factory StudentCaseReport.fromJson(Map<String, dynamic> json) =>
      StudentCaseReport(
        item: StudentCaseItem.fromJson(json),
        type: json['jenis_laporan'] as String? ?? 'kejadian',
        typeLabel: json['label_jenis_laporan'] as String? ?? 'Laporan Kejadian',
        incidentTime: json['waktu_kejadian'] as String?,
        source: json['sumber'] as String? ?? '-',
        chronology: json['kronologi'] as String? ?? '-',
      );

  final StudentCaseItem item;
  final String type;
  final String typeLabel;
  final String? incidentTime;
  final String source;
  final String chronology;
}

class StudentCaseStage {
  const StudentCaseStage({
    required this.number,
    required this.label,
    required this.complete,
  });

  factory StudentCaseStage.fromJson(Map<String, dynamic> json) =>
      StudentCaseStage(
        number: _integer(json['nomor']),
        label: json['label'] as String? ?? '-',
        complete: json['selesai'] as bool? ?? false,
      );

  final int number;
  final String label;
  final bool complete;
}

class StudentCaseDecision {
  const StudentCaseDecision({
    required this.status,
    required this.description,
    required this.finalized,
    required this.violations,
    required this.provisional,
    this.officialPoints,
  });

  factory StudentCaseDecision.fromJson(Map<String, dynamic> json) =>
      StudentCaseDecision(
        status: json['status'] as String? ?? '-',
        description: json['deskripsi'] as String? ?? '',
        finalized: json['final'] as bool? ?? false,
        officialPoints: _nullableInteger(json['total_poin_resmi']),
        violations: _list(
          json['butir_pelanggaran'],
          StudentCaseViolation.fromJson,
        ),
        provisional: json['rekomendasi_belum_resmi'] as bool? ?? false,
      );

  final String status;
  final String description;
  final bool finalized;
  final int? officialPoints;
  final List<StudentCaseViolation> violations;
  final bool provisional;
}

class StudentCaseViolation {
  const StudentCaseViolation({required this.name, required this.points});

  factory StudentCaseViolation.fromJson(Map<String, dynamic> json) =>
      StudentCaseViolation(
        name: json['nama'] as String? ?? '-',
        points: _integer(json['poin']),
      );

  final String name;
  final int points;
}

class StudentCaseTimeline {
  const StudentCaseTimeline({
    required this.title,
    required this.description,
    this.date,
  });

  factory StudentCaseTimeline.fromJson(Map<String, dynamic> json) =>
      StudentCaseTimeline(
        title: json['judul'] as String? ?? '-',
        description: json['deskripsi'] as String? ?? '',
        date: json['tanggal'] as String?,
      );

  final String title;
  final String description;
  final String? date;
}

class StudentCaseFollowUp {
  const StudentCaseFollowUp({
    required this.type,
    required this.status,
    this.date,
  });

  factory StudentCaseFollowUp.fromJson(Map<String, dynamic> json) =>
      StudentCaseFollowUp(
        type: json['jenis'] as String? ?? '-',
        date: json['tanggal'] as String?,
        status: json['status'] as String? ?? '-',
      );

  final String type;
  final String? date;
  final String status;
}

class StudentCasePerson {
  const StudentCasePerson({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
  });

  factory StudentCasePerson.fromJson(Map<String, dynamic> json) =>
      StudentCasePerson(
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

class StudentCaseReference {
  const StudentCaseReference({required this.id, required this.name});

  factory StudentCaseReference.fromJson(Map<String, dynamic> json) =>
      StudentCaseReference(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class StudentCasePagination {
  const StudentCasePagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory StudentCasePagination.fromJson(Map<String, dynamic> json) =>
      StudentCasePagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
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

int _integer(Object? value) => value is num ? value.toInt() : 0;
int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
