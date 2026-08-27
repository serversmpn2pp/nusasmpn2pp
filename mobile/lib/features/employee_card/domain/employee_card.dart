class EmployeeCardPage {
  const EmployeeCardPage({
    required this.items,
    required this.summary,
    required this.employeeTypes,
    required this.pagination,
    required this.query,
    required this.status,
    required this.employeeType,
    required this.cardSize,
    required this.canManagePhoto,
  });

  factory EmployeeCardPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final access = _map(json['hak_akses']);
    return EmployeeCardPage(
      items: _list(json['items'], EmployeeCardPerson.fromJson),
      summary: EmployeeCardSummary.fromJson(_map(json['ringkasan'])),
      employeeTypes:
          (json['pilihan_jenis_pegawai'] as List<dynamic>? ?? const [])
              .whereType<String>()
              .toList(growable: false),
      pagination: EmployeeCardPagination.fromJson(_map(json['paginasi'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'aktif',
      employeeType: filter['jenis_pegawai'] as String? ?? '',
      cardSize: EmployeeCardSize.fromJson(_map(json['ukuran_kartu'])),
      canManagePhoto: access['dapat_kelola_foto'] as bool? ?? false,
    );
  }

  final List<EmployeeCardPerson> items;
  final EmployeeCardSummary summary;
  final List<String> employeeTypes;
  final EmployeeCardPagination pagination;
  final String query;
  final String status;
  final String employeeType;
  final EmployeeCardSize cardSize;
  final bool canManagePhoto;

  EmployeeCardPage append(EmployeeCardPage next) => EmployeeCardPage(
    items: [...items, ...next.items],
    summary: next.summary,
    employeeTypes: next.employeeTypes,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
    employeeType: next.employeeType,
    cardSize: next.cardSize,
    canManagePhoto: next.canManagePhoto,
  );
}

class EmployeeCardPerson {
  const EmployeeCardPerson({
    required this.id,
    required this.name,
    required this.position,
    required this.hasPhoto,
    required this.active,
    required this.canMakeQr,
    this.nip,
    this.employeeType,
    this.photoUrl,
    this.qrData,
  });

  factory EmployeeCardPerson.fromJson(Map<String, dynamic> json) =>
      EmployeeCardPerson(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        employeeType: json['jenis_pegawai'] as String?,
        position: json['jabatan'] as String? ?? 'Pegawai',
        photoUrl: json['foto_url'] as String?,
        hasPhoto: json['punya_foto'] as bool? ?? false,
        active: json['aktif'] as bool? ?? false,
        qrData: json['qr_data'] as String?,
        canMakeQr: json['qr_bisa_dibuat'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String? nip;
  final String? employeeType;
  final String position;
  final String? photoUrl;
  final bool hasPhoto;
  final bool active;
  final String? qrData;
  final bool canMakeQr;

  String get nipLabel => 'NIP ${nip?.trim().isNotEmpty == true ? nip : '-'}';

  String get initials {
    final words = name.trim().split(RegExp(r'\s+'));
    return words
        .where((word) => word.isNotEmpty)
        .take(2)
        .map((word) => word[0].toUpperCase())
        .join();
  }
}

class EmployeeCardSummary {
  const EmployeeCardSummary({
    required this.total,
    required this.qrReady,
    required this.withPhoto,
  });

  factory EmployeeCardSummary.fromJson(Map<String, dynamic> json) =>
      EmployeeCardSummary(
        total: _integer(json['total']),
        qrReady: _integer(json['siap_qr']),
        withPhoto: _integer(json['dengan_foto']),
      );

  final int total;
  final int qrReady;
  final int withPhoto;
}

class EmployeeCardPagination {
  const EmployeeCardPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory EmployeeCardPagination.fromJson(Map<String, dynamic> json) =>
      EmployeeCardPagination(
        page: _integer(json['halaman'], fallback: 1),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class EmployeeCardSize {
  const EmployeeCardSize({
    required this.widthMillimeter,
    required this.heightMillimeter,
    required this.orientation,
  });

  factory EmployeeCardSize.fromJson(Map<String, dynamic> json) =>
      EmployeeCardSize(
        widthMillimeter: _double(json['lebar_mm'], fallback: 53.98),
        heightMillimeter: _double(json['tinggi_mm'], fallback: 85.6),
        orientation: json['orientasi'] as String? ?? 'portrait',
      );

  final double widthMillimeter;
  final double heightMillimeter;
  final String orientation;

  double get aspectRatio => widthMillimeter / heightMillimeter;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(convert)
        .toList(growable: false);

int _integer(Object? value, {int fallback = 0}) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};

double _double(Object? value, {required double fallback}) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? fallback,
  _ => fallback,
};
