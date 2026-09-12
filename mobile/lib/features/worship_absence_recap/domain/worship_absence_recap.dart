class WorshipAbsenceRecapPage {
  const WorshipAbsenceRecapPage({
    required this.privateMode,
    required this.privacyMessage,
    required this.month,
    required this.monthLabel,
    required this.minimumMonth,
    required this.maximumMonth,
    required this.filter,
    required this.classes,
    required this.statuses,
    required this.summary,
    required this.items,
    required this.pagination,
    required this.printedAt,
    this.academicYear,
  });

  factory WorshipAbsenceRecapPage.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    return WorshipAbsenceRecapPage(
      privateMode: json['mode_privat'] as bool? ?? true,
      privacyMessage: json['pesan_privasi'] as String? ?? '',
      month: json['bulan'] as String? ?? '',
      monthLabel: json['bulan_label'] as String? ?? '-',
      minimumMonth: json['bulan_minimum'] as String? ?? '',
      maximumMonth: json['bulan_maksimum'] as String? ?? '',
      printedAt: json['tanggal_cetak'] as String? ?? '-',
      academicYear: json['tahun_pelajaran'] is Map
          ? WorshipAbsenceAcademicYear.fromJson(_map(json['tahun_pelajaran']))
          : null,
      filter: WorshipAbsenceFilter.fromJson(_map(json['filter'])),
      classes: _list(references['kelas'], WorshipAbsenceClass.fromJson),
      statuses: _list(references['status'], WorshipAbsenceStatus.fromJson),
      summary: WorshipAbsenceSummary.fromJson(_map(json['ringkasan'])),
      items: _list(json['items'], WorshipAbsenceRecapItem.fromJson),
      pagination: WorshipAbsencePagination.fromJson(_map(json['paginasi'])),
    );
  }

  final bool privateMode;
  final String privacyMessage;
  final String month;
  final String monthLabel;
  final String minimumMonth;
  final String maximumMonth;
  final String printedAt;
  final WorshipAbsenceAcademicYear? academicYear;
  final WorshipAbsenceFilter filter;
  final List<WorshipAbsenceClass> classes;
  final List<WorshipAbsenceStatus> statuses;
  final WorshipAbsenceSummary summary;
  final List<WorshipAbsenceRecapItem> items;
  final WorshipAbsencePagination pagination;

  WorshipAbsenceRecapPage append(WorshipAbsenceRecapPage next) =>
      WorshipAbsenceRecapPage(
        privateMode: next.privateMode,
        privacyMessage: next.privacyMessage,
        month: next.month,
        monthLabel: next.monthLabel,
        minimumMonth: next.minimumMonth,
        maximumMonth: next.maximumMonth,
        printedAt: next.printedAt,
        academicYear: next.academicYear,
        filter: next.filter,
        classes: next.classes,
        statuses: next.statuses,
        summary: next.summary,
        items: [...items, ...next.items],
        pagination: next.pagination,
      );
}

class WorshipAbsenceAcademicYear {
  const WorshipAbsenceAcademicYear({required this.id, required this.name});
  factory WorshipAbsenceAcademicYear.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class WorshipAbsenceFilter {
  const WorshipAbsenceFilter({
    required this.status,
    required this.query,
    this.classId,
  });
  factory WorshipAbsenceFilter.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceFilter(
        classId: _nullableInteger(json['kelas_id']),
        status: json['status'] as String? ?? 'semua',
        query: json['cari'] as String? ?? '',
      );
  final int? classId;
  final String status;
  final String query;
}

class WorshipAbsenceClass {
  const WorshipAbsenceClass({required this.id, required this.name});
  factory WorshipAbsenceClass.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class WorshipAbsenceStatus {
  const WorshipAbsenceStatus({required this.code, required this.label});
  factory WorshipAbsenceStatus.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceStatus(
        code: json['kode'] as String? ?? 'semua',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class WorshipAbsenceSummary {
  const WorshipAbsenceSummary({
    required this.periods,
    required this.students,
    required this.active,
    required this.needsConfirmation,
    required this.completed,
  });
  factory WorshipAbsenceSummary.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceSummary(
        periods: _integer(json['periode']),
        students: _integer(json['siswi']),
        active: _integer(json['aktif']),
        needsConfirmation: _integer(json['perlu_konfirmasi']),
        completed: _integer(json['selesai']),
      );
  final int periods;
  final int students;
  final int active;
  final int needsConfirmation;
  final int completed;
}

class WorshipAbsenceRecapItem {
  const WorshipAbsenceRecapItem({
    required this.id,
    required this.student,
    required this.schoolClass,
    required this.startDateLabel,
    required this.endDateLabel,
    required this.durationDays,
    required this.monthlyScans,
    required this.monthlyConfirmations,
    required this.status,
    required this.statusLabel,
    this.lastConfirmation,
    this.completionMethod,
  });
  factory WorshipAbsenceRecapItem.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceRecapItem(
        id: _integer(json['id']),
        student: WorshipAbsenceStudent.fromJson(_map(json['siswa'])),
        schoolClass: WorshipAbsenceClass.fromJson(_map(json['kelas'])),
        startDateLabel: json['tanggal_mulai_label'] as String? ?? '-',
        endDateLabel: json['tanggal_selesai_label'] as String? ?? 'Sekarang',
        durationDays: _integer(json['durasi_hari']),
        monthlyScans: _integer(json['presensi_bulan']),
        monthlyConfirmations: _integer(json['konfirmasi_bulan']),
        lastConfirmation: json['konfirmasi_terakhir'] is Map
            ? WorshipAbsenceLastConfirmation.fromJson(
                _map(json['konfirmasi_terakhir']),
              )
            : null,
        status: json['status'] as String? ?? '-',
        statusLabel: json['status_label'] as String? ?? '-',
        completionMethod: json['cara_selesai_label'] as String?,
      );
  final int id;
  final WorshipAbsenceStudent student;
  final WorshipAbsenceClass schoolClass;
  final String startDateLabel;
  final String endDateLabel;
  final int durationDays;
  final int monthlyScans;
  final int monthlyConfirmations;
  final WorshipAbsenceLastConfirmation? lastConfirmation;
  final String status;
  final String statusLabel;
  final String? completionMethod;
}

class WorshipAbsenceStudent {
  const WorshipAbsenceStudent({
    required this.id,
    required this.name,
    this.nisn,
    this.photoUrl,
  });
  factory WorshipAbsenceStudent.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nisn: json['nisn'] as String?,
        photoUrl: json['foto_url'] as String?,
      );
  final int id;
  final String name;
  final String? nisn;
  final String? photoUrl;
  String get initials => name
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .take(2)
      .map((part) => part[0])
      .join()
      .toUpperCase();
}

class WorshipAbsenceLastConfirmation {
  const WorshipAbsenceLastConfirmation({
    required this.dateLabel,
    required this.resultLabel,
    this.confirmedBy,
  });
  factory WorshipAbsenceLastConfirmation.fromJson(Map<String, dynamic> json) =>
      WorshipAbsenceLastConfirmation(
        dateLabel: json['tanggal_label'] as String? ?? '-',
        resultLabel: json['hasil_label'] as String? ?? '-',
        confirmedBy: json['oleh'] as String?,
      );
  final String dateLabel;
  final String resultLabel;
  final String? confirmedBy;
}

class WorshipAbsencePagination {
  const WorshipAbsencePagination({
    required this.page,
    required this.lastPage,
    required this.total,
    required this.hasNextPage,
  });
  factory WorshipAbsencePagination.fromJson(Map<String, dynamic> json) =>
      WorshipAbsencePagination(
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

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => convert(Map<String, dynamic>.from(item)))
          .toList()
    : <T>[];

int _integer(Object? value) => value is num ? value.toInt() : 0;
int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
