import 'package:nusa/features/student_report/domain/student_report.dart';

class ReportVerificationPage {
  const ReportVerificationPage({
    required this.items,
    required this.summary,
    required this.queueOptions,
    required this.filter,
    required this.pagination,
    required this.access,
  });

  factory ReportVerificationPage.fromJson(Map<String, dynamic> json) =>
      ReportVerificationPage(
        items: _list(json['items'], ReportVerificationTask.fromJson),
        summary: ReportVerificationSummary.fromJson(_map(json['ringkasan'])),
        queueOptions: _list(
          json['pilihan_antrean'],
          StudentReportCodeOption.fromJson,
        ),
        filter: ReportVerificationFilter.fromJson(_map(json['filter'])),
        pagination: StudentReportPagination.fromJson(_map(json['paginasi'])),
        access: ReportVerificationAccess.fromJson(_map(json['hak_akses'])),
      );

  final List<ReportVerificationTask> items;
  final ReportVerificationSummary summary;
  final List<StudentReportCodeOption> queueOptions;
  final ReportVerificationFilter filter;
  final StudentReportPagination pagination;
  final ReportVerificationAccess access;

  ReportVerificationPage append(ReportVerificationPage next) =>
      ReportVerificationPage(
        items: [...items, ...next.items],
        summary: next.summary,
        queueOptions: next.queueOptions,
        filter: next.filter,
        pagination: next.pagination,
        access: next.access,
      );
}

class ReportVerificationSummary {
  const ReportVerificationSummary({
    required this.active,
    required this.counseling,
    required this.approval,
    required this.overdue,
    required this.completed,
  });

  factory ReportVerificationSummary.fromJson(Map<String, dynamic> json) =>
      ReportVerificationSummary(
        active: _integer(json['aktif']),
        counseling: _integer(json['bk']),
        approval: _integer(json['wakil']),
        overdue: _integer(json['terlambat']),
        completed: _integer(json['selesai']),
      );

  final int active;
  final int counseling;
  final int approval;
  final int overdue;
  final int completed;
}

class ReportVerificationFilter {
  const ReportVerificationFilter({required this.query, required this.queue});

  factory ReportVerificationFilter.fromJson(Map<String, dynamic> json) =>
      ReportVerificationFilter(
        query: json['kata_kunci'] as String? ?? '',
        queue: json['antrean'] as String? ?? 'semua',
      );

  final String query;
  final String queue;
}

class ReportVerificationAccess {
  const ReportVerificationAccess({
    required this.canReview,
    required this.canApprove,
    required this.canMonitorAll,
  });

  factory ReportVerificationAccess.fromJson(Map<String, dynamic> json) =>
      ReportVerificationAccess(
        canReview: json['dapat_verifikasi_bk'] as bool? ?? false,
        canApprove: json['dapat_sahkan_wakil'] as bool? ?? false,
        canMonitorAll: json['dapat_memantau_semua'] as bool? ?? false,
      );

  final bool canReview;
  final bool canApprove;
  final bool canMonitorAll;
}

class ReportVerificationTask {
  const ReportVerificationTask({
    required this.report,
    required this.userTask,
    required this.activeStage,
    required this.dayLimit,
    required this.waitingDays,
    required this.remainingDays,
    required this.overdue,
    required this.facts,
    this.lastDecision,
  });

  factory ReportVerificationTask.fromJson(Map<String, dynamic> json) =>
      ReportVerificationTask(
        report: StudentReportItem.fromJson(json),
        userTask: json['tugas_pengguna'] as String? ?? '-',
        activeStage: _integer(json['tahap_aktif']),
        dayLimit: _integer(json['batas_hari']),
        waitingDays: _integer(json['hari_menunggu']),
        remainingDays: _integer(json['sisa_hari']),
        overdue: json['terlambat_diproses'] as bool? ?? false,
        facts: ReportFactCompleteness.fromJson(
          _map(json['kelengkapan_fakta']),
        ),
        lastDecision: _nullableMap(
          json['keputusan_bk_terakhir'],
          ReportLastDecision.fromJson,
        ),
      );

  final StudentReportItem report;
  final String userTask;
  final int activeStage;
  final int dayLimit;
  final int waitingDays;
  final int remainingDays;
  final bool overdue;
  final ReportFactCompleteness facts;
  final ReportLastDecision? lastDecision;
}

class ReportFactCompleteness {
  const ReportFactCompleteness({
    required this.chronology,
    required this.location,
    required this.violation,
    required this.evidence,
    required this.witness,
    required this.clarification,
  });

  factory ReportFactCompleteness.fromJson(Map<String, dynamic> json) =>
      ReportFactCompleteness(
        chronology: json['kronologi'] as bool? ?? false,
        location: json['lokasi'] as bool? ?? false,
        violation: json['butir'] as bool? ?? false,
        evidence: json['bukti'] as bool? ?? false,
        witness: json['saksi'] as bool? ?? false,
        clarification: json['klarifikasi'] as bool? ?? false,
      );

  final bool chronology;
  final bool location;
  final bool violation;
  final bool evidence;
  final bool witness;
  final bool clarification;

  int get completedCount => [
    chronology,
    location,
    violation,
    evidence,
    witness,
    clarification,
  ].where((value) => value).length;
}

class ReportLastDecision {
  const ReportLastDecision({
    required this.result,
    required this.resultLabel,
    this.note,
    this.officer,
    this.processedAt,
  });

  factory ReportLastDecision.fromJson(Map<String, dynamic> json) =>
      ReportLastDecision(
        result: json['hasil'] as String? ?? '',
        resultLabel: json['label_hasil'] as String? ?? '-',
        note: json['catatan'] as String?,
        officer: json['petugas'] as String?,
        processedAt: _dateTime(json['diproses_pada']),
      );

  final String result;
  final String resultLabel;
  final String? note;
  final String? officer;
  final DateTime? processedAt;
}

class ReportVerificationDetail {
  const ReportVerificationDetail({
    required this.reportDetail,
    required this.process,
    required this.reviewOptions,
    required this.approvalOptions,
    required this.violationOptions,
    required this.canReview,
    required this.canApprove,
  });

  factory ReportVerificationDetail.fromJson(Map<String, dynamic> json) {
    final access = _map(json['hak_aksi']);
    return ReportVerificationDetail(
      reportDetail: StudentReportDetail.fromJson(json),
      process: ReportVerificationProcess.fromJson(_map(json['proses'])),
      reviewOptions: _list(
        json['pilihan_hasil_bk'],
        StudentReportCodeOption.fromJson,
      ),
      approvalOptions: _list(
        json['pilihan_keputusan_wakil'],
        StudentReportCodeOption.fromJson,
      ),
      violationOptions: _list(
        json['jenis_pelanggaran'],
        ReportViolationOption.fromJson,
      ),
      canReview: access['dapat_verifikasi_bk'] as bool? ?? false,
      canApprove: access['dapat_sahkan_wakil'] as bool? ?? false,
    );
  }

  final StudentReportDetail reportDetail;
  final ReportVerificationProcess process;
  final List<StudentReportCodeOption> reviewOptions;
  final List<StudentReportCodeOption> approvalOptions;
  final List<ReportViolationOption> violationOptions;
  final bool canReview;
  final bool canApprove;
}

class ReportVerificationProcess {
  const ReportVerificationProcess({
    required this.userTask,
    required this.activeStage,
    required this.dayLimit,
    required this.waitingDays,
    required this.remainingDays,
    required this.overdue,
    required this.facts,
  });

  factory ReportVerificationProcess.fromJson(Map<String, dynamic> json) =>
      ReportVerificationProcess(
        userTask: json['tugas_pengguna'] as String? ?? '-',
        activeStage: _integer(json['tahap_aktif']),
        dayLimit: _integer(json['batas_hari']),
        waitingDays: _integer(json['hari_menunggu']),
        remainingDays: _integer(json['sisa_hari']),
        overdue: json['terlambat_diproses'] as bool? ?? false,
        facts: ReportFactCompleteness.fromJson(
          _map(json['kelengkapan_fakta']),
        ),
      );

  final String userTask;
  final int activeStage;
  final int dayLimit;
  final int waitingDays;
  final int remainingDays;
  final bool overdue;
  final ReportFactCompleteness facts;
}

class ReportViolationOption {
  const ReportViolationOption({
    required this.id,
    required this.code,
    required this.name,
    required this.level,
    required this.points,
    this.category,
  });

  factory ReportViolationOption.fromJson(Map<String, dynamic> json) =>
      ReportViolationOption(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        level: json['tingkat'] as String? ?? '',
        points: _integer(json['poin']),
        category: json['kategori'] as String?,
      );

  final int id;
  final String code;
  final String name;
  final String level;
  final int points;
  final String? category;
}

class ReportVerificationMutation {
  const ReportVerificationMutation({
    required this.message,
    required this.status,
    required this.statusLabel,
    required this.totalPoints,
  });

  factory ReportVerificationMutation.fromJson(Map<String, dynamic> json) {
    final data = _map(json['data']);
    return ReportVerificationMutation(
      message: json['message'] as String? ?? 'Keputusan berhasil disimpan.',
      status: data['status_verifikasi'] as String? ?? '',
      statusLabel: data['label_status_verifikasi'] as String? ?? '-',
      totalPoints: _integer(data['total_poin']),
    );
  }

  final String message;
  final String status;
  final String statusLabel;
  final int totalPoints;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

T? _nullableMap<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
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

DateTime? _dateTime(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
