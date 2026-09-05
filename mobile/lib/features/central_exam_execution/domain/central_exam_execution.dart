class CentralExamExecutionPage {
  const CentralExamExecutionPage({
    required this.summary,
    required this.items,
    required this.statuses,
    required this.filter,
    required this.pagination,
  });

  factory CentralExamExecutionPage.fromJson(Map<String, dynamic> json) =>
      CentralExamExecutionPage(
        summary: CentralExamEventSummary.fromJson(_map(json['ringkasan'])),
        items: _list(json['items'], CentralExamEvent.fromJson),
        statuses: _list(
          _map(json['referensi'])['status'],
          CentralExamOption.fromJson,
        ),
        filter: CentralExamEventFilter.fromJson(_map(json['filter'])),
        pagination: CentralExamPagination.fromJson(_map(json['paginasi'])),
      );

  final CentralExamEventSummary summary;
  final List<CentralExamEvent> items;
  final List<CentralExamOption> statuses;
  final CentralExamEventFilter filter;
  final CentralExamPagination pagination;

  CentralExamExecutionPage append(CentralExamExecutionPage next) =>
      CentralExamExecutionPage(
        summary: next.summary,
        items: [...items, ...next.items],
        statuses: next.statuses,
        filter: next.filter,
        pagination: next.pagination,
      );
}

class CentralExamEventSummary {
  const CentralExamEventSummary({
    required this.total,
    required this.active,
    required this.preparation,
    required this.finished,
  });
  factory CentralExamEventSummary.fromJson(Map<String, dynamic> json) =>
      CentralExamEventSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        preparation: _integer(json['persiapan']),
        finished: _integer(json['selesai']),
      );
  final int total;
  final int active;
  final int preparation;
  final int finished;
}

class CentralExamEvent {
  const CentralExamEvent({
    required this.id,
    required this.code,
    required this.name,
    required this.semester,
    required this.status,
    required this.statusLabel,
    required this.scheduleCount,
    required this.readyPackageCount,
    required this.participantCount,
    this.type,
    this.academicYear,
    this.startDate,
    this.endDate,
  });
  factory CentralExamEvent.fromJson(Map<String, dynamic> json) =>
      CentralExamEvent(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        type: json['jenis'] as String?,
        academicYear: json['tahun_pelajaran'] as String?,
        semester: json['semester'] as String? ?? '-',
        startDate: json['tanggal_mulai'] as String?,
        endDate: json['tanggal_selesai'] as String?,
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        scheduleCount: _integer(json['jumlah_jadwal']),
        readyPackageCount: _integer(json['paket_siap']),
        participantCount: _integer(json['jumlah_peserta']),
      );
  final int id;
  final String code;
  final String name;
  final String? type;
  final String? academicYear;
  final String semester;
  final String? startDate;
  final String? endDate;
  final String status;
  final String statusLabel;
  final int scheduleCount;
  final int readyPackageCount;
  final int participantCount;
}

class CentralExamExecutionDetail {
  const CentralExamExecutionDetail({
    required this.event,
    required this.summary,
    required this.schedules,
    required this.participants,
    required this.alerts,
    required this.statuses,
    required this.employees,
    required this.capabilities,
    required this.generatedAt,
  });
  factory CentralExamExecutionDetail.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    return CentralExamExecutionDetail(
      event: CentralExamExecutionEvent.fromJson(_map(json['kegiatan'])),
      summary: CentralExamExecutionSummary.fromJson(_map(json['ringkasan'])),
      schedules: _list(json['jadwal'], CentralExamSchedule.fromJson),
      participants: CentralExamParticipants.fromJson(_map(json['peserta'])),
      alerts: _list(json['peringatan'], CentralExamAlert.fromJson),
      statuses: _list(references['status_peserta'], CentralExamOption.fromJson),
      employees: _list(references['pegawai'], CentralExamEmployee.fromJson),
      capabilities: CentralExamCapabilities.fromJson(_map(json['kemampuan'])),
      generatedAt: json['dihasilkan_pada'] as String?,
    );
  }
  final CentralExamExecutionEvent event;
  final CentralExamExecutionSummary summary;
  final List<CentralExamSchedule> schedules;
  final CentralExamParticipants participants;
  final List<CentralExamAlert> alerts;
  final List<CentralExamOption> statuses;
  final List<CentralExamEmployee> employees;
  final CentralExamCapabilities capabilities;
  final String? generatedAt;
}

class CentralExamExecutionEvent {
  const CentralExamExecutionEvent({
    required this.id,
    required this.code,
    required this.name,
    required this.semester,
    required this.period,
    required this.status,
    required this.statusLabel,
    this.type,
    this.academicYear,
  });
  factory CentralExamExecutionEvent.fromJson(Map<String, dynamic> json) =>
      CentralExamExecutionEvent(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        type: json['jenis'] as String?,
        academicYear: json['tahun_pelajaran'] as String?,
        semester: json['semester'] as String? ?? '-',
        period: json['periode'] as String? ?? '-',
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
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
}

class CentralExamExecutionSummary {
  const CentralExamExecutionSummary({
    required this.total,
    required this.notPresent,
    required this.presentNotStarted,
    required this.absent,
    required this.working,
    required this.finished,
    required this.blocked,
    required this.scheduleCount,
    required this.roomCount,
    required this.runningRoomCount,
    required this.pendingEvidenceCount,
  });
  factory CentralExamExecutionSummary.fromJson(Map<String, dynamic> json) =>
      CentralExamExecutionSummary(
        total: _integer(json['total']),
        notPresent: _integer(json['belum_hadir']),
        presentNotStarted: _integer(json['hadir_belum_mulai']),
        absent: _integer(json['tidak_hadir']),
        working: _integer(json['sedang_mengerjakan']),
        finished: _integer(json['selesai']),
        blocked: _integer(json['terblokir']),
        scheduleCount: _integer(json['jumlah_jadwal']),
        roomCount: _integer(json['jumlah_ruang']),
        runningRoomCount: _integer(json['ruang_berlangsung']),
        pendingEvidenceCount: _integer(json['bukti_menunggu']),
      );
  final int total;
  final int notPresent;
  final int presentNotStarted;
  final int absent;
  final int working;
  final int finished;
  final int blocked;
  final int scheduleCount;
  final int roomCount;
  final int runningRoomCount;
  final int pendingEvidenceCount;
}

class CentralExamSchedule {
  const CentralExamSchedule({
    required this.id,
    required this.subject,
    required this.grade,
    required this.classes,
    required this.time,
    required this.status,
    required this.statusLabel,
    required this.rooms,
    this.date,
    this.session,
    this.package,
  });
  factory CentralExamSchedule.fromJson(Map<String, dynamic> json) =>
      CentralExamSchedule(
        id: _integer(json['id']),
        subject: json['mata_pelajaran'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        classes: (json['kelas'] as List? ?? const [])
            .map((item) => item.toString())
            .toList(),
        date: json['tanggal'] as String?,
        time: json['waktu'] as String? ?? '-',
        session: json['sesi'] as String?,
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        package: json['paket'] is Map
            ? CentralExamPackage.fromJson(_map(json['paket']))
            : null,
        rooms: _list(json['ruang'], CentralExamRoom.fromJson),
      );
  final int id;
  final String subject;
  final int grade;
  final List<String> classes;
  final String? date;
  final String time;
  final String? session;
  final String status;
  final String statusLabel;
  final CentralExamPackage? package;
  final List<CentralExamRoom> rooms;
}

class CentralExamPackage {
  const CentralExamPackage({
    required this.id,
    required this.name,
    required this.status,
    required this.statusLabel,
    required this.requiresToken,
    this.token,
  });
  factory CentralExamPackage.fromJson(Map<String, dynamic> json) =>
      CentralExamPackage(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        token: json['token'] as String?,
        requiresToken: json['memerlukan_token'] as bool? ?? false,
      );
  final int id;
  final String name;
  final String status;
  final String statusLabel;
  final String? token;
  final bool requiresToken;
}

class CentralExamRoom {
  const CentralExamRoom({
    required this.id,
    required this.sourceRoomId,
    required this.code,
    required this.name,
    required this.status,
    required this.statusLabel,
    required this.evidenceStatus,
    required this.evidenceStatusLabel,
    required this.summary,
    required this.canManageSupervisors,
    this.location,
    this.primarySupervisor,
    this.secondarySupervisor,
  });
  factory CentralExamRoom.fromJson(Map<String, dynamic> json) =>
      CentralExamRoom(
        id: _integer(json['id']),
        sourceRoomId: _integer(json['ruang_kegiatan_id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        location: json['lokasi'] as String?,
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        evidenceStatus: json['status_bukti'] as String? ?? 'belum_diunggah',
        evidenceStatusLabel: json['label_status_bukti'] as String? ?? '-',
        primarySupervisor: json['pengawas_utama'] is Map
            ? CentralExamEmployee.fromJson(_map(json['pengawas_utama']))
            : null,
        secondarySupervisor: json['pengawas_pendamping'] is Map
            ? CentralExamEmployee.fromJson(_map(json['pengawas_pendamping']))
            : null,
        summary: CentralExamRoomSummary.fromJson(_map(json['ringkasan'])),
        canManageSupervisors: json['dapat_mengatur_pengawas'] as bool? ?? false,
      );
  final int id;
  final int sourceRoomId;
  final String code;
  final String name;
  final String? location;
  final String status;
  final String statusLabel;
  final String evidenceStatus;
  final String evidenceStatusLabel;
  final CentralExamEmployee? primarySupervisor;
  final CentralExamEmployee? secondarySupervisor;
  final CentralExamRoomSummary summary;
  final bool canManageSupervisors;
}

class CentralExamRoomSummary {
  const CentralExamRoomSummary({
    required this.total,
    required this.notPresent,
    required this.presentNotStarted,
    required this.absent,
    required this.working,
    required this.finished,
    required this.blocked,
  });
  factory CentralExamRoomSummary.fromJson(Map<String, dynamic> json) =>
      CentralExamRoomSummary(
        total: _integer(json['total']),
        notPresent: _integer(json['belum_hadir']),
        presentNotStarted: _integer(json['hadir_belum_mulai']),
        absent: _integer(json['tidak_hadir']),
        working: _integer(json['sedang_mengerjakan']),
        finished: _integer(json['selesai']),
        blocked: _integer(json['terblokir']),
      );
  final int total;
  final int notPresent;
  final int presentNotStarted;
  final int absent;
  final int working;
  final int finished;
  final int blocked;
}

class CentralExamParticipants {
  const CentralExamParticipants({
    required this.items,
    required this.filter,
    required this.pagination,
  });
  factory CentralExamParticipants.fromJson(Map<String, dynamic> json) =>
      CentralExamParticipants(
        items: _list(json['items'], CentralExamParticipant.fromJson),
        filter: CentralExamParticipantFilter.fromJson(_map(json['filter'])),
        pagination: CentralExamPagination.fromJson(_map(json['paginasi'])),
      );
  final List<CentralExamParticipant> items;
  final CentralExamParticipantFilter filter;
  final CentralExamPagination pagination;
}

class CentralExamParticipant {
  const CentralExamParticipant({
    required this.id,
    required this.name,
    required this.participantNumber,
    required this.className,
    required this.room,
    required this.subject,
    required this.status,
    required this.statusLabel,
    required this.savedAnswerCount,
    required this.appSwitchCount,
    required this.staleHeartbeat,
    required this.canUnlockSafeMode,
    this.nisn,
    this.roomId,
    this.scheduleId,
    this.lastHeartbeat,
  });
  factory CentralExamParticipant.fromJson(Map<String, dynamic> json) =>
      CentralExamParticipant(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? 'Siswa',
        nisn: json['nisn'] as String?,
        participantNumber: json['nomor_peserta'] as String? ?? '-',
        className: json['kelas'] as String? ?? '-',
        room: json['ruang'] as String? ?? '-',
        roomId: _nullableInteger(json['ruang_id']),
        scheduleId: _nullableInteger(json['jadwal_id']),
        subject: json['mata_pelajaran'] as String? ?? '-',
        status: json['status'] as String? ?? 'belum_hadir',
        statusLabel: json['label_status'] as String? ?? '-',
        savedAnswerCount: _integer(json['jawaban_tersimpan']),
        appSwitchCount: _integer(json['jumlah_pindah_aplikasi']),
        lastHeartbeat: json['heartbeat_terakhir_pada'] as String?,
        staleHeartbeat: json['heartbeat_terlambat'] as bool? ?? false,
        canUnlockSafeMode: json['dapat_dibuka_mode_aman'] as bool? ?? false,
      );
  final int id;
  final String name;
  final String? nisn;
  final String participantNumber;
  final String className;
  final String room;
  final int? roomId;
  final int? scheduleId;
  final String subject;
  final String status;
  final String statusLabel;
  final int savedAnswerCount;
  final int appSwitchCount;
  final String? lastHeartbeat;
  final bool staleHeartbeat;
  final bool canUnlockSafeMode;
}

class CentralExamAlert {
  const CentralExamAlert({
    required this.type,
    required this.title,
    required this.description,
    this.participantId,
    this.roomId,
  });
  factory CentralExamAlert.fromJson(Map<String, dynamic> json) =>
      CentralExamAlert(
        type: json['jenis'] as String? ?? '-',
        title: json['judul'] as String? ?? '-',
        description: json['keterangan'] as String? ?? '-',
        participantId: _nullableInteger(json['peserta_id']),
        roomId: _nullableInteger(json['ruang_id']),
      );
  final String type;
  final String title;
  final String description;
  final int? participantId;
  final int? roomId;
}

class CentralExamEmployee {
  const CentralExamEmployee({required this.id, required this.name, this.nip});
  factory CentralExamEmployee.fromJson(Map<String, dynamic> json) =>
      CentralExamEmployee(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
      );
  final int id;
  final String name;
  final String? nip;
}

class CentralExamCapabilities {
  const CentralExamCapabilities({
    required this.canManageSupervisors,
    required this.canUnlockSafeMode,
    required this.canViewRooms,
  });
  factory CentralExamCapabilities.fromJson(Map<String, dynamic> json) =>
      CentralExamCapabilities(
        canManageSupervisors: json['mengatur_pengawas'] as bool? ?? false,
        canUnlockSafeMode: json['membuka_mode_aman'] as bool? ?? false,
        canViewRooms: json['melihat_ruang'] as bool? ?? true,
      );
  final bool canManageSupervisors;
  final bool canUnlockSafeMode;
  final bool canViewRooms;
}

class CentralExamOption {
  const CentralExamOption({required this.code, required this.label});
  factory CentralExamOption.fromJson(Map<String, dynamic> json) =>
      CentralExamOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class CentralExamEventFilter {
  const CentralExamEventFilter({required this.query, required this.status});
  factory CentralExamEventFilter.fromJson(Map<String, dynamic> json) =>
      CentralExamEventFilter(
        query: json['kata_kunci'] as String? ?? '',
        status: json['status'] as String? ?? 'semua',
      );
  final String query;
  final String status;
}

class CentralExamParticipantFilter {
  const CentralExamParticipantFilter({
    required this.status,
    required this.query,
    this.scheduleId,
    this.roomId,
  });
  factory CentralExamParticipantFilter.fromJson(Map<String, dynamic> json) =>
      CentralExamParticipantFilter(
        status: json['status'] as String? ?? 'semua',
        query: json['kata_kunci'] as String? ?? '',
        scheduleId: _nullableInteger(json['jadwal_id']),
        roomId: _nullableInteger(json['ruang_id']),
      );
  final String status;
  final String query;
  final int? scheduleId;
  final int? roomId;
}

class CentralExamPagination {
  const CentralExamPagination({
    required this.page,
    required this.lastPage,
    required this.total,
    required this.hasNextPage,
  });
  factory CentralExamPagination.fromJson(Map<String, dynamic> json) =>
      CentralExamPagination(
        page: _integer(json['halaman']),
        lastPage: _integer(json['halaman_terakhir']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int lastPage;
  final int total;
  final bool hasNextPage;
}

typedef CentralExamExecutionRequest = ({
  int eventId,
  String status,
  int? scheduleId,
  int? roomId,
  String query,
  int page,
});

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) parser) =>
    (value as List? ?? const [])
        .whereType<Map>()
        .map((item) => parser(Map<String, dynamic>.from(item)))
        .toList();

int _integer(Object? value) => value is num ? value.toInt() : 0;
int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
