class ManualAttendanceRule {
  const ManualAttendanceRule({this.minimumMinutes = 5, this.points = 15});

  factory ManualAttendanceRule.fromJson(Object? value) {
    final json = value is Map ? value : const {};
    return ManualAttendanceRule(
      minimumMinutes: (json['menit_minimal'] as num?)?.toInt() ?? 5,
      points: (json['poin'] as num?)?.toInt() ?? 15,
    );
  }

  static const reasons = {
    'tidak_membawa_kartu': 'Tidak membawa kartu pelajar',
    'kartu_hilang': 'Kartu pelajar hilang',
  };

  static String normalizeReason(String? reason) =>
      reasons.containsKey(reason) ? reason! : 'tidak_membawa_kartu';

  final int minimumMinutes;
  final int points;

  String get explanation =>
      'Hadir tanpa scan kartu dikenai minimal '
      '$minimumMinutes menit keterlambatan dan $points poin sesuai aturan '
      'presensi. Keterlambatan lebih besar tetap dihitung. Poin tidak diterapkan '
      'pada hari dengan pengecualian presensi. Orang tua mendapat '
      'keterangan alasannya. Segera hubungi admin untuk mendapatkan atau '
      'mengganti kartu pelajar.';
}
