class WorshipRecapPage {
  const WorshipRecapPage({
    required this.available,
    required this.date,
    required this.dateLabel,
    required this.activities,
    required this.classes,
    required this.summary,
    required this.classSummaries,
    required this.records,
    required this.filter,
    required this.pagination,
    required this.access,
    required this.privacyMessage,
    this.academicYear,
    this.selectedActivity,
    this.selectedClassId,
    this.schedule,
  });

  factory WorshipRecapPage.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    return WorshipRecapPage(
      available: json['tersedia'] as bool? ?? false,
      date: json['tanggal'] as String? ?? '',
      dateLabel: json['tanggal_label'] as String? ?? '-',
      academicYear: json['tahun_pelajaran'] is Map<String, dynamic>
          ? WorshipRecapAcademicYear.fromJson(_map(json['tahun_pelajaran']))
          : null,
      selectedActivity: json['kegiatan_dipilih'] is Map<String, dynamic>
          ? WorshipRecapActivity.fromJson(_map(json['kegiatan_dipilih']))
          : null,
      selectedClassId: _nullableInteger(json['kelas_dipilih_id']),
      filter: WorshipRecapFilter.fromJson(_map(json['filter'])),
      activities: _list(references['kegiatan'], WorshipRecapActivity.fromJson),
      classes: _list(references['kelas'], WorshipRecapClass.fromJson),
      schedule: json['jadwal'] is Map<String, dynamic>
          ? WorshipRecapSchedule.fromJson(_map(json['jadwal']))
          : null,
      summary: WorshipRecapSummary.fromJson(_map(json['ringkasan'])),
      classSummaries: _list(
        json['ringkasan_kelas'],
        WorshipRecapClassSummary.fromJson,
      ),
      records: _list(json['items'], WorshipRecapRecord.fromJson),
      pagination: WorshipRecapPagination.fromJson(_map(json['paginasi'])),
      access: WorshipRecapAccess.fromJson(_map(json['hak_akses'])),
      privacyMessage: json['pesan_privasi'] as String? ?? '',
    );
  }

  final bool available;
  final String date;
  final String dateLabel;
  final WorshipRecapAcademicYear? academicYear;
  final WorshipRecapActivity? selectedActivity;
  final int? selectedClassId;
  final WorshipRecapFilter filter;
  final List<WorshipRecapActivity> activities;
  final List<WorshipRecapClass> classes;
  final WorshipRecapSchedule? schedule;
  final WorshipRecapSummary summary;
  final List<WorshipRecapClassSummary> classSummaries;
  final List<WorshipRecapRecord> records;
  final WorshipRecapPagination pagination;
  final WorshipRecapAccess access;
  final String privacyMessage;

  WorshipRecapPage append(WorshipRecapPage next) => WorshipRecapPage(
    available: next.available,
    date: next.date,
    dateLabel: next.dateLabel,
    academicYear: next.academicYear,
    selectedActivity: next.selectedActivity,
    selectedClassId: next.selectedClassId,
    filter: next.filter,
    activities: next.activities,
    classes: next.classes,
    schedule: next.schedule,
    summary: next.summary,
    classSummaries: next.classSummaries,
    records: [...records, ...next.records],
    pagination: next.pagination,
    access: next.access,
    privacyMessage: next.privacyMessage,
  );
}

class WorshipRecapAcademicYear {
  const WorshipRecapAcademicYear({required this.id, required this.name});

  factory WorshipRecapAcademicYear.fromJson(Map<String, dynamic> json) =>
      WorshipRecapAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class WorshipRecapActivity {
  const WorshipRecapActivity({
    required this.id,
    required this.name,
    required this.active,
    this.code,
  });

  factory WorshipRecapActivity.fromJson(Map<String, dynamic> json) =>
      WorshipRecapActivity(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        code: json['kode'] as String?,
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String? code;
  final bool active;

  bool get maleOnly => code == 'sholat_jumat';
}

class WorshipRecapClass {
  const WorshipRecapClass({
    required this.id,
    required this.name,
    required this.grade,
    required this.studentCount,
  });

  factory WorshipRecapClass.fromJson(Map<String, dynamic> json) =>
      WorshipRecapClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        studentCount: _integer(json['jumlah_siswa']),
      );

  final int id;
  final String name;
  final int grade;
  final int studentCount;
}

class WorshipRecapFilter {
  const WorshipRecapFilter({required this.status, required this.query});

  factory WorshipRecapFilter.fromJson(Map<String, dynamic> json) =>
      WorshipRecapFilter(
        status: json['status'] as String? ?? 'semua',
        query: json['cari'] as String? ?? '',
      );

  final String status;
  final String query;
}

class WorshipRecapSchedule {
  const WorshipRecapSchedule({
    required this.id,
    required this.active,
    required this.eventTime,
    required this.scanStart,
    required this.scanEnd,
    required this.scanRange,
    this.notes,
  });

  factory WorshipRecapSchedule.fromJson(Map<String, dynamic> json) =>
      WorshipRecapSchedule(
        id: _integer(json['id']),
        active: json['aktif'] as bool? ?? false,
        eventTime: json['jam_pelaksanaan'] as String? ?? '-',
        scanStart: json['jam_scan_mulai'] as String? ?? '-',
        scanEnd: json['jam_scan_selesai'] as String? ?? '-',
        scanRange: json['rentang_scan'] as String? ?? '-',
        notes: json['keterangan'] as String?,
      );

  final int id;
  final bool active;
  final String eventTime;
  final String scanStart;
  final String scanEnd;
  final String scanRange;
  final String? notes;
}

class WorshipRecapSummary {
  const WorshipRecapSummary({
    required this.total,
    required this.atSchool,
    required this.notAtSchool,
    required this.excused,
    required this.notRequired,
    required this.requiredToPray,
    required this.present,
    required this.notPresent,
    required this.percentage,
  });

  factory WorshipRecapSummary.fromJson(Map<String, dynamic> json) =>
      WorshipRecapSummary(
        total: _integer(json['total']),
        atSchool: _integer(json['hadir']),
        notAtSchool: _integer(json['tidak_hadir']),
        excused: _integer(json['berhalangan']),
        notRequired: _integer(json['tidak_wajib']),
        requiredToPray: _integer(json['wajib']),
        present: _integer(json['sudah']),
        notPresent: _integer(json['belum']),
        percentage: _integer(json['persentase']),
      );

  final int total;
  final int atSchool;
  final int notAtSchool;
  final int excused;
  final int notRequired;
  final int requiredToPray;
  final int present;
  final int notPresent;
  final int percentage;
}

class WorshipRecapClassSummary {
  const WorshipRecapClassSummary({
    required this.schoolClass,
    required this.total,
    required this.atSchool,
    required this.notAtSchool,
    required this.excused,
    required this.notRequired,
    required this.requiredToPray,
    required this.present,
    required this.notPresent,
    required this.percentage,
  });

  factory WorshipRecapClassSummary.fromJson(Map<String, dynamic> json) =>
      WorshipRecapClassSummary(
        schoolClass: WorshipRecapClass.fromJson(_map(json['kelas'])),
        total: _integer(json['total']),
        atSchool: _integer(json['hadir']),
        notAtSchool: _integer(json['tidak_hadir']),
        excused: _integer(json['berhalangan']),
        notRequired: _integer(json['tidak_wajib']),
        requiredToPray: _integer(json['wajib']),
        present: _integer(json['sudah']),
        notPresent: _integer(json['belum']),
        percentage: _integer(json['persentase']),
      );

  final WorshipRecapClass schoolClass;
  final int total;
  final int atSchool;
  final int notAtSchool;
  final int excused;
  final int notRequired;
  final int requiredToPray;
  final int present;
  final int notPresent;
  final int percentage;
}

class WorshipRecapRecord {
  const WorshipRecapRecord({
    required this.memberId,
    required this.student,
    required this.schoolClass,
    required this.status,
    required this.statusLabel,
    required this.schoolAttendance,
    required this.schoolAttendanceLabel,
    this.rollNumber,
    this.attendance,
  });

  factory WorshipRecapRecord.fromJson(Map<String, dynamic> json) =>
      WorshipRecapRecord(
        memberId: _integer(json['anggota_kelas_id']),
        rollNumber: _nullableInteger(json['nomor_absen']),
        student: WorshipRecapStudent.fromJson(_map(json['siswa'])),
        schoolClass: WorshipRecapClass.fromJson(_map(json['kelas'])),
        status: json['status'] as String? ?? 'tidak_hadir',
        statusLabel: json['status_label'] as String? ?? 'Tidak hadir sekolah',
        schoolAttendance: json['status_kehadiran'] as String? ?? 'alfa',
        schoolAttendanceLabel:
            json['status_kehadiran_label'] as String? ??
            'Belum tercatat di presensi sekolah',
        attendance: json['presensi'] is Map<String, dynamic>
            ? WorshipRecapAttendance.fromJson(_map(json['presensi']))
            : null,
      );

  final int memberId;
  final int? rollNumber;
  final WorshipRecapStudent student;
  final WorshipRecapClass schoolClass;
  final String status;
  final String statusLabel;
  final String schoolAttendance;
  final String schoolAttendanceLabel;
  final WorshipRecapAttendance? attendance;

  bool get present => status == 'sudah';
  bool get canBeCorrected => status == 'sudah' || status == 'belum';
}

class WorshipRecapStudent {
  const WorshipRecapStudent({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
    this.photoUrl,
  });

  factory WorshipRecapStudent.fromJson(Map<String, dynamic> json) =>
      WorshipRecapStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        photoUrl: json['foto_url'] as String?,
      );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? photoUrl;

  String get initials {
    final words = name.trim().split(RegExp(r'\s+'));
    return words
        .where((word) => word.isNotEmpty)
        .take(2)
        .map((word) => word[0])
        .join()
        .toUpperCase();
  }
}

class WorshipRecapAttendance {
  const WorshipRecapAttendance({
    required this.id,
    required this.time,
    required this.source,
    required this.sourceLabel,
    this.recordedBy,
    this.correctedBy,
    this.correctedAt,
    this.correctionNote,
  });

  factory WorshipRecapAttendance.fromJson(Map<String, dynamic> json) =>
      WorshipRecapAttendance(
        id: _integer(json['id']),
        time: json['waktu'] as String? ?? '-',
        source: json['sumber'] as String? ?? '-',
        sourceLabel: json['sumber_label'] as String? ?? '-',
        recordedBy: json['dicatat_oleh'] as String?,
        correctedBy: json['dikoreksi_oleh'] as String?,
        correctedAt: DateTime.tryParse(json['dikoreksi_pada'] as String? ?? ''),
        correctionNote: json['catatan_koreksi'] as String?,
      );

  final int id;
  final String time;
  final String source;
  final String sourceLabel;
  final String? recordedBy;
  final String? correctedBy;
  final DateTime? correctedAt;
  final String? correctionNote;
}

class WorshipRecapPagination {
  const WorshipRecapPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory WorshipRecapPagination.fromJson(Map<String, dynamic> json) =>
      WorshipRecapPagination(
        page: _integer(json['halaman'], fallback: 1),
        lastPage: _integer(json['halaman_terakhir'], fallback: 1),
        perPage: _integer(json['per_halaman'], fallback: 40),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasNextPage;
}

class WorshipRecapAccess {
  const WorshipRecapAccess({
    required this.canCorrect,
    required this.canScanNow,
  });

  factory WorshipRecapAccess.fromJson(Map<String, dynamic> json) =>
      WorshipRecapAccess(
        canCorrect: json['dapat_koreksi'] as bool? ?? false,
        canScanNow: json['dapat_scan_sekarang'] as bool? ?? false,
      );

  final bool canCorrect;
  final bool canScanNow;
}

class WorshipCorrectionQuery {
  const WorshipCorrectionQuery({
    required this.memberId,
    required this.date,
    required this.activityId,
  });

  final int memberId;
  final String date;
  final int activityId;

  @override
  bool operator ==(Object other) =>
      other is WorshipCorrectionQuery &&
      other.memberId == memberId &&
      other.date == date &&
      other.activityId == activityId;

  @override
  int get hashCode => Object.hash(memberId, date, activityId);
}

class WorshipCorrectionDetail {
  const WorshipCorrectionDetail({
    required this.date,
    required this.dateLabel,
    required this.activity,
    required this.member,
    required this.canCreate,
    required this.initialStatus,
    required this.initialTime,
    required this.history,
    this.schedule,
    this.attendance,
  });

  factory WorshipCorrectionDetail.fromJson(Map<String, dynamic> json) {
    final initial = _map(json['nilai_awal']);
    return WorshipCorrectionDetail(
      date: json['tanggal'] as String? ?? '',
      dateLabel: json['tanggal_label'] as String? ?? '-',
      activity: WorshipRecapActivity.fromJson(_map(json['kegiatan'])),
      member: WorshipCorrectionMember.fromJson(_map(json['anggota_kelas'])),
      schedule: json['jadwal'] is Map<String, dynamic>
          ? WorshipRecapSchedule.fromJson(_map(json['jadwal']))
          : null,
      canCreate: json['dapat_input_baru'] as bool? ?? false,
      attendance: json['presensi'] is Map<String, dynamic>
          ? WorshipRecapAttendance.fromJson(_map(json['presensi']))
          : null,
      initialStatus: initial['status'] as String? ?? 'sudah',
      initialTime: initial['waktu'] as String? ?? '',
      history: _list(json['riwayat'], WorshipCorrectionHistory.fromJson),
    );
  }

  final String date;
  final String dateLabel;
  final WorshipRecapActivity activity;
  final WorshipCorrectionMember member;
  final WorshipRecapSchedule? schedule;
  final bool canCreate;
  final WorshipRecapAttendance? attendance;
  final String initialStatus;
  final String initialTime;
  final List<WorshipCorrectionHistory> history;
}

class WorshipCorrectionMember {
  const WorshipCorrectionMember({
    required this.id,
    required this.student,
    required this.schoolClass,
    this.rollNumber,
  });

  factory WorshipCorrectionMember.fromJson(Map<String, dynamic> json) =>
      WorshipCorrectionMember(
        id: _integer(json['id']),
        rollNumber: _nullableInteger(json['nomor_absen']),
        student: WorshipRecapStudent.fromJson(_map(json['siswa'])),
        schoolClass: WorshipRecapClass.fromJson(_map(json['kelas'])),
      );

  final int id;
  final int? rollNumber;
  final WorshipRecapStudent student;
  final WorshipRecapClass schoolClass;
}

class WorshipCorrectionHistory {
  const WorshipCorrectionHistory({
    required this.id,
    required this.action,
    required this.actionLabel,
    required this.reason,
    required this.changedBy,
    required this.createdAtLabel,
    this.beforeTime,
    this.afterTime,
    this.createdAt,
  });

  factory WorshipCorrectionHistory.fromJson(Map<String, dynamic> json) =>
      WorshipCorrectionHistory(
        id: _integer(json['id']),
        action: json['tindakan'] as String? ?? '-',
        actionLabel: json['tindakan_label'] as String? ?? '-',
        beforeTime: json['waktu_sebelum'] as String?,
        afterTime: json['waktu_sesudah'] as String?,
        reason: json['alasan'] as String? ?? '-',
        changedBy: json['diubah_oleh'] as String? ?? '-',
        createdAt: DateTime.tryParse(json['dibuat_pada'] as String? ?? ''),
        createdAtLabel: json['dibuat_pada_label'] as String? ?? '-',
      );

  final int id;
  final String action;
  final String actionLabel;
  final String? beforeTime;
  final String? afterTime;
  final String reason;
  final String changedBy;
  final DateTime? createdAt;
  final String createdAtLabel;
}

class WorshipCorrectionResult {
  const WorshipCorrectionResult({required this.message, required this.detail});

  final String message;
  final WorshipCorrectionDetail detail;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) mapper) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(mapper)
        .toList(growable: false);

int _integer(Object? value, {int fallback = 0}) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);
