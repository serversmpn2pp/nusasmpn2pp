class StudentScanStatusDashboard {
  const StudentScanStatusDashboard({
    required this.date,
    required this.dateLabel,
    required this.serverTime,
    required this.nextRefreshSeconds,
    required this.schedule,
    required this.summary,
    required this.activities,
    required this.classes,
    required this.status,
    required this.query,
    this.academicYear,
    this.selectedClassId,
  });

  factory StudentScanStatusDashboard.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    return StudentScanStatusDashboard(
      date: json['tanggal'] as String? ?? '',
      dateLabel: json['tanggal_label'] as String? ?? '-',
      serverTime: _dateTime(json['waktu_server']),
      nextRefreshSeconds: _integer(json['pembaruan_berikutnya_detik'], 15),
      academicYear: switch (_map(json['tahun_pelajaran'])) {
        final value? => ScanAcademicYear.fromJson(value),
        _ => null,
      },
      schedule: ScanScheduleStatus.fromJson(_map(json['jadwal']) ?? const {}),
      summary: StudentScanSummary.fromJson(_map(json['ringkasan']) ?? const {}),
      activities: _list(json['aktivitas'], StudentScanActivity.fromJson),
      classes: _list(json['kelas'], ScanClassOption.fromJson),
      selectedClassId: _nullableInteger(filter['kelas_id']),
      status: filter['status'] as String? ?? 'semua',
      query: filter['cari'] as String? ?? '',
    );
  }

  final String date;
  final String dateLabel;
  final DateTime? serverTime;
  final int nextRefreshSeconds;
  final ScanAcademicYear? academicYear;
  final ScanScheduleStatus schedule;
  final StudentScanSummary summary;
  final List<StudentScanActivity> activities;
  final List<ScanClassOption> classes;
  final int? selectedClassId;
  final String status;
  final String query;
}

class ScanAcademicYear {
  const ScanAcademicYear({required this.id, required this.name});

  factory ScanAcademicYear.fromJson(Map<String, dynamic> json) =>
      ScanAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class ScanClassOption {
  const ScanClassOption({required this.id, required this.name, this.level});

  factory ScanClassOption.fromJson(Map<String, dynamic> json) =>
      ScanClassOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        level: _nullableInteger(json['tingkat']),
      );

  final int id;
  final String name;
  final int? level;
}

class ScanScheduleStatus {
  const ScanScheduleStatus({
    required this.available,
    required this.day,
    required this.dayLabel,
    required this.phase,
    required this.phaseLabel,
    this.checkInScanStart,
    this.checkInTime,
    this.checkInScanEnd,
    this.checkOutScanStart,
    this.checkOutTime,
    this.checkOutScanEnd,
  });

  factory ScanScheduleStatus.fromJson(Map<String, dynamic> json) =>
      ScanScheduleStatus(
        available: json['tersedia'] as bool? ?? false,
        day: json['hari'] as String? ?? '',
        dayLabel: json['hari_label'] as String? ?? '-',
        phase: json['fase'] as String? ?? 'tidak_tersedia',
        phaseLabel: json['fase_label'] as String? ?? 'Jadwal belum tersedia',
        checkInScanStart: json['jam_scan_masuk_mulai'] as String?,
        checkInTime: json['jam_masuk'] as String?,
        checkInScanEnd: json['jam_scan_masuk_selesai'] as String?,
        checkOutScanStart: json['jam_scan_pulang_mulai'] as String?,
        checkOutTime: json['jam_pulang'] as String?,
        checkOutScanEnd: json['jam_scan_pulang_selesai'] as String?,
      );

  final bool available;
  final String day;
  final String dayLabel;
  final String phase;
  final String phaseLabel;
  final String? checkInScanStart;
  final String? checkInTime;
  final String? checkInScanEnd;
  final String? checkOutScanStart;
  final String? checkOutTime;
  final String? checkOutScanEnd;

  String get checkInWindow =>
      '${checkInScanStart ?? '-'}–${checkInScanEnd ?? '-'}';
  String get checkOutWindow =>
      '${checkOutScanStart ?? '-'}–${checkOutScanEnd ?? '-'}';
}

class StudentScanSummary {
  const StudentScanSummary({
    required this.studentCount,
    required this.checkedIn,
    required this.late,
    required this.checkedOut,
    required this.notCheckedIn,
    required this.notCheckedOut,
    required this.successfulScans,
    required this.alreadyRecorded,
    required this.needsAttention,
  });

  factory StudentScanSummary.fromJson(Map<String, dynamic> json) =>
      StudentScanSummary(
        studentCount: _integer(json['jumlah_siswa']),
        checkedIn: _integer(json['sudah_masuk']),
        late: _integer(json['terlambat']),
        checkedOut: _integer(json['sudah_pulang']),
        notCheckedIn: _integer(json['belum_scan_masuk']),
        notCheckedOut: _integer(json['belum_scan_pulang']),
        successfulScans: _integer(json['scan_berhasil']),
        alreadyRecorded: _integer(json['sudah_tercatat']),
        needsAttention: _integer(json['perlu_perhatian']),
      );

  final int studentCount;
  final int checkedIn;
  final int late;
  final int checkedOut;
  final int notCheckedIn;
  final int notCheckedOut;
  final int successfulScans;
  final int alreadyRecorded;
  final int needsAttention;
}

class StudentScanActivity {
  const StudentScanActivity({
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
    this.student,
    this.attendance,
  });

  factory StudentScanActivity.fromJson(Map<String, dynamic> json) =>
      StudentScanActivity(
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
        student: switch (_map(json['siswa'])) {
          final value? => ScannedStudent.fromJson(value),
          _ => null,
        },
        attendance: switch (_map(json['presensi'])) {
          final value? => ScanAttendanceResult.fromJson(value),
          _ => null,
        },
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
  final ScannedStudent? student;
  final ScanAttendanceResult? attendance;

  bool get alreadyRecorded => const {
    'duplikat_cepat',
    'sudah_scan_masuk',
    'sudah_scan_pulang',
  }.contains(status);
}

class ScannedStudent {
  const ScannedStudent({
    required this.id,
    required this.name,
    required this.initials,
    this.studentNumber,
    this.nationalStudentNumber,
    this.className,
    this.photoUrl,
  });

  factory ScannedStudent.fromJson(Map<String, dynamic> json) => ScannedStudent(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    studentNumber: json['nis'] as String?,
    nationalStudentNumber: json['nisn'] as String?,
    className: json['kelas'] as String?,
    photoUrl: json['foto_url'] as String?,
    initials: json['inisial'] as String? ?? 'S',
  );

  final int id;
  final String name;
  final String? studentNumber;
  final String? nationalStudentNumber;
  final String? className;
  final String? photoUrl;
  final String initials;
}

class ScanAttendanceResult {
  const ScanAttendanceResult({
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    this.checkInTime,
    this.checkInStatus,
    this.checkOutTime,
    this.checkOutStatus,
    this.attendanceStatus,
  });

  factory ScanAttendanceResult.fromJson(Map<String, dynamic> json) =>
      ScanAttendanceResult(
        checkInTime: json['jam_masuk'] as String?,
        checkInStatus: json['status_masuk'] as String?,
        lateMinutes: _integer(json['menit_terlambat']),
        checkOutTime: json['jam_pulang'] as String?,
        checkOutStatus: json['status_pulang'] as String?,
        earlyLeaveMinutes: _integer(json['menit_pulang_cepat']),
        attendanceStatus: json['status_kehadiran'] as String?,
      );

  final String? checkInTime;
  final String? checkInStatus;
  final int lateMinutes;
  final String? checkOutTime;
  final String? checkOutStatus;
  final int earlyLeaveMinutes;
  final String? attendanceStatus;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic>? _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

int _integer(Object? value, [int fallback = 0]) =>
    value is num ? value.toInt() : fallback;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;

DateTime? _dateTime(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
