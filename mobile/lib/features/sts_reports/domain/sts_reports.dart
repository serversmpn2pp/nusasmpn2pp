enum StsReportKind { report, ledger }

enum StsLedgerMode { classGroup, grade }

class StsReportFilter {
  const StsReportFilter({
    this.activityId,
    this.classId,
    this.grade,
    this.mode = StsLedgerMode.classGroup,
  });

  factory StsReportFilter.fromJson(Map<String, dynamic> json) =>
      StsReportFilter(
        activityId: _integerOrNull(json['kegiatan_id']),
        classId: _integerOrNull(json['kelas_id']),
        grade: _integerOrNull(json['tingkat']),
        mode: json['mode'] == 'tingkat'
            ? StsLedgerMode.grade
            : StsLedgerMode.classGroup,
      );

  final int? activityId, classId, grade;
  final StsLedgerMode mode;

  Map<String, dynamic> query(StsReportKind kind) => {
    'kegiatan_id': ?activityId,
    if (kind == StsReportKind.report || mode == StsLedgerMode.classGroup)
      'kelas_id': ?classId,
    if (kind == StsReportKind.ledger) ...{
      'mode': mode == StsLedgerMode.grade ? 'tingkat' : 'kelas',
      if (mode == StsLedgerMode.grade) 'tingkat': ?grade,
    },
  };
}

class StsReportOption {
  StsReportOption.fromJson(Map<String, dynamic> json)
    : id = _integer(json['id']),
      label = json['label'] as String;
  final int id;
  final String label;
}

class StsSubject {
  StsSubject.fromJson(Map<String, dynamic> json)
    : id = _integer(json['id']),
      name = json['nama'] as String;
  final int id;
  final String name;
}

class StsReportPage {
  StsReportPage.fromJson(Map<String, dynamic> json)
    : filter = StsReportFilter.fromJson(_map(json['filter'])),
      activities = _options(_map(json['opsi'])['kegiatan']),
      classes = _options(_map(json['opsi'])['kelas']),
      grades = _options(_map(json['opsi'])['tingkat']),
      context = json['konteks'] == null
          ? null
          : StsReportContext.fromJson(_map(json['konteks']));

  final StsReportFilter filter;
  final List<StsReportOption> activities, classes, grades;
  final StsReportContext? context;
}

class StsReportContext {
  StsReportContext.fromJson(Map<String, dynamic> json)
    : activityName = _map(json['kegiatan'])['nama'] as String,
      academicYear = _map(json['kegiatan'])['tahun_pelajaran'] as String?,
      semester = _map(json['kegiatan'])['semester'] as String?,
      className = _map(json['kelas'])['nama'] as String?,
      grade = _integerOrNull(json['tingkat'] ?? _map(json['kelas'])['tingkat']),
      homeroomTeacher = _map(json['wali_kelas'])['nama'] as String?,
      period = json['periode'] == null
          ? null
          : StsReportPeriod.fromJson(_map(json['periode'])),
      temporaryRanking = json['ranking_sementara'] == true,
      completeGradeScope = json['cakupan_tingkat_lengkap'] as bool?,
      divisor = _integer(json['jumlah_mapel_pembagi']),
      summary = StsReportSummary.fromJson(_map(json['ringkasan'])),
      subjects = _items(json['mapel']).map(StsSubject.fromJson).toList(),
      students = _items(json['siswa']).map(StsReportStudent.fromJson).toList(),
      subjectStats = _items(json['statistik_mapel'])
          .map(StsSubjectStats.fromJson)
          .toList(),
      classStats = _items(json['statistik_kelas'])
          .map(StsClassStats.fromJson)
          .toList();

  final String activityName;
  final String? academicYear, semester, className, homeroomTeacher;
  final int? grade;
  final StsReportPeriod? period;
  final bool temporaryRanking;
  final bool? completeGradeScope;
  final int divisor;
  final StsReportSummary summary;
  final List<StsSubject> subjects;
  final List<StsReportStudent> students;
  final List<StsSubjectStats> subjectStats;
  final List<StsClassStats> classStats;

  String subjectName(int id) =>
      subjects.where((subject) => subject.id == id).firstOrNull?.name ??
      'Mata pelajaran';
}

class StsReportPeriod {
  StsReportPeriod.fromJson(Map<String, dynamic> json)
    : start = json['awal'] as String,
      end = json['akhir'] as String,
      reportDate = json['tanggal_rapor'] as String,
      saved = json['tersimpan'] == true,
      ended = json['sudah_berakhir'] == true;
  final String start, end, reportDate;
  final bool saved, ended;
}

class StsReportSummary {
  StsReportSummary.fromJson(Map<String, dynamic> json)
    : studentCount = _integer(json['jumlah_siswa']),
      completeCount = _integer(json['nilai_lengkap']),
      readyCount = _integer(json['siap_cetak']),
      attendanceReviewed = _integer(json['kehadiran_diperiksa']),
      rankedCount = _integer(json['masuk_ranking']),
      unrankedCount = _integer(json['belum_masuk_ranking']),
      finalCount = _integer(json['lengkap_final']),
      average = _number(json['rata_kelas'] ?? json['rata_tingkat']),
      highest = _number(json['rata_tertinggi']),
      lowest = _number(json['rata_terendah']);
  final int studentCount, completeCount, readyCount, attendanceReviewed;
  final int rankedCount, unrankedCount, finalCount;
  final double? average, highest, lowest;
}

class StsReportGrade {
  StsReportGrade.fromJson(Map<String, dynamic> json)
    : subjectId = _integer(json['mapel_id']),
      value = _number(json['nilai']),
      description = json['keterangan'] as String? ?? '',
      draft = json['draf'] == true,
      excluded = json['dikecualikan'] == true,
      exclusionReason = json['alasan'] as String?;
  final int subjectId;
  final double? value;
  final String description;
  final bool draft, excluded;
  final String? exclusionReason;
}

class StsReportStudent {
  StsReportStudent.fromJson(Map<String, dynamic> json)
    : membershipId = _integer(json['id']),
      name = json['nama'] as String,
      attendanceNumber = _integerOrNull(json['nomor_absen']),
      className = _map(json['kelas'])['nama'] as String? ?? '',
      grades = _items(json['nilai']).map(StsReportGrade.fromJson).toList(),
      complete = json['nilai_lengkap'] == true,
      completeFinal = json['nilai_final_lengkap'] == true,
      gradeCount = _integer(
        json['jumlah_bernilai'] ?? json['jumlah_nilai_tersedia'],
      ),
      finalCount = _integer(json['jumlah_nilai_final']),
      draftCount = _integer(json['jumlah_nilai_draf']),
      total = _number(json['jumlah'] ?? json['jumlah_leger']),
      average = _number(json['rata'] ?? json['rata_leger']),
      rank = _integerOrNull(json['ranking']),
      rankingStatus = json['status_ranking'] as String? ?? '',
      attendanceReviewed = json['diperiksa'] == true,
      sourceChanged = json['sumber_berubah'] == true,
      ready = json['siap_cetak'] == true,
      behaviorReady = json['lampiran_perilaku_siap'] == true,
      sick = _integer(_map(json['kehadiran'])['sakit']),
      excused = _integer(_map(json['kehadiran'])['izin']),
      absent = _integer(_map(json['kehadiran'])['alfa']),
      lateCount = _integer(_map(json['keterlambatan'])['jumlah']),
      lateMinutes = _integer(_map(json['keterlambatan'])['total_menit']),
      attendanceCorrection = json['catatan_koreksi'] as String?;

  final int membershipId;
  final String name, className, rankingStatus;
  final int? attendanceNumber, rank;
  final List<StsReportGrade> grades;
  final bool complete, completeFinal, attendanceReviewed, sourceChanged;
  final bool ready, behaviorReady;
  final int gradeCount, finalCount, draftCount, sick, excused, absent;
  final int lateCount, lateMinutes;
  final double? total, average;
  final String? attendanceCorrection;
}

class StsSubjectStats {
  StsSubjectStats.fromJson(Map<String, dynamic> json)
    : name = _map(json['mapel'])['nama'] as String,
      average = _number(json['rata']),
      highest = _number(json['tertinggi_nilai']),
      lowest = _number(json['terendah_nilai']),
      count = _integer(json['jumlah_nilai']),
      finalCount = _integer(json['jumlah_final']),
      draftCount = _integer(json['jumlah_draf']),
      highestAverage = json['tertinggi'] == true;
  final String name;
  final double? average, highest, lowest;
  final int count, finalCount, draftCount;
  final bool highestAverage;
}

class StsClassStats {
  StsClassStats.fromJson(Map<String, dynamic> json)
    : name = _map(json['kelas'])['nama'] as String,
      rank = _integerOrNull(json['ranking']),
      average = _number(json['rata']),
      count = _integer(json['jumlah_siswa']),
      rankedCount = _integer(json['masuk_ranking']);
  final String name;
  final int? rank;
  final double? average;
  final int count, rankedCount;
}

String stsNumber(num? value) {
  if (value == null) return '—';
  return value
      .toStringAsFixed(2)
      .replaceFirst(RegExp(r'\.?0+$'), '')
      .replaceAll('.', ',');
}

String stsDate(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value;
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};
Iterable<Map<String, dynamic>> _items(Object? value) => value is List
    ? value.whereType<Map>().map((item) => Map<String, dynamic>.from(item))
    : const [];
List<StsReportOption> _options(Object? value) =>
    _items(value).map(StsReportOption.fromJson).toList();
int _integer(Object? value) => _integerOrNull(value) ?? 0;
int? _integerOrNull(Object? value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '');
double? _number(Object? value) =>
    value is num ? value.toDouble() : double.tryParse(value?.toString() ?? '');
