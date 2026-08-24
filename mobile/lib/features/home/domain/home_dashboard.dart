class HomeDashboard {
  const HomeDashboard({
    required this.generatedAt,
    required this.greeting,
    required this.dayName,
    required this.dateLabel,
    required this.monthLabel,
    required this.academicYear,
    required this.employee,
    required this.attendance,
    required this.duty,
    required this.guardianship,
    required this.notifications,
  });

  factory HomeDashboard.fromJson(Map<String, dynamic> json) {
    final date = _map(json['tanggal']);
    final year = _map(json['tahun_pelajaran']);

    return HomeDashboard(
      generatedAt: DateTime.parse(json['dihasilkan_pada'] as String),
      greeting: json['salam'] as String,
      dayName: date?['hari'] as String? ?? '-',
      dateLabel: date?['label'] as String? ?? '-',
      monthLabel: date?['bulan'] as String? ?? '-',
      academicYear: year?['nama'] as String?,
      employee: switch (_map(json['pegawai'])) {
        final data? => EmployeeSummary.fromJson(data),
        _ => null,
      },
      attendance: switch (_map(json['presensi'])) {
        final data? => AttendanceSummary.fromJson(data),
        _ => null,
      },
      duty: switch (_map(json['piket_hari_ini'])) {
        final data? => DutySummary.fromJson(data),
        _ => null,
      },
      guardianship: switch (_map(json['perwalian'])) {
        final data? => GuardianshipSummary.fromJson(data),
        _ => null,
      },
      notifications: NotificationSummary.fromJson(
        _map(json['notifikasi']) ?? const {},
      ),
    );
  }

  final DateTime generatedAt;
  final String greeting;
  final String dayName;
  final String dateLabel;
  final String monthLabel;
  final String? academicYear;
  final EmployeeSummary? employee;
  final AttendanceSummary? attendance;
  final DutySummary? duty;
  final GuardianshipSummary? guardianship;
  final NotificationSummary notifications;
}

class EmployeeSummary {
  const EmployeeSummary({
    required this.name,
    this.nip,
    this.position,
    this.email,
    this.phone,
    this.photoUrl,
  });

  factory EmployeeSummary.fromJson(Map<String, dynamic> json) {
    return EmployeeSummary(
      name: json['nama'] as String,
      nip: json['nip'] as String?,
      position: json['jabatan'] as String?,
      email: json['email'] as String?,
      phone: json['no_hp'] as String?,
      photoUrl: json['foto_url'] as String?,
    );
  }

  final String name;
  final String? nip;
  final String? position;
  final String? email;
  final String? phone;
  final String? photoUrl;
}

class AttendanceSummary {
  const AttendanceSummary({required this.today, required this.month});

  factory AttendanceSummary.fromJson(Map<String, dynamic> json) {
    return AttendanceSummary(
      today: TodayAttendance.fromJson(_map(json['hari_ini']) ?? const {}),
      month: MonthlyAttendance.fromJson(_map(json['bulan_ini']) ?? const {}),
    );
  }

  final TodayAttendance today;
  final MonthlyAttendance month;
}

class TodayAttendance {
  const TodayAttendance({
    required this.recorded,
    required this.statusLabel,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    this.checkIn,
    this.checkOut,
  });

  factory TodayAttendance.fromJson(Map<String, dynamic> json) {
    return TodayAttendance(
      recorded: json['tercatat'] as bool? ?? false,
      statusLabel: json['label_status'] as String? ?? 'Belum tercatat',
      checkIn: json['jam_masuk'] as String?,
      checkOut: json['jam_pulang'] as String?,
      lateMinutes: _integer(json['menit_terlambat']),
      earlyLeaveMinutes: _integer(json['menit_pulang_cepat']),
    );
  }

  final bool recorded;
  final String statusLabel;
  final String? checkIn;
  final String? checkOut;
  final int lateMinutes;
  final int earlyLeaveMinutes;
}

class MonthlyAttendance {
  const MonthlyAttendance({
    required this.total,
    required this.present,
    required this.sick,
    required this.permitted,
    required this.officialDuty,
    required this.leave,
    required this.absent,
    required this.late,
    required this.earlyLeave,
  });

  factory MonthlyAttendance.fromJson(Map<String, dynamic> json) {
    return MonthlyAttendance(
      total: _integer(json['total_catatan']),
      present: _integer(json['hadir']),
      sick: _integer(json['sakit']),
      permitted: _integer(json['izin']),
      officialDuty: _integer(json['dinas_luar']),
      leave: _integer(json['cuti']),
      absent: _integer(json['alfa']),
      late: _integer(json['terlambat']),
      earlyLeave: _integer(json['pulang_cepat']),
    );
  }

  final int total;
  final int present;
  final int sick;
  final int permitted;
  final int officialDuty;
  final int leave;
  final int absent;
  final int late;
  final int earlyLeave;
}

class DutySummary {
  const DutySummary({required this.dayLabel, this.notes});

  factory DutySummary.fromJson(Map<String, dynamic> json) {
    return DutySummary(
      dayLabel: json['label_hari'] as String,
      notes: json['keterangan'] as String?,
    );
  }

  final String dayLabel;
  final String? notes;
}

class GuardianshipSummary {
  const GuardianshipSummary({
    required this.classCount,
    required this.classStudentCount,
    required this.menteeCount,
    required this.classes,
  });

  factory GuardianshipSummary.fromJson(Map<String, dynamic> json) {
    final classes = json['kelas'];

    return GuardianshipSummary(
      classCount: _integer(json['jumlah_kelas']),
      classStudentCount: _integer(json['jumlah_siswa_kelas']),
      menteeCount: _integer(json['jumlah_siswa_guru_wali']),
      classes: switch (classes) {
        List() => List.unmodifiable(
          classes.map((item) => GuardianClass.fromJson(_map(item)!)),
        ),
        _ => const [],
      },
    );
  }

  final int classCount;
  final int classStudentCount;
  final int menteeCount;
  final List<GuardianClass> classes;

  bool get hasAssignments => classCount > 0 || menteeCount > 0;
}

class GuardianClass {
  const GuardianClass({required this.name, required this.studentCount});

  factory GuardianClass.fromJson(Map<String, dynamic> json) {
    return GuardianClass(
      name: json['nama'] as String,
      studentCount: _integer(json['jumlah_siswa']),
    );
  }

  final String name;
  final int studentCount;
}

class NotificationSummary {
  const NotificationSummary({required this.unreadCount, required this.items});

  factory NotificationSummary.fromJson(Map<String, dynamic> json) {
    final items = json['terbaru'];

    return NotificationSummary(
      unreadCount: _integer(json['jumlah_belum_dibaca']),
      items: switch (items) {
        List() => List.unmodifiable(
          items.map((item) => AppNotification.fromJson(_map(item)!)),
        ),
        _ => const [],
      },
    );
  }

  final int unreadCount;
  final List<AppNotification> items;
}

class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.title,
    required this.message,
    required this.unread,
    required this.createdAt,
    required this.relativeTime,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: _integer(json['id']),
      type: json['jenis'] as String,
      typeLabel: json['label_jenis'] as String,
      title: json['judul'] as String,
      message: json['pesan'] as String,
      unread: json['belum_dibaca'] as bool? ?? false,
      createdAt: DateTime.parse(json['dibuat_pada'] as String),
      relativeTime: json['waktu_relatif'] as String,
    );
  }

  final int id;
  final String type;
  final String typeLabel;
  final String title;
  final String message;
  final bool unread;
  final DateTime createdAt;
  final String relativeTime;
}

Map<String, dynamic>? _map(Object? value) {
  return value is Map ? Map<String, dynamic>.from(value) : null;
}

int _integer(Object? value) => value is num ? value.toInt() : 0;
