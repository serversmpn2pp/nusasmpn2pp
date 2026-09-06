class InventoryAcquisitionSourcePage {
  const InventoryAcquisitionSourcePage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.query,
    required this.status,
  });

  factory InventoryAcquisitionSourcePage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return InventoryAcquisitionSourcePage(
      items: _list(json['items'], InventoryAcquisitionSource.fromJson),
      summary: InventoryAcquisitionSourceSummary.fromJson(
        _map(json['ringkasan']),
      ),
      access: InventoryAcquisitionSourceAccess.fromJson(
        _map(json['hak_akses']),
      ),
      pagination: InventoryAcquisitionSourcePagination.fromJson(
        _map(json['paginasi']),
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<InventoryAcquisitionSource> items;
  final InventoryAcquisitionSourceSummary summary;
  final InventoryAcquisitionSourceAccess access;
  final InventoryAcquisitionSourcePagination pagination;
  final String query;
  final String status;

  InventoryAcquisitionSourcePage append(InventoryAcquisitionSourcePage next) =>
      InventoryAcquisitionSourcePage(
        items: [...items, ...next.items],
        summary: next.summary,
        access: next.access,
        pagination: next.pagination,
        query: next.query,
        status: next.status,
      );
}

class InventoryAcquisitionSource {
  const InventoryAcquisitionSource({
    required this.id,
    required this.name,
    required this.code,
    required this.active,
    required this.assetUnitsCount,
    this.description,
  });

  factory InventoryAcquisitionSource.fromJson(Map<String, dynamic> json) =>
      InventoryAcquisitionSource(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String? ?? '-',
        description: json['deskripsi'] as String?,
        active: json['aktif'] as bool? ?? false,
        assetUnitsCount: _integer(json['jumlah_unit_aset']),
      );

  final int id;
  final String name;
  final String code;
  final String? description;
  final bool active;
  final int assetUnitsCount;
}

class InventoryAcquisitionSourceSummary {
  const InventoryAcquisitionSourceSummary({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory InventoryAcquisitionSourceSummary.fromJson(
    Map<String, dynamic> json,
  ) => InventoryAcquisitionSourceSummary(
    total: _integer(json['total']),
    active: _integer(json['aktif']),
    inactive: _integer(json['nonaktif']),
  );

  final int total;
  final int active;
  final int inactive;
}

class InventoryAcquisitionSourceAccess {
  const InventoryAcquisitionSourceAccess({required this.canManage});

  factory InventoryAcquisitionSourceAccess.fromJson(
    Map<String, dynamic> json,
  ) => InventoryAcquisitionSourceAccess(
    canManage: json['dapat_kelola'] as bool? ?? false,
  );

  final bool canManage;
}

class InventoryAcquisitionSourcePagination {
  const InventoryAcquisitionSourcePagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory InventoryAcquisitionSourcePagination.fromJson(
    Map<String, dynamic> json,
  ) => InventoryAcquisitionSourcePagination(
    page: _integer(json['halaman']),
    total: _integer(json['total']),
    hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
  );

  final int page;
  final int total;
  final bool hasNextPage;
}

class InventoryAcquisitionSourceFormValue {
  const InventoryAcquisitionSourceFormValue({
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
