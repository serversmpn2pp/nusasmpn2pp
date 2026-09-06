class GoodsReceiptPage {
  const GoodsReceiptPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.sources,
    required this.goods,
    required this.locations,
    required this.acquisitionMethods,
    required this.conditions,
    required this.query,
    this.sourceId,
    this.startDate,
    this.endDate,
  });

  factory GoodsReceiptPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final options = _map(json['pilihan']);
    return GoodsReceiptPage(
      items: _list(json['items'], GoodsReceipt.fromJson),
      summary: GoodsReceiptSummary.fromJson(_map(json['ringkasan'])),
      access: GoodsReceiptAccess.fromJson(_map(json['hak_akses'])),
      pagination: GoodsReceiptPagination.fromJson(_map(json['paginasi'])),
      sources: _list(options['sumber_perolehan'], GoodsReceiptOption.fromJson),
      goods: _list(options['barang'], GoodsReceiptGoods.fromJson),
      locations: _list(options['lokasi'], GoodsReceiptOption.fromJson),
      acquisitionMethods: _list(
        options['cara_perolehan'],
        GoodsReceiptValueOption.fromJson,
      ),
      conditions: _list(options['kondisi'], GoodsReceiptValueOption.fromJson),
      query: filter['cari'] as String? ?? '',
      sourceId: _nullableInt(filter['sumber_perolehan_barang_id']),
      startDate: _date(filter['tanggal_mulai']),
      endDate: _date(filter['tanggal_selesai']),
    );
  }

  final List<GoodsReceipt> items;
  final GoodsReceiptSummary summary;
  final GoodsReceiptAccess access;
  final GoodsReceiptPagination pagination;
  final List<GoodsReceiptOption> sources;
  final List<GoodsReceiptGoods> goods;
  final List<GoodsReceiptOption> locations;
  final List<GoodsReceiptValueOption> acquisitionMethods;
  final List<GoodsReceiptValueOption> conditions;
  final String query;
  final int? sourceId;
  final DateTime? startDate;
  final DateTime? endDate;

  GoodsReceiptPage append(GoodsReceiptPage next) => GoodsReceiptPage(
    items: [...items, ...next.items],
    summary: next.summary,
    access: next.access,
    pagination: next.pagination,
    sources: next.sources,
    goods: next.goods,
    locations: next.locations,
    acquisitionMethods: next.acquisitionMethods,
    conditions: next.conditions,
    query: next.query,
    sourceId: next.sourceId,
    startDate: next.startDate,
    endDate: next.endDate,
  );
}

class GoodsReceipt {
  const GoodsReceipt({
    required this.id,
    required this.number,
    required this.date,
    required this.dateLabel,
    required this.acquisitionMethod,
    required this.acquisitionMethodLabel,
    required this.status,
    required this.statusLabel,
    required this.detailCount,
    required this.totalValue,
    required this.details,
    this.source,
    this.documentNumber,
    this.origin,
    this.notes,
    this.createdBy,
    this.cancellationReason,
    this.cancelledAtLabel,
    this.cancelledBy,
  });

  factory GoodsReceipt.fromJson(Map<String, dynamic> json) => GoodsReceipt(
    id: _int(json['id']),
    number: json['nomor'] as String? ?? '-',
    date: _date(json['tanggal']),
    dateLabel: json['tanggal_label'] as String? ?? '-',
    source: json['sumber_perolehan'] is Map
        ? GoodsReceiptOption.fromJson(_map(json['sumber_perolehan']))
        : null,
    acquisitionMethod: json['cara_perolehan'] as String? ?? '-',
    acquisitionMethodLabel: json['cara_perolehan_label'] as String? ?? '-',
    status: json['status'] as String? ?? 'aktif',
    statusLabel: json['status_label'] as String? ?? 'Aktif',
    documentNumber: json['nomor_dokumen'] as String?,
    origin: json['asal_barang'] as String?,
    notes: json['catatan'] as String?,
    createdBy: json['dibuat_oleh'] as String?,
    detailCount: _int(json['jumlah_rincian']),
    totalValue: _double(json['nilai_total']),
    cancellationReason: json['alasan_pembatalan'] as String?,
    cancelledAtLabel: json['dibatalkan_pada_label'] as String?,
    cancelledBy: json['dibatalkan_oleh'] as String?,
    details: _list(json['rincian'], GoodsReceiptDetail.fromJson),
  );

  final int id;
  final String number;
  final DateTime? date;
  final String dateLabel;
  final GoodsReceiptOption? source;
  final String acquisitionMethod;
  final String acquisitionMethodLabel;
  final String status;
  final String statusLabel;
  final String? documentNumber;
  final String? origin;
  final String? notes;
  final String? createdBy;
  final int detailCount;
  final double totalValue;
  final String? cancellationReason;
  final String? cancelledAtLabel;
  final String? cancelledBy;
  final List<GoodsReceiptDetail> details;

  bool get cancelled => status == 'dibatalkan';
}

class GoodsReceiptDetail {
  const GoodsReceiptDetail({
    required this.id,
    required this.goods,
    required this.quantity,
    required this.subtotal,
    required this.assetUnits,
    this.location,
    this.unitPrice,
    this.brand,
    this.model,
    this.condition,
    this.conditionLabel,
    this.notes,
    this.stockMutationId,
    this.cancellationMutationId,
  });

  factory GoodsReceiptDetail.fromJson(Map<String, dynamic> json) =>
      GoodsReceiptDetail(
        id: _int(json['id']),
        goods: GoodsReceiptGoods.fromJson(_map(json['barang'])),
        location: json['lokasi'] is Map
            ? GoodsReceiptOption.fromJson(_map(json['lokasi']))
            : null,
        quantity: _double(json['jumlah']),
        unitPrice: _nullableDouble(json['harga_satuan']),
        subtotal: _double(json['nilai_subtotal']),
        brand: json['merek'] as String?,
        model: json['tipe'] as String?,
        condition: json['kondisi'] as String?,
        conditionLabel: json['kondisi_label'] as String?,
        notes: json['keterangan'] as String?,
        stockMutationId: _nullableInt(json['mutasi_stok_id']),
        cancellationMutationId: _nullableInt(json['mutasi_pembatalan_id']),
        assetUnits: _list(json['unit_aset'], GoodsReceiptAssetUnit.fromJson),
      );

  final int id;
  final GoodsReceiptGoods goods;
  final GoodsReceiptOption? location;
  final double quantity;
  final double? unitPrice;
  final double subtotal;
  final String? brand;
  final String? model;
  final String? condition;
  final String? conditionLabel;
  final String? notes;
  final int? stockMutationId;
  final int? cancellationMutationId;
  final List<GoodsReceiptAssetUnit> assetUnits;
}

class GoodsReceiptAssetUnit {
  const GoodsReceiptAssetUnit({
    required this.id,
    required this.goodsUnitCode,
    required this.inventoryCode,
    required this.active,
    this.officialAssetNumber,
  });

  factory GoodsReceiptAssetUnit.fromJson(Map<String, dynamic> json) =>
      GoodsReceiptAssetUnit(
        id: _int(json['id']),
        goodsUnitCode: json['kode_barang_unit'] as String? ?? '-',
        inventoryCode: json['kode_inventaris'] as String? ?? '-',
        officialAssetNumber: json['nomor_aset_resmi'] as String?,
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String goodsUnitCode;
  final String inventoryCode;
  final String? officialAssetNumber;
  final bool active;
}

class GoodsReceiptSummary {
  const GoodsReceiptSummary({
    required this.total,
    required this.today,
    required this.assetUnitsCreated,
    required this.stockKindsEntered,
  });

  factory GoodsReceiptSummary.fromJson(Map<String, dynamic> json) =>
      GoodsReceiptSummary(
        total: _int(json['total']),
        today: _int(json['hari_ini']),
        assetUnitsCreated: _int(json['unit_aset_dibuat']),
        stockKindsEntered: _int(json['jenis_stok_masuk']),
      );

  final int total;
  final int today;
  final int assetUnitsCreated;
  final int stockKindsEntered;
}

class GoodsReceiptAccess {
  const GoodsReceiptAccess({required this.canManage, this.canCancel = false});

  factory GoodsReceiptAccess.fromJson(Map<String, dynamic> json) =>
      GoodsReceiptAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
        canCancel: json['dapat_dibatalkan'] as bool? ?? false,
      );

  final bool canManage;
  final bool canCancel;
}

class GoodsReceiptPagination {
  const GoodsReceiptPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory GoodsReceiptPagination.fromJson(Map<String, dynamic> json) =>
      GoodsReceiptPagination(
        page: _int(json['halaman']),
        total: _int(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class GoodsReceiptOption {
  const GoodsReceiptOption({
    required this.id,
    required this.name,
    required this.code,
    this.active = true,
  });

  factory GoodsReceiptOption.fromJson(Map<String, dynamic> json) =>
      GoodsReceiptOption(
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

class GoodsReceiptGoods {
  const GoodsReceiptGoods({
    required this.id,
    required this.name,
    required this.code,
    required this.type,
    required this.typeLabel,
    required this.unit,
    this.managementType,
  });

  factory GoodsReceiptGoods.fromJson(Map<String, dynamic> json) =>
      GoodsReceiptGoods(
        id: _int(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String? ?? '-',
        type: json['jenis_barang'] as String? ?? 'tidak_habis_pakai',
        typeLabel: json['jenis_label'] as String? ?? '-',
        managementType: json['tipe_pengelolaan'] as String?,
        unit: json['satuan'] as String? ?? 'unit',
      );

  final int id;
  final String name;
  final String code;
  final String type;
  final String typeLabel;
  final String? managementType;
  final String unit;
  bool get isAsset => type == 'tidak_habis_pakai';
}

class GoodsReceiptValueOption {
  const GoodsReceiptValueOption({required this.value, required this.label});

  factory GoodsReceiptValueOption.fromJson(Map<String, dynamic> json) =>
      GoodsReceiptValueOption(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String value;
  final String label;
}

class GoodsReceiptFormValue {
  const GoodsReceiptFormValue({
    required this.storageToken,
    required this.date,
    required this.sourceId,
    required this.acquisitionMethod,
    required this.lines,
    this.documentNumber,
    this.origin,
    this.notes,
  });

  final String storageToken;
  final DateTime date;
  final int sourceId;
  final String acquisitionMethod;
  final String? documentNumber;
  final String? origin;
  final String? notes;
  final List<GoodsReceiptLineValue> lines;
}

class GoodsReceiptLineValue {
  const GoodsReceiptLineValue({
    required this.goodsId,
    required this.locationId,
    required this.quantity,
    this.unitPrice,
    this.brand,
    this.model,
    this.condition,
    this.notes,
  });

  final int goodsId;
  final int locationId;
  final double quantity;
  final double? unitPrice;
  final String? brand;
  final String? model;
  final String? condition;
  final String? notes;
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

int _int(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInt(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text),
  _ => null,
};

double _double(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? 0,
  _ => 0,
};

double? _nullableDouble(Object? value) => value == null ? null : _double(value);

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value) : null;
