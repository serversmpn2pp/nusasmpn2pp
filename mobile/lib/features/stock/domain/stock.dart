class StockBalancePage {
  const StockBalancePage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.statusOptions,
    required this.categories,
    required this.locations,
    required this.query,
    required this.status,
    this.categoryId,
    this.locationId,
  });

  factory StockBalancePage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final options = _map(json['pilihan']);
    return StockBalancePage(
      items: _list(json['items'], StockBalance.fromJson),
      summary: StockBalanceSummary.fromJson(_map(json['ringkasan'])),
      access: StockAccess.fromJson(_map(json['hak_akses'])),
      pagination: StockPagination.fromJson(_map(json['paginasi'])),
      statusOptions: _list(options['status_stok'], StockValueOption.fromJson),
      categories: _list(options['kategori'], StockOption.fromJson),
      locations: _list(options['lokasi'], StockOption.fromJson),
      query: filter['cari'] as String? ?? '',
      status: filter['status_stok'] as String? ?? 'semua',
      categoryId: _nullableInt(filter['kategori_barang_id']),
      locationId: _nullableInt(filter['lokasi_barang_id']),
    );
  }

  final List<StockBalance> items;
  final StockBalanceSummary summary;
  final StockAccess access;
  final StockPagination pagination;
  final List<StockValueOption> statusOptions;
  final List<StockOption> categories;
  final List<StockOption> locations;
  final String query;
  final String status;
  final int? categoryId;
  final int? locationId;

  StockBalancePage append(StockBalancePage next) => StockBalancePage(
    items: [...items, ...next.items],
    summary: next.summary,
    access: next.access,
    pagination: next.pagination,
    statusOptions: next.statusOptions,
    categories: next.categories,
    locations: next.locations,
    query: next.query,
    status: next.status,
    categoryId: next.categoryId,
    locationId: next.locationId,
  );
}

class StockMovementPage {
  const StockMovementPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.typeOptions,
    required this.categoryOptions,
    required this.categoriesByType,
    required this.goods,
    required this.locations,
    required this.query,
    required this.type,
    this.goodsId,
    this.locationId,
    this.startDate,
    this.endDate,
  });

  factory StockMovementPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final options = _map(json['pilihan']);
    final rawCategories = _map(options['kategori_per_jenis']);
    return StockMovementPage(
      items: _list(json['items'], StockMovement.fromJson),
      summary: StockMovementSummary.fromJson(_map(json['ringkasan'])),
      access: StockAccess.fromJson(_map(json['hak_akses'])),
      pagination: StockPagination.fromJson(_map(json['paginasi'])),
      typeOptions: _list(options['jenis_mutasi'], StockValueOption.fromJson),
      categoryOptions: _list(
        options['kategori_mutasi'],
        StockValueOption.fromJson,
      ),
      categoriesByType: {
        for (final entry in rawCategories.entries)
          entry.key: (entry.value as List? ?? const [])
              .map((item) => item.toString())
              .toList(growable: false),
      },
      goods: _list(options['barang'], StockGoods.fromJson),
      locations: _list(options['lokasi'], StockOption.fromJson),
      query: filter['cari'] as String? ?? '',
      type: filter['jenis_mutasi'] as String? ?? 'semua',
      goodsId: _nullableInt(filter['barang_id']),
      locationId: _nullableInt(filter['lokasi_barang_id']),
      startDate: _date(filter['tanggal_mulai']),
      endDate: _date(filter['tanggal_selesai']),
    );
  }

  final List<StockMovement> items;
  final StockMovementSummary summary;
  final StockAccess access;
  final StockPagination pagination;
  final List<StockValueOption> typeOptions;
  final List<StockValueOption> categoryOptions;
  final Map<String, List<String>> categoriesByType;
  final List<StockGoods> goods;
  final List<StockOption> locations;
  final String query;
  final String type;
  final int? goodsId;
  final int? locationId;
  final DateTime? startDate;
  final DateTime? endDate;

  StockMovementPage append(StockMovementPage next) => StockMovementPage(
    items: [...items, ...next.items],
    summary: next.summary,
    access: next.access,
    pagination: next.pagination,
    typeOptions: next.typeOptions,
    categoryOptions: next.categoryOptions,
    categoriesByType: next.categoriesByType,
    goods: next.goods,
    locations: next.locations,
    query: next.query,
    type: next.type,
    goodsId: next.goodsId,
    locationId: next.locationId,
    startDate: next.startDate,
    endDate: next.endDate,
  );
}

class StockBalance {
  const StockBalance({
    required this.id,
    required this.goods,
    required this.location,
    required this.quantity,
    required this.minimum,
    required this.status,
    required this.statusLabel,
  });

  factory StockBalance.fromJson(Map<String, dynamic> json) => StockBalance(
    id: _int(json['id']),
    goods: StockGoods.fromJson(_map(json['barang'])),
    location: StockOption.fromJson(_map(json['lokasi'])),
    quantity: _double(json['jumlah']),
    minimum: _double(json['stok_minimum']),
    status: json['status'] as String? ?? 'aman',
    statusLabel: json['status_label'] as String? ?? 'Aman',
  );

  final int id;
  final StockGoods goods;
  final StockOption location;
  final double quantity;
  final double minimum;
  final String status;
  final String statusLabel;
}

class StockMovement {
  const StockMovement({
    required this.id,
    required this.dateLabel,
    required this.goods,
    required this.location,
    required this.type,
    required this.typeLabel,
    required this.category,
    required this.categoryLabel,
    required this.change,
    required this.before,
    required this.after,
    required this.createdBy,
    this.date,
    this.reference,
    this.notes,
    this.createdAt,
  });

  factory StockMovement.fromJson(Map<String, dynamic> json) => StockMovement(
    id: _int(json['id']),
    date: _date(json['tanggal']),
    dateLabel: json['tanggal_label'] as String? ?? '-',
    goods: StockGoods.fromJson(_map(json['barang'])),
    location: StockOption.fromJson(_map(json['lokasi'])),
    type: json['jenis_mutasi'] as String? ?? '-',
    typeLabel: json['jenis_label'] as String? ?? '-',
    category: json['kategori_mutasi'] as String? ?? '-',
    categoryLabel: json['kategori_label'] as String? ?? '-',
    change: _double(json['jumlah_perubahan']),
    before: _double(json['saldo_sebelum']),
    after: _double(json['saldo_sesudah']),
    reference: json['referensi'] as String?,
    notes: json['keterangan'] as String?,
    createdBy: json['dibuat_oleh'] as String? ?? 'Sistem',
    createdAt: DateTime.tryParse(json['dibuat_pada'] as String? ?? ''),
  );

  final int id;
  final DateTime? date;
  final String dateLabel;
  final StockGoods goods;
  final StockOption location;
  final String type;
  final String typeLabel;
  final String category;
  final String categoryLabel;
  final double change;
  final double before;
  final double after;
  final String? reference;
  final String? notes;
  final String createdBy;
  final DateTime? createdAt;
}

class StockGoods {
  const StockGoods({
    required this.id,
    required this.name,
    required this.code,
    required this.unit,
    required this.active,
    this.category,
  });

  factory StockGoods.fromJson(Map<String, dynamic> json) => StockGoods(
    id: _int(json['id']),
    name: json['nama'] as String? ?? '-',
    code: json['kode'] as String? ?? '-',
    unit: json['satuan'] as String? ?? 'unit',
    active: json['aktif'] as bool? ?? true,
    category: json['kategori'] as String?,
  );

  final int id;
  final String name;
  final String code;
  final String unit;
  final bool active;
  final String? category;
  String get label => active ? '$name · $code' : '$name · Nonaktif';
}

class StockOption {
  const StockOption({
    required this.id,
    required this.name,
    required this.code,
    required this.active,
  });

  factory StockOption.fromJson(Map<String, dynamic> json) => StockOption(
    id: _int(json['id']),
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

class StockValueOption {
  const StockValueOption({required this.value, required this.label});

  factory StockValueOption.fromJson(Map<String, dynamic> json) =>
      StockValueOption(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String value;
  final String label;
}

class StockBalanceSummary {
  const StockBalanceSummary({
    required this.rows,
    required this.locations,
    required this.low,
    required this.empty,
  });

  factory StockBalanceSummary.fromJson(Map<String, dynamic> json) =>
      StockBalanceSummary(
        rows: _int(json['baris_saldo']),
        locations: _int(json['lokasi_stok']),
        low: _int(json['menipis']),
        empty: _int(json['habis']),
      );

  final int rows;
  final int locations;
  final int low;
  final int empty;
}

class StockMovementSummary {
  const StockMovementSummary({
    required this.total,
    required this.today,
    required this.inToday,
    required this.outToday,
  });

  factory StockMovementSummary.fromJson(Map<String, dynamic> json) =>
      StockMovementSummary(
        total: _int(json['total']),
        today: _int(json['hari_ini']),
        inToday: _double(json['masuk_hari_ini']),
        outToday: _double(json['keluar_hari_ini']),
      );

  final int total;
  final int today;
  final double inToday;
  final double outToday;
}

class StockAccess {
  const StockAccess({required this.canManage});
  factory StockAccess.fromJson(Map<String, dynamic> json) =>
      StockAccess(canManage: json['dapat_kelola'] as bool? ?? false);
  final bool canManage;
}

class StockPagination {
  const StockPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });
  factory StockPagination.fromJson(Map<String, dynamic> json) =>
      StockPagination(
        page: _int(json['halaman']),
        total: _int(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int total;
  final bool hasNextPage;
}

class StockMovementFormValue {
  const StockMovementFormValue({
    required this.goodsId,
    required this.locationId,
    required this.type,
    required this.category,
    required this.date,
    required this.quantity,
    this.reference,
    this.notes,
  });

  final int goodsId;
  final int locationId;
  final String type;
  final String category;
  final DateTime date;
  final double quantity;
  final String? reference;
  final String? notes;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) parser) =>
    (value as List? ?? const [])
        .whereType<Map>()
        .map((item) => parser(Map<String, dynamic>.from(item)))
        .toList(growable: false);

int _int(Object? value) => switch (value) {
  int item => item,
  num item => item.toInt(),
  String item => int.tryParse(item) ?? 0,
  _ => 0,
};

int? _nullableInt(Object? value) => value == null ? null : _int(value);

double _double(Object? value) => switch (value) {
  num item => item.toDouble(),
  String item => double.tryParse(item) ?? 0,
  _ => 0,
};

DateTime? _date(Object? value) =>
    value == null ? null : DateTime.tryParse(value.toString());
