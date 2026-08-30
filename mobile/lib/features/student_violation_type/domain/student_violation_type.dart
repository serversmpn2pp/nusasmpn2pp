class StudentViolationTypePage {
  const StudentViolationTypePage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.access,
    required this.references,
    required this.query,
    required this.status,
    required this.level,
    this.categoryId,
  });

  factory StudentViolationTypePage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return StudentViolationTypePage(
      items: _list(json['items'], StudentViolationType.fromJson),
      summary: StudentViolationTypeSummary.fromJson(_map(json['ringkasan'])),
      pagination: StudentViolationTypePagination.fromJson(
        _map(json['paginasi']),
      ),
      access: StudentViolationTypeAccess.fromJson(_map(json['hak_akses'])),
      references: StudentViolationTypeReferences.fromJson(
        _map(json['referensi']),
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      level: filter['tingkat'] as String? ?? 'semua',
      categoryId: _nullableInteger(filter['kategori_id']),
    );
  }

  final List<StudentViolationType> items;
  final StudentViolationTypeSummary summary;
  final StudentViolationTypePagination pagination;
  final StudentViolationTypeAccess access;
  final StudentViolationTypeReferences references;
  final String query;
  final String status;
  final String level;
  final int? categoryId;

  StudentViolationTypePage append(StudentViolationTypePage next) =>
      StudentViolationTypePage(
        items: [...items, ...next.items],
        summary: next.summary,
        pagination: next.pagination,
        access: next.access,
        references: next.references,
        query: next.query,
        status: next.status,
        level: next.level,
        categoryId: next.categoryId,
      );
}

class StudentViolationType {
  const StudentViolationType({
    required this.id,
    required this.code,
    required this.name,
    required this.level,
    required this.levelLabel,
    required this.points,
    required this.order,
    required this.active,
    required this.usageCount,
    this.category,
  });

  factory StudentViolationType.fromJson(Map<String, dynamic> json) =>
      StudentViolationType(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        level: json['tingkat'] as String? ?? 'ringan',
        levelLabel: json['tingkat_label'] as String? ?? '-',
        points: _integer(json['poin']),
        order: _integer(json['urutan']),
        active: json['aktif'] as bool? ?? false,
        usageCount: _integer(json['jumlah_pemakaian']),
        category: json['kategori'] is Map
            ? StudentViolationCategory.fromJson(_map(json['kategori']))
            : null,
      );

  final int id;
  final String code;
  final String name;
  final String level;
  final String levelLabel;
  final int points;
  final int order;
  final bool active;
  final int usageCount;
  final StudentViolationCategory? category;
}

class StudentViolationCategory {
  const StudentViolationCategory({
    required this.id,
    required this.name,
    required this.code,
    required this.active,
  });

  factory StudentViolationCategory.fromJson(Map<String, dynamic> json) =>
      StudentViolationCategory(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String code;
  final bool active;
}

class StudentViolationLevel {
  const StudentViolationLevel({required this.code, required this.label});

  factory StudentViolationLevel.fromJson(Map<String, dynamic> json) =>
      StudentViolationLevel(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '',
      );

  final String code;
  final String label;
}

class StudentViolationTypeReferences {
  const StudentViolationTypeReferences({
    required this.levels,
    required this.categories,
  });

  factory StudentViolationTypeReferences.fromJson(Map<String, dynamic> json) =>
      StudentViolationTypeReferences(
        levels: _list(json['tingkat'], StudentViolationLevel.fromJson),
        categories: _list(json['kategori'], StudentViolationCategory.fromJson),
      );

  final List<StudentViolationLevel> levels;
  final List<StudentViolationCategory> categories;
}

class StudentViolationTypeSummary {
  const StudentViolationTypeSummary({
    required this.total,
    required this.active,
    required this.inactive,
    required this.byLevel,
  });

  factory StudentViolationTypeSummary.fromJson(Map<String, dynamic> json) =>
      StudentViolationTypeSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
        byLevel: _map(json['per_tingkat'])
            .map((key, value) => MapEntry(key, _integer(value))),
      );

  final int total;
  final int active;
  final int inactive;
  final Map<String, int> byLevel;
}

class StudentViolationTypePagination {
  const StudentViolationTypePagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory StudentViolationTypePagination.fromJson(Map<String, dynamic> json) =>
      StudentViolationTypePagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class StudentViolationTypeAccess {
  const StudentViolationTypeAccess({required this.canManage});

  factory StudentViolationTypeAccess.fromJson(Map<String, dynamic> json) =>
      StudentViolationTypeAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
      );

  final bool canManage;
}

class StudentViolationTypeFormValue {
  const StudentViolationTypeFormValue({
    required this.code,
    required this.name,
    required this.level,
    required this.points,
    required this.order,
    required this.active,
    this.categoryId,
  });

  final String code;
  final String name;
  final String level;
  final int points;
  final int order;
  final bool active;
  final int? categoryId;
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
