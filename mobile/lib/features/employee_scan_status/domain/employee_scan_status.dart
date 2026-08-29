class EmployeeScanStatusDashboard {
  const EmployeeScanStatusDashboard({
    required this.date,
    required this.dateLabel,
    required this.serverTime,
    required this.nextRefreshSeconds,
    required this.schedule,
    required this.summary,
    required this.activities,
    required this.employeeTypes,
    required this.status,
    required this.query,
    this.selectedEmployeeType,
  });

  factory EmployeeScanStatusDashboard.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return EmployeeScanStatusDashboard(
      date: json['tanggal'] as String? ?? '',
      dateLabel: json['tanggal_label'] as String? ?? '-',
      serverTime: _dateTime(json['waktu_server']),
      nextRefreshSeconds: _integer(json['pembaruan_berikutnya_detik'], 15),
      schedule: EmployeeScanSchedule.fromJson(_map(json['jadwal'])),
      summary: EmployeeScanSummary.fromJson(_map(json['ringkasan'])),
      activities: _list(json['aktivitas'], EmployeeScanActivity.fromJson),
      employeeTypes: (json['jenis_pegawai'] as List<dynamic>? ?? const [])
          .whereType<String>()
          .toList(growable: false),
      selectedEmployeeType: filter['jenis_pegawai'] as String?,
      status: filter['status'] as String? ?? 'semua',
      query: filter['cari'] as String? ?? '',
    );
  }

  final String date;
  final String dateLabel;
  final DateTime? serverTime;
  final int nextRefreshSeconds;
  final EmployeeScanSchedule schedule;
  final EmployeeScanSummary summary;
  final List<EmployeeScanActivity> activities;
  final List<String> employeeTypes;
  final String? selectedEmployeeType;
  final String status;
  final String query;
}

class EmployeeScanSchedule {
  const EmployeeScanSchedule({
    required this.available,
    required this.count,
    required this.day,
    required this.dayLabel,
    required this.phase,
    required this.phaseLabel,
    required this.items,
    this.checkInScanStart,
    this.checkInScanEnd,
    this.checkOutScanStart,
    this.checkOutScanEnd,
  });

  factory EmployeeScanSchedule.fromJson(Map<String, dynamic> json) =>
      EmployeeScanSchedule(
        available: json['tersedia'] as bool? ?? false,
        count: _integer(json['jumlah']),
        day: json['hari'] as String? ?? '',
        dayLabel: json['hari_label'] as String? ?? '-',
        phase: json['fase'] as String? ?? 'tidak_tersedia',
        phaseLabel: json['fase_label'] as String? ?? 'Jadwal belum tersedia',
        checkInScanStart: json['jam_scan_masuk_mulai'] as String?,
        checkInScanEnd: json['jam_scan_masuk_selesai'] as String?,
        checkOutScanStart: json['jam_scan_pulang_mulai'] as String?,
        checkOutScanEnd: json['jam_scan_pulang_selesai'] as String?,
        items: _list(json['items'], EmployeeScanScheduleItem.fromJson),
      );

  final bool available;
  final int count;
  final String day;
  final String dayLabel;
  final String phase;
  final String phaseLabel;
  final String? checkInScanStart;
  final String? checkInScanEnd;
  final String? checkOutScanStart;
  final String? checkOutScanEnd;
  final List<EmployeeScanScheduleItem> items;

  String get checkInWindow =>
      '${checkInScanStart ?? '-'}–${checkInScanEnd ?? '-'}';
  String get checkOutWindow =>
      '${checkOutScanStart ?? '-'}–${checkOutScanEnd ?? '-'}';
}

class EmployeeScanScheduleItem {
  const EmployeeScanScheduleItem({
    required this.id,
    required this.name,
    required this.scope,
    required this.target,
    this.checkInTime,
    this.checkOutTime,
  });

  factory EmployeeScanScheduleItem.fromJson(Map<String, dynamic> json) =>
      EmployeeScanScheduleItem(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        scope: json['cakupan'] as String? ?? '-',
        target: json['sasaran'] as String? ?? '-',
        checkInTime: json['jam_masuk'] as String?,
        checkOutTime: json['jam_pulang'] as String?,
      );

  final int id;
  final String name;
  final String scope;
  final String target;
  final String? checkInTime;
  final String? checkOutTime;
}

class EmployeeScanSummary {
  const EmployeeScanSummary({
    required this.employeeCount,
    required this.checkedIn,
    required this.late,
    required this.checkedOut,
    required this.notCheckedIn,
    required this.notCheckedOut,
    required this.successfulScans,
    required this.alreadyRecorded,
    required this.needsAttention,
  });

  factory EmployeeScanSummary.fromJson(Map<String, dynamic> json) =>
      EmployeeScanSummary(
        employeeCount: _integer(json['jumlah_pegawai']),
        checkedIn: _integer(json['sudah_masuk']),
        late: _integer(json['terlambat']),
        checkedOut: _integer(json['sudah_pulang']),
        notCheckedIn: _integer(json['belum_scan_masuk']),
        notCheckedOut: _integer(json['belum_scan_pulang']),
        successfulScans: _integer(json['scan_berhasil']),
        alreadyRecorded: _integer(json['sudah_tercatat']),
        needsAttention: _integer(json['perlu_perhatian']),
      );

  final int employeeCount;
  final int checkedIn;
  final int late;
  final int checkedOut;
  final int notCheckedIn;
  final int notCheckedOut;
  final int successfulScans;
  final int alreadyRecorded;
  final int needsAttention;
}

class EmployeeScanActivity {
  const EmployeeScanActivity({
    required this.id,
    required this.successful,
    required this.status,
    required this.statusLabel,
    required this.scanTypeLabel,
    required this.scanTime,
    this.message,
    this.scanType,
    this.scannerId,
    this.scannedAt,
    this.employee,
    this.attendance,
  });

  factory EmployeeScanActivity.fromJson(Map<String, dynamic> json) =>
      EmployeeScanActivity(
        id: _integer(json['id']),
        successful: json['berhasil'] as bool? ?? false,
        status: json['status'] as String? ?? '',
        statusLabel: json['status_label'] as String? ?? '-',
        message: json['pesan'] as String?,
        scanType: json['jenis_scan'] as String?,
        scanTypeLabel: json['jenis_scan_label'] as String? ?? '-',
        scannerId: json['scanner_id'] as String?,
        scannedAt: _dateTime(json['waktu_scan']),
        scanTime: json['jam_scan'] as String? ?? '-',
        employee: json['pegawai'] is Map
            ? ScannedEmployee.fromJson(_map(json['pegawai']))
            : null,
        attendance: json['presensi'] is Map
            ? EmployeeScanAttendance.fromJson(_map(json['presensi']))
            : null,
      );

  final int id;
  final bool successful;
  final String status;
  final String statusLabel;
  final String? message;
  final String? scanType;
  final String scanTypeLabel;
  final String? scannerId;
  final DateTime? scannedAt;
  final String scanTime;
  final ScannedEmployee? employee;
  final EmployeeScanAttendance? attendance;

  bool get alreadyRecorded => const {
    'duplikat_cepat',
    'sudah_scan_masuk',
    'sudah_scan_pulang',
  }.contains(status);
}

class ScannedEmployee {
  const ScannedEmployee({
    required this.id,
    required this.name,
    required this.initials,
    this.nip,
    this.type,
    this.position,
    this.photoUrl,
  });

  factory ScannedEmployee.fromJson(Map<String, dynamic> json) =>
      ScannedEmployee(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        type: json['jenis_pegawai'] as String?,
        position: json['jabatan'] as String?,
        photoUrl: json['foto_url'] as String?,
        initials: json['inisial'] as String? ?? 'P',
      );

  final int id;
  final String name;
  final String? nip;
  final String? type;
  final String? position;
  final String? photoUrl;
  final String initials;
}

class EmployeeScanAttendance {
  const EmployeeScanAttendance({
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    this.checkInTime,
    this.checkInStatus,
    this.checkOutTime,
    this.checkOutStatus,
    this.attendanceStatus,
    this.scheduleName,
  });

  factory EmployeeScanAttendance.fromJson(Map<String, dynamic> json) =>
      EmployeeScanAttendance(
        checkInTime: json['jam_masuk'] as String?,
        checkInStatus: json['status_masuk'] as String?,
        lateMinutes: _integer(json['menit_terlambat']),
        checkOutTime: json['jam_pulang'] as String?,
        checkOutStatus: json['status_pulang'] as String?,
        earlyLeaveMinutes: _integer(json['menit_pulang_cepat']),
        attendanceStatus: json['status_kehadiran'] as String?,
        scheduleName: json['nama_jadwal'] as String?,
      );

  final String? checkInTime;
  final String? checkInStatus;
  final int lateMinutes;
  final String? checkOutTime;
  final String? checkOutStatus;
  final int earlyLeaveMinutes;
  final String? attendanceStatus;
  final String? scheduleName;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value, [int fallback = 0]) =>
    value is num ? value.toInt() : fallback;

DateTime? _dateTime(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
