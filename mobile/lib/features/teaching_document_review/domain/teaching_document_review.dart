import 'dart:typed_data';

import 'package:nusa/features/teaching_document/domain/teaching_document.dart';

class TeachingDocumentReviewPage {
  const TeachingDocumentReviewPage({
    required this.items,
    required this.summary,
    required this.academicYears,
    required this.filter,
    required this.pagination,
    required this.canReview,
  });

  factory TeachingDocumentReviewPage.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentReviewPage(
        items: _list(json['items'], TeachingDocumentTeacherReview.fromJson),
        summary: TeachingDocumentReviewSummary.fromJson(
          _map(json['ringkasan']),
        ),
        academicYears: _list(
          json['tahun_pelajaran'],
          TeachingDocumentAcademicYear.fromJson,
        ),
        filter: TeachingDocumentReviewFilter.fromJson(_map(json['filter'])),
        pagination: TeachingDocumentReviewPagination.fromJson(
          _map(json['paginasi']),
        ),
        canReview: _map(json['hak_akses'])['dapat_memeriksa'] as bool? ?? false,
      );

  final List<TeachingDocumentTeacherReview> items;
  final TeachingDocumentReviewSummary summary;
  final List<TeachingDocumentAcademicYear> academicYears;
  final TeachingDocumentReviewFilter filter;
  final TeachingDocumentReviewPagination pagination;
  final bool canReview;

  TeachingDocumentReviewPage append(TeachingDocumentReviewPage next) =>
      TeachingDocumentReviewPage(
        items: [...items, ...next.items],
        summary: next.summary,
        academicYears: next.academicYears,
        filter: next.filter,
        pagination: next.pagination,
        canReview: next.canReview,
      );
}

class TeachingDocumentTeacherReview {
  const TeachingDocumentTeacherReview({
    required this.employee,
    required this.subjects,
    required this.grades,
    required this.requiredCount,
    required this.uploadedCount,
    required this.percentage,
    required this.complete,
    required this.waitingCount,
    required this.revisionCount,
    required this.reviewedCount,
  });

  factory TeachingDocumentTeacherReview.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentTeacherReview(
        employee: TeachingDocumentEmployee.fromJson(_map(json['pegawai'])),
        subjects: _list(
          json['mata_pelajaran'],
          TeachingDocumentSubject.fromJson,
        ),
        grades: (json['tingkat'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .toList(growable: false),
        requiredCount: _integer(json['jumlah_wajib']),
        uploadedCount: _integer(json['jumlah_terunggah_wajib']),
        percentage: _integer(json['persentase']),
        complete: json['lengkap'] as bool? ?? false,
        waitingCount: _integer(json['jumlah_menunggu']),
        revisionCount: _integer(json['jumlah_perlu_perbaikan']),
        reviewedCount: _integer(json['jumlah_sudah_diperiksa']),
      );

  final TeachingDocumentEmployee employee;
  final List<TeachingDocumentSubject> subjects;
  final List<String> grades;
  final int requiredCount;
  final int uploadedCount;
  final int percentage;
  final bool complete;
  final int waitingCount;
  final int revisionCount;
  final int reviewedCount;
}

class TeachingDocumentReviewSummary {
  const TeachingDocumentReviewSummary({
    required this.teacherCount,
    required this.completeCount,
    required this.incompleteCount,
    required this.waitingCount,
    required this.revisionCount,
  });

  factory TeachingDocumentReviewSummary.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentReviewSummary(
        teacherCount: _integer(json['jumlah_guru']),
        completeCount: _integer(json['lengkap']),
        incompleteCount: _integer(json['belum_lengkap']),
        waitingCount: _integer(json['menunggu_pemeriksaan']),
        revisionCount: _integer(json['perlu_perbaikan']),
      );

  final int teacherCount;
  final int completeCount;
  final int incompleteCount;
  final int waitingCount;
  final int revisionCount;
}

class TeachingDocumentReviewFilter {
  const TeachingDocumentReviewFilter({
    required this.academicYearId,
    required this.semester,
    required this.completeness,
    required this.documentStatus,
    required this.query,
  });

  factory TeachingDocumentReviewFilter.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentReviewFilter(
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        semester: _integer(json['semester']) == 2 ? 2 : 1,
        completeness: json['kelengkapan'] as String? ?? 'semua',
        documentStatus: json['status_dokumen'] as String? ?? 'semua',
        query: json['kata_kunci'] as String? ?? '',
      );

  final int? academicYearId;
  final int semester;
  final String completeness;
  final String documentStatus;
  final String query;
}

class TeachingDocumentReviewPagination {
  const TeachingDocumentReviewPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory TeachingDocumentReviewPagination.fromJson(
    Map<String, dynamic> json,
  ) => TeachingDocumentReviewPagination(
    page: _integer(json['halaman']),
    total: _integer(json['total']),
    hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
  );

  final int page;
  final int total;
  final bool hasNextPage;
}

class TeachingDocumentTeacherDetail {
  const TeachingDocumentTeacherDetail({
    required this.employee,
    required this.academicYears,
    required this.filter,
    required this.summary,
    required this.assignments,
    required this.legacyDocuments,
    required this.canReview,
  });

  factory TeachingDocumentTeacherDetail.fromJson(
    Map<String, dynamic> json,
  ) => TeachingDocumentTeacherDetail(
    employee: TeachingDocumentEmployee.fromJson(_map(json['pegawai'])),
    academicYears: _list(
      json['tahun_pelajaran'],
      TeachingDocumentAcademicYear.fromJson,
    ),
    filter: TeachingDocumentFilter.fromJson(_map(json['filter'])),
    summary: TeachingDocumentTeacherSummary.fromJson(_map(json['ringkasan'])),
    assignments: _list(json['penugasan'], TeachingDocumentAssignment.fromJson),
    legacyDocuments: _list(
      json['dokumen_tanpa_tingkat'],
      TeachingDocument.fromJson,
    ),
    canReview: _map(json['hak_akses'])['dapat_memeriksa'] as bool? ?? false,
  );

  final TeachingDocumentEmployee employee;
  final List<TeachingDocumentAcademicYear> academicYears;
  final TeachingDocumentFilter filter;
  final TeachingDocumentTeacherSummary summary;
  final List<TeachingDocumentAssignment> assignments;
  final List<TeachingDocument> legacyDocuments;
  final bool canReview;
}

class TeachingDocumentTeacherSummary {
  const TeachingDocumentTeacherSummary({
    required this.requiredCount,
    required this.uploadedCount,
    required this.completeness,
    required this.waitingCount,
    required this.revisionCount,
    required this.reviewedCount,
  });

  factory TeachingDocumentTeacherSummary.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentTeacherSummary(
        requiredCount: _integer(json['wajib']),
        uploadedCount: _integer(json['terunggah']),
        completeness: _integer(json['kelengkapan']),
        waitingCount: _integer(json['menunggu']),
        revisionCount: _integer(json['perlu_perbaikan']),
        reviewedCount: _integer(json['sudah_diperiksa']),
      );

  final int requiredCount;
  final int uploadedCount;
  final int completeness;
  final int waitingCount;
  final int revisionCount;
  final int reviewedCount;
}

class TeachingDocumentReviewDetail {
  const TeachingDocumentReviewDetail({
    required this.document,
    required this.employee,
    required this.histories,
    required this.canReview,
  });

  factory TeachingDocumentReviewDetail.fromJson(Map<String, dynamic> json) {
    final documentJson = _map(json['perangkat_ajar']);
    return TeachingDocumentReviewDetail(
      document: TeachingDocument.fromJson(documentJson),
      employee: TeachingDocumentEmployee.fromJson(
        _map(documentJson['pegawai']),
      ),
      histories: _list(json['riwayat'], TeachingDocumentHistory.fromJson),
      canReview: _map(json['hak_akses'])['dapat_memeriksa'] as bool? ?? false,
    );
  }

  final TeachingDocument document;
  final TeachingDocumentEmployee employee;
  final List<TeachingDocumentHistory> histories;
  final bool canReview;
}

class TeachingDocumentReviewValue {
  const TeachingDocumentReviewValue({required this.status, this.reviewerNote});

  final String status;
  final String? reviewerNote;

  Map<String, dynamic> toJson() => {
    'status': status,
    'catatan_pemeriksa': reviewerNote?.trim().isEmpty == true
        ? null
        : reviewerNote?.trim(),
  };
}

class TeachingDocumentDownload {
  const TeachingDocumentDownload({required this.fileName, required this.bytes});

  final String fileName;
  final Uint8List bytes;
}

typedef TeachingDocumentTeacherQuery = ({
  int teacherId,
  int? academicYearId,
  int semester,
});

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
