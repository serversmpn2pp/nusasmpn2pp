import 'dart:typed_data';

class ReportAcademicYear {
  const ReportAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });
  factory ReportAcademicYear.fromJson(Map<String, dynamic> json) =>
      ReportAcademicYear(
        id: _int(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );
  final int id;
  final String name;
  final bool active;
}

class ReportClassOption {
  const ReportClassOption({required this.id, required this.name});
  factory ReportClassOption.fromJson(Map<String, dynamic> json) =>
      ReportClassOption(
        id: _int(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class StudentAttendanceReportSummary {
  const StudentAttendanceReportSummary({
    required this.students,
    required this.effectiveDays,
    required this.present,
    required this.permitted,
    required this.sick,
    required this.absent,
    required this.late,
    required this.lateMinutes,
    required this.earlyLeave,
    required this.earlyLeaveMinutes,
    required this.averageAttendance,
  });
  factory StudentAttendanceReportSummary.fromJson(Map<String, dynamic> json) =>
      StudentAttendanceReportSummary(
        students: _int(json['siswa']),
        effectiveDays: _int(json['hari_efektif']),
        present: _int(json['hadir']),
        permitted: _int(json['izin']),
        sick: _int(json['sakit']),
        absent: _int(json['alfa']),
        late: _int(json['terlambat']),
        lateMinutes: _int(json['menit_terlambat']),
        earlyLeave: _int(json['pulang_cepat']),
        earlyLeaveMinutes: _int(json['menit_pulang_cepat']),
        averageAttendance: _double(
          json['rata_persentase_hadir'] ?? json['persentase_hadir'],
        ),
      );
  final int students,
      effectiveDays,
      present,
      permitted,
      sick,
      absent,
      late,
      lateMinutes,
      earlyLeave,
      earlyLeaveMinutes;
  final double averageAttendance;
}

class StudentAttendanceReportItem {
  const StudentAttendanceReportItem({
    required this.classMemberId,
    required this.name,
    required this.initials,
    required this.className,
    required this.effectiveDays,
    required this.present,
    required this.permitted,
    required this.sick,
    required this.absent,
    required this.late,
    required this.lateMinutes,
    required this.earlyLeave,
    required this.earlyLeaveMinutes,
    required this.attendancePercentage,
    this.number,
    this.studentNumber,
    this.nationalStudentNumber,
    this.photoUrl,
  });
  factory StudentAttendanceReportItem.fromJson(Map<String, dynamic> json) {
    final summary = _map(json['ringkasan']);
    return StudentAttendanceReportItem(
      classMemberId: _int(json['anggota_kelas_id']),
      name: json['nama'] as String? ?? '-',
      initials: json['inisial'] as String? ?? 'S',
      className: json['kelas'] as String? ?? '-',
      number: _nullableInt(json['nomor_absen']),
      studentNumber: json['nis'] as String?,
      nationalStudentNumber: json['nisn'] as String?,
      photoUrl: json['foto_url'] as String?,
      effectiveDays: _int(summary['hari_efektif']),
      present: _int(summary['hadir']),
      permitted: _int(summary['izin']),
      sick: _int(summary['sakit']),
      absent: _int(summary['alfa']),
      late: _int(summary['terlambat']),
      lateMinutes: _int(summary['menit_terlambat']),
      earlyLeave: _int(summary['pulang_cepat']),
      earlyLeaveMinutes: _int(summary['menit_pulang_cepat']),
      attendancePercentage: _double(summary['persentase_hadir']),
    );
  }
  final int classMemberId;
  final int? number;
  final String name, initials, className;
  final String? studentNumber, nationalStudentNumber, photoUrl;
  final int effectiveDays,
      present,
      permitted,
      sick,
      absent,
      late,
      lateMinutes,
      earlyLeave,
      earlyLeaveMinutes;
  final double attendancePercentage;
}

class StudentAttendanceReportPage {
  const StudentAttendanceReportPage({
    required this.period,
    required this.periodLabel,
    required this.startDate,
    required this.endDate,
    required this.summary,
    required this.items,
    required this.academicYears,
    required this.classes,
    required this.date,
    required this.month,
    required this.semester,
    required this.query,
    required this.page,
    required this.hasMore,
    required this.guardianScope,
    required this.canExport,
    this.academicYearId,
    this.classId,
  });
  factory StudentAttendanceReportPage.fromJson(Map<String, dynamic> json) {
    final period = _map(json['periode']);
    final filter = _map(json['filter']);
    final pagination = _map(json['paginasi']);
    final access = _map(json['hak_akses']);
    return StudentAttendanceReportPage(
      period: period['jenis'] as String? ?? 'bulanan',
      periodLabel: period['label'] as String? ?? '-',
      startDate: period['tanggal_mulai'] as String? ?? '',
      endDate: period['tanggal_selesai'] as String? ?? '',
      summary: StudentAttendanceReportSummary.fromJson(_map(json['ringkasan'])),
      items: _list(json['items'], StudentAttendanceReportItem.fromJson),
      academicYears: _list(
        json['tahun_pelajaran'],
        ReportAcademicYear.fromJson,
      ),
      classes: _list(json['kelas'], ReportClassOption.fromJson),
      academicYearId: _nullableInt(filter['tahun_pelajaran_id']),
      classId: _nullableInt(filter['kelas_id']),
      date: filter['tanggal'] as String? ?? '',
      month: filter['bulan'] as String? ?? '',
      semester: filter['semester'] as String? ?? 'ganjil',
      query: filter['cari'] as String? ?? '',
      page: _int(pagination['halaman']),
      hasMore: pagination['ada_halaman_berikutnya'] as bool? ?? false,
      guardianScope: access['cakupan_wali_kelas'] as bool? ?? false,
      canExport: access['dapat_export'] as bool? ?? false,
    );
  }
  final String period,
      periodLabel,
      startDate,
      endDate,
      date,
      month,
      semester,
      query;
  final StudentAttendanceReportSummary summary;
  final List<StudentAttendanceReportItem> items;
  final List<ReportAcademicYear> academicYears;
  final List<ReportClassOption> classes;
  final int? academicYearId, classId;
  final int page;
  final bool hasMore, guardianScope, canExport;
}

class StudentAttendanceReportDay {
  const StudentAttendanceReportDay({
    required this.date,
    required this.dateLabel,
    required this.status,
    required this.statusLabel,
    required this.inferred,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    this.checkIn,
    this.checkOut,
    this.source,
    this.notes,
  });
  factory StudentAttendanceReportDay.fromJson(Map<String, dynamic> json) =>
      StudentAttendanceReportDay(
        date: json['tanggal'] as String? ?? '',
        dateLabel: json['tanggal_label'] as String? ?? '-',
        status: json['status'] as String? ?? 'alfa',
        statusLabel: json['status_label'] as String? ?? 'Alfa',
        inferred: json['inferensi'] as bool? ?? false,
        checkIn: json['jam_masuk'] as String?,
        checkOut: json['jam_pulang'] as String?,
        lateMinutes: _int(json['menit_terlambat']),
        earlyLeaveMinutes: _int(json['menit_pulang_cepat']),
        source: json['sumber'] as String?,
        notes: json['catatan'] as String?,
      );
  final String date, dateLabel, status, statusLabel;
  final bool inferred;
  final String? checkIn, checkOut, source, notes;
  final int lateMinutes, earlyLeaveMinutes;
}

class StudentAttendanceReportDetail {
  const StudentAttendanceReportDetail({
    required this.student,
    required this.periodLabel,
    required this.summary,
    required this.days,
  });
  factory StudentAttendanceReportDetail.fromJson(Map<String, dynamic> json) {
    final student = _map(json['siswa']);
    final period = _map(json['periode']);
    return StudentAttendanceReportDetail(
      student: StudentAttendanceReportItem.fromJson({
        ...student,
        'ringkasan': _map(json['ringkasan']),
      }),
      periodLabel: period['label'] as String? ?? '-',
      summary: StudentAttendanceReportSummary.fromJson({
        ..._map(json['ringkasan']),
        'siswa': 1,
      }),
      days: _list(json['rincian'], StudentAttendanceReportDay.fromJson),
    );
  }
  final StudentAttendanceReportItem student;
  final String periodLabel;
  final StudentAttendanceReportSummary summary;
  final List<StudentAttendanceReportDay> days;
}

class AttendanceReportDownload {
  const AttendanceReportDownload({required this.fileName, required this.bytes});
  final String fileName;
  final Uint8List bytes;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);
int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
int? _nullableInt(Object? value) =>
    value == null ? null : int.tryParse('$value');
double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
