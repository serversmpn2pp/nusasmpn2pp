class PersonalWorshipPage {
  const PersonalWorshipPage({
    required this.mode,
    required this.students,
    required this.academicYears,
    required this.filter,
    required this.monthLabel,
    required this.summary,
    required this.records,
    required this.emptyMessage,
    required this.privacyMessage,
    this.student,
    this.selectedAcademicYear,
    this.schoolClass,
  });

  factory PersonalWorshipPage.fromJson(Map<String, dynamic> json) =>
      PersonalWorshipPage(
        mode: json['mode'] as String? ?? 'siswa',
        student: json['siswa'] is Map
            ? PersonalWorshipStudent.fromJson(_map(json['siswa']))
            : null,
        students: _list(json['pilihan_siswa'], PersonalWorshipStudent.fromJson),
        academicYears: _list(
          json['tahun_pelajaran'],
          PersonalWorshipAcademicYear.fromJson,
        ),
        selectedAcademicYear: json['tahun_pelajaran_dipilih'] is Map
            ? PersonalWorshipAcademicYear.fromJson(
                _map(json['tahun_pelajaran_dipilih']),
              )
            : null,
        schoolClass: json['kelas'] is Map
            ? PersonalWorshipClass.fromJson(_map(json['kelas']))
            : null,
        filter: PersonalWorshipFilter.fromJson(_map(json['filter'])),
        monthLabel: json['bulan_label'] as String? ?? '-',
        summary: PersonalWorshipSummary.fromJson(_map(json['ringkasan'])),
        records: _list(json['riwayat'], PersonalWorshipRecord.fromJson),
        emptyMessage:
            json['pesan_kosong'] as String? ??
            'Belum ada kegiatan ibadah pada bulan ini.',
        privacyMessage:
            json['pesan_privasi'] as String? ??
            'Status berhalangan ditampilkan tanpa catatan privat.',
      );

  final String mode;
  final PersonalWorshipStudent? student;
  final List<PersonalWorshipStudent> students;
  final List<PersonalWorshipAcademicYear> academicYears;
  final PersonalWorshipAcademicYear? selectedAcademicYear;
  final PersonalWorshipClass? schoolClass;
  final PersonalWorshipFilter filter;
  final String monthLabel;
  final PersonalWorshipSummary summary;
  final List<PersonalWorshipRecord> records;
  final String emptyMessage;
  final String privacyMessage;

  bool get isParent => mode == 'orang_tua';
}

class PersonalWorshipStudent {
  const PersonalWorshipStudent({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
    this.gender,
  });

  factory PersonalWorshipStudent.fromJson(Map<String, dynamic> json) =>
      PersonalWorshipStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        gender: json['jenis_kelamin'] as String?,
      );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? gender;
}

class PersonalWorshipAcademicYear {
  const PersonalWorshipAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory PersonalWorshipAcademicYear.fromJson(Map<String, dynamic> json) =>
      PersonalWorshipAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;

  String get label => active ? '$name · Aktif' : name;
}

class PersonalWorshipClass {
  const PersonalWorshipClass({
    required this.name,
    required this.grade,
    required this.membershipStatus,
    this.attendanceNumber,
  });

  factory PersonalWorshipClass.fromJson(Map<String, dynamic> json) =>
      PersonalWorshipClass(
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        attendanceNumber: _nullableInteger(json['nomor_absen']),
        membershipStatus: json['status_keanggotaan'] as String? ?? '-',
      );

  final String name;
  final int grade;
  final int? attendanceNumber;
  final String membershipStatus;
}

class PersonalWorshipFilter {
  const PersonalWorshipFilter({
    required this.studentId,
    required this.month,
    this.academicYearId,
  });

  factory PersonalWorshipFilter.fromJson(Map<String, dynamic> json) =>
      PersonalWorshipFilter(
        studentId: _integer(json['siswa_id']),
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        month: json['bulan'] as String? ?? '',
      );

  final int studentId;
  final int? academicYearId;
  final String month;
}

class PersonalWorshipSummary {
  const PersonalWorshipSummary({
    required this.total,
    required this.completed,
    required this.missed,
    required this.excused,
    required this.absentFromSchool,
    required this.notRequired,
    required this.requiredCount,
    required this.percentage,
  });

  factory PersonalWorshipSummary.fromJson(Map<String, dynamic> json) =>
      PersonalWorshipSummary(
        total: _integer(json['total']),
        completed: _integer(json['sudah']),
        missed: _integer(json['belum']),
        excused: _integer(json['berhalangan']),
        absentFromSchool: _integer(json['tidak_hadir']),
        notRequired: _integer(json['tidak_wajib']),
        requiredCount: _integer(json['wajib']),
        percentage: _integer(json['persentase']),
      );

  final int total;
  final int completed;
  final int missed;
  final int excused;
  final int absentFromSchool;
  final int notRequired;
  final int requiredCount;
  final int percentage;
}

class PersonalWorshipRecord {
  const PersonalWorshipRecord({
    required this.scheduleId,
    required this.date,
    required this.dateLabel,
    required this.day,
    required this.time,
    required this.activity,
    required this.status,
    required this.statusLabel,
    required this.schoolAttendanceLabel,
    this.recordedAt,
  });

  factory PersonalWorshipRecord.fromJson(Map<String, dynamic> json) =>
      PersonalWorshipRecord(
        scheduleId: _integer(json['jadwal_id']),
        date: json['tanggal'] as String? ?? '',
        dateLabel: json['tanggal_label'] as String? ?? '-',
        day: json['hari'] as String? ?? '-',
        time: json['jam_pelaksanaan'] as String? ?? '-',
        activity: PersonalWorshipActivity.fromJson(_map(json['kegiatan'])),
        status: json['status'] as String? ?? '',
        statusLabel: json['status_label'] as String? ?? '-',
        schoolAttendanceLabel: json['status_kehadiran_label'] as String? ?? '-',
        recordedAt: json['waktu_tercatat'] as String?,
      );

  final int scheduleId;
  final String date;
  final String dateLabel;
  final String day;
  final String time;
  final PersonalWorshipActivity activity;
  final String status;
  final String statusLabel;
  final String schoolAttendanceLabel;
  final String? recordedAt;
}

class PersonalWorshipActivity {
  const PersonalWorshipActivity({
    required this.id,
    required this.code,
    required this.name,
    required this.maleOnly,
  });

  factory PersonalWorshipActivity.fromJson(Map<String, dynamic> json) =>
      PersonalWorshipActivity(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '',
        name: json['nama'] as String? ?? 'Kegiatan ibadah',
        maleOnly: json['khusus_laki_laki'] as bool? ?? false,
      );

  final int id;
  final String code;
  final String name;
  final bool maleOnly;
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
