class GoodsCatalogPage {
  const GoodsCatalogPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.categories,
    required this.types,
    required this.availabilityOptions,
    required this.filter,
  });

  factory GoodsCatalogPage.fromJson(Map<String, dynamic> json) {
    final options = _map(json['pilihan']);
    return GoodsCatalogPage(
      items: _list(json['items'], GoodsCatalogItem.fromJson),
      summary: GoodsCatalogSummary.fromJson(_map(json['ringkasan'])),
      access: GoodsCatalogAccess.fromJson(_map(json['hak_akses'])),
      pagination: GoodsCatalogPagination.fromJson(_map(json['paginasi'])),
      categories: _list(options['kategori'], GoodsCatalogIdOption.fromJson),
      types: _list(options['jenis_barang'], GoodsCatalogValueOption.fromJson),
      availabilityOptions: _list(
        options['ketersediaan'],
        GoodsCatalogValueOption.fromJson,
      ),
      filter: GoodsCatalogFilter.fromJson(_map(json['filter'])),
    );
  }

  final List<GoodsCatalogItem> items;
  final GoodsCatalogSummary summary;
  final GoodsCatalogAccess access;
  final GoodsCatalogPagination pagination;
  final List<GoodsCatalogIdOption> categories;
  final List<GoodsCatalogValueOption> types;
  final List<GoodsCatalogValueOption> availabilityOptions;
  final GoodsCatalogFilter filter;

  GoodsCatalogPage append(GoodsCatalogPage next) => GoodsCatalogPage(
    items: [...items, ...next.items],
    summary: next.summary,
    access: next.access,
    pagination: next.pagination,
    categories: next.categories,
    types: next.types,
    availabilityOptions: next.availabilityOptions,
    filter: next.filter,
  );
}

class GoodsCatalogDetail {
  const GoodsCatalogDetail({required this.item, required this.access});

  factory GoodsCatalogDetail.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogDetail(
        item: GoodsCatalogItem.fromJson(_map(json['barang'])),
        access: GoodsCatalogAccess.fromJson(_map(json['hak_akses'])),
      );

  final GoodsCatalogItem item;
  final GoodsCatalogAccess access;
}

class GoodsCatalogItem {
  const GoodsCatalogItem({
    required this.id,
    required this.code,
    required this.name,
    required this.category,
    required this.type,
    required this.typeLabel,
    required this.managementType,
    required this.managementTypeLabel,
    required this.serviceType,
    required this.availableQuantity,
    required this.unitCount,
    required this.borrowedCount,
    required this.unit,
    required this.available,
    required this.locations,
    required this.units,
    required this.activeBorrowers,
    this.description,
  });

  factory GoodsCatalogItem.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogItem(
        id: _int(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        category: json['kategori'] as String? ?? '-',
        type: json['jenis_barang'] as String? ?? '-',
        typeLabel: json['jenis_barang_label'] as String? ?? '-',
        managementType: json['tipe_pengelolaan'] as String? ?? '-',
        managementTypeLabel: json['tipe_pengelolaan_label'] as String? ?? '-',
        serviceType: json['jenis_layanan'] as String? ?? '-',
        availableQuantity: _double(json['jumlah_tersedia']),
        unitCount: _int(json['jumlah_unit']),
        borrowedCount: _int(json['jumlah_dipinjam']),
        unit: json['satuan'] as String? ?? 'unit',
        available: json['tersedia'] as bool? ?? false,
        description: json['deskripsi'] as String?,
        locations: _list(json['lokasi'], GoodsCatalogLocation.fromJson),
        units: _list(json['unit'], GoodsCatalogUnit.fromJson),
        activeBorrowers: _list(
          json['peminjam_aktif'],
          GoodsCatalogBorrower.fromJson,
        ),
      );

  final int id;
  final String code;
  final String name;
  final String category;
  final String type;
  final String typeLabel;
  final String managementType;
  final String managementTypeLabel;
  final String serviceType;
  final double availableQuantity;
  final int unitCount;
  final int borrowedCount;
  final String unit;
  final bool available;
  final String? description;
  final List<GoodsCatalogLocation> locations;
  final List<GoodsCatalogUnit> units;
  final List<GoodsCatalogBorrower> activeBorrowers;

  bool get isAsset => managementType == 'aset_individual';
  String get availableLabel => '${_quantity(availableQuantity)} $unit';
}

class GoodsCatalogSummary {
  const GoodsCatalogSummary({
    required this.activeGoods,
    required this.availableUnits,
    required this.borrowedUnits,
    required this.availableStock,
    required this.outOfStock,
  });

  factory GoodsCatalogSummary.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogSummary(
        activeGoods: _int(json['barang_aktif']),
        availableUnits: _int(json['unit_tersedia']),
        borrowedUnits: _int(json['unit_dipinjam']),
        availableStock: _int(json['stok_tersedia']),
        outOfStock: _int(json['stok_habis']),
      );

  final int activeGoods;
  final int availableUnits;
  final int borrowedUnits;
  final int availableStock;
  final int outOfStock;
}

class GoodsCatalogAccess {
  const GoodsCatalogAccess({required this.canRequest});
  factory GoodsCatalogAccess.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogAccess(
        canRequest: json['dapat_mengajukan'] as bool? ?? false,
      );
  final bool canRequest;
}

class GoodsCatalogPagination {
  const GoodsCatalogPagination({
    required this.page,
    required this.lastPage,
    required this.total,
    required this.hasNextPage,
  });
  factory GoodsCatalogPagination.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogPagination(
        page: _int(json['halaman']),
        lastPage: _int(json['halaman_terakhir']),
        total: _int(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int lastPage;
  final int total;
  final bool hasNextPage;
}

class GoodsCatalogFilter {
  const GoodsCatalogFilter({
    this.query = '',
    this.categoryId,
    this.type = 'semua',
    this.availability = 'semua',
  });
  factory GoodsCatalogFilter.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogFilter(
        query: json['kata_kunci'] as String? ?? '',
        categoryId: _nullableInt(json['kategori_barang_id']),
        type: json['jenis_barang'] as String? ?? 'semua',
        availability: json['ketersediaan'] as String? ?? 'semua',
      );
  final String query;
  final int? categoryId;
  final String type;
  final String availability;

  int get activeCount =>
      (categoryId == null ? 0 : 1) +
      (type == 'semua' ? 0 : 1) +
      (availability == 'semua' ? 0 : 1);
}

class GoodsCatalogIdOption {
  const GoodsCatalogIdOption({required this.id, required this.label});
  factory GoodsCatalogIdOption.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogIdOption(
        id: _int(json['id']),
        label: json['label'] as String? ?? '-',
      );
  final int id;
  final String label;
}

class GoodsCatalogValueOption {
  const GoodsCatalogValueOption({required this.value, required this.label});
  factory GoodsCatalogValueOption.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogValueOption(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String value;
  final String label;
}

class GoodsCatalogLocation {
  const GoodsCatalogLocation({
    required this.name,
    required this.quantity,
    required this.available,
    required this.borrowed,
  });
  factory GoodsCatalogLocation.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogLocation(
        name: json['lokasi'] as String? ?? '-',
        quantity: _double(json['jumlah']),
        available: _double(json['tersedia']),
        borrowed: _double(json['dipinjam']),
      );
  final String name;
  final double quantity;
  final double available;
  final double borrowed;
}

class GoodsCatalogUnit {
  const GoodsCatalogUnit({
    required this.id,
    required this.inventoryCode,
    required this.officialAssetNumber,
    required this.location,
    required this.condition,
    required this.status,
    required this.statusLabel,
  });
  factory GoodsCatalogUnit.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogUnit(
        id: _int(json['id']),
        inventoryCode: json['kode_inventaris'] as String? ?? '-',
        officialAssetNumber: json['nomor_aset_resmi'] as String?,
        location: json['lokasi'] as String? ?? '-',
        condition: json['kondisi'] as String? ?? '-',
        status: json['status'] as String? ?? '-',
        statusLabel: json['status_label'] as String? ?? '-',
      );
  final int id;
  final String inventoryCode;
  final String? officialAssetNumber;
  final String location;
  final String condition;
  final String status;
  final String statusLabel;
}

class GoodsCatalogBorrower {
  const GoodsCatalogBorrower({
    required this.name,
    required this.unit,
    required this.inventoryCode,
    this.plannedReturn,
    this.plannedReturnLabel,
  });
  factory GoodsCatalogBorrower.fromJson(Map<String, dynamic> json) =>
      GoodsCatalogBorrower(
        name: json['nama'] as String? ?? '-',
        unit: json['unit'] as String? ?? '-',
        inventoryCode: json['kode_inventaris'] as String? ?? '-',
        plannedReturn: json['rencana_kembali'] as String?,
        plannedReturnLabel: json['rencana_kembali_label'] as String?,
      );
  final String name;
  final String unit;
  final String inventoryCode;
  final String? plannedReturn;
  final String? plannedReturnLabel;
}

String goodsCatalogMessage(Object error) {
  final value = '$error'.trim();
  return value.isEmpty ? 'Data katalog tidak dapat dimuat.' : value;
}

String goodsCatalogQuantity(num value) => _quantity(value.toDouble());

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) fromJson) =>
    value is List
    ? value.whereType<Map>().map((item) => fromJson(_map(item))).toList()
    : <T>[];
int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
int? _nullableInt(Object? value) => value == null ? null : _int(value);
double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
String _quantity(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value
          .toStringAsFixed(2)
          .replaceFirst(RegExp(r'0+$'), '')
          .replaceFirst(RegExp(r'\.$'), '');
