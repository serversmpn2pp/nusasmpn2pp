class SurveyMonitoringPage {
  const SurveyMonitoringPage({
    required this.items,
    required this.summary,
    required this.academicYears,
    required this.filter,
    required this.pagination,
    required this.minimumRespondents,
  });

  factory SurveyMonitoringPage.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringPage(
        items: _list(json['items'], SurveyMonitoringAssignment.fromJson),
        summary: SurveyMonitoringSummary.fromJson(_map(json['ringkasan'])),
        academicYears: _list(
          json['tahun_pelajaran'],
          SurveyMonitoringAcademicYear.fromJson,
        ),
        filter: SurveyMonitoringFilter.fromJson(_map(json['filter'])),
        pagination: SurveyMonitoringPagination.fromJson(_map(json['paginasi'])),
        minimumRespondents: _integer(json['minimal_responden']),
      );

  final List<SurveyMonitoringAssignment> items;
  final SurveyMonitoringSummary summary;
  final List<SurveyMonitoringAcademicYear> academicYears;
  final SurveyMonitoringFilter filter;
  final SurveyMonitoringPagination pagination;
  final int minimumRespondents;

  SurveyMonitoringPage append(SurveyMonitoringPage next) =>
      SurveyMonitoringPage(
        items: [...items, ...next.items],
        summary: next.summary,
        academicYears: next.academicYears,
        filter: next.filter,
        pagination: next.pagination,
        minimumRespondents: next.minimumRespondents,
      );
}

class SurveyMonitoringAssignment {
  const SurveyMonitoringAssignment({
    required this.id,
    required this.teacherName,
    required this.subjectName,
    required this.className,
    required this.academicYearName,
    required this.active,
    required this.studentCount,
    required this.respondentCount,
    required this.responsePercentage,
    required this.responseStatus,
    required this.resultsOpen,
    this.teacherNip,
    this.average,
  });

  factory SurveyMonitoringAssignment.fromJson(Map<String, dynamic> json) {
    final teacher = _map(json['guru']);
    final subject = _map(json['mata_pelajaran']);
    final schoolClass = _map(json['kelas']);
    final academicYear = _map(json['tahun_pelajaran']);
    return SurveyMonitoringAssignment(
      id: _integer(json['id']),
      teacherName: teacher['nama'] as String? ?? '-',
      teacherNip: teacher['nip'] as String?,
      subjectName: subject['nama'] as String? ?? '-',
      className: schoolClass['nama'] as String? ?? '-',
      academicYearName: academicYear['nama'] as String? ?? '-',
      active: json['aktif'] as bool? ?? false,
      studentCount: _integer(json['jumlah_siswa']),
      respondentCount: _integer(json['jumlah_pengisi']),
      responsePercentage: _decimal(json['persentase_pengisian']),
      responseStatus: json['status_pengisian'] as String? ?? 'belum',
      resultsOpen: json['hasil_terbuka'] as bool? ?? false,
      average: _nullableDecimal(json['rata_rata_keseluruhan']),
    );
  }

  final int id;
  final String teacherName;
  final String? teacherNip;
  final String subjectName;
  final String className;
  final String academicYearName;
  final bool active;
  final int studentCount;
  final int respondentCount;
  final double responsePercentage;
  final String responseStatus;
  final bool resultsOpen;
  final double? average;
}

class SurveyMonitoringSummary {
  const SurveyMonitoringSummary({
    required this.assignments,
    required this.responseTarget,
    required this.responses,
    required this.openResults,
  });

  factory SurveyMonitoringSummary.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringSummary(
        assignments: _integer(json['penugasan']),
        responseTarget: _integer(json['target_respons']),
        responses: _integer(json['respons_masuk']),
        openResults: _integer(json['hasil_terbuka']),
      );

  final int assignments;
  final int responseTarget;
  final int responses;
  final int openResults;
}

class SurveyMonitoringAcademicYear {
  const SurveyMonitoringAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory SurveyMonitoringAcademicYear.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class SurveyMonitoringFilter {
  const SurveyMonitoringFilter({
    required this.semester,
    required this.status,
    required this.query,
    this.academicYearId,
  });

  factory SurveyMonitoringFilter.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringFilter(
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        semester: json['semester'] as String? ?? 'ganjil',
        status: json['status'] as String? ?? 'semua',
        query: json['cari'] as String? ?? '',
      );

  final int? academicYearId;
  final String semester;
  final String status;
  final String query;
}

class SurveyMonitoringPagination {
  const SurveyMonitoringPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory SurveyMonitoringPagination.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class SurveyMonitoringDetail {
  const SurveyMonitoringDetail({
    required this.assignment,
    required this.semester,
    required this.minimumRespondents,
    required this.scale,
    required this.questions,
    required this.suggestions,
  });

  factory SurveyMonitoringDetail.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringDetail(
        assignment: SurveyMonitoringAssignment.fromJson(
          _map(json['penugasan']),
        ),
        semester: json['semester'] as String? ?? 'ganjil',
        minimumRespondents: _integer(json['minimal_responden']),
        scale: _list(json['skala'], SurveyMonitoringScale.fromJson),
        questions: _list(
          json['rincian_pertanyaan'],
          SurveyMonitoringQuestion.fromJson,
        ),
        suggestions: _list(json['saran'], SurveyMonitoringSuggestion.fromJson),
      );

  final SurveyMonitoringAssignment assignment;
  final String semester;
  final int minimumRespondents;
  final List<SurveyMonitoringScale> scale;
  final List<SurveyMonitoringQuestion> questions;
  final List<SurveyMonitoringSuggestion> suggestions;
}

class SurveyMonitoringScale {
  const SurveyMonitoringScale({required this.value, required this.label});

  factory SurveyMonitoringScale.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringScale(
        value: _integer(json['nilai']),
        label: json['label'] as String? ?? '-',
      );

  final int value;
  final String label;
}

class SurveyMonitoringQuestion {
  const SurveyMonitoringQuestion({
    required this.code,
    required this.statement,
    required this.order,
    required this.answerCount,
    required this.distribution,
    this.average,
  });

  factory SurveyMonitoringQuestion.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringQuestion(
        code: json['kode'] as String? ?? '-',
        statement: json['pernyataan'] as String? ?? '-',
        order: _integer(json['urutan']),
        answerCount: _integer(json['jumlah_jawaban']),
        average: _nullableDecimal(json['rata_rata']),
        distribution: _list(
          json['distribusi'],
          SurveyMonitoringDistribution.fromJson,
        ),
      );

  final String code;
  final String statement;
  final int order;
  final int answerCount;
  final double? average;
  final List<SurveyMonitoringDistribution> distribution;
}

class SurveyMonitoringDistribution {
  const SurveyMonitoringDistribution({
    required this.value,
    required this.count,
    required this.percentage,
  });

  factory SurveyMonitoringDistribution.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringDistribution(
        value: _integer(json['nilai']),
        count: _integer(json['jumlah']),
        percentage: _decimal(json['persentase']),
      );

  final int value;
  final int count;
  final double percentage;
}

class SurveyMonitoringSuggestion {
  const SurveyMonitoringSuggestion({required this.text, this.filledAt});

  factory SurveyMonitoringSuggestion.fromJson(Map<String, dynamic> json) =>
      SurveyMonitoringSuggestion(
        text: json['saran'] as String? ?? '-',
        filledAt: switch (json['diisi_pada']) {
          final String value => DateTime.tryParse(value),
          _ => null,
        },
      );

  final String text;
  final DateTime? filledAt;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;

double _decimal(Object? value) => value is num ? value.toDouble() : 0;

double? _nullableDecimal(Object? value) =>
    value is num ? value.toDouble() : null;
