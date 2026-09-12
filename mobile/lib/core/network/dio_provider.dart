import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/core/security/password_change_gate.dart';
import 'package:nusa/core/storage/token_storage.dart';

final dioProvider = Provider<Dio>((ref) {
  final config = ref.watch(appConfigProvider);
  final tokenStorage = ref.watch(tokenStorageProvider);

  final dio = Dio(
    BaseOptions(
      baseUrl: config.apiBaseUri.toString(),
      connectTimeout: const Duration(seconds: 15),
      sendTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      responseType: ResponseType.json,
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await tokenStorage.read();

        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }

        handler.next(options);
      },
      onError: (error, handler) {
        final data = error.response?.data;
        final mustChangePassword =
            error.response?.statusCode == 428 &&
            data is Map &&
            data['wajib_ganti_kata_sandi'] == true;

        if (mustChangePassword) {
          ref.read(passwordChangeGateProvider.notifier).requireChange();
        }

        handler.next(error);
      },
    ),
  );

  return dio;
});
