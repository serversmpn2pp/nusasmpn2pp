class InventoryUnitPage {
  const InventoryUnitPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.query,
    required this.status,
  });

  factory InventoryUnitPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return InventoryUnitPage(
      items: _list(json['items'], InventoryUnit.fromJson),
      summary: InventoryUnitSummary.fromJson(_map(json['ringkasan'])),
      access: InventoryUnitAccess.fromJson(_map(json['hak_akses'])),
      pagination: InventoryUnitPagination.fromJson(_map(json['paginasi'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<InventoryUnit> items;
  final InventoryUnitSummary summary;
  final InventoryUnitAccess access;
  final InventoryUnitPagination pagination;
  final String query;
  final String status;

  InventoryUnitPage append(InventoryUnitPage next) => InventoryUnitPage(
    items: [...items, ...next.items],
    summary: next.summary,
    access: next.access,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
  );
}

class InventoryUnit {
  const InventoryUnit({
    required this.id,
    required this.name,
    required this.code,
    required this.active,
    required this.goodsCount,
    this.description,
  });

  factory InventoryUnit.fromJson(Map<String, dynamic> json) => InventoryUnit(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    code: json['kode'] as String? ?? '-',
    description: json['deskripsi'] as String?,
    active: json['aktif'] as bool? ?? false,
    goodsCount: _integer(json['jumlah_barang']),
  );

  final int id;
  final String name;
  final String code;
  final String? description;
  final bool active;
  final int goodsCount;
}

class InventoryUnitSummary {
  const InventoryUnitSummary({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory InventoryUnitSummary.fromJson(Map<String, dynamic> json) =>
      InventoryUnitSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class InventoryUnitAccess {
  const InventoryUnitAccess({required this.canManage});

  factory InventoryUnitAccess.fromJson(Map<String, dynamic> json) =>
      InventoryUnitAccess(canManage: json['dapat_kelola'] as bool? ?? false);

  final bool canManage;
}

class InventoryUnitPagination {
  const InventoryUnitPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory InventoryUnitPagination.fromJson(Map<String, dynamic> json) =>
      InventoryUnitPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class InventoryUnitFormValue {
  const InventoryUnitFormValue({
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
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => convert(Map<String, dynamic>.from(item)))
          .toList(growable: false)
    : <T>[];

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};
