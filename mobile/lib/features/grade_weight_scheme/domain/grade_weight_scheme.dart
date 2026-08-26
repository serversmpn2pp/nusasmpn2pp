class GradeWeightSchemePage {
  const GradeWeightSchemePage({
    required this.items,
    required this.summary,
    required this.academicYears,
    required this.filter,
    required this.pagination,
    required this.canManage,
  });

  factory GradeWeightSchemePage.fromJson(Map<String, dynamic> json) =>
      GradeWeightSchemePage(
        items: _list(json['items'], GradeWeightScheme.fromJson),
        summary: GradeWeightSchemeSummary.fromJson(_map(json['ringkasan'])),
        academicYears: _list(
          json['tahun_pelajaran'],
          SchemeAcademicYear.fromJson,
        ),
        filter: GradeWeightSchemeFilter.fromJson(_map(json['filter'])),
        pagination: SchemePagination.fromJson(_map(json['paginasi'])),
        canManage: _map(json['hak_akses'])['dapat_kelola'] as bool? ?? false,
      );

  final List<GradeWeightScheme> items;
  final GradeWeightSchemeSummary summary;
  final List<SchemeAcademicYear> academicYears;
  final GradeWeightSchemeFilter filter;
  final SchemePagination pagination;
  final bool canManage;

  GradeWeightSchemePage append(GradeWeightSchemePage next) =>
      GradeWeightSchemePage(
        items: [...items, ...next.items],
        summary: next.summary,
        academicYears: next.academicYears,
        filter: next.filter,
        pagination: next.pagination,
        canManage: next.canManage,
      );
}

class GradeWeightScheme {
  const GradeWeightScheme({
    required this.id,
    required this.academicYear,
    required this.semester,
    required this.semesterLabel,
    required this.gradeLabel,
    required this.formativeWeight,
    required this.summativeWeight,
    required this.midtermWeight,
    required this.finalWeight,
    required this.finalLabel,
    required this.totalWeight,
    required this.active,
    this.grade,
    this.notes,
  });

  factory GradeWeightScheme.fromJson(Map<String, dynamic> json) =>
      GradeWeightScheme(
        id: _integer(json['id']),
        academicYear: SchemeAcademicYear.fromJson(
          _map(json['tahun_pelajaran']),
        ),
        semester: json['semester'] as String? ?? 'ganjil',
        semesterLabel: json['semester_label'] as String? ?? '-',
        grade: _nullableInteger(json['tingkat']),
        gradeLabel: json['tingkat_label'] as String? ?? 'Semua tingkat',
        formativeWeight: _integer(json['bobot_formatif']),
        summativeWeight: _integer(json['bobot_sumatif']),
        midtermWeight: _integer(json['bobot_sts']),
        finalWeight: _integer(json['bobot_sas_saj']),
        finalLabel: json['label_nilai_akhir'] as String? ?? 'SAS/SAJ',
        totalWeight: _integer(json['total_bobot']),
        active: json['aktif'] as bool? ?? false,
        notes: json['keterangan'] as String?,
      );

  final int id;
  final SchemeAcademicYear academicYear;
  final String semester;
  final String semesterLabel;
  final int? grade;
  final String gradeLabel;
  final int formativeWeight;
  final int summativeWeight;
  final int midtermWeight;
  final int finalWeight;
  final String finalLabel;
  final int totalWeight;
  final bool active;
  final String? notes;
}

class SchemeAcademicYear {
  const SchemeAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory SchemeAcademicYear.fromJson(Map<String, dynamic> json) =>
      SchemeAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class GradeWeightSchemeSummary {
  const GradeWeightSchemeSummary({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory GradeWeightSchemeSummary.fromJson(Map<String, dynamic> json) =>
      GradeWeightSchemeSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class GradeWeightSchemeFilter {
  const GradeWeightSchemeFilter({
    required this.semester,
    required this.grade,
    required this.status,
    this.academicYearId,
  });

  factory GradeWeightSchemeFilter.fromJson(Map<String, dynamic> json) =>
      GradeWeightSchemeFilter(
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        semester: json['semester'] as String? ?? 'semua',
        grade: json['tingkat'] as String? ?? 'semua',
        status: json['status'] as String? ?? 'semua',
      );

  final int? academicYearId;
  final String semester;
  final String grade;
  final String status;
}

class SchemePagination {
  const SchemePagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory SchemePagination.fromJson(Map<String, dynamic> json) =>
      SchemePagination(
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

class GradeWeightSchemeFormValue {
  const GradeWeightSchemeFormValue({
    required this.academicYearId,
    required this.semester,
    required this.grade,
    required this.formativeWeight,
    required this.summativeWeight,
    required this.midtermWeight,
    required this.finalWeight,
    required this.active,
    this.notes,
  });

  final int academicYearId;
  final String semester;
  final int? grade;
  final int formativeWeight;
  final int summativeWeight;
  final int midtermWeight;
  final int finalWeight;
  final bool active;
  final String? notes;

  int get total =>
      formativeWeight + summativeWeight + midtermWeight + finalWeight;

  Map<String, dynamic> toJson() => {
    'tahun_pelajaran_id': academicYearId,
    'semester': semester,
    'tingkat': grade,
    'bobot_formatif': formativeWeight,
    'bobot_sumatif': summativeWeight,
    'bobot_sts': midtermWeight,
    'bobot_sas_saj': finalWeight,
    'aktif': active,
    'keterangan': notes?.trim().isEmpty == true ? null : notes?.trim(),
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
