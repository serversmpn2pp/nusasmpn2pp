class AcademicYearPage {
  const AcademicYearPage({
    required this.items,
    required this.counts,
    required this.pagination,
    required this.query,
    required this.status,
    required this.canManage,
    this.activeYear,
  });

  factory AcademicYearPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    return AcademicYearPage(
      items: _list(json['items'], AcademicYearItem.fromJson),
      counts: AcademicYearCounts.fromJson(_map(json['ringkasan']) ?? const {}),
      activeYear: switch (_map(json['tahun_aktif'])) {
        final data? => AcademicYearItem.fromJson(data),
        _ => null,
      },
      pagination: AcademicYearPagination.fromJson(
        _map(json['paginasi']) ?? const {},
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<AcademicYearItem> items;
  final AcademicYearCounts counts;
  final AcademicYearItem? activeYear;
  final AcademicYearPagination pagination;
  final String query;
  final String status;
  final bool canManage;

  AcademicYearPage append(AcademicYearPage next) => AcademicYearPage(
    items: [...items, ...next.items],
    counts: next.counts,
    activeYear: next.activeYear,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
    canManage: next.canManage,
  );
}

class AcademicYearCounts {
  const AcademicYearCounts({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory AcademicYearCounts.fromJson(Map<String, dynamic> json) =>
      AcademicYearCounts(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class AcademicYearPagination {
  const AcademicYearPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory AcademicYearPagination.fromJson(Map<String, dynamic> json) =>
      AcademicYearPagination(
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

class AcademicYearItem {
  const AcademicYearItem({
    required this.id,
    required this.name,
    required this.active,
    required this.classCount,
    this.startDate,
    this.endDate,
    this.notes,
  });

  factory AcademicYearItem.fromJson(Map<String, dynamic> json) =>
      AcademicYearItem(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        startDate: _date(json['tanggal_mulai']),
        endDate: _date(json['tanggal_selesai']),
        active: json['aktif'] as bool? ?? false,
        notes: json['keterangan'] as String?,
        classCount: _integer(json['jumlah_kelas']),
      );

  final int id;
  final String name;
  final DateTime? startDate;
  final DateTime? endDate;
  final bool active;
  final String? notes;
  final int classCount;
}

class AcademicYearFormValue {
  const AcademicYearFormValue({
    required this.name,
    required this.active,
    this.startDate,
    this.endDate,
    this.notes,
  });

  final String name;
  final DateTime? startDate;
  final DateTime? endDate;
  final bool active;
  final String? notes;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic>? _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

int _integer(Object? value) => value is num ? value.toInt() : 0;

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value) : null;
