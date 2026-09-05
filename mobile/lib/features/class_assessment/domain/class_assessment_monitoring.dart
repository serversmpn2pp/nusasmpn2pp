class AssessmentMonitoringData {
  const AssessmentMonitoringData({
    required this.generatedAt,
    required this.refreshSeconds,
    required this.assessment,
    required this.readiness,
    required this.summary,
    required this.classes,
    required this.statuses,
    required this.selectedStatus,
    required this.items,
    this.selectedClassId,
  });

  factory AssessmentMonitoringData.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    final filter = _map(json['filter']);
    return AssessmentMonitoringData(
      generatedAt: _date(json['dihasilkan_pada']),
      refreshSeconds: _integer(json['pembaruan_berikutnya_detik'], 15),
      assessment: AssessmentOperationHeader.fromJson(_map(json['asesmen'])),
      readiness: AssessmentReadiness.fromJson(_map(json['kesiapan'])),
      summary: AssessmentMonitoringSummary.fromJson(_map(json['ringkasan'])),
      classes: _list(references['kelas'], AssessmentFilterClass.fromJson),
      statuses: _list(references['status'], AssessmentFilterOption.fromJson),
      selectedClassId: _nullableInteger(filter['kelas_id']),
      selectedStatus: filter['status'] as String? ?? 'semua',
      items: _list(json['items'], AssessmentMonitoringParticipant.fromJson),
    );
  }

  final DateTime? generatedAt;
  final int refreshSeconds;
  final AssessmentOperationHeader assessment;
  final AssessmentReadiness readiness;
  final AssessmentMonitoringSummary summary;
  final List<AssessmentFilterClass> classes;
  final List<AssessmentFilterOption> statuses;
  final int? selectedClassId;
  final String selectedStatus;
  final List<AssessmentMonitoringParticipant> items;
}

class AssessmentResultsData {
  const AssessmentResultsData({
    required this.generatedAt,
    required this.assessment,
    required this.questionCount,
    required this.totalWeight,
    required this.summary,
    required this.classes,
    required this.statuses,
    required this.selectedStatus,
    required this.items,
    this.selectedClassId,
  });

  factory AssessmentResultsData.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    final filter = _map(json['filter']);
    return AssessmentResultsData(
      generatedAt: _date(json['dihasilkan_pada']),
      assessment: AssessmentOperationHeader.fromJson(_map(json['asesmen'])),
      questionCount: _integer(json['jumlah_soal']),
      totalWeight: _decimal(json['bobot_total']),
      summary: AssessmentResultsSummary.fromJson(_map(json['ringkasan'])),
      classes: _list(references['kelas'], AssessmentFilterClass.fromJson),
      statuses: _list(references['status'], AssessmentFilterOption.fromJson),
      selectedClassId: _nullableInteger(filter['kelas_id']),
      selectedStatus: filter['status'] as String? ?? 'semua',
      items: _list(json['items'], AssessmentResultItem.fromJson),
    );
  }

  final DateTime? generatedAt;
  final AssessmentOperationHeader assessment;
  final int questionCount;
  final double totalWeight;
  final AssessmentResultsSummary summary;
  final List<AssessmentFilterClass> classes;
  final List<AssessmentFilterOption> statuses;
  final int? selectedClassId;
  final String selectedStatus;
  final List<AssessmentResultItem> items;
}

class AssessmentOperationHeader {
  const AssessmentOperationHeader({
    required this.id,
    required this.name,
    required this.code,
    required this.subject,
    required this.semester,
    required this.grade,
    required this.status,
    required this.statusLabel,
    required this.durationMinutes,
    required this.packageQuestionCount,
    required this.displayQuestionCount,
    required this.classes,
    this.academicYear,
    this.startsAt,
    this.endsAt,
    this.minimumScore,
  });

  factory AssessmentOperationHeader.fromJson(Map<String, dynamic> json) =>
      AssessmentOperationHeader(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String? ?? '-',
        subject: json['mata_pelajaran'] as String? ?? '-',
        academicYear: json['tahun_pelajaran'] as String?,
        semester: json['semester'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        status: json['status'] as String? ?? '-',
        statusLabel: json['label_status'] as String? ?? '-',
        startsAt: _date(json['tanggal_mulai']),
        endsAt: _date(json['tanggal_selesai']),
        durationMinutes: _integer(json['durasi_menit']),
        minimumScore: _nullableInteger(json['kkm']),
        packageQuestionCount: _integer(json['jumlah_soal_paket']),
        displayQuestionCount: _integer(json['jumlah_soal_tampil']),
        classes: _list(json['kelas'], AssessmentTargetReference.fromJson),
      );

  final int id;
  final String name;
  final String code;
  final String subject;
  final String? academicYear;
  final String semester;
  final int grade;
  final String status;
  final String statusLabel;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final int durationMinutes;
  final int? minimumScore;
  final int packageQuestionCount;
  final int displayQuestionCount;
  final List<AssessmentTargetReference> classes;
}

class AssessmentTargetReference {
  const AssessmentTargetReference({
    required this.id,
    required this.name,
    this.component,
  });
  factory AssessmentTargetReference.fromJson(Map<String, dynamic> json) =>
      AssessmentTargetReference(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        component: json['komponen_nilai'] as String?,
      );
  final int id;
  final String name;
  final String? component;
}

class AssessmentReadiness {
  const AssessmentReadiness({
    required this.packageOpen,
    required this.questionsReady,
    required this.participantsReady,
  });
  factory AssessmentReadiness.fromJson(Map<String, dynamic> json) =>
      AssessmentReadiness(
        packageOpen: json['paket_dibuka'] as bool? ?? false,
        questionsReady: json['soal_siap'] as bool? ?? false,
        participantsReady: json['peserta_siap'] as bool? ?? false,
      );
  final bool packageOpen;
  final bool questionsReady;
  final bool participantsReady;
}

class AssessmentMonitoringSummary {
  const AssessmentMonitoringSummary({
    required this.total,
    required this.notPresent,
    required this.presentNotStarted,
    required this.absent,
    required this.working,
    required this.finished,
    required this.inactive,
    required this.blocked,
  });
  factory AssessmentMonitoringSummary.fromJson(Map<String, dynamic> json) =>
      AssessmentMonitoringSummary(
        total: _integer(json['total']),
        notPresent: _integer(json['belum_hadir']),
        presentNotStarted: _integer(json['hadir_belum_mulai']),
        absent: _integer(json['tidak_hadir']),
        working: _integer(json['sedang_mengerjakan']),
        finished: _integer(json['selesai']),
        inactive: _integer(json['nonaktif']),
        blocked: _integer(json['terblokir']),
      );
  final int total;
  final int notPresent;
  final int presentNotStarted;
  final int absent;
  final int working;
  final int finished;
  final int inactive;
  final int blocked;

  int get finishedPercent =>
      total == 0 ? 0 : ((finished / total) * 100).round();
}

class AssessmentParticipantStudent {
  const AssessmentParticipantStudent({
    required this.id,
    required this.name,
    this.studentNumber,
    this.nationalStudentNumber,
    this.rollNumber,
  });
  factory AssessmentParticipantStudent.fromJson(Map<String, dynamic> json) =>
      AssessmentParticipantStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        studentNumber: json['nis'] as String?,
        nationalStudentNumber: json['nisn'] as String?,
        rollNumber: _nullableInteger(json['nomor_absen']),
      );
  final int id;
  final String name;
  final String? studentNumber;
  final String? nationalStudentNumber;
  final int? rollNumber;
}

class AssessmentMonitoringParticipant {
  const AssessmentMonitoringParticipant({
    required this.id,
    required this.student,
    required this.className,
    required this.status,
    required this.statusLabel,
    required this.statusTone,
    required this.attendance,
    required this.attendanceLabel,
    required this.attendanceTone,
    required this.savedAnswers,
    required this.doubtfulAnswers,
    required this.answerPercent,
    required this.appSwitchCount,
    required this.totalAwaySeconds,
    this.startedAt,
    this.finishedAt,
    this.remainingMinutes,
    this.lastHeartbeatAt,
    this.safeModeLockedAt,
  });
  factory AssessmentMonitoringParticipant.fromJson(Map<String, dynamic> json) =>
      AssessmentMonitoringParticipant(
        id: _integer(json['id']),
        student: AssessmentParticipantStudent.fromJson(_map(json['siswa'])),
        className: json['kelas'] as String? ?? '-',
        status: json['status'] as String? ?? '-',
        statusLabel: json['label_status'] as String? ?? '-',
        statusTone: json['nada_status'] as String? ?? 'netral',
        attendance: json['kehadiran'] as String? ?? '-',
        attendanceLabel: json['label_kehadiran'] as String? ?? '-',
        attendanceTone: json['nada_kehadiran'] as String? ?? 'netral',
        savedAnswers: _integer(json['jawaban_tersimpan']),
        doubtfulAnswers: _integer(json['jawaban_ragu']),
        answerPercent: _integer(json['persen_jawaban']),
        appSwitchCount: _integer(json['jumlah_pindah_aplikasi']),
        totalAwaySeconds: _integer(json['durasi_di_luar_aplikasi_detik']),
        startedAt: _date(json['waktu_mulai']),
        finishedAt: _date(json['waktu_selesai']),
        remainingMinutes: _nullableInteger(json['sisa_menit']),
        lastHeartbeatAt: _date(json['heartbeat_terakhir_pada']),
        safeModeLockedAt: _date(json['ditahan_mode_aman_pada']),
      );
  final int id;
  final AssessmentParticipantStudent student;
  final String className;
  final String status;
  final String statusLabel;
  final String statusTone;
  final String attendance;
  final String attendanceLabel;
  final String attendanceTone;
  final int savedAnswers;
  final int doubtfulAnswers;
  final int answerPercent;
  final int appSwitchCount;
  final int totalAwaySeconds;
  final DateTime? startedAt;
  final DateTime? finishedAt;
  final int? remainingMinutes;
  final DateTime? lastHeartbeatAt;
  final DateTime? safeModeLockedAt;
}

class AssessmentResultsSummary {
  const AssessmentResultsSummary({
    required this.total,
    required this.finished,
    required this.finalResults,
    required this.passed,
    required this.notPassed,
    required this.needsCorrection,
    required this.notFinished,
    required this.appliedToGrades,
    this.average,
    this.highest,
    this.lowest,
  });
  factory AssessmentResultsSummary.fromJson(Map<String, dynamic> json) =>
      AssessmentResultsSummary(
        total: _integer(json['total_peserta']),
        finished: _integer(json['selesai']),
        finalResults: _integer(json['hasil_final']),
        average: _nullableDecimal(json['rata_rata_final']),
        highest: _nullableDecimal(json['nilai_tertinggi_final']),
        lowest: _nullableDecimal(json['nilai_terendah_final']),
        passed: _integer(json['tuntas']),
        notPassed: _integer(json['belum_tuntas']),
        needsCorrection: _integer(json['perlu_koreksi']),
        notFinished: _integer(json['belum_selesai']),
        appliedToGrades: _integer(json['sudah_masuk_nilai']),
      );
  final int total;
  final int finished;
  final int finalResults;
  final double? average;
  final double? highest;
  final double? lowest;
  final int passed;
  final int notPassed;
  final int needsCorrection;
  final int notFinished;
  final int appliedToGrades;
}

class AssessmentResultItem {
  const AssessmentResultItem({
    required this.id,
    required this.student,
    required this.className,
    required this.workStatus,
    required this.workStatusLabel,
    required this.savedAnswers,
    required this.correctedAnswers,
    required this.correct,
    required this.incorrect,
    required this.unanswered,
    required this.automaticCorrections,
    required this.manualCorrections,
    required this.totalScore,
    required this.scoreState,
    required this.status,
    required this.statusLabel,
    required this.statusTone,
    required this.appliedToGrades,
    this.score,
    this.finishedAt,
    this.appliedAt,
  });
  factory AssessmentResultItem.fromJson(Map<String, dynamic> json) =>
      AssessmentResultItem(
        id: _integer(json['id']),
        student: AssessmentParticipantStudent.fromJson(_map(json['siswa'])),
        className: json['kelas'] as String? ?? '-',
        workStatus: json['status_pengerjaan'] as String? ?? '-',
        workStatusLabel: json['label_status_pengerjaan'] as String? ?? '-',
        finishedAt: _date(json['waktu_selesai']),
        savedAnswers: _integer(json['jawaban_tersimpan']),
        correctedAnswers: _integer(json['jawaban_dikoreksi']),
        correct: _integer(json['benar']),
        incorrect: _integer(json['salah']),
        unanswered: _integer(json['belum_jawab']),
        automaticCorrections: _integer(json['perlu_koreksi_otomatis']),
        manualCorrections: _integer(json['perlu_koreksi_manual']),
        totalScore: _decimal(json['skor_total']),
        score: _nullableDecimal(json['nilai']),
        scoreState: json['status_nilai'] as String? ?? 'belum_tersedia',
        status: json['status'] as String? ?? '-',
        statusLabel: json['label_status'] as String? ?? '-',
        statusTone: json['nada_status'] as String? ?? 'netral',
        appliedToGrades: json['nilai_sudah_diterapkan'] as bool? ?? false,
        appliedAt: _date(json['nilai_diterapkan_pada']),
      );
  final int id;
  final AssessmentParticipantStudent student;
  final String className;
  final String workStatus;
  final String workStatusLabel;
  final DateTime? finishedAt;
  final int savedAnswers;
  final int correctedAnswers;
  final int correct;
  final int incorrect;
  final int unanswered;
  final int automaticCorrections;
  final int manualCorrections;
  final double totalScore;
  final double? score;
  final String scoreState;
  final String status;
  final String statusLabel;
  final String statusTone;
  final bool appliedToGrades;
  final DateTime? appliedAt;
}

class AssessmentFilterClass {
  const AssessmentFilterClass({required this.id, required this.label});
  factory AssessmentFilterClass.fromJson(Map<String, dynamic> json) =>
      AssessmentFilterClass(
        id: _integer(json['id']),
        label: json['label'] as String? ?? '-',
      );
  final int id;
  final String label;
}

class AssessmentFilterOption {
  const AssessmentFilterOption({required this.code, required this.label});
  factory AssessmentFilterOption.fromJson(Map<String, dynamic> json) =>
      AssessmentFilterOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
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
double _decimal(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? 0,
  _ => 0,
};
double? _nullableDecimal(Object? value) =>
    value == null ? null : _decimal(value);
DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
