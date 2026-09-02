class StudentPointRecapPage {
  const StudentPointRecapPage({
    required this.items,
    required this.summary,
    required this.classSummaries,
    required this.options,
    required this.filter,
    required this.pagination,
    required this.access,
  });

  factory StudentPointRecapPage.fromJson(Map<String, dynamic> json) =>
      StudentPointRecapPage(
        items: _list(json['items'], StudentPointRecapItem.fromJson),
        summary: StudentPointRecapSummary.fromJson(_map(json['ringkasan'])),
        classSummaries: _list(
          json['ringkasan_kelas'],
          StudentPointClassSummary.fromJson,
        ),
        options: StudentPointRecapOptions.fromJson(_map(json['pilihan'])),
        filter: StudentPointRecapFilter.fromJson(_map(json['filter'])),
        pagination: StudentPointPagination.fromJson(_map(json['paginasi'])),
        access: StudentPointAccess.fromJson(_map(json['hak_akses'])),
      );

  final List<StudentPointRecapItem> items;
  final StudentPointRecapSummary summary;
  final List<StudentPointClassSummary> classSummaries;
  final StudentPointRecapOptions options;
  final StudentPointRecapFilter filter;
  final StudentPointPagination pagination;
  final StudentPointAccess access;

  StudentPointRecapPage append(StudentPointRecapPage next) =>
      StudentPointRecapPage(
        items: [...items, ...next.items],
        summary: next.summary,
        classSummaries: next.classSummaries,
        options: next.options,
        filter: next.filter,
        pagination: next.pagination,
        access: next.access,
      );
}

class StudentPointRecapSummary {
  const StudentPointRecapSummary({
    required this.students,
    required this.withPoints,
    required this.nearSanction,
    required this.pendingReports,
    required this.activeSanctions,
  });
  factory StudentPointRecapSummary.fromJson(Map<String, dynamic> json) =>
      StudentPointRecapSummary(
        students: _integer(json['total_siswa']),
        withPoints: _integer(json['siswa_berpoin']),
        nearSanction: _integer(json['mendekati_sanksi']),
        pendingReports: _integer(json['laporan_menunggu']),
        activeSanctions: _integer(json['sanksi_aktif']),
      );
  final int students;
  final int withPoints;
  final int nearSanction;
  final int pendingReports;
  final int activeSanctions;
}

class StudentPointClassSummary {
  const StudentPointClassSummary({
    required this.schoolClass,
    required this.students,
    required this.withPoints,
    required this.totalPoints,
    required this.pending,
    required this.activeSanctions,
  });
  factory StudentPointClassSummary.fromJson(Map<String, dynamic> json) =>
      StudentPointClassSummary(
        schoolClass: PointIdName.fromJson(_map(json['kelas'])),
        students: _integer(json['jumlah_siswa']),
        withPoints: _integer(json['siswa_berpoin']),
        totalPoints: _integer(json['total_poin']),
        pending: _integer(json['menunggu']),
        activeSanctions: _integer(json['sanksi_aktif']),
      );
  final PointIdName schoolClass;
  final int students;
  final int withPoints;
  final int totalPoints;
  final int pending;
  final int activeSanctions;
}

class StudentPointRecapItem {
  const StudentPointRecapItem({
    required this.student,
    required this.totalPoints,
    required this.pendingReports,
    required this.activeSanctions,
    required this.indicator,
    this.schoolClass,
    this.homeroomTeacher,
  });
  factory StudentPointRecapItem.fromJson(Map<String, dynamic> json) =>
      StudentPointRecapItem(
        student: PointPerson.fromJson(_map(json['siswa'])),
        schoolClass: _nullable(json['kelas'], PointIdName.fromJson),
        homeroomTeacher: _nullable(json['guru_wali'], PointPerson.fromJson),
        totalPoints: _integer(json['total_poin']),
        pendingReports: _integer(json['laporan_menunggu']),
        activeSanctions: _integer(json['sanksi_aktif']),
        indicator: StudentPointIndicator.fromJson(_map(json['indikator'])),
      );
  final PointPerson student;
  final PointIdName? schoolClass;
  final PointPerson? homeroomTeacher;
  final int totalPoints;
  final int pendingReports;
  final int activeSanctions;
  final StudentPointIndicator indicator;
}

class StudentPointIndicator {
  const StudentPointIndicator({
    required this.code,
    required this.label,
    required this.percentage,
    this.distance,
    this.nextThreshold,
  });
  factory StudentPointIndicator.fromJson(Map<String, dynamic> json) =>
      StudentPointIndicator(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
        distance: _nullableInteger(json['jarak']),
        percentage: _integer(json['persentase']),
        nextThreshold: _nullable(
          json['ambang_berikutnya'],
          PointThreshold.fromJson,
        ),
      );
  final String code;
  final String label;
  final int? distance;
  final int percentage;
  final PointThreshold? nextThreshold;
}

class PointThreshold extends PointIdName {
  const PointThreshold({
    required super.id,
    required super.name,
    required this.points,
  });
  factory PointThreshold.fromJson(Map<String, dynamic> json) => PointThreshold(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    points: _integer(json['batas_poin']),
  );
  final int points;
}

class StudentPointRecapOptions {
  const StudentPointRecapOptions({
    required this.attentionStatuses,
    required this.academicYears,
    required this.classes,
  });
  factory StudentPointRecapOptions.fromJson(
    Map<String, dynamic> json,
  ) => StudentPointRecapOptions(
    attentionStatuses: _list(json['status_perhatian'], PointCodeLabel.fromJson),
    academicYears: _list(json['tahun_pelajaran'], PointAcademicYear.fromJson),
    classes: _list(json['kelas'], PointClass.fromJson),
  );
  final List<PointCodeLabel> attentionStatuses;
  final List<PointAcademicYear> academicYears;
  final List<PointClass> classes;
}

class StudentPointRecapFilter {
  const StudentPointRecapFilter({
    required this.query,
    required this.attentionStatus,
    this.academicYearId,
    this.classId,
  });
  factory StudentPointRecapFilter.fromJson(Map<String, dynamic> json) =>
      StudentPointRecapFilter(
        query: json['kata_kunci'] as String? ?? '',
        attentionStatus: json['status_perhatian'] as String? ?? 'semua',
        academicYearId: _nullableInteger(json['tahun_pelajaran_id']),
        classId: _nullableInteger(json['kelas_id']),
      );
  final String query;
  final String attentionStatus;
  final int? academicYearId;
  final int? classId;
}

class StudentPointPagination {
  const StudentPointPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });
  factory StudentPointPagination.fromJson(Map<String, dynamic> json) =>
      StudentPointPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int total;
  final bool hasNextPage;
}

class StudentPointAccess {
  const StudentPointAccess({
    required this.wideScope,
    required this.canManageAssistance,
  });
  factory StudentPointAccess.fromJson(Map<String, dynamic> json) =>
      StudentPointAccess(
        wideScope: json['cakupan_luas'] as bool? ?? false,
        canManageAssistance:
            json['dapat_kelola_pendampingan'] as bool? ?? false,
      );
  final bool wideScope;
  final bool canManageAssistance;
}

class StudentPointRecapDetail {
  const StudentPointRecapDetail({
    required this.student,
    required this.summary,
    required this.monthlyProgress,
    required this.transactions,
    required this.reports,
    required this.warnings,
    required this.assistances,
    required this.sanctions,
    required this.reductions,
    required this.lateArrivals,
    required this.academicYears,
    required this.access,
    this.academicYear,
  });
  factory StudentPointRecapDetail.fromJson(Map<String, dynamic> json) =>
      StudentPointRecapDetail(
        student: StudentPointRecapItem.fromJson(_map(json['siswa'])),
        academicYear: _nullable(json['tahun_pelajaran'], PointIdName.fromJson),
        summary: StudentPointDetailSummary.fromJson(_map(json['ringkasan'])),
        monthlyProgress: _list(
          json['perkembangan_bulanan'],
          PointMonthlyProgress.fromJson,
        ),
        transactions: _list(json['transaksi'], PointTransaction.fromJson),
        reports: _list(json['laporan'], PointReport.fromJson),
        warnings: _list(json['peringatan'], PointWarning.fromJson),
        assistances: _list(json['pendampingan'], PointAssistance.fromJson),
        sanctions: _list(json['sanksi'], PointSanction.fromJson),
        reductions: _list(json['pengurangan'], PointReduction.fromJson),
        lateArrivals: _list(json['keterlambatan'], PointLateArrival.fromJson),
        academicYears: _list(json['pilihan_tahun'], PointAcademicYear.fromJson),
        access: StudentPointAccess.fromJson(_map(json['hak_akses'])),
      );
  final StudentPointRecapItem student;
  final PointIdName? academicYear;
  final StudentPointDetailSummary summary;
  final List<PointMonthlyProgress> monthlyProgress;
  final List<PointTransaction> transactions;
  final List<PointReport> reports;
  final List<PointWarning> warnings;
  final List<PointAssistance> assistances;
  final List<PointSanction> sanctions;
  final List<PointReduction> reductions;
  final List<PointLateArrival> lateArrivals;
  final List<PointAcademicYear> academicYears;
  final StudentPointAccess access;
}

class StudentPointDetailSummary {
  const StudentPointDetailSummary({
    required this.totalPoints,
    required this.activeWarnings,
    required this.importantWarnings,
    required this.pendingReports,
    required this.pendingPoints,
    required this.activeSanctions,
    required this.lateCount,
    required this.lateMinutes,
    required this.indicator,
  });
  factory StudentPointDetailSummary.fromJson(Map<String, dynamic> json) {
    final late = _map(json['keterlambatan']);
    return StudentPointDetailSummary(
      totalPoints: _integer(json['total_poin']),
      activeWarnings: _integer(json['peringatan_aktif']),
      importantWarnings: _integer(json['peringatan_penting']),
      pendingReports: _integer(json['laporan_menunggu']),
      pendingPoints: _integer(json['poin_dalam_proses']),
      activeSanctions: _integer(json['sanksi_aktif']),
      lateCount: _integer(late['jumlah']),
      lateMinutes: _integer(late['total_menit']),
      indicator: StudentPointIndicator.fromJson(_map(json['indikator'])),
    );
  }
  final int totalPoints;
  final int activeWarnings;
  final int importantWarnings;
  final int pendingReports;
  final int pendingPoints;
  final int activeSanctions;
  final int lateCount;
  final int lateMinutes;
  final StudentPointIndicator indicator;
}

class PointMonthlyProgress {
  const PointMonthlyProgress({
    required this.key,
    required this.label,
    required this.change,
    required this.balance,
  });
  factory PointMonthlyProgress.fromJson(Map<String, dynamic> json) =>
      PointMonthlyProgress(
        key: json['kunci'] as String? ?? '',
        label: json['label'] as String? ?? '-',
        change: _integer(json['perubahan']),
        balance: _integer(json['saldo']),
      );
  final String key;
  final String label;
  final int change;
  final int balance;
}

class PointTransaction {
  const PointTransaction({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.points,
    required this.description,
    this.recordedAt,
    this.source,
  });
  factory PointTransaction.fromJson(Map<String, dynamic> json) =>
      PointTransaction(
        id: _integer(json['id']),
        type: json['jenis'] as String? ?? '',
        typeLabel: json['label_jenis'] as String? ?? '-',
        points: _integer(json['poin']),
        description: json['keterangan'] as String? ?? '-',
        recordedAt: _dateTime(json['tercatat_pada']),
        source: _nullable(json['sumber'], PointSource.fromJson),
      );
  final int id;
  final String type;
  final String typeLabel;
  final int points;
  final String description;
  final DateTime? recordedAt;
  final PointSource? source;
}

class PointSource {
  const PointSource({
    required this.type,
    required this.id,
    required this.label,
  });
  factory PointSource.fromJson(Map<String, dynamic> json) => PointSource(
    type: json['jenis'] as String? ?? '',
    id: _integer(json['id']),
    label: json['label'] as String? ?? '-',
  );
  final String type;
  final int id;
  final String label;
}

class PointReport {
  const PointReport({
    required this.id,
    required this.number,
    required this.date,
    required this.typeLabel,
    required this.status,
    required this.statusLabel,
    required this.points,
    required this.violationCodes,
    this.category,
  });
  factory PointReport.fromJson(Map<String, dynamic> json) => PointReport(
    id: _integer(json['id']),
    number: json['nomor'] as String? ?? '-',
    date: json['tanggal'] as String? ?? '',
    typeLabel: json['label_jenis'] as String? ?? '-',
    category: json['kategori'] as String?,
    status: json['status'] as String? ?? '',
    statusLabel: json['label_status'] as String? ?? '-',
    points: _integer(json['poin']),
    violationCodes: _strings(json['kode_pelanggaran']),
  );
  final int id;
  final String number;
  final String date;
  final String typeLabel;
  final String? category;
  final String status;
  final String statusLabel;
  final int points;
  final List<String> violationCodes;
}

class PointWarning {
  const PointWarning({
    required this.id,
    required this.typeLabel,
    required this.level,
    required this.levelLabel,
    required this.message,
    required this.cycle,
    this.lastDetectedAt,
  });
  factory PointWarning.fromJson(Map<String, dynamic> json) => PointWarning(
    id: _integer(json['id']),
    typeLabel: json['label_jenis'] as String? ?? '-',
    level: json['tingkat'] as String? ?? '',
    levelLabel: json['label_tingkat'] as String? ?? '-',
    message: json['pesan'] as String? ?? '-',
    cycle: _integer(json['siklus']),
    lastDetectedAt: _dateTime(json['terakhir_terdeteksi_pada']),
  );
  final int id;
  final String typeLabel;
  final String level;
  final String levelLabel;
  final String message;
  final int cycle;
  final DateTime? lastDetectedAt;
}

class PointAssistance {
  const PointAssistance({
    required this.id,
    required this.date,
    required this.typeLabel,
    required this.status,
    required this.statusLabel,
    required this.summary,
    this.officer,
    this.warningId,
  });
  factory PointAssistance.fromJson(Map<String, dynamic> json) =>
      PointAssistance(
        id: _integer(json['id']),
        date: json['tanggal'] as String? ?? '',
        typeLabel: json['label_jenis'] as String? ?? '-',
        status: json['status'] as String? ?? '',
        statusLabel: json['label_status'] as String? ?? '-',
        officer: json['petugas'] as String?,
        summary: json['ringkasan'] as String? ?? '-',
        warningId: _nullableInteger(json['peringatan_id']),
      );
  final int id;
  final String date;
  final String typeLabel;
  final String status;
  final String statusLabel;
  final String? officer;
  final String summary;
  final int? warningId;
}

class PointSanction {
  const PointSanction({
    required this.id,
    required this.name,
    required this.triggerPoints,
    required this.status,
    required this.statusLabel,
    required this.overdue,
    this.thresholdPoints,
    this.triggeredAt,
    this.deadline,
    this.officer,
  });
  factory PointSanction.fromJson(Map<String, dynamic> json) => PointSanction(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    thresholdPoints: _nullableInteger(json['ambang_poin']),
    triggerPoints: _integer(json['poin_saat_terpicu']),
    status: json['status'] as String? ?? '',
    statusLabel: json['label_status'] as String? ?? '-',
    triggeredAt: _dateTime(json['terpicu_pada']),
    deadline: json['batas_pelaksanaan'] as String?,
    officer: json['petugas'] as String?,
    overdue: json['terlambat'] as bool? ?? false,
  );
  final int id;
  final String name;
  final int? thresholdPoints;
  final int triggerPoints;
  final String status;
  final String statusLabel;
  final DateTime? triggeredAt;
  final String? deadline;
  final String? officer;
  final bool overdue;
}

class PointReduction {
  const PointReduction({
    required this.id,
    required this.date,
    required this.activity,
    required this.points,
    required this.status,
    required this.statusLabel,
    this.description,
    this.approvedBy,
  });
  factory PointReduction.fromJson(Map<String, dynamic> json) => PointReduction(
    id: _integer(json['id']),
    date: json['tanggal'] as String? ?? '',
    activity: json['jenis_kegiatan'] as String? ?? '-',
    description: json['deskripsi'] as String?,
    points: _integer(json['poin']),
    status: json['status'] as String? ?? '',
    statusLabel: json['label_status'] as String? ?? '-',
    approvedBy: json['disetujui_oleh'] as String?,
  );
  final int id;
  final String date;
  final String activity;
  final String? description;
  final int points;
  final String status;
  final String statusLabel;
  final String? approvedBy;
}

class PointLateArrival {
  const PointLateArrival({
    required this.id,
    required this.date,
    required this.minutes,
    required this.points,
    this.schoolClass,
    this.pointStatus,
  });
  factory PointLateArrival.fromJson(Map<String, dynamic> json) =>
      PointLateArrival(
        id: _integer(json['id']),
        date: json['tanggal'] as String? ?? '',
        schoolClass: json['kelas'] as String?,
        minutes: _integer(json['menit']),
        points: _integer(json['poin']),
        pointStatus: json['status_poin'] as String?,
      );
  final int id;
  final String date;
  final String? schoolClass;
  final int minutes;
  final int points;
  final String? pointStatus;
}

class PointCodeLabel {
  const PointCodeLabel({required this.code, required this.label});
  factory PointCodeLabel.fromJson(Map<String, dynamic> json) => PointCodeLabel(
    code: json['kode'] as String? ?? '',
    label: json['label'] as String? ?? '-',
  );
  final String code;
  final String label;
}

class PointIdName {
  const PointIdName({required this.id, required this.name});
  factory PointIdName.fromJson(Map<String, dynamic> json) => PointIdName(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
  );
  final int id;
  final String name;
}

class PointPerson extends PointIdName {
  const PointPerson({
    required super.id,
    required super.name,
    this.nis,
    this.nisn,
    this.nip,
  });
  factory PointPerson.fromJson(Map<String, dynamic> json) => PointPerson(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    nis: json['nis'] as String?,
    nisn: json['nisn'] as String?,
    nip: json['nip'] as String?,
  );
  final String? nis;
  final String? nisn;
  final String? nip;
}

class PointAcademicYear extends PointIdName {
  const PointAcademicYear({
    required super.id,
    required super.name,
    required this.active,
  });
  factory PointAcademicYear.fromJson(Map<String, dynamic> json) =>
      PointAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );
  final bool active;
}

class PointClass extends PointIdName {
  const PointClass({
    required super.id,
    required super.name,
    required this.academicYearId,
    required this.level,
  });
  factory PointClass.fromJson(Map<String, dynamic> json) => PointClass(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    academicYearId: _integer(json['tahun_pelajaran_id']),
    level: _integer(json['tingkat']),
  );
  final int academicYearId;
  final int level;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};
T? _nullable<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is Map<String, dynamic> ? convert(value) : null;
List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value.whereType<Map>().map((item) => convert(item.cast())).toList()
    : <T>[];
List<String> _strings(Object? value) =>
    value is List ? value.map((item) => '$item').toList() : <String>[];
int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};
int? _nullableInteger(Object? value) => value == null ? null : _integer(value);
DateTime? _dateTime(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
