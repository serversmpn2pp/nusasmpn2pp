class InventoryCategoryPage {
  const InventoryCategoryPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.query,
    required this.status,
  });

  factory InventoryCategoryPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return InventoryCategoryPage(
      items: _list(json['items'], InventoryCategory.fromJson),
      summary: InventoryCategorySummary.fromJson(_map(json['ringkasan'])),
      access: InventoryCategoryAccess.fromJson(_map(json['hak_akses'])),
      pagination: InventoryCategoryPagination.fromJson(_map(json['paginasi'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<InventoryCategory> items;
  final InventoryCategorySummary summary;
  final InventoryCategoryAccess access;
  final InventoryCategoryPagination pagination;
  final String query;
  final String status;

  InventoryCategoryPage append(InventoryCategoryPage next) =>
      InventoryCategoryPage(
        items: [...items, ...next.items],
        summary: next.summary,
        access: next.access,
        pagination: next.pagination,
        query: next.query,
        status: next.status,
      );
}

class InventoryCategory {
  const InventoryCategory({
    required this.id,
    required this.name,
    required this.code,
    required this.active,
    required this.goodsCount,
    this.description,
  });

  factory InventoryCategory.fromJson(Map<String, dynamic> json) =>
      InventoryCategory(
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

class InventoryCategorySummary {
  const InventoryCategorySummary({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory InventoryCategorySummary.fromJson(Map<String, dynamic> json) =>
      InventoryCategorySummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class InventoryCategoryAccess {
  const InventoryCategoryAccess({required this.canManage});

  factory InventoryCategoryAccess.fromJson(Map<String, dynamic> json) =>
      InventoryCategoryAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
      );

  final bool canManage;
}

class InventoryCategoryPagination {
  const InventoryCategoryPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory InventoryCategoryPagination.fromJson(Map<String, dynamic> json) =>
      InventoryCategoryPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class InventoryCategoryFormValue {
  const InventoryCategoryFormValue({
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
