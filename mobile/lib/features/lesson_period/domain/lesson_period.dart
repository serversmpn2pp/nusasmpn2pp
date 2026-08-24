class LessonPeriodCatalog {
  const LessonPeriodCatalog({
    required this.items,
    required this.counts,
    required this.days,
    required this.types,
    required this.selectedDay,
    required this.status,
  });

  factory LessonPeriodCatalog.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    return LessonPeriodCatalog(
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((item) => LessonPeriod.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
      counts: LessonPeriodCounts.fromJson(_map(json['ringkasan']) ?? const {}),
      days: _codeLabels(json['hari']),
      types: _codeLabels(json['jenis']),
      selectedDay: filter['hari'] as String? ?? 'semua',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<LessonPeriod> items;
  final LessonPeriodCounts counts;
  final List<CodeLabel> days;
  final List<CodeLabel> types;
  final String selectedDay;
  final String status;

  LessonPeriodCatalog copyWithItems(List<LessonPeriod> value) =>
      LessonPeriodCatalog(
        items: value,
        counts: counts,
        days: days,
        types: types,
        selectedDay: selectedDay,
        status: status,
      );
}

class LessonPeriodCounts {
  const LessonPeriodCounts({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory LessonPeriodCounts.fromJson(Map<String, dynamic> json) =>
      LessonPeriodCounts(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class CodeLabel {
  const CodeLabel({required this.code, required this.label});

  factory CodeLabel.fromJson(Map<String, dynamic> json) => CodeLabel(
    code: json['kode'] as String? ?? '',
    label: json['label'] as String? ?? '-',
  );

  final String code;
  final String label;
}

class LessonPeriod {
  const LessonPeriod({
    required this.id,
    required this.day,
    required this.dayLabel,
    required this.number,
    required this.startTime,
    required this.endTime,
    required this.type,
    required this.typeLabel,
    required this.active,
    required this.activeScheduleCount,
    this.label,
    this.notes,
  });

  factory LessonPeriod.fromJson(Map<String, dynamic> json) => LessonPeriod(
    id: _integer(json['id']),
    day: json['hari'] as String? ?? '',
    dayLabel: json['hari_label'] as String? ?? '-',
    number: _integer(json['nomor_jam']),
    label: json['label'] as String?,
    startTime: json['jam_mulai'] as String? ?? '-',
    endTime: json['jam_selesai'] as String? ?? '-',
    type: json['jenis'] as String? ?? 'pelajaran',
    typeLabel: json['jenis_label'] as String? ?? '-',
    active: json['aktif'] as bool? ?? false,
    notes: json['keterangan'] as String?,
    activeScheduleCount: _integer(json['jumlah_jadwal_aktif']),
  );

  final int id;
  final String day;
  final String dayLabel;
  final int number;
  final String? label;
  final String startTime;
  final String endTime;
  final String type;
  final String typeLabel;
  final bool active;
  final String? notes;
  final int activeScheduleCount;

  String get displayLabel =>
      label?.trim().isNotEmpty == true ? label! : 'Jam ke-$number';
  String get timeLabel => '$startTime - $endTime';
}

List<CodeLabel> _codeLabels(Object? value) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => CodeLabel.fromJson(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic>? _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

int _integer(Object? value) => value is num ? value.toInt() : 0;
