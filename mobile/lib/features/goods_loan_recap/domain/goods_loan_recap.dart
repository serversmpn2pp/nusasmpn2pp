import 'package:nusa/features/goods_loan/domain/goods_loan.dart';

class GoodsLoanRecapPage {
  const GoodsLoanRecapPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.monitoringStatuses,
    required this.borrowerTypes,
    required this.borrowers,
    required this.goods,
    required this.filter,
    required this.overdueReport,
    this.printedAt,
  });

  factory GoodsLoanRecapPage.fromJson(Map<String, dynamic> json) {
    final options = _map(json['pilihan']);
    return GoodsLoanRecapPage(
      items: _list(json['items'], GoodsLoan.fromJson),
      summary: GoodsLoanRecapSummary.fromJson(_map(json['ringkasan'])),
      access: GoodsLoanRecapAccess.fromJson(_map(json['hak_akses'])),
      pagination: GoodsLoanPagination.fromJson(_map(json['paginasi'])),
      monitoringStatuses: _list(
        options['status_pemantauan'],
        GoodsLoanValueOption.fromJson,
      ),
      borrowerTypes: _list(
        options['jenis_peminjam'],
        GoodsLoanValueOption.fromJson,
      ),
      borrowers: _list(options['peminjam'], GoodsLoanRecapBorrower.fromJson),
      goods: _list(options['barang'], GoodsLoanRecapGoods.fromJson),
      filter: GoodsLoanRecapFilter.fromJson(_map(json['filter'])),
      overdueReport: GoodsLoanOverdueReport.fromJson(
        _map(json['daftar_terlambat']),
      ),
      printedAt: json['dicetak_pada'] as String?,
    );
  }

  final List<GoodsLoan> items;
  final GoodsLoanRecapSummary summary;
  final GoodsLoanRecapAccess access;
  final GoodsLoanPagination pagination;
  final List<GoodsLoanValueOption> monitoringStatuses;
  final List<GoodsLoanValueOption> borrowerTypes;
  final List<GoodsLoanRecapBorrower> borrowers;
  final List<GoodsLoanRecapGoods> goods;
  final GoodsLoanRecapFilter filter;
  final GoodsLoanOverdueReport overdueReport;
  final String? printedAt;

  GoodsLoanRecapPage append(GoodsLoanRecapPage next) => GoodsLoanRecapPage(
    items: [...items, ...next.items],
    summary: next.summary,
    access: next.access,
    pagination: next.pagination,
    monitoringStatuses: next.monitoringStatuses,
    borrowerTypes: next.borrowerTypes,
    borrowers: next.borrowers,
    goods: next.goods,
    filter: next.filter,
    overdueReport: next.overdueReport,
    printedAt: next.printedAt,
  );
}

class GoodsLoanRecapSummary {
  const GoodsLoanRecapSummary({
    required this.active,
    required this.overdue,
    required this.dueSoon,
    required this.withoutPlan,
  });
  factory GoodsLoanRecapSummary.fromJson(Map<String, dynamic> json) =>
      GoodsLoanRecapSummary(
        active: _int(json['aktif']),
        overdue: _int(json['terlambat']),
        dueSoon: _int(json['jatuh_tempo']),
        withoutPlan: _int(json['tanpa_rencana']),
      );
  final int active;
  final int overdue;
  final int dueSoon;
  final int withoutPlan;
}

class GoodsLoanRecapAccess {
  const GoodsLoanRecapAccess({required this.canReturn});
  factory GoodsLoanRecapAccess.fromJson(Map<String, dynamic> json) =>
      GoodsLoanRecapAccess(
        canReturn: json['dapat_mengembalikan'] as bool? ?? false,
      );
  final bool canReturn;
}

class GoodsLoanRecapBorrower {
  const GoodsLoanRecapBorrower({
    required this.value,
    required this.label,
    required this.type,
  });
  factory GoodsLoanRecapBorrower.fromJson(Map<String, dynamic> json) =>
      GoodsLoanRecapBorrower(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
        type: json['jenis'] as String? ?? '-',
      );
  final String value;
  final String label;
  final String type;
}

class GoodsLoanRecapGoods {
  const GoodsLoanRecapGoods({
    required this.id,
    required this.code,
    required this.name,
    required this.label,
  });
  factory GoodsLoanRecapGoods.fromJson(Map<String, dynamic> json) =>
      GoodsLoanRecapGoods(
        id: _int(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
      );
  final int id;
  final String code;
  final String name;
  final String label;
}

class GoodsLoanOverdueReport {
  const GoodsLoanOverdueReport({required this.count, required this.text});
  factory GoodsLoanOverdueReport.fromJson(Map<String, dynamic> json) =>
      GoodsLoanOverdueReport(
        count: _int(json['jumlah']),
        text: json['teks'] as String? ?? '',
      );
  final int count;
  final String text;
}

class GoodsLoanRecapFilter {
  const GoodsLoanRecapFilter({
    this.query = '',
    this.monitoringStatus = 'aktif',
    this.borrowerType = 'semua',
    this.borrower = '',
    this.goodsId,
    this.startDate,
    this.endDate,
  });

  factory GoodsLoanRecapFilter.fromJson(Map<String, dynamic> json) =>
      GoodsLoanRecapFilter(
        query: json['kata_kunci'] as String? ?? '',
        monitoringStatus: json['status_pemantauan'] as String? ?? 'aktif',
        borrowerType: json['jenis_peminjam'] as String? ?? 'semua',
        borrower: json['peminjam'] as String? ?? '',
        goodsId: _nullableInt(json['barang_id']),
        startDate: _date(json['tanggal_mulai']),
        endDate: _date(json['tanggal_selesai']),
      );

  final String query;
  final String monitoringStatus;
  final String borrowerType;
  final String borrower;
  final int? goodsId;
  final DateTime? startDate;
  final DateTime? endDate;

  GoodsLoanRecapFilter copyWith({
    String? query,
    String? monitoringStatus,
    String? borrowerType,
    String? borrower,
    int? goodsId,
    bool clearGoods = false,
    DateTime? startDate,
    bool clearStart = false,
    DateTime? endDate,
    bool clearEnd = false,
  }) => GoodsLoanRecapFilter(
    query: query ?? this.query,
    monitoringStatus: monitoringStatus ?? this.monitoringStatus,
    borrowerType: borrowerType ?? this.borrowerType,
    borrower: borrower ?? this.borrower,
    goodsId: clearGoods ? null : (goodsId ?? this.goodsId),
    startDate: clearStart ? null : (startDate ?? this.startDate),
    endDate: clearEnd ? null : (endDate ?? this.endDate),
  );

  int get activeCount =>
      (monitoringStatus == 'aktif' ? 0 : 1) +
      (borrowerType == 'semua' ? 0 : 1) +
      (borrower.isEmpty ? 0 : 1) +
      (goodsId == null ? 0 : 1) +
      (startDate == null && endDate == null ? 0 : 1);
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) fromJson) =>
    value is List
    ? value.whereType<Map>().map((item) => fromJson(_map(item))).toList()
    : <T>[];
int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
int? _nullableInt(Object? value) => value == null ? null : _int(value);
DateTime? _date(Object? value) =>
    value == null || '$value'.isEmpty ? null : DateTime.tryParse('$value');
