class WorshipAbsenceSettingsPage {
  const WorshipAbsenceSettingsPage({
    required this.available,
    required this.summary,
    required this.employees,
    required this.classes,
    required this.assignments,
    this.academicYear,
    this.settings,
  });

  factory WorshipAbsenceSettingsPage.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    final academicYearJson = _map(json['tahun_pelajaran']);
    final settingsJson = _map(json['pengaturan']);
    return WorshipAbsenceSettingsPage(
      available: json['tersedia'] as bool? ?? false,
      academicYear: academicYearJson.isEmpty
          ? null
          : WorshipAbsenceAcademicYear.fromJson(academicYearJson),
      settings: settingsJson.isEmpty
          ? null
          : WorshipAbsenceSettings.fromJson(settingsJson),
      summary: WorshipAbsenceSummary.fromJson(_map(json['ringkasan'])),
      employees: _list(
        references['pegawai_perempuan'],
        WorshipCompanionEmployee.fromJson,
      ),
      classes: _list(references['kelas'], WorshipCompanionClass.fromJson),
      assignments: _list(
        json['penugasan'],
        WorshipCompanionAssignment.fromJson,
      ),
    );
  }

  final bool available;
  final WorshipAbsenceAcademicYear? academicYear;
  final WorshipAbsenceSettings? settings;
  final WorshipAbsenceSummary summary;
  final List<WorshipCompanionEmployee> employees;
  final List<WorshipCompanionClass> classes;
  final List<WorshipCompanionAssignment> assignments;
}

class WorshipAbsenceAcademicYear {
  const WorshipAbsenceAcademicYear({required this.id, required this.name});

  factory WorshipAbsenceAcademicYear.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class WorshipAbsenceSettings {
  const WorshipAbsenceSettings({
    required this.confirmationDayLimit,
    required this.active,
  });

  factory WorshipAbsenceSettings.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceSettings(
        confirmationDayLimit: _integer(json['batas_hari_konfirmasi']),
        active: json['aktif'] as bool? ?? false,
      );

  final int confirmationDayLimit;
  final bool active;
}

class WorshipAbsenceSummary {
  const WorshipAbsenceSummary({
    required this.activeCompanions,
    required this.coveredClasses,
    required this.classCount,
  });

  factory WorshipAbsenceSummary.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceSummary(
        activeCompanions: _integer(json['pendamping_aktif']),
        coveredClasses: _integer(json['kelas_tercakup']),
        classCount: _integer(json['jumlah_kelas']),
      );

  final int activeCompanions;
  final int coveredClasses;
  final int classCount;
}

class WorshipCompanionEmployee {
  const WorshipCompanionEmployee({
    required this.id,
    required this.name,
    required this.accountActive,
    this.nip,
    this.position,
  });

  factory WorshipCompanionEmployee.fromJson(Map<String, dynamic> json) =>
      WorshipCompanionEmployee(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        position: json['jabatan'] as String?,
        accountActive: json['akun_aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String? nip;
  final String? position;
  final bool accountActive;
}

class WorshipCompanionClass {
  const WorshipCompanionClass({
    required this.id,
    required this.name,
    required this.grade,
  });

  factory WorshipCompanionClass.fromJson(Map<String, dynamic> json) =>
      WorshipCompanionClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
      );

  final int id;
  final String name;
  final int grade;
}

class WorshipCompanionAssignment {
  const WorshipCompanionAssignment({
    required this.id,
    required this.employeeId,
    required this.allClasses,
    required this.active,
    required this.employeeName,
    required this.classes,
    this.employeeNip,
    this.employeePosition,
    this.assignedBy,
    this.updatedAt,
  });

  factory WorshipCompanionAssignment.fromJson(Map<String, dynamic> json) {
    final employee = _map(json['pegawai']);
    return WorshipCompanionAssignment(
      id: _integer(json['id']),
      employeeId: _integer(json['pegawai_id']),
      allClasses: json['semua_kelas'] as bool? ?? false,
      active: json['aktif'] as bool? ?? false,
      employeeName: employee['nama'] as String? ?? '-',
      employeeNip: employee['nip'] as String?,
      employeePosition: employee['jabatan'] as String?,
      classes: _list(json['kelas'], WorshipCompanionClass.fromJson),
      assignedBy: json['ditugaskan_oleh'] as String?,
      updatedAt: json['diperbarui_pada'] as String?,
    );
  }

  final int id;
  final int employeeId;
  final bool allClasses;
  final bool active;
  final String employeeName;
  final String? employeeNip;
  final String? employeePosition;
  final List<WorshipCompanionClass> classes;
  final String? assignedBy;
  final String? updatedAt;
}

class WorshipAbsenceSettingsValue {
  const WorshipAbsenceSettingsValue({
    required this.confirmationDayLimit,
    required this.active,
  });

  final int confirmationDayLimit;
  final bool active;
}

class WorshipCompanionAssignmentValue {
  const WorshipCompanionAssignmentValue({
    required this.employeeId,
    required this.allClasses,
    required this.classIds,
  });

  final int employeeId;
  final bool allClasses;
  final List<int> classIds;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) fromJson) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => fromJson(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value) => value is num ? value.toInt() : 0;
