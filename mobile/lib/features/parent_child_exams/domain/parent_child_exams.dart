class ParentChildExamsPage {
  const ParentChildExamsPage({
    required this.monitoringOnly,
    required this.selectedStudentId,
    required this.children,
    required this.summary,
    required this.exams,
    required this.notice,
    this.student,
  });

  factory ParentChildExamsPage.fromJson(Map<String, dynamic> json) =>
      ParentChildExamsPage(
        monitoringOnly: json['hanya_pemantauan'] as bool? ?? true,
        student: _nullable(json['siswa'], ExamChild.fromJson),
        selectedStudentId: _nullableInteger(json['siswa_id']),
        children: _list(json['pilihan_siswa'], ExamChild.fromJson),
        summary: ParentExamSummary.fromJson(_map(json['ringkasan'])),
        exams: _list(json['items'], ParentExam.fromJson),
        notice:
            json['catatan'] as String? ??
            'Halaman ini hanya digunakan untuk memantau ujian anak.',
      );

  final bool monitoringOnly;
  final ExamChild? student;
  final int? selectedStudentId;
  final List<ExamChild> children;
  final ParentExamSummary summary;
  final List<ParentExam> exams;
  final String notice;
}

class ExamChild {
  const ExamChild({required this.id, required this.name, this.nis, this.nisn});

  factory ExamChild.fromJson(Map<String, dynamic> json) => ExamChild(
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

class ParentExamSummary {
  const ParentExamSummary({
    required this.active,
    required this.upcoming,
    required this.completed,
    required this.total,
  });

  factory ParentExamSummary.fromJson(Map<String, dynamic> json) =>
      ParentExamSummary(
        active: _integer(json['aktif']),
        upcoming: _integer(json['akan_datang']),
        completed: _integer(json['selesai']),
        total: _integer(json['total']),
      );

  final int active;
  final int upcoming;
  final int completed;
  final int total;
}

class ParentExam {
  const ParentExam({
    required this.id,
    required this.examId,
    required this.name,
    required this.subject,
    required this.group,
    required this.statusLabel,
    required this.statusTone,
    required this.workStatus,
    required this.durationMinutes,
    required this.progress,
    required this.result,
    this.code,
    this.examType,
    this.schoolClass,
    this.startAt,
    this.endAt,
    this.date,
    this.time,
    this.participantNumber,
  });

  factory ParentExam.fromJson(Map<String, dynamic> json) => ParentExam(
    id: _integer(json['id']),
    examId: _integer(json['ujian_id']),
    name: json['nama'] as String? ?? '-',
    code: json['kode'] as String?,
    examType: json['jenis_ujian'] as String?,
    subject: json['mata_pelajaran'] as String? ?? '-',
    schoolClass: json['kelas'] as String?,
    group: json['kelompok'] as String? ?? 'akan_datang',
    statusLabel: json['label_status'] as String? ?? '-',
    statusTone: json['nada_status'] as String? ?? 'menunggu',
    workStatus: json['status_pengerjaan'] as String? ?? '-',
    startAt: _date(json['waktu_mulai']),
    endAt: _date(json['waktu_selesai']),
    date: json['tanggal'] as String?,
    time: json['waktu'] as String?,
    durationMinutes: _integer(json['durasi_menit']),
    participantNumber: json['nomor_peserta'] as String?,
    progress: ParentExamProgress.fromJson(_map(json['kemajuan'])),
    result: ParentExamResult.fromJson(_map(json['hasil'])),
  );

  final int id;
  final int examId;
  final String name;
  final String? code;
  final String? examType;
  final String subject;
  final String? schoolClass;
  final String group;
  final String statusLabel;
  final String statusTone;
  final String workStatus;
  final DateTime? startAt;
  final DateTime? endAt;
  final String? date;
  final String? time;
  final int durationMinutes;
  final String? participantNumber;
  final ParentExamProgress progress;
  final ParentExamResult result;
}

class ParentExamProgress {
  const ParentExamProgress({
    required this.questionCount,
    required this.answered,
    required this.unanswered,
  });

  factory ParentExamProgress.fromJson(Map<String, dynamic> json) =>
      ParentExamProgress(
        questionCount: _integer(json['jumlah_soal']),
        answered: _integer(json['terjawab']),
        unanswered: _integer(json['belum_dijawab']),
      );

  final int questionCount;
  final int answered;
  final int unanswered;
}

class ParentExamResult {
  const ParentExamResult({
    required this.visible,
    required this.waitingForCorrection,
    this.score,
    this.minimumScore,
    this.passed,
  });

  factory ParentExamResult.fromJson(Map<String, dynamic> json) =>
      ParentExamResult(
        visible: json['ditampilkan'] as bool? ?? false,
        waitingForCorrection: json['menunggu_koreksi'] as bool? ?? false,
        score: _number(json['nilai']),
        minimumScore: _number(json['kkm']),
        passed: json['tuntas'] as bool?,
      );

  final bool visible;
  final bool waitingForCorrection;
  final double? score;
  final double? minimumScore;
  final bool? passed;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

T? _nullable<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    value is Map ? factory(Map<String, dynamic>.from(value)) : null;

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);

double? _number(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text),
  _ => null,
};

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
