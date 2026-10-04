import 'dart:typed_data';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/humas/domain/humas.dart';

class HumasRepository {
  HumasRepository(this._dio);
  final Dio _dio;
  Future<HumasData> get(String path, [HumasData query = const {}]) async {
    try {
      final response = await _dio.get<HumasData>(
        'humas/$path',
        queryParameters: query,
      );
      return humasMap(response.data?['data']);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }

  Future<HumasData> send(
    String path,
    HumasData data, {
    String method = 'POST',
    Object? multipart,
  }) async {
    try {
      final response = await _dio.request<HumasData>(
        'humas/$path',
        data: multipart ?? data,
        options: Options(method: method),
      );
      return humasMap(response.data?['data']);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }

  Future<HumasPage> page(String path, [HumasData query = const {}]) async =>
      HumasPage.fromJson(await get(path, query));

  // Only construct authenticated download paths locally, never attach a token to a supplied URL.
  Future<bool> download(String path, String fileName, {HumasData? data}) async {
    try {
      final response = await _dio.request<List<int>>(
        'humas/$path',
        data: data,
        options: Options(
          method: data == null ? 'GET' : 'POST',
          responseType: ResponseType.bytes,
          headers: {'Accept': 'application/json'},
        ),
      );
      final bytes = response.data;
      if (bytes == null) {
        throw const NetworkException('Berkas belum dapat diunduh.');
      }
      final safeName = fileName.replaceAll(RegExp(r'[\\/:*?"<>|]'), '_');
      return await FilePicker.saveFile(
            dialogTitle: 'Simpan berkas Humas',
            fileName: safeName,
            bytes: Uint8List.fromList(bytes),
          ) !=
          null;
    } on DioException catch (error) {
      // Error bodies use JSON even though success is a binary download.
      if (error.response?.data is List<int>) {
        final response = error.response!;
        try {
          response.data = jsonDecode(utf8.decode(response.data as List<int>));
        } catch (_) {
          response.data = null;
        }
      }
      throw mapDioException(error);
    }
  }
}

final humasRepositoryProvider = Provider<HumasRepository>(
  (ref) => HumasRepository(ref.watch(dioProvider)),
);
