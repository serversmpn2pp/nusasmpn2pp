class EarlyWarningSettingPage {
  const EarlyWarningSettingPage({
    required this.items,
    required this.summary,
    required this.access,
    required this.query,
    required this.status,
  });

  factory EarlyWarningSettingPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return EarlyWarningSettingPage(
      items: _list(json['items'], EarlyWarningSetting.fromJson),
      summary: EarlyWarningSettingSummary.fromJson(_map(json['ringkasan'])),
      access: EarlyWarningSettingAccess.fromJson(_map(json['hak_akses'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<EarlyWarningSetting> items;
  final EarlyWarningSettingSummary summary;
  final EarlyWarningSettingAccess access;
  final String query;
  final String status;
}

class EarlyWarningSetting {
  const EarlyWarningSetting({
    required this.academicYear,
    required this.saved,
    required this.detectionActive,
    required this.notificationActive,
    required this.nearThresholdPercentage,
    required this.repeatedViolationCount,
    required this.violationPeriodDays,
    required this.repeatedLateCount,
    required this.latePeriodDays,
    this.updatedBy,
    this.updatedAt,
  });

  factory EarlyWarningSetting.fromJson(Map<String, dynamic> json) =>
      EarlyWarningSetting(
        academicYear: EarlyWarningAcademicYear.fromJson(
          _map(json['tahun_pelajaran']),
        ),
        saved: json['tersimpan'] as bool? ?? false,
        detectionActive: json['deteksi_aktif'] as bool? ?? true,
        notificationActive: json['notifikasi_aktif'] as bool? ?? true,
        nearThresholdPercentage: _integer(json['persentase_mendekati_ambang']),
        repeatedViolationCount: _integer(json['jumlah_pelanggaran_berulang']),
        violationPeriodDays: _integer(json['periode_pelanggaran_hari']),
        repeatedLateCount: _integer(json['jumlah_keterlambatan_berulang']),
        latePeriodDays: _integer(json['periode_keterlambatan_hari']),
        updatedBy: json['diperbarui_oleh'] as String?,
        updatedAt: DateTime.tryParse(json['diperbarui_pada'] as String? ?? ''),
      );

  final EarlyWarningAcademicYear academicYear;
  final bool saved;
  final bool detectionActive;
  final bool notificationActive;
  final int nearThresholdPercentage;
  final int repeatedViolationCount;
  final int violationPeriodDays;
  final int repeatedLateCount;
  final int latePeriodDays;
  final String? updatedBy;
  final DateTime? updatedAt;
}

class EarlyWarningAcademicYear {
  const EarlyWarningAcademicYear({
    required this.id,
    required this.name,
    required this.active,
    this.startDate,
    this.endDate,
  });

  factory EarlyWarningAcademicYear.fromJson(Map<String, dynamic> json) =>
      EarlyWarningAcademicYear(
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

class EarlyWarningSettingSummary {
  const EarlyWarningSettingSummary({
    required this.academicYearCount,
    required this.configuredCount,
    required this.detectionActiveCount,
    required this.notificationActiveCount,
    this.activeAcademicYearId,
  });

  factory EarlyWarningSettingSummary.fromJson(Map<String, dynamic> json) =>
      EarlyWarningSettingSummary(
        academicYearCount: _integer(json['jumlah_tahun']),
        activeAcademicYearId: _nullableInteger(json['tahun_aktif_id']),
        configuredCount: _integer(json['sudah_diatur']),
        detectionActiveCount: _integer(json['deteksi_aktif']),
        notificationActiveCount: _integer(json['notifikasi_aktif']),
      );

  final int academicYearCount;
  final int? activeAcademicYearId;
  final int configuredCount;
  final int detectionActiveCount;
  final int notificationActiveCount;
}

class EarlyWarningSettingAccess {
  const EarlyWarningSettingAccess({required this.canManage});

  factory EarlyWarningSettingAccess.fromJson(Map<String, dynamic> json) =>
      EarlyWarningSettingAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
      );

  final bool canManage;
}

class EarlyWarningSettingFormValue {
  const EarlyWarningSettingFormValue({
    required this.detectionActive,
    required this.notificationActive,
    required this.nearThresholdPercentage,
    required this.repeatedViolationCount,
    required this.violationPeriodDays,
    required this.repeatedLateCount,
    required this.latePeriodDays,
  });

  final bool detectionActive;
  final bool notificationActive;
  final int nearThresholdPercentage;
  final int repeatedViolationCount;
  final int violationPeriodDays;
  final int repeatedLateCount;
  final int latePeriodDays;
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
