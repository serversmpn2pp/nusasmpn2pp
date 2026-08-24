import 'package:nusa/features/student/domain/student.dart';

class SchoolClassPage {
  const SchoolClassPage({
    required this.items,
    required this.counts,
    required this.academicYears,
    required this.pagination,
    required this.query,
    required this.status,
    this.academicYearId,
  });

  factory SchoolClassPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};

    return SchoolClassPage(
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                SchoolClassSummary.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      counts: SchoolClassCounts.fromJson(_map(json['ringkasan']) ?? const {}),
      academicYears: (json['tahun_pelajaran'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((item) => AcademicYear.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
      pagination: SchoolClassPagination.fromJson(
        _map(json['paginasi']) ?? const {},
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      academicYearId: _nullableInteger(filter['tahun_pelajaran_id']),
    );
  }

  final List<SchoolClassSummary> items;
  final SchoolClassCounts counts;
  final List<AcademicYear> academicYears;
  final SchoolClassPagination pagination;
  final String query;
  final String status;
  final int? academicYearId;

  SchoolClassPage append(SchoolClassPage next) {
    return SchoolClassPage(
      items: List.unmodifiable([...items, ...next.items]),
      counts: next.counts,
      academicYears: next.academicYears,
      pagination: next.pagination,
      query: next.query,
      status: next.status,
      academicYearId: next.academicYearId,
    );
  }
}

class SchoolClassCounts {
  const SchoolClassCounts({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory SchoolClassCounts.fromJson(Map<String, dynamic> json) {
    return SchoolClassCounts(
      total: _integer(json['total']),
      active: _integer(json['aktif']),
      inactive: _integer(json['nonaktif']),
    );
  }

  final int total;
  final int active;
  final int inactive;
}

class SchoolClassPagination {
  const SchoolClassPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory SchoolClassPagination.fromJson(Map<String, dynamic> json) {
    return SchoolClassPagination(
      page: _integer(json['halaman']),
      lastPage: _integer(json['halaman_terakhir']),
      perPage: _integer(json['per_halaman']),
      total: _integer(json['total']),
      hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
    );
  }

  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasNextPage;
}

class SchoolClassSummary {
  const SchoolClassSummary({
    required this.id,
    required this.name,
    required this.activeStudentCount,
    required this.active,
    this.level,
    this.capacity,
    this.availableCapacity,
    this.academicYear,
    this.homeroomTeacher,
  });

  factory SchoolClassSummary.fromJson(Map<String, dynamic> json) {
    return SchoolClassSummary(
      id: _integer(json['id']),
      name: json['nama'] as String? ?? '-',
      level: _nullableInteger(json['tingkat']),
      capacity: _nullableInteger(json['kapasitas']),
      activeStudentCount: _integer(json['jumlah_siswa_aktif']),
      availableCapacity: _nullableInteger(json['kapasitas_tersedia']),
      active: json['aktif'] as bool? ?? false,
      academicYear: switch (_map(json['tahun_pelajaran'])) {
        final data? => AcademicYear.fromJson(data),
        _ => null,
      },
      homeroomTeacher: switch (_map(json['wali_kelas'])) {
        final data? => HomeroomTeacher.fromJson(data),
        _ => null,
      },
    );
  }

  final int id;
  final String name;
  final int? level;
  final int? capacity;
  final int activeStudentCount;
  final int? availableCapacity;
  final bool active;
  final AcademicYear? academicYear;
  final HomeroomTeacher? homeroomTeacher;

  double get capacityFraction {
    if (capacity == null || capacity! <= 0) {
      return 0;
    }

    return (activeStudentCount / capacity!).clamp(0, 1).toDouble();
  }

  String get capacityLabel {
    return capacity == null
        ? '$activeStudentCount siswa'
        : '$activeStudentCount/$capacity siswa';
  }
}

class AcademicYear {
  const AcademicYear({
    required this.id,
    required this.name,
    required this.active,
    this.startDate,
    this.endDate,
  });

  factory AcademicYear.fromJson(Map<String, dynamic> json) {
    return AcademicYear(
      id: _integer(json['id']),
      name: json['nama'] as String? ?? '-',
      active: json['aktif'] as bool? ?? false,
      startDate: DateTime.tryParse(json['tanggal_mulai'] as String? ?? ''),
      endDate: DateTime.tryParse(json['tanggal_selesai'] as String? ?? ''),
    );
  }

  final int id;
  final String name;
  final bool active;
  final DateTime? startDate;
  final DateTime? endDate;
}

class HomeroomTeacher {
  const HomeroomTeacher({
    required this.id,
    required this.name,
    this.nip,
    this.position,
    this.photoUrl,
  });

  factory HomeroomTeacher.fromJson(Map<String, dynamic> json) {
    return HomeroomTeacher(
      id: _integer(json['id']),
      name: json['nama'] as String? ?? '-',
      nip: json['nip'] as String?,
      position: json['jabatan'] as String?,
      photoUrl: json['foto_url'] as String?,
    );
  }

  final int id;
  final String name;
  final String? nip;
  final String? position;
  final String? photoUrl;
}

class SchoolClassDetail {
  const SchoolClassDetail({
    required this.summary,
    required this.members,
    required this.permissions,
    this.notes,
    this.schedule,
  });

  factory SchoolClassDetail.fromJson(Map<String, dynamic> json) {
    return SchoolClassDetail(
      summary: SchoolClassSummary.fromJson(json),
      notes: json['keterangan'] as String?,
      members: (json['anggota_siswa'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                SchoolClassMember.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      schedule: switch (_map(json['jadwal_kelas'])) {
        final data? => SchoolClassSchedule.fromJson(data),
        _ => null,
      },
      permissions: SchoolClassPermissions.fromJson(
        _map(json['hak_akses']) ?? const {},
      ),
    );
  }

  final SchoolClassSummary summary;
  final List<SchoolClassMember> members;
  final String? notes;
  final SchoolClassSchedule? schedule;
  final SchoolClassPermissions permissions;

  List<SchoolClassMember> get activeMembers => members
      .where((member) => member.membershipStatus == 'aktif')
      .toList(growable: false);

  List<SchoolClassMember> get membershipHistory => members
      .where((member) => member.membershipStatus != 'aktif')
      .toList(growable: false);
}

class SchoolClassPermissions {
  const SchoolClassPermissions({
    required this.canManageMembers,
    required this.canViewSchedule,
    required this.canManageSchedule,
  });

  factory SchoolClassPermissions.fromJson(Map<String, dynamic> json) {
    return SchoolClassPermissions(
      canManageMembers: json['dapat_kelola_anggota'] as bool? ?? false,
      canViewSchedule: json['dapat_melihat_jadwal'] as bool? ?? false,
      canManageSchedule: json['dapat_kelola_jadwal'] as bool? ?? false,
    );
  }

  final bool canManageMembers;
  final bool canViewSchedule;
  final bool canManageSchedule;
}

class SchoolClassSchedule {
  const SchoolClassSchedule({
    required this.todayCode,
    required this.filledCount,
    required this.days,
  });

  factory SchoolClassSchedule.fromJson(Map<String, dynamic> json) {
    return SchoolClassSchedule(
      todayCode: json['hari_ini'] as String? ?? '',
      filledCount: _integer(json['jumlah_terisi']),
      days: (json['hari'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                ClassScheduleDay.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
    );
  }

  final String todayCode;
  final int filledCount;
  final List<ClassScheduleDay> days;
}

class ClassScheduleDay {
  const ClassScheduleDay({
    required this.code,
    required this.label,
    required this.slots,
  });

  factory ClassScheduleDay.fromJson(Map<String, dynamic> json) {
    return ClassScheduleDay(
      code: json['kode'] as String? ?? '',
      label: json['label'] as String? ?? '-',
      slots: (json['slots'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                ClassScheduleSlot.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
    );
  }

  final String code;
  final String label;
  final List<ClassScheduleSlot> slots;
}

class ClassScheduleSlot {
  const ClassScheduleSlot({
    required this.id,
    required this.number,
    required this.startTime,
    required this.endTime,
    required this.type,
    required this.typeLabel,
    required this.filled,
    this.scheduleChoice,
    this.label,
    this.subject,
    this.teacher,
    this.notes,
  });

  factory ClassScheduleSlot.fromJson(Map<String, dynamic> json) {
    return ClassScheduleSlot(
      id: _integer(json['id']),
      number: _integer(json['nomor_jam']),
      label: json['label'] as String?,
      startTime: json['jam_mulai'] as String? ?? '-',
      endTime: json['jam_selesai'] as String? ?? '-',
      type: json['jenis'] as String? ?? 'pelajaran',
      typeLabel: json['jenis_label'] as String? ?? '-',
      filled: json['terisi'] as bool? ?? false,
      scheduleChoice: json['pilihan_jadwal'] as String?,
      subject: switch (_map(json['mata_pelajaran'])) {
        final data? => ScheduleSubject.fromJson(data),
        _ => null,
      },
      teacher: switch (_map(json['guru'])) {
        final data? => ScheduleTeacher.fromJson(data),
        _ => null,
      },
      notes: json['keterangan'] as String?,
    );
  }

  final int id;
  final int number;
  final String? label;
  final String startTime;
  final String endTime;
  final String type;
  final String typeLabel;
  final bool filled;
  final String? scheduleChoice;
  final ScheduleSubject? subject;
  final ScheduleTeacher? teacher;
  final String? notes;

  bool get isLesson => type == 'pelajaran';
  String get timeLabel => '$startTime - $endTime';
  String get title => isLesson
      ? (subject?.name ?? 'Belum diisi')
      : (label?.trim().isNotEmpty == true ? label! : typeLabel);
}

class ScheduleChoiceCatalog {
  const ScheduleChoiceCatalog({required this.items, required this.count});

  factory ScheduleChoiceCatalog.fromJson(Map<String, dynamic> json) {
    return ScheduleChoiceCatalog(
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) => ScheduleChoice.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      count: _integer(json['jumlah']),
    );
  }

  final List<ScheduleChoice> items;
  final int count;

  List<ScheduleChoice> get teacherAssignments =>
      items.where((item) => item.type == 'guru').toList(growable: false);

  List<ScheduleChoice> get activities =>
      items.where((item) => item.type == 'kegiatan').toList(growable: false);
}

class ScheduleChoice {
  const ScheduleChoice({
    required this.value,
    required this.type,
    required this.title,
    required this.subtitle,
    this.subjectId,
    this.employeeId,
  });

  factory ScheduleChoice.fromJson(Map<String, dynamic> json) {
    return ScheduleChoice(
      value: json['nilai'] as String? ?? '',
      type: json['jenis'] as String? ?? '',
      title: json['judul'] as String? ?? '-',
      subtitle: json['subjudul'] as String? ?? '-',
      subjectId: _nullableInteger(json['mata_pelajaran_id']),
      employeeId: _nullableInteger(json['pegawai_id']),
    );
  }

  final String value;
  final String type;
  final String title;
  final String subtitle;
  final int? subjectId;
  final int? employeeId;

  bool get isTeacherAssignment => type == 'guru';
}

class ScheduleSubject {
  const ScheduleSubject({required this.id, required this.name, this.group});

  factory ScheduleSubject.fromJson(Map<String, dynamic> json) {
    return ScheduleSubject(
      id: _integer(json['id']),
      name: json['nama'] as String? ?? '-',
      group: json['kelompok'] as String?,
    );
  }

  final int id;
  final String name;
  final String? group;
}

class ScheduleTeacher {
  const ScheduleTeacher({required this.id, required this.name, this.nip});

  factory ScheduleTeacher.fromJson(Map<String, dynamic> json) {
    return ScheduleTeacher(
      id: _integer(json['id']),
      name: json['nama'] as String? ?? '-',
      nip: json['nip'] as String?,
    );
  }

  final int id;
  final String name;
  final String? nip;
}

class SchoolClassCandidatePage {
  const SchoolClassCandidatePage({
    required this.items,
    required this.query,
    required this.count,
    this.availableCapacity,
  });

  factory SchoolClassCandidatePage.fromJson(Map<String, dynamic> json) {
    return SchoolClassCandidatePage(
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) => StudentSummary.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      query: json['cari'] as String? ?? '',
      count: _integer(json['jumlah']),
      availableCapacity: _nullableInteger(json['kapasitas_tersedia']),
    );
  }

  final List<StudentSummary> items;
  final String query;
  final int count;
  final int? availableCapacity;
}

class SchoolClassMember {
  const SchoolClassMember({
    required this.id,
    required this.membershipStatus,
    required this.student,
    this.attendanceNumber,
    this.joinDate,
    this.leaveDate,
    this.notes,
  });

  factory SchoolClassMember.fromJson(Map<String, dynamic> json) {
    return SchoolClassMember(
      id: _integer(json['id']),
      attendanceNumber: _nullableInteger(json['nomor_absen']),
      membershipStatus:
          json['status_keanggotaan'] as String? ?? 'tidak_diketahui',
      joinDate: DateTime.tryParse(json['tanggal_masuk'] as String? ?? ''),
      leaveDate: DateTime.tryParse(json['tanggal_keluar'] as String? ?? ''),
      notes: json['keterangan'] as String?,
      student: StudentSummary.fromJson(
        _map(json['siswa']) ?? const <String, dynamic>{},
      ),
    );
  }

  final int id;
  final int? attendanceNumber;
  final String membershipStatus;
  final DateTime? joinDate;
  final DateTime? leaveDate;
  final String? notes;
  final StudentSummary student;

  String get membershipStatusLabel {
    final value = membershipStatus.replaceAll('_', ' ');
    if (value.isEmpty) {
      return '-';
    }

    return '${value[0].toUpperCase()}${value.substring(1)}';
  }
}

Map<String, dynamic>? _map(Object? value) {
  return value is Map ? Map<String, dynamic>.from(value) : null;
}

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
