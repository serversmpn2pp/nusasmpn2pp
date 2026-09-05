enum CbtCenterFocus { management, supervisor, student }

class CbtCenterData {
  const CbtCenterData({
    required this.access,
    this.management,
    this.supervisor,
    this.student,
  });

  factory CbtCenterData.fromJson(Map<String, dynamic> json) => CbtCenterData(
    access: CbtAccess.fromJson(_map(json['akses'])),
    management: _nullable(json['pengelolaan'], CbtManagement.fromJson),
    supervisor: _nullable(json['pengawas'], CbtSupervisor.fromJson),
    student: _nullable(json['siswa'], CbtStudent.fromJson),
  );

  final CbtAccess access;
  final CbtManagement? management;
  final CbtSupervisor? supervisor;
  final CbtStudent? student;
}

class CbtAccess {
  const CbtAccess({
    required this.canManage,
    required this.hasSupervisorTasks,
    required this.isStudent,
  });

  factory CbtAccess.fromJson(Map<String, dynamic> json) => CbtAccess(
    canManage: json['dapat_mengelola'] as bool? ?? false,
    hasSupervisorTasks: json['memiliki_tugas_pengawas'] as bool? ?? false,
    isStudent: json['akun_siswa'] as bool? ?? false,
  );

  final bool canManage;
  final bool hasSupervisorTasks;
  final bool isStudent;
}

class CbtManagement {
  const CbtManagement({
    required this.summary,
    required this.flows,
    required this.tools,
  });

  factory CbtManagement.fromJson(Map<String, dynamic> json) => CbtManagement(
    summary: CbtManagementSummary.fromJson(_map(json['ringkasan'])),
    flows: _list(json['alur'], CbtFlow.fromJson),
    tools: _list(json['alat'], CbtTool.fromJson),
  );

  final CbtManagementSummary summary;
  final List<CbtFlow> flows;
  final List<CbtTool> tools;
}

class CbtManagementSummary {
  const CbtManagementSummary({
    required this.readyQuestions,
    required this.centralActivities,
    required this.classAssessments,
    required this.scheduledPackages,
  });

  factory CbtManagementSummary.fromJson(Map<String, dynamic> json) =>
      CbtManagementSummary(
        readyQuestions: _integer(json['soal_siap']),
        centralActivities: _integer(json['kegiatan_terpusat']),
        classAssessments: _integer(json['asesmen_kelas']),
        scheduledPackages: _integer(json['paket_terjadwal']),
      );

  final int readyQuestions;
  final int centralActivities;
  final int classAssessments;
  final int scheduledPackages;
}

class CbtFlow {
  const CbtFlow({
    required this.code,
    required this.title,
    required this.description,
    required this.color,
  });

  factory CbtFlow.fromJson(Map<String, dynamic> json) => CbtFlow(
    code: json['kode'] as String? ?? '',
    title: json['judul'] as String? ?? '-',
    description: json['deskripsi'] as String? ?? '',
    color: json['warna'] as String? ?? 'biru',
  );

  final String code;
  final String title;
  final String description;
  final String color;
}

class CbtTool {
  const CbtTool({
    required this.code,
    required this.label,
    required this.status,
    this.route,
  });

  factory CbtTool.fromJson(Map<String, dynamic> json) => CbtTool(
    code: json['kode'] as String? ?? '',
    label: json['label'] as String? ?? '-',
    status: json['status'] as String? ?? 'fondasi',
    route: json['rute'] as String?,
  );

  final String code;
  final String label;
  final String status;
  final String? route;
}

class CbtSupervisor {
  const CbtSupervisor({
    required this.summary,
    required this.tasks,
    required this.nativeOperations,
  });

  factory CbtSupervisor.fromJson(Map<String, dynamic> json) => CbtSupervisor(
    summary: CbtSupervisorSummary.fromJson(_map(json['ringkasan'])),
    tasks: _list(json['items'], CbtSupervisorTask.fromJson),
    nativeOperations: json['operasional_native'] as bool? ?? false,
  );

  final CbtSupervisorSummary summary;
  final List<CbtSupervisorTask> tasks;
  final bool nativeOperations;
}

class CbtSupervisorSummary {
  const CbtSupervisorSummary({
    required this.total,
    required this.today,
    required this.needsEvidence,
  });

  factory CbtSupervisorSummary.fromJson(Map<String, dynamic> json) =>
      CbtSupervisorSummary(
        total: _integer(json['jumlah']),
        today: _integer(json['hari_ini']),
        needsEvidence: _integer(json['perlu_bukti']),
      );

  final int total;
  final int today;
  final int needsEvidence;
}

class CbtSupervisorTask {
  const CbtSupervisorTask({
    required this.id,
    required this.canOpen,
    required this.activity,
    required this.subject,
    required this.room,
    required this.role,
    required this.evidenceLabel,
    required this.studentCount,
    this.examType,
    this.date,
    this.time,
    this.evidenceStatus,
    this.roomId,
    this.status,
    this.statusLabel,
  });

  factory CbtSupervisorTask.fromJson(Map<String, dynamic> json) =>
      CbtSupervisorTask(
        id: _integer(json['id']),
        roomId: _nullableInteger(json['ruang_id']),
        canOpen: json['dapat_dibuka'] as bool? ?? false,
        activity: json['kegiatan'] as String? ?? 'Ujian Terpusat',
        examType: json['jenis_ujian'] as String?,
        subject: json['mata_pelajaran'] as String? ?? '-',
        date: json['tanggal'] as String?,
        time: json['waktu'] as String?,
        room: json['ruang'] as String? ?? '-',
        role: json['peran'] as String? ?? '-',
        evidenceStatus: json['status_bukti'] as String?,
        evidenceLabel: json['label_status_bukti'] as String? ?? '-',
        studentCount: _integer(json['jumlah_peserta']),
        status: json['status'] as String?,
        statusLabel: json['label_status'] as String?,
      );

  final int id;
  final int? roomId;
  final bool canOpen;
  final String activity;
  final String? examType;
  final String subject;
  final String? date;
  final String? time;
  final String room;
  final String role;
  final String? evidenceStatus;
  final String evidenceLabel;
  final int studentCount;
  final String? status;
  final String? statusLabel;
}

class CbtStudent {
  const CbtStudent({
    required this.summary,
    required this.exams,
    required this.nativeExamRunner,
  });

  factory CbtStudent.fromJson(Map<String, dynamic> json) => CbtStudent(
    summary: CbtStudentSummary.fromJson(_map(json['ringkasan'])),
    exams: _list(json['items'], CbtStudentExam.fromJson),
    nativeExamRunner: json['pengerjaan_native'] as bool? ?? false,
  );

  final CbtStudentSummary summary;
  final List<CbtStudentExam> exams;
  final bool nativeExamRunner;
}

class CbtStudentSummary {
  const CbtStudentSummary({
    required this.active,
    required this.upcoming,
    required this.completed,
    required this.total,
  });

  factory CbtStudentSummary.fromJson(Map<String, dynamic> json) =>
      CbtStudentSummary(
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

class CbtStudentExam {
  const CbtStudentExam({
    required this.id,
    required this.examId,
    required this.name,
    required this.subject,
    required this.group,
    required this.statusLabel,
    required this.statusTone,
    required this.durationMinutes,
    required this.requiresToken,
    required this.canOpen,
    this.code,
    this.examType,
    this.startAt,
    this.endAt,
    this.date,
    this.time,
    this.participantNumber,
  });

  factory CbtStudentExam.fromJson(Map<String, dynamic> json) => CbtStudentExam(
    id: _integer(json['id']),
    examId: _integer(json['ujian_id']),
    name: json['nama'] as String? ?? '-',
    code: json['kode'] as String?,
    examType: json['jenis_ujian'] as String?,
    subject: json['mata_pelajaran'] as String? ?? '-',
    group: json['kelompok'] as String? ?? 'akan_datang',
    statusLabel: json['label_status'] as String? ?? '-',
    statusTone: json['nada_status'] as String? ?? 'menunggu',
    startAt: _date(json['waktu_mulai']),
    endAt: _date(json['waktu_selesai']),
    date: json['tanggal'] as String?,
    time: json['waktu'] as String?,
    durationMinutes: _integer(json['durasi_menit']),
    requiresToken: json['memerlukan_token'] as bool? ?? false,
    canOpen: json['dapat_dibuka'] as bool? ?? true,
    participantNumber: json['nomor_peserta'] as String?,
  );

  final int id;
  final int examId;
  final String name;
  final String? code;
  final String? examType;
  final String subject;
  final String group;
  final String statusLabel;
  final String statusTone;
  final DateTime? startAt;
  final DateTime? endAt;
  final String? date;
  final String? time;
  final int durationMinutes;
  final bool requiresToken;
  final bool canOpen;
  final String? participantNumber;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value.whereType<Map<String, dynamic>>().map(convert).toList()
    : <T>[];

T? _nullable<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is Map<String, dynamic> ? convert(value) : null;

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
