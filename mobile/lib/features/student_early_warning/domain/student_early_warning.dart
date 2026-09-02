class StudentEarlyWarningPage {
  const StudentEarlyWarningPage({
    required this.items,
    required this.summary,
    required this.options,
    required this.filter,
    required this.pagination,
    required this.access,
  });

  factory StudentEarlyWarningPage.fromJson(Map<String, dynamic> json) =>
      StudentEarlyWarningPage(
        items: _list(json['items'], StudentEarlyWarningItem.fromJson),
        summary: StudentEarlyWarningSummary.fromJson(_map(json['ringkasan'])),
        options: StudentEarlyWarningOptions.fromJson(_map(json['pilihan'])),
        filter: StudentEarlyWarningFilter.fromJson(_map(json['filter'])),
        pagination: StudentEarlyWarningPagination.fromJson(
          _map(json['paginasi']),
        ),
        access: StudentEarlyWarningAccess.fromJson(_map(json['hak_akses'])),
      );

  final List<StudentEarlyWarningItem> items;
  final StudentEarlyWarningSummary summary;
  final StudentEarlyWarningOptions options;
  final StudentEarlyWarningFilter filter;
  final StudentEarlyWarningPagination pagination;
  final StudentEarlyWarningAccess access;

  StudentEarlyWarningPage append(StudentEarlyWarningPage next) =>
      StudentEarlyWarningPage(
        items: [...items, ...next.items],
        summary: next.summary,
        options: next.options,
        filter: next.filter,
        pagination: next.pagination,
        access: next.access,
      );
}

class StudentEarlyWarningSummary {
  const StudentEarlyWarningSummary({
    required this.active,
    required this.important,
    required this.nearSanction,
    required this.repeatedPattern,
    required this.activeSanction,
  });

  factory StudentEarlyWarningSummary.fromJson(Map<String, dynamic> json) =>
      StudentEarlyWarningSummary(
        active: _integer(json['total_aktif']),
        important: _integer(json['penting']),
        nearSanction: _integer(json['mendekati_sanksi']),
        repeatedPattern: _integer(json['pola_berulang']),
        activeSanction: _integer(json['sanksi_aktif']),
      );

  final int active;
  final int important;
  final int nearSanction;
  final int repeatedPattern;
  final int activeSanction;
}

class StudentEarlyWarningItem {
  const StudentEarlyWarningItem({
    required this.id,
    required this.student,
    required this.type,
    required this.typeLabel,
    required this.level,
    required this.levelLabel,
    required this.status,
    required this.statusLabel,
    required this.title,
    required this.message,
    required this.supportingData,
    required this.cycle,
    this.schoolClass,
    this.academicYear,
    this.homeroomTeacher,
    this.activeAssistance,
    this.sanction,
    this.detectedAt,
    this.lastDetectedAt,
    this.resolvedAt,
  });

  factory StudentEarlyWarningItem.fromJson(Map<String, dynamic> json) =>
      StudentEarlyWarningItem(
        id: _integer(json['id']),
        student: StudentWarningPerson.fromJson(_map(json['siswa'])),
        schoolClass: _nullable(json['kelas'], StudentWarningIdName.fromJson),
        academicYear: _nullable(
          json['tahun_pelajaran'],
          StudentWarningIdName.fromJson,
        ),
        homeroomTeacher: _nullable(
          json['guru_wali'],
          StudentWarningPerson.fromJson,
        ),
        type: json['jenis'] as String? ?? '',
        typeLabel: json['label_jenis'] as String? ?? '-',
        level: json['tingkat'] as String? ?? '',
        levelLabel: json['label_tingkat'] as String? ?? '-',
        status: json['status'] as String? ?? '',
        statusLabel: json['label_status'] as String? ?? '-',
        title: json['judul'] as String? ?? '-',
        message: json['pesan'] as String? ?? '-',
        supportingData: _list(
          json['data_pendukung_ringkas'],
          StudentWarningSupportingDatum.fromJson,
        ),
        cycle: _integer(json['siklus']),
        detectedAt: _dateTime(json['terdeteksi_pada']),
        lastDetectedAt: _dateTime(json['terakhir_terdeteksi_pada']),
        resolvedAt: _dateTime(json['diselesaikan_pada']),
        activeAssistance: _nullable(
          json['pendampingan_aktif'],
          StudentWarningAssistance.fromJson,
        ),
        sanction: _nullable(json['sanksi'], StudentWarningSanction.fromJson),
      );

  final int id;
  final StudentWarningPerson student;
  final StudentWarningIdName? schoolClass;
  final StudentWarningIdName? academicYear;
  final StudentWarningPerson? homeroomTeacher;
  final String type;
  final String typeLabel;
  final String level;
  final String levelLabel;
  final String status;
  final String statusLabel;
  final String title;
  final String message;
  final List<StudentWarningSupportingDatum> supportingData;
  final int cycle;
  final DateTime? detectedAt;
  final DateTime? lastDetectedAt;
  final DateTime? resolvedAt;
  final StudentWarningAssistance? activeAssistance;
  final StudentWarningSanction? sanction;
}

class StudentEarlyWarningDetail {
  const StudentEarlyWarningDetail({required this.item, required this.access});

  factory StudentEarlyWarningDetail.fromJson(Map<String, dynamic> json) =>
      StudentEarlyWarningDetail(
        item: StudentEarlyWarningItem.fromJson(_map(json['peringatan'])),
        access: StudentEarlyWarningAccess.fromJson(_map(json['hak_akses'])),
      );

  final StudentEarlyWarningItem item;
  final StudentEarlyWarningAccess access;
}

class StudentEarlyWarningOptions {
  const StudentEarlyWarningOptions({
    required this.types,
    required this.levels,
    required this.statuses,
    required this.academicYears,
    required this.classes,
  });

  factory StudentEarlyWarningOptions.fromJson(Map<String, dynamic> json) =>
      StudentEarlyWarningOptions(
        types: _list(json['jenis'], StudentWarningCodeLabel.fromJson),
        levels: _list(json['tingkat'], StudentWarningCodeLabel.fromJson),
        statuses: _list(json['status'], StudentWarningCodeLabel.fromJson),
        academicYears: _list(
          json['tahun_pelajaran'],
          StudentWarningAcademicYear.fromJson,
        ),
        classes: _list(json['kelas'], StudentWarningClass.fromJson),
      );

  final List<StudentWarningCodeLabel> types;
  final List<StudentWarningCodeLabel> levels;
  final List<StudentWarningCodeLabel> statuses;
  final List<StudentWarningAcademicYear> academicYears;
  final List<StudentWarningClass> classes;
}

class StudentEarlyWarningFilter {
  const StudentEarlyWarningFilter({
    required this.query,
    required this.type,
    required this.level,
    required this.status,
    this.academicYearId,
    this.classId,
  });

  factory StudentEarlyWarningFilter.fromJson(Map<String, dynamic> json) =>
      StudentEarlyWarningFilter(
        query: json['kata_kunci'] as String? ?? '',
        type: json['jenis'] as String? ?? 'semua',
        level: json['tingkat'] as String? ?? 'semua',
        status: json['status'] as String? ?? 'aktif',
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        classId: _nullableInteger(json['kelas_id']),
      );

  final String query;
  final String type;
  final String level;
  final String status;
  final int? academicYearId;
  final int? classId;
}

class StudentEarlyWarningPagination {
  const StudentEarlyWarningPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory StudentEarlyWarningPagination.fromJson(Map<String, dynamic> json) =>
      StudentEarlyWarningPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class StudentEarlyWarningAccess {
  const StudentEarlyWarningAccess({
    required this.wideScope,
    required this.canProcess,
    required this.canManageAssistance,
  });

  factory StudentEarlyWarningAccess.fromJson(Map<String, dynamic> json) =>
      StudentEarlyWarningAccess(
        wideScope: json['cakupan_luas'] as bool? ?? false,
        canProcess: json['dapat_proses'] as bool? ?? false,
        canManageAssistance:
            json['dapat_kelola_pendampingan'] as bool? ?? false,
      );

  final bool wideScope;
  final bool canProcess;
  final bool canManageAssistance;
}

class StudentWarningSupportingDatum {
  const StudentWarningSupportingDatum({
    required this.label,
    required this.value,
  });

  factory StudentWarningSupportingDatum.fromJson(Map<String, dynamic> json) =>
      StudentWarningSupportingDatum(
        label: json['label'] as String? ?? '-',
        value: '${json['nilai'] ?? '-'}',
      );
  final String label;
  final String value;
}

class StudentWarningAssistance {
  const StudentWarningAssistance({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.date,
    this.officer,
  });

  factory StudentWarningAssistance.fromJson(Map<String, dynamic> json) =>
      StudentWarningAssistance(
        id: _integer(json['id']),
        type: json['jenis'] as String? ?? '',
        typeLabel: json['label_jenis'] as String? ?? '-',
        date: json['tanggal'] as String? ?? '',
        officer: _nullable(json['petugas'], StudentWarningPerson.fromJson),
      );
  final int id;
  final String type;
  final String typeLabel;
  final String date;
  final StudentWarningPerson? officer;
}

class StudentWarningSanction {
  const StudentWarningSanction({
    required this.id,
    required this.name,
    required this.status,
    required this.statusLabel,
    required this.overdue,
    this.deadline,
  });

  factory StudentWarningSanction.fromJson(Map<String, dynamic> json) =>
      StudentWarningSanction(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        status: json['status'] as String? ?? '',
        statusLabel: json['label_status'] as String? ?? '-',
        deadline: json['batas_pelaksanaan'] as String?,
        overdue: json['terlambat'] as bool? ?? false,
      );
  final int id;
  final String name;
  final String status;
  final String statusLabel;
  final String? deadline;
  final bool overdue;
}

class StudentWarningCodeLabel {
  const StudentWarningCodeLabel({required this.code, required this.label});
  factory StudentWarningCodeLabel.fromJson(Map<String, dynamic> json) =>
      StudentWarningCodeLabel(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class StudentWarningIdName {
  const StudentWarningIdName({required this.id, required this.name});
  factory StudentWarningIdName.fromJson(Map<String, dynamic> json) =>
      StudentWarningIdName(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class StudentWarningPerson extends StudentWarningIdName {
  const StudentWarningPerson({
    required super.id,
    required super.name,
    this.nis,
    this.nisn,
    this.nip,
  });
  factory StudentWarningPerson.fromJson(Map<String, dynamic> json) =>
      StudentWarningPerson(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        nip: json['nip'] as String?,
      );
  final String? nis;
  final String? nisn;
  final String? nip;
}

class StudentWarningAcademicYear extends StudentWarningIdName {
  const StudentWarningAcademicYear({
    required super.id,
    required super.name,
    required this.active,
  });
  factory StudentWarningAcademicYear.fromJson(Map<String, dynamic> json) =>
      StudentWarningAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );
  final bool active;
}

class StudentWarningClass extends StudentWarningIdName {
  const StudentWarningClass({
    required super.id,
    required super.name,
    required this.academicYearId,
    required this.level,
  });
  factory StudentWarningClass.fromJson(Map<String, dynamic> json) =>
      StudentWarningClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        academicYearId: _integer(json['tahun_pelajaran_id']),
        level: _integer(json['tingkat']),
      );
  final int academicYearId;
  final int level;
}

class StudentWarningProcessResult {
  const StudentWarningProcessResult({
    required this.message,
    required this.created,
    required this.updated,
    required this.resolved,
  });
  factory StudentWarningProcessResult.fromJson(Map<String, dynamic> json) {
    final data = _map(json['data']);
    return StudentWarningProcessResult(
      message: json['message'] as String? ?? 'Deteksi peringatan selesai.',
      created: _integer(data['peringatan_baru']),
      updated: _integer(data['peringatan_diperbarui']),
      resolved: _integer(data['peringatan_diselesaikan']),
    );
  }
  final String message;
  final int created;
  final int updated;
  final int resolved;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

T? _nullable<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is Map<String, dynamic> ? convert(value) : null;

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value.whereType<Map>().map((item) => convert(item.cast())).toList()
    : <T>[];

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);
DateTime? _dateTime(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
