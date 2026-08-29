import 'package:nusa/features/worship_scan/domain/worship_scan.dart';

class PrivateWorshipScanDashboard {
  const PrivateWorshipScanDashboard({
    required this.privateMode,
    required this.academicYearName,
    required this.dateLabel,
    required this.serverTime,
    required this.scanOpen,
    required this.scheduleStatus,
    required this.selectedScheduleId,
    required this.schedules,
    required this.todayCount,
    required this.classScope,
    required this.confirmationDayLimit,
    required this.settingsActive,
    required this.privacyMessage,
  });

  factory PrivateWorshipScanDashboard.fromJson(Map<String, dynamic> json) {
    final academicYear = _mapOrNull(json['tahun_pelajaran']);
    return PrivateWorshipScanDashboard(
      privateMode: json['mode_privat'] as bool? ?? true,
      academicYearName: academicYear?['nama'] as String?,
      dateLabel: json['tanggal_label'] as String? ?? '-',
      serverTime: json['waktu_server'] as String? ?? '',
      scanOpen: json['scan_dibuka'] as bool? ?? false,
      scheduleStatus: WorshipScanScheduleStatus.fromJson(
        _map(json['status_jadwal']),
      ),
      selectedScheduleId: _nullableInteger(json['jadwal_dipilih_id']),
      schedules: _list(json['jadwal'], WorshipScanSchedule.fromJson),
      todayCount: _integer(json['jumlah_hari_ini']),
      classScope: _list(json['cakupan_kelas'], PrivateWorshipClass.fromJson),
      confirmationDayLimit: _integer(json['batas_hari_konfirmasi']),
      settingsActive: json['pengaturan_aktif'] as bool? ?? true,
      privacyMessage:
          json['pesan_privasi'] as String? ??
          'Identitas hasil scan hanya ditampilkan sesaat.',
    );
  }

  final bool privateMode;
  final String? academicYearName;
  final String dateLabel;
  final String serverTime;
  final bool scanOpen;
  final WorshipScanScheduleStatus scheduleStatus;
  final int? selectedScheduleId;
  final List<WorshipScanSchedule> schedules;
  final int todayCount;
  final List<PrivateWorshipClass> classScope;
  final int confirmationDayLimit;
  final bool settingsActive;
  final String privacyMessage;

  WorshipScanSchedule? get selectedSchedule {
    for (final schedule in schedules) {
      if (schedule.id == selectedScheduleId) return schedule;
    }
    return null;
  }

  PrivateWorshipScanDashboard withTodayCount(int value) =>
      PrivateWorshipScanDashboard(
        privateMode: privateMode,
        academicYearName: academicYearName,
        dateLabel: dateLabel,
        serverTime: serverTime,
        scanOpen: scanOpen,
        scheduleStatus: scheduleStatus,
        selectedScheduleId: selectedScheduleId,
        schedules: schedules,
        todayCount: value,
        classScope: classScope,
        confirmationDayLimit: confirmationDayLimit,
        settingsActive: settingsActive,
        privacyMessage: privacyMessage,
      );
}

class PrivateWorshipClass {
  const PrivateWorshipClass({required this.id, required this.name});

  factory PrivateWorshipClass.fromJson(Map<String, dynamic> json) =>
      PrivateWorshipClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );

  final int id;
  final String name;
}

class PrivateWorshipScanStudent {
  const PrivateWorshipScanStudent({
    required this.name,
    required this.nisn,
    required this.className,
    required this.dayNumber,
    this.photoUrl,
  });

  factory PrivateWorshipScanStudent.fromJson(Map<String, dynamic> json) =>
      PrivateWorshipScanStudent(
        name: json['nama_lengkap'] as String? ?? '-',
        nisn: json['nisn'] as String? ?? '-',
        className: json['kelas'] as String? ?? '-',
        dayNumber: _nullableInteger(json['hari_ke']),
        photoUrl: json['foto_url'] as String?,
      );

  final String name;
  final String nisn;
  final String className;
  final int? dayNumber;
  final String? photoUrl;
}

class PrivateWorshipScanResult {
  const PrivateWorshipScanResult({
    required this.success,
    required this.isNew,
    required this.status,
    required this.message,
    required this.serverTime,
    required this.todayCount,
    this.student,
  });

  factory PrivateWorshipScanResult.fromJson(Map<String, dynamic> json) {
    final student = _mapOrNull(json['siswa']);
    return PrivateWorshipScanResult(
      success: json['berhasil'] as bool? ?? false,
      isNew: json['baru'] as bool? ?? false,
      status: json['status'] as String? ?? 'gagal',
      message: json['pesan'] as String? ?? 'Hasil scan belum tersedia.',
      serverTime: json['waktu_server'] as String? ?? '',
      todayCount: _integer(json['jumlah_hari_ini']),
      student: student == null
          ? null
          : PrivateWorshipScanStudent.fromJson(student),
    );
  }

  final bool success;
  final bool isNew;
  final String status;
  final String message;
  final String serverTime;
  final int todayCount;
  final PrivateWorshipScanStudent? student;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

Map<String, dynamic>? _mapOrNull(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) fromJson) =>
    value is List ? value.map((item) => fromJson(_map(item))).toList() : [];

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};

int? _nullableInteger(Object? value) => value == null ? null : _integer(value);
