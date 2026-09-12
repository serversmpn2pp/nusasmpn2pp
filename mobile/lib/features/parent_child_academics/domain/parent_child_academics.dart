import 'package:nusa/features/my_grades/domain/my_grades.dart';

enum ParentChildAcademicsTab {
  schedule,
  grades;

  String get apiValue => this == schedule ? 'jadwal' : 'nilai';

  static ParentChildAcademicsTab fromApi(Object? value) =>
      value == 'nilai' ? grades : schedule;
}

class ParentChildAcademicsPage {
  const ParentChildAcademicsPage({
    required this.tab,
    required this.selectedStudentId,
    required this.children,
    required this.schedule,
    required this.grades,
    required this.gradeNotice,
  });

  factory ParentChildAcademicsPage.fromJson(Map<String, dynamic> json) =>
      ParentChildAcademicsPage(
        tab: ParentChildAcademicsTab.fromApi(json['tab']),
        selectedStudentId: _nullableInteger(json['siswa_id']),
        children: _list(json['pilihan_siswa'], MyGradesStudent.fromJson),
        schedule: ChildSchedule.fromJson(_map(json['jadwal'])),
        grades: MyGradesPage.fromJson(_map(json['nilai'])),
        gradeNotice:
            json['catatan_nilai'] as String? ??
            'Nilai terkunci menunggu survei pembelajaran anak.',
      );

  final ParentChildAcademicsTab tab;
  final int? selectedStudentId;
  final List<MyGradesStudent> children;
  final ChildSchedule schedule;
  final MyGradesPage grades;
  final String gradeNotice;
}

class ChildSchedule {
  const ChildSchedule({
    required this.today,
    required this.summary,
    required this.days,
    this.emptyMessage,
  });

  factory ChildSchedule.fromJson(Map<String, dynamic> json) => ChildSchedule(
    today: json['hari_hari_ini'] as String? ?? 'senin',
    summary: ChildScheduleSummary.fromJson(_map(json['ringkasan'])),
    days: _list(json['hari'], ChildScheduleDay.fromJson),
    emptyMessage: json['pesan_kosong'] as String?,
  );

  final String today;
  final ChildScheduleSummary summary;
  final List<ChildScheduleDay> days;
  final String? emptyMessage;
}

class ChildScheduleSummary {
  const ChildScheduleSummary({
    required this.scheduledPeriods,
    required this.subjects,
    this.schoolClass,
  });

  factory ChildScheduleSummary.fromJson(Map<String, dynamic> json) =>
      ChildScheduleSummary(
        schoolClass: json['kelas'] as String?,
        scheduledPeriods: _integer(json['jam_terjadwal']),
        subjects: _integer(json['mata_pelajaran']),
      );

  final String? schoolClass;
  final int scheduledPeriods;
  final int subjects;
}

class ChildScheduleDay {
  const ChildScheduleDay({
    required this.code,
    required this.label,
    required this.isToday,
    required this.items,
  });

  factory ChildScheduleDay.fromJson(Map<String, dynamic> json) =>
      ChildScheduleDay(
        code: json['kode'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
        isToday: json['hari_ini'] as bool? ?? false,
        items: _list(json['items'], ChildScheduleItem.fromJson),
      );

  final String code;
  final String label;
  final bool isToday;
  final List<ChildScheduleItem> items;
}

class ChildScheduleItem {
  const ChildScheduleItem({
    required this.id,
    required this.periodNumber,
    required this.startTime,
    required this.endTime,
    required this.type,
    required this.label,
    required this.current,
    required this.scheduled,
    this.subjectName,
    this.teacherName,
  });

  factory ChildScheduleItem.fromJson(Map<String, dynamic> json) {
    final subject = _map(json['mata_pelajaran']);
    final teacher = _map(json['guru']);
    return ChildScheduleItem(
      id: _integer(json['id']),
      periodNumber: _integer(json['nomor_jam']),
      startTime: json['jam_mulai'] as String? ?? '-',
      endTime: json['jam_selesai'] as String? ?? '-',
      type: json['jenis'] as String? ?? 'pelajaran',
      label: json['label'] as String? ?? '-',
      current: json['sedang_berlangsung'] as bool? ?? false,
      scheduled: json['terjadwal'] as bool? ?? false,
      subjectName: subject['nama'] as String?,
      teacherName: teacher['nama'] as String?,
    );
  }

  final int id;
  final int periodNumber;
  final String startTime;
  final String endTime;
  final String type;
  final String label;
  final bool current;
  final bool scheduled;
  final String? subjectName;
  final String? teacherName;

  bool get isLesson => type == 'pelajaran';
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);
