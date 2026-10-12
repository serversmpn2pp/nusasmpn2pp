import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';

typedef ExceptionData = Map<String, dynamic>;
ExceptionData exceptionMap(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : {};
List<ExceptionData> exceptionItems(Object? value) =>
    value is List ? value.whereType<Map>().map(exceptionMap).toList() : [];
int exceptionInt(Object? value) => int.tryParse('$value') ?? 0;
String exceptionText(Object? value, [String fallback = '-']) =>
    value == null || '$value'.trim().isEmpty ? fallback : '$value';
String exceptionDate(Object? value) {
  final d = DateTime.tryParse('$value')?.toLocal();
  if (d == null) return '-';
  return '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}'
      '${'$value'.contains('T') ? ' ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}' : ''}';
}

String exceptionError(Object error) =>
    error is ValidationException && error.errors.isNotEmpty
    ? error.errors.values.expand((v) => v).join('\n')
    : error is AppException
    ? error.message
    : 'Pengecualian presensi belum dapat diproses. Silakan coba lagi.';
bool canManageAttendanceExceptions(Pengguna? user) {
  if (user == null ||
      !['Pegawai', 'Administrator sistem'].contains(user.jenisAkun)) {
    return false;
  }
  if (user.administrator) return true;
  if (!user.izin.contains('absensi.pengaturan_kelola')) return false;
  return !user.peran.contains('wali_kelas') ||
      user.peran.any(
        [
          'pimpinan',
          'wakil_pimpinan_kesiswaan',
          'wakil_pimpinan_kurikulum',
          'bk',
        ].contains,
      );
}

const exceptionImpactLabels = {
  'siswa_dalam_cakupan': 'Siswa dalam cakupan',
  'siswa_terdampak': 'Siswa terdampak',
  'alfa_otomatis_dibatalkan': 'Alfa otomatis dikecualikan',
  'hari_aktif_terlewati': 'Hari aktif terlewati',
  'catatan_dipertahankan': 'Catatan tetap tersimpan',
  'alfa_manual': 'Alfa manual tetap berlaku',
};
