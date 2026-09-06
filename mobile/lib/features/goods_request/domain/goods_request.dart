class GoodsRequestPage {
  const GoodsRequestPage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.types,
    required this.statuses,
    required this.query,
    required this.type,
    required this.status,
  });

  factory GoodsRequestPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final options = _map(json['pilihan']);
    return GoodsRequestPage(
      items: _list(json['items'], GoodsRequest.fromJson),
      summary: GoodsRequestSummary.fromJson(_map(json['ringkasan'])),
      pagination: GoodsRequestPagination.fromJson(_map(json['paginasi'])),
      types: _list(options['jenis'], GoodsRequestOption.fromJson),
      statuses: _list(options['status'], GoodsRequestOption.fromJson),
      query: filter['kata_kunci'] as String? ?? '',
      type: filter['jenis'] as String? ?? 'semua',
      status: filter['status'] as String? ?? 'menunggu',
    );
  }

  final List<GoodsRequest> items;
  final GoodsRequestSummary summary;
  final GoodsRequestPagination pagination;
  final List<GoodsRequestOption> types;
  final List<GoodsRequestOption> statuses;
  final String query;
  final String type;
  final String status;

  GoodsRequestPage append(GoodsRequestPage next) => GoodsRequestPage(
    items: [...items, ...next.items],
    summary: next.summary,
    pagination: next.pagination,
    types: next.types,
    statuses: next.statuses,
    query: next.query,
    type: next.type,
    status: next.status,
  );
}

class GoodsRequestDetail {
  const GoodsRequestDetail({
    required this.request,
    required this.units,
    required this.stocks,
    required this.requiredUnits,
    required this.canFulfill,
    required this.canReject,
  });

  factory GoodsRequestDetail.fromJson(Map<String, dynamic> json) {
    final availability = _map(json['ketersediaan']);
    final access = _map(json['hak_akses']);
    return GoodsRequestDetail(
      request: GoodsRequest.fromJson(_map(json['pengajuan'])),
      units: _list(availability['unit'], GoodsRequestUnit.fromJson),
      stocks: _list(availability['saldo'], GoodsRequestStock.fromJson),
      requiredUnits: _int(availability['unit_dibutuhkan']),
      canFulfill: access['dapat_memenuhi'] as bool? ?? false,
      canReject: access['dapat_menolak'] as bool? ?? false,
    );
  }

  final GoodsRequest request;
  final List<GoodsRequestUnit> units;
  final List<GoodsRequestStock> stocks;
  final int requiredUnits;
  final bool canFulfill;
  final bool canReject;
}

class GoodsRequest {
  const GoodsRequest({
    required this.id,
    required this.number,
    required this.employeeName,
    required this.employeeType,
    required this.goodsName,
    required this.goodsCode,
    required this.type,
    required this.typeLabel,
    required this.quantity,
    required this.unit,
    required this.submissionDateLabel,
    required this.requiredDateLabel,
    required this.status,
    required this.statusLabel,
    this.nip,
    this.plannedReturnLabel,
    this.purpose,
    this.officerNotes,
    this.processedBy,
    this.processedAtLabel,
    this.loanId,
    this.loanNumber,
    this.managementType,
    this.category,
  });

  factory GoodsRequest.fromJson(Map<String, dynamic> json) => GoodsRequest(
    id: _int(json['id']),
    number: json['nomor'] as String? ?? '-',
    employeeName: json['nama_pegawai'] as String? ?? '-',
    nip: json['nip'] as String?,
    employeeType: json['jenis_pegawai'] as String? ?? 'Pegawai',
    goodsName: json['nama_barang'] as String? ?? '-',
    goodsCode: json['kode_barang'] as String? ?? '-',
    type: json['jenis'] as String? ?? '-',
    typeLabel: json['jenis_label'] as String? ?? '-',
    quantity: _double(json['jumlah']),
    unit: json['satuan'] as String? ?? 'unit',
    submissionDateLabel: json['tanggal_pengajuan_label'] as String? ?? '-',
    requiredDateLabel: json['tanggal_dibutuhkan_label'] as String? ?? '-',
    plannedReturnLabel: json['rencana_kembali_label'] as String?,
    status: json['status'] as String? ?? '-',
    statusLabel: json['status_label'] as String? ?? '-',
    purpose: json['tujuan'] as String?,
    officerNotes: json['catatan_petugas'] as String?,
    processedBy: json['diproses_oleh'] as String?,
    processedAtLabel: json['diproses_pada_label'] as String?,
    loanId: _nullableInt(json['peminjaman_barang_id']),
    loanNumber: json['nomor_peminjaman'] as String?,
    managementType: json['tipe_pengelolaan'] as String?,
    category: json['kategori_barang'] as String?,
  );

  final int id;
  final String number;
  final String employeeName;
  final String? nip;
  final String employeeType;
  final String goodsName;
  final String goodsCode;
  final String type;
  final String typeLabel;
  final double quantity;
  final String unit;
  final String submissionDateLabel;
  final String requiredDateLabel;
  final String? plannedReturnLabel;
  final String status;
  final String statusLabel;
  final String? purpose;
  final String? officerNotes;
  final String? processedBy;
  final String? processedAtLabel;
  final int? loanId;
  final String? loanNumber;
  final String? managementType;
  final String? category;
  bool get pending => status == 'menunggu';
}

class GoodsRequestUnit {
  const GoodsRequestUnit({
    required this.id,
    required this.code,
    required this.location,
    required this.condition,
    this.officialNumber,
  });
  factory GoodsRequestUnit.fromJson(Map<String, dynamic> json) =>
      GoodsRequestUnit(
        id: _int(json['id']),
        code: json['kode'] as String? ?? '-',
        officialNumber: json['nomor_aset_resmi'] as String?,
        location: json['lokasi'] as String? ?? '-',
        condition: json['kondisi'] as String? ?? '-',
      );
  final int id;
  final String code;
  final String? officialNumber;
  final String location;
  final String condition;
}

class GoodsRequestStock {
  const GoodsRequestStock({
    required this.locationId,
    required this.location,
    required this.quantity,
    required this.unit,
  });
  factory GoodsRequestStock.fromJson(Map<String, dynamic> json) =>
      GoodsRequestStock(
        locationId: _int(json['lokasi_barang_id']),
        location: json['lokasi'] as String? ?? '-',
        quantity: _double(json['jumlah']),
        unit: json['satuan'] as String? ?? 'unit',
      );
  final int locationId;
  final String location;
  final double quantity;
  final String unit;
}

class GoodsRequestSummary {
  const GoodsRequestSummary({
    required this.total,
    required this.pending,
    required this.loans,
    required this.consumables,
  });
  factory GoodsRequestSummary.fromJson(Map<String, dynamic> json) =>
      GoodsRequestSummary(
        total: _int(json['semua']),
        pending: _int(json['menunggu']),
        loans: _int(json['peminjaman']),
        consumables: _int(json['permintaan']),
      );
  final int total;
  final int pending;
  final int loans;
  final int consumables;
}

class GoodsRequestPagination {
  const GoodsRequestPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });
  factory GoodsRequestPagination.fromJson(Map<String, dynamic> json) =>
      GoodsRequestPagination(
        page: _int(json['halaman'], 1),
        total: _int(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int total;
  final bool hasNextPage;
}

class GoodsRequestOption {
  const GoodsRequestOption({required this.value, required this.label});
  factory GoodsRequestOption.fromJson(Map<String, dynamic> json) =>
      GoodsRequestOption(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String value;
  final String label;
}

class GoodsRequestFulfillValue {
  const GoodsRequestFulfillValue({
    required this.unitIds,
    this.locationId,
    this.notes,
  });
  final List<int> unitIds;
  final int? locationId;
  final String? notes;
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<T> _list<T>(dynamic value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => convert(Map<String, dynamic>.from(item)))
          .toList()
    : <T>[];
int _int(dynamic value, [int fallback = 0]) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;
int? _nullableInt(dynamic value) => value == null ? null : _int(value);
double _double(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
