class StudentExamSession {
  const StudentExamSession({
    required this.mode,
    required this.serverTime,
    required this.participant,
    required this.exam,
    required this.progress,
    required this.questions,
    required this.requiresToken,
    required this.canStart,
    required this.remainingSeconds,
    required this.security,
    this.endsAt,
    this.result,
  });

  factory StudentExamSession.fromJson(Map<String, dynamic> json) =>
      StudentExamSession(
        mode: json['mode'] as String? ?? 'konfirmasi',
        serverTime: _date(json['waktu_server']),
        participant: StudentExamParticipant.fromJson(_map(json['peserta'])),
        exam: StudentExamInfo.fromJson(_map(json['ujian'])),
        progress: StudentExamProgress.fromJson(_map(json['kemajuan'])),
        questions: _list(json['soal'], StudentExamQuestion.fromJson),
        requiresToken: json['memerlukan_token'] as bool? ?? false,
        canStart: json['dapat_dimulai'] as bool? ?? false,
        remainingSeconds: _integer(json['sisa_detik']),
        security: StudentExamSecurity.fromJson(_map(json['keamanan'])),
        endsAt: _nullableDate(json['berakhir_pada']),
        result: json['hasil'] is Map
            ? StudentExamResult.fromJson(_map(json['hasil']))
            : null,
      );

  final String mode;
  final DateTime serverTime;
  final StudentExamParticipant participant;
  final StudentExamInfo exam;
  final StudentExamProgress progress;
  final List<StudentExamQuestion> questions;
  final bool requiresToken;
  final bool canStart;
  final int remainingSeconds;
  final StudentExamSecurity security;
  final DateTime? endsAt;
  final StudentExamResult? result;

  bool get isConfirmation => mode == 'konfirmasi';
  bool get isRunning => mode == 'pengerjaan';
  bool get isCompleted => mode == 'selesai';
  bool get isLocked => mode == 'ditahan';

  StudentExamSession copyWith({
    String? mode,
    List<StudentExamQuestion>? questions,
    StudentExamProgress? progress,
    int? remainingSeconds,
    StudentExamSecurity? security,
  }) => StudentExamSession(
    mode: mode ?? this.mode,
    serverTime: serverTime,
    participant: participant,
    exam: exam,
    progress: progress ?? this.progress,
    questions: questions ?? this.questions,
    requiresToken: requiresToken,
    canStart: canStart,
    remainingSeconds: remainingSeconds ?? this.remainingSeconds,
    security: security ?? this.security,
    endsAt: endsAt,
    result: result,
  );
}

class StudentExamParticipant {
  const StudentExamParticipant({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.name,
    required this.schoolClass,
    this.participantNumber,
    this.nisn,
    this.room,
    this.seatNumber,
    this.session,
    this.startedAt,
    this.finishedAt,
  });

  factory StudentExamParticipant.fromJson(Map<String, dynamic> json) =>
      StudentExamParticipant(
        id: _integer(json['id']),
        participantNumber: json['nomor_peserta'] as String?,
        status: json['status'] as String? ?? 'aktif',
        statusLabel: json['label_status'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        nisn: json['nisn'] as String?,
        schoolClass: json['kelas'] as String? ?? '-',
        room: json['ruang'] as String?,
        seatNumber: _nullableInteger(json['nomor_meja']),
        session: json['sesi'] as String?,
        startedAt: _nullableDate(json['waktu_mulai']),
        finishedAt: _nullableDate(json['waktu_selesai']),
      );

  final int id;
  final String? participantNumber;
  final String status;
  final String statusLabel;
  final String name;
  final String? nisn;
  final String schoolClass;
  final String? room;
  final int? seatNumber;
  final String? session;
  final DateTime? startedAt;
  final DateTime? finishedAt;
}

class StudentExamInfo {
  const StudentExamInfo({
    required this.id,
    required this.name,
    required this.code,
    required this.subject,
    required this.durationMinutes,
    required this.questionCount,
    required this.showResult,
    required this.singleDevice,
    required this.flow,
    this.type,
    this.academicYear,
    this.semester,
    this.instructions,
  });

  factory StudentExamInfo.fromJson(Map<String, dynamic> json) =>
      StudentExamInfo(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String? ?? '-',
        type: json['jenis'] as String?,
        subject: json['mata_pelajaran'] as String? ?? '-',
        academicYear: json['tahun_pelajaran'] as String?,
        semester: json['semester'] as String?,
        durationMinutes: _integer(json['durasi_menit']),
        questionCount: _integer(json['jumlah_soal']),
        instructions: json['petunjuk'] as String?,
        showResult: json['tampilkan_hasil'] as bool? ?? false,
        singleDevice: json['batasi_satu_perangkat'] as bool? ?? false,
        flow: json['alur'] as String? ?? 'kelas',
      );

  final int id;
  final String name;
  final String code;
  final String? type;
  final String subject;
  final String? academicYear;
  final String? semester;
  final int durationMinutes;
  final int questionCount;
  final String? instructions;
  final bool showResult;
  final bool singleDevice;
  final String flow;
}

class StudentExamSecurity {
  const StudentExamSecurity({
    required this.enabled,
    required this.trackAppSwitch,
    required this.secureScreen,
    required this.requireFullscreen,
    required this.graceSeconds,
    required this.incidentLimit,
    required this.action,
    required this.incidentCount,
    required this.remainingIncidents,
    required this.totalAwaySeconds,
    required this.locked,
    this.lockedAt,
    this.lastHeartbeatAt,
  });

  factory StudentExamSecurity.fromJson(Map<String, dynamic> json) =>
      StudentExamSecurity(
        enabled: json['aktif'] as bool? ?? false,
        trackAppSwitch: json['catat_pindah_aplikasi'] as bool? ?? false,
        secureScreen: json['layar_aman'] as bool? ?? false,
        requireFullscreen: json['wajib_fullscreen'] as bool? ?? false,
        graceSeconds: _positiveInteger(json['toleransi_detik'], 3),
        incidentLimit: _positiveInteger(json['batas_kejadian'], 3),
        action: json['tindakan'] as String? ?? 'catat',
        incidentCount: _integer(json['jumlah_kejadian']),
        remainingIncidents: _integer(json['sisa_kejadian']),
        totalAwaySeconds: _integer(json['durasi_total_detik']),
        locked: json['ditahan'] as bool? ?? false,
        lockedAt: _nullableDate(json['ditahan_pada']),
        lastHeartbeatAt: _nullableDate(json['heartbeat_terakhir_pada']),
      );

  final bool enabled;
  final bool trackAppSwitch;
  final bool secureScreen;
  final bool requireFullscreen;
  final int graceSeconds;
  final int incidentLimit;
  final String action;
  final int incidentCount;
  final int remainingIncidents;
  final int totalAwaySeconds;
  final bool locked;
  final DateTime? lockedAt;
  final DateTime? lastHeartbeatAt;
}

class StudentExamSecurityUpdate {
  const StudentExamSecurityUpdate({
    required this.mode,
    required this.security,
    required this.counted,
    required this.incidentDurationSeconds,
    this.message,
  });

  factory StudentExamSecurityUpdate.fromJson(Map<String, dynamic> json) =>
      StudentExamSecurityUpdate(
        mode: json['mode'] as String? ?? 'pengerjaan',
        security: StudentExamSecurity.fromJson(_map(json['keamanan'])),
        counted: json['kejadian_dihitung'] as bool? ?? false,
        incidentDurationSeconds: _integer(json['durasi_kejadian_detik']),
        message: json['pesan'] as String?,
      );

  final String mode;
  final StudentExamSecurity security;
  final bool counted;
  final int incidentDurationSeconds;
  final String? message;
}

class StudentExamProgress {
  const StudentExamProgress({
    required this.questionCount,
    required this.answered,
    required this.unanswered,
    required this.doubtful,
  });

  factory StudentExamProgress.fromJson(Map<String, dynamic> json) =>
      StudentExamProgress(
        questionCount: _integer(json['jumlah_soal']),
        answered: _integer(json['terjawab']),
        unanswered: _integer(json['belum_dijawab']),
        doubtful: _integer(json['ragu']),
      );

  final int questionCount;
  final int answered;
  final int unanswered;
  final int doubtful;
}

class StudentExamResult {
  const StudentExamResult({
    required this.visible,
    required this.awaitingCorrection,
    this.score,
    this.minimumScore,
    this.passed,
  });

  factory StudentExamResult.fromJson(Map<String, dynamic> json) =>
      StudentExamResult(
        visible: json['ditampilkan'] as bool? ?? false,
        awaitingCorrection: json['menunggu_koreksi'] as bool? ?? false,
        score: _nullableDecimal(json['nilai']),
        minimumScore: _nullableDecimal(json['kkm']),
        passed: json['tuntas'] as bool?,
      );

  final bool visible;
  final bool awaitingCorrection;
  final double? score;
  final double? minimumScore;
  final bool? passed;
}

class StudentExamQuestion {
  const StudentExamQuestion({
    required this.id,
    required this.number,
    required this.type,
    required this.typeLabel,
    required this.question,
    required this.media,
    required this.options,
    required this.statements,
    required this.pairs,
    required this.answer,
    required this.doubtful,
    this.stimulus,
  });

  factory StudentExamQuestion.fromJson(Map<String, dynamic> json) =>
      StudentExamQuestion(
        id: _integer(json['id']),
        number: _integer(json['nomor']),
        type: json['jenis'] as String? ?? 'uraian',
        typeLabel: json['label_jenis'] as String? ?? 'Soal',
        stimulus: json['stimulus'] as String?,
        question: json['pertanyaan'] as String? ?? '-',
        media: StudentExamMedia.fromJson(_map(json['media'])),
        options: _list(json['pilihan'], StudentExamOption.fromJson),
        statements: _list(json['pernyataan'], StudentExamStatement.fromJson),
        pairs: _list(json['pasangan'], StudentExamPair.fromJson),
        answer: _stringMap(json['jawaban']),
        doubtful: json['ragu'] as bool? ?? false,
      );

  final int id;
  final int number;
  final String type;
  final String typeLabel;
  final String? stimulus;
  final String question;
  final StudentExamMedia media;
  final List<StudentExamOption> options;
  final List<StudentExamStatement> statements;
  final List<StudentExamPair> pairs;
  final Map<String, String> answer;
  final bool doubtful;

  bool get isAnswered => answer.values.any((value) => value.trim().isNotEmpty);

  Object? get answerPayload {
    final values = answer.values
        .map((value) => value.trim())
        .where((value) => value.isNotEmpty)
        .toList(growable: false);
    if (type == 'benar_salah' || type == 'menjodohkan') {
      final mapped = <String, String>{};
      for (final entry in answer.entries) {
        final value = entry.value.trim();
        if (value.isNotEmpty) mapped[entry.key] = value;
      }
      return mapped.isEmpty ? null : mapped;
    }
    return values.isEmpty ? null : values;
  }

  StudentExamQuestion copyWith({Map<String, String>? answer, bool? doubtful}) =>
      StudentExamQuestion(
        id: id,
        number: number,
        type: type,
        typeLabel: typeLabel,
        stimulus: stimulus,
        question: question,
        media: media,
        options: options,
        statements: statements,
        pairs: pairs,
        answer: answer ?? this.answer,
        doubtful: doubtful ?? this.doubtful,
      );
}

class StudentExamOption {
  const StudentExamOption({required this.code, required this.text});
  factory StudentExamOption.fromJson(Map<String, dynamic> json) =>
      StudentExamOption(
        code: json['kode'] as String? ?? '-',
        text: json['teks'] as String? ?? '-',
      );
  final String code;
  final String text;
}

class StudentExamStatement {
  const StudentExamStatement({required this.number, required this.text});
  factory StudentExamStatement.fromJson(Map<String, dynamic> json) =>
      StudentExamStatement(
        number: json['nomor']?.toString() ?? '-',
        text: json['teks'] as String? ?? '-',
      );
  final String number;
  final String text;
}

class StudentExamPair {
  const StudentExamPair({required this.number, required this.left});
  factory StudentExamPair.fromJson(Map<String, dynamic> json) =>
      StudentExamPair(
        number: json['nomor']?.toString() ?? '-',
        left: json['kiri'] as String? ?? '-',
      );
  final String number;
  final String left;
}

class StudentExamMedia {
  const StudentExamMedia({this.image, this.table, this.formula});
  factory StudentExamMedia.fromJson(Map<String, dynamic> json) =>
      StudentExamMedia(
        image: json['gambar'] is Map
            ? StudentExamImage.fromJson(_map(json['gambar']))
            : null,
        table: json['tabel'] is Map
            ? StudentExamTable.fromJson(_map(json['tabel']))
            : null,
        formula: json['rumus'] is Map
            ? StudentExamFormula.fromJson(_map(json['rumus']))
            : null,
      );
  final StudentExamImage? image;
  final StudentExamTable? table;
  final StudentExamFormula? formula;
}

class StudentExamImage {
  const StudentExamImage({required this.url, this.alt, this.caption});
  factory StudentExamImage.fromJson(Map<String, dynamic> json) =>
      StudentExamImage(
        url: json['url'] as String? ?? '',
        alt: json['alt'] as String?,
        caption: json['keterangan'] as String?,
      );
  final String url;
  final String? alt;
  final String? caption;
}

class StudentExamTable {
  const StudentExamTable({required this.rows, this.title});
  factory StudentExamTable.fromJson(Map<String, dynamic> json) =>
      StudentExamTable(
        title: json['judul'] as String?,
        rows: (json['baris'] as List? ?? const [])
            .whereType<List>()
            .map(
              (row) => row
                  .map((cell) => cell?.toString() ?? '')
                  .toList(growable: false),
            )
            .toList(growable: false),
      );
  final String? title;
  final List<List<String>> rows;
}

class StudentExamFormula {
  const StudentExamFormula({required this.latex, this.caption});
  factory StudentExamFormula.fromJson(Map<String, dynamic> json) =>
      StudentExamFormula(
        latex: json['latex'] as String? ?? '',
        caption: json['keterangan'] as String?,
      );
  final String latex;
  final String? caption;
}

class StudentExamSaveResult {
  const StudentExamSaveResult({
    required this.completed,
    required this.remainingSeconds,
    this.session,
  });

  factory StudentExamSaveResult.fromJson(Map<String, dynamic> json) {
    final completed = json['mode'] == 'selesai';
    return StudentExamSaveResult(
      completed: completed,
      remainingSeconds: _integer(json['sisa_detik']),
      session: completed ? StudentExamSession.fromJson(json) : null,
    );
  }

  final bool completed;
  final int remainingSeconds;
  final StudentExamSession? session;
}

Map<String, dynamic> _map(Object? value) => value is Map
    ? value.map((key, value) => MapEntry(key.toString(), value))
    : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value.whereType<Map>().map((item) => convert(_map(item))).toList()
    : <T>[];

Map<String, String> _stringMap(Object? value) => value is Map
    ? value.map(
        (key, value) => MapEntry(key.toString(), value?.toString() ?? ''),
      )
    : <String, String>{};

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int _positiveInteger(Object? value, int fallback) {
  final parsed = _integer(value);
  return parsed > 0 ? parsed : fallback;
}

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);

double? _nullableDecimal(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text),
  _ => null,
};

DateTime _date(Object? value) =>
    _nullableDate(value) ?? DateTime.fromMillisecondsSinceEpoch(0);

DateTime? _nullableDate(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
