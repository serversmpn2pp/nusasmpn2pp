class InventoryLocationPage {
  const InventoryLocationPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.pagination,
    required this.types,
    required this.employees,
    required this.query,
    required this.status,
    required this.type,
  });

  factory InventoryLocationPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final options = _map(json['pilihan']);
    return InventoryLocationPage(
      items: _list(json['items'], InventoryLocation.fromJson),
      summary: InventoryLocationSummary.fromJson(_map(json['ringkasan'])),
      access: InventoryLocationAccess.fromJson(_map(json['hak_akses'])),
      pagination: InventoryLocationPagination.fromJson(_map(json['paginasi'])),
      types: _list(options['jenis'], InventoryLocationType.fromJson),
      employees: _list(options['pegawai'], InventoryLocationEmployee.fromJson),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      type: filter['jenis'] as String? ?? 'semua',
    );
  }

  final List<InventoryLocation> items;
  final InventoryLocationSummary summary;
  final InventoryLocationAccess access;
  final InventoryLocationPagination pagination;
  final List<InventoryLocationType> types;
  final List<InventoryLocationEmployee> employees;
  final String query;
  final String status;
  final String type;

  InventoryLocationPage append(InventoryLocationPage next) =>
      InventoryLocationPage(
        items: [...items, ...next.items],
        summary: next.summary,
        access: next.access,
        pagination: next.pagination,
        types: next.types,
        employees: next.employees,
        query: next.query,
        status: next.status,
        type: next.type,
      );
}

class InventoryLocation {
  const InventoryLocation({
    required this.id,
    required this.name,
    required this.code,
    required this.type,
    required this.typeLabel,
    required this.active,
    required this.goodsCount,
    this.responsibleEmployee,
    this.description,
  });

  factory InventoryLocation.fromJson(Map<String, dynamic> json) =>
      InventoryLocation(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String? ?? '-',
        type: json['jenis'] as String? ?? 'lainnya',
        typeLabel: json['label_jenis'] as String? ?? 'Lainnya',
        responsibleEmployee: json['penanggung_jawab'] is Map
            ? InventoryLocationEmployee.fromJson(_map(json['penanggung_jawab']))
            : null,
        description: json['deskripsi'] as String?,
        active: json['aktif'] as bool? ?? false,
        goodsCount: _integer(json['jumlah_barang']),
      );

  final int id;
  final String name;
  final String code;
  final String type;
  final String typeLabel;
  final InventoryLocationEmployee? responsibleEmployee;
  final String? description;
  final bool active;
  final int goodsCount;
}

class InventoryLocationSummary {
  const InventoryLocationSummary({
    required this.total,
    required this.active,
    required this.withResponsibleEmployee,
  });

  factory InventoryLocationSummary.fromJson(Map<String, dynamic> json) =>
      InventoryLocationSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        withResponsibleEmployee: _integer(json['dengan_penanggung_jawab']),
      );

  final int total;
  final int active;
  final int withResponsibleEmployee;
}

class InventoryLocationAccess {
  const InventoryLocationAccess({required this.canManage});

  factory InventoryLocationAccess.fromJson(Map<String, dynamic> json) =>
      InventoryLocationAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
      );

  final bool canManage;
}

class InventoryLocationPagination {
  const InventoryLocationPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory InventoryLocationPagination.fromJson(Map<String, dynamic> json) =>
      InventoryLocationPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class InventoryLocationType {
  const InventoryLocationType({required this.value, required this.label});

  factory InventoryLocationType.fromJson(Map<String, dynamic> json) =>
      InventoryLocationType(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String value;
  final String label;
}

class InventoryLocationEmployee {
  const InventoryLocationEmployee({
    required this.id,
    required this.name,
    this.nip,
  });

  factory InventoryLocationEmployee.fromJson(Map<String, dynamic> json) =>
      InventoryLocationEmployee(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
      );

  final int id;
  final String name;
  final String? nip;

  String get optionLabel => nip?.isNotEmpty == true ? '$name · $nip' : name;
}

class InventoryLocationFormValue {
  const InventoryLocationFormValue({
    required this.name,
    required this.code,
    required this.type,
    required this.active,
    this.responsibleEmployeeId,
    this.description,
  });

  final String name;
  final String code;
  final String type;
  final int? responsibleEmployeeId;
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
