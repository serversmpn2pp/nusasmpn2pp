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
    required this.finalization,
    required this.results,
  });
  factory CentralExamResultsDetail.fromJson(Map<String, dynamic> json) =>
      CentralExamResultsDetail(
        event: CentralExamResultEvent.fromJson(_map(json['kegiatan'])),
        schedules: _list(json['jadwal'], CentralExamResultSchedule.fromJson),
        selectedScheduleId: _nullableInteger(json['jadwal_terpilih_id']),
        canApply: json['dapat_menerapkan_nilai'] as bool? ?? false,
        finalization: CentralExamResultFinalization.fromJson(
          _map(json['finalisasi']),
        ),
        results: AssessmentResultsData.fromJson(_map(json['hasil'])),
      );
  final CentralExamResultEvent event;
  final List<CentralExamResultSchedule> schedules;
  final int? selectedScheduleId;
  final bool canApply;
  final CentralExamResultFinalization finalization;
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
    required this.resultStatus,
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
        resultStatus: json['status_hasil'] as String? ?? 'draf',
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
  final String resultStatus;
}

class CentralExamResultFinalization {
  const CentralExamResultFinalization({
    required this.status,
    required this.statusLabel,
    required this.canManage,
    required this.ready,
    required this.canFinalize,
    required this.canCancelFinalization,
    required this.canPublish,
    required this.canUnpublish,
    required this.readiness,
    this.finalizedAt,
    this.finalizedBy,
    this.publishedAt,
    this.publishedBy,
  });

  factory CentralExamResultFinalization.fromJson(
    Map<String, dynamic> json,
  ) => CentralExamResultFinalization(
    status: json['status'] as String? ?? 'draf',
    statusLabel: json['label_status'] as String? ?? 'Draf hasil',
    canManage: json['dapat_mengelola'] as bool? ?? false,
    ready: json['siap_difinalisasi'] as bool? ?? false,
    canFinalize: json['dapat_finalisasi'] as bool? ?? false,
    canCancelFinalization: json['dapat_batalkan_finalisasi'] as bool? ?? false,
    canPublish: json['dapat_publikasi'] as bool? ?? false,
    canUnpublish: json['dapat_batalkan_publikasi'] as bool? ?? false,
    finalizedAt: DateTime.tryParse(json['difinalisasi_pada'] as String? ?? ''),
    finalizedBy: json['difinalisasi_oleh'] as String?,
    publishedAt: DateTime.tryParse(
      json['dipublikasikan_pada'] as String? ?? '',
    ),
    publishedBy: json['dipublikasikan_oleh'] as String?,
    readiness: CentralExamResultReadiness.fromJson(_map(json['kesiapan'])),
  );

  final String status;
  final String statusLabel;
  final bool canManage;
  final bool ready;
  final bool canFinalize;
  final bool canCancelFinalization;
  final bool canPublish;
  final bool canUnpublish;
  final DateTime? finalizedAt;
  final String? finalizedBy;
  final DateTime? publishedAt;
  final String? publishedBy;
  final CentralExamResultReadiness readiness;

  bool get isFinal => status == 'final' || status == 'dipublikasikan';
  bool get isPublished => status == 'dipublikasikan';
}

class CentralExamResultReadiness {
  const CentralExamResultReadiness({
    required this.totalParticipants,
    required this.requiredParticipants,
    required this.finishedParticipants,
    required this.unfinishedParticipants,
    required this.absentParticipants,
    required this.pendingManualCorrections,
    required this.questionCount,
  });

  factory CentralExamResultReadiness.fromJson(Map<String, dynamic> json) =>
      CentralExamResultReadiness(
        totalParticipants: _integer(json['total_peserta']),
        requiredParticipants: _integer(json['peserta_wajib_selesai']),
        finishedParticipants: _integer(json['peserta_selesai']),
        unfinishedParticipants: _integer(json['peserta_belum_selesai']),
        absentParticipants: _integer(json['peserta_tidak_hadir']),
        pendingManualCorrections: _integer(json['perlu_koreksi_manual']),
        questionCount: _integer(json['jumlah_soal']),
      );

  final int totalParticipants;
  final int requiredParticipants;
  final int finishedParticipants;
  final int unfinishedParticipants;
  final int absentParticipants;
  final int pendingManualCorrections;
  final int questionCount;
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

class CentralExamResultLifecycleResult {
  const CentralExamResultLifecycleResult({
    required this.message,
    required this.finalization,
  });

  factory CentralExamResultLifecycleResult.fromJson(
    Map<String, dynamic> json,
  ) => CentralExamResultLifecycleResult(
    message: json['pesan'] as String? ?? 'Status hasil berhasil diperbarui.',
    finalization: CentralExamResultFinalization.fromJson(_map(json['data'])),
  );

  final String message;
  final CentralExamResultFinalization finalization;
}

enum CentralExamResultLifecycleAction {
  finalize(path: 'finalisasi'),
  cancelFinalization(path: 'finalisasi', isDelete: true),
  publish(path: 'publikasi'),
  unpublish(path: 'publikasi', isDelete: true);

  const CentralExamResultLifecycleAction({
    required this.path,
    this.isDelete = false,
  });

  final String path;
  final bool isDelete;
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
