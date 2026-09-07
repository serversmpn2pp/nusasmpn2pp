class InventoryMonthlyReport {
  const InventoryMonthlyReport({
    required this.period,
    required this.periodLabel,
    required this.startDate,
    required this.endDate,
    required this.locationLabel,
    required this.printedAt,
    required this.locations,
    required this.summary,
    required this.employeeServiceSummary,
    required this.signatories,
    required this.unrecordedStock,
    required this.stockRows,
    required this.unitDistribution,
    required this.unitsNeedingAttention,
    required this.employeeServices,
    required this.movements,
    this.locationId,
  });

  factory InventoryMonthlyReport.fromJson(Map<String, dynamic> json) =>
      InventoryMonthlyReport(
        period: json['periode'] as String? ?? '',
        periodLabel: json['label_periode'] as String? ?? '-',
        startDate: json['awal_periode'] as String? ?? '',
        endDate: json['akhir_periode'] as String? ?? '',
        locationId: _nullableInt(json['lokasi_barang_id']),
        locationLabel: json['lokasi_label'] as String? ?? 'Semua lokasi',
        printedAt: json['dicetak_pada'] as String? ?? '-',
        locations: _list(
          _map(json['pilihan'])['lokasi'],
          InventoryReportLocation.fromJson,
        ),
        summary: InventoryReportSummary.fromJson(_map(json['ringkasan'])),
        employeeServiceSummary: InventoryEmployeeServiceSummary.fromJson(
          _map(json['ringkasan_layanan_pegawai']),
        ),
        signatories: _list(
          json['penandatangan'],
          InventoryReportSignatory.fromJson,
        ),
        unrecordedStock: _list(
          json['barang_stok_belum_dicatat'],
          InventoryUnrecordedStock.fromJson,
        ),
        stockRows: _list(json['rekap_stok'], InventoryStockRecap.fromJson),
        unitDistribution: _list(
          json['distribusi_status_unit'],
          InventoryUnitDistribution.fromJson,
        ),
        unitsNeedingAttention: _list(
          json['unit_perlu_perhatian'],
          InventoryUnitAttention.fromJson,
        ),
        employeeServices: _list(
          json['layanan_barang_pegawai'],
          InventoryEmployeeService.fromJson,
        ),
        movements: _list(
          json['mutasi_periode'],
          InventoryReportMovement.fromJson,
        ),
      );

  final String period;
  final String periodLabel;
  final String startDate;
  final String endDate;
  final int? locationId;
  final String locationLabel;
  final String printedAt;
  final List<InventoryReportLocation> locations;
  final InventoryReportSummary summary;
  final InventoryEmployeeServiceSummary employeeServiceSummary;
  final List<InventoryReportSignatory> signatories;
  final List<InventoryUnrecordedStock> unrecordedStock;
  final List<InventoryStockRecap> stockRows;
  final List<InventoryUnitDistribution> unitDistribution;
  final List<InventoryUnitAttention> unitsNeedingAttention;
  final List<InventoryEmployeeService> employeeServices;
  final List<InventoryReportMovement> movements;
}

class InventoryReportFilter {
  const InventoryReportFilter({required this.period, this.locationId});
  final String period;
  final int? locationId;
}

class InventoryReportSummary {
  const InventoryReportSummary({
    required this.stockRows,
    required this.movements,
    required this.lowStock,
    required this.outOfStock,
    required this.unrecordedStock,
    required this.assetUnits,
    required this.acquiredUnits,
    required this.unitsNeedingAttention,
  });
  factory InventoryReportSummary.fromJson(Map<String, dynamic> json) =>
      InventoryReportSummary(
        stockRows: _int(json['baris_stok']),
        movements: _int(json['jumlah_mutasi']),
        lowStock: _int(json['stok_menipis']),
        outOfStock: _int(json['stok_habis']),
        unrecordedStock: _int(json['stok_belum_dicatat']),
        assetUnits: _int(json['unit_aset']),
        acquiredUnits: _int(json['unit_diperoleh']),
        unitsNeedingAttention: _int(json['unit_perlu_perhatian']),
      );
  final int stockRows;
  final int movements;
  final int lowStock;
  final int outOfStock;
  final int unrecordedStock;
  final int assetUnits;
  final int acquiredUnits;
  final int unitsNeedingAttention;
  int get stockNeedsAttention => lowStock + outOfStock;
}

class InventoryEmployeeServiceSummary {
  const InventoryEmployeeServiceSummary({
    required this.services,
    required this.employees,
    required this.assetLoans,
    required this.consumables,
    required this.activeLoans,
  });
  factory InventoryEmployeeServiceSummary.fromJson(Map<String, dynamic> json) =>
      InventoryEmployeeServiceSummary(
        services: _int(json['jumlah_layanan']),
        employees: _int(json['pegawai_dilayani']),
        assetLoans: _int(json['peminjaman_aset']),
        consumables: _int(json['penyerahan_habis_pakai']),
        activeLoans: _int(json['pinjaman_aktif']),
      );
  final int services;
  final int employees;
  final int assetLoans;
  final int consumables;
  final int activeLoans;
}

class InventoryReportLocation {
  const InventoryReportLocation({
    required this.id,
    required this.code,
    required this.name,
    required this.active,
  });
  factory InventoryReportLocation.fromJson(Map<String, dynamic> json) =>
      InventoryReportLocation(
        id: _int(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? true,
      );
  final int id;
  final String code;
  final String name;
  final bool active;
  String get label => active ? name : '$name · Nonaktif';
}

class InventoryReportSignatory {
  const InventoryReportSignatory({
    required this.code,
    required this.position,
    required this.name,
    this.nip,
  });
  factory InventoryReportSignatory.fromJson(Map<String, dynamic> json) =>
      InventoryReportSignatory(
        code: json['kode'] as String? ?? '-',
        position: json['jabatan'] as String? ?? '-',
        name: json['nama'] as String? ?? 'Belum ditentukan',
        nip: json['nip'] as String?,
      );
  final String code;
  final String position;
  final String name;
  final String? nip;
}

class InventoryUnrecordedStock {
  const InventoryUnrecordedStock({
    required this.id,
    required this.code,
    required this.name,
    required this.unit,
  });
  factory InventoryUnrecordedStock.fromJson(Map<String, dynamic> json) =>
      InventoryUnrecordedStock(
        id: _int(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        unit: json['satuan'] as String? ?? 'unit',
      );
  final int id;
  final String code;
  final String name;
  final String unit;
}

class InventoryStockRecap {
  const InventoryStockRecap({
    required this.id,
    required this.goods,
    required this.location,
    required this.opening,
    required this.incoming,
    required this.outgoing,
    required this.adjustment,
    required this.closing,
    required this.movementCount,
    required this.status,
    required this.statusLabel,
  });
  factory InventoryStockRecap.fromJson(Map<String, dynamic> json) =>
      InventoryStockRecap(
        id: _int(json['id']),
        goods: InventoryReportGoods.fromJson(_map(json['barang'])),
        location: InventoryReportSimpleLocation.fromJson(_map(json['lokasi'])),
        opening: _double(json['saldo_awal']),
        incoming: _double(json['stok_masuk']),
        outgoing: _double(json['stok_keluar']),
        adjustment: _double(json['penyesuaian']),
        closing: _double(json['saldo_akhir']),
        movementCount: _int(json['jumlah_mutasi']),
        status: json['status'] as String? ?? 'aman',
        statusLabel: json['status_label'] as String? ?? 'Aman',
      );
  final int id;
  final InventoryReportGoods goods;
  final InventoryReportSimpleLocation location;
  final double opening;
  final double incoming;
  final double outgoing;
  final double adjustment;
  final double closing;
  final int movementCount;
  final String status;
  final String statusLabel;
}

class InventoryReportGoods {
  const InventoryReportGoods({
    required this.id,
    required this.code,
    required this.name,
    required this.category,
    required this.unit,
  });
  factory InventoryReportGoods.fromJson(Map<String, dynamic> json) =>
      InventoryReportGoods(
        id: _int(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        category: json['kategori'] as String? ?? '-',
        unit: json['satuan'] as String? ?? 'unit',
      );
  final int id;
  final String code;
  final String name;
  final String category;
  final String unit;
}

class InventoryReportSimpleLocation {
  const InventoryReportSimpleLocation({required this.id, required this.name});
  factory InventoryReportSimpleLocation.fromJson(Map<String, dynamic> json) =>
      InventoryReportSimpleLocation(
        id: _int(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class InventoryUnitDistribution {
  const InventoryUnitDistribution({
    required this.code,
    required this.label,
    required this.count,
  });
  factory InventoryUnitDistribution.fromJson(Map<String, dynamic> json) =>
      InventoryUnitDistribution(
        code: json['kode'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
        count: _int(json['jumlah']),
      );
  final String code;
  final String label;
  final int count;
}

class InventoryUnitAttention {
  const InventoryUnitAttention({
    required this.id,
    required this.inventoryCode,
    required this.goods,
    required this.location,
    required this.condition,
    required this.conditionLabel,
    required this.status,
    required this.statusLabel,
  });
  factory InventoryUnitAttention.fromJson(Map<String, dynamic> json) =>
      InventoryUnitAttention(
        id: _int(json['id']),
        inventoryCode: json['kode_inventaris'] as String? ?? '-',
        goods: json['barang'] as String? ?? '-',
        location: json['lokasi'] as String? ?? '-',
        condition: json['kondisi'] as String? ?? '-',
        conditionLabel: json['kondisi_label'] as String? ?? '-',
        status: json['status'] as String? ?? '-',
        statusLabel: json['status_label'] as String? ?? '-',
      );
  final int id;
  final String inventoryCode;
  final String goods;
  final String location;
  final String condition;
  final String conditionLabel;
  final String status;
  final String statusLabel;
}

class InventoryEmployeeService {
  const InventoryEmployeeService({
    required this.id,
    required this.number,
    required this.employee,
    required this.dateLabel,
    required this.status,
    required this.statusLabel,
    required this.source,
    required this.items,
    this.date,
    this.plannedReturn,
    this.plannedReturnLabel,
  });
  factory InventoryEmployeeService.fromJson(Map<String, dynamic> json) =>
      InventoryEmployeeService(
        id: _int(json['id']),
        number: json['nomor'] as String? ?? '-',
        employee: InventoryReportEmployee.fromJson(_map(json['pegawai'])),
        date: json['tanggal'] as String?,
        dateLabel: json['tanggal_label'] as String? ?? '-',
        plannedReturn: json['rencana_kembali'] as String?,
        plannedReturnLabel: json['rencana_kembali_label'] as String?,
        status: json['status'] as String? ?? '-',
        statusLabel: json['status_label'] as String? ?? '-',
        source: json['sumber'] as String? ?? 'Dicatat petugas',
        items: _list(json['items'], InventoryEmployeeServiceItem.fromJson),
      );
  final int id;
  final String number;
  final InventoryReportEmployee employee;
  final String? date;
  final String dateLabel;
  final String? plannedReturn;
  final String? plannedReturnLabel;
  final String status;
  final String statusLabel;
  final String source;
  final List<InventoryEmployeeServiceItem> items;
}

class InventoryReportEmployee {
  const InventoryReportEmployee({
    required this.name,
    required this.type,
    this.id,
    this.nip,
  });
  factory InventoryReportEmployee.fromJson(Map<String, dynamic> json) =>
      InventoryReportEmployee(
        id: _nullableInt(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        type: json['jenis'] as String? ?? 'Pegawai',
      );
  final int? id;
  final String name;
  final String? nip;
  final String type;
}

class InventoryEmployeeServiceItem {
  const InventoryEmployeeServiceItem({
    required this.goods,
    required this.quantity,
    required this.unit,
    required this.location,
    required this.mustReturn,
    this.inventoryCode,
  });
  factory InventoryEmployeeServiceItem.fromJson(Map<String, dynamic> json) =>
      InventoryEmployeeServiceItem(
        goods: json['barang'] as String? ?? '-',
        quantity: _double(json['jumlah']),
        unit: json['satuan'] as String? ?? 'unit',
        inventoryCode: json['kode_inventaris'] as String?,
        location: json['lokasi'] as String? ?? '-',
        mustReturn: json['wajib_dikembalikan'] as bool? ?? false,
      );
  final String goods;
  final double quantity;
  final String unit;
  final String? inventoryCode;
  final String location;
  final bool mustReturn;
}

class InventoryReportMovement {
  const InventoryReportMovement({
    required this.id,
    required this.dateLabel,
    required this.goods,
    required this.location,
    required this.type,
    required this.typeLabel,
    required this.category,
    required this.categoryLabel,
    required this.change,
    required this.closing,
    required this.unit,
    this.date,
    this.reference,
  });
  factory InventoryReportMovement.fromJson(Map<String, dynamic> json) =>
      InventoryReportMovement(
        id: _int(json['id']),
        date: json['tanggal'] as String?,
        dateLabel: json['tanggal_label'] as String? ?? '-',
        goods: json['barang'] as String? ?? '-',
        location: json['lokasi'] as String? ?? '-',
        type: json['jenis'] as String? ?? '-',
        typeLabel: json['jenis_label'] as String? ?? '-',
        category: json['kategori'] as String? ?? '-',
        categoryLabel: json['kategori_label'] as String? ?? '-',
        change: _double(json['jumlah_perubahan']),
        closing: _double(json['saldo_akhir']),
        unit: json['satuan'] as String? ?? 'unit',
        reference: json['referensi'] as String?,
      );
  final int id;
  final String? date;
  final String dateLabel;
  final String goods;
  final String location;
  final String type;
  final String typeLabel;
  final String category;
  final String categoryLabel;
  final double change;
  final double closing;
  final String unit;
  final String? reference;
}

String inventoryReportNumber(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value
          .toStringAsFixed(2)
          .replaceFirst(RegExp(r'0+$'), '')
          .replaceFirst(RegExp(r'\.$'), '');

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
