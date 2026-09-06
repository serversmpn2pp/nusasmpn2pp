class CentralExamPreparationPage {
  const CentralExamPreparationPage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.references,
    required this.access,
    required this.query,
    required this.status,
  });

  factory CentralExamPreparationPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return CentralExamPreparationPage(
      items: _list(json['items'], CentralExamEvent.fromJson),
      summary: CentralExamPreparationSummary.fromJson(_map(json['ringkasan'])),
      pagination: CentralExamPreparationPagination.fromJson(
        _map(json['paginasi']),
      ),
      references: CentralExamPreparationReferences.fromJson(
        _map(json['referensi']),
      ),
      access: CentralExamPreparationAccess.fromJson(_map(json['hak_akses'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<CentralExamEvent> items;
  final CentralExamPreparationSummary summary;
  final CentralExamPreparationPagination pagination;
  final CentralExamPreparationReferences references;
  final CentralExamPreparationAccess access;
  final String query;
  final String status;

  CentralExamPreparationPage append(CentralExamPreparationPage next) =>
      CentralExamPreparationPage(
        items: [...items, ...next.items],
        summary: next.summary,
        pagination: next.pagination,
        references: next.references,
        access: next.access,
        query: next.query,
        status: next.status,
      );
}

class CentralExamPreparationDetail {
  const CentralExamPreparationDetail({
    required this.event,
    required this.committee,
    required this.sessions,
    required this.rooms,
    required this.participantStage,
    required this.scheduleStage,
    required this.references,
    required this.access,
  });

  factory CentralExamPreparationDetail.fromJson(Map<String, dynamic> json) =>
      CentralExamPreparationDetail(
        event: CentralExamEvent.fromJson(_map(json['kegiatan'])),
        committee: _list(json['panitia'], CentralExamCommitteeMember.fromJson),
        sessions: _list(json['sesi'], CentralExamSession.fromJson),
        rooms: _list(json['ruang'], CentralExamRoom.fromJson),
        participantStage: CentralExamParticipantStage.fromJson(
          _map(json['tahap_peserta']),
        ),
        scheduleStage: CentralExamScheduleStage.fromJson(
          _map(json['tahap_jadwal']),
        ),
        references: CentralExamPreparationReferences.fromJson(
          _map(json['referensi']),
        ),
        access: CentralExamPreparationAccess.fromJson(_map(json['hak_akses'])),
      );

  final CentralExamEvent event;
  final List<CentralExamCommitteeMember> committee;
  final List<CentralExamSession> sessions;
  final List<CentralExamRoom> rooms;
  final CentralExamParticipantStage participantStage;
  final CentralExamScheduleStage scheduleStage;
  final CentralExamPreparationReferences references;
  final CentralExamPreparationAccess access;
}

class CentralExamParticipantStage {
  const CentralExamParticipantStage({
    required this.grades,
    required this.roomUsages,
  });

  factory CentralExamParticipantStage.fromJson(Map<String, dynamic> json) =>
      CentralExamParticipantStage(
        grades: _list(json['tingkat'], CentralExamGradePreparation.fromJson),
        roomUsages: _list(
          json['penggunaan_ruang'],
          CentralExamRoomUsage.fromJson,
        ),
      );

  final List<CentralExamGradePreparation> grades;
  final List<CentralExamRoomUsage> roomUsages;
}

class CentralExamGradePreparation {
  const CentralExamGradePreparation({
    required this.grade,
    required this.activeStudentCount,
    required this.classes,
    this.assignment,
  });

  factory CentralExamGradePreparation.fromJson(Map<String, dynamic> json) =>
      CentralExamGradePreparation(
        grade: _integer(json['tingkat']),
        activeStudentCount: _integer(json['jumlah_siswa_aktif']),
        classes: _list(json['kelas'], CentralExamClassReference.fromJson),
        assignment: json['penetapan'] is Map
            ? CentralExamParticipantAssignment.fromJson(_map(json['penetapan']))
            : null,
      );

  final int grade;
  final int activeStudentCount;
  final List<CentralExamClassReference> classes;
  final CentralExamParticipantAssignment? assignment;
}

class CentralExamClassReference {
  const CentralExamClassReference({
    required this.id,
    required this.name,
    required this.activeStudentCount,
  });

  factory CentralExamClassReference.fromJson(Map<String, dynamic> json) =>
      CentralExamClassReference(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        activeStudentCount: _integer(json['jumlah_siswa_aktif']),
      );

  final int id;
  final String name;
  final int activeStudentCount;
}

class CentralExamParticipantAssignment {
  const CentralExamParticipantAssignment({
    required this.id,
    required this.sessionId,
    required this.sessionName,
    required this.timeLabel,
    required this.classIds,
    required this.roomIds,
    required this.participantCount,
    required this.distributedCount,
    required this.totalCapacity,
    required this.scheduleCount,
    required this.canDelete,
    this.generatedAt,
  });

  factory CentralExamParticipantAssignment.fromJson(
    Map<String, dynamic> json,
  ) => CentralExamParticipantAssignment(
    id: _integer(json['id']),
    sessionId: _integer(json['sesi_id']),
    sessionName: json['nama_sesi'] as String? ?? '-',
    timeLabel: json['label_waktu'] as String? ?? '-',
    classIds: _integers(json['kelas_id']),
    roomIds: _integers(json['ruang_id']),
    participantCount: _integer(json['jumlah_peserta']),
    distributedCount: _integer(json['jumlah_terbagi']),
    totalCapacity: _integer(json['total_kapasitas']),
    scheduleCount: _integer(json['jumlah_jadwal']),
    generatedAt: _date(json['dibangkitkan_pada']),
    canDelete: json['dapat_dihapus'] as bool? ?? false,
  );

  final int id;
  final int sessionId;
  final String sessionName;
  final String timeLabel;
  final List<int> classIds;
  final List<int> roomIds;
  final int participantCount;
  final int distributedCount;
  final int totalCapacity;
  final int scheduleCount;
  final DateTime? generatedAt;
  final bool canDelete;
}

class CentralExamRoomUsage {
  const CentralExamRoomUsage({
    required this.roomId,
    required this.sessionId,
    required this.grade,
  });

  factory CentralExamRoomUsage.fromJson(Map<String, dynamic> json) =>
      CentralExamRoomUsage(
        roomId: _integer(json['ruang_id']),
        sessionId: _integer(json['sesi_id']),
        grade: _integer(json['tingkat']),
      );

  final int roomId;
  final int sessionId;
  final int grade;
}

class CentralExamDistributionDetail {
  const CentralExamDistributionDetail({
    required this.eventName,
    required this.eventCode,
    required this.grade,
    required this.sessionName,
    required this.timeLabel,
    required this.classNames,
    required this.participantCount,
    required this.totalCapacity,
    required this.rooms,
  });

  factory CentralExamDistributionDetail.fromJson(Map<String, dynamic> json) {
    final event = _map(json['kegiatan']);
    final group = _map(json['kelompok']);
    return CentralExamDistributionDetail(
      eventName: event['nama'] as String? ?? '-',
      eventCode: event['kode'] as String? ?? '-',
      grade: _integer(group['tingkat']),
      sessionName: group['nama_sesi'] as String? ?? '-',
      timeLabel: group['label_waktu'] as String? ?? '-',
      classNames: (group['nama_kelas'] as List<dynamic>? ?? const [])
          .whereType<String>()
          .toList(growable: false),
      participantCount: _integer(group['jumlah_peserta']),
      totalCapacity: _integer(group['total_kapasitas']),
      rooms: _list(json['ruang'], CentralExamDistributionRoom.fromJson),
    );
  }

  final String eventName;
  final String eventCode;
  final int grade;
  final String sessionName;
  final String timeLabel;
  final List<String> classNames;
  final int participantCount;
  final int totalCapacity;
  final List<CentralExamDistributionRoom> rooms;
}

class CentralExamDistributionRoom {
  const CentralExamDistributionRoom({
    required this.id,
    required this.code,
    required this.name,
    required this.capacity,
    required this.occupiedCount,
    required this.participants,
    this.location,
  });

  factory CentralExamDistributionRoom.fromJson(Map<String, dynamic> json) =>
      CentralExamDistributionRoom(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        location: json['lokasi'] as String?,
        capacity: _integer(json['kapasitas']),
        occupiedCount: _integer(json['jumlah_terisi']),
        participants: _list(
          json['peserta'],
          CentralExamDistributedParticipant.fromJson,
        ),
      );

  final int id;
  final String code;
  final String name;
  final String? location;
  final int capacity;
  final int occupiedCount;
  final List<CentralExamDistributedParticipant> participants;
}

class CentralExamDistributedParticipant {
  const CentralExamDistributedParticipant({
    required this.id,
    required this.seatNumber,
    required this.seatCode,
    required this.name,
    required this.className,
    this.participantNumber,
    this.nisn,
  });

  factory CentralExamDistributedParticipant.fromJson(
    Map<String, dynamic> json,
  ) => CentralExamDistributedParticipant(
    id: _integer(json['id']),
    seatNumber: _integer(json['nomor_meja']),
    seatCode: json['kode_meja'] as String? ?? '-',
    participantNumber: json['nomor_peserta'] as String?,
    name: json['nama'] as String? ?? '-',
    nisn: json['nisn'] as String?,
    className: json['kelas'] as String? ?? '-',
  );

  final int id;
  final int seatNumber;
  final String seatCode;
  final String? participantNumber;
  final String name;
  final String? nisn;
  final String className;
}

class CentralExamScheduleStage {
  const CentralExamScheduleStage({required this.items, required this.subjects});

  factory CentralExamScheduleStage.fromJson(Map<String, dynamic> json) =>
      CentralExamScheduleStage(
        items: _list(json['items'], CentralExamSchedule.fromJson),
        subjects: _list(
          json['mata_pelajaran'],
          CentralExamScheduleSubject.fromJson,
        ),
      );

  final List<CentralExamSchedule> items;
  final List<CentralExamScheduleSubject> subjects;
}

class CentralExamScheduleSubject {
  const CentralExamScheduleSubject({
    required this.id,
    required this.code,
    required this.name,
    required this.grades,
  });

  factory CentralExamScheduleSubject.fromJson(Map<String, dynamic> json) =>
      CentralExamScheduleSubject(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        grades: _integers(json['tingkat']),
      );

  final int id;
  final String code;
  final String name;
  final List<int> grades;
}

class CentralExamSchedule {
  const CentralExamSchedule({
    required this.id,
    required this.date,
    required this.subjectId,
    required this.subjectName,
    required this.grade,
    required this.sessionName,
    required this.timeLabel,
    required this.classNames,
    required this.roomNames,
    required this.participantCount,
    required this.status,
    required this.statusLabel,
    required this.locked,
    required this.canDelete,
    this.notes,
    this.package,
  });

  factory CentralExamSchedule.fromJson(Map<String, dynamic> json) =>
      CentralExamSchedule(
        id: _integer(json['id']),
        date: _date(json['tanggal']),
        subjectId: _integer(json['mata_pelajaran_id']),
        subjectName: json['mata_pelajaran'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        sessionName: json['nama_sesi'] as String? ?? '-',
        timeLabel: json['label_waktu'] as String? ?? '-',
        classNames: _strings(json['kelas']),
        roomNames: _strings(json['ruang']),
        participantCount: _integer(json['jumlah_peserta']),
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        notes: json['keterangan'] as String?,
        locked: json['terkunci'] as bool? ?? false,
        package: json['paket'] is Map
            ? CentralExamSchedulePackage.fromJson(_map(json['paket']))
            : null,
        canDelete: json['dapat_dihapus'] as bool? ?? false,
      );

  final int id;
  final DateTime? date;
  final int subjectId;
  final String subjectName;
  final int grade;
  final String sessionName;
  final String timeLabel;
  final List<String> classNames;
  final List<String> roomNames;
  final int participantCount;
  final String status;
  final String statusLabel;
  final String? notes;
  final bool locked;
  final CentralExamSchedulePackage? package;
  final bool canDelete;
}

class CentralExamSchedulePackage {
  const CentralExamSchedulePackage({
    required this.id,
    required this.status,
    required this.questionCount,
  });

  factory CentralExamSchedulePackage.fromJson(Map<String, dynamic> json) =>
      CentralExamSchedulePackage(
        id: _integer(json['id']),
        status: json['status'] as String? ?? 'draft',
        questionCount: _integer(json['jumlah_soal']),
      );

  final int id;
  final String status;
  final int questionCount;
}

class CentralExamEvent {
  const CentralExamEvent({
    required this.id,
    required this.code,
    required this.name,
    required this.examType,
    required this.academicYear,
    required this.semester,
    required this.startsOn,
    required this.endsOn,
    required this.status,
    required this.statusLabel,
    required this.committeeCount,
    required this.sessionCount,
    required this.roomCount,
    required this.scheduleCount,
    required this.totalCapacity,
    required this.canDelete,
    this.examTypeId,
    this.academicYearId,
    this.notes,
  });

  factory CentralExamEvent.fromJson(Map<String, dynamic> json) =>
      CentralExamEvent(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        examType: json['jenis_ujian'] as String? ?? '-',
        academicYear: json['tahun_pelajaran'] as String? ?? '-',
        semester: json['semester'] as String? ?? 'ganjil',
        startsOn: _date(json['tanggal_mulai']),
        endsOn: _date(json['tanggal_selesai']),
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        committeeCount: _integer(json['jumlah_panitia']),
        sessionCount: _integer(json['jumlah_sesi']),
        roomCount: _integer(json['jumlah_ruang']),
        scheduleCount: _integer(json['jumlah_jadwal']),
        totalCapacity: _integer(json['total_kapasitas']),
        canDelete: json['dapat_dihapus'] as bool? ?? false,
        examTypeId: _nullableInteger(json['jenis_ujian_cbt_id']),
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        notes: json['keterangan'] as String?,
      );

  final int id;
  final String code;
  final String name;
  final String examType;
  final String academicYear;
  final String semester;
  final DateTime? startsOn;
  final DateTime? endsOn;
  final String status;
  final String statusLabel;
  final int committeeCount;
  final int sessionCount;
  final int roomCount;
  final int scheduleCount;
  final int totalCapacity;
  final bool canDelete;
  final int? examTypeId;
  final int? academicYearId;
  final String? notes;
}

class CentralExamCommitteeMember {
  const CentralExamCommitteeMember({
    required this.id,
    required this.employeeId,
    required this.name,
    required this.position,
    required this.positionLabel,
    required this.hasAccount,
    this.employeeNumber,
    this.notes,
  });

  factory CentralExamCommitteeMember.fromJson(Map<String, dynamic> json) =>
      CentralExamCommitteeMember(
        id: _integer(json['id']),
        employeeId: _integer(json['pegawai_id']),
        name: json['nama'] as String? ?? '-',
        employeeNumber: json['nip'] as String?,
        position: json['jabatan'] as String? ?? 'anggota',
        positionLabel: json['label_jabatan'] as String? ?? '-',
        notes: json['catatan'] as String?,
        hasAccount: json['memiliki_akun'] as bool? ?? false,
      );

  final int id;
  final int employeeId;
  final String name;
  final String? employeeNumber;
  final String position;
  final String positionLabel;
  final String? notes;
  final bool hasAccount;
}

class CentralExamSession {
  const CentralExamSession({
    required this.id,
    required this.code,
    required this.name,
    required this.startsAt,
    required this.endsAt,
    required this.timeLabel,
    required this.active,
    required this.canDelete,
    this.notes,
  });

  factory CentralExamSession.fromJson(Map<String, dynamic> json) =>
      CentralExamSession(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        startsAt: json['waktu_mulai'] as String? ?? '',
        endsAt: json['waktu_selesai'] as String? ?? '',
        timeLabel: json['label_waktu'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
        canDelete: json['dapat_dihapus'] as bool? ?? false,
        notes: json['keterangan'] as String?,
      );

  final int id;
  final String code;
  final String name;
  final String startsAt;
  final String endsAt;
  final String timeLabel;
  final bool active;
  final bool canDelete;
  final String? notes;
}

class CentralExamRoom {
  const CentralExamRoom({
    required this.id,
    required this.code,
    required this.name,
    required this.capacity,
    required this.active,
    required this.canDelete,
    this.location,
    this.notes,
  });

  factory CentralExamRoom.fromJson(Map<String, dynamic> json) =>
      CentralExamRoom(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        location: json['lokasi'] as String?,
        capacity: _integer(json['kapasitas']),
        active: json['aktif'] as bool? ?? false,
        canDelete: json['dapat_dihapus'] as bool? ?? false,
        notes: json['keterangan'] as String?,
      );

  final int id;
  final String code;
  final String name;
  final String? location;
  final int capacity;
  final bool active;
  final bool canDelete;
  final String? notes;
}

class CentralExamPreparationReferences {
  const CentralExamPreparationReferences({
    required this.examTypes,
    required this.academicYears,
    required this.statuses,
    required this.committeePositions,
    required this.employees,
  });

  factory CentralExamPreparationReferences.fromJson(
    Map<String, dynamic> json,
  ) => CentralExamPreparationReferences(
    examTypes: _list(json['jenis_ujian'], CentralExamIdReference.fromJson),
    academicYears: _list(
      json['tahun_pelajaran'],
      CentralExamAcademicYearReference.fromJson,
    ),
    statuses: _list(json['status'], CentralExamCodeReference.fromJson),
    committeePositions: _list(
      json['jabatan_panitia'],
      CentralExamCodeReference.fromJson,
    ),
    employees: _list(json['pegawai'], CentralExamEmployeeReference.fromJson),
  );

  final List<CentralExamIdReference> examTypes;
  final List<CentralExamAcademicYearReference> academicYears;
  final List<CentralExamCodeReference> statuses;
  final List<CentralExamCodeReference> committeePositions;
  final List<CentralExamEmployeeReference> employees;
}

class CentralExamIdReference {
  const CentralExamIdReference({required this.id, required this.name});
  factory CentralExamIdReference.fromJson(Map<String, dynamic> json) =>
      CentralExamIdReference(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class CentralExamAcademicYearReference {
  const CentralExamAcademicYearReference({
    required this.id,
    required this.name,
    required this.active,
  });
  factory CentralExamAcademicYearReference.fromJson(
    Map<String, dynamic> json,
  ) => CentralExamAcademicYearReference(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    active: json['aktif'] as bool? ?? false,
  );
  final int id;
  final String name;
  final bool active;
}

class CentralExamCodeReference {
  const CentralExamCodeReference({required this.code, required this.label});
  factory CentralExamCodeReference.fromJson(Map<String, dynamic> json) =>
      CentralExamCodeReference(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class CentralExamEmployeeReference {
  const CentralExamEmployeeReference({
    required this.id,
    required this.name,
    required this.hasAccount,
    this.employeeNumber,
  });
  factory CentralExamEmployeeReference.fromJson(Map<String, dynamic> json) =>
      CentralExamEmployeeReference(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        employeeNumber: json['nip'] as String?,
        hasAccount: json['memiliki_akun'] as bool? ?? false,
      );
  final int id;
  final String name;
  final String? employeeNumber;
  final bool hasAccount;

  String get label => '$name${hasAccount ? '' : ' · belum memiliki akun'}';
}

class CentralExamPreparationSummary {
  const CentralExamPreparationSummary({
    required this.total,
    required this.draft,
    required this.active,
    required this.completed,
  });
  factory CentralExamPreparationSummary.fromJson(Map<String, dynamic> json) =>
      CentralExamPreparationSummary(
        total: _integer(json['total']),
        draft: _integer(json['persiapan']),
        active: _integer(json['aktif']),
        completed: _integer(json['selesai']),
      );
  final int total;
  final int draft;
  final int active;
  final int completed;
}

class CentralExamPreparationPagination {
  const CentralExamPreparationPagination({
    required this.page,
    required this.lastPage,
    required this.total,
    required this.hasNextPage,
  });
  factory CentralExamPreparationPagination.fromJson(
    Map<String, dynamic> json,
  ) => CentralExamPreparationPagination(
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

class CentralExamPreparationAccess {
  const CentralExamPreparationAccess({
    required this.canManageMain,
    required this.canManagePreparation,
  });
  factory CentralExamPreparationAccess.fromJson(Map<String, dynamic> json) =>
      CentralExamPreparationAccess(
        canManageMain: json['dapat_kelola_utama'] as bool? ?? false,
        canManagePreparation: json['dapat_kelola_persiapan'] as bool? ?? false,
      );
  final bool canManageMain;
  final bool canManagePreparation;
}

class CentralExamEventFormValue {
  const CentralExamEventFormValue({
    required this.examTypeId,
    required this.academicYearId,
    required this.name,
    required this.semester,
    required this.startsOn,
    required this.endsOn,
    required this.status,
    this.notes,
  });
  final int examTypeId;
  final int academicYearId;
  final String name;
  final String semester;
  final DateTime startsOn;
  final DateTime endsOn;
  final String status;
  final String? notes;
}

class CentralExamCommitteeFormValue {
  const CentralExamCommitteeFormValue({
    required this.employeeId,
    required this.position,
    this.notes,
  });
  final int employeeId;
  final String position;
  final String? notes;
}

class CentralExamSessionFormValue {
  const CentralExamSessionFormValue({
    required this.name,
    required this.startsAt,
    required this.endsAt,
    required this.active,
    this.notes,
  });
  final String name;
  final String startsAt;
  final String endsAt;
  final bool active;
  final String? notes;
}

class CentralExamRoomFormValue {
  const CentralExamRoomFormValue({
    required this.name,
    required this.capacity,
    required this.active,
    this.location,
    this.notes,
  });
  final String name;
  final String? location;
  final int capacity;
  final bool active;
  final String? notes;
}

class CentralExamRoomAssignmentFormValue {
  const CentralExamRoomAssignmentFormValue({
    required this.grade,
    required this.sessionId,
    required this.classIds,
    required this.roomIds,
  });

  final int grade;
  final int sessionId;
  final List<int> classIds;
  final List<int> roomIds;
}

class CentralExamScheduleFormValue {
  const CentralExamScheduleFormValue({
    required this.date,
    required this.subjectId,
    required this.grades,
    this.notes,
  });

  final DateTime date;
  final int subjectId;
  final List<int> grades;
  final String? notes;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => convert(Map<String, dynamic>.from(item)))
        .toList(growable: false);

int _integer(Object? value) => value is num ? value.toInt() : 0;
int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
DateTime? _date(Object? value) => DateTime.tryParse(value as String? ?? '');
List<int> _integers(Object? value) => (value as List<dynamic>? ?? const [])
    .whereType<num>()
    .map((item) => item.toInt())
    .toList(growable: false);
List<String> _strings(Object? value) => (value as List<dynamic>? ?? const [])
    .map((item) => item.toString())
    .toList(growable: false);
