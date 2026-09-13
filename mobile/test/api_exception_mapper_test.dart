import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/presentation/startup_view.dart';

void main() {
  test('gangguan koneksi memakai bahasa umum untuk pengguna', () {
    final result = mapDioException(
      DioException(
        requestOptions: RequestOptions(path: '/beranda'),
        type: DioExceptionType.connectionError,
        error: Exception('Failed host lookup: nusa.example'),
      ),
    );

    expect(result, isA<NetworkException>());
    expect(result.message, contains('Koneksi ke NUSA belum tersedia'));
    expect(result.message, contains('hubungi admin sekolah'));
    expect(result.message, isNot(contains('API')));
    expect(result.message, isNot(contains('host')));
  });

  test('gangguan server tidak membocorkan pesan teknis Laravel atau SQL', () {
    final options = RequestOptions(path: '/ujian-anak-saya');
    final result = mapDioException(
      DioException(
        requestOptions: options,
        type: DioExceptionType.badResponse,
        response: Response<Map<String, dynamic>>(
          requestOptions: options,
          statusCode: 500,
          data: {
            'message': 'SQLSTATE[42P01]: Undefined table: relation rahasia does not exist',
          },
        ),
      ),
    );

    expect(result.message, contains('Layanan NUSA sedang mengalami gangguan'));
    expect(result.message, isNot(contains('SQLSTATE')));
    expect(result.message, isNot(contains('relation')));
  });

  test('pesan validasi yang aman tetap diteruskan', () {
    final options = RequestOptions(path: '/auth/login');
    final result = mapDioException(
      DioException(
        requestOptions: options,
        type: DioExceptionType.badResponse,
        response: Response<Map<String, dynamic>>(
          requestOptions: options,
          statusCode: 422,
          data: {
            'message': 'Username atau kata sandi belum benar.',
            'errors': {
              'username': ['Username atau kata sandi belum benar.'],
            },
          },
        ),
      ),
    );

    expect(result, isA<ValidationException>());
    expect(result.message, 'Username atau kata sandi belum benar.');
  });

  testWidgets('splash menampilkan peringatan koneksi yang mudah dipahami', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(_OfflineAuthController.new),
        ],
        child: MaterialApp(theme: AppTheme.light, home: const StartupView()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Koneksi belum tersedia'), findsOneWidget);
    expect(find.textContaining('Pastikan perangkat terhubung'), findsOneWidget);
    expect(find.text('Coba lagi'), findsOneWidget);
    expect(find.textContaining('alamat API'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

class _OfflineAuthController extends AuthController {
  @override
  Future<AuthState> build() async {
    throw const NetworkException(
      'Koneksi ke NUSA belum tersedia. Pastikan perangkat terhubung ke '
      'internet, lalu coba lagi. Jika masih belum berhasil, hubungi admin '
      'sekolah.',
    );
  }
}
