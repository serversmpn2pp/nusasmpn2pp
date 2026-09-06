class InventoryGoodsPage {
  const InventoryGoodsPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.types,
    required this.categories,
    required this.units,
    required this.locations,
    required this.query,
    required this.status,
    required this.type,
    this.categoryId,
  });

  factory InventoryGoodsPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final options = _map(json['pilihan']);
    return InventoryGoodsPage(
      items: _list(json['items'], InventoryGoods.fromJson),
      summary: InventoryGoodsSummary.fromJson(_map(json['ringkasan'])),
      access: InventoryGoodsAccess.fromJson(_map(json['hak_akses'])),
      pagination: InventoryGoodsPagination.fromJson(_map(json['paginasi'])),
      types: _list(options['jenis_barang'], InventoryGoodsType.fromJson),
      categories: _list(options['kategori'], InventoryGoodsOption.fromJson),
      units: _list(options['satuan'], InventoryGoodsOption.fromJson),
      locations: _list(options['lokasi'], InventoryGoodsOption.fromJson),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      type: filter['jenis_barang'] as String? ?? 'semua',
      categoryId: _nullableInteger(filter['kategori_barang_id']),
    );
  }

  final List<InventoryGoods> items;
  final InventoryGoodsSummary summary;
  final InventoryGoodsAccess access;
  final InventoryGoodsPagination pagination;
  final List<InventoryGoodsType> types;
  final List<InventoryGoodsOption> categories;
  final List<InventoryGoodsOption> units;
  final List<InventoryGoodsOption> locations;
  final String query;
  final String status;
  final String type;
  final int? categoryId;

  InventoryGoodsPage append(InventoryGoodsPage next) => InventoryGoodsPage(
    items: [...items, ...next.items],
    summary: next.summary,
    access: next.access,
    pagination: next.pagination,
    types: next.types,
    categories: next.categories,
    units: next.units,
    locations: next.locations,
    query: next.query,
    status: next.status,
    type: next.type,
    categoryId: next.categoryId,
  );
}

class InventoryGoods {
  const InventoryGoods({
    required this.id,
    required this.code,
    required this.name,
    required this.category,
    required this.unit,
    required this.type,
    required this.typeLabel,
    required this.managementType,
    required this.managementTypeLabel,
    required this.minimumStock,
    required this.stockBalance,
    required this.assetUnitsCount,
    required this.quantitySummary,
    required this.active,
    required this.typeCanChange,
    this.location,
    this.description,
  });

  factory InventoryGoods.fromJson(Map<String, dynamic> json) => InventoryGoods(
    id: _integer(json['id']),
    code: json['kode'] as String? ?? '-',
    name: json['nama'] as String? ?? '-',
    category: InventoryGoodsOption.fromJson(_map(json['kategori'])),
    unit: InventoryGoodsOption.fromJson(_map(json['satuan'])),
    location: json['lokasi_penyimpanan'] is Map
        ? InventoryGoodsOption.fromJson(_map(json['lokasi_penyimpanan']))
        : null,
    type: json['jenis_barang'] as String? ?? 'tidak_habis_pakai',
    typeLabel: json['label_jenis_barang'] as String? ?? '-',
    managementType: json['tipe_pengelolaan'] as String? ?? '-',
    managementTypeLabel: json['label_tipe_pengelolaan'] as String? ?? '-',
    minimumStock: _decimal(json['stok_minimum']),
    stockBalance: _decimal(json['saldo_stok']),
    assetUnitsCount: _integer(json['jumlah_unit_aset']),
    quantitySummary: json['ringkasan_kuantitas'] as String? ?? '-',
    description: json['deskripsi'] as String?,
    active: json['aktif'] as bool? ?? false,
    typeCanChange: json['jenis_dapat_diubah'] as bool? ?? true,
  );

  final int id;
  final String code;
  final String name;
  final InventoryGoodsOption category;
  final InventoryGoodsOption unit;
  final InventoryGoodsOption? location;
  final String type;
  final String typeLabel;
  final String managementType;
  final String managementTypeLabel;
  final double minimumStock;
  final double stockBalance;
  final int assetUnitsCount;
  final String quantitySummary;
  final String? description;
  final bool active;
  final bool typeCanChange;

  bool get isConsumable => type == 'habis_pakai';
}

class InventoryGoodsSummary {
  const InventoryGoodsSummary({
    required this.total,
    required this.active,
    required this.nonConsumable,
    required this.consumable,
  });

  factory InventoryGoodsSummary.fromJson(Map<String, dynamic> json) =>
      InventoryGoodsSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        nonConsumable: _integer(json['tidak_habis_pakai']),
        consumable: _integer(json['habis_pakai']),
      );

  final int total;
  final int active;
  final int nonConsumable;
  final int consumable;
}

class InventoryGoodsAccess {
  const InventoryGoodsAccess({required this.canManage});

  factory InventoryGoodsAccess.fromJson(Map<String, dynamic> json) =>
      InventoryGoodsAccess(canManage: json['dapat_kelola'] as bool? ?? false);

  final bool canManage;
}

class InventoryGoodsPagination {
  const InventoryGoodsPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory InventoryGoodsPagination.fromJson(Map<String, dynamic> json) =>
      InventoryGoodsPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class InventoryGoodsType {
  const InventoryGoodsType({required this.value, required this.label});

  factory InventoryGoodsType.fromJson(Map<String, dynamic> json) =>
      InventoryGoodsType(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String value;
  final String label;
}

class InventoryGoodsOption {
  const InventoryGoodsOption({
    required this.id,
    required this.name,
    required this.code,
    this.active = true,
  });

  factory InventoryGoodsOption.fromJson(Map<String, dynamic> json) =>
      InventoryGoodsOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String? ?? '-',
        active: json['aktif'] as bool? ?? true,
      );

  final int id;
  final String name;
  final String code;
  final bool active;

  String get label => active ? name : '$name · Nonaktif';
}

class InventoryGoodsFormValue {
  const InventoryGoodsFormValue({
    required this.name,
    required this.code,
    required this.categoryId,
    required this.unitId,
    required this.type,
    required this.minimumStock,
    required this.active,
    this.locationId,
    this.description,
  });

  final String name;
  final String? code;
  final int categoryId;
  final int unitId;
  final int? locationId;
  final String type;
  final double minimumStock;
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

int? _nullableInteger(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text),
  _ => null,
};

double _decimal(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? 0,
  _ => 0,
};
