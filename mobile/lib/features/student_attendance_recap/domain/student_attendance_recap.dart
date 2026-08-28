class AttendanceAcademicYear {
  const AttendanceAcademicYear({
    required this.id,
    required this.name,
    this.active = false,
  });
  factory AttendanceAcademicYear.fromJson(Map<String, dynamic> json) =>
      AttendanceAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );
  final int id;
  final String name;
  final bool active;
}

class AttendanceClassOption {
  const AttendanceClassOption({
    required this.id,
    required this.name,
    this.level,
  });
  factory AttendanceClassOption.fromJson(Map<String, dynamic> json) =>
      AttendanceClassOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        level: _nullableInteger(json['tingkat']),
      );
  final int id;
  final String name;
  final int? level;
}

class StudentAttendanceSummary {
  const StudentAttendanceSummary({
    required this.total,
    required this.present,
    required this.permitted,
    required this.sick,
    required this.absent,
    required this.notScanned,
    required this.late,
    required this.earlyLeave,
    required this.notCheckedOut,
  });
  factory StudentAttendanceSummary.fromJson(Map<String, dynamic> json) =>
      StudentAttendanceSummary(
        total: _integer(json['total']),
        present: _integer(json['hadir']),
        permitted: _integer(json['izin']),
        sick: _integer(json['sakit']),
        absent: _integer(json['alfa']),
        notScanned: _integer(json['belum_scan']),
        late: _integer(json['terlambat']),
        earlyLeave: _integer(json['pulang_cepat']),
        notCheckedOut: _integer(json['belum_pulang']),
      );
  final int total;
  final int present;
  final int permitted;
  final int sick;
  final int absent;
  final int notScanned;
  final int late;
  final int earlyLeave;
  final int notCheckedOut;
}

class AttendanceCorrectionAccess {
  const AttendanceCorrectionAccess({
    required this.allowed,
    required this.todayOnly,
    this.reason,
  });
  factory AttendanceCorrectionAccess.fromJson(Map<String, dynamic> json) =>
      AttendanceCorrectionAccess(
        allowed: json['dapat'] as bool? ?? false,
        todayOnly: json['terbatas_hari_ini'] as bool? ?? false,
        reason: json['alasan'] as String?,
      );
  final bool allowed;
  final bool todayOnly;
  final String? reason;
}

class StudentAttendanceRecord {
  const StudentAttendanceRecord({
    required this.classMemberId,
    required this.name,
    required this.initials,
    required this.className,
    required this.status,
    required this.statusLabel,
    required this.source,
    required this.sourceLabel,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    required this.notCheckedOut,
    required this.correction,
    this.studentNumber,
    this.nationalStudentNumber,
    this.photoUrl,
    this.checkInTime,
    this.checkOutTime,
    this.checkInStatus,
    this.checkOutStatus,
    this.notes,
  });
  factory StudentAttendanceRecord.fromJson(Map<String, dynamic> json) {
    final student = _map(json['siswa']) ?? const {};
    final schoolClass = _map(json['kelas']) ?? const {};
    final attendance = _map(json['presensi']) ?? const {};
    return StudentAttendanceRecord(
      classMemberId: _integer(json['anggota_kelas_id']),
      name: student['nama'] as String? ?? '-',
      initials: student['inisial'] as String? ?? 'S',
      className: schoolClass['nama'] as String? ?? '-',
      studentNumber: student['nis'] as String?,
      nationalStudentNumber: student['nisn'] as String?,
      photoUrl: student['foto_url'] as String?,
      status: attendance['status'] as String? ?? 'belum_scan',
      statusLabel: attendance['status_label'] as String? ?? 'Belum scan',
      source: attendance['sumber'] as String? ?? 'inferensi',
      sourceLabel: attendance['sumber_label'] as String? ?? '-',
      checkInTime: attendance['jam_masuk'] as String?,
      checkOutTime: attendance['jam_pulang'] as String?,
      checkInStatus: attendance['status_masuk'] as String?,
      checkOutStatus: attendance['status_pulang'] as String?,
      lateMinutes: _integer(attendance['menit_terlambat']),
      earlyLeaveMinutes: _integer(attendance['menit_pulang_cepat']),
      notCheckedOut: attendance['belum_pulang'] as bool? ?? false,
      notes: attendance['catatan'] as String?,
      correction: AttendanceCorrectionAccess.fromJson(
        _map(json['koreksi']) ?? const {},
      ),
    );
  }
  final int classMemberId;
  final String name;
  final String initials;
  final String className;
  final String? studentNumber;
  final String? nationalStudentNumber;
  final String? photoUrl;
  final String status;
  final String statusLabel;
  final String source;
  final String sourceLabel;
  final String? checkInTime;
  final String? checkOutTime;
  final String? checkInStatus;
  final String? checkOutStatus;
  final int lateMinutes;
  final int earlyLeaveMinutes;
  final bool notCheckedOut;
  final String? notes;
  final AttendanceCorrectionAccess correction;
}

class StudentAttendanceRecapPage {
  const StudentAttendanceRecapPage({
    required this.date,
    required this.dateLabel,
    required this.items,
    required this.summary,
    required this.academicYears,
    required this.classes,
    required this.status,
    required this.query,
    required this.page,
    required this.hasMore,
    required this.canCorrect,
    required this.todayOnly,
    required this.guardianScope,
    this.academicYearId,
    this.classId,
  });
  factory StudentAttendanceRecapPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final pagination = _map(json['paginasi']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    return StudentAttendanceRecapPage(
      date: json['tanggal'] as String? ?? '',
      dateLabel: json['tanggal_label'] as String? ?? '-',
      items: _list(json['items'], StudentAttendanceRecord.fromJson),
      summary: StudentAttendanceSummary.fromJson(
        _map(json['ringkasan']) ?? const {},
      ),
      academicYears: _list(
        json['tahun_pelajaran'],
        AttendanceAcademicYear.fromJson,
      ),
      classes: _list(json['kelas'], AttendanceClassOption.fromJson),
      academicYearId: _nullableInteger(filter['tahun_pelajaran_id']),
      classId: _nullableInteger(filter['kelas_id']),
      status: filter['status'] as String? ?? 'semua',
      query: filter['cari'] as String? ?? '',
      page: _integer(pagination['halaman']),
      hasMore: pagination['ada_halaman_berikutnya'] as bool? ?? false,
      canCorrect: access['dapat_koreksi'] as bool? ?? false,
      todayOnly: access['koreksi_hari_ini_terbatas'] as bool? ?? false,
      guardianScope: access['cakupan_wali_kelas'] as bool? ?? false,
    );
  }
  final String date;
  final String dateLabel;
  final List<StudentAttendanceRecord> items;
  final StudentAttendanceSummary summary;
  final List<AttendanceAcademicYear> academicYears;
  final List<AttendanceClassOption> classes;
  final int? academicYearId;
  final int? classId;
  final String status;
  final String query;
  final int page;
  final bool hasMore;
  final bool canCorrect;
  final bool todayOnly;
  final bool guardianScope;
}

class AttendanceHistoryEntry {
  const AttendanceHistoryEntry({
    required this.id,
    required this.afterStatus,
    required this.sourceLabel,
    this.beforeStatus,
    this.notes,
    this.createdBy,
    this.createdAt,
  });
  factory AttendanceHistoryEntry.fromJson(Map<String, dynamic> json) =>
      AttendanceHistoryEntry(
        id: _integer(json['id']),
        beforeStatus: json['status_sebelum'] as String?,
        afterStatus: json['status_sesudah'] as String? ?? '-',
        sourceLabel: json['sumber_label'] as String? ?? '-',
        notes: json['catatan'] as String?,
        createdBy: json['dibuat_oleh'] as String?,
        createdAt: DateTime.tryParse(json['dibuat_pada'] as String? ?? ''),
      );
  final int id;
  final String? beforeStatus;
  final String afterStatus;
  final String sourceLabel;
  final String? notes;
  final String? createdBy;
  final DateTime? createdAt;
}

class StudentAttendanceDetail {
  const StudentAttendanceDetail({
    required this.date,
    required this.dateLabel,
    required this.record,
    required this.scheduleAvailable,
    required this.history,
    required this.correction,
    this.officialCheckIn,
    this.officialCheckOut,
  });
  factory StudentAttendanceDetail.fromJson(Map<String, dynamic> json) {
    final schedule = _map(json['jadwal_presensi']) ?? const {};
    return StudentAttendanceDetail(
      date: json['tanggal'] as String? ?? '',
      dateLabel: json['tanggal_label'] as String? ?? '-',
      record: StudentAttendanceRecord.fromJson(_map(json['item']) ?? const {}),
      scheduleAvailable: schedule['tersedia'] as bool? ?? false,
      officialCheckIn: schedule['jam_masuk'] as String?,
      officialCheckOut: schedule['jam_pulang'] as String?,
      history: _list(json['riwayat'], AttendanceHistoryEntry.fromJson),
      correction: AttendanceCorrectionAccess.fromJson(
        _map(json['hak_akses']) ?? const {},
      ),
    );
  }
  final String date;
  final String dateLabel;
  final StudentAttendanceRecord record;
  final bool scheduleAvailable;
  final String? officialCheckIn;
  final String? officialCheckOut;
  final List<AttendanceHistoryEntry> history;
  final AttendanceCorrectionAccess correction;
}

class AttendanceCorrectionValue {
  const AttendanceCorrectionValue({
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
