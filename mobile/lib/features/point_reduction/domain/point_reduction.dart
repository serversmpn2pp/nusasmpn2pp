import 'dart:typed_data';

class PointReductionPage {
  const PointReductionPage({
    required this.items,
    required this.summary,
    required this.options,
    required this.filter,
    required this.pagination,
    required this.access,
    this.activeAcademicYear,
  });

  factory PointReductionPage.fromJson(Map<String, dynamic> json) =>
      PointReductionPage(
        items: _list(json['items'], PointReductionItem.fromJson),
        summary: PointReductionSummary.fromJson(_map(json['ringkasan'])),
        options: PointReductionOptions.fromJson(_map(json['pilihan'])),
        filter: PointReductionFilter.fromJson(_map(json['filter'])),
        pagination: PointReductionPagination.fromJson(_map(json['paginasi'])),
        access: PointReductionAccess.fromJson(_map(json['hak_akses'])),
        activeAcademicYear: _nullable(
          json['tahun_pelajaran_aktif'],
          ReductionIdName.fromJson,
        ),
      );

  final List<PointReductionItem> items;
  final PointReductionSummary summary;
  final PointReductionOptions options;
  final PointReductionFilter filter;
  final PointReductionPagination pagination;
  final PointReductionAccess access;
  final ReductionIdName? activeAcademicYear;

  PointReductionPage append(PointReductionPage next) => PointReductionPage(
    items: [...items, ...next.items],
    summary: next.summary,
    options: next.options,
    filter: next.filter,
    pagination: next.pagination,
    access: next.access,
    activeAcademicYear: next.activeAcademicYear,
  );
}

class PointReductionSummary {
  const PointReductionSummary({
    required this.all,
    required this.pending,
    required this.approved,
    required this.rejected,
    required this.approvedPoints,
  });
  factory PointReductionSummary.fromJson(Map<String, dynamic> json) =>
      PointReductionSummary(
        all: _integer(json['semua']),
        pending: _integer(json['diajukan']),
        approved: _integer(json['disetujui']),
        rejected: _integer(json['ditolak']),
        approvedPoints: _integer(json['poin_disetujui']),
      );
  final int all;
  final int pending;
  final int approved;
  final int rejected;
  final int approvedPoints;
}

class PointReductionItem {
  const PointReductionItem({
    required this.id,
    required this.student,
    required this.activityDate,
    required this.activity,
    required this.points,
    required this.status,
    required this.statusLabel,
    required this.canDecide,
    this.schoolClass,
    this.academicYear,
    this.description,
    this.evidence,
    this.submittedBy,
    this.approvedBy,
    this.decidedAt,
    this.decisionNote,
  });
  factory PointReductionItem.fromJson(Map<String, dynamic> json) =>
      PointReductionItem(
        id: _integer(json['id']),
        student: ReductionPerson.fromJson(_map(json['siswa'])),
        schoolClass: _nullable(json['kelas'], ReductionIdName.fromJson),
        academicYear: _nullable(
          json['tahun_pelajaran'],
          ReductionIdName.fromJson,
        ),
        activityDate: json['tanggal_kegiatan'] as String? ?? '',
        activity: json['jenis_kegiatan'] as String? ?? '-',
        description: json['deskripsi'] as String?,
        points: _integer(json['poin_pengurangan']),
        status: json['status'] as String? ?? '',
        statusLabel: json['label_status'] as String? ?? '-',
        evidence: _nullable(json['bukti'], ReductionEvidence.fromJson),
        submittedBy: json['diajukan_oleh'] as String?,
        approvedBy: json['disetujui_oleh'] as String?,
        decidedAt: _dateTime(json['diputuskan_pada']),
        decisionNote: json['catatan_keputusan'] as String?,
        canDecide: json['dapat_diputuskan'] as bool? ?? false,
      );
  final int id;
  final ReductionPerson student;
  final ReductionIdName? schoolClass;
  final ReductionIdName? academicYear;
  final String activityDate;
  final String activity;
  final String? description;
  final int points;
  final String status;
  final String statusLabel;
  final ReductionEvidence? evidence;
  final String? submittedBy;
  final String? approvedBy;
  final DateTime? decidedAt;
  final String? decisionNote;
  final bool canDecide;
}

class PointReductionOptions {
  const PointReductionOptions({
    required this.statuses,
    required this.academicYears,
    required this.classes,
    required this.students,
    required this.activities,
    required this.points,
  });
  factory PointReductionOptions.fromJson(Map<String, dynamic> json) =>
      PointReductionOptions(
        statuses: _list(json['status'], ReductionCodeLabel.fromJson),
        academicYears: _list(
          json['tahun_pelajaran'],
          ReductionAcademicYear.fromJson,
        ),
        classes: _list(json['kelas'], ReductionSchoolClass.fromJson),
        students: _list(json['siswa'], ReductionStudent.fromJson),
        activities: _strings(json['kegiatan']),
        points: _integers(json['poin']),
      );
  final List<ReductionCodeLabel> statuses;
  final List<ReductionAcademicYear> academicYears;
  final List<ReductionSchoolClass> classes;
  final List<ReductionStudent> students;
  final List<String> activities;
  final List<int> points;
}

class PointReductionFilter {
  const PointReductionFilter({
    required this.query,
    required this.status,
    this.academicYearId,
    this.classId,
  });
  factory PointReductionFilter.fromJson(Map<String, dynamic> json) =>
      PointReductionFilter(
        query: json['kata_kunci'] as String? ?? '',
        status: json['status'] as String? ?? 'semua',
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        classId: _nullableInteger(json['kelas_id']),
      );
  final String query;
  final String status;
  final int? academicYearId;
  final int? classId;
}

class PointReductionPagination {
  const PointReductionPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });
  factory PointReductionPagination.fromJson(Map<String, dynamic> json) =>
      PointReductionPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int total;
  final bool hasNextPage;
}

class PointReductionAccess {
  const PointReductionAccess({
    required this.canSubmit,
    required this.canDecide,
  });
  factory PointReductionAccess.fromJson(Map<String, dynamic> json) =>
      PointReductionAccess(
        canSubmit: json['dapat_mengajukan'] as bool? ?? false,
        canDecide: json['dapat_memutuskan'] as bool? ?? false,
      );
  final bool canSubmit;
  final bool canDecide;
}

class ReductionStudent extends ReductionPerson {
  const ReductionStudent({
    required super.id,
    required super.name,
    required this.balance,
    super.nis,
    super.nisn,
    this.schoolClass,
  });
  factory ReductionStudent.fromJson(Map<String, dynamic> json) =>
      ReductionStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        balance: _integer(json['saldo_poin']),
        schoolClass: _nullable(json['kelas'], ReductionIdName.fromJson),
      );
  final int balance;
  final ReductionIdName? schoolClass;
}

class ReductionPerson {
  const ReductionPerson({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
  });
  factory ReductionPerson.fromJson(Map<String, dynamic> json) =>
      ReductionPerson(
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

class ReductionIdName {
  const ReductionIdName({required this.id, required this.name});
  factory ReductionIdName.fromJson(Map<String, dynamic> json) =>
      ReductionIdName(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class ReductionAcademicYear extends ReductionIdName {
  const ReductionAcademicYear({
    required super.id,
    required super.name,
    required this.active,
  });
  factory ReductionAcademicYear.fromJson(Map<String, dynamic> json) =>
      ReductionAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );
  final bool active;
}

class ReductionSchoolClass extends ReductionIdName {
  const ReductionSchoolClass({
    required super.id,
    required super.name,
    required this.academicYearId,
    required this.grade,
  });
  factory ReductionSchoolClass.fromJson(Map<String, dynamic> json) =>
      ReductionSchoolClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        academicYearId: _integer(json['tahun_pelajaran_id']),
        grade: _integer(json['tingkat']),
      );
  final int academicYearId;
  final int grade;
}

class ReductionCodeLabel {
  const ReductionCodeLabel({required this.code, required this.label});
  factory ReductionCodeLabel.fromJson(Map<String, dynamic> json) =>
      ReductionCodeLabel(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class ReductionEvidence {
  const ReductionEvidence({
    required this.fileName,
    required this.size,
    this.mimeType,
  });
  factory ReductionEvidence.fromJson(Map<String, dynamic> json) =>
      ReductionEvidence(
        fileName: json['nama_file'] as String? ?? 'Bukti penghargaan',
        mimeType: json['tipe_file'] as String?,
        size: _integer(json['ukuran_file']),
      );
  final String fileName;
  final String? mimeType;
  final int size;

  String get sizeLabel {
    if (size >= 1024 * 1024) {
      return '${(size / (1024 * 1024)).toStringAsFixed(1)} MB';
    }
    return '${(size / 1024).ceil()} KB';
  }
}

class PointReductionCreatePayload {
  const PointReductionCreatePayload({
    required this.studentId,
    required this.activityDate,
    required this.activity,
    required this.points,
    this.description,
    this.evidence,
  });
  final int studentId;
  final String activityDate;
  final String activity;
  final int points;
  final String? description;
  final ReductionPickedFile? evidence;
}

class ReductionPickedFile {
  const ReductionPickedFile({required this.name, required this.bytes});
  final String name;
  final Uint8List bytes;
}

class ReductionEvidenceDownload {
  const ReductionEvidenceDownload({
    required this.fileName,
    required this.mimeType,
    required this.bytes,
  });
  final String fileName;
  final String mimeType;
  final Uint8List bytes;
}

class PointReductionMutation {
  const PointReductionMutation({required this.message, required this.item});
  final String message;
  final PointReductionItem item;
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<T> _list<T>(dynamic value, T Function(Map<String, dynamic>) parser) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => parser(Map<String, dynamic>.from(item)))
          .toList()
    : <T>[];

List<String> _strings(dynamic value) =>
    value is List ? value.whereType<String>().toList() : <String>[];

List<int> _integers(dynamic value) =>
    value is List ? value.map(_integer).toList() : <int>[];

T? _nullable<T>(dynamic value, T Function(Map<String, dynamic>) parser) =>
    value is Map ? parser(Map<String, dynamic>.from(value)) : null;

int _integer(dynamic value) => value is num ? value.toInt() : 0;
int? _nullableInteger(dynamic value) => value is num ? value.toInt() : null;
DateTime? _dateTime(dynamic value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
