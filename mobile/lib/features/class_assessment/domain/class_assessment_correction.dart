import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';

class AssessmentCorrectionData {
  const AssessmentCorrectionData({
    required this.generatedAt,
    required this.assessment,
    required this.manualQuestionCount,
    required this.summary,
    required this.classes,
    required this.statuses,
    required this.selectedStatus,
    required this.items,
    this.selectedClassId,
  });

  factory AssessmentCorrectionData.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    final filter = _map(json['filter']);
    return AssessmentCorrectionData(
      generatedAt: _date(json['dihasilkan_pada']),
      assessment: AssessmentOperationHeader.fromJson(_map(json['asesmen'])),
      manualQuestionCount: _integer(json['jumlah_soal_manual']),
      summary: AssessmentCorrectionSummary.fromJson(_map(json['ringkasan'])),
      classes: _list(references['kelas'], AssessmentFilterClass.fromJson),
      statuses: _list(references['status'], AssessmentFilterOption.fromJson),
      selectedClassId: _nullableInteger(filter['kelas_id']),
      selectedStatus: filter['status'] as String? ?? 'semua',
      items: _list(json['items'], AssessmentCorrectionItem.fromJson),
    );
  }

  final DateTime? generatedAt;
  final AssessmentOperationHeader assessment;
  final int manualQuestionCount;
  final AssessmentCorrectionSummary summary;
  final List<AssessmentFilterClass> classes;
  final List<AssessmentFilterOption> statuses;
  final int? selectedClassId;
  final String selectedStatus;
  final List<AssessmentCorrectionItem> items;
}

class AssessmentCorrectionSummary {
  const AssessmentCorrectionSummary({
    required this.total,
    required this.answered,
    required this.unanswered,
    required this.pending,
    required this.corrected,
  });

  factory AssessmentCorrectionSummary.fromJson(Map<String, dynamic> json) =>
      AssessmentCorrectionSummary(
        total: _integer(json['total']),
        answered: _integer(json['terjawab']),
        unanswered: _integer(json['belum_dijawab']),
        pending: _integer(json['belum_dikoreksi']),
        corrected: _integer(json['sudah_dikoreksi']),
      );

  final int total;
  final int answered;
  final int unanswered;
  final int pending;
  final int corrected;
}

class AssessmentCorrectionItem {
  const AssessmentCorrectionItem({
    required this.id,
    required this.participantId,
    required this.student,
    required this.className,
    required this.question,
    required this.answer,
    required this.answered,
    required this.corrected,
    this.answerId,
    this.score,
  });

  factory AssessmentCorrectionItem.fromJson(Map<String, dynamic> json) =>
      AssessmentCorrectionItem(
        id: json['id'] as String? ?? '-',
        answerId: _nullableInteger(json['jawaban_id']),
        participantId: _integer(json['peserta_id']),
        student: AssessmentParticipantStudent.fromJson(_map(json['siswa'])),
        className: json['kelas'] as String? ?? '-',
        question: AssessmentCorrectionQuestion.fromJson(_map(json['soal'])),
        answer: json['jawaban'] as String? ?? '',
        answered: json['sudah_dijawab'] as bool? ?? false,
        corrected: json['sudah_dikoreksi'] as bool? ?? false,
        score: _nullableDecimal(json['skor']),
      );

  final String id;
  final int? answerId;
  final int participantId;
  final AssessmentParticipantStudent student;
  final String className;
  final AssessmentCorrectionQuestion question;
  final String answer;
  final bool answered;
  final bool corrected;
  final double? score;
}

class AssessmentCorrectionQuestion {
  const AssessmentCorrectionQuestion({
    required this.id,
    required this.number,
    required this.code,
    required this.type,
    required this.typeLabel,
    required this.question,
    required this.weight,
    this.rubric,
  });

  factory AssessmentCorrectionQuestion.fromJson(Map<String, dynamic> json) =>
      AssessmentCorrectionQuestion(
        id: _integer(json['id']),
        number: _integer(json['nomor']),
        code: json['kode'] as String? ?? '-',
        type: json['jenis'] as String? ?? '-',
        typeLabel: json['label_jenis'] as String? ?? '-',
        question: json['pertanyaan'] as String? ?? '-',
        rubric: json['rubrik'] as String?,
        weight: _decimal(json['bobot']),
      );

  final int id;
  final int number;
  final String code;
  final String type;
  final String typeLabel;
  final String question;
  final String? rubric;
  final double weight;
}

class AssessmentScorePayload {
  const AssessmentScorePayload({required this.answerId, required this.score});

  final int answerId;
  final double? score;

  Map<String, dynamic> toJson() => {'jawaban_id': answerId, 'nilai': score};
}

class AssessmentApplyResult {
  const AssessmentApplyResult({
    required this.message,
    required this.applied,
    required this.notFinished,
    required this.automaticCorrectionPending,
    required this.manualCorrectionPending,
    required this.invalidTarget,
  });

  factory AssessmentApplyResult.fromJson(Map<String, dynamic> json) {
    final data = _map(json['data']);
    return AssessmentApplyResult(
      message: json['pesan'] as String? ?? 'Penerapan nilai selesai.',
      applied: _integer(data['diterapkan']),
      notFinished: _integer(data['belum_selesai']),
      automaticCorrectionPending: _integer(data['perlu_koreksi_otomatis']),
      manualCorrectionPending: _integer(data['perlu_koreksi_manual']),
      invalidTarget: _integer(data['tujuan_tidak_valid']),
    );
  }

  final String message;
  final int applied;
  final int notFinished;
  final int automaticCorrectionPending;
  final int manualCorrectionPending;
  final int invalidTarget;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => convert(Map<String, dynamic>.from(item)))
        .toList(growable: false);
int _integer(Object? value) => switch (value) {
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};
int? _nullableInteger(Object? value) => value == null ? null : _integer(value);
double _decimal(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? 0,
  _ => 0,
};
double? _nullableDecimal(Object? value) =>
    value == null ? null : _decimal(value);
DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
