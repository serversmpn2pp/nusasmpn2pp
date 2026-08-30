import 'dart:typed_data';

class StudentSanctionPage {
  const StudentSanctionPage({
    required this.items,
    required this.summary,
    required this.options,
    required this.filter,
    required this.pagination,
    required this.access,
  });

  factory StudentSanctionPage.fromJson(Map<String, dynamic> json) =>
      StudentSanctionPage(
        items: _list(json['items'], StudentSanctionItem.fromJson),
        summary: StudentSanctionSummary.fromJson(_map(json['ringkasan'])),
        options: StudentSanctionOptions.fromJson(_map(json['pilihan'])),
        filter: StudentSanctionFilter.fromJson(_map(json['filter'])),
        pagination: StudentSanctionPagination.fromJson(_map(json['paginasi'])),
        access: StudentSanctionListAccess.fromJson(_map(json['hak_akses'])),
      );

  final List<StudentSanctionItem> items;
  final StudentSanctionSummary summary;
  final StudentSanctionOptions options;
  final StudentSanctionFilter filter;
  final StudentSanctionPagination pagination;
  final StudentSanctionListAccess access;

  StudentSanctionPage append(StudentSanctionPage next) => StudentSanctionPage(
    items: [...items, ...next.items],
    summary: next.summary,
    options: next.options,
    filter: next.filter,
    pagination: next.pagination,
    access: next.access,
  );
}

class StudentSanctionSummary {
  const StudentSanctionSummary({
    required this.active,
    required this.waiting,
    required this.inProgress,
    required this.overdue,
    required this.completed,
  });
  factory StudentSanctionSummary.fromJson(Map<String, dynamic> json) =>
      StudentSanctionSummary(
        active: _integer(json['aktif']),
        waiting: _integer(json['menunggu']),
        inProgress: _integer(json['diproses']),
        overdue: _integer(json['terlambat']),
        completed: _integer(json['selesai']),
      );
  final int active;
  final int waiting;
  final int inProgress;
  final int overdue;
  final int completed;
}

class StudentSanctionItem {
  const StudentSanctionItem({
    required this.id,
    required this.student,
    required this.rule,
    required this.triggerPoints,
    required this.status,
    required this.statusLabel,
    required this.overdue,
    required this.evidenceCount,
    this.schoolClass,
    this.academicYear,
    this.officer,
    this.triggeredAt,
    this.deadline,
    this.startedAt,
    this.completedAt,
    this.note,
    this.result,
    this.homeroomTeacher,
    this.studentMentor,
    this.updatedBy,
  });

  factory StudentSanctionItem.fromJson(Map<String, dynamic> json) =>
      StudentSanctionItem(
        id: _integer(json['id']),
        student: PersonOption.fromJson(_map(json['siswa'])),
        schoolClass: _nullable(json['kelas'], IdNameOption.fromJson),
        academicYear: _nullable(json['tahun_pelajaran'], IdNameOption.fromJson),
        rule: SanctionRule.fromJson(_map(json['aturan'])),
        officer: _nullable(json['petugas'], PersonOption.fromJson),
        triggerPoints: _integer(json['poin_saat_terpicu']),
        status: json['status'] as String? ?? '',
        statusLabel: json['label_status'] as String? ?? '-',
        triggeredAt: _dateTime(json['terpicu_pada']),
        deadline: json['batas_pelaksanaan'] as String?,
        overdue: json['terlambat'] as bool? ?? false,
        evidenceCount: _integer(json['jumlah_bukti']),
        startedAt: _dateTime(json['mulai_diproses_pada']),
        completedAt: _dateTime(json['dilaksanakan_pada']),
        note: json['catatan'] as String?,
        result: json['hasil_pelaksanaan'] as String?,
        homeroomTeacher: _nullable(json['wali_kelas'], PersonOption.fromJson),
        studentMentor: _nullable(json['guru_wali'], PersonOption.fromJson),
        updatedBy: json['diperbarui_oleh'] as String?,
      );

  final int id;
  final PersonOption student;
  final IdNameOption? schoolClass;
  final IdNameOption? academicYear;
  final SanctionRule rule;
  final PersonOption? officer;
  final int triggerPoints;
  final String status;
  final String statusLabel;
  final DateTime? triggeredAt;
  final String? deadline;
  final bool overdue;
  final int evidenceCount;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final String? note;
  final String? result;
  final PersonOption? homeroomTeacher;
  final PersonOption? studentMentor;
  final String? updatedBy;
}

class StudentSanctionDetail {
  const StudentSanctionDetail({
    required this.item,
    required this.evidence,
    required this.history,
    required this.statusOptions,
    required this.officers,
    required this.access,
  });
  factory StudentSanctionDetail.fromJson(Map<String, dynamic> json) =>
      StudentSanctionDetail(
        item: StudentSanctionItem.fromJson(_map(json['sanksi'])),
        evidence: _list(json['bukti'], SanctionEvidence.fromJson),
        history: _list(json['riwayat'], SanctionHistory.fromJson),
        statusOptions: _list(json['pilihan_status'], CodeLabelOption.fromJson),
        officers: _list(json['pegawai'], PersonOption.fromJson),
        access: StudentSanctionDetailAccess.fromJson(_map(json['hak_akses'])),
      );
  final StudentSanctionItem item;
  final List<SanctionEvidence> evidence;
  final List<SanctionHistory> history;
  final List<CodeLabelOption> statusOptions;
  final List<PersonOption> officers;
  final StudentSanctionDetailAccess access;
}

class SanctionRule {
  const SanctionRule({
    required this.id,
    required this.name,
    required this.pointThreshold,
    this.description,
  });
  factory SanctionRule.fromJson(Map<String, dynamic> json) => SanctionRule(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    pointThreshold: _integer(json['batas_poin']),
    description: json['deskripsi'] as String?,
  );
  final int id;
  final String name;
  final int pointThreshold;
  final String? description;
}

class SanctionEvidence {
  const SanctionEvidence({
    required this.id,
    required this.fileName,
    required this.fileSize,
    this.mimeType,
    this.description,
    this.uploadedBy,
    this.uploadedAt,
  });
  factory SanctionEvidence.fromJson(Map<String, dynamic> json) =>
      SanctionEvidence(
        id: _integer(json['id']),
        fileName: json['nama_file'] as String? ?? '-',
        mimeType: json['tipe_file'] as String?,
        fileSize: json['ukuran_ringkas'] as String? ?? '-',
        description: json['keterangan'] as String?,
        uploadedBy: json['diunggah_oleh'] as String?,
        uploadedAt: _dateTime(json['diunggah_pada']),
      );
  final int id;
  final String fileName;
  final String? mimeType;
  final String fileSize;
  final String? description;
  final String? uploadedBy;
  final DateTime? uploadedAt;
}

class SanctionHistory {
  const SanctionHistory({
    required this.id,
    required this.title,
    required this.user,
    this.type,
    this.previousStatus,
    this.previousStatusLabel,
    this.nextStatus,
    this.nextStatusLabel,
    this.note,
    this.occurredAt,
  });
  factory SanctionHistory.fromJson(Map<String, dynamic> json) =>
      SanctionHistory(
        id: _integer(json['id']),
        type: json['jenis_kegiatan'] as String?,
        title: json['judul'] as String? ?? '-',
        previousStatus: json['status_sebelum'] as String?,
        previousStatusLabel: json['label_status_sebelum'] as String?,
        nextStatus: json['status_sesudah'] as String?,
        nextStatusLabel: json['label_status_sesudah'] as String?,
        note: json['catatan'] as String?,
        user: json['pengguna'] as String? ?? 'Sistem NUSA',
        occurredAt: _dateTime(json['terjadi_pada']),
      );
  final int id;
  final String? type;
  final String title;
  final String? previousStatus;
  final String? previousStatusLabel;
  final String? nextStatus;
  final String? nextStatusLabel;
  final String? note;
  final String user;
  final DateTime? occurredAt;
}

class StudentSanctionOptions {
  const StudentSanctionOptions({
    required this.statuses,
    required this.academicYears,
    required this.classes,
  });
  factory StudentSanctionOptions.fromJson(Map<String, dynamic> json) =>
      StudentSanctionOptions(
        statuses: _list(json['status'], CodeLabelOption.fromJson),
        academicYears: _list(
          json['tahun_pelajaran'],
          AcademicYearOption.fromJson,
        ),
        classes: _list(json['kelas'], ClassOption.fromJson),
      );
  final List<CodeLabelOption> statuses;
  final List<AcademicYearOption> academicYears;
  final List<ClassOption> classes;
}

class StudentSanctionFilter {
  const StudentSanctionFilter({
    required this.query,
    required this.status,
    this.academicYearId,
    this.classId,
  });
  factory StudentSanctionFilter.fromJson(Map<String, dynamic> json) =>
      StudentSanctionFilter(
        query: json['kata_kunci'] as String? ?? '',
        status: json['status'] as String? ?? 'aktif',
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        classId: _nullableInteger(json['kelas_id']),
      );
  final String query;
  final String status;
  final int? academicYearId;
  final int? classId;
}

class StudentSanctionPagination {
  const StudentSanctionPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });
  factory StudentSanctionPagination.fromJson(Map<String, dynamic> json) =>
      StudentSanctionPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int total;
  final bool hasNextPage;
}

class StudentSanctionListAccess {
  const StudentSanctionListAccess({
    required this.wideScope,
    required this.canManageGenerally,
  });
  factory StudentSanctionListAccess.fromJson(Map<String, dynamic> json) =>
      StudentSanctionListAccess(
        wideScope: json['cakupan_luas'] as bool? ?? false,
        canManageGenerally: json['dapat_kelola_umum'] as bool? ?? false,
      );
  final bool wideScope;
  final bool canManageGenerally;
}

class StudentSanctionDetailAccess {
  const StudentSanctionDetailAccess({
    required this.canManage,
    required this.canDownloadEvidence,
    required this.finalStatus,
  });
  factory StudentSanctionDetailAccess.fromJson(Map<String, dynamic> json) =>
      StudentSanctionDetailAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
        canDownloadEvidence: json['dapat_unduh_bukti'] as bool? ?? false,
        finalStatus: json['status_final'] as bool? ?? false,
      );
  final bool canManage;
  final bool canDownloadEvidence;
  final bool finalStatus;
}

class StudentSanctionPayload {
  const StudentSanctionPayload({
    required this.status,
    this.officerId,
    this.deadline,
    this.note,
    this.result,
  });
  final String status;
  final int? officerId;
  final String? deadline;
  final String? note;
  final String? result;
  Map<String, dynamic> toJson() => {
    'status': status,
    'petugas_pegawai_id': officerId,
    'batas_pelaksanaan': deadline,
    'catatan': note,
    'hasil_pelaksanaan': result,
  };
}

class SanctionPickedFile {
  const SanctionPickedFile({required this.name, required this.bytes});
  final String name;
  final Uint8List bytes;
}

class SanctionEvidenceDownload {
  const SanctionEvidenceDownload({
    required this.fileName,
    required this.mimeType,
    required this.bytes,
  });
  final String fileName;
  final String mimeType;
  final Uint8List bytes;
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

class PersonOption extends IdNameOption {
  const PersonOption({
    required super.id,
    required super.name,
    this.nis,
    this.nisn,
    this.nip,
  });
  factory PersonOption.fromJson(Map<String, dynamic> json) => PersonOption(
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

class AcademicYearOption extends IdNameOption {
  const AcademicYearOption({
    required super.id,
    required super.name,
    required this.active,
  });
  factory AcademicYearOption.fromJson(Map<String, dynamic> json) =>
      AcademicYearOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );
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
