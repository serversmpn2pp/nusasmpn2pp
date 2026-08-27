import 'dart:typed_data';

class TeachingDocumentPage {
  const TeachingDocumentPage({
    required this.employee,
    required this.academicYears,
    required this.filter,
    required this.summary,
    required this.assignments,
    required this.legacyDocuments,
    required this.types,
    required this.uploadLimit,
  });

  factory TeachingDocumentPage.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentPage(
        employee: json['pegawai'] == null
            ? null
            : TeachingDocumentEmployee.fromJson(_map(json['pegawai'])),
        academicYears: _list(
          json['tahun_pelajaran'],
          TeachingDocumentAcademicYear.fromJson,
        ),
        filter: TeachingDocumentFilter.fromJson(_map(json['filter'])),
        summary: TeachingDocumentSummary.fromJson(_map(json['ringkasan'])),
        assignments: _list(
          json['penugasan'],
          TeachingDocumentAssignment.fromJson,
        ),
        legacyDocuments: _list(
          json['dokumen_tanpa_tingkat'],
          TeachingDocument.fromJson,
        ),
        types: _list(json['jenis_perangkat'], TeachingDocumentType.fromJson),
        uploadLimit: TeachingDocumentUploadLimit.fromJson(
          _map(json['batas_unggah']),
        ),
      );

  final TeachingDocumentEmployee? employee;
  final List<TeachingDocumentAcademicYear> academicYears;
  final TeachingDocumentFilter filter;
  final TeachingDocumentSummary summary;
  final List<TeachingDocumentAssignment> assignments;
  final List<TeachingDocument> legacyDocuments;
  final List<TeachingDocumentType> types;
  final TeachingDocumentUploadLimit uploadLimit;
}

class TeachingDocumentDetail {
  const TeachingDocumentDetail({
    required this.document,
    required this.availableGrades,
    required this.histories,
    required this.uploadLimit,
  });

  factory TeachingDocumentDetail.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentDetail(
        document: TeachingDocument.fromJson(_map(json['perangkat_ajar'])),
        availableGrades:
            (json['tingkat_tersedia'] as List<dynamic>? ?? const [])
                .map(_integer)
                .where((grade) => grade > 0)
                .toList(growable: false),
        histories: _list(json['riwayat'], TeachingDocumentHistory.fromJson),
        uploadLimit: TeachingDocumentUploadLimit.fromJson(
          _map(json['batas_unggah']),
        ),
      );

  final TeachingDocument document;
  final List<int> availableGrades;
  final List<TeachingDocumentHistory> histories;
  final TeachingDocumentUploadLimit uploadLimit;
}

class TeachingDocumentEmployee {
  const TeachingDocumentEmployee({
    required this.id,
    required this.name,
    this.nip,
  });

  factory TeachingDocumentEmployee.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentEmployee(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
      );

  final int id;
  final String name;
  final String? nip;
}

class TeachingDocumentAcademicYear {
  const TeachingDocumentAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory TeachingDocumentAcademicYear.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class TeachingDocumentFilter {
  const TeachingDocumentFilter({
    required this.academicYearId,
    required this.semester,
  });

  factory TeachingDocumentFilter.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentFilter(
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        semester: _integer(json['semester']) == 2 ? 2 : 1,
      );

  final int? academicYearId;
  final int semester;
}

class TeachingDocumentSummary {
  const TeachingDocumentSummary({
    required this.requiredCount,
    required this.uploadedCount,
    required this.completeness,
    required this.waitingCount,
    required this.revisionCount,
  });

  factory TeachingDocumentSummary.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentSummary(
        requiredCount: _integer(json['wajib']),
        uploadedCount: _integer(json['terunggah']),
        completeness: _integer(json['kelengkapan']),
        waitingCount: _integer(json['menunggu']),
        revisionCount: _integer(json['perlu_perbaikan']),
      );

  final int requiredCount;
  final int uploadedCount;
  final int completeness;
  final int waitingCount;
  final int revisionCount;
}

class TeachingDocumentAssignment {
  const TeachingDocumentAssignment({
    required this.subject,
    required this.grade,
    required this.gradeLabel,
    required this.slots,
  });

  factory TeachingDocumentAssignment.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentAssignment(
        subject: TeachingDocumentSubject.fromJson(_map(json['mata_pelajaran'])),
        grade: _integer(json['tingkat']),
        gradeLabel: json['label_tingkat'] as String? ?? '-',
        slots: _list(json['dokumen'], TeachingDocumentSlot.fromJson),
      );

  final TeachingDocumentSubject subject;
  final int grade;
  final String gradeLabel;
  final List<TeachingDocumentSlot> slots;

  String get label => '${subject.name} · Tingkat $gradeLabel';
}

class TeachingDocumentSubject {
  const TeachingDocumentSubject({required this.id, required this.name});

  factory TeachingDocumentSubject.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentSubject(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class TeachingDocumentSlot {
  const TeachingDocumentSlot({required this.type, required this.document});

  factory TeachingDocumentSlot.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentSlot(
        type: TeachingDocumentType.fromJson(_map(json['jenis'])),
        document: json['perangkat_ajar'] == null
            ? null
            : TeachingDocument.fromJson(_map(json['perangkat_ajar'])),
      );

  final TeachingDocumentType type;
  final TeachingDocument? document;
}

class TeachingDocumentType {
  const TeachingDocumentType({
    required this.id,
    required this.code,
    required this.name,
    required this.required,
    this.description,
  });

  factory TeachingDocumentType.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentType(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        description: json['deskripsi'] as String?,
        required: json['wajib'] as bool? ?? false,
      );

  final int id;
  final String code;
  final String name;
  final String? description;
  final bool required;
}

class TeachingDocument {
  const TeachingDocument({
    required this.id,
    required this.title,
    required this.grade,
    required this.gradeLabel,
    required this.fileName,
    required this.fileSize,
    required this.status,
    required this.statusLabel,
    this.uploadedAt,
    this.teacherNote,
    this.reviewerNote,
    this.reviewer,
    this.reviewedAt,
    this.academicYear,
    this.subject,
    this.type,
  });

  factory TeachingDocument.fromJson(Map<String, dynamic> json) =>
      TeachingDocument(
        id: _integer(json['id']),
        title: json['judul'] as String? ?? '-',
        grade: _nullableInteger(json['tingkat']),
        gradeLabel: json['label_tingkat'] as String? ?? '-',
        fileName: json['nama_file'] as String? ?? '-',
        fileSize: _integer(json['ukuran_file']),
        status: json['status'] as String? ?? 'menunggu_pemeriksaan',
        statusLabel: json['label_status'] as String? ?? '-',
        uploadedAt: _date(json['diunggah_pada']),
        teacherNote: json['catatan_guru'] as String?,
        reviewerNote: json['catatan_pemeriksa'] as String?,
        reviewer: json['pemeriksa'] as String?,
        reviewedAt: _date(json['diperiksa_pada']),
        academicYear: json['tahun_pelajaran'] == null
            ? null
            : TeachingDocumentAcademicYear.fromJson(
                _map(json['tahun_pelajaran']),
              ),
        subject: json['mata_pelajaran'] == null
            ? null
            : TeachingDocumentSubject.fromJson(_map(json['mata_pelajaran'])),
        type: json['jenis'] == null
            ? null
            : TeachingDocumentType.fromJson(_map(json['jenis'])),
      );

  final int id;
  final String title;
  final int? grade;
  final String gradeLabel;
  final String fileName;
  final int fileSize;
  final String status;
  final String statusLabel;
  final DateTime? uploadedAt;
  final String? teacherNote;
  final String? reviewerNote;
  final String? reviewer;
  final DateTime? reviewedAt;
  final TeachingDocumentAcademicYear? academicYear;
  final TeachingDocumentSubject? subject;
  final TeachingDocumentType? type;
}

class TeachingDocumentHistory {
  const TeachingDocumentHistory({
    required this.id,
    required this.fileName,
    required this.fileSize,
    this.note,
    this.uploadedAt,
    this.uploader,
  });

  factory TeachingDocumentHistory.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentHistory(
        id: _integer(json['id']),
        fileName: json['nama_file'] as String? ?? '-',
        fileSize: _integer(json['ukuran_file']),
        note: json['catatan'] as String?,
        uploadedAt: _date(json['diunggah_pada']),
        uploader: json['pengunggah'] as String?,
      );

  final int id;
  final String fileName;
  final int fileSize;
  final String? note;
  final DateTime? uploadedAt;
  final String? uploader;
}

class TeachingDocumentUploadLimit {
  const TeachingDocumentUploadLimit({
    required this.bytes,
    required this.label,
    required this.serverLimited,
  });

  factory TeachingDocumentUploadLimit.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentUploadLimit(
        bytes: _integer(json['byte']),
        label: json['label'] as String? ?? '10 MB',
        serverLimited: json['dibatasi_server'] as bool? ?? false,
      );

  final int bytes;
  final String label;
  final bool serverLimited;
}

class TeachingDocumentPickedFile {
  const TeachingDocumentPickedFile({required this.name, required this.bytes});

  final String name;
  final Uint8List bytes;

  int get size => bytes.lengthInBytes;
}

class TeachingDocumentFormValue {
  const TeachingDocumentFormValue({
    required this.grade,
    required this.title,
    required this.teacherNote,
    this.academicYearId,
    this.semester,
    this.subjectId,
    this.typeId,
    this.file,
  });

  final int? academicYearId;
  final int? semester;
  final int? subjectId;
  final int grade;
  final int? typeId;
  final String title;
  final String? teacherNote;
  final TeachingDocumentPickedFile? file;
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

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value) : null;
