class StudentAttendanceSettingsCatalog {
  const StudentAttendanceSettingsCatalog({
    required this.items,
    required this.summary,
    required this.days,
    required this.selectedDay,
    required this.status,
    required this.canManage,
  });

  factory StudentAttendanceSettingsCatalog.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    return StudentAttendanceSettingsCatalog(
      items: _list(json['items'], StudentAttendanceSetting.fromJson),
      summary: StudentAttendanceSettingsSummary.fromJson(
        _map(json['ringkasan']) ?? const {},
      ),
      days: _list(json['hari'], AttendanceDay.fromJson),
      selectedDay: filter['hari'] as String? ?? 'semua',
      status: filter['status'] as String? ?? 'semua',
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<StudentAttendanceSetting> items;
  final StudentAttendanceSettingsSummary summary;
  final List<AttendanceDay> days;
  final String selectedDay;
  final String status;
  final bool canManage;

  Set<String> get configuredDays => items.map((item) => item.day).toSet();
}

class StudentAttendanceSettingsSummary {
  const StudentAttendanceSettingsSummary({
    required this.total,
    required this.active,
    required this.inactive,
    required this.unconfigured,
  });

  factory StudentAttendanceSettingsSummary.fromJson(
    Map<String, dynamic> json,
  ) => StudentAttendanceSettingsSummary(
    total: _integer(json['total']),
    active: _integer(json['aktif']),
    inactive: _integer(json['nonaktif']),
    unconfigured: _integer(json['belum_diatur']),
  );

  final int total;
  final int active;
  final int inactive;
  final int unconfigured;
}

class AttendanceDay {
  const AttendanceDay({
    required this.code,
    required this.label,
    required this.order,
    required this.configured,
  });

  factory AttendanceDay.fromJson(Map<String, dynamic> json) => AttendanceDay(
    code: json['kode'] as String? ?? '',
    label: json['label'] as String? ?? '-',
    order: _integer(json['urutan']),
    configured: json['sudah_diatur'] as bool? ?? false,
  );

  final String code;
  final String label;
  final int order;
  final bool configured;
}

class StudentAttendanceSetting {
  const StudentAttendanceSetting({
    required this.id,
    required this.day,
    required this.dayLabel,
    required this.dayOrder,
    required this.checkInScanStart,
    required this.checkInTime,
    required this.checkInScanEnd,
    required this.checkOutScanStart,
    required this.checkOutTime,
    required this.checkOutScanEnd,
    required this.active,
    this.notes,
  });

  factory StudentAttendanceSetting.fromJson(Map<String, dynamic> json) =>
      StudentAttendanceSetting(
        id: _integer(json['id']),
        day: json['hari'] as String? ?? '',
        dayLabel: json['hari_label'] as String? ?? '-',
        dayOrder: _integer(json['urutan_hari']),
        checkInScanStart: json['jam_scan_masuk_mulai'] as String? ?? '-',
        checkInTime: json['jam_masuk'] as String? ?? '-',
        checkInScanEnd: json['jam_scan_masuk_selesai'] as String? ?? '-',
        checkOutScanStart: json['jam_scan_pulang_mulai'] as String? ?? '-',
        checkOutTime: json['jam_pulang'] as String? ?? '-',
        checkOutScanEnd: json['jam_scan_pulang_selesai'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
        notes: json['keterangan'] as String?,
      );

  final int id;
  final String day;
  final String dayLabel;
  final int dayOrder;
  final String checkInScanStart;
  final String checkInTime;
  final String checkInScanEnd;
  final String checkOutScanStart;
  final String checkOutTime;
  final String checkOutScanEnd;
  final bool active;
  final String? notes;

  String get checkInWindow => '$checkInScanStart–$checkInScanEnd';
  String get checkOutWindow => '$checkOutScanStart–$checkOutScanEnd';
}

class StudentAttendanceSettingsFormValue {
  const StudentAttendanceSettingsFormValue({
    required this.day,
    required this.checkInScanStart,
    required this.checkInTime,
    required this.checkInScanEnd,
    required this.checkOutScanStart,
    required this.checkOutTime,
    required this.checkOutScanEnd,
    required this.active,
    this.notes,
  });

  final String day;
  final String checkInScanStart;
  final String checkInTime;
  final String checkInScanEnd;
  final String checkOutScanStart;
  final String checkOutTime;
  final String checkOutScanEnd;
  final bool active;
  final String? notes;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic>? _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

int _integer(Object? value) => value is num ? value.toInt() : 0;
