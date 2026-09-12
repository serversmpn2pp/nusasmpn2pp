import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';

enum ParentChildGuidanceTab {
  reports('laporan'),
  points('poin');

  const ParentChildGuidanceTab(this.apiValue);
  final String apiValue;

  static ParentChildGuidanceTab fromApi(String? value) =>
      value == points.apiValue ? points : reports;
}

class ParentChildGuidancePage {
  const ParentChildGuidancePage({
    required this.children,
    required this.academicYears,
    required this.filter,
    required this.summary,
    required this.reports,
    required this.points,
    required this.pagination,
    required this.privacy,
    this.student,
    this.selectedAcademicYear,
    this.schoolClass,
  });

  factory ParentChildGuidancePage.fromJson(Map<String, dynamic> json) =>
      ParentChildGuidancePage(
        student: _nullable(json['siswa'], StudentCasePerson.fromJson),
        children: _list(json['pilihan_siswa'], StudentCasePerson.fromJson),
        academicYears: _list(
          json['tahun_pelajaran'],
          ParentChildAcademicYear.fromJson,
        ),
        selectedAcademicYear: _nullable(
          json['tahun_pelajaran_dipilih'],
          ParentChildAcademicYear.fromJson,
        ),
        schoolClass: _nullable(
          json['kelas'],
          ParentChildGuidanceClass.fromJson,
        ),
        filter: ParentChildGuidanceFilter.fromJson(_map(json['filter'])),
        summary: ParentChildGuidanceSummary.fromJson(_map(json['ringkasan'])),
        reports: _list(json['laporan'], StudentCaseItem.fromJson),
        points: _list(json['riwayat_poin'], ParentChildPoint.fromJson),
        pagination: StudentCasePagination.fromJson(_map(json['paginasi'])),
        privacy: json['privasi'] as String? ?? '',
      );

  final StudentCasePerson? student;
  final List<StudentCasePerson> children;
  final List<ParentChildAcademicYear> academicYears;
  final ParentChildAcademicYear? selectedAcademicYear;
  final ParentChildGuidanceClass? schoolClass;
  final ParentChildGuidanceFilter filter;
  final ParentChildGuidanceSummary summary;
  final List<StudentCaseItem> reports;
  final List<ParentChildPoint> points;
  final StudentCasePagination pagination;
  final String privacy;

  ParentChildGuidancePage append(ParentChildGuidancePage next) =>
      ParentChildGuidancePage(
        student: next.student,
        children: next.children,
        academicYears: next.academicYears,
        selectedAcademicYear: next.selectedAcademicYear,
        schoolClass: next.schoolClass,
        filter: next.filter,
        summary: next.summary,
        reports: [...reports, ...next.reports],
        points: [...points, ...next.points],
        pagination: next.pagination,
        privacy: next.privacy,
      );
}

class ParentChildGuidanceFilter {
  const ParentChildGuidanceFilter({
    required this.tab,
    this.studentId,
    this.academicYearId,
  });

  factory ParentChildGuidanceFilter.fromJson(Map<String, dynamic> json) =>
      ParentChildGuidanceFilter(
        tab: ParentChildGuidanceTab.fromApi(json['tab'] as String?),
        studentId: _nullableInteger(json['siswa_id']),
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
      );

  final ParentChildGuidanceTab tab;
  final int? studentId;
  final int? academicYearId;
}

class ParentChildGuidanceSummary {
  const ParentChildGuidanceSummary({
    required this.reports,
    required this.inProgress,
    required this.violationPoints,
    required this.reductions,
    required this.balance,
  });

  factory ParentChildGuidanceSummary.fromJson(Map<String, dynamic> json) =>
      ParentChildGuidanceSummary(
        reports: _integer(json['laporan']),
        inProgress: _integer(json['diproses']),
        violationPoints: _integer(json['poin_pelanggaran']),
        reductions: _integer(json['pengurangan']),
        balance: _integer(json['saldo']),
      );

  final int reports;
  final int inProgress;
  final int violationPoints;
  final int reductions;
  final int balance;
}

class ParentChildAcademicYear {
  const ParentChildAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory ParentChildAcademicYear.fromJson(Map<String, dynamic> json) =>
      ParentChildAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;

  String get label => active ? '$name · Aktif' : name;
}

class ParentChildGuidanceClass {
  const ParentChildGuidanceClass({
    required this.id,
    required this.name,
    required this.grade,
  });

  factory ParentChildGuidanceClass.fromJson(Map<String, dynamic> json) =>
      ParentChildGuidanceClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
      );

  final int id;
  final String name;
  final int grade;
}

class ParentChildPoint {
  const ParentChildPoint({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.points,
    required this.description,
    this.recordedAt,
    this.source,
    this.reportId,
  });

  factory ParentChildPoint.fromJson(Map<String, dynamic> json) =>
      ParentChildPoint(
        id: _integer(json['id']),
        type: json['jenis'] as String? ?? '',
        typeLabel: json['label_jenis'] as String? ?? '-',
        points: _integer(json['poin']),
        description: json['keterangan'] as String? ?? '-',
        recordedAt: json['tercatat_pada'] as String?,
        source: json['sumber'] as String?,
        reportId: _nullableInteger(json['laporan_id']),
      );

  final int id;
  final String type;
  final String typeLabel;
  final int points;
  final String description;
  final String? recordedAt;
  final String? source;
  final int? reportId;

  bool get isReduction => type == 'pengurangan' || points < 0;
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
