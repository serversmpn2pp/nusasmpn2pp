class EmployeeAttendanceOption {
  const EmployeeAttendanceOption({
    required this.id,
    required this.name,
    this.nip,
  });

  factory EmployeeAttendanceOption.fromJson(Map<String, dynamic> json) =>
      EmployeeAttendanceOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
      );

  final int id;
  final String name;
  final String? nip;
}

class EmployeeAttendanceSummary {
  const EmployeeAttendanceSummary({
    required this.total,
    required this.present,
    required this.permitted,
    required this.sick,
    required this.officialDuty,
    required this.leave,
    required this.absent,
    required this.late,
    required this.earlyLeave,
    required this.notCheckedOut,
  });

  factory EmployeeAttendanceSummary.fromJson(Map<String, dynamic> json) =>
      EmployeeAttendanceSummary(
        total: _integer(json['total']),
        present: _integer(json['hadir']),
        permitted: _integer(json['izin']),
        sick: _integer(json['sakit']),
        officialDuty: _integer(json['dinas_luar']),
        leave: _integer(json['cuti']),
        absent: _integer(json['alfa']),
        late: _integer(json['terlambat']),
        earlyLeave: _integer(json['pulang_cepat']),
        notCheckedOut: _integer(json['belum_pulang']),
      );

  final int total;
  final int present;
  final int permitted;
  final int sick;
  final int officialDuty;
  final int leave;
  final int absent;
  final int late;
  final int earlyLeave;
  final int notCheckedOut;
}

class EmployeeAttendanceRecord {
  const EmployeeAttendanceRecord({
    required this.employeeId,
    required this.name,
    required this.initials,
    required this.active,
    required this.status,
    required this.statusLabel,
    required this.sourceLabel,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    required this.notCheckedOut,
    required this.canCorrect,
    this.nip,
    this.photoUrl,
    this.employeeType,
    this.position,
    this.employmentStatus,
    this.checkInTime,
    this.checkOutTime,
    this.checkInStatus,
    this.checkOutStatus,
    this.notes,
    this.scheduleName,
  });

  factory EmployeeAttendanceRecord.fromJson(Map<String, dynamic> json) {
    final employee = _map(json['pegawai']) ?? const {};
    final attendance = _map(json['presensi']) ?? const {};
    final correction = _map(json['koreksi']) ?? const {};

    return EmployeeAttendanceRecord(
      employeeId: _integer(employee['id']),
      name: employee['nama'] as String? ?? '-',
      initials: employee['inisial'] as String? ?? 'P',
      nip: employee['nip'] as String?,
      photoUrl: employee['foto_url'] as String?,
      employeeType: employee['jenis_pegawai'] as String?,
      position: employee['jabatan'] as String?,
      employmentStatus: employee['status_kepegawaian'] as String?,
      active: employee['aktif'] as bool? ?? false,
      status: attendance['status'] as String? ?? 'alfa',
      statusLabel: attendance['status_label'] as String? ?? 'Alfa',
      sourceLabel: attendance['sumber_label'] as String? ?? '-',
      checkInTime: attendance['jam_masuk'] as String?,
      checkOutTime: attendance['jam_pulang'] as String?,
      checkInStatus: attendance['status_masuk'] as String?,
      checkOutStatus: attendance['status_pulang'] as String?,
      lateMinutes: _integer(attendance['menit_terlambat']),
      earlyLeaveMinutes: _integer(attendance['menit_pulang_cepat']),
      notCheckedOut: attendance['belum_pulang'] as bool? ?? false,
      notes: attendance['catatan'] as String?,
      scheduleName: attendance['nama_jadwal'] as String?,
      canCorrect: correction['dapat'] as bool? ?? false,
    );
  }

  final int employeeId;
  final String name;
  final String initials;
  final String? nip;
  final String? photoUrl;
  final String? employeeType;
  final String? position;
  final String? employmentStatus;
  final bool active;
  final String status;
  final String statusLabel;
  final String sourceLabel;
  final String? checkInTime;
  final String? checkOutTime;
  final String? checkInStatus;
  final String? checkOutStatus;
  final int lateMinutes;
  final int earlyLeaveMinutes;
  final bool notCheckedOut;
  final String? notes;
  final String? scheduleName;
  final bool canCorrect;
}

class EmployeeAttendanceRecapPage {
  const EmployeeAttendanceRecapPage({
    required this.date,
    required this.dateLabel,
    required this.items,
    required this.summary,
    required this.employeeTypes,
    required this.employees,
    required this.employeeStatus,
    required this.status,
    required this.query,
    required this.page,
    required this.hasMore,
    required this.privateScope,
    required this.canCorrect,
    this.employeeType,
    this.employeeId,
  });

  factory EmployeeAttendanceRecapPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final pagination = _map(json['paginasi']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};

    return EmployeeAttendanceRecapPage(
      date: json['tanggal'] as String? ?? '',
      dateLabel: json['tanggal_label'] as String? ?? '-',
      items: _list(json['items'], EmployeeAttendanceRecord.fromJson),
      summary: EmployeeAttendanceSummary.fromJson(
        _map(json['ringkasan']) ?? const {},
      ),
      employeeTypes: (json['jenis_pegawai'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(growable: false),
      employees: _list(json['pegawai'], EmployeeAttendanceOption.fromJson),
      employeeType: filter['jenis_pegawai'] as String?,
      employeeId: _nullableInteger(filter['pegawai_id']),
      employeeStatus: filter['status_pegawai'] as String? ?? 'aktif',
      status: filter['status'] as String? ?? 'semua',
      query: filter['cari'] as String? ?? '',
      page: _integer(pagination['halaman']),
      hasMore: pagination['ada_halaman_berikutnya'] as bool? ?? false,
      privateScope: access['cakupan_pribadi'] as bool? ?? false,
      canCorrect: access['dapat_koreksi'] as bool? ?? false,
    );
  }

  final String date;
  final String dateLabel;
  final List<EmployeeAttendanceRecord> items;
  final EmployeeAttendanceSummary summary;
  final List<String> employeeTypes;
  final List<EmployeeAttendanceOption> employees;
  final String? employeeType;
  final int? employeeId;
  final String employeeStatus;
  final String status;
  final String query;
  final int page;
  final bool hasMore;
  final bool privateScope;
  final bool canCorrect;
}

class EmployeeAttendanceDetail {
  const EmployeeAttendanceDetail({
    required this.date,
    required this.dateLabel,
    required this.record,
    required this.scheduleAvailable,
    required this.privateScope,
    required this.canCorrect,
    this.scheduleName,
    this.officialCheckIn,
    this.officialCheckOut,
  });

  factory EmployeeAttendanceDetail.fromJson(Map<String, dynamic> json) {
    final schedule = _map(json['jadwal_presensi']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};

    return EmployeeAttendanceDetail(
      date: json['tanggal'] as String? ?? '',
      dateLabel: json['tanggal_label'] as String? ?? '-',
      record: EmployeeAttendanceRecord.fromJson(_map(json['item']) ?? const {}),
      scheduleAvailable: schedule['tersedia'] as bool? ?? false,
      scheduleName: schedule['nama'] as String?,
      officialCheckIn: schedule['jam_masuk'] as String?,
      officialCheckOut: schedule['jam_pulang'] as String?,
      privateScope: access['cakupan_pribadi'] as bool? ?? false,
      canCorrect: access['dapat_koreksi'] as bool? ?? false,
    );
  }

  final String date;
  final String dateLabel;
  final EmployeeAttendanceRecord record;
  final bool scheduleAvailable;
  final String? scheduleName;
  final String? officialCheckIn;
  final String? officialCheckOut;
  final bool privateScope;
  final bool canCorrect;
}

class EmployeeAttendanceCorrectionValue {
  const EmployeeAttendanceCorrectionValue({
    required this.status,
    required this.notes,
    this.checkInTime,
    this.checkOutTime,
  });

  final String status;
  final String? checkInTime;
  final String? checkOutTime;
  final String notes;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);
Map<String, dynamic>? _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;
int _integer(Object? value) => value is num ? value.toInt() : 0;
int? _nullableInteger(Object? value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '');
