class GradeComponentPage {
  const GradeComponentPage({
    required this.items,
    required this.summary,
    required this.academicYears,
    required this.assignments,
    required this.filter,
    required this.pagination,
    required this.canManage,
  });

  factory GradeComponentPage.fromJson(Map<String, dynamic> json) =>
      GradeComponentPage(
        items: _list(json['items'], GradeComponent.fromJson),
        summary: GradeComponentSummary.fromJson(_map(json['ringkasan'])),
        academicYears: _list(
          json['tahun_pelajaran'],
          ComponentAcademicYear.fromJson,
        ),
        assignments: _list(
          json['guru_mata_pelajaran'],
          GradeComponentAssignment.fromJson,
        ),
        filter: GradeComponentFilter.fromJson(_map(json['filter'])),
        pagination: ComponentPagination.fromJson(_map(json['paginasi'])),
        canManage: _map(json['hak_akses'])['dapat_kelola'] as bool? ?? false,
      );

  final List<GradeComponent> items;
  final GradeComponentSummary summary;
  final List<ComponentAcademicYear> academicYears;
  final List<GradeComponentAssignment> assignments;
  final GradeComponentFilter filter;
  final ComponentPagination pagination;
  final bool canManage;

  GradeComponentPage append(GradeComponentPage next) => GradeComponentPage(
    items: [...items, ...next.items],
    summary: next.summary,
    academicYears: next.academicYears,
    assignments: next.assignments,
    filter: next.filter,
    pagination: next.pagination,
    canManage: next.canManage,
  );
}

class GradeComponent {
  const GradeComponent({
    required this.id,
    required this.assignment,
    required this.semester,
    required this.semesterLabel,
    required this.type,
    required this.typeLabel,
    required this.name,
    required this.order,
    required this.active,
    this.assessmentDate,
    this.assessmentDateLabel,
    this.notes,
  });

  factory GradeComponent.fromJson(Map<String, dynamic> json) => GradeComponent(
    id: _integer(json['id']),
    assignment: GradeComponentAssignment.fromJson(
      _map(json['guru_mata_pelajaran']),
    ),
    semester: json['semester'] as String? ?? 'ganjil',
    semesterLabel: json['semester_label'] as String? ?? '-',
    type: json['jenis_komponen'] as String? ?? 'formatif',
    typeLabel: json['jenis_label'] as String? ?? '-',
    name: json['nama'] as String? ?? '-',
    assessmentDate: json['tanggal_penilaian'] as String?,
    assessmentDateLabel: json['tanggal_label'] as String?,
    order: _integer(json['urutan']),
    active: json['aktif'] as bool? ?? false,
    notes: json['keterangan'] as String?,
  );

  final int id;
  final GradeComponentAssignment assignment;
  final String semester;
  final String semesterLabel;
  final String type;
  final String typeLabel;
  final String name;
  final String? assessmentDate;
  final String? assessmentDateLabel;
  final int order;
  final bool active;
  final String? notes;
}

class GradeComponentAssignment {
  const GradeComponentAssignment({
    required this.id,
    required this.academicYear,
    required this.schoolClass,
    required this.subject,
    required this.employee,
  });

  factory GradeComponentAssignment.fromJson(Map<String, dynamic> json) =>
      GradeComponentAssignment(
        id: _integer(json['id']),
        academicYear: ComponentAcademicYear.fromJson(
          _map(json['tahun_pelajaran']),
        ),
        schoolClass: ComponentSchoolClass.fromJson(_map(json['kelas'])),
        subject: ComponentSubject.fromJson(_map(json['mata_pelajaran'])),
        employee: ComponentEmployee.fromJson(_map(json['pegawai'])),
      );

  final int id;
  final ComponentAcademicYear academicYear;
  final ComponentSchoolClass schoolClass;
  final ComponentSubject subject;
  final ComponentEmployee employee;

  String get label =>
      '${academicYear.name} · ${schoolClass.name} · ${subject.name} · ${employee.name}';
}

class ComponentAcademicYear {
  const ComponentAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory ComponentAcademicYear.fromJson(Map<String, dynamic> json) =>
      ComponentAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class ComponentSchoolClass {
  const ComponentSchoolClass({
    required this.id,
    required this.name,
    required this.grade,
  });

  factory ComponentSchoolClass.fromJson(Map<String, dynamic> json) =>
      ComponentSchoolClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
      );

  final int id;
  final String name;
  final int grade;
}

class ComponentSubject {
  const ComponentSubject({
    required this.id,
    required this.code,
    required this.name,
  });

  factory ComponentSubject.fromJson(Map<String, dynamic> json) =>
      ComponentSubject(
        id: _integer(json['id']),
        code: json['kode'] as String?,
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String? code;
  final String name;
}

class ComponentEmployee {
  const ComponentEmployee({required this.id, required this.name, this.nip});

  factory ComponentEmployee.fromJson(Map<String, dynamic> json) =>
      ComponentEmployee(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
      );

  final int id;
  final String name;
  final String? nip;
}

class GradeComponentSummary {
  const GradeComponentSummary({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory GradeComponentSummary.fromJson(Map<String, dynamic> json) =>
      GradeComponentSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class GradeComponentFilter {
  const GradeComponentFilter({
    required this.search,
    required this.semester,
    required this.type,
    required this.status,
    this.academicYearId,
  });

  factory GradeComponentFilter.fromJson(Map<String, dynamic> json) =>
      GradeComponentFilter(
        search: json['cari'] as String? ?? '',
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        semester: json['semester'] as String? ?? 'semua',
        type: json['jenis_komponen'] as String? ?? 'semua',
        status: json['status'] as String? ?? 'semua',
      );

  final String search;
  final int? academicYearId;
  final String semester;
  final String type;
  final String status;
}

class ComponentPagination {
  const ComponentPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory ComponentPagination.fromJson(Map<String, dynamic> json) =>
      ComponentPagination(
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

class GradeComponentFormValue {
  const GradeComponentFormValue({
    required this.assignmentId,
    required this.semester,
    required this.type,
    required this.name,
    required this.order,
    required this.active,
    this.assessmentDate,
    this.notes,
  });

  final int assignmentId;
  final String semester;
  final String type;
  final String name;
  final String? assessmentDate;
  final int order;
  final bool active;
  final String? notes;

  Map<String, dynamic> toJson() => {
    'guru_mata_pelajaran_id': assignmentId,
    'semester': semester,
    'jenis_komponen': type,
    'nama': name.trim(),
    'tanggal_penilaian': assessmentDate,
    'urutan': order,
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
