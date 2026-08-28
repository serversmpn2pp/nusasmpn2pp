class DutyAcademicYear {
  const DutyAcademicYear({
    required this.id,
    required this.name,
    this.active = false,
  });

  factory DutyAcademicYear.fromJson(Map<String, dynamic> json) =>
      DutyAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class DutyDay {
  const DutyDay({required this.code, required this.label});
  factory DutyDay.fromJson(Map<String, dynamic> json) => DutyDay(
    code: json['kode'] as String? ?? json['hari'] as String? ?? '',
    label: json['label'] as String? ?? json['hari_label'] as String? ?? '-',
  );
  final String code;
  final String label;
}

class DutyTeacher {
  const DutyTeacher({
    required this.id,
    required this.name,
    this.employeeNumber,
  });
  factory DutyTeacher.fromJson(Map<String, dynamic> json) => DutyTeacher(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    employeeNumber: json['nip'] as String?,
  );
  final int id;
  final String name;
  final String? employeeNumber;
}

class DutySchedule {
  const DutySchedule({
    required this.id,
    required this.academicYear,
    required this.teacher,
    required this.day,
    required this.dayLabel,
    required this.active,
    this.notes,
  });

  factory DutySchedule.fromJson(Map<String, dynamic> json) => DutySchedule(
    id: _integer(json['id']),
    academicYear: DutyAcademicYear.fromJson(
      _map(json['tahun_pelajaran']) ?? const {},
    ),
    teacher: DutyTeacher.fromJson(_map(json['pegawai']) ?? const {}),
    day: json['hari'] as String? ?? '',
    dayLabel: json['hari_label'] as String? ?? '-',
    active: json['aktif'] as bool? ?? false,
    notes: json['keterangan'] as String?,
  );

  final int id;
  final DutyAcademicYear academicYear;
  final DutyTeacher teacher;
  final String day;
  final String dayLabel;
  final bool active;
  final String? notes;
}

class DutyScheduleSummary {
  const DutyScheduleSummary({
    required this.activeSchedules,
    required this.teachers,
    required this.filledDays,
  });
  factory DutyScheduleSummary.fromJson(Map<String, dynamic> json) =>
      DutyScheduleSummary(
        activeSchedules: _integer(json['jadwal_aktif']),
        teachers: _integer(json['jumlah_guru']),
        filledDays: _integer(json['hari_terisi']),
      );
  final int activeSchedules;
  final int teachers;
  final int filledDays;
}

class DutyScheduleCatalog {
  const DutyScheduleCatalog({
    required this.items,
    required this.summary,
    required this.academicYears,
    required this.days,
    required this.academicYearId,
    required this.day,
    required this.status,
    required this.query,
    required this.canManage,
  });

  factory DutyScheduleCatalog.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    return DutyScheduleCatalog(
      items: _list(json['items'], DutySchedule.fromJson),
      summary: DutyScheduleSummary.fromJson(
        _map(json['ringkasan']) ?? const {},
      ),
      academicYears: _list(json['tahun_pelajaran'], DutyAcademicYear.fromJson),
      days: _list(json['hari'], DutyDay.fromJson),
      academicYearId: _nullableInteger(filter['tahun_pelajaran_id']),
      day: filter['hari'] as String? ?? 'semua',
      status: filter['status'] as String? ?? 'semua',
      query: filter['cari'] as String? ?? '',
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<DutySchedule> items;
  final DutyScheduleSummary summary;
  final List<DutyAcademicYear> academicYears;
  final List<DutyDay> days;
  final int? academicYearId;
  final String day;
  final String status;
  final String query;
  final bool canManage;
}

class DutyScheduleReference {
  const DutyScheduleReference({
    required this.academicYears,
    required this.teachers,
    required this.days,
    this.academicYearId,
  });
  factory DutyScheduleReference.fromJson(Map<String, dynamic> json) =>
      DutyScheduleReference(
        academicYears: _list(
          json['tahun_pelajaran'],
          DutyAcademicYear.fromJson,
        ),
        teachers: _list(json['guru'], DutyTeacher.fromJson),
        days: _list(json['hari'], DutyDay.fromJson),
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
      );
  final List<DutyAcademicYear> academicYears;
  final List<DutyTeacher> teachers;
  final List<DutyDay> days;
  final int? academicYearId;
}

class DutyScheduleFormValue {
  const DutyScheduleFormValue({
    required this.academicYearId,
    required this.day,
    required this.teacherIds,
    required this.active,
    this.notes,
  });
  final int academicYearId;
  final String day;
  final List<int> teacherIds;
  final bool active;
  final String? notes;
}

class MyDutySummary {
  const MyDutySummary({
    required this.total,
    required this.present,
    required this.sick,
    required this.permitted,
    required this.notScanned,
  });
  factory MyDutySummary.fromJson(Map<String, dynamic> json) => MyDutySummary(
    total: _integer(json['total']),
    present: _integer(json['hadir']),
    sick: _integer(json['sakit']),
    permitted: _integer(json['izin']),
    notScanned: _integer(json['belum_scan']),
  );
  final int total;
  final int present;
  final int sick;
  final int permitted;
  final int notScanned;
}

class DutyClass {
  const DutyClass({required this.id, required this.name});
  factory DutyClass.fromJson(Map<String, dynamic> json) =>
      DutyClass(id: _integer(json['id']), name: json['nama'] as String? ?? '-');
  final int id;
  final String name;
}

class MyDutyStudent {
  const MyDutyStudent({
    required this.classMemberId,
    required this.name,
    required this.initials,
    required this.schoolClass,
    required this.status,
    required this.statusLabel,
    required this.canRecord,
    this.studentNumber,
    this.nationalStudentNumber,
    this.photoUrl,
    this.notes,
    this.checkInTime,
  });
  factory MyDutyStudent.fromJson(Map<String, dynamic> json) {
    final student = _map(json['siswa']) ?? const {};
    final schoolClass = _map(json['kelas']) ?? const {};
    final attendance = _map(json['presensi']) ?? const {};
    return MyDutyStudent(
      classMemberId: _integer(json['anggota_kelas_id']),
      name: student['nama'] as String? ?? '-',
      initials: student['inisial'] as String? ?? 'S',
      schoolClass: schoolClass['nama'] as String? ?? '-',
      status: attendance['status'] as String? ?? 'belum_scan',
      statusLabel: attendance['status_label'] as String? ?? 'Belum scan',
      canRecord: attendance['dapat_dicatat'] as bool? ?? false,
      studentNumber: student['nis'] as String?,
      nationalStudentNumber: student['nisn'] as String?,
      photoUrl: student['foto_url'] as String?,
      notes: attendance['catatan'] as String?,
      checkInTime: attendance['jam_masuk'] as String?,
    );
  }
  final int classMemberId;
  final String name;
  final String initials;
  final String schoolClass;
  final String status;
  final String statusLabel;
  final bool canRecord;
  final String? studentNumber;
  final String? nationalStudentNumber;
  final String? photoUrl;
  final String? notes;
  final String? checkInTime;
}

class MyDutyDashboard {
  const MyDutyDashboard({
    required this.dateLabel,
    required this.academicYear,
    required this.today,
    required this.mySchedules,
    required this.activeSubjectTeacher,
    required this.canRecordToday,
    required this.items,
    required this.summary,
    required this.classes,
    required this.status,
    required this.query,
    required this.page,
    required this.hasMore,
    this.classId,
  });
  factory MyDutyDashboard.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final pagination = _map(json['paginasi']) ?? const {};
    return MyDutyDashboard(
      dateLabel: json['tanggal_label'] as String? ?? '-',
      academicYear: DutyAcademicYear.fromJson(
        _map(json['tahun_pelajaran']) ?? const {},
      ),
      today: DutyDay.fromJson(_map(json['hari_ini']) ?? const {}),
      mySchedules: _list(json['jadwal_saya'], DutyDay.fromJson),
      activeSubjectTeacher: json['guru_mapel_aktif'] as bool? ?? false,
      canRecordToday: json['dapat_mencatat_hari_ini'] as bool? ?? false,
      items: _list(json['items'], MyDutyStudent.fromJson),
      summary: MyDutySummary.fromJson(_map(json['ringkasan']) ?? const {}),
      classes: _list(json['kelas'], DutyClass.fromJson),
      classId: _nullableInteger(filter['kelas_id']),
      status: filter['status'] as String? ?? 'semua',
      query: filter['cari'] as String? ?? '',
      page: _integer(pagination['halaman']),
      hasMore: pagination['ada_halaman_berikutnya'] as bool? ?? false,
    );
  }
  final String dateLabel;
  final DutyAcademicYear academicYear;
  final DutyDay today;
  final List<DutyDay> mySchedules;
  final bool activeSubjectTeacher;
  final bool canRecordToday;
  final List<MyDutyStudent> items;
  final MyDutySummary summary;
  final List<DutyClass> classes;
  final int? classId;
  final String status;
  final String query;
  final int page;
  final bool hasMore;
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
