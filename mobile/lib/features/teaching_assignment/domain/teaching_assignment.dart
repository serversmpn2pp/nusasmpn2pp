class TeachingAssignmentPage {
  const TeachingAssignmentPage({
    required this.items,
    required this.counts,
    required this.academicYears,
    required this.pagination,
    required this.query,
    required this.status,
    required this.canManage,
    this.academicYearId,
  });

  factory TeachingAssignmentPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    return TeachingAssignmentPage(
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                TeachingAssignment.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      counts: AssignmentCounts.fromJson(_map(json['ringkasan']) ?? const {}),
      academicYears: (json['tahun_pelajaran'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                AssignmentYearOption.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      pagination: AssignmentPagination.fromJson(
        _map(json['paginasi']) ?? const {},
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      academicYearId: _nullableInteger(filter['tahun_pelajaran_id']),
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<TeachingAssignment> items;
  final AssignmentCounts counts;
  final List<AssignmentYearOption> academicYears;
  final AssignmentPagination pagination;
  final String query;
  final String status;
  final int? academicYearId;
  final bool canManage;

  TeachingAssignmentPage append(TeachingAssignmentPage next) =>
      TeachingAssignmentPage(
        items: [...items, ...next.items],
        counts: next.counts,
        academicYears: next.academicYears,
        pagination: next.pagination,
        query: next.query,
        status: next.status,
        academicYearId: next.academicYearId,
        canManage: next.canManage,
      );
}

class AssignmentCounts {
  const AssignmentCounts({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory AssignmentCounts.fromJson(Map<String, dynamic> json) =>
      AssignmentCounts(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class AssignmentPagination {
  const AssignmentPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory AssignmentPagination.fromJson(Map<String, dynamic> json) =>
      AssignmentPagination(
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

class TeachingAssignment {
  const TeachingAssignment({
    required this.id,
    required this.assignmentType,
    required this.assignmentTypeLabel,
    required this.active,
    this.academicYear,
    this.schoolClass,
    this.subject,
    this.employee,
    this.notes,
  });

  factory TeachingAssignment.fromJson(Map<String, dynamic> json) =>
      TeachingAssignment(
        id: _integer(json['id']),
        academicYear: switch (_map(json['tahun_pelajaran'])) {
          final data? => AssignmentYearOption.fromJson(data),
          _ => null,
        },
        schoolClass: switch (_map(json['kelas'])) {
          final data? => AssignmentClassOption.fromJson(data),
          _ => null,
        },
        subject: switch (_map(json['mata_pelajaran'])) {
          final data? => AssignmentSubjectOption.fromJson(data),
          _ => null,
        },
        employee: switch (_map(json['pegawai'])) {
          final data? => AssignmentEmployeeOption.fromJson(data),
          _ => null,
        },
        assignmentType: json['jenis_penugasan'] as String? ?? 'pengampu',
        assignmentTypeLabel:
            json['jenis_penugasan_label'] as String? ?? 'Pengampu',
        active: json['aktif'] as bool? ?? false,
        notes: json['keterangan'] as String?,
      );

  final int id;
  final AssignmentYearOption? academicYear;
  final AssignmentClassOption? schoolClass;
  final AssignmentSubjectOption? subject;
  final AssignmentEmployeeOption? employee;
  final String assignmentType;
  final String assignmentTypeLabel;
  final bool active;
  final String? notes;
}

class TeachingAssignmentReference {
  const TeachingAssignmentReference({
    required this.academicYears,
    required this.classes,
    required this.employees,
    required this.subjects,
    required this.assignmentTypes,
  });

  factory TeachingAssignmentReference.fromJson(Map<String, dynamic> json) =>
      TeachingAssignmentReference(
        academicYears: _list(
          json['tahun_pelajaran'],
          AssignmentYearOption.fromJson,
        ),
        classes: _list(json['kelas'], AssignmentClassOption.fromJson),
        employees: _list(json['pegawai'], AssignmentEmployeeOption.fromJson),
        subjects: _list(
          json['mata_pelajaran'],
          AssignmentSubjectOption.fromJson,
        ),
        assignmentTypes: _list(
          json['jenis_penugasan'],
          AssignmentTypeOption.fromJson,
        ),
      );

  final List<AssignmentYearOption> academicYears;
  final List<AssignmentClassOption> classes;
  final List<AssignmentEmployeeOption> employees;
  final List<AssignmentSubjectOption> subjects;
  final List<AssignmentTypeOption> assignmentTypes;
}

class AssignmentYearOption {
  const AssignmentYearOption({
    required this.id,
    required this.name,
    required this.active,
  });

  factory AssignmentYearOption.fromJson(Map<String, dynamic> json) =>
      AssignmentYearOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class AssignmentClassOption {
  const AssignmentClassOption({
    required this.id,
    required this.name,
    required this.level,
    this.academicYearId,
    this.academicYearName,
  });

  factory AssignmentClassOption.fromJson(Map<String, dynamic> json) =>
      AssignmentClassOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        level: _integer(json['tingkat']),
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        academicYearName: json['tahun_pelajaran'] as String?,
      );

  final int id;
  final String name;
  final int level;
  final int? academicYearId;
  final String? academicYearName;
}

class AssignmentSubjectOption {
  const AssignmentSubjectOption({
    required this.id,
    required this.name,
    this.code,
    this.group,
    this.availableClassIds = const [],
  });

  factory AssignmentSubjectOption.fromJson(Map<String, dynamic> json) =>
      AssignmentSubjectOption(
        id: _integer(json['id']),
        code: json['kode'] as String?,
        name: json['nama'] as String? ?? '-',
        group: json['kelompok'] as String?,
        availableClassIds:
            (json['kelas_ids_tersedia'] as List<dynamic>? ?? const [])
                .whereType<num>()
                .map((value) => value.toInt())
                .toList(growable: false),
      );

  final int id;
  final String? code;
  final String name;
  final String? group;
  final List<int> availableClassIds;
}

class AssignmentEmployeeOption {
  const AssignmentEmployeeOption({
    required this.id,
    required this.name,
    this.nip,
    this.position,
  });

  factory AssignmentEmployeeOption.fromJson(Map<String, dynamic> json) =>
      AssignmentEmployeeOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        position: json['jabatan'] as String?,
      );

  final int id;
  final String name;
  final String? nip;
  final String? position;
}

class AssignmentTypeOption {
  const AssignmentTypeOption({required this.code, required this.label});

  factory AssignmentTypeOption.fromJson(Map<String, dynamic> json) =>
      AssignmentTypeOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String code;
  final String label;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic>? _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
