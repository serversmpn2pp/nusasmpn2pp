import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/learning_survey/domain/learning_survey.dart';

abstract interface class LearningSurveyRemoteDataSource {
  Future<LearningSurveyPage> fetch(LearningSurveyArgs args);

  Future<LearningSurveySubmitResult> submit(
    LearningSurveyArgs args,
    LearningSurveySubmission value,
  );
}

final class DioLearningSurveyRemoteDataSource
    implements LearningSurveyRemoteDataSource {
  DioLearningSurveyRemoteDataSource(this._dio);

  final Dio _dio;

  String _path(LearningSurveyArgs args) =>
      'survei-pembelajaran/${args.assignmentId}/${args.semester}';

  @override
  Future<LearningSurveyPage> fetch(LearningSurveyArgs args) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(_path(args));
      return LearningSurveyPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<LearningSurveySubmitResult> submit(
    LearningSurveyArgs args,
    LearningSurveySubmission value,
  ) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        _path(args),
        data: value.toJson(),
      );
      final data = response.data?['data'];
      return LearningSurveySubmitResult(
        message:
            response.data?['pesan'] as String? ?? 'Survei berhasil dikirim.',
        alreadyCompleted: data is Map
            ? data['sudah_diisi'] as bool? ?? true
            : true,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final learningSurveyRemoteDataSourceProvider =
    Provider<LearningSurveyRemoteDataSource>(
      (ref) => DioLearningSurveyRemoteDataSource(ref.watch(dioProvider)),
    );
