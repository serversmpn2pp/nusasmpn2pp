class PrivateConfirmationPage {
  const PrivateConfirmationPage({
    required this.privateMode,
    required this.privacyMessage,
    required this.academicYear,
    required this.summary,
    required this.filter,
    required this.classes,
    required this.items,
    required this.pagination,
  });

  factory PrivateConfirmationPage.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    return PrivateConfirmationPage(
      privateMode: json['mode_privat'] as bool? ?? true,
      privacyMessage: json['pesan_privasi'] as String? ?? '',
      academicYear: PrivateConfirmationAcademicYear.fromJson(
        _map(json['tahun_pelajaran']),
      ),
      summary: PrivateConfirmationSummary.fromJson(_map(json['ringkasan'])),
      filter: PrivateConfirmationFilter.fromJson(_map(json['filter'])),
      classes: _list(references['kelas'], PrivateConfirmationClass.fromJson),
      items: _list(json['items'], PrivateConfirmationItem.fromJson),
      pagination: PrivateConfirmationPagination.fromJson(
        _map(json['paginasi']),
      ),
    );
  }

  final bool privateMode;
  final String privacyMessage;
  final PrivateConfirmationAcademicYear academicYear;
  final PrivateConfirmationSummary summary;
  final PrivateConfirmationFilter filter;
  final List<PrivateConfirmationClass> classes;
  final List<PrivateConfirmationItem> items;
  final PrivateConfirmationPagination pagination;

  PrivateConfirmationPage append(PrivateConfirmationPage next) =>
      PrivateConfirmationPage(
        privateMode: next.privateMode,
        privacyMessage: next.privacyMessage,
        academicYear: next.academicYear,
        summary: next.summary,
        filter: next.filter,
        classes: next.classes,
        items: [...items, ...next.items],
        pagination: next.pagination,
      );
}

class PrivateConfirmationAcademicYear {
  const PrivateConfirmationAcademicYear({required this.id, required this.name});

  factory PrivateConfirmationAcademicYear.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class PrivateConfirmationSummary {
  const PrivateConfirmationSummary({
    required this.pending,
    required this.monitored,
    required this.completedThisMonth,
  });

  factory PrivateConfirmationSummary.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationSummary(
        pending: _integer(json['perlu_konfirmasi']),
        monitored: _integer(json['dipantau']),
        completedThisMonth: _integer(json['selesai_bulan_ini']),
      );

  final int pending;
  final int monitored;
  final int completedThisMonth;
}

class PrivateConfirmationFilter {
  const PrivateConfirmationFilter({required this.query, this.classId});

  factory PrivateConfirmationFilter.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationFilter(
        query: json['cari'] as String? ?? '',
        classId: _nullableInteger(json['kelas_id']),
      );

  final String query;
  final int? classId;
}

class PrivateConfirmationClass {
  const PrivateConfirmationClass({required this.id, required this.name});

  factory PrivateConfirmationClass.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class PrivateConfirmationItem {
  const PrivateConfirmationItem({
    required this.id,
    required this.student,
    required this.schoolClass,
    required this.startDateLabel,
    required this.dayNumber,
    required this.attendanceCount,
    this.startDate,
    this.pendingSince,
  });

  factory PrivateConfirmationItem.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationItem(
        id: _integer(json['id']),
        student: PrivateConfirmationStudent.fromJson(_map(json['siswa'])),
        schoolClass: PrivateConfirmationClass.fromJson(_map(json['kelas'])),
        startDate: json['tanggal_mulai'] as String?,
        startDateLabel: json['tanggal_mulai_label'] as String? ?? '-',
        pendingSince: json['perlu_konfirmasi_sejak'] as String?,
        dayNumber: _integer(json['hari_ke']),
        attendanceCount: _integer(json['jumlah_presensi']),
      );

  final int id;
  final PrivateConfirmationStudent student;
  final PrivateConfirmationClass schoolClass;
  final String? startDate;
  final String startDateLabel;
  final String? pendingSince;
  final int dayNumber;
  final int attendanceCount;
}

class PrivateConfirmationStudent {
  const PrivateConfirmationStudent({
    required this.name,
    required this.nisn,
    this.photoUrl,
  });

  factory PrivateConfirmationStudent.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationStudent(
        name: json['nama_lengkap'] as String? ?? '-',
        nisn: json['nisn'] as String? ?? '-',
        photoUrl: json['foto_url'] as String?,
      );

  final String name;
  final String nisn;
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

class PrivateConfirmationPagination {
  const PrivateConfirmationPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory PrivateConfirmationPagination.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationPagination(
        page: _integer(json['halaman'], fallback: 1),
        lastPage: _integer(json['halaman_terakhir'], fallback: 1),
        perPage: _integer(json['per_halaman'], fallback: 12),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasNextPage;
}

class PrivateConfirmationDetail {
  const PrivateConfirmationDetail({
    required this.privateMode,
    required this.privacyMessage,
    required this.canConfirm,
    required this.initialReminderDays,
    required this.period,
    required this.attendance,
    required this.history,
  });

  factory PrivateConfirmationDetail.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationDetail(
        privateMode: json['mode_privat'] as bool? ?? true,
        privacyMessage: json['pesan_privasi'] as String? ?? '',
        canConfirm: json['dapat_dikonfirmasi'] as bool? ?? false,
        initialReminderDays: _integer(json['jeda_awal_hari'], fallback: 3),
        period: PrivateConfirmationPeriod.fromJson(_map(json['periode'])),
        attendance: _list(
          json['presensi_harian'],
          PrivateConfirmationAttendance.fromJson,
        ),
        history: _list(
          json['riwayat_konfirmasi'],
          PrivateConfirmationHistory.fromJson,
        ),
      );

  final bool privateMode;
  final String privacyMessage;
  final bool canConfirm;
  final int initialReminderDays;
  final PrivateConfirmationPeriod period;
  final List<PrivateConfirmationAttendance> attendance;
  final List<PrivateConfirmationHistory> history;
}

class PrivateConfirmationPeriod {
  const PrivateConfirmationPeriod({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.startDateLabel,
    required this.dayNumber,
    required this.confirmationDayLimit,
    required this.student,
    required this.schoolClass,
    this.startDate,
    this.endDate,
    this.pendingSince,
    this.nextConfirmationDate,
    this.initialPrivateNote,
  });

  factory PrivateConfirmationPeriod.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationPeriod(
        id: _integer(json['id']),
        status: json['status'] as String? ?? '-',
        statusLabel: json['status_label'] as String? ?? '-',
        startDate: json['tanggal_mulai'] as String?,
        startDateLabel: json['tanggal_mulai_label'] as String? ?? '-',
        endDate: json['tanggal_selesai'] as String?,
        dayNumber: _integer(json['hari_ke']),
        confirmationDayLimit: _integer(json['batas_hari_konfirmasi']),
        pendingSince: json['perlu_konfirmasi_sejak'] as String?,
        nextConfirmationDate: json['konfirmasi_berikutnya_pada'] as String?,
        initialPrivateNote: json['catatan_privat_awal'] as String?,
        student: PrivateConfirmationStudent.fromJson(_map(json['siswa'])),
        schoolClass: PrivateConfirmationClass.fromJson(_map(json['kelas'])),
      );

  final int id;
  final String status;
  final String statusLabel;
  final String? startDate;
  final String startDateLabel;
  final String? endDate;
  final int dayNumber;
  final int confirmationDayLimit;
  final String? pendingSince;
  final String? nextConfirmationDate;
  final String? initialPrivateNote;
  final PrivateConfirmationStudent student;
  final PrivateConfirmationClass schoolClass;
}

class PrivateConfirmationAttendance {
  const PrivateConfirmationAttendance({
    required this.id,
    required this.dateLabel,
    required this.time,
    required this.activity,
    this.date,
  });

  factory PrivateConfirmationAttendance.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationAttendance(
        id: _integer(json['id']),
        date: json['tanggal'] as String?,
        dateLabel: json['tanggal_label'] as String? ?? '-',
        time: json['waktu_scan'] as String? ?? '-',
        activity: json['kegiatan'] as String? ?? 'Kegiatan ibadah',
      );

  final int id;
  final String? date;
  final String dateLabel;
  final String time;
  final String activity;
}

class PrivateConfirmationHistory {
  const PrivateConfirmationHistory({
    required this.id,
    required this.result,
    required this.resultLabel,
    required this.confirmedAtLabel,
    required this.confirmedBy,
    this.confirmedAt,
    this.nextConfirmationDate,
    this.privateNote,
  });

  factory PrivateConfirmationHistory.fromJson(Map<String, dynamic> json) =>
      PrivateConfirmationHistory(
        id: _integer(json['id']),
        result: json['hasil'] as String? ?? '-',
        resultLabel: json['hasil_label'] as String? ?? '-',
        confirmedAt: DateTime.tryParse(
          json['dikonfirmasi_pada'] as String? ?? '',
        ),
        confirmedAtLabel: json['dikonfirmasi_pada_label'] as String? ?? '-',
        confirmedBy: json['dikonfirmasi_oleh'] as String? ?? '-',
        nextConfirmationDate: json['konfirmasi_berikutnya_pada'] as String?,
        privateNote: json['catatan_privat'] as String?,
      );

  final int id;
  final String result;
  final String resultLabel;
  final DateTime? confirmedAt;
  final String confirmedAtLabel;
  final String confirmedBy;
  final String? nextConfirmationDate;
  final String? privateNote;
}

class PrivateConfirmationUpdateResult {
  const PrivateConfirmationUpdateResult({
    required this.message,
    required this.detail,
  });

  final String message;
  final PrivateConfirmationDetail detail;
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
