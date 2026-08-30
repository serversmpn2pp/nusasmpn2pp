class StudentGuidanceCategoryPage {
  const StudentGuidanceCategoryPage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.access,
    required this.query,
    required this.status,
  });

  factory StudentGuidanceCategoryPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return StudentGuidanceCategoryPage(
      items: _list(json['items'], StudentGuidanceCategory.fromJson),
      summary: StudentGuidanceCategorySummary.fromJson(_map(json['ringkasan'])),
      pagination: StudentGuidanceCategoryPagination.fromJson(
        _map(json['paginasi']),
      ),
      access: StudentGuidanceCategoryAccess.fromJson(_map(json['hak_akses'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<StudentGuidanceCategory> items;
  final StudentGuidanceCategorySummary summary;
  final StudentGuidanceCategoryPagination pagination;
  final StudentGuidanceCategoryAccess access;
  final String query;
  final String status;

  StudentGuidanceCategoryPage append(StudentGuidanceCategoryPage next) =>
      StudentGuidanceCategoryPage(
        items: [...items, ...next.items],
        summary: next.summary,
        pagination: next.pagination,
        access: next.access,
        query: next.query,
        status: next.status,
      );
}

class StudentGuidanceCategory {
  const StudentGuidanceCategory({
    required this.id,
    required this.name,
    required this.code,
    required this.active,
    required this.reportCount,
    required this.violationTypeCount,
    this.description,
    this.createdAt,
    this.updatedAt,
  });

  factory StudentGuidanceCategory.fromJson(Map<String, dynamic> json) =>
      StudentGuidanceCategory(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String? ?? '-',
        description: json['deskripsi'] as String?,
        active: json['aktif'] as bool? ?? false,
        reportCount: _integer(json['jumlah_laporan']),
        violationTypeCount: _integer(json['jumlah_jenis_pelanggaran']),
        createdAt: DateTime.tryParse(json['dibuat_pada'] as String? ?? ''),
        updatedAt: DateTime.tryParse(json['diperbarui_pada'] as String? ?? ''),
      );

  final int id;
  final String name;
  final String code;
  final String? description;
  final bool active;
  final int reportCount;
  final int violationTypeCount;
  final DateTime? createdAt;
  final DateTime? updatedAt;
}

class StudentGuidanceCategorySummary {
  const StudentGuidanceCategorySummary({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory StudentGuidanceCategorySummary.fromJson(Map<String, dynamic> json) =>
      StudentGuidanceCategorySummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class StudentGuidanceCategoryPagination {
  const StudentGuidanceCategoryPagination({
    required this.page,
    required this.lastPage,
    required this.total,
    required this.hasNextPage,
  });

  factory StudentGuidanceCategoryPagination.fromJson(
    Map<String, dynamic> json,
  ) => StudentGuidanceCategoryPagination(
    page: _integer(json['halaman']),
    lastPage: _integer(json['halaman_terakhir']),
    total: _integer(json['total']),
    hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
  );

  final int page;
  final int lastPage;
  final int total;
  final bool hasNextPage;
}

class StudentGuidanceCategoryAccess {
  const StudentGuidanceCategoryAccess({required this.canManage});

  factory StudentGuidanceCategoryAccess.fromJson(Map<String, dynamic> json) =>
      StudentGuidanceCategoryAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
      );

  final bool canManage;
}

class StudentGuidanceCategoryFormValue {
  const StudentGuidanceCategoryFormValue({
    required this.name,
    required this.code,
    required this.active,
    this.description,
  });

  final String name;
  final String code;
  final String? description;
  final bool active;
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
