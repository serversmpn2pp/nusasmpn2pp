class ManualAttendanceRule {
  const ManualAttendanceRule({
    this.points = 15,
    this.violationName,
    this.supportsScanIssue = false,
    this.supportsArrivalStatus = true,
  });

  factory ManualAttendanceRule.fromJson(Object? value) {
    final json = value is Map ? value : const {};
    return ManualAttendanceRule(
      points: (json['poin'] as num?)?.toInt() ?? 15,
      supportsArrivalStatus: json['pilih_status_masuk'] == true,
      supportsScanIssue:
          json['alasan'] is List &&
          (json['alasan'] as List).any(
            (item) => item is Map && item['kode'] == scanIssue,
          ),
      violationName: json['pelanggaran'] is Map
          ? json['pelanggaran']['nama'] as String?
          : null,
    );
  }

  static const reasons = {
    'tidak_membawa_kartu': 'Tidak membawa kartu pelajar',
    'kartu_hilang': 'Kartu pelajar hilang',
  };
  static const arrivalStatuses = {
    'tepat_waktu': 'Hadir tepat waktu',
    'terlambat': 'Hadir terlambat',
  };

  static const scanIssue = 'scan_bermasalah';
  static const pendingExplanation = 'Hadir · Perlu verifikasi. Poin ditahan.';
  static const serverUpdateNeeded =
      'Server NUSA perlu diperbarui untuk menyimpan status datang tanpa jam.';

  Map<String, String> get availableReasons => {
    ...reasons,
    if (supportsScanIssue) scanIssue: 'Scan bermasalah',
  };

  final int points;
  final String? violationName;
  final bool supportsScanIssue;
  final bool supportsArrivalStatus;

  String get explanation =>
      'Aturan tanpa kartu: $points poin${violationName == null ? '' : ' ($violationName)'}. '
      'Poin kartu terpisah dari status datang dan tidak berlaku pada pengecualian presensi.';
}
