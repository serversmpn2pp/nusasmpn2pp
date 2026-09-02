import 'dart:typed_data';

enum StudentReportScope { all, guardianStudents }

class StudentReportPage {
  const StudentReportPage({
    required this.items,
    required this.summary,
    required this.options,
    required this.filter,
    required this.pagination,
    required this.access,
  });

  factory StudentReportPage.fromJson(Map<String, dynamic> json) =>
      StudentReportPage(
        items: _list(json['items'], StudentReportItem.fromJson),
        summary: StudentReportSummary.fromJson(_map(json['ringkasan'])),
        options: StudentReportOptions.fromJson(_map(json['pilihan'])),
        filter: StudentReportFilter.fromJson(_map(json['filter'])),
        pagination: StudentReportPagination.fromJson(_map(json['paginasi'])),
        access: StudentReportAccess.fromJson(_map(json['hak_akses'])),
      );

  final List<StudentReportItem> items;
  final StudentReportSummary summary;
  final StudentReportOptions options;
  final StudentReportFilter filter;
  final StudentReportPagination pagination;
  final StudentReportAccess access;

  StudentReportPage append(StudentReportPage next) => StudentReportPage(
    items: [...items, ...next.items],
    summary: next.summary,
    options: next.options,
    filter: next.filter,
    pagination: next.pagination,
    access: next.access,
  );
}

class StudentReportSummary {
  const StudentReportSummary({
    required this.total,
    required this.incidents,
    required this.guidance,
    required this.violations,
    required this.waitingCounseling,
    required this.waitingApproval,
    required this.approved,
  });

  factory StudentReportSummary.fromJson(Map<String, dynamic> json) =>
      StudentReportSummary(
        total: _integer(json['total']),
        incidents: _integer(json['kejadian']),
        guidance: _integer(json['pembinaan']),
        violations: _integer(json['pelanggaran']),
        waitingCounseling: _integer(json['menunggu_bk']),
        waitingApproval: _integer(json['menunggu_wakil']),
        approved: _integer(json['disahkan']),
      );

  final int total;
  final int incidents;
  final int guidance;
  final int violations;
  final int waitingCounseling;
  final int waitingApproval;
  final int approved;
}

class StudentReportOptions {
  const StudentReportOptions({
    required this.statuses,
    required this.levels,
    required this.types,
    required this.verificationStatuses,
    required this.academicYears,
    required this.classes,
  });

  factory StudentReportOptions.fromJson(Map<String, dynamic> json) =>
      StudentReportOptions(
        statuses: _list(json['status'], StudentReportCodeOption.fromJson),
        levels: _list(json['tingkat'], StudentReportCodeOption.fromJson),
        types: _list(json['jenis_laporan'], StudentReportCodeOption.fromJson),
        verificationStatuses: _list(
          json['status_verifikasi'],
          StudentReportCodeOption.fromJson,
        ),
        academicYears: _list(
          json['tahun_pelajaran'],
          StudentReportAcademicYear.fromJson,
        ),
        classes: _list(json['kelas'], StudentReportClass.fromJson),
      );

  final List<StudentReportCodeOption> statuses;
  final List<StudentReportCodeOption> levels;
  final List<StudentReportCodeOption> types;
  final List<StudentReportCodeOption> verificationStatuses;
  final List<StudentReportAcademicYear> academicYears;
  final List<StudentReportClass> classes;
}

class StudentReportFilter {
  const StudentReportFilter({
    required this.query,
    required this.status,
    required this.level,
    required this.type,
    required this.verificationStatus,
    this.academicYearId,
    this.classId,
  });

  factory StudentReportFilter.fromJson(Map<String, dynamic> json) =>
      StudentReportFilter(
        query: json['kata_kunci'] as String? ?? '',
        status: json['status'] as String? ?? 'semua',
        level: json['tingkat'] as String? ?? 'semua',
        type: json['jenis_laporan'] as String? ?? 'semua',
        verificationStatus: json['status_verifikasi'] as String? ?? 'semua',
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        classId: _nullableInteger(json['kelas_id']),
      );

  final String query;
  final String status;
  final String level;
  final String type;
  final String verificationStatus;
  final int? academicYearId;
  final int? classId;
}

class StudentReportPagination {
  const StudentReportPagination({
    required this.page,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory StudentReportPagination.fromJson(Map<String, dynamic> json) =>
      StudentReportPagination(
        page: _integer(json['halaman']),
        perPage: _integer(json['per_halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int perPage;
  final int total;
  final bool hasNextPage;
}

class StudentReportAccess {
  const StudentReportAccess({
    required this.broadScope,
    required this.canReport,
  });

  factory StudentReportAccess.fromJson(Map<String, dynamic> json) =>
      StudentReportAccess(
        broadScope: json['cakupan_luas'] as bool? ?? false,
        canReport: json['dapat_melaporkan'] as bool? ?? false,
      );

  final bool broadScope;
  final bool canReport;
}

class StudentReportItem {
  const StudentReportItem({
    required this.id,
    required this.number,
    required this.type,
    required this.typeLabel,
    required this.source,
    required this.incidentDate,
    required this.level,
    required this.levelLabel,
    required this.status,
    required this.statusLabel,
    required this.verificationStatus,
    required this.verificationStatusLabel,
    required this.totalPoints,
    required this.deadline,
    required this.violationCount,
    required this.evidenceCount,
    required this.witnessCount,
    required this.clarificationCount,
    required this.followUpCount,
    this.incidentTime,
    this.place,
    this.student,
    this.schoolClass,
    this.academicYear,
    this.category,
    this.reporter,
    this.chronology,
    this.initialAction,
    this.homeroomTeacher,
    this.studentAdvisor,
    this.createdAt,
  });

  factory StudentReportItem.fromJson(
    Map<String, dynamic> json,
  ) => StudentReportItem(
    id: _integer(json['id']),
    number: json['nomor_laporan'] as String? ?? '-',
    type: json['jenis_laporan'] as String? ?? '',
    typeLabel: json['label_jenis_laporan'] as String? ?? '-',
    source: json['sumber_laporan'] as String? ?? '',
    incidentDate: json['tanggal_kejadian'] as String? ?? '',
    incidentTime: json['waktu_kejadian'] as String?,
    place: json['tempat_kejadian'] as String?,
    student: _nullableMap(json['siswa'], StudentReportPerson.fromJson),
    schoolClass: _nullableMap(json['kelas'], StudentReportClass.fromJson),
    academicYear: _nullableMap(
      json['tahun_pelajaran'],
      StudentReportAcademicYear.fromJson,
    ),
    category: _nullableMap(json['kategori'], StudentReportNamedItem.fromJson),
    reporter: _nullableMap(json['pelapor'], StudentReportPerson.fromJson),
    level: json['tingkat'] as String? ?? '',
    levelLabel: json['label_tingkat'] as String? ?? '-',
    status: json['status'] as String? ?? '',
    statusLabel: json['label_status'] as String? ?? '-',
    verificationStatus: json['status_verifikasi'] as String? ?? '',
    verificationStatusLabel: json['label_status_verifikasi'] as String? ?? '-',
    totalPoints: _integer(json['total_poin']),
    deadline: StudentReportDeadline.fromJson(_map(json['tenggat'])),
    violationCount: _integer(json['jumlah_butir']),
    evidenceCount: _integer(json['jumlah_bukti']),
    witnessCount: _integer(json['jumlah_saksi']),
    clarificationCount: _integer(json['jumlah_klarifikasi']),
    followUpCount: _integer(json['jumlah_tindak_lanjut']),
    chronology: json['kronologi'] as String?,
    initialAction: json['tindakan_awal'] as String?,
    homeroomTeacher: _nullableMap(
      json['wali_kelas'],
      StudentReportPerson.fromJson,
    ),
    studentAdvisor: _nullableMap(
      json['guru_wali'],
      StudentReportPerson.fromJson,
    ),
    createdAt: _dateTime(json['dibuat_pada']),
  );

  final int id;
  final String number;
  final String type;
  final String typeLabel;
  final String source;
  final String incidentDate;
  final String? incidentTime;
  final String? place;
  final StudentReportPerson? student;
  final StudentReportClass? schoolClass;
  final StudentReportAcademicYear? academicYear;
  final StudentReportNamedItem? category;
  final StudentReportPerson? reporter;
  final String level;
  final String levelLabel;
  final String status;
  final String statusLabel;
  final String verificationStatus;
  final String verificationStatusLabel;
  final int totalPoints;
  final StudentReportDeadline deadline;
  final int violationCount;
  final int evidenceCount;
  final int witnessCount;
  final int clarificationCount;
  final int followUpCount;
  final String? chronology;
  final String? initialAction;
  final StudentReportPerson? homeroomTeacher;
  final StudentReportPerson? studentAdvisor;
  final DateTime? createdAt;
}

class StudentReportDeadline {
  const StudentReportDeadline({
    required this.overdue,
    this.stage,
    this.stageLabel,
    this.at,
  });

  factory StudentReportDeadline.fromJson(Map<String, dynamic> json) =>
      StudentReportDeadline(
        stage: json['tahap'] as String?,
        stageLabel: json['label_tahap'] as String?,
        at: _dateTime(json['pada']),
        overdue: json['terlambat'] as bool? ?? false,
      );

  final String? stage;
  final String? stageLabel;
  final DateTime? at;
  final bool overdue;
}

class StudentReportDetail {
  const StudentReportDetail({
    required this.report,
    required this.violations,
    required this.counselingDecisions,
    required this.approvals,
    required this.evidence,
    required this.witnesses,
    required this.clarifications,
    required this.followUps,
    required this.timeline,
    required this.canManageFacts,
    required this.canRecordClarification,
  });

  factory StudentReportDetail.fromJson(Map<String, dynamic> json) {
    final access = _map(json['hak_akses']);
    return StudentReportDetail(
      report: StudentReportItem.fromJson(_map(json['laporan'])),
      violations: _list(
        json['butir_pelanggaran'],
        StudentReportViolation.fromJson,
      ),
      counselingDecisions: _list(
        json['pemeriksaan_bk'],
        StudentReportDecision.fromJson,
      ),
      approvals: _list(json['persetujuan'], StudentReportApproval.fromJson),
      evidence: _list(json['bukti'], StudentReportEvidence.fromJson),
      witnesses: _list(json['saksi'], StudentReportWitness.fromJson),
      clarifications: _list(
        json['klarifikasi'],
        StudentReportClarification.fromJson,
      ),
      followUps: _list(json['tindak_lanjut'], StudentReportFollowUp.fromJson),
      timeline: _list(json['linimasa'], StudentReportTimeline.fromJson),
      canManageFacts: access['dapat_kelola_fakta'] as bool? ?? false,
      canRecordClarification:
          access['dapat_mencatat_klarifikasi'] as bool? ?? false,
    );
  }

  final StudentReportItem report;
  final List<StudentReportViolation> violations;
  final List<StudentReportDecision> counselingDecisions;
  final List<StudentReportApproval> approvals;
  final List<StudentReportEvidence> evidence;
  final List<StudentReportWitness> witnesses;
  final List<StudentReportClarification> clarifications;
  final List<StudentReportFollowUp> followUps;
  final List<StudentReportTimeline> timeline;
  final bool canManageFacts;
  final bool canRecordClarification;
}

class StudentReportCodeOption {
  const StudentReportCodeOption({required this.code, required this.label});
  factory StudentReportCodeOption.fromJson(Map<String, dynamic> json) =>
      StudentReportCodeOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class StudentReportAcademicYear {
  const StudentReportAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });
  factory StudentReportAcademicYear.fromJson(Map<String, dynamic> json) =>
      StudentReportAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );
  final int id;
  final String name;
  final bool active;
}

class StudentReportClass {
  const StudentReportClass({
    required this.id,
    required this.name,
    required this.grade,
    this.academicYearId,
  });
  factory StudentReportClass.fromJson(Map<String, dynamic> json) =>
      StudentReportClass(
        id: _integer(json['id']),
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
      );
  final int id;
  final int? academicYearId;
  final String name;
  final int grade;
}

class StudentReportNamedItem {
  const StudentReportNamedItem({required this.id, required this.name});
  factory StudentReportNamedItem.fromJson(Map<String, dynamic> json) =>
      StudentReportNamedItem(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class StudentReportPerson {
  const StudentReportPerson({
    required this.name,
    this.id,
    this.nis,
    this.nisn,
    this.nip,
  });
  factory StudentReportPerson.fromJson(Map<String, dynamic> json) =>
      StudentReportPerson(
        id: _nullableInteger(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        nip: json['nip'] as String?,
      );
  final int? id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? nip;
}

class StudentReportViolation {
  const StudentReportViolation({
    required this.id,
    required this.code,
    required this.name,
    required this.level,
    required this.points,
    this.violationTypeId,
    this.note,
  });
  factory StudentReportViolation.fromJson(Map<String, dynamic> json) =>
      StudentReportViolation(
        id: _integer(json['id']),
        violationTypeId: _nullableInteger(json['jenis_pelanggaran_id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        level: json['tingkat'] as String? ?? '',
        points: _integer(json['poin']),
        note: json['catatan'] as String?,
      );
  final int id;
  final int? violationTypeId;
  final String code;
  final String name;
  final String level;
  final int points;
  final String? note;
}

class StudentReportDecision {
  const StudentReportDecision({
    required this.id,
    required this.result,
    required this.resultLabel,
    this.note,
    this.officer,
    this.processedAt,
  });
  factory StudentReportDecision.fromJson(Map<String, dynamic> json) =>
      StudentReportDecision(
        id: _integer(json['id']),
        result: json['hasil'] as String? ?? '',
        resultLabel: json['label_hasil'] as String? ?? '-',
        note: json['catatan'] as String?,
        officer: json['petugas'] as String?,
        processedAt: _dateTime(json['diproses_pada']),
      );
  final int id;
  final String result;
  final String resultLabel;
  final String? note;
  final String? officer;
  final DateTime? processedAt;
}

class StudentReportApproval {
  const StudentReportApproval({
    required this.id,
    required this.typeLabel,
    required this.decisionLabel,
    this.note,
    this.officer,
    this.processedAt,
  });
  factory StudentReportApproval.fromJson(Map<String, dynamic> json) =>
      StudentReportApproval(
        id: _integer(json['id']),
        typeLabel: json['label_jenis'] as String? ?? '-',
        decisionLabel: json['label_keputusan'] as String? ?? '-',
        note: json['catatan'] as String?,
        officer: json['petugas'] as String?,
        processedAt: _dateTime(json['diproses_pada']),
      );
  final int id;
  final String typeLabel;
  final String decisionLabel;
  final String? note;
  final String? officer;
  final DateTime? processedAt;
}

class StudentReportEvidence {
  const StudentReportEvidence({
    required this.id,
    required this.type,
    required this.fileName,
    required this.mimeType,
    required this.size,
    required this.sizeLabel,
    this.note,
    this.uploadedAt,
  });
  factory StudentReportEvidence.fromJson(Map<String, dynamic> json) =>
      StudentReportEvidence(
        id: _integer(json['id']),
        type: json['jenis'] as String? ?? '',
        fileName: json['nama_file'] as String? ?? '-',
        mimeType: json['tipe_file'] as String? ?? 'application/octet-stream',
        size: _integer(json['ukuran_file']),
        sizeLabel: json['ukuran_ringkas'] as String? ?? '-',
        note: json['keterangan'] as String?,
        uploadedAt: _dateTime(json['diunggah_pada']),
      );
  final int id;
  final String type;
  final String fileName;
  final String mimeType;
  final int size;
  final String sizeLabel;
  final String? note;
  final DateTime? uploadedAt;
}

class StudentReportWitness {
  const StudentReportWitness({
    required this.id,
    required this.typeLabel,
    required this.name,
    required this.statement,
    this.recordedAt,
  });
  factory StudentReportWitness.fromJson(Map<String, dynamic> json) =>
      StudentReportWitness(
        id: _integer(json['id']),
        typeLabel: json['label_jenis'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        statement: json['pernyataan'] as String? ?? '-',
        recordedAt: _dateTime(json['dicatat_pada']),
      );
  final int id;
  final String typeLabel;
  final String name;
  final String statement;
  final DateTime? recordedAt;
}

class StudentReportClarification {
  const StudentReportClarification({
    required this.id,
    required this.methodLabel,
    required this.content,
    this.companion,
    this.recordedBy,
    this.deliveredAt,
  });
  factory StudentReportClarification.fromJson(Map<String, dynamic> json) =>
      StudentReportClarification(
        id: _integer(json['id']),
        methodLabel: json['label_metode'] as String? ?? '-',
        content: json['isi'] as String? ?? '-',
        companion: json['pendamping'] as String?,
        recordedBy: json['dicatat_oleh'] as String?,
        deliveredAt: _dateTime(json['disampaikan_pada']),
      );
  final int id;
  final String methodLabel;
  final String content;
  final String? companion;
  final String? recordedBy;
  final DateTime? deliveredAt;
}

class StudentReportFollowUp {
  const StudentReportFollowUp({
    required this.id,
    required this.typeLabel,
    required this.date,
    required this.summary,
    required this.statusLabel,
    this.time,
    this.officer,
    this.result,
    this.nextPlan,
  });
  factory StudentReportFollowUp.fromJson(Map<String, dynamic> json) =>
      StudentReportFollowUp(
        id: _integer(json['id']),
        typeLabel: json['label_jenis'] as String? ?? '-',
        date: json['tanggal'] as String? ?? '',
        time: json['waktu'] as String?,
        officer: json['petugas'] as String?,
        summary: json['ringkasan'] as String? ?? '-',
        result: json['hasil'] as String?,
        nextPlan: json['rencana_lanjutan'] as String?,
        statusLabel: json['label_status'] as String? ?? '-',
      );
  final int id;
  final String typeLabel;
  final String date;
  final String? time;
  final String? officer;
  final String summary;
  final String? result;
  final String? nextPlan;
  final String statusLabel;
}

class StudentReportTimeline {
  const StudentReportTimeline({
    required this.id,
    required this.title,
    this.description,
    this.previousStatus,
    this.nextStatus,
    this.user,
    this.occurredAt,
  });
  factory StudentReportTimeline.fromJson(Map<String, dynamic> json) =>
      StudentReportTimeline(
        id: _integer(json['id']),
        title: json['judul'] as String? ?? '-',
        description: json['keterangan'] as String?,
        previousStatus: json['status_sebelum'] as String?,
        nextStatus: json['status_sesudah'] as String?,
        user: json['pengguna'] as String?,
        occurredAt: _dateTime(json['terjadi_pada']),
      );
  final int id;
  final String title;
  final String? description;
  final String? previousStatus;
  final String? nextStatus;
  final String? user;
  final DateTime? occurredAt;
}

class StudentReportEvidenceDownload {
  const StudentReportEvidenceDownload({
    required this.fileName,
    required this.mimeType,
    required this.bytes,
  });
  final String fileName;
  final String mimeType;
  final Uint8List bytes;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

T? _nullableMap<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is Map ? convert(Map<String, dynamic>.from(value)) : null;

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => convert(Map<String, dynamic>.from(item)))
          .toList(growable: false)
    : <T>[];

int _integer(Object? value) => value is num ? value.toInt() : 0;
int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
DateTime? _dateTime(Object? value) =>
    value is String ? DateTime.tryParse(value) : null;
