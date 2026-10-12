import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/persuratan/data/surat_file_picker.dart';
import 'package:nusa/features/persuratan/domain/persuratan.dart';

class PersuratanRepository {
  PersuratanRepository(this._dio);
  final Dio _dio;
  void _path(String path) {
    if (!RegExp(
      r'^(persuratan|surat-saya|tindak-lanjut-surat)(/[A-Za-z0-9-]+)*$',
    ).hasMatch(path)) {
      throw const ValidationException('Alamat layanan persuratan tidak valid.');
    }
  }

  Future<SuratData> get(String path, [SuratData query = const {}]) async {
    _path(path);
    try {
      final r = await _dio.get<SuratData>(path, queryParameters: query);
      return suratMap(r.data?['data']);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<SuratData> send(
    String path,
    SuratData data, {
    FormData? multipart,
  }) async {
    _path(path);
    try {
      final r = await _dio.post<SuratData>(path, data: multipart ?? data);
      return suratMap(r.data?['data']);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<SuratPage> page(String path, [SuratData query = const {}]) async =>
      SuratPage(await get(path, query));
  Future<bool> download(String path, String name) async {
    _path(path);
    try {
      final r = await _dio.get<List<int>>(
        path,
        options: Options(responseType: ResponseType.bytes),
      );
      if (r.data == null ||
          r.headers.value('content-type')?.contains('application/json') ==
              true) {
        throw const NetworkException('Berkas belum dapat diunduh.');
      }
      return await FilePicker.saveFile(
            dialogTitle: 'Simpan berkas surat',
            fileName: safeSuratFileName(name),
            bytes: Uint8List.fromList(r.data!),
          ) !=
          null;
    } on DioException catch (e) {
      if (e.response?.data is List<int>) {
        try {
          e.response!.data = jsonDecode(
            utf8.decode(e.response!.data as List<int>),
          );
        } catch (_) {
          e.response!.data = null;
        }
      }
      throw mapDioException(e);
    }
  }
}

FormData suratMultipart(
  SuratData data, {
  SuratUpload? primary,
  List<SuratUpload> attachments = const [],
  List<SuratUpload> evidence = const [],
}) {
  MultipartFile file(SuratUpload f) {
    validateSuratUpload(f);
    return MultipartFile.fromBytes(
      f.bytes,
      filename: safeSuratFileName(f.name),
    );
  }

  return FormData.fromMap({
    for (final e in data.entries)
      if (e.value != null)
        e.key: e.value is bool ? (e.value == true ? '1' : '0') : e.value,
    if (primary != null) 'berkas_utama': file(primary),
    if (attachments.isNotEmpty) 'lampiran': attachments.map(file).toList(),
    if (evidence.isNotEmpty) 'bukti': evidence.map(file).toList(),
  }, ListFormat.multiCompatible);
}

final persuratanRepositoryProvider = Provider<PersuratanRepository>(
  (ref) => PersuratanRepository(ref.watch(dioProvider)),
);
