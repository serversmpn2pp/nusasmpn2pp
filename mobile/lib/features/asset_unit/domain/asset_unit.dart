class AssetUnitPage {
  const AssetUnitPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.goods,
    required this.locations,
    required this.sources,
    required this.conditions,
    required this.statuses,
    required this.assetNumber,
    required this.query,
    required this.dataStatus,
    required this.condition,
    required this.unitStatus,
    this.goodsId,
    this.locationId,
  });

  factory AssetUnitPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final options = _map(json['pilihan']);
    return AssetUnitPage(
      items: _list(json['items'], AssetUnit.fromJson),
      summary: AssetUnitSummary.fromJson(_map(json['ringkasan'])),
      access: AssetUnitAccess.fromJson(_map(json['hak_akses'])),
      pagination: AssetUnitPagination.fromJson(_map(json['paginasi'])),
      goods: _list(options['barang'], AssetGoods.fromJson),
      locations: _list(options['lokasi'], AssetOption.fromJson),
      sources: _list(options['sumber_perolehan'], AssetOption.fromJson),
      conditions: _list(options['kondisi'], AssetLabelOption.fromJson),
      statuses: _list(options['status_unit'], AssetLabelOption.fromJson),
      assetNumber: AssetNumberPattern.fromJson(_map(options['nomor_aset'])),
      query: filter['cari'] as String? ?? '',
      dataStatus: filter['status'] as String? ?? 'semua',
      condition: filter['kondisi'] as String? ?? 'semua',
      unitStatus: filter['status_unit'] as String? ?? 'semua',
      goodsId: _nullableInteger(filter['barang_id']),
      locationId: _nullableInteger(filter['lokasi_barang_id']),
    );
  }

  final List<AssetUnit> items;
  final AssetUnitSummary summary;
  final AssetUnitAccess access;
  final AssetUnitPagination pagination;
  final List<AssetGoods> goods;
  final List<AssetOption> locations;
  final List<AssetOption> sources;
  final List<AssetLabelOption> conditions;
  final List<AssetLabelOption> statuses;
  final AssetNumberPattern assetNumber;
  final String query;
  final String dataStatus;
  final String condition;
  final String unitStatus;
  final int? goodsId;
  final int? locationId;

  AssetUnitPage append(AssetUnitPage next) => AssetUnitPage(
    items: [...items, ...next.items],
    summary: next.summary,
    access: next.access,
    pagination: next.pagination,
    goods: next.goods,
    locations: next.locations,
    sources: next.sources,
    conditions: next.conditions,
    statuses: next.statuses,
    assetNumber: next.assetNumber,
    query: next.query,
    dataStatus: next.dataStatus,
    condition: next.condition,
    unitStatus: next.unitStatus,
    goodsId: next.goodsId,
    locationId: next.locationId,
  );
}

class AssetUnit {
  const AssetUnit({
    required this.id,
    required this.goods,
    required this.unitNumber,
    required this.goodsUnitCode,
    required this.inventoryCode,
    required this.condition,
    required this.conditionLabel,
    required this.unitStatus,
    required this.unitStatusLabel,
    required this.active,
    this.officialAssetNumber,
    this.location,
    this.serialNumber,
    this.brand,
    this.model,
    this.acquisitionDate,
    this.acquisitionYear,
    this.source,
    this.legacySource,
    this.acquisitionPrice,
    this.notes,
    this.activeLoan,
    this.history = const [],
  });

  factory AssetUnit.fromJson(Map<String, dynamic> json) => AssetUnit(
    id: _integer(json['id']),
    goods: AssetGoods.fromJson(_map(json['barang'])),
    unitNumber: _integer(json['nomor_unit']),
    goodsUnitCode: json['kode_barang_unit'] as String? ?? '-',
    inventoryCode: json['kode_inventaris'] as String? ?? '-',
    officialAssetNumber: json['nomor_aset_resmi'] as String?,
    location: json['lokasi'] is Map
        ? AssetOption.fromJson(_map(json['lokasi']))
        : null,
    serialNumber: json['nomor_seri'] as String?,
    brand: json['merek'] as String?,
    model: json['tipe'] as String?,
    condition: json['kondisi'] as String? ?? 'baik',
    conditionLabel: json['label_kondisi'] as String? ?? '-',
    unitStatus: json['status_unit'] as String? ?? 'tersedia',
    unitStatusLabel: json['label_status_unit'] as String? ?? '-',
    acquisitionDate: _date(json['tanggal_perolehan']),
    acquisitionYear: _nullableInteger(json['tahun_perolehan']),
    source: json['sumber_perolehan'] is Map
        ? AssetOption.fromJson(_map(json['sumber_perolehan']))
        : null,
    legacySource: json['sumber_perolehan_lama'] as String?,
    acquisitionPrice: _nullableDecimal(json['harga_perolehan']),
    notes: json['keterangan'] as String?,
    active: json['aktif'] as bool? ?? false,
    activeLoan: json['peminjaman_aktif'] is Map
        ? AssetActiveLoan.fromJson(_map(json['peminjaman_aktif']))
        : null,
    history: _list(json['riwayat'], AssetHistory.fromJson),
  );

  final int id;
  final AssetGoods goods;
  final int unitNumber;
  final String goodsUnitCode;
  final String inventoryCode;
  final String? officialAssetNumber;
  final AssetOption? location;
  final String? serialNumber;
  final String? brand;
  final String? model;
  final String condition;
  final String conditionLabel;
  final String unitStatus;
  final String unitStatusLabel;
  final DateTime? acquisitionDate;
  final int? acquisitionYear;
  final AssetOption? source;
  final String? legacySource;
  final double? acquisitionPrice;
  final String? notes;
  final bool active;
  final AssetActiveLoan? activeLoan;
  final List<AssetHistory> history;

  String get brandModel => [
    brand,
    model,
  ].where((value) => value?.trim().isNotEmpty == true).join(' · ');

  String get sourceName => source?.name ?? legacySource ?? '-';
}

class AssetUnitSummary {
  const AssetUnitSummary({
    required this.total,
    required this.active,
    required this.available,
    required this.needsAttention,
  });

  factory AssetUnitSummary.fromJson(Map<String, dynamic> json) =>
      AssetUnitSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        available: _integer(json['tersedia']),
        needsAttention: _integer(json['perlu_perhatian']),
      );

  final int total;
  final int active;
  final int available;
  final int needsAttention;
}

class AssetGoods {
  const AssetGoods({
    required this.id,
    required this.name,
    required this.code,
    this.category = '-',
    this.unit = '-',
    this.active = true,
  });

  factory AssetGoods.fromJson(Map<String, dynamic> json) => AssetGoods(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    code: json['kode'] as String? ?? '-',
    category: json['kategori'] as String? ?? '-',
    unit: json['satuan'] as String? ?? '-',
    active: json['aktif'] as bool? ?? true,
  );

  final int id;
  final String name;
  final String code;
  final String category;
  final String unit;
  final bool active;

  String get label => '$name · $code${active ? '' : ' · Nonaktif'}';
}

class AssetOption {
  const AssetOption({
    required this.id,
    required this.name,
    required this.code,
    this.active = true,
  });

  factory AssetOption.fromJson(Map<String, dynamic> json) => AssetOption(
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

class AssetLabelOption {
  const AssetLabelOption({required this.value, required this.label});

  factory AssetLabelOption.fromJson(Map<String, dynamic> json) =>
      AssetLabelOption(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String value;
  final String label;
}

class AssetNumberPattern {
  const AssetNumberPattern({
    required this.prefix,
    required this.suffix,
    required this.example,
  });

  factory AssetNumberPattern.fromJson(Map<String, dynamic> json) =>
      AssetNumberPattern(
        prefix: json['awalan'] as String? ?? '',
        suffix: json['akhiran'] as String? ?? '',
        example: json['contoh'] as String? ?? '-',
      );

  final String prefix;
  final String suffix;
  final String example;

  String preview(int year) => '$prefix.$year.$suffix';
}

class AssetActiveLoan {
  const AssetActiveLoan({
    required this.number,
    required this.borrower,
    required this.identity,
    required this.monitoring,
    this.dueDate,
  });

  factory AssetActiveLoan.fromJson(Map<String, dynamic> json) =>
      AssetActiveLoan(
        number: json['nomor'] as String? ?? '-',
        borrower: json['peminjam'] as String? ?? '-',
        identity: json['identitas'] as String? ?? '-',
        dueDate: _date(json['rencana_kembali']),
        monitoring: json['pemantauan'] as String? ?? '-',
      );

  final String number;
  final String borrower;
  final String identity;
  final DateTime? dueDate;
  final String monitoring;
}

class AssetHistory {
  const AssetHistory({
    required this.type,
    required this.label,
    required this.title,
    required this.description,
    this.date,
  });

  factory AssetHistory.fromJson(Map<String, dynamic> json) => AssetHistory(
    type: json['jenis'] as String? ?? 'pencatatan',
    label: json['label'] as String? ?? '-',
    title: json['judul'] as String? ?? '-',
    description: json['keterangan'] as String? ?? '',
    date: _date(json['tanggal']),
  );

  final String type;
  final String label;
  final String title;
  final String description;
  final DateTime? date;
}

class AssetUnitAccess {
  const AssetUnitAccess({required this.canManage});

  factory AssetUnitAccess.fromJson(Map<String, dynamic> json) =>
      AssetUnitAccess(canManage: json['dapat_kelola'] as bool? ?? false);

  final bool canManage;
}

class AssetUnitPagination {
  const AssetUnitPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory AssetUnitPagination.fromJson(Map<String, dynamic> json) =>
      AssetUnitPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class AssetUnitFormValue {
  const AssetUnitFormValue({
    required this.goodsId,
    required this.quantity,
    required this.condition,
    required this.unitStatus,
    required this.acquisitionYear,
    required this.active,
    this.locationId,
    this.serialNumber,
    this.brand,
    this.model,
    this.acquisitionDate,
    this.sourceId,
    this.acquisitionPrice,
    this.notes,
  });

  final int goodsId;
  final int quantity;
  final int? locationId;
  final String? serialNumber;
  final String? brand;
  final String? model;
  final String condition;
  final String unitStatus;
  final DateTime? acquisitionDate;
  final int acquisitionYear;
  final int? sourceId;
  final double? acquisitionPrice;
  final String? notes;
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

double? _nullableDecimal(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text),
  _ => null,
};

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value) : null;
