class EmployeeAttendanceSettingsCatalog {
  const EmployeeAttendanceSettingsCatalog({
    required this.items,
    required this.summary,
    required this.days,
    required this.scopes,
    required this.employeeTypes,
    required this.employees,
    required this.query,
    required this.selectedDay,
    required this.selectedScope,
    required this.status,
    required this.canManage,
  });

  factory EmployeeAttendanceSettingsCatalog.fromJson(
    Map<String, dynamic> json,
  ) {
    final filter = _map(json['filter']);
    final access = _map(json['hak_akses']);
    return EmployeeAttendanceSettingsCatalog(
      items: _list(json['items'], EmployeeAttendanceSetting.fromJson),
      summary: EmployeeAttendanceSettingsSummary.fromJson(
        _map(json['ringkasan']),
      ),
      days: _list(json['hari'], AttendanceReferenceOption.fromJson),
      scopes: _list(json['cakupan'], AttendanceReferenceOption.fromJson),
      employeeTypes: (json['jenis_pegawai'] as List<dynamic>? ?? const [])
          .whereType<String>()
          .toList(growable: false),
      employees: _list(json['pegawai'], AttendanceEmployeeReference.fromJson),
      query: filter['q'] as String? ?? '',
      selectedDay: filter['hari'] as String? ?? 'semua',
      selectedScope: filter['cakupan'] as String? ?? 'semua_cakupan',
      status: filter['status'] as String? ?? 'semua_status',
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<EmployeeAttendanceSetting> items;
  final EmployeeAttendanceSettingsSummary summary;
  final List<AttendanceReferenceOption> days;
  final List<AttendanceReferenceOption> scopes;
  final List<String> employeeTypes;
  final List<AttendanceEmployeeReference> employees;
  final String query;
  final String selectedDay;
  final String selectedScope;
  final String status;
  final bool canManage;
}

class EmployeeAttendanceSettingsSummary {
  const EmployeeAttendanceSettingsSummary({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory EmployeeAttendanceSettingsSummary.fromJson(
    Map<String, dynamic> json,
  ) => EmployeeAttendanceSettingsSummary(
    total: _integer(json['total']),
    active: _integer(json['aktif']),
    inactive: _integer(json['nonaktif']),
  );

  final int total;
  final int active;
  final int inactive;
}

class AttendanceReferenceOption {
  const AttendanceReferenceOption({
    required this.code,
    required this.label,
    this.order = 0,
  });

  factory AttendanceReferenceOption.fromJson(Map<String, dynamic> json) =>
      AttendanceReferenceOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
        order: _integer(json['urutan']),
      );

  final String code;
  final String label;
  final int order;
}

class AttendanceEmployeeReference {
  const AttendanceEmployeeReference({
    required this.id,
    required this.name,
    this.nip,
    this.type,
    this.position,
  });

  factory AttendanceEmployeeReference.fromJson(Map<String, dynamic> json) =>
      AttendanceEmployeeReference(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        type: json['jenis_pegawai'] as String?,
        position: json['jabatan'] as String?,
      );

  final int id;
  final String name;
  final String? nip;
  final String? type;
  final String? position;

  String get label =>
      [name, if (nip?.trim().isNotEmpty == true) nip!].join(' · ');
}

class EmployeeAttendanceSetting {
  const EmployeeAttendanceSetting({
    required this.id,
    required this.name,
    required this.scope,
    required this.scopeLabel,
    required this.targetLabel,
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
    this.employeeType,
    this.employeeId,
    this.employee,
    this.notes,
  });

  factory EmployeeAttendanceSetting.fromJson(Map<String, dynamic> json) =>
      EmployeeAttendanceSetting(
        id: _integer(json['id']),
        name: json['nama_jadwal'] as String? ?? '-',
        scope: json['cakupan'] as String? ?? 'semua',
        scopeLabel: json['cakupan_label'] as String? ?? '-',
        targetLabel: json['sasaran_label'] as String? ?? '-',
        employeeType: json['jenis_pegawai'] as String?,
        employeeId: _nullableInteger(json['pegawai_id']),
        employee: json['pegawai'] is Map
            ? AttendanceEmployeeReference.fromJson(_map(json['pegawai']))
            : null,
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
  final String name;
  final String scope;
  final String scopeLabel;
  final String targetLabel;
  final String? employeeType;
  final int? employeeId;
  final AttendanceEmployeeReference? employee;
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

class EmployeeAttendanceSettingsFormValue {
  const EmployeeAttendanceSettingsFormValue({
    required this.name,
    required this.scope,
    required this.day,
    required this.checkInScanStart,
    required this.checkInTime,
    required this.checkInScanEnd,
    required this.checkOutScanStart,
    required this.checkOutTime,
    required this.checkOutScanEnd,
    required this.active,
    this.employeeType,
    this.employeeId,
    this.notes,
  });

  final String name;
  final String scope;
  final String? employeeType;
  final int? employeeId;
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

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
