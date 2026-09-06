class InventoryLabelPage {
  const InventoryLabelPage({
    required this.items,
    required this.filters,
    required this.rules,
    required this.types,
    required this.sizes,
    required this.receipts,
    required this.categories,
    required this.goods,
    required this.locations,
  });

  factory InventoryLabelPage.fromJson(Map<String, dynamic> json) {
    final options = _map(json['pilihan']);
    return InventoryLabelPage(
      items: _list(json['items'], InventoryLabelItem.fromJson),
      filters: InventoryLabelFilters.fromJson(_map(json['filter'])),
      rules: InventoryLabelPrintRules.fromJson(_map(json['aturan_cetak'])),
      types: _list(options['jenis_label'], InventoryLabelValueOption.fromJson),
      sizes: _list(options['ukuran'], InventoryLabelSize.fromJson),
      receipts: _list(options['penerimaan'], InventoryLabelReceipt.fromJson),
      categories: _list(options['kategori'], InventoryLabelOption.fromJson),
      goods: _list(options['barang'], InventoryLabelOption.fromJson),
      locations: _list(options['lokasi'], InventoryLabelOption.fromJson),
    );
  }

  final List<InventoryLabelItem> items;
  final InventoryLabelFilters filters;
  final InventoryLabelPrintRules rules;
  final List<InventoryLabelValueOption> types;
  final List<InventoryLabelSize> sizes;
  final List<InventoryLabelReceipt> receipts;
  final List<InventoryLabelOption> categories;
  final List<InventoryLabelOption> goods;
  final List<InventoryLabelOption> locations;
}

class InventoryLabelFilters {
  const InventoryLabelFilters({
    this.type = 'unit',
    this.receiptId,
    this.acquisitionYear,
    this.categoryId,
    this.goodsId,
    this.locationId,
  });

  factory InventoryLabelFilters.fromJson(Map<String, dynamic> json) =>
      InventoryLabelFilters(
        type: json['jenis_label'] as String? ?? 'unit',
        receiptId: _nullableInt(json['penerimaan_barang_id']),
        acquisitionYear: _nullableInt(json['tahun_perolehan']),
        categoryId: _nullableInt(json['kategori_barang_id']),
        goodsId: _nullableInt(json['barang_id']),
        locationId: _nullableInt(json['lokasi_barang_id']),
      );

  final String type;
  final int? receiptId;
  final int? acquisitionYear;
  final int? categoryId;
  final int? goodsId;
  final int? locationId;

  int get activeCount => [
    receiptId,
    acquisitionYear,
    categoryId,
    goodsId,
    locationId,
  ].whereType<int>().length;
}

class InventoryLabelPrintRules {
  const InventoryLabelPrintRules({
    required this.paperFormat,
    required this.marginMm,
    required this.gapMm,
    required this.maximumSelection,
    required this.maximumCopies,
  });

  factory InventoryLabelPrintRules.fromJson(Map<String, dynamic> json) =>
      InventoryLabelPrintRules(
        paperFormat: json['format_kertas'] as String? ?? 'A4',
        marginMm: _number(json['margin_mm'], fallback: 8),
        gapMm: _number(json['jarak_label_mm'], fallback: 3),
        maximumSelection: _integer(json['maksimal_pilihan'], fallback: 500),
        maximumCopies: _integer(json['maksimal_salinan'], fallback: 20),
      );

  final String paperFormat;
  final double marginMm;
  final double gapMm;
  final int maximumSelection;
  final int maximumCopies;
}

class InventoryLabelItem {
  const InventoryLabelItem({
    required this.id,
    required this.type,
    required this.code,
    required this.name,
    required this.location,
    required this.summary,
    this.title,
    this.officialAssetNumber,
    this.goodsCode,
    this.sourceYear,
    this.owner,
    this.unit,
  });

  factory InventoryLabelItem.fromJson(Map<String, dynamic> json) =>
      InventoryLabelItem(
        id: _integer(json['id']),
        type: json['jenis'] as String? ?? 'unit',
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        location: json['lokasi'] as String? ?? '-',
        summary: json['ringkasan'] as String? ?? '-',
        title: json['judul'] as String?,
        officialAssetNumber: json['nomor_aset_resmi'] as String?,
        goodsCode: json['kode_barang'] as String?,
        sourceYear: json['sumber_tahun'] as String?,
        owner: json['pemilik'] as String?,
        unit: json['satuan'] as String?,
      );

  final int id;
  final String type;
  final String code;
  final String name;
  final String location;
  final String summary;
  final String? title;
  final String? officialAssetNumber;
  final String? goodsCode;
  final String? sourceYear;
  final String? owner;
  final String? unit;

  bool get isAsset => type == 'unit';
}

class InventoryLabelSize {
  const InventoryLabelSize({
    required this.value,
    required this.label,
    required this.widthMm,
    required this.heightMm,
  });

  factory InventoryLabelSize.fromJson(Map<String, dynamic> json) =>
      InventoryLabelSize(
        value: json['nilai'] as String? ?? 'sedang',
        label: json['label'] as String? ?? '65 x 35 mm',
        widthMm: _number(json['lebar_mm'], fallback: 65),
        heightMm: _number(json['tinggi_mm'], fallback: 35),
      );

  final String value;
  final String label;
  final double widthMm;
  final double heightMm;
}

class InventoryLabelValueOption {
  const InventoryLabelValueOption({required this.value, required this.label});

  factory InventoryLabelValueOption.fromJson(Map<String, dynamic> json) =>
      InventoryLabelValueOption(
        value: json['nilai'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String value;
  final String label;
}

class InventoryLabelOption {
  const InventoryLabelOption({
    required this.id,
    required this.name,
    required this.code,
    this.label,
  });

  factory InventoryLabelOption.fromJson(Map<String, dynamic> json) =>
      InventoryLabelOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String? ?? '-',
        label: json['label'] as String?,
      );

  final int id;
  final String name;
  final String code;
  final String? label;

  String get displayLabel => label ?? name;
}

class InventoryLabelReceipt {
  const InventoryLabelReceipt({
    required this.id,
    required this.number,
    required this.label,
    this.date,
  });

  factory InventoryLabelReceipt.fromJson(Map<String, dynamic> json) =>
      InventoryLabelReceipt(
        id: _integer(json['id']),
        number: json['nomor'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
        date: _date(json['tanggal']),
      );

  final int id;
  final String number;
  final String label;
  final DateTime? date;
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

int _integer(Object? value, {int fallback = 0}) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};

int? _nullableInt(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text),
  _ => null,
};

double _number(Object? value, {required double fallback}) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? fallback,
  _ => fallback,
};

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value) : null;
