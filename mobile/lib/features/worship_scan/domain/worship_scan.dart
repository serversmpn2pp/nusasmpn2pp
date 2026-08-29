class WorshipScanDashboard {
  const WorshipScanDashboard({
    required this.academicYearName,
    required this.dateLabel,
    required this.serverTime,
    required this.scanOpen,
    required this.scheduleStatus,
    required this.selectedScheduleId,
    required this.schedules,
    required this.todayCount,
    required this.recentAttendances,
  });

  factory WorshipScanDashboard.fromJson(Map<String, dynamic> json) {
    final academicYear = _mapOrNull(json['tahun_pelajaran']);
    return WorshipScanDashboard(
      academicYearName: academicYear?['nama'] as String?,
      dateLabel: json['tanggal_label'] as String? ?? '-',
      serverTime: json['waktu_server'] as String? ?? '',
      scanOpen: json['scan_dibuka'] as bool? ?? false,
      scheduleStatus: WorshipScanScheduleStatus.fromJson(
        _map(json['status_jadwal']),
      ),
      selectedScheduleId: _nullableInteger(json['jadwal_dipilih_id']),
      schedules: _list(json['jadwal'], WorshipScanSchedule.fromJson),
      todayCount: _integer(json['jumlah_hari_ini']),
      recentAttendances: _list(
        json['presensi_terbaru'],
        WorshipScanAttendance.fromJson,
      ),
    );
  }

  final String? academicYearName;
  final String dateLabel;
  final String serverTime;
  final bool scanOpen;
  final WorshipScanScheduleStatus scheduleStatus;
  final int? selectedScheduleId;
  final List<WorshipScanSchedule> schedules;
  final int todayCount;
  final List<WorshipScanAttendance> recentAttendances;

  WorshipScanSchedule? get selectedSchedule {
    for (final schedule in schedules) {
      if (schedule.id == selectedScheduleId) return schedule;
    }
    return null;
  }

  WorshipScanDashboard applyResult(WorshipScanResult result) {
    final attendance = result.attendance;
    final recent = [...recentAttendances];
    if (result.isNew && attendance != null) {
      recent
        ..removeWhere((item) => item.id == attendance.id)
        ..insert(0, attendance);
      if (recent.length > 8) recent.removeRange(8, recent.length);
    }
    return WorshipScanDashboard(
      academicYearName: academicYearName,
      dateLabel: dateLabel,
      serverTime: serverTime,
      scanOpen: scanOpen,
      scheduleStatus: scheduleStatus,
      selectedScheduleId: selectedScheduleId,
      schedules: schedules,
      todayCount: result.todayCount,
      recentAttendances: recent,
    );
  }
}

class WorshipScanScheduleStatus {
  const WorshipScanScheduleStatus({
    required this.code,
    required this.label,
    required this.message,
  });

  factory WorshipScanScheduleStatus.fromJson(Map<String, dynamic> json) =>
      WorshipScanScheduleStatus(
        code: json['kode'] as String? ?? 'tidak_ada',
        label: json['label'] as String? ?? 'Tidak ada jadwal',
        message: json['pesan'] as String? ?? '',
      );

  final String code;
  final String label;
  final String message;
}

class WorshipScanSchedule {
  const WorshipScanSchedule({
    required this.id,
    required this.activityId,
    required this.activity,
    required this.activityCode,
    required this.scanStart,
    required this.eventTime,
    required this.scanEnd,
    required this.scanRange,
    required this.scanOpen,
    this.notes,
  });

  factory WorshipScanSchedule.fromJson(Map<String, dynamic> json) =>
      WorshipScanSchedule(
        id: _integer(json['id']),
        activityId: _integer(json['kegiatan_id']),
        activity: json['kegiatan'] as String? ?? 'Kegiatan Ibadah',
        activityCode: json['kode_kegiatan'] as String? ?? '',
        scanStart: json['jam_scan_mulai'] as String? ?? '-',
        eventTime: json['jam_pelaksanaan'] as String? ?? '-',
        scanEnd: json['jam_scan_selesai'] as String? ?? '-',
        scanRange: json['rentang_scan'] as String? ?? '-',
        scanOpen: json['scan_dibuka'] as bool? ?? false,
        notes: json['keterangan'] as String?,
      );

  final int id;
  final int activityId;
  final String activity;
  final String activityCode;
  final String scanStart;
  final String eventTime;
  final String scanEnd;
  final String scanRange;
  final bool scanOpen;
  final String? notes;
}

class WorshipScanAttendance {
  const WorshipScanAttendance({
    required this.id,
    required this.studentName,
    required this.nisn,
    required this.className,
    required this.scanTime,
    this.photoUrl,
  });

  factory WorshipScanAttendance.fromJson(Map<String, dynamic> json) =>
      WorshipScanAttendance(
        id: _integer(json['id']),
        studentName: json['nama_lengkap'] as String? ?? '-',
        nisn: json['nisn'] as String? ?? '-',
        className: json['kelas'] as String? ?? '-',
        scanTime: json['waktu_scan'] as String? ?? '-',
        photoUrl: json['foto_url'] as String?,
      );

  final int id;
  final String studentName;
  final String nisn;
  final String className;
  final String scanTime;
  final String? photoUrl;
}

class WorshipScanStudent {
  const WorshipScanStudent({
    required this.name,
    required this.nisn,
    required this.className,
    this.photoUrl,
  });

  factory WorshipScanStudent.fromJson(Map<String, dynamic> json) =>
      WorshipScanStudent(
        name: json['nama_lengkap'] as String? ?? '-',
        nisn: json['nisn'] as String? ?? '-',
        className: json['kelas'] as String? ?? '-',
        photoUrl: json['foto_url'] as String?,
      );

  final String name;
  final String nisn;
  final String className;
  final String? photoUrl;
}

class WorshipScanResult {
  const WorshipScanResult({
    required this.success,
    required this.isNew,
    required this.status,
    required this.message,
    required this.absencePeriodCompleted,
    required this.serverTime,
    required this.todayCount,
    this.attendance,
    this.student,
  });

  factory WorshipScanResult.fromJson(Map<String, dynamic> json) {
    final attendance = _mapOrNull(json['presensi']);
    final student = _mapOrNull(json['siswa']);
    return WorshipScanResult(
      success: json['berhasil'] as bool? ?? false,
      isNew: json['baru'] as bool? ?? false,
      status: json['status'] as String? ?? 'gagal',
      message: json['pesan'] as String? ?? 'Hasil scan belum tersedia.',
      absencePeriodCompleted:
          json['periode_berhalangan_diselesaikan'] as bool? ?? false,
      serverTime: json['waktu_server'] as String? ?? '',
      todayCount: _integer(json['jumlah_hari_ini']),
      attendance: attendance == null
          ? null
          : WorshipScanAttendance.fromJson(attendance),
      student: student == null ? null : WorshipScanStudent.fromJson(student),
    );
  }

  final bool success;
  final bool isNew;
  final String status;
  final String message;
  final bool absencePeriodCompleted;
  final String serverTime;
  final int todayCount;
  final WorshipScanAttendance? attendance;
  final WorshipScanStudent? student;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

Map<String, dynamic>? _mapOrNull(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) fromJson) =>
    value is List ? value.map((item) => fromJson(_map(item))).toList() : [];

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);
