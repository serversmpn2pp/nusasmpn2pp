class WorshipMonthlySummaryPage {
  const WorshipMonthlySummaryPage({
    required this.available,
    required this.month,
    required this.monthLabel,
    required this.minimumMonth,
    required this.maximumMonth,
    required this.activities,
    required this.classes,
    required this.activityDates,
    required this.summary,
    required this.classSummaries,
    required this.students,
    required this.calculationNote,
    required this.privacyMessage,
    this.academicYear,
    this.selectedActivity,
    this.selectedClass,
  });

  factory WorshipMonthlySummaryPage.fromJson(Map<String, dynamic> json) {
    final references = _map(json['referensi']);
    return WorshipMonthlySummaryPage(
      available: json['tersedia'] as bool? ?? false,
      month: json['bulan'] as String? ?? '',
      monthLabel: json['bulan_label'] as String? ?? '-',
      minimumMonth: json['bulan_minimum'] as String?,
      maximumMonth: json['bulan_maksimum'] as String? ?? '',
      academicYear: json['tahun_pelajaran'] is Map<String, dynamic>
          ? WorshipMonthlyAcademicYear.fromJson(_map(json['tahun_pelajaran']))
          : null,
      selectedActivity: json['kegiatan_dipilih'] is Map<String, dynamic>
          ? WorshipMonthlyActivity.fromJson(_map(json['kegiatan_dipilih']))
          : null,
      selectedClass: json['kelas_dipilih'] is Map<String, dynamic>
          ? WorshipMonthlyClass.fromJson(_map(json['kelas_dipilih']))
          : null,
      activities: _list(
        references['kegiatan'],
        WorshipMonthlyActivity.fromJson,
      ),
      classes: _list(references['kelas'], WorshipMonthlyClass.fromJson),
      activityDates: _list(
        json['tanggal_kegiatan'],
        WorshipMonthlyActivityDate.fromJson,
      ),
      summary: WorshipMonthlySummary.fromJson(_map(json['ringkasan'])),
      classSummaries: _list(
        json['ringkasan_kelas'],
        WorshipMonthlyClassSummary.fromJson,
      ),
      students: _list(json['items'], WorshipMonthlyStudentSummary.fromJson),
      calculationNote: json['catatan_perhitungan'] as String? ?? '',
      privacyMessage: json['pesan_privasi'] as String? ?? '',
    );
  }

  final bool available;
  final String month;
  final String monthLabel;
  final String? minimumMonth;
  final String maximumMonth;
  final WorshipMonthlyAcademicYear? academicYear;
  final WorshipMonthlyActivity? selectedActivity;
  final WorshipMonthlyClass? selectedClass;
  final List<WorshipMonthlyActivity> activities;
  final List<WorshipMonthlyClass> classes;
  final List<WorshipMonthlyActivityDate> activityDates;
  final WorshipMonthlySummary summary;
  final List<WorshipMonthlyClassSummary> classSummaries;
  final List<WorshipMonthlyStudentSummary> students;
  final String calculationNote;
  final String privacyMessage;
}

class WorshipMonthlyAcademicYear {
  const WorshipMonthlyAcademicYear({required this.id, required this.name});

  factory WorshipMonthlyAcademicYear.fromJson(Map<String, dynamic> json) =>
      WorshipMonthlyAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class WorshipMonthlyActivity {
  const WorshipMonthlyActivity({
    required this.id,
    required this.name,
    required this.active,
    this.code,
  });

  factory WorshipMonthlyActivity.fromJson(Map<String, dynamic> json) =>
      WorshipMonthlyActivity(
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

class WorshipMonthlyClass {
  const WorshipMonthlyClass({
    required this.id,
    required this.name,
    required this.grade,
  });

  factory WorshipMonthlyClass.fromJson(Map<String, dynamic> json) =>
      WorshipMonthlyClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        grade: _integer(json['tingkat']),
      );

  final int id;
  final String name;
  final int grade;
}

class WorshipMonthlyActivityDate {
  const WorshipMonthlyActivityDate({required this.date, required this.label});

  factory WorshipMonthlyActivityDate.fromJson(Map<String, dynamic> json) =>
      WorshipMonthlyActivityDate(
        date: json['tanggal'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );

  final String date;
  final String label;
}

class WorshipMonthlySummary {
  const WorshipMonthlySummary({
    required this.classCount,
    required this.studentCount,
    required this.activityDays,
    required this.target,
    required this.recorded,
    required this.missing,
    required this.percentage,
  });

  factory WorshipMonthlySummary.fromJson(Map<String, dynamic> json) =>
      WorshipMonthlySummary(
        classCount: _integer(json['kelas']),
        studentCount: _integer(json['siswa']),
        activityDays: _integer(json['hari_kegiatan']),
        target: _integer(json['target']),
        recorded: _integer(json['tercatat']),
        missing: _integer(json['belum']),
        percentage: _decimal(json['persentase']),
      );

  final int classCount;
  final int studentCount;
  final int activityDays;
  final int target;
  final int recorded;
  final int missing;
  final double percentage;
}

class WorshipMonthlyClassSummary {
  const WorshipMonthlyClassSummary({
    required this.schoolClass,
    required this.studentCount,
    required this.target,
    required this.recorded,
    required this.missing,
    required this.percentage,
  });

  factory WorshipMonthlyClassSummary.fromJson(Map<String, dynamic> json) =>
      WorshipMonthlyClassSummary(
        schoolClass: WorshipMonthlyClass.fromJson(_map(json['kelas'])),
        studentCount: _integer(json['siswa']),
        target: _integer(json['target']),
        recorded: _integer(json['tercatat']),
        missing: _integer(json['belum']),
        percentage: _decimal(json['persentase']),
      );

  final WorshipMonthlyClass schoolClass;
  final int studentCount;
  final int target;
  final int recorded;
  final int missing;
  final double percentage;
}

class WorshipMonthlyStudentSummary {
  const WorshipMonthlyStudentSummary({
    required this.memberId,
    required this.student,
    required this.target,
    required this.recorded,
    required this.missing,
    required this.manual,
    required this.percentage,
    this.rollNumber,
    this.lastDate,
    this.lastDateLabel,
  });

  factory WorshipMonthlyStudentSummary.fromJson(Map<String, dynamic> json) =>
      WorshipMonthlyStudentSummary(
        memberId: _integer(json['anggota_kelas_id']),
        rollNumber: _nullableInteger(json['nomor_absen']),
        student: WorshipMonthlyStudent.fromJson(_map(json['siswa'])),
        target: _integer(json['target']),
        recorded: _integer(json['tercatat']),
        missing: _integer(json['belum']),
        manual: _integer(json['manual']),
        lastDate: json['terakhir'] as String?,
        lastDateLabel: json['terakhir_label'] as String?,
        percentage: _decimal(json['persentase']),
      );

  final int memberId;
  final int? rollNumber;
  final WorshipMonthlyStudent student;
  final int target;
  final int recorded;
  final int missing;
  final int manual;
  final String? lastDate;
  final String? lastDateLabel;
  final double percentage;
}

class WorshipMonthlyStudent {
  const WorshipMonthlyStudent({
    required this.id,
    required this.name,
    this.nis,
    this.nisn,
    this.photoUrl,
  });

  factory WorshipMonthlyStudent.fromJson(Map<String, dynamic> json) =>
      WorshipMonthlyStudent(
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

  String get initials => name
      .trim()
      .split(RegExp(r'\s+'))
      .where((word) => word.isNotEmpty)
      .take(2)
      .map((word) => word[0])
      .join()
      .toUpperCase();
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => convert(Map<String, dynamic>.from(item)))
          .toList()
    : <T>[];

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;

double _decimal(Object? value) => value is num ? value.toDouble() : 0;
