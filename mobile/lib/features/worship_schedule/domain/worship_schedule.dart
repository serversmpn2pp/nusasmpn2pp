class WorshipSchedulePage {
  const WorshipSchedulePage({
    required this.items,
    required this.summary,
    required this.academicYears,
    required this.activities,
    required this.days,
    required this.selectedAcademicYearId,
    required this.selectedActivityId,
  });

  factory WorshipSchedulePage.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    final filter = _map(json['filter']);
    return WorshipSchedulePage(
      items: _list(
        references: json['items'],
        fromJson: WorshipSchedule.fromJson,
      ),
      summary: WorshipScheduleSummary.fromJson(_map(json['ringkasan'])),
      academicYears: _list(
        references: references['tahun_pelajaran'],
        fromJson: AcademicYearOption.fromJson,
      ),
      activities: _list(
        references: references['kegiatan_ibadah'],
        fromJson: WorshipActivityOption.fromJson,
      ),
      days: _list(
        references: references['hari'],
        fromJson: WorshipDay.fromJson,
      ),
      selectedAcademicYearId: _integer(filter['tahun_pelajaran_id']),
      selectedActivityId: _integer(filter['kegiatan_ibadah_id']),
    );
  }

  final List<WorshipSchedule> items;
  final WorshipScheduleSummary summary;
  final List<AcademicYearOption> academicYears;
  final List<WorshipActivityOption> activities;
  final List<WorshipDay> days;
  final int selectedAcademicYearId;
  final int selectedActivityId;

  WorshipSchedule? scheduleFor(String day) {
    for (final item in items) {
      if (item.day == day) return item;
    }
    return null;
  }

  WorshipActivityOption? get selectedActivity {
    for (final item in activities) {
      if (item.id == selectedActivityId) return item;
    }
    return null;
  }

  AcademicYearOption? get selectedAcademicYear {
    for (final item in academicYears) {
      if (item.id == selectedAcademicYearId) return item;
    }
    return null;
  }
}

class WorshipSchedule {
  const WorshipSchedule({
    required this.id,
    required this.activityId,
    required this.academicYearId,
    required this.day,
    required this.dayLabel,
    required this.dayOrder,
    required this.scanStart,
    required this.eventTime,
    required this.scanEnd,
    required this.active,
    this.notes,
  });

  factory WorshipSchedule.fromJson(Map<String, dynamic> json) =>
      WorshipSchedule(
        id: _integer(json['id']),
        activityId: _integer(json['kegiatan_ibadah_id']),
        academicYearId: _integer(json['tahun_pelajaran_id']),
        day: json['hari'] as String? ?? '',
        dayLabel: json['label_hari'] as String? ?? '-',
        dayOrder: _integer(json['urutan_hari']),
        scanStart: json['jam_scan_mulai'] as String? ?? '-',
        eventTime: json['jam_pelaksanaan'] as String? ?? '-',
        scanEnd: json['jam_scan_selesai'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
        notes: json['keterangan'] as String?,
      );

  final int id;
  final int activityId;
  final int academicYearId;
  final String day;
  final String dayLabel;
  final int dayOrder;
  final String scanStart;
  final String eventTime;
  final String scanEnd;
  final bool active;
  final String? notes;
}

class WorshipScheduleSummary {
  const WorshipScheduleSummary({
    required this.dayCount,
    required this.configured,
    required this.active,
  });

  factory WorshipScheduleSummary.fromJson(Map<String, dynamic> json) =>
      WorshipScheduleSummary(
        dayCount: _integer(json['jumlah_hari']),
        configured: _integer(json['sudah_diatur']),
        active: _integer(json['aktif']),
      );

  final int dayCount;
  final int configured;
  final int active;
}

class AcademicYearOption {
  const AcademicYearOption({
    required this.id,
    required this.name,
    required this.active,
  });

  factory AcademicYearOption.fromJson(Map<String, dynamic> json) =>
      AcademicYearOption(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class WorshipActivityOption {
  const WorshipActivityOption({
    required this.id,
    required this.code,
    required this.name,
    required this.active,
  });

  factory WorshipActivityOption.fromJson(Map<String, dynamic> json) =>
      WorshipActivityOption(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String code;
  final String name;
  final bool active;
}

class WorshipDay {
  const WorshipDay({
    required this.code,
    required this.label,
    required this.order,
  });

  factory WorshipDay.fromJson(Map<String, dynamic> json) => WorshipDay(
    code: json['kode'] as String? ?? '',
    label: json['label'] as String? ?? '-',
    order: _integer(json['urutan']),
  );

  final String code;
  final String label;
  final int order;
}

class WorshipScheduleFormValue {
  const WorshipScheduleFormValue({
    required this.activityId,
    required this.academicYearId,
    required this.days,
    required this.scanStart,
    required this.eventTime,
    required this.scanEnd,
    required this.active,
    this.notes,
  });

  final int activityId;
  final int academicYearId;
  final List<String> days;
  final String scanStart;
  final String eventTime;
  final String scanEnd;
  final bool active;
  final String? notes;
}

List<T> _list<T>({
  required Object? references,
  required T Function(Map<String, dynamic>) fromJson,
}) => (references as List<dynamic>? ?? const [])
    .whereType<Map>()
    .map((item) => fromJson(Map<String, dynamic>.from(item)))
    .toList(growable: false);

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value) => value is num ? value.toInt() : 0;
