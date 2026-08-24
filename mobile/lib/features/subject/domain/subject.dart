class SubjectPage {
  const SubjectPage({
    required this.items,
    required this.counts,
    required this.academicYears,
    required this.pagination,
    required this.query,
    required this.status,
    required this.level,
    required this.academicYearId,
    required this.canManage,
  });

  factory SubjectPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    return SubjectPage(
      items: _list(json['items'], Subject.fromJson),
      counts: SubjectCounts.fromJson(_map(json['ringkasan']) ?? const {}),
      academicYears: _list(
        json['tahun_pelajaran'],
        SubjectAcademicYear.fromJson,
      ),
      pagination: SubjectPagination.fromJson(
        _map(json['paginasi']) ?? const {},
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      level: filter['tingkat'] as String? ?? 'semua',
      academicYearId: _integer(filter['tahun_pelajaran_id']),
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<Subject> items;
  final SubjectCounts counts;
  final List<SubjectAcademicYear> academicYears;
  final SubjectPagination pagination;
  final String query;
  final String status;
  final String level;
  final int academicYearId;
  final bool canManage;

  SubjectPage append(SubjectPage next) => SubjectPage(
    items: [...items, ...next.items],
    counts: next.counts,
    academicYears: next.academicYears,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
    level: next.level,
    academicYearId: next.academicYearId,
    canManage: next.canManage,
  );
}

class SubjectCounts {
  const SubjectCounts({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory SubjectCounts.fromJson(Map<String, dynamic> json) => SubjectCounts(
    total: _integer(json['total']),
    active: _integer(json['aktif']),
    inactive: _integer(json['nonaktif']),
  );

  final int total;
  final int active;
  final int inactive;
}

class SubjectPagination {
  const SubjectPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory SubjectPagination.fromJson(Map<String, dynamic> json) =>
      SubjectPagination(
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

class Subject {
  const Subject({
    required this.id,
    required this.name,
    required this.assessmentType,
    required this.assessmentTypeLabel,
    required this.order,
    required this.active,
    required this.settings,
    this.group,
    this.notes,
  });

  factory Subject.fromJson(Map<String, dynamic> json) => Subject(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    group: json['kelompok'] as String?,
    assessmentType: json['jenis_penilaian'] as String? ?? 'angka',
    assessmentTypeLabel:
        json['jenis_penilaian_label'] as String? ?? 'Angka (0-100)',
    order: _integer(json['urutan']),
    active: json['aktif'] as bool? ?? false,
    notes: json['keterangan'] as String?,
    settings: _list(json['pengaturan'], SubjectLevelSetting.fromJson),
  );

  final int id;
  final String name;
  final String? group;
  final String assessmentType;
  final String assessmentTypeLabel;
  final int order;
  final bool active;
  final String? notes;
  final List<SubjectLevelSetting> settings;

  bool get usesPredicate => assessmentType == 'predikat';

  SubjectLevelSetting? settingFor(int level) =>
      settings.where((item) => item.level == level).firstOrNull;
}

class SubjectLevelSetting {
  const SubjectLevelSetting({
    required this.level,
    required this.code,
    required this.active,
    this.id,
    this.minimumScore,
  });

  factory SubjectLevelSetting.fromJson(Map<String, dynamic> json) =>
      SubjectLevelSetting(
        id: _nullableInteger(json['id']),
        level: _integer(json['tingkat']),
        code: json['kode'] as String? ?? '',
        minimumScore: _nullableInteger(json['kkm']),
        active: json['aktif'] as bool? ?? false,
      );

  final int? id;
  final int level;
  final String code;
  final int? minimumScore;
  final bool active;
}

class SubjectReference {
  const SubjectReference({
    required this.academicYears,
    required this.groups,
    required this.levels,
  });

  factory SubjectReference.fromJson(Map<String, dynamic> json) =>
      SubjectReference(
        academicYears: _list(
          json['tahun_pelajaran'],
          SubjectAcademicYear.fromJson,
        ),
        groups: _list(json['kelompok'], SubjectGroup.fromJson),
        levels: _list(json['tingkat'], SubjectLevel.fromJson),
      );

  final List<SubjectAcademicYear> academicYears;
  final List<SubjectGroup> groups;
  final List<SubjectLevel> levels;
}

class SubjectAcademicYear {
  const SubjectAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory SubjectAcademicYear.fromJson(Map<String, dynamic> json) =>
      SubjectAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class SubjectGroup {
  const SubjectGroup({required this.name, required this.usesPredicate});

  factory SubjectGroup.fromJson(Map<String, dynamic> json) => SubjectGroup(
    name: json['nama'] as String? ?? '-',
    usesPredicate: json['menggunakan_predikat'] as bool? ?? false,
  );

  final String name;
  final bool usesPredicate;
}

class SubjectLevel {
  const SubjectLevel({required this.value, required this.label});

  factory SubjectLevel.fromJson(Map<String, dynamic> json) => SubjectLevel(
    value: _integer(json['nilai']),
    label: json['label'] as String? ?? '-',
  );

  final int value;
  final String label;
}

class SubjectFormValue {
  const SubjectFormValue({
    required this.academicYearId,
    required this.name,
    required this.order,
    required this.active,
    required this.settings,
    this.group,
    this.notes,
  });

  final int academicYearId;
  final String name;
  final String? group;
  final int order;
  final bool active;
  final String? notes;
  final List<SubjectLevelSetting> settings;
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
