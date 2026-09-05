import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';

class CentralExamResultsPage {
  const CentralExamResultsPage({
    required this.summary,
    required this.items,
    required this.statuses,
    required this.filter,
    required this.pagination,
  });

  factory CentralExamResultsPage.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    return CentralExamResultsPage(
      summary: CentralExamResultsListSummary.fromJson(_map(json['ringkasan'])),
      items: _list(json['items'], CentralExamResultEvent.fromJson),
      statuses: _list(references['status'], CentralExamResultOption.fromJson),
      filter: CentralExamResultsFilter.fromJson(_map(json['filter'])),
      pagination: CentralExamResultsPagination.fromJson(_map(json['paginasi'])),
    );
  }

  final CentralExamResultsListSummary summary;
  final List<CentralExamResultEvent> items;
  final List<CentralExamResultOption> statuses;
  final CentralExamResultsFilter filter;
  final CentralExamResultsPagination pagination;

  CentralExamResultsPage append(CentralExamResultsPage next) =>
      CentralExamResultsPage(
        summary: next.summary,
        items: [...items, ...next.items],
        statuses: next.statuses,
        filter: next.filter,
        pagination: next.pagination,
      );
}

class CentralExamResultsListSummary {
  const CentralExamResultsListSummary({
    required this.total,
    required this.active,
    required this.finished,
  });
  factory CentralExamResultsListSummary.fromJson(Map<String, dynamic> json) =>
      CentralExamResultsListSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        finished: _integer(json['selesai']),
      );
  final int total;
  final int active;
  final int finished;
}

class CentralExamResultEvent {
  const CentralExamResultEvent({
    required this.id,
    required this.code,
    required this.name,
    required this.semester,
    required this.period,
    required this.status,
    required this.statusLabel,
    required this.scheduleCount,
    required this.participantCount,
    required this.finishedParticipantCount,
    required this.appliedCount,
    this.type,
    this.academicYear,
  });
  factory CentralExamResultEvent.fromJson(Map<String, dynamic> json) =>
      CentralExamResultEvent(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        type: json['jenis'] as String?,
        academicYear: json['tahun_pelajaran'] as String?,
        semester: json['semester'] as String? ?? '-',
        period: json['periode'] as String? ?? '-',
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        scheduleCount: _integer(json['jumlah_jadwal']),
        participantCount: _integer(json['jumlah_peserta']),
        finishedParticipantCount: _integer(json['peserta_selesai']),
        appliedCount: _integer(json['sudah_masuk_nilai']),
      );
  final int id;
  final String code;
  final String name;
  final String? type;
  final String? academicYear;
  final String semester;
  final String period;
  final String status;
  final String statusLabel;
  final int scheduleCount;
  final int participantCount;
  final int finishedParticipantCount;
  final int appliedCount;
}

class CentralExamResultsDetail {
  const CentralExamResultsDetail({
    required this.event,
    required this.schedules,
    required this.selectedScheduleId,
    required this.canApply,
    required this.results,
  });
  factory CentralExamResultsDetail.fromJson(Map<String, dynamic> json) =>
      CentralExamResultsDetail(
        event: CentralExamResultEvent.fromJson(_map(json['kegiatan'])),
        schedules: _list(json['jadwal'], CentralExamResultSchedule.fromJson),
        selectedScheduleId: _nullableInteger(json['jadwal_terpilih_id']),
        canApply: json['dapat_menerapkan_nilai'] as bool? ?? false,
        results: AssessmentResultsData.fromJson(_map(json['hasil'])),
      );
  final CentralExamResultEvent event;
  final List<CentralExamResultSchedule> schedules;
  final int? selectedScheduleId;
  final bool canApply;
  final AssessmentResultsData results;
}

class CentralExamResultSchedule {
  const CentralExamResultSchedule({
    required this.id,
    required this.label,
    required this.subject,
    required this.time,
    required this.grade,
    required this.participantCount,
    required this.canApply,
    required this.packageAvailable,
    this.date,
  });
  factory CentralExamResultSchedule.fromJson(Map<String, dynamic> json) =>
      CentralExamResultSchedule(
        id: _integer(json['id']),
        label: json['label'] as String? ?? '-',
        subject: json['mata_pelajaran'] as String? ?? '-',
        date: json['tanggal'] as String?,
        time: json['waktu'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        participantCount: _integer(json['jumlah_peserta']),
        canApply: json['dapat_menerapkan_nilai'] as bool? ?? false,
        packageAvailable: json['paket_tersedia'] as bool? ?? false,
      );
  final int id;
  final String label;
  final String subject;
  final String? date;
  final String time;
  final int grade;
  final int participantCount;
  final bool canApply;
  final bool packageAvailable;
}

class CentralExamResultOption {
  const CentralExamResultOption({required this.code, required this.label});
  factory CentralExamResultOption.fromJson(Map<String, dynamic> json) =>
      CentralExamResultOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class CentralExamResultsFilter {
  const CentralExamResultsFilter({required this.query, required this.status});
  factory CentralExamResultsFilter.fromJson(Map<String, dynamic> json) =>
      CentralExamResultsFilter(
        query: json['kata_kunci'] as String? ?? '',
        status: json['status'] as String? ?? 'semua',
      );
  final String query;
  final String status;
}

class CentralExamResultsPagination {
  const CentralExamResultsPagination({
    required this.page,
    required this.lastPage,
    required this.total,
    required this.hasNextPage,
  });
  factory CentralExamResultsPagination.fromJson(Map<String, dynamic> json) =>
      CentralExamResultsPagination(
        page: _integer(json['halaman'], 1),
        lastPage: _integer(json['halaman_terakhir'], 1),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int lastPage;
  final int total;
  final bool hasNextPage;
}

class CentralExamApplyResult {
  const CentralExamApplyResult({required this.message});
  factory CentralExamApplyResult.fromJson(Map<String, dynamic> json) =>
      CentralExamApplyResult(
        message: json['pesan'] as String? ?? 'Nilai berhasil diterapkan.',
      );
  final String message;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => convert(Map<String, dynamic>.from(item)))
        .toList(growable: false);
int _integer(Object? value, [int fallback = 0]) => switch (value) {
  num number => number.toInt(),
  String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};
int? _nullableInteger(Object? value) => value == null ? null : _integer(value);
