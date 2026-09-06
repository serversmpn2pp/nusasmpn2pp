class InventorySettings {
  const InventorySettings({
    required this.id,
    required this.code,
    required this.assetNumberPrefix,
    required this.assetNumberSuffix,
    required this.ownerName,
    required this.internalIdDigits,
    required this.exampleYear,
    required this.assetNumberExample,
    required this.consumableCodeExample,
    required this.assetUnitCodeExample,
    required this.canManage,
    this.updatedBy,
    this.updatedAt,
  });

  factory InventorySettings.fromJson(Map<String, dynamic> json) =>
      InventorySettings(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? 'utama',
        assetNumberPrefix: json['awalan_nomor_aset'] as String? ?? '',
        assetNumberSuffix: json['akhiran_nomor_aset'] as String? ?? '',
        ownerName: json['nama_pemilik'] as String? ?? '',
        internalIdDigits: _integer(json['jumlah_digit_id_internal']),
        exampleYear: _integer(json['tahun_contoh']),
        assetNumberExample: json['contoh_nomor_aset'] as String? ?? '-',
        consumableCodeExample:
            json['contoh_kode_barang_habis_pakai'] as String? ?? '-',
        assetUnitCodeExample: json['contoh_kode_unit_aset'] as String? ?? '-',
        updatedBy: json['diperbarui_oleh'] as String?,
        updatedAt: DateTime.tryParse(json['diperbarui_pada'] as String? ?? '')
            ?.toLocal(),
        canManage: _map(json['hak_akses'])['dapat_kelola'] as bool? ?? false,
      );

  final int id;
  final String code;
  final String assetNumberPrefix;
  final String assetNumberSuffix;
  final String ownerName;
  final int internalIdDigits;
  final int exampleYear;
  final String assetNumberExample;
  final String consumableCodeExample;
  final String assetUnitCodeExample;
  final String? updatedBy;
  final DateTime? updatedAt;
  final bool canManage;
}

class InventorySettingsFormValue {
  const InventorySettingsFormValue({
    required this.assetNumberPrefix,
    required this.assetNumberSuffix,
    required this.ownerName,
    required this.internalIdDigits,
  });

  final String assetNumberPrefix;
  final String assetNumberSuffix;
  final String ownerName;
  final int internalIdDigits;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};
