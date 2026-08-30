class ViolationProcessDeadlinePage {
  const ViolationProcessDeadlinePage({
    required this.items,
    required this.summary,
    required this.access,
    required this.query,
    required this.status,
  });

  factory ViolationProcessDeadlinePage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return ViolationProcessDeadlinePage(
      items: _list(json['items'], ViolationProcessDeadline.fromJson),
      summary: ViolationProcessDeadlineSummary.fromJson(
        _map(json['ringkasan']),
      ),
      access: ViolationProcessDeadlineAccess.fromJson(_map(json['hak_akses'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<ViolationProcessDeadline> items;
  final ViolationProcessDeadlineSummary summary;
  final ViolationProcessDeadlineAccess access;
  final String query;
  final String status;
}

class ViolationProcessDeadline {
  const ViolationProcessDeadline({
    required this.academicYear,
    required this.saved,
    required this.counselingDays,
    required this.approvalDays,
    required this.reminderDaysBefore,
    required this.reminderNotificationActive,
    required this.overdueNotificationActive,
    this.updatedBy,
    this.updatedAt,
  });

  factory ViolationProcessDeadline.fromJson(Map<String, dynamic> json) =>
      ViolationProcessDeadline(
        academicYear: ViolationDeadlineAcademicYear.fromJson(
          _map(json['tahun_pelajaran']),
        ),
        saved: json['tersimpan'] as bool? ?? false,
        counselingDays: _integer(json['batas_hari_pemeriksaan_bk']),
        approvalDays: _integer(json['batas_hari_persetujuan']),
        reminderDaysBefore: _integer(json['pengingat_hari_sebelum_batas']),
        reminderNotificationActive:
            json['notifikasi_pengingat_aktif'] as bool? ?? true,
        overdueNotificationActive:
            json['notifikasi_terlambat_aktif'] as bool? ?? true,
        updatedBy: json['diperbarui_oleh'] as String?,
        updatedAt: DateTime.tryParse(json['diperbarui_pada'] as String? ?? ''),
      );

  final ViolationDeadlineAcademicYear academicYear;
  final bool saved;
  final int counselingDays;
  final int approvalDays;
  final int reminderDaysBefore;
  final bool reminderNotificationActive;
  final bool overdueNotificationActive;
  final String? updatedBy;
  final DateTime? updatedAt;
}

class ViolationDeadlineAcademicYear {
  const ViolationDeadlineAcademicYear({
    required this.id,
    required this.name,
    required this.active,
    this.startDate,
    this.endDate,
  });

  factory ViolationDeadlineAcademicYear.fromJson(Map<String, dynamic> json) =>
      ViolationDeadlineAcademicYear(
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

class ViolationProcessDeadlineSummary {
  const ViolationProcessDeadlineSummary({
    required this.academicYearCount,
    required this.configuredCount,
    required this.defaultCount,
    required this.reminderActiveCount,
    required this.overdueActiveCount,
    this.activeAcademicYearId,
  });

  factory ViolationProcessDeadlineSummary.fromJson(Map<String, dynamic> json) =>
      ViolationProcessDeadlineSummary(
        academicYearCount: _integer(json['jumlah_tahun']),
        activeAcademicYearId: _nullableInteger(json['tahun_aktif_id']),
        configuredCount: _integer(json['sudah_diatur']),
        defaultCount: _integer(json['memakai_bawaan']),
        reminderActiveCount: _integer(json['pengingat_aktif']),
        overdueActiveCount: _integer(json['terlambat_aktif']),
      );

  final int academicYearCount;
  final int? activeAcademicYearId;
  final int configuredCount;
  final int defaultCount;
  final int reminderActiveCount;
  final int overdueActiveCount;
}

class ViolationProcessDeadlineAccess {
  const ViolationProcessDeadlineAccess({required this.canManage});

  factory ViolationProcessDeadlineAccess.fromJson(Map<String, dynamic> json) =>
      ViolationProcessDeadlineAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
      );

  final bool canManage;
}

class ViolationProcessDeadlineFormValue {
  const ViolationProcessDeadlineFormValue({
    required this.counselingDays,
    required this.approvalDays,
    required this.reminderDaysBefore,
    required this.reminderNotificationActive,
    required this.overdueNotificationActive,
  });

  final int counselingDays;
  final int approvalDays;
  final int reminderDaysBefore;
  final bool reminderNotificationActive;
  final bool overdueNotificationActive;
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
