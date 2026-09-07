class MyAttendancePage {
  const MyAttendancePage({
    required this.mode,
    required this.students,
    required this.academicYears,
    required this.filter,
    required this.monthLabel,
    required this.today,
    required this.summary,
    required this.records,
    required this.emptyMessage,
    this.student,
    this.selectedAcademicYear,
    this.schoolClass,
  });

  factory MyAttendancePage.fromJson(Map<String, dynamic> json) =>
      MyAttendancePage(
        mode: json['mode'] as String? ?? 'siswa',
        student: json['siswa'] is Map
            ? MyAttendanceStudent.fromJson(_map(json['siswa']))
            : null,
        students: _list(json['pilihan_siswa'], MyAttendanceStudent.fromJson),
        academicYears: _list(
          json['tahun_pelajaran'],
          MyAttendanceAcademicYear.fromJson,
        ),
        selectedAcademicYear: json['tahun_pelajaran_dipilih'] is Map
            ? MyAttendanceAcademicYear.fromJson(
                _map(json['tahun_pelajaran_dipilih']),
              )
            : null,
        schoolClass: json['kelas'] is Map
            ? MyAttendanceClass.fromJson(_map(json['kelas']))
            : null,
        filter: MyAttendanceFilter.fromJson(_map(json['filter'])),
        monthLabel: json['bulan_label'] as String? ?? '-',
        today: MyAttendanceToday.fromJson(_map(json['hari_ini'])),
        summary: MyAttendanceSummary.fromJson(_map(json['ringkasan'])),
        records: _list(json['riwayat'], MyAttendanceRecord.fromJson),
        emptyMessage:
            json['pesan_kosong'] as String? ??
            'Belum ada catatan kehadiran pada bulan ini.',
      );

  final String mode;
  final MyAttendanceStudent? student;
  final List<MyAttendanceStudent> students;
  final List<MyAttendanceAcademicYear> academicYears;
  final MyAttendanceAcademicYear? selectedAcademicYear;
  final MyAttendanceClass? schoolClass;
  final MyAttendanceFilter filter;
  final String monthLabel;
  final MyAttendanceToday today;
  final MyAttendanceSummary summary;
  final List<MyAttendanceRecord> records;
  final String emptyMessage;

  bool get isParent => mode == 'orang_tua';
}

class MyAttendanceStudent {
  const MyAttendanceStudent({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
  });

  factory MyAttendanceStudent.fromJson(Map<String, dynamic> json) =>
      MyAttendanceStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
      );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
}

class MyAttendanceAcademicYear {
  const MyAttendanceAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory MyAttendanceAcademicYear.fromJson(Map<String, dynamic> json) =>
      MyAttendanceAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;

  String get label => active ? '$name · Aktif' : name;
}

class MyAttendanceClass {
  const MyAttendanceClass({
    required this.name,
    required this.grade,
    required this.membershipStatus,
    this.attendanceNumber,
  });

  factory MyAttendanceClass.fromJson(Map<String, dynamic> json) =>
      MyAttendanceClass(
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        membershipStatus: json['status_keanggotaan'] as String? ?? '-',
        attendanceNumber: _nullableInteger(json['nomor_absen']),
      );

  final String name;
  final int grade;
  final int? attendanceNumber;
  final String membershipStatus;
}

class MyAttendanceFilter {
  const MyAttendanceFilter({
    required this.studentId,
    required this.month,
    this.academicYearId,
  });

  factory MyAttendanceFilter.fromJson(Map<String, dynamic> json) =>
      MyAttendanceFilter(
        studentId: _integer(json['siswa_id']),
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        month: json['bulan'] as String? ?? '',
      );

  final int studentId;
  final int? academicYearId;
  final String month;
}

class MyAttendanceToday {
  const MyAttendanceToday({
    required this.recorded,
    required this.statusLabel,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    this.status,
    this.checkIn,
    this.checkOut,
  });

  factory MyAttendanceToday.fromJson(Map<String, dynamic> json) =>
      MyAttendanceToday(
        recorded: json['tercatat'] as bool? ?? false,
        status: json['status'] as String?,
        statusLabel: json['label_status'] as String? ?? 'Belum tercatat',
        checkIn: json['jam_masuk'] as String?,
        checkOut: json['jam_pulang'] as String?,
        lateMinutes: _integer(json['menit_terlambat']),
        earlyLeaveMinutes: _integer(json['menit_pulang_cepat']),
      );

  final bool recorded;
  final String? status;
  final String statusLabel;
  final String? checkIn;
  final String? checkOut;
  final int lateMinutes;
  final int earlyLeaveMinutes;
}

class MyAttendanceSummary {
  const MyAttendanceSummary({
    required this.total,
    required this.present,
    required this.sick,
    required this.permitted,
    required this.absent,
    required this.late,
    required this.lateMinutes,
    required this.earlyLeave,
    required this.earlyLeaveMinutes,
    required this.presentPercentage,
  });

  factory MyAttendanceSummary.fromJson(Map<String, dynamic> json) =>
      MyAttendanceSummary(
        total: _integer(json['total_catatan']),
        present: _integer(json['hadir']),
        sick: _integer(json['sakit']),
        permitted: _integer(json['izin']),
        absent: _integer(json['alfa']),
        late: _integer(json['terlambat']),
        lateMinutes: _integer(json['menit_terlambat']),
        earlyLeave: _integer(json['pulang_cepat']),
        earlyLeaveMinutes: _integer(json['menit_pulang_cepat']),
        presentPercentage: _decimal(json['persentase_hadir']),
      );

  final int total;
  final int present;
  final int sick;
  final int permitted;
  final int absent;
  final int late;
  final int lateMinutes;
  final int earlyLeave;
  final int earlyLeaveMinutes;
  final double presentPercentage;
}

class MyAttendanceRecord {
  const MyAttendanceRecord({
    required this.id,
    required this.dateLabel,
    required this.status,
    required this.statusLabel,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    required this.sourceLabel,
    this.date,
    this.checkIn,
    this.checkOut,
    this.notes,
  });

  factory MyAttendanceRecord.fromJson(Map<String, dynamic> json) =>
      MyAttendanceRecord(
        id: _integer(json['id']),
        date: json['tanggal'] as String?,
        dateLabel: json['tanggal_label'] as String? ?? '-',
        status: json['status'] as String? ?? '',
        statusLabel: json['status_label'] as String? ?? '-',
        checkIn: json['jam_masuk'] as String?,
        checkOut: json['jam_pulang'] as String?,
        lateMinutes: _integer(json['menit_terlambat']),
        earlyLeaveMinutes: _integer(json['menit_pulang_cepat']),
        sourceLabel: json['sumber_label'] as String? ?? '-',
        notes: json['catatan'] as String?,
      );

  final int id;
  final String? date;
  final String dateLabel;
  final String status;
  final String statusLabel;
  final String? checkIn;
  final String? checkOut;
  final int lateMinutes;
  final int earlyLeaveMinutes;
  final String sourceLabel;
  final String? notes;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);

double _decimal(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? 0,
  _ => 0,
};
