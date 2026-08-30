class LatePointSettingPage {
  const LatePointSettingPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.query,
    required this.status,
  });

  factory LatePointSettingPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return LatePointSettingPage(
      items: _list(json['items'], LatePointSetting.fromJson),
      summary: LatePointSettingSummary.fromJson(_map(json['ringkasan'])),
      access: LatePointSettingAccess.fromJson(_map(json['hak_akses'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<LatePointSetting> items;
  final LatePointSettingSummary summary;
  final LatePointSettingAccess access;
  final String query;
  final String status;
}

class LatePointSetting {
  const LatePointSetting({
    required this.academicYear,
    required this.saved,
    required this.automaticActive,
    required this.ranges,
    this.updatedBy,
    this.updatedAt,
  });

  factory LatePointSetting.fromJson(Map<String, dynamic> json) =>
      LatePointSetting(
        academicYear: LatePointAcademicYear.fromJson(
          _map(json['tahun_pelajaran']),
        ),
        saved: json['tersimpan'] as bool? ?? false,
        automaticActive: json['otomatis_aktif'] as bool? ?? false,
        ranges: _list(json['rentang'], LatePointRange.fromJson),
        updatedBy: json['diperbarui_oleh'] as String?,
        updatedAt: DateTime.tryParse(json['diperbarui_pada'] as String? ?? ''),
      );

  final LatePointAcademicYear academicYear;
  final bool saved;
  final bool automaticActive;
  final List<LatePointRange> ranges;
  final String? updatedBy;
  final DateTime? updatedAt;
}

class LatePointAcademicYear {
  const LatePointAcademicYear({
    required this.id,
    required this.name,
    required this.active,
    this.startDate,
    this.endDate,
  });

  factory LatePointAcademicYear.fromJson(Map<String, dynamic> json) =>
      LatePointAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
        startDate: DateTime.tryParse(json['tanggal_mulai'] as String? ?? ''),
        endDate: DateTime.tryParse(json['tanggal_selesai'] as String? ?? ''),
      );

  final int id;
  final String name;
  final bool active;
  final DateTime? startDate;
  final DateTime? endDate;
}

class LatePointRange {
  const LatePointRange({
    required this.startMinute,
    required this.points,
    required this.order,
    required this.label,
    this.id,
    this.endMinute,
  });

  factory LatePointRange.fromJson(Map<String, dynamic> json) => LatePointRange(
    id: _nullableInteger(json['id']),
    startMinute: _integer(json['menit_mulai']),
    endMinute: _nullableInteger(json['menit_selesai']),
    points: _integer(json['poin']),
    order: _integer(json['urutan']),
    label: json['label'] as String? ?? '-',
  );

  final int? id;
  final int startMinute;
  final int? endMinute;
  final int points;
  final int order;
  final String label;
}

class LatePointSettingSummary {
  const LatePointSettingSummary({
    required this.academicYearCount,
    required this.configuredCount,
    required this.automaticActiveCount,
    this.activeAcademicYearId,
  });

  factory LatePointSettingSummary.fromJson(Map<String, dynamic> json) =>
      LatePointSettingSummary(
        academicYearCount: _integer(json['jumlah_tahun']),
        activeAcademicYearId: _nullableInteger(json['tahun_aktif_id']),
        configuredCount: _integer(json['sudah_diatur']),
        automaticActiveCount: _integer(json['otomatis_aktif']),
      );

  final int academicYearCount;
  final int? activeAcademicYearId;
  final int configuredCount;
  final int automaticActiveCount;
}

class LatePointSettingAccess {
  const LatePointSettingAccess({required this.canManage});

  factory LatePointSettingAccess.fromJson(Map<String, dynamic> json) =>
      LatePointSettingAccess(canManage: json['dapat_kelola'] as bool? ?? false);

  final bool canManage;
}

class LatePointSettingFormValue {
  const LatePointSettingFormValue({required this.active, required this.ranges});

  final bool active;
  final List<LatePointRangeFormValue> ranges;
}

class LatePointRangeFormValue {
  const LatePointRangeFormValue({
    required this.startMinute,
    required this.points,
    this.endMinute,
  });

  final int startMinute;
  final int? endMinute;
  final int points;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => convert(Map<String, dynamic>.from(item)))
          .toList(growable: false)
    : <T>[];

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
