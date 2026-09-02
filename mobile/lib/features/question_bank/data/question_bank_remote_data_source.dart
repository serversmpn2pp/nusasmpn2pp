import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/question_bank/domain/question_bank.dart';

abstract interface class QuestionBankRemoteDataSource {
  Future<QuestionBankPage> fetch({
    required String query,
    required int? subjectId,
    required String grade,
    required String type,
    required String status,
    required int page,
  });

  Future<BankQuestionDetail> detail(int id);
  Future<BankQuestionDetail> create(QuestionFormValue value);
  Future<BankQuestionDetail> update(int id, QuestionFormValue value);
  Future<void> archive(int id);
}

final class DioQuestionBankRemoteDataSource
    implements QuestionBankRemoteDataSource {
  DioQuestionBankRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<QuestionBankPage> fetch({
    required String query,
    required int? subjectId,
    required String grade,
    required String type,
    required String status,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'bank-soal',
        queryParameters: {
          if (query.trim().isNotEmpty) 'kata_kunci': query.trim(),
          'mata_pelajaran_id': ?subjectId,
          'tingkat': grade,
          'jenis_soal': type,
          'status': status,
          'halaman': page,
          'per_halaman': 12,
        },
      );
      return QuestionBankPage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<BankQuestionDetail> detail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('bank-soal/$id');
      return BankQuestionDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<BankQuestionDetail> create(QuestionFormValue value) =>
      _send('bank-soal', value);

  @override
  Future<BankQuestionDetail> update(int id, QuestionFormValue value) =>
      _send('bank-soal/$id', value);

  Future<BankQuestionDetail> _send(String path, QuestionFormValue value) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        path,
        data: FormData.fromMap({
          'payload': jsonEncode(value.payload),
          if (value.image case final image?)
            'gambar_soal': MultipartFile.fromBytes(
              image.bytes,
              filename: image.name,
            ),
        }),
      );
      return BankQuestionDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> archive(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('bank-soal/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final questionBankRemoteDataSourceProvider =
    Provider<QuestionBankRemoteDataSource>(
      (ref) => DioQuestionBankRemoteDataSource(ref.watch(dioProvider)),
    );
