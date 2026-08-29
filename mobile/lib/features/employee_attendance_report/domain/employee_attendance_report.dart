class EmployeeReportOption {
  const EmployeeReportOption({required this.id, required this.name, this.nip});

  factory EmployeeReportOption.fromJson(Map<String, dynamic> json) =>
      EmployeeReportOption(
        id: _int(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
      );

  final int id;
  final String name;
  final String? nip;
}

class EmployeeAttendanceReportSummary {
  const EmployeeAttendanceReportSummary({
    required this.employees,
    required this.effectiveDays,
    required this.present,
    required this.permitted,
    required this.sick,
    required this.officialDuty,
    required this.leave,
    required this.absent,
    required this.late,
    required this.lateMinutes,
    required this.earlyLeave,
    required this.earlyLeaveMinutes,
    required this.notCheckedOut,
    required this.manual,
    required this.averageAttendance,
  });

  factory EmployeeAttendanceReportSummary.fromJson(Map<String, dynamic> json) =>
      EmployeeAttendanceReportSummary(
        employees: _int(json['pegawai']),
        effectiveDays: _int(json['hari_efektif']),
        present: _int(json['hadir']),
        permitted: _int(json['izin']),
        sick: _int(json['sakit']),
        officialDuty: _int(json['dinas_luar']),
        leave: _int(json['cuti']),
        absent: _int(json['alfa']),
        late: _int(json['terlambat']),
        lateMinutes: _int(json['menit_terlambat']),
        earlyLeave: _int(json['pulang_cepat']),
        earlyLeaveMinutes: _int(json['menit_pulang_cepat']),
        notCheckedOut: _int(json['belum_pulang']),
        manual: _int(json['manual']),
        averageAttendance: _double(
          json['rata_persentase_hadir'] ?? json['persentase_hadir'],
        ),
      );

  final int employees;
  final int effectiveDays;
  final int present;
  final int permitted;
  final int sick;
  final int officialDuty;
  final int leave;
  final int absent;
  final int late;
  final int lateMinutes;
  final int earlyLeave;
  final int earlyLeaveMinutes;
  final int notCheckedOut;
  final int manual;
  final double averageAttendance;
}

class EmployeeAttendanceReportItem {
  const EmployeeAttendanceReportItem({
    required this.employeeId,
    required this.name,
    required this.initials,
    required this.active,
    required this.summary,
    this.nip,
    this.photoUrl,
    this.employeeType,
    this.position,
    this.employmentStatus,
  });

  factory EmployeeAttendanceReportItem.fromJson(Map<String, dynamic> json) =>
      EmployeeAttendanceReportItem(
        employeeId: _int(json['id']),
        name: json['nama'] as String? ?? '-',
        initials: json['inisial'] as String? ?? 'P',
        nip: json['nip'] as String?,
        photoUrl: json['foto_url'] as String?,
        employeeType: json['jenis_pegawai'] as String?,
        position: json['jabatan'] as String?,
        employmentStatus: json['status_kepegawaian'] as String?,
        active: json['aktif'] as bool? ?? false,
        summary: EmployeeAttendanceReportSummary.fromJson(
          _map(json['ringkasan']),
        ),
      );

  final int employeeId;
  final String name;
  final String initials;
  final String? nip;
  final String? photoUrl;
  final String? employeeType;
  final String? position;
  final String? employmentStatus;
  final bool active;
  final EmployeeAttendanceReportSummary summary;
}

class EmployeeAttendanceReportPage {
  const EmployeeAttendanceReportPage({
    required this.month,
    required this.periodLabel,
    required this.startDate,
    required this.endDate,
    required this.summary,
    required this.items,
    required this.employeeTypes,
    required this.employees,
    required this.employeeStatus,
    required this.query,
    required this.page,
    required this.hasMore,
    required this.privateScope,
    this.employeeType,
    this.employeeId,
  });

  factory EmployeeAttendanceReportPage.fromJson(Map<String, dynamic> json) {
    final period = _map(json['periode']);
    final filter = _map(json['filter']);
    final pagination = _map(json['paginasi']);
    final access = _map(json['hak_akses']);

    return EmployeeAttendanceReportPage(
      month: period['bulan'] as String? ?? '',
      periodLabel: period['label'] as String? ?? '-',
      startDate: period['tanggal_mulai'] as String? ?? '',
      endDate: period['tanggal_selesai'] as String? ?? '',
      summary: EmployeeAttendanceReportSummary.fromJson(
        _map(json['ringkasan']),
      ),
      items: _list(json['items'], EmployeeAttendanceReportItem.fromJson),
      employeeTypes: (json['jenis_pegawai'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(growable: false),
      employees: _list(json['pegawai'], EmployeeReportOption.fromJson),
      employeeType: filter['jenis_pegawai'] as String?,
      employeeId: _nullableInt(filter['pegawai_id']),
      employeeStatus: filter['status_pegawai'] as String? ?? 'aktif',
      query: filter['cari'] as String? ?? '',
      page: _int(pagination['halaman']),
      hasMore: pagination['ada_halaman_berikutnya'] as bool? ?? false,
      privateScope: access['cakupan_pribadi'] as bool? ?? false,
    );
  }

  final String month;
  final String periodLabel;
  final String startDate;
  final String endDate;
  final EmployeeAttendanceReportSummary summary;
  final List<EmployeeAttendanceReportItem> items;
  final List<String> employeeTypes;
  final List<EmployeeReportOption> employees;
  final String? employeeType;
  final int? employeeId;
  final String employeeStatus;
  final String query;
  final int page;
  final bool hasMore;
  final bool privateScope;
}

class EmployeeAttendanceReportDay {
  const EmployeeAttendanceReportDay({
    required this.date,
    required this.dateLabel,
    required this.day,
    required this.status,
    required this.statusLabel,
    required this.inferred,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    required this.description,
    this.scheduleName,
    this.scheduledCheckIn,
    this.scheduledCheckOut,
    this.checkIn,
    this.checkOut,
    this.source,
    this.notes,
  });

  factory EmployeeAttendanceReportDay.fromJson(Map<String, dynamic> json) {
    final schedule = json['jadwal'] is Map ? _map(json['jadwal']) : null;
    return EmployeeAttendanceReportDay(
      date: json['tanggal'] as String? ?? '',
      dateLabel: json['tanggal_label'] as String? ?? '-',
      day: json['hari'] as String? ?? '-',
      status: json['status'] as String? ?? 'alfa',
      statusLabel: json['status_label'] as String? ?? 'Alfa',
      inferred: json['inferensi'] as bool? ?? false,
      scheduleName: schedule?['nama'] as String?,
      scheduledCheckIn: schedule?['jam_masuk'] as String?,
      scheduledCheckOut: schedule?['jam_pulang'] as String?,
      checkIn: json['jam_masuk'] as String?,
      checkOut: json['jam_pulang'] as String?,
      lateMinutes: _int(json['menit_terlambat']),
      earlyLeaveMinutes: _int(json['menit_pulang_cepat']),
      source: json['sumber'] as String?,
      notes: json['catatan'] as String?,
      description: json['keterangan'] as String? ?? '-',
    );
  }

  final String date;
  final String dateLabel;
  final String day;
  final String status;
  final String statusLabel;
  final bool inferred;
  final String? scheduleName;
  final String? scheduledCheckIn;
  final String? scheduledCheckOut;
  final String? checkIn;
  final String? checkOut;
  final int lateMinutes;
  final int earlyLeaveMinutes;
  final String? source;
  final String? notes;
  final String description;
}

class EmployeeAttendanceReportDetail {
  const EmployeeAttendanceReportDetail({
    required this.employee,
    required this.month,
    required this.periodLabel,
    required this.summary,
    required this.days,
    required this.privateScope,
  });

  factory EmployeeAttendanceReportDetail.fromJson(Map<String, dynamic> json) {
    final period = _map(json['periode']);
    final employee = _map(json['pegawai']);
    final access = _map(json['hak_akses']);

    return EmployeeAttendanceReportDetail(
      employee: EmployeeAttendanceReportItem.fromJson({
        ...employee,
        'ringkasan': _map(json['ringkasan']),
      }),
      month: period['bulan'] as String? ?? '',
      periodLabel: period['label'] as String? ?? '-',
      summary: EmployeeAttendanceReportSummary.fromJson({
        ..._map(json['ringkasan']),
        'pegawai': 1,
      }),
      days: _list(json['rincian'], EmployeeAttendanceReportDay.fromJson),
      privateScope: access['cakupan_pribadi'] as bool? ?? false,
    );
  }

  final EmployeeAttendanceReportItem employee;
  final String month;
  final String periodLabel;
  final EmployeeAttendanceReportSummary summary;
  final List<EmployeeAttendanceReportDay> days;
  final bool privateScope;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);
int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
int? _nullableInt(Object? value) =>
    value == null ? null : int.tryParse('$value');
double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
