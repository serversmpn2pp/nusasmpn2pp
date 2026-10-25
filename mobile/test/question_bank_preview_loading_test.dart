import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_bank/data/question_bank_remote_data_source.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_preview_view.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_question_view.dart';

void main() {
  for (final status in [403, 404, 500]) {
    testWidgets(
      'pratinjau HTTP $status berhenti loading dan hanya mengulang lewat tombol',
      (tester) async {
        var requests = 0;
        var available = false;
        final gate = Completer<void>();
        final dio = Dio(BaseOptions(baseUrl: 'https://question.test/api/v1/'));
        addTearDown(dio.close);
        dio.interceptors.add(
          InterceptorsWrapper(
            onRequest: (options, handler) async {
              requests++;
              if (!available) {
                handler.reject(
                  DioException(
                    requestOptions: options,
                    type: DioExceptionType.badResponse,
                    response: Response(
                      requestOptions: options,
                      statusCode: status,
                      data: {'message': 'Not found'},
                    ),
                  ),
                );
                return;
              }
              await gate.future;
              handler.resolve(
                Response(
                  requestOptions: options,
                  statusCode: 200,
                  data: {
                    'data': {'soal': _question},
                  },
                ),
              );
            },
          ),
        );
        await tester.pumpWidget(
          ProviderScope(
            overrides: [
              questionBankRemoteDataSourceProvider.overrideWithValue(
                DioQuestionBankRemoteDataSource(dio),
              ),
            ],
            child: MaterialApp(
              theme: AppTheme.light,
              home: const QuestionBankPreviewView(questionId: 7),
            ),
          ),
        );
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 100));
        expect(find.byType(CircularProgressIndicator), findsNothing);
        expect(find.text('Coba Lagi'), findsOneWidget);
        if (status == 404) {
          expect(
            find.textContaining('server NUSA sudah diperbarui'),
            findsOneWidget,
          );
        }
        await tester.pump(const Duration(seconds: 30));
        expect(requests, 1);

        available = true;
        await tester.tap(find.text('Coba Lagi'));
        await tester.pump();
        expect(find.byType(CircularProgressIndicator), findsOneWidget);
        expect(find.text('Coba Lagi'), findsNothing);
        gate.complete();
        await tester.pumpAndSettle();
        expect(find.byType(StudentExamQuestionView), findsOneWidget);
        expect(find.text('Pertanyaan percobaan'), findsOneWidget);
        expect(requests, 2);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'permintaan menggantung berhenti setelah 30 detik dan respons terlambat diabaikan',
    (tester) async {
      final dio = Dio();
      addTearDown(dio.close);
      RequestOptions? request;
      final gate = Completer<void>();
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (options, handler) async {
            request = options;
            await gate.future;
            handler.resolve(
              Response(
                requestOptions: options,
                statusCode: 200,
                data: {
                  'data': {'soal': _question},
                },
              ),
            );
          },
        ),
      );
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            questionBankRemoteDataSourceProvider.overrideWithValue(
              DioQuestionBankRemoteDataSource(dio),
            ),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const QuestionBankPreviewView(questionId: 7),
          ),
        ),
      );
      await tester.pump();
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      await tester.pump(const Duration(seconds: 31));
      await tester.pump();
      expect(find.byType(CircularProgressIndicator), findsNothing);
      expect(find.textContaining('Pratinjau terlalu lama'), findsOneWidget);
      expect(request?.cancelToken?.isCancelled, isTrue);
      gate.complete();
      await tester.pumpAndSettle();
      expect(find.byType(StudentExamQuestionView), findsNothing);
      expect(find.text('Coba Lagi'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  for (final data in [
    <String, dynamic>{},
    {
      'soal': {..._question, 'id': 99},
    },
  ]) {
    test('respons pratinjau tidak lengkap atau salah soal menghasilkan pesan kesalahan', () async {
      final dio = Dio();
      addTearDown(dio.close);
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (options, handler) {
            handler.resolve(
              Response(
                requestOptions: options,
                statusCode: 200,
                data: {'data': data},
              ),
            );
          },
        ),
      );
      await expectLater(
        DioQuestionBankRemoteDataSource(dio).preview(7),
        throwsA(isA<NetworkException>()),
      );
    });
  }

  test('sesi kedaluwarsa tetap menghasilkan UnauthorizedException', () async {
    final dio = Dio();
    addTearDown(dio.close);
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          handler.reject(
            DioException(
              requestOptions: options,
              response: Response(requestOptions: options, statusCode: 401),
            ),
          );
        },
      ),
    );
    await expectLater(
      DioQuestionBankRemoteDataSource(dio).preview(7),
      throwsA(isA<UnauthorizedException>()),
    );
  });
}

const _question = <String, dynamic>{
  'id': 7,
  'nomor': 1,
  'jenis': 'pilihan_ganda',
  'pertanyaan': 'Pertanyaan percobaan',
  'label_jenis': 'Pilihan Ganda',
  'jawaban': <String, dynamic>{},
  'ragu': false,
  'pilihan': [
    {'kode': 'A', 'teks': 'Pilihan satu'},
    {'kode': 'B', 'teks': 'Pilihan dua'},
  ],
};
