class ManualAttendanceRule {
  const ManualAttendanceRule({
    this.minimumMinutes = 5,
    this.points = 15,
    this.violationName,
    this.supportsScanIssue = false,
  });

  factory ManualAttendanceRule.fromJson(Object? value) {
    final json = value is Map ? value : const {};
    return ManualAttendanceRule(
      minimumMinutes: (json['menit_minimal'] as num?)?.toInt() ?? 5,
      points: (json['poin'] as num?)?.toInt() ?? 15,
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

  static const scanIssue = 'scan_bermasalah';
  static const pendingExplanation = 'Hadir · Perlu verifikasi. Poin ditahan.';

  Map<String, String> get availableReasons => {
    ...reasons,
    if (supportsScanIssue) scanIssue: 'Scan bermasalah',
  };

  static String normalizeReason(String? reason) =>
      reasons.containsKey(reason) ? reason! : 'tidak_membawa_kartu';

  final int minimumMinutes;
  final int points;
  final String? violationName;
  final bool supportsScanIssue;

  String get explanation =>
      'Hadir tanpa scan kartu dikenai minimal '
      '$minimumMinutes menit keterlambatan dan $points poin sesuai aturan '
      'presensi${violationName == null ? '' : ' ($violationName)'}. Keterlambatan lebih besar tetap dihitung. Poin tidak diterapkan '
      'pada hari dengan pengecualian presensi. Orang tua mendapat '
      'keterangan alasannya. Segera hubungi admin untuk mendapatkan atau '
      'mengganti kartu pelajar.';
}
