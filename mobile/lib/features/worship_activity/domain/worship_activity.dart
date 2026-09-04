class WorshipActivityPage {
  const WorshipActivityPage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.query,
    required this.status,
  });

  factory WorshipActivityPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return WorshipActivityPage(
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) => WorshipActivity.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      summary: WorshipActivitySummary.fromJson(_map(json['ringkasan'])),
      pagination: WorshipActivityPagination.fromJson(_map(json['paginasi'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<WorshipActivity> items;
  final WorshipActivitySummary summary;
  final WorshipActivityPagination pagination;
  final String query;
  final String status;

  WorshipActivityPage append(WorshipActivityPage next) => WorshipActivityPage(
    items: [...items, ...next.items],
    summary: next.summary,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
  );
}

class WorshipActivity {
  const WorshipActivity({
    required this.id,
    required this.code,
    required this.name,
    required this.active,
    required this.scheduleCount,
    required this.activeScheduleCount,
    this.notes,
    this.audience,
    this.maleOnly = false,
  });

  factory WorshipActivity.fromJson(Map<String, dynamic> json) =>
      WorshipActivity(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
        notes: json['keterangan'] as String?,
        audience: json['cakupan_peserta'] as String?,
        maleOnly:
            json['khusus_laki_laki'] as bool? ?? json['kode'] == 'sholat_jumat',
        scheduleCount: _integer(json['jumlah_jadwal']),
        activeScheduleCount: _integer(json['jumlah_jadwal_aktif']),
      );

  final int id;
  final String code;
  final String name;
  final bool active;
  final String? notes;
  final String? audience;
  final bool maleOnly;
  final int scheduleCount;
  final int activeScheduleCount;
}

class WorshipActivitySummary {
  const WorshipActivitySummary({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory WorshipActivitySummary.fromJson(Map<String, dynamic> json) =>
      WorshipActivitySummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class WorshipActivityPagination {
  const WorshipActivityPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory WorshipActivityPagination.fromJson(Map<String, dynamic> json) =>
      WorshipActivityPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class WorshipActivityFormValue {
  const WorshipActivityFormValue({
    required this.code,
    required this.name,
    required this.active,
    this.notes,
  });

  final String code;
  final String name;
  final bool active;
  final String? notes;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value) => value is num ? value.toInt() : 0;
