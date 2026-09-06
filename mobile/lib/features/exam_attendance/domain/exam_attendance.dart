class ExamAttendanceDashboard {
  const ExamAttendanceDashboard({
    required this.summary,
    required this.todayRooms,
    required this.otherRooms,
    required this.canManageAll,
    this.generatedAt,
  });

  factory ExamAttendanceDashboard.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceDashboard(
        summary: ExamAttendanceDashboardSummary.fromJson(
          _map(json['ringkasan']),
        ),
        todayRooms: _list(json['ruang_hari_ini'], ExamAttendanceRoom.fromJson),
        otherRooms: _list(json['ruang_lain'], ExamAttendanceRoom.fromJson),
        canManageAll: json['dapat_kelola_semua'] as bool? ?? false,
        generatedAt: _date(json['dihasilkan_pada']),
      );

  final ExamAttendanceDashboardSummary summary;
  final List<ExamAttendanceRoom> todayRooms;
  final List<ExamAttendanceRoom> otherRooms;
  final bool canManageAll;
  final DateTime? generatedAt;
}

class ExamAttendanceDashboardSummary {
  const ExamAttendanceDashboardSummary({
    required this.rooms,
    required this.participants,
    required this.present,
  });

  factory ExamAttendanceDashboardSummary.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceDashboardSummary(
        rooms: _integer(json['jumlah_ruang']),
        participants: _integer(json['jumlah_peserta']),
        present: _integer(json['jumlah_hadir']),
      );

  final int rooms;
  final int participants;
  final int present;
}

class ExamAttendanceRoom {
  const ExamAttendanceRoom({
    required this.id,
    required this.examId,
    required this.code,
    required this.name,
    required this.activity,
    required this.subject,
    required this.status,
    required this.statusLabel,
    required this.participants,
    required this.present,
    required this.notRecorded,
    required this.absent,
    required this.presentPercentage,
    this.location,
    this.date,
    this.dateLabel,
    this.time,
    this.session,
    this.primarySupervisor,
    this.secondarySupervisor,
  });

  factory ExamAttendanceRoom.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceRoom(
        id: _integer(json['id']),
        examId: _integer(json['ujian_id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        location: json['lokasi'] as String?,
        activity: json['kegiatan'] as String? ?? 'Ujian CBT',
        subject: json['mata_pelajaran'] as String? ?? '-',
        date: json['tanggal'] as String?,
        dateLabel: json['tanggal_label'] as String?,
        time: json['waktu'] as String?,
        session: json['sesi'] as String?,
        status: json['status'] as String? ?? 'siap',
        statusLabel: json['label_status'] as String? ?? '-',
        primarySupervisor: json['pengawas_utama'] as String?,
        secondarySupervisor: json['pengawas_pendamping'] as String?,
        participants: _integer(json['jumlah_peserta']),
        present: _integer(json['jumlah_hadir']),
        notRecorded: _integer(json['jumlah_belum_absen']),
        absent: _integer(json['jumlah_tidak_hadir']),
        presentPercentage: _integer(json['persentase_hadir']),
      );

  final int id;
  final int examId;
  final String code;
  final String name;
  final String? location;
  final String activity;
  final String subject;
  final String? date;
  final String? dateLabel;
  final String? time;
  final String? session;
  final String status;
  final String statusLabel;
  final String? primarySupervisor;
  final String? secondarySupervisor;
  final int participants;
  final int present;
  final int notRecorded;
  final int absent;
  final int presentPercentage;
}

class ExamAttendanceDetail {
  const ExamAttendanceDetail({
    required this.room,
    required this.summary,
    required this.attendanceOptions,
    required this.recentAttendances,
    required this.participants,
    this.serverTime,
  });

  factory ExamAttendanceDetail.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceDetail(
        room: ExamAttendanceRoomDetail.fromJson(_map(json['ruang'])),
        summary: ExamAttendanceSummary.fromJson(_map(json['ringkasan'])),
        attendanceOptions: _list(
          json['status_kehadiran'],
          ExamAttendanceOption.fromJson,
        ),
        recentAttendances: _list(
          json['presensi_terbaru'],
          ExamAttendanceParticipant.fromJson,
        ),
        participants: _list(
          json['peserta'],
          ExamAttendanceParticipant.fromJson,
        ),
        serverTime: _date(json['waktu_server']),
      );

  final ExamAttendanceRoomDetail room;
  final ExamAttendanceSummary summary;
  final List<ExamAttendanceOption> attendanceOptions;
  final List<ExamAttendanceParticipant> recentAttendances;
  final List<ExamAttendanceParticipant> participants;
  final DateTime? serverTime;
}

class ExamAttendanceRoomDetail {
  const ExamAttendanceRoomDetail({
    required this.id,
    required this.examId,
    required this.code,
    required this.name,
    required this.activity,
    required this.subject,
    required this.status,
    required this.statusLabel,
    required this.myRole,
    required this.canChange,
    this.location,
    this.examType,
    this.date,
    this.dateLabel,
    this.time,
    this.session,
    this.primarySupervisor,
    this.secondarySupervisor,
  });

  factory ExamAttendanceRoomDetail.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceRoomDetail(
        id: _integer(json['id']),
        examId: _integer(json['ujian_id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        location: json['lokasi'] as String?,
        activity: json['kegiatan'] as String? ?? 'Ujian CBT',
        examType: json['jenis_ujian'] as String?,
        subject: json['mata_pelajaran'] as String? ?? '-',
        date: json['tanggal'] as String?,
        dateLabel: json['tanggal_label'] as String?,
        time: json['waktu'] as String?,
        session: json['sesi'] as String?,
        status: json['status'] as String? ?? 'siap',
        statusLabel: json['label_status'] as String? ?? '-',
        primarySupervisor: json['pengawas_utama'] as String?,
        secondarySupervisor: json['pengawas_pendamping'] as String?,
        myRole: json['peran_saya'] as String? ?? '-',
        canChange: json['dapat_mengubah'] as bool? ?? false,
      );

  final int id;
  final int examId;
  final String code;
  final String name;
  final String? location;
  final String activity;
  final String? examType;
  final String subject;
  final String? date;
  final String? dateLabel;
  final String? time;
  final String? session;
  final String status;
  final String statusLabel;
  final String? primarySupervisor;
  final String? secondarySupervisor;
  final String myRole;
  final bool canChange;
}

class ExamAttendanceSummary {
  const ExamAttendanceSummary({
    required this.participants,
    required this.present,
    required this.notRecorded,
    required this.absent,
  });

  factory ExamAttendanceSummary.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceSummary(
        participants: _integer(json['peserta']),
        present: _integer(json['hadir']),
        notRecorded: _integer(json['belum_absen']),
        absent: _integer(json['tidak_hadir']),
      );

  final int participants;
  final int present;
  final int notRecorded;
  final int absent;
}

class ExamAttendanceOption {
  const ExamAttendanceOption({required this.code, required this.label});

  factory ExamAttendanceOption.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String code;
  final String label;
}

class ExamAttendanceParticipant {
  const ExamAttendanceParticipant({
    required this.id,
    required this.name,
    required this.className,
    required this.status,
    required this.statusLabel,
    this.nisn,
    this.photoUrl,
    this.participantNumber,
    this.deskNumber,
    this.scanTime,
    this.note,
  });

  factory ExamAttendanceParticipant.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceParticipant(
        id: _integer(json['id']),
        name: json['nama_lengkap'] as String? ?? 'Siswa',
        nisn: json['nisn'] as String?,
        className: json['kelas'] as String? ?? '-',
        photoUrl: json['foto_url'] as String?,
        participantNumber: json['nomor_peserta'] as String?,
        deskNumber: _nullableInteger(json['nomor_meja']),
        status: json['status'] as String? ?? 'belum_absen',
        statusLabel: json['label_status'] as String? ?? 'Belum hadir',
        scanTime: json['waktu_scan'] as String?,
        note: json['catatan'] as String?,
      );

  final int id;
  final String name;
  final String? nisn;
  final String className;
  final String? photoUrl;
  final String? participantNumber;
  final int? deskNumber;
  final String status;
  final String statusLabel;
  final String? scanTime;
  final String? note;
}

class ExamAttendanceStudent {
  const ExamAttendanceStudent({
    required this.name,
    this.nisn,
    this.photoUrl,
    this.className,
    this.deskNumber,
  });

  factory ExamAttendanceStudent.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceStudent(
        name: json['nama_lengkap'] as String? ?? 'Siswa',
        nisn: json['nisn'] as String?,
        photoUrl: json['foto_url'] as String?,
        className: json['kelas'] as String?,
        deskNumber: _nullableInteger(json['nomor_meja']),
      );

  final String name;
  final String? nisn;
  final String? photoUrl;
  final String? className;
  final int? deskNumber;
}

class ExamAttendanceScanResult {
  const ExamAttendanceScanResult({
    required this.success,
    required this.isNew,
    required this.status,
    required this.message,
    required this.serverTime,
    this.participant,
    this.student,
    this.summary,
    this.expectedRoom,
  });

  factory ExamAttendanceScanResult.fromJson(Map<String, dynamic> json) =>
      ExamAttendanceScanResult(
        success: json['berhasil'] as bool? ?? false,
        isNew: json['baru'] as bool? ?? false,
        status: json['status'] as String? ?? 'gagal',
        message: json['pesan'] as String? ?? 'Hasil scan belum tersedia.',
        serverTime: json['waktu_server'] as String? ?? '',
        participant: _nullable(
          json['peserta'],
          ExamAttendanceParticipant.fromJson,
        ),
        student: _nullable(json['siswa'], ExamAttendanceStudent.fromJson),
        summary: _nullable(json['ringkasan'], ExamAttendanceSummary.fromJson),
        expectedRoom: json['ruang_seharusnya'] as String?,
      );

  final bool success;
  final bool isNew;
  final String status;
  final String message;
  final String serverTime;
  final ExamAttendanceParticipant? participant;
  final ExamAttendanceStudent? student;
  final ExamAttendanceSummary? summary;
  final String? expectedRoom;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

T? _nullable<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is Map ? convert(Map<String, dynamic>.from(value)) : null;

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List ? value.map((item) => convert(_map(item))).toList() : <T>[];

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
