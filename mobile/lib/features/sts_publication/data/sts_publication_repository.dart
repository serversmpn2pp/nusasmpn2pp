import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';

typedef StsData = Map<String, dynamic>;
StsData stsMap(Object? v) => v is Map ? Map<String, dynamic>.from(v) : {};
List<StsData> stsItems(Object? v) =>
    v is List ? v.whereType<Map>().map(stsMap).toList() : [];
int stsInt(Object? v) => int.tryParse('$v') ?? 0;
String stsText(Object? v, [String fallback = '-']) =>
    v == null || '$v'.trim().isEmpty ? fallback : '$v';
String stsDate(Object? v) {
  final d = DateTime.tryParse('$v')?.toLocal();
  if (d == null) return '-';
  return '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';
}

String stsError(Object e) => e is ValidationException && e.errors.isNotEmpty
    ? e.errors.values.expand((v) => v).join('\n')
    : e is AppException
    ? e.message
    : 'Rapor belum dapat diproses. Silakan coba lagi.';
bool stsRecipient(Pengguna? u) =>
    u != null && ['Siswa', 'Orang tua'].contains(u.jenisAkun);
bool stsPublisher(Pengguna? u) =>
    u != null &&
    ['Pegawai', 'Administrator sistem'].contains(u.jenisAkun) &&
    (u.administrator ||
        (u.izin.contains('nilai.rekap') &&
            u.peran.any(['wali_kelas', 'wakil_pimpinan_kurikulum'].contains)));

class StsPublicationRepository {
  StsPublicationRepository(this._dio);
  final Dio _dio;
  String _path(int activity, int classId) {
    if (activity < 1 || classId < 1) {
      throw ArgumentError('Cakupan rapor tidak valid');
    }
    return 'rapor-sts/$activity/$classId/publikasi';
  }

  Future<StsData> list() => _call('GET', 'rapor-sts-saya');
  Future<StsData> detail(int id) {
    if (id < 1) throw ArgumentError('ID rapor tidak valid');
    return _call('GET', 'rapor-sts-saya/$id');
  }

  Future<StsData> publication(int activity, int classId) =>
      _call('GET', _path(activity, classId));
  Future<StsData> publish(int activity, int classId, StsData data) =>
      _call('POST', _path(activity, classId), {...data, 'konfirmasi': true});
  Future<StsData> cancel(
    int activity,
    int classId,
    List<StsData> students,
    String reason,
  ) => _call('DELETE', _path(activity, classId), {
    'siswa': students,
    'alasan': reason,
    'konfirmasi': true,
  });
  Future<StsData> _call(String method, String path, [StsData? data]) async {
    try {
      final r = await _dio.request<StsData>(
        path,
        data: data,
        options: Options(method: method),
      );
      return stsMap(r.data?['data']);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }
}

final stsPublicationRepositoryProvider = Provider(
  (ref) => StsPublicationRepository(ref.watch(dioProvider)),
);
