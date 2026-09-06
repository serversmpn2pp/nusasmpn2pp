class FacilityDashboard {
  const FacilityDashboard({
    required this.date,
    required this.dateLabel,
    required this.access,
    required this.tools,
    required this.summary,
    required this.stockAttention,
    required this.unrecordedStock,
    required this.overdueLoans,
    required this.unitDistribution,
    required this.unitAttention,
    required this.recentActivities,
  });

  factory FacilityDashboard.fromJson(Map<String, dynamic> json) =>
      FacilityDashboard(
        date: json['tanggal'] as String? ?? '',
        dateLabel: json['tanggal_label'] as String? ?? '-',
        access: FacilityAccess.fromJson(_map(json['hak_akses'])),
        tools: _list(json['menu'], FacilityTool.fromJson),
        summary: FacilitySummary.fromJson(_map(json['ringkasan'])),
        stockAttention: _list(
          json['stok_perlu_perhatian'],
          FacilityStock.fromJson,
        ),
        unrecordedStock: _list(
          json['stok_belum_dicatat'],
          FacilityUnrecordedStock.fromJson,
        ),
        overdueLoans: _list(
          json['peminjaman_terlambat'],
          FacilityOverdueLoan.fromJson,
        ),
        unitDistribution: _list(
          json['distribusi_status_unit'],
          FacilityUnitDistribution.fromJson,
        ),
        unitAttention: _list(
          json['unit_perlu_perhatian'],
          FacilityUnitAttention.fromJson,
        ),
        recentActivities: _list(
          json['aktivitas_terbaru'],
          FacilityActivity.fromJson,
        ),
      );

  final String date;
  final String dateLabel;
  final FacilityAccess access;
  final List<FacilityTool> tools;
  final FacilitySummary summary;
  final List<FacilityStock> stockAttention;
  final List<FacilityUnrecordedStock> unrecordedStock;
  final List<FacilityOverdueLoan> overdueLoans;
  final List<FacilityUnitDistribution> unitDistribution;
  final List<FacilityUnitAttention> unitAttention;
  final List<FacilityActivity> recentActivities;
}

class FacilityTool {
  const FacilityTool({
    required this.code,
    required this.label,
    required this.description,
    required this.initials,
    required this.status,
    this.subgroup,
    this.route,
  });

  factory FacilityTool.fromJson(Map<String, dynamic> json) => FacilityTool(
    code: json['kode'] as String? ?? '',
    label: json['label'] as String? ?? '-',
    description: json['deskripsi'] as String? ?? '',
    initials: json['inisial'] as String? ?? '',
    subgroup: json['subkelompok'] as String?,
    status: json['status'] as String? ?? 'segera_hadir',
    route: json['rute'] as String?,
  );

  final String code;
  final String label;
  final String description;
  final String initials;
  final String? subgroup;
  final String status;
  final String? route;

  bool get isAvailable => status == 'tersedia' && route != null;
}

class FacilityAccess {
  const FacilityAccess({
    required this.canViewGoods,
    required this.canManageGoods,
    required this.canManageLoans,
  });

  factory FacilityAccess.fromJson(Map<String, dynamic> json) => FacilityAccess(
    canViewGoods: json['dapat_melihat_barang'] as bool? ?? false,
    canManageGoods: json['dapat_mengelola_barang'] as bool? ?? false,
    canManageLoans: json['dapat_mengelola_peminjaman'] as bool? ?? false,
  );

  final bool canViewGoods;
  final bool canManageGoods;
  final bool canManageLoans;
}

class FacilitySummary {
  const FacilitySummary({
    required this.goodsTypes,
    required this.assetUnits,
    required this.availableUnits,
    required this.activeLoans,
    required this.overdueLoans,
    required this.dueSoon,
    required this.lowStock,
    required this.outOfStock,
    required this.unitsNeedingAttention,
    required this.unrecordedStock,
  });

  factory FacilitySummary.fromJson(Map<String, dynamic> json) =>
      FacilitySummary(
        goodsTypes: _integer(json['jenis_barang']),
        assetUnits: _integer(json['unit_aset']),
        availableUnits: _integer(json['unit_tersedia']),
        activeLoans: _integer(json['peminjaman_aktif']),
        overdueLoans: _integer(json['peminjaman_terlambat']),
        dueSoon: _integer(json['jatuh_tempo']),
        lowStock: _integer(json['stok_menipis']),
        outOfStock: _integer(json['stok_habis']),
        unitsNeedingAttention: _integer(json['unit_perlu_perhatian']),
        unrecordedStock: _integer(json['stok_belum_dicatat']),
      );

  final int goodsTypes;
  final int assetUnits;
  final int availableUnits;
  final int activeLoans;
  final int overdueLoans;
  final int dueSoon;
  final int lowStock;
  final int outOfStock;
  final int unitsNeedingAttention;
  final int unrecordedStock;
}

class FacilityStock {
  const FacilityStock({
    required this.id,
    required this.name,
    required this.location,
    required this.amount,
    required this.minimum,
    required this.unit,
    required this.status,
    this.code,
  });

  factory FacilityStock.fromJson(Map<String, dynamic> json) => FacilityStock(
    id: _integer(json['id']),
    code: json['kode'] as String?,
    name: json['nama'] as String? ?? '-',
    location: json['lokasi'] as String? ?? '-',
    amount: _decimal(json['jumlah']),
    minimum: _decimal(json['stok_minimum']),
    unit: json['satuan'] as String? ?? 'unit',
    status: json['status'] as String? ?? 'menipis',
  );

  final int id;
  final String? code;
  final String name;
  final String location;
  final double amount;
  final double minimum;
  final String unit;
  final String status;
}

class FacilityUnrecordedStock {
  const FacilityUnrecordedStock({
    required this.id,
    required this.name,
    required this.unit,
    this.code,
  });

  factory FacilityUnrecordedStock.fromJson(Map<String, dynamic> json) =>
      FacilityUnrecordedStock(
        id: _integer(json['id']),
        code: json['kode'] as String?,
        name: json['nama'] as String? ?? '-',
        unit: json['satuan'] as String? ?? 'unit',
      );

  final int id;
  final String? code;
  final String name;
  final String unit;
}

class FacilityOverdueLoan {
  const FacilityOverdueLoan({
    required this.id,
    required this.number,
    required this.borrower,
    required this.identity,
    required this.overdueDays,
    required this.items,
    this.dueDate,
    this.dueDateLabel,
  });

  factory FacilityOverdueLoan.fromJson(Map<String, dynamic> json) =>
      FacilityOverdueLoan(
        id: _integer(json['id']),
        number: json['nomor'] as String? ?? '-',
        borrower: json['peminjam'] as String? ?? '-',
        identity: json['identitas'] as String? ?? '-',
        dueDate: json['rencana_kembali'] as String?,
        dueDateLabel: json['rencana_kembali_label'] as String?,
        overdueDays: _integer(json['terlambat_hari']),
        items: json['barang'] is List
            ? (json['barang'] as List).whereType<String>().toList()
            : const <String>[],
      );

  final int id;
  final String number;
  final String borrower;
  final String identity;
  final String? dueDate;
  final String? dueDateLabel;
  final int overdueDays;
  final List<String> items;
}

class FacilityUnitDistribution {
  const FacilityUnitDistribution({
    required this.code,
    required this.label,
    required this.count,
    required this.color,
  });

  factory FacilityUnitDistribution.fromJson(Map<String, dynamic> json) =>
      FacilityUnitDistribution(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
        count: _integer(json['jumlah']),
        color: json['warna'] as String? ?? '#94A3B8',
      );

  final String code;
  final String label;
  final int count;
  final String color;
}

class FacilityUnitAttention {
  const FacilityUnitAttention({
    required this.id,
    required this.goods,
    required this.inventoryCode,
    required this.location,
    required this.status,
    required this.statusLabel,
    required this.condition,
    required this.conditionLabel,
    required this.tone,
  });

  factory FacilityUnitAttention.fromJson(Map<String, dynamic> json) =>
      FacilityUnitAttention(
        id: _integer(json['id']),
        goods: json['barang'] as String? ?? '-',
        inventoryCode: json['kode_inventaris'] as String? ?? '-',
        location: json['lokasi'] as String? ?? '-',
        status: json['status'] as String? ?? '',
        statusLabel: json['status_label'] as String? ?? '-',
        condition: json['kondisi'] as String? ?? '',
        conditionLabel: json['kondisi_label'] as String? ?? '-',
        tone: json['nada'] as String? ?? 'peringatan',
      );

  final int id;
  final String goods;
  final String inventoryCode;
  final String location;
  final String status;
  final String statusLabel;
  final String condition;
  final String conditionLabel;
  final String tone;
}

class FacilityActivity {
  const FacilityActivity({
    required this.type,
    required this.title,
    required this.description,
    required this.tone,
    this.time,
  });

  factory FacilityActivity.fromJson(Map<String, dynamic> json) =>
      FacilityActivity(
        type: json['jenis'] as String? ?? '-',
        title: json['judul'] as String? ?? '-',
        description: json['keterangan'] as String? ?? '',
        time: json['waktu'] is String
            ? DateTime.tryParse(json['waktu'] as String)?.toLocal()
            : null,
        tone: json['nada'] as String? ?? 'netral',
      );

  final String type;
  final String title;
  final String description;
  final DateTime? time;
  final String tone;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value.whereType<Map<String, dynamic>>().map(convert).toList()
    : <T>[];

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

double _decimal(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? 0,
  _ => 0,
};
