class StudentAssistancePage {
  const StudentAssistancePage({
    required this.items,
    required this.summary,
    required this.options,
    required this.filter,
    required this.pagination,
    required this.access,
  });

  factory StudentAssistancePage.fromJson(Map<String, dynamic> json) =>
      StudentAssistancePage(
        items: _list(json['items'], StudentAssistanceItem.fromJson),
        summary: StudentAssistanceSummary.fromJson(_map(json['ringkasan'])),
        options: StudentAssistanceOptions.fromJson(_map(json['pilihan'])),
        filter: StudentAssistanceFilter.fromJson(_map(json['filter'])),
        pagination: StudentAssistancePagination.fromJson(
          _map(json['paginasi']),
        ),
        access: StudentAssistanceAccess.fromJson(_map(json['hak_akses'])),
      );

  final List<StudentAssistanceItem> items;
  final StudentAssistanceSummary summary;
  final StudentAssistanceOptions options;
  final StudentAssistanceFilter filter;
  final StudentAssistancePagination pagination;
  final StudentAssistanceAccess access;

  StudentAssistancePage append(StudentAssistancePage next) =>
      StudentAssistancePage(
        items: [...items, ...next.items],
        summary: next.summary,
        options: next.options,
        filter: next.filter,
        pagination: next.pagination,
        access: next.access,
      );
}

class StudentAssistanceSummary {
  const StudentAssistanceSummary({
    required this.total,
    required this.inProgress,
    required this.completed,
  });

  factory StudentAssistanceSummary.fromJson(Map<String, dynamic> json) =>
      StudentAssistanceSummary(
        total: _integer(json['total']),
        inProgress: _integer(json['dalam_proses']),
        completed: _integer(json['selesai']),
      );

  final int total;
  final int inProgress;
  final int completed;
}

class StudentAssistanceItem {
  const StudentAssistanceItem({
    required this.id,
    required this.student,
    required this.type,
    required this.typeLabel,
    required this.date,
    required this.note,
    required this.status,
    required this.statusLabel,
    this.schoolClass,
    this.academicYear,
    this.warning,
    this.officer,
    this.result,
    this.completedAt,
    this.updatedAt,
  });

  factory StudentAssistanceItem.fromJson(Map<String, dynamic> json) =>
      StudentAssistanceItem(
        id: _integer(json['id']),
        student: PersonOption.fromJson(_map(json['siswa'])),
        schoolClass: _nullable(json['kelas'], IdNameOption.fromJson),
        academicYear: _nullable(json['tahun_pelajaran'], IdNameOption.fromJson),
        warning: _nullable(json['peringatan'], AssistanceWarning.fromJson),
        officer: _nullable(json['petugas'], PersonOption.fromJson),
        type: json['jenis_tindakan'] as String? ?? '',
        typeLabel: json['label_jenis_tindakan'] as String? ?? '-',
        date: json['tanggal_tindak_lanjut'] as String? ?? '',
        note: json['catatan'] as String? ?? '',
        status: json['status'] as String? ?? '',
        statusLabel: json['label_status'] as String? ?? '-',
        result: json['hasil'] as String?,
        completedAt: _dateTime(json['selesai_pada']),
        updatedAt: _dateTime(json['diperbarui_pada']),
      );

  final int id;
  final PersonOption student;
  final IdNameOption? schoolClass;
  final IdNameOption? academicYear;
  final AssistanceWarning? warning;
  final PersonOption? officer;
  final String type;
  final String typeLabel;
  final String date;
  final String note;
  final String status;
  final String statusLabel;
  final String? result;
  final DateTime? completedAt;
  final DateTime? updatedAt;
}

class StudentAssistanceDetail {
  const StudentAssistanceDetail({
    required this.item,
    required this.types,
    required this.statuses,
    required this.officers,
    required this.access,
  });

  factory StudentAssistanceDetail.fromJson(Map<String, dynamic> json) {
    final options = _map(json['pilihan']);
    return StudentAssistanceDetail(
      item: StudentAssistanceItem.fromJson(_map(json['pendampingan'])),
      types: _list(options['jenis_tindakan'], CodeLabelOption.fromJson),
      statuses: _list(options['status'], CodeLabelOption.fromJson),
      officers: _list(options['pegawai'], PersonOption.fromJson),
      access: StudentAssistanceAccess.fromJson(_map(json['hak_akses'])),
    );
  }

  final StudentAssistanceItem item;
  final List<CodeLabelOption> types;
  final List<CodeLabelOption> statuses;
  final List<PersonOption> officers;
  final StudentAssistanceAccess access;
}

class StudentAssistanceReference {
  const StudentAssistanceReference({
    required this.students,
    required this.officers,
    required this.types,
    required this.academicYearId,
    required this.classId,
    required this.query,
  });

  factory StudentAssistanceReference.fromJson(Map<String, dynamic> json) =>
      StudentAssistanceReference(
        students: _list(json['siswa'], AssistanceStudentOption.fromJson),
        officers: _list(json['pegawai'], PersonOption.fromJson),
        types: _list(json['jenis_tindakan'], CodeLabelOption.fromJson),
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        classId: _nullableInteger(json['kelas_id']),
        query: json['kata_kunci'] as String? ?? '',
      );

  final List<AssistanceStudentOption> students;
  final List<PersonOption> officers;
  final List<CodeLabelOption> types;
  final int? academicYearId;
  final int? classId;
  final String query;
}

class StudentAssistanceOptions {
  const StudentAssistanceOptions({
    required this.statuses,
    required this.types,
    required this.academicYears,
    required this.classes,
  });

  factory StudentAssistanceOptions.fromJson(Map<String, dynamic> json) =>
      StudentAssistanceOptions(
        statuses: _list(json['status'], CodeLabelOption.fromJson),
        types: _list(json['jenis_tindakan'], CodeLabelOption.fromJson),
        academicYears: _list(
          json['tahun_pelajaran'],
          AcademicYearOption.fromJson,
        ),
        classes: _list(json['kelas'], ClassOption.fromJson),
      );

  final List<CodeLabelOption> statuses;
  final List<CodeLabelOption> types;
  final List<AcademicYearOption> academicYears;
  final List<ClassOption> classes;
}

class StudentAssistanceFilter {
  const StudentAssistanceFilter({
    required this.query,
    required this.status,
    this.academicYearId,
    this.classId,
  });

  factory StudentAssistanceFilter.fromJson(Map<String, dynamic> json) =>
      StudentAssistanceFilter(
        query: json['kata_kunci'] as String? ?? '',
        status: json['status'] as String? ?? 'dalam_proses',
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        classId: _nullableInteger(json['kelas_id']),
      );

  final String query;
  final String status;
  final int? academicYearId;
  final int? classId;
}

class StudentAssistancePagination {
  const StudentAssistancePagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory StudentAssistancePagination.fromJson(Map<String, dynamic> json) =>
      StudentAssistancePagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class StudentAssistanceAccess {
  const StudentAssistanceAccess({
    required this.canManage,
    required this.wideScope,
  });

  factory StudentAssistanceAccess.fromJson(Map<String, dynamic> json) =>
      StudentAssistanceAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
        wideScope: json['cakupan_luas'] as bool? ?? false,
      );

  final bool canManage;
  final bool wideScope;
}

class AssistanceStudentOption {
  const AssistanceStudentOption({
    required this.person,
    required this.hasActiveAssistance,
    this.schoolClass,
  });

  factory AssistanceStudentOption.fromJson(Map<String, dynamic> json) =>
      AssistanceStudentOption(
        person: PersonOption.fromJson(json),
        schoolClass: _nullable(json['kelas'], IdNameOption.fromJson),
        hasActiveAssistance:
            json['memiliki_pendampingan_aktif'] as bool? ?? false,
      );

  final PersonOption person;
  final IdNameOption? schoolClass;
  final bool hasActiveAssistance;
}

class AssistanceWarning {
  const AssistanceWarning({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.title,
  });

  factory AssistanceWarning.fromJson(Map<String, dynamic> json) =>
      AssistanceWarning(
        id: _integer(json['id']),
        type: json['jenis'] as String? ?? '',
        typeLabel: json['label_jenis'] as String? ?? '-',
        title: json['judul'] as String? ?? '-',
      );

  final int id;
  final String type;
  final String typeLabel;
  final String title;
}

class CodeLabelOption {
  const CodeLabelOption({required this.code, required this.label});
  factory CodeLabelOption.fromJson(Map<String, dynamic> json) =>
      CodeLabelOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class IdNameOption {
  const IdNameOption({required this.id, required this.name});
  factory IdNameOption.fromJson(Map<String, dynamic> json) => IdNameOption(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
  );
  final int id;
  final String name;
}

class PersonOption {
  const PersonOption({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
    this.nip,
    this.position,
  });

  factory PersonOption.fromJson(Map<String, dynamic> json) => PersonOption(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    nis: json['nis'] as String?,
    nisn: json['nisn'] as String?,
    nip: json['nip'] as String?,
    position: json['jabatan'] as String?,
  );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? nip;
  final String? position;
}

class AcademicYearOption {
  const AcademicYearOption({
    required this.id,
    required this.name,
    required this.active,
  });
  factory AcademicYearOption.fromJson(Map<String, dynamic> json) =>
      AcademicYearOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );
  final int id;
  final String name;
  final bool active;
}

class ClassOption extends IdNameOption {
  const ClassOption({
    required super.id,
    required super.name,
    required this.academicYearId,
    required this.level,
  });
  factory ClassOption.fromJson(Map<String, dynamic> json) => ClassOption(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    academicYearId: _integer(json['tahun_pelajaran_id']),
    level: _integer(json['tingkat']),
  );
  final int academicYearId;
  final int level;
}

class StudentAssistancePayload {
  const StudentAssistancePayload({
    required this.type,
    required this.officerId,
    required this.date,
    required this.note,
    this.studentId,
    this.academicYearId,
    this.warningId,
    this.status,
    this.result,
  });

  final int? studentId;
  final int? academicYearId;
  final int? warningId;
  final String type;
  final int officerId;
  final String date;
  final String note;
  final String? status;
  final String? result;

  Map<String, dynamic> toJson({required bool create}) => {
    if (create) 'siswa_id': studentId,
    if (create) 'tahun_pelajaran_id': academicYearId,
    if (create && warningId != null) 'peringatan_dini_siswa_id': warningId,
    'jenis_tindakan': type,
    'petugas_pegawai_id': officerId,
    'tanggal_tindak_lanjut': date,
    'catatan': note,
    if (!create) 'status': status,
    'hasil': result,
  };
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
