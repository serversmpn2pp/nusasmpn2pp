class MyTeachingSchedulePage {
  const MyTeachingSchedulePage({
    required this.academicYears,
    required this.linkedEmployee,
    required this.todayCode,
    required this.serverTime,
    required this.summary,
    required this.days,
    required this.warnings,
    this.selectedAcademicYearId,
    this.employee,
  });

  factory MyTeachingSchedulePage.fromJson(Map<String, dynamic> json) =>
      MyTeachingSchedulePage(
        academicYears: _list(
          json['tahun_pelajaran'],
          TeachingAcademicYear.fromJson,
        ),
        selectedAcademicYearId: _nullableInteger(json['tahun_terpilih_id']),
        employee: json['pegawai'] is Map<String, dynamic>
            ? TeachingEmployee.fromJson(json['pegawai'] as Map<String, dynamic>)
            : null,
        linkedEmployee: json['terhubung_pegawai'] as bool? ?? false,
        todayCode: json['hari_ini'] as String? ?? 'senin',
        serverTime: DateTime.tryParse(json['waktu_server'] as String? ?? ''),
        summary: TeachingScheduleSummary.fromJson(_map(json['ringkasan'])),
        days: _list(json['hari'], TeachingScheduleDay.fromJson),
        warnings: (json['peringatan'] as List<dynamic>? ?? const [])
            .whereType<String>()
            .toList(growable: false),
      );

  final List<TeachingAcademicYear> academicYears;
  final int? selectedAcademicYearId;
  final TeachingEmployee? employee;
  final bool linkedEmployee;
  final String todayCode;
  final DateTime? serverTime;
  final TeachingScheduleSummary summary;
  final List<TeachingScheduleDay> days;
  final List<String> warnings;

  TeachingScheduleDay? dayByCode(String code) {
    for (final day in days) {
      if (day.code == code) return day;
    }
    return null;
  }
}

class TeachingAcademicYear {
  const TeachingAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory TeachingAcademicYear.fromJson(Map<String, dynamic> json) =>
      TeachingAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class TeachingEmployee {
  const TeachingEmployee({
    required this.id,
    required this.name,
    this.nip,
    this.position,
  });

  factory TeachingEmployee.fromJson(Map<String, dynamic> json) =>
      TeachingEmployee(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        position: json['jabatan'] as String?,
      );

  final int id;
  final String name;
  final String? nip;
  final String? position;
}

class TeachingScheduleSummary {
  const TeachingScheduleSummary({
    required this.teachingPeriods,
    required this.classes,
    required this.subjects,
    required this.teachingDays,
    required this.todaySchedules,
  });

  factory TeachingScheduleSummary.fromJson(Map<String, dynamic> json) =>
      TeachingScheduleSummary(
        teachingPeriods: _integer(json['jam_mengajar']),
        classes: _integer(json['kelas']),
        subjects: _integer(json['mata_pelajaran']),
        teachingDays: _integer(json['hari_mengajar']),
        todaySchedules: _integer(json['jadwal_hari_ini']),
      );

  final int teachingPeriods;
  final int classes;
  final int subjects;
  final int teachingDays;
  final int todaySchedules;
}

class TeachingScheduleDay {
  const TeachingScheduleDay({
    required this.code,
    required this.label,
    required this.today,
    required this.count,
    required this.schedules,
  });

  factory TeachingScheduleDay.fromJson(Map<String, dynamic> json) =>
      TeachingScheduleDay(
        code: json['kode'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
        today: json['hari_ini'] as bool? ?? false,
        count: _integer(json['jumlah']),
        schedules: _list(json['jadwal'], TeachingScheduleItem.fromJson),
      );

  final String code;
  final String label;
  final bool today;
  final int count;
  final List<TeachingScheduleItem> schedules;
}

class TeachingScheduleItem {
  const TeachingScheduleItem({
    required this.id,
    required this.ongoing,
    this.period,
    this.subject,
    this.schoolClass,
    this.note,
  });

  factory TeachingScheduleItem.fromJson(Map<String, dynamic> json) =>
      TeachingScheduleItem(
        id: _integer(json['id']),
        period: json['jam'] is Map<String, dynamic>
            ? TeachingPeriod.fromJson(json['jam'] as Map<String, dynamic>)
            : null,
        subject: json['mata_pelajaran'] is Map<String, dynamic>
            ? TeachingSubject.fromJson(
                json['mata_pelajaran'] as Map<String, dynamic>,
              )
            : null,
        schoolClass: json['kelas'] is Map<String, dynamic>
            ? TeachingClass.fromJson(json['kelas'] as Map<String, dynamic>)
            : null,
        ongoing: json['sedang_berlangsung'] as bool? ?? false,
        note: json['keterangan'] as String?,
      );

  final int id;
  final TeachingPeriod? period;
  final TeachingSubject? subject;
  final TeachingClass? schoolClass;
  final bool ongoing;
  final String? note;
}

class TeachingPeriod {
  const TeachingPeriod({
    required this.id,
    required this.number,
    required this.label,
    this.start,
    this.end,
  });

  factory TeachingPeriod.fromJson(Map<String, dynamic> json) => TeachingPeriod(
    id: _integer(json['id']),
    number: _integer(json['nomor']),
    label: json['label'] as String? ?? '-',
    start: json['mulai'] as String?,
    end: json['selesai'] as String?,
  );

  final int id;
  final int number;
  final String label;
  final String? start;
  final String? end;

  String get timeLabel => '${start ?? '--:--'} – ${end ?? '--:--'}';
}

class TeachingSubject {
  const TeachingSubject({required this.id, required this.name, this.code});

  factory TeachingSubject.fromJson(Map<String, dynamic> json) =>
      TeachingSubject(
        id: _integer(json['id']),
        code: json['kode'] as String?,
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String? code;
  final String name;
}

class TeachingClass {
  const TeachingClass({
    required this.id,
    required this.name,
    required this.grade,
  });

  factory TeachingClass.fromJson(Map<String, dynamic> json) => TeachingClass(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    grade: _integer(json['tingkat']),
  );

  final int id;
  final String name;
  final int grade;
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map<String, dynamic> ? value : const {};

List<T> _list<T>(dynamic value, T Function(Map<String, dynamic>) parser) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(parser)
        .toList(growable: false);

int _integer(dynamic value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(dynamic value) => value == null ? null : _integer(value);
