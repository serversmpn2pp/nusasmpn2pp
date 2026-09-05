import 'dart:typed_data';

class ExamSupervisionDetail {
  const ExamSupervisionDetail({
    required this.room,
    required this.summary,
    required this.attendanceOptions,
    required this.participants,
    required this.evidence,
    required this.capabilities,
    this.generatedAt,
  });

  factory ExamSupervisionDetail.fromJson(Map<String, dynamic> json) =>
      ExamSupervisionDetail(
        room: SupervisionRoom.fromJson(_map(json['ruang'])),
        summary: SupervisionSummary.fromJson(_map(json['ringkasan'])),
        attendanceOptions: _list(
          json['status_kehadiran'],
          AttendanceOption.fromJson,
        ),
        participants: _list(json['peserta'], SupervisionParticipant.fromJson),
        evidence: _list(json['bukti'], SupervisionEvidence.fromJson),
        capabilities: SupervisionCapabilities.fromJson(_map(json['kemampuan'])),
        generatedAt: _date(json['dihasilkan_pada']),
      );

  final SupervisionRoom room;
  final SupervisionSummary summary;
  final List<AttendanceOption> attendanceOptions;
  final List<SupervisionParticipant> participants;
  final List<SupervisionEvidence> evidence;
  final SupervisionCapabilities capabilities;
  final DateTime? generatedAt;
}

class SupervisionRoom {
  const SupervisionRoom({
    required this.id,
    required this.code,
    required this.name,
    required this.status,
    required this.statusLabel,
    required this.locked,
    required this.activity,
    required this.subject,
    required this.level,
    required this.myRole,
    required this.evidenceStatus,
    required this.evidenceStatusLabel,
    this.location,
    this.examType,
    this.date,
    this.time,
    this.actualStart,
    this.actualEnd,
    this.primarySupervisor,
    this.secondarySupervisor,
    this.reviewNote,
    this.minutes,
    this.obstacles,
    this.followUp,
    this.notes,
  });

  factory SupervisionRoom.fromJson(Map<String, dynamic> json) =>
      SupervisionRoom(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        location: json['lokasi'] as String?,
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        locked: json['terkunci'] as bool? ?? false,
        activity: json['kegiatan'] as String? ?? 'Ujian Terpusat',
        examType: json['jenis_ujian'] as String?,
        subject: json['mata_pelajaran'] as String? ?? '-',
        level: _integer(json['tingkat']),
        date: json['tanggal'] as String?,
        time: json['waktu'] as String?,
        actualStart: _date(json['waktu_mulai_aktual']),
        actualEnd: _date(json['waktu_selesai_aktual']),
        primarySupervisor: json['pengawas_utama'] as String?,
        secondarySupervisor: json['pengawas_pendamping'] as String?,
        myRole: json['peran_saya'] as String? ?? '-',
        evidenceStatus: json['status_bukti'] as String? ?? 'belum_diunggah',
        evidenceStatusLabel:
            json['label_status_bukti'] as String? ?? 'Belum diunggah',
        reviewNote: json['catatan_pemeriksaan_bukti'] as String?,
        minutes: json['berita_acara'] as String?,
        obstacles: json['hambatan'] as String?,
        followUp: json['tindak_lanjut'] as String?,
        notes: json['catatan'] as String?,
      );

  final int id;
  final String code;
  final String name;
  final String? location;
  final String status;
  final String statusLabel;
  final bool locked;
  final String activity;
  final String? examType;
  final String subject;
  final int level;
  final String? date;
  final String? time;
  final DateTime? actualStart;
  final DateTime? actualEnd;
  final String? primarySupervisor;
  final String? secondarySupervisor;
  final String myRole;
  final String evidenceStatus;
  final String evidenceStatusLabel;
  final String? reviewNote;
  final String? minutes;
  final String? obstacles;
  final String? followUp;
  final String? notes;
}

class SupervisionSummary {
  const SupervisionSummary({
    required this.total,
    required this.present,
    required this.notPresent,
    required this.absent,
    required this.presentNotStarted,
    required this.working,
    required this.finished,
    required this.blocked,
    required this.appSwitches,
  });

  factory SupervisionSummary.fromJson(Map<String, dynamic> json) =>
      SupervisionSummary(
        total: _integer(json['total']),
        present: _integer(json['hadir']),
        notPresent: _integer(json['belum_hadir']),
        absent: _integer(json['tidak_hadir']),
        presentNotStarted: _integer(json['hadir_belum_mulai']),
        working: _integer(json['sedang_mengerjakan']),
        finished: _integer(json['selesai']),
        blocked: _integer(json['terblokir']),
        appSwitches: _integer(json['jumlah_pindah_aplikasi']),
      );

  final int total;
  final int present;
  final int notPresent;
  final int absent;
  final int presentNotStarted;
  final int working;
  final int finished;
  final int blocked;
  final int appSwitches;
}

class AttendanceOption {
  const AttendanceOption({required this.code, required this.label});

  factory AttendanceOption.fromJson(Map<String, dynamic> json) =>
      AttendanceOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String code;
  final String label;
}

class SupervisionParticipant {
  const SupervisionParticipant({
    required this.id,
    required this.name,
    required this.className,
    required this.status,
    required this.statusLabel,
    required this.attendance,
    required this.attendanceLabel,
    required this.savedAnswers,
    required this.deviceBound,
    required this.appSwitches,
    required this.awaySeconds,
    this.nisn,
    this.participantNumber,
    this.deskNumber,
    this.attendanceNote,
    this.attendanceAt,
    this.startAt,
    this.endAt,
    this.device,
    this.lastHeartbeat,
    this.blockedAt,
  });

  factory SupervisionParticipant.fromJson(Map<String, dynamic> json) =>
      SupervisionParticipant(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? 'Siswa',
        nisn: json['nisn'] as String?,
        className: json['kelas'] as String? ?? '-',
        participantNumber: json['nomor_peserta'] as String?,
        deskNumber: _nullableInteger(json['nomor_meja']),
        status: json['status'] as String? ?? 'belum_hadir',
        statusLabel: json['label_status'] as String? ?? '-',
        attendance: json['kehadiran'] as String? ?? 'belum_absen',
        attendanceLabel: json['label_kehadiran'] as String? ?? '-',
        attendanceNote: json['catatan_kehadiran'] as String?,
        attendanceAt: _date(json['waktu_absen']),
        startAt: _date(json['waktu_mulai']),
        endAt: _date(json['waktu_selesai']),
        savedAnswers: _integer(json['jawaban_tersimpan']),
        deviceBound: json['perangkat_terikat'] as bool? ?? false,
        device: json['perangkat'] as String?,
        appSwitches: _integer(json['jumlah_pindah_aplikasi']),
        awaySeconds: _integer(json['durasi_di_luar_aplikasi_detik']),
        lastHeartbeat: _date(json['heartbeat_terakhir_pada']),
        blockedAt: _date(json['ditahan_mode_aman_pada']),
      );

  final int id;
  final String name;
  final String? nisn;
  final String className;
  final String? participantNumber;
  final int? deskNumber;
  final String status;
  final String statusLabel;
  final String attendance;
  final String attendanceLabel;
  final String? attendanceNote;
  final DateTime? attendanceAt;
  final DateTime? startAt;
  final DateTime? endAt;
  final int savedAnswers;
  final bool deviceBound;
  final String? device;
  final int appSwitches;
  final int awaySeconds;
  final DateTime? lastHeartbeat;
  final DateTime? blockedAt;
}

class SupervisionEvidence {
  const SupervisionEvidence({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.fileName,
    required this.size,
    required this.sizeLabel,
    this.mimeType,
    this.uploadedAt,
    this.uploadedBy,
  });

  factory SupervisionEvidence.fromJson(Map<String, dynamic> json) =>
      SupervisionEvidence(
        id: _integer(json['id']),
        type: json['jenis'] as String? ?? '',
        typeLabel: json['label_jenis'] as String? ?? '-',
        fileName: json['nama_file'] as String? ?? '-',
        mimeType: json['tipe_file'] as String?,
        size: _integer(json['ukuran']),
        sizeLabel: json['ukuran_ringkas'] as String? ?? '-',
        uploadedAt: _date(json['diunggah_pada']),
        uploadedBy: json['diunggah_oleh'] as String?,
      );

  final int id;
  final String type;
  final String typeLabel;
  final String fileName;
  final String? mimeType;
  final int size;
  final String sizeLabel;
  final DateTime? uploadedAt;
  final String? uploadedBy;
}

class SupervisionCapabilities {
  const SupervisionCapabilities({
    required this.manageRoom,
    required this.changeAttendance,
    required this.resetDevice,
    required this.unlockSafeMode,
    required this.changeEvidence,
    required this.submitEvidence,
  });

  factory SupervisionCapabilities.fromJson(Map<String, dynamic> json) =>
      SupervisionCapabilities(
        manageRoom: json['mengelola_ruang'] as bool? ?? false,
        changeAttendance: json['mengubah_kehadiran'] as bool? ?? false,
        resetDevice: json['mereset_perangkat'] as bool? ?? false,
        unlockSafeMode: json['membuka_mode_aman'] as bool? ?? false,
        changeEvidence: json['mengubah_bukti'] as bool? ?? false,
        submitEvidence: json['mengirim_bukti'] as bool? ?? false,
      );

  final bool manageRoom;
  final bool changeAttendance;
  final bool resetDevice;
  final bool unlockSafeMode;
  final bool changeEvidence;
  final bool submitEvidence;
}

class SupervisionPickedFile {
  const SupervisionPickedFile({required this.name, required this.bytes});

  final String name;
  final Uint8List bytes;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value.whereType<Map<String, dynamic>>().map(convert).toList()
    : <T>[];

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
