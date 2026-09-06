class GoodsLoanPage {
  const GoodsLoanPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.borrowerTypes,
    required this.statuses,
    required this.students,
    required this.employees,
    required this.availableItems,
    required this.query,
    required this.borrowerType,
    required this.status,
    this.startDate,
    this.endDate,
  });

  factory GoodsLoanPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final options = _map(json['pilihan']);
    return GoodsLoanPage(
      items: _list(json['items'], GoodsLoan.fromJson),
      summary: GoodsLoanSummary.fromJson(_map(json['ringkasan'])),
      access: GoodsLoanAccess.fromJson(_map(json['hak_akses'])),
      pagination: GoodsLoanPagination.fromJson(_map(json['paginasi'])),
      borrowerTypes: _list(
        options['jenis_peminjam'],
        GoodsLoanValueOption.fromJson,
      ),
      statuses: _list(options['status'], GoodsLoanValueOption.fromJson),
      students: _list(options['siswa'], GoodsLoanPersonOption.fromJson),
      employees: _list(options['pegawai'], GoodsLoanPersonOption.fromJson),
      availableItems: _list(options['barang'], GoodsLoanAvailableItem.fromJson),
      query: filter['cari'] as String? ?? '',
      borrowerType: filter['jenis_peminjam'] as String? ?? 'semua',
      status: filter['status'] as String? ?? 'semua',
      startDate: _date(filter['tanggal_mulai']),
      endDate: _date(filter['tanggal_selesai']),
    );
  }

  final List<GoodsLoan> items;
  final GoodsLoanSummary summary;
  final GoodsLoanAccess access;
  final GoodsLoanPagination pagination;
  final List<GoodsLoanValueOption> borrowerTypes;
  final List<GoodsLoanValueOption> statuses;
  final List<GoodsLoanPersonOption> students;
  final List<GoodsLoanPersonOption> employees;
  final List<GoodsLoanAvailableItem> availableItems;
  final String query;
  final String borrowerType;
  final String status;
  final DateTime? startDate;
  final DateTime? endDate;

  GoodsLoanPage append(GoodsLoanPage next) => GoodsLoanPage(
    items: [...items, ...next.items],
    summary: next.summary,
    access: next.access,
    pagination: next.pagination,
    borrowerTypes: next.borrowerTypes,
    statuses: next.statuses,
    students: next.students,
    employees: next.employees,
    availableItems: next.availableItems,
    query: next.query,
    borrowerType: next.borrowerType,
    status: next.status,
    startDate: next.startDate,
    endDate: next.endDate,
  );
}

class GoodsReturnPage {
  const GoodsReturnPage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.query,
  });

  factory GoodsReturnPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return GoodsReturnPage(
      items: _list(json['items'], GoodsLoan.fromJson),
      summary: GoodsReturnSummary.fromJson(_map(json['ringkasan'])),
      pagination: GoodsLoanPagination.fromJson(_map(json['paginasi'])),
      query: filter['cari'] as String? ?? '',
    );
  }

  final List<GoodsLoan> items;
  final GoodsReturnSummary summary;
  final GoodsLoanPagination pagination;
  final String query;

  GoodsReturnPage append(GoodsReturnPage next) => GoodsReturnPage(
    items: [...items, ...next.items],
    summary: next.summary,
    pagination: next.pagination,
    query: next.query,
  );
}

class GoodsLoanDetailResponse {
  const GoodsLoanDetailResponse({
    required this.loan,
    required this.access,
    required this.conditions,
  });

  factory GoodsLoanDetailResponse.fromJson(Map<String, dynamic> json) {
    final options = _map(json['pilihan']);
    return GoodsLoanDetailResponse(
      loan: GoodsLoan.fromJson(_map(json['peminjaman'])),
      access: GoodsLoanAccess.fromJson(_map(json['hak_akses'])),
      conditions: _list(options['kondisi'], GoodsLoanValueOption.fromJson),
    );
  }

  final GoodsLoan loan;
  final GoodsLoanAccess access;
  final List<GoodsLoanValueOption> conditions;
}

class GoodsLoan {
  const GoodsLoan({
    required this.id,
    required this.number,
    required this.borrowerType,
    required this.borrowerTypeLabel,
    required this.borrowerName,
    required this.borrowerIdentity,
    required this.dateLabel,
    required this.status,
    required this.statusLabel,
    required this.monitoringLabel,
    required this.overdue,
    required this.overdueDays,
    required this.itemCount,
    required this.items,
    required this.returns,
    this.date,
    this.plannedReturn,
    this.plannedReturnLabel,
    this.notes,
    this.createdBy,
    this.outstandingItemCount,
  });

  factory GoodsLoan.fromJson(Map<String, dynamic> json) => GoodsLoan(
    id: _int(json['id']),
    number: json['nomor'] as String? ?? '-',
    borrowerType: json['jenis_peminjam'] as String? ?? '-',
    borrowerTypeLabel: json['jenis_peminjam_label'] as String? ?? '-',
    borrowerName: json['nama_peminjam'] as String? ?? '-',
    borrowerIdentity: json['identitas_peminjam'] as String? ?? '-',
    date: _date(json['tanggal']),
    dateLabel: json['tanggal_label'] as String? ?? '-',
    plannedReturn: _date(json['rencana_kembali']),
    plannedReturnLabel: json['rencana_kembali_label'] as String?,
    status: json['status'] as String? ?? '-',
    statusLabel: json['status_label'] as String? ?? '-',
    monitoringLabel: json['pemantauan_label'] as String? ?? '-',
    overdue: json['terlambat'] as bool? ?? false,
    overdueDays: _int(json['hari_terlambat']),
    itemCount: _int(json['jumlah_item']),
    outstandingItemCount: _nullableInt(json['items_belum_kembali']),
    notes: json['catatan'] as String?,
    createdBy: json['dibuat_oleh'] as String?,
    items: _list(json['items'], GoodsLoanItem.fromJson),
    returns: _list(json['pengembalian'], GoodsReturnHistory.fromJson),
  );

  final int id;
  final String number;
  final String borrowerType;
  final String borrowerTypeLabel;
  final String borrowerName;
  final String borrowerIdentity;
  final DateTime? date;
  final String dateLabel;
  final DateTime? plannedReturn;
  final String? plannedReturnLabel;
  final String status;
  final String statusLabel;
  final String monitoringLabel;
  final bool overdue;
  final int overdueDays;
  final int itemCount;
  final int? outstandingItemCount;
  final String? notes;
  final String? createdBy;
  final List<GoodsLoanItem> items;
  final List<GoodsReturnHistory> returns;
  bool get active => status == 'dipinjam' || status == 'sebagian_dikembalikan';
}

class GoodsLoanItem {
  const GoodsLoanItem({
    required this.id,
    required this.goodsId,
    required this.goodsName,
    required this.code,
    required this.location,
    required this.managementType,
    required this.quantity,
    required this.returned,
    required this.remaining,
    required this.mustReturn,
    required this.unit,
    required this.inputMethod,
    this.assetUnitId,
    this.notes,
  });

  factory GoodsLoanItem.fromJson(Map<String, dynamic> json) => GoodsLoanItem(
    id: _int(json['id']),
    goodsId: _int(json['barang_id']),
    goodsName: json['nama_barang'] as String? ?? '-',
    code: json['kode'] as String? ?? '-',
    assetUnitId: _nullableInt(json['unit_barang_id']),
    location: json['lokasi'] as String? ?? '-',
    managementType: json['tipe_pengelolaan'] as String? ?? '-',
    quantity: _double(json['jumlah']),
    returned: _double(json['jumlah_dikembalikan']),
    remaining: _double(json['jumlah_belum_dikembalikan']),
    mustReturn: json['wajib_dikembalikan'] as bool? ?? false,
    unit: json['satuan'] as String? ?? 'unit',
    inputMethod: json['cara_input'] as String? ?? 'manual',
    notes: json['catatan'] as String?,
  );

  final int id;
  final int goodsId;
  final String goodsName;
  final String code;
  final int? assetUnitId;
  final String location;
  final String managementType;
  final double quantity;
  final double returned;
  final double remaining;
  final bool mustReturn;
  final String unit;
  final String inputMethod;
  final String? notes;
}

class GoodsReturnHistory {
  const GoodsReturnHistory({
    required this.id,
    required this.number,
    required this.dateLabel,
    required this.createdBy,
    required this.items,
    this.date,
    this.notes,
  });

  factory GoodsReturnHistory.fromJson(Map<String, dynamic> json) =>
      GoodsReturnHistory(
        id: _int(json['id']),
        number: json['nomor'] as String? ?? '-',
        date: _date(json['tanggal']),
        dateLabel: json['tanggal_label'] as String? ?? '-',
        notes: json['catatan'] as String?,
        createdBy: json['dibuat_oleh'] as String? ?? 'Sistem',
        items: _list(json['items'], GoodsReturnedItem.fromJson),
      );

  final int id;
  final String number;
  final DateTime? date;
  final String dateLabel;
  final String? notes;
  final String createdBy;
  final List<GoodsReturnedItem> items;
}

class GoodsReturnedItem {
  const GoodsReturnedItem({
    required this.id,
    required this.goodsName,
    required this.quantity,
    required this.unit,
    required this.inputMethod,
    this.condition,
    this.conditionLabel,
    this.notes,
  });

  factory GoodsReturnedItem.fromJson(Map<String, dynamic> json) =>
      GoodsReturnedItem(
        id: _int(json['id']),
        goodsName: json['nama_barang'] as String? ?? '-',
        quantity: _double(json['jumlah']),
        unit: json['satuan'] as String? ?? 'unit',
        condition: json['kondisi'] as String?,
        conditionLabel: json['kondisi_label'] as String?,
        inputMethod: json['cara_input'] as String? ?? 'manual',
        notes: json['catatan'] as String?,
      );

  final int id;
  final String goodsName;
  final double quantity;
  final String unit;
  final String? condition;
  final String? conditionLabel;
  final String inputMethod;
  final String? notes;
}

class GoodsLoanAvailableItem {
  const GoodsLoanAvailableItem({
    required this.keyValue,
    required this.type,
    required this.goodsId,
    required this.code,
    required this.label,
    required this.description,
    required this.displayType,
    required this.mustReturn,
    required this.unit,
    required this.balance,
    this.assetUnitId,
    this.locationId,
  });

  factory GoodsLoanAvailableItem.fromJson(Map<String, dynamic> json) =>
      GoodsLoanAvailableItem(
        keyValue: json['kunci'] as String? ?? '',
        type: json['tipe_item'] as String? ?? 'stok',
        assetUnitId: _nullableInt(json['unit_barang_id']),
        goodsId: _int(json['barang_id']),
        locationId: _nullableInt(json['lokasi_barang_id']),
        code: json['kode'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
        description: json['keterangan'] as String? ?? '-',
        displayType: json['jenis_tampilan'] as String? ?? '-',
        mustReturn: json['wajib_dikembalikan'] as bool? ?? false,
        unit: json['satuan'] as String? ?? 'unit',
        balance: _double(json['saldo']),
      );

  final String keyValue;
  final String type;
  final int? assetUnitId;
  final int goodsId;
  final int? locationId;
  final String code;
  final String label;
  final String description;
  final String displayType;
  final bool mustReturn;
  final String unit;
  final double balance;
}

class GoodsLoanPersonOption {
  const GoodsLoanPersonOption({required this.id, required this.label});
  factory GoodsLoanPersonOption.fromJson(Map<String, dynamic> json) =>
      GoodsLoanPersonOption(
        id: _int(json['id']),
        label: json['label'] as String? ?? '-',
      );
  final int id;
  final String label;
}

class IdentifiedBorrower {
  const IdentifiedBorrower({
    required this.type,
    required this.id,
    required this.name,
    required this.identity,
    required this.information,
  });
  factory IdentifiedBorrower.fromJson(Map<String, dynamic> json) =>
      IdentifiedBorrower(
        type: json['jenis_peminjam'] as String? ?? '-',
        id: _int(json['id']),
        name: json['nama'] as String? ?? '-',
        identity: json['identitas'] as String? ?? '-',
        information: json['informasi'] as String? ?? '-',
      );
  final String type;
  final int id;
  final String name;
  final String identity;
  final String information;
}

class IdentifiedReturn {
  const IdentifiedReturn({
    required this.loanId,
    required this.detailId,
    required this.code,
    required this.goodsName,
    required this.borrowerName,
    required this.borrowerIdentity,
    required this.loanNumber,
    required this.location,
    required this.condition,
    required this.loanDate,
    required this.plannedReturn,
  });
  factory IdentifiedReturn.fromJson(Map<String, dynamic> json) =>
      IdentifiedReturn(
        loanId: _int(json['peminjaman_id']),
        detailId: _int(json['detail_id']),
        code: json['kode'] as String? ?? '-',
        goodsName: json['nama_barang'] as String? ?? '-',
        borrowerName: json['nama_peminjam'] as String? ?? '-',
        borrowerIdentity: json['identitas_peminjam'] as String? ?? '-',
        loanNumber: json['nomor_peminjaman'] as String? ?? '-',
        location: json['lokasi_asal'] as String? ?? '-',
        condition: json['kondisi_tercatat'] as String? ?? '-',
        loanDate: json['tanggal_peminjaman'] as String? ?? '-',
        plannedReturn: json['rencana_kembali'] as String? ?? '-',
      );
  final int loanId;
  final int detailId;
  final String code;
  final String goodsName;
  final String borrowerName;
  final String borrowerIdentity;
  final String loanNumber;
  final String location;
  final String condition;
  final String loanDate;
  final String plannedReturn;
}

class GoodsLoanSummary {
  const GoodsLoanSummary({
    required this.total,
    required this.active,
    required this.finished,
    required this.today,
  });
  factory GoodsLoanSummary.fromJson(Map<String, dynamic> json) =>
      GoodsLoanSummary(
        total: _int(json['total']),
        active: _int(json['aktif']),
        finished: _int(json['selesai']),
        today: _int(json['hari_ini']),
      );
  final int total;
  final int active;
  final int finished;
  final int today;
}

class GoodsReturnSummary {
  const GoodsReturnSummary({
    required this.active,
    required this.overdue,
    required this.partial,
    required this.dueSoon,
  });
  factory GoodsReturnSummary.fromJson(Map<String, dynamic> json) =>
      GoodsReturnSummary(
        active: _int(json['aktif']),
        overdue: _int(json['terlambat']),
        partial: _int(json['sebagian']),
        dueSoon: _int(json['jatuh_tempo']),
      );
  final int active;
  final int overdue;
  final int partial;
  final int dueSoon;
}

class GoodsLoanAccess {
  const GoodsLoanAccess({required this.canManage, this.canReturn = false});
  factory GoodsLoanAccess.fromJson(Map<String, dynamic> json) =>
      GoodsLoanAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
        canReturn: json['dapat_mengembalikan'] as bool? ?? false,
      );
  final bool canManage;
  final bool canReturn;
}

class GoodsLoanPagination {
  const GoodsLoanPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });
  factory GoodsLoanPagination.fromJson(Map<String, dynamic> json) =>
      GoodsLoanPagination(
        page: _int(json['halaman']),
        total: _int(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int total;
  final bool hasNextPage;
}

class GoodsLoanValueOption {
  const GoodsLoanValueOption({required this.value, required this.label});
  factory GoodsLoanValueOption.fromJson(Map<String, dynamic> json) =>
      GoodsLoanValueOption(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String value;
  final String label;
}

class GoodsLoanFormValue {
  const GoodsLoanFormValue({
    required this.borrowerType,
    required this.borrowerId,
    required this.borrowerInputMethod,
    required this.date,
    required this.lines,
    this.plannedReturn,
    this.notes,
  });
  final String borrowerType;
  final int borrowerId;
  final String borrowerInputMethod;
  final DateTime date;
  final DateTime? plannedReturn;
  final String? notes;
  final List<GoodsLoanLineValue> lines;
}

class GoodsLoanLineValue {
  const GoodsLoanLineValue({
    required this.item,
    required this.quantity,
    required this.inputMethod,
    this.notes,
  });
  final GoodsLoanAvailableItem item;
  final double quantity;
  final String inputMethod;
  final String? notes;
}

class GoodsReturnFormValue {
  const GoodsReturnFormValue({
    required this.date,
    required this.lines,
    this.notes,
  });
  final DateTime date;
  final String? notes;
  final List<GoodsReturnLineValue> lines;
}

class GoodsReturnLineValue {
  const GoodsReturnLineValue({
    required this.detailId,
    required this.quantity,
    required this.inputMethod,
    this.condition,
    this.notes,
  });
  final int detailId;
  final double quantity;
  final String? condition;
  final String inputMethod;
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
