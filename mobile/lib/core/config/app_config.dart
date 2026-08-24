import 'package:flutter_riverpod/flutter_riverpod.dart';

enum AppEnvironment { development, staging, production }

class AppConfig {
  const AppConfig({required this.environment, required this.apiBaseUri});

  static const _developmentApiUrl = 'http://10.0.2.2:8000/api/v1/';

  final AppEnvironment environment;
  final Uri apiBaseUri;

  bool get isDevelopment => environment == AppEnvironment.development;

  factory AppConfig.fromEnvironment() {
    const environmentValue = String.fromEnvironment(
      'APP_ENV',
      defaultValue: 'development',
    );
    const configuredApiUrl = String.fromEnvironment('API_BASE_URL');

    final environment = switch (environmentValue.toLowerCase()) {
      'development' => AppEnvironment.development,
      'staging' => AppEnvironment.staging,
      'production' => AppEnvironment.production,
      _ => throw StateError('APP_ENV tidak dikenal: $environmentValue'),
    };

    final apiUrl = configuredApiUrl.isNotEmpty
        ? configuredApiUrl
        : environment == AppEnvironment.development
        ? _developmentApiUrl
        : throw StateError(
            'API_BASE_URL wajib ditentukan untuk staging dan production.',
          );
    final apiBaseUri = Uri.tryParse(_withTrailingSlash(apiUrl));

    if (apiBaseUri == null ||
        !apiBaseUri.hasScheme ||
        !apiBaseUri.hasAuthority ||
        !const {'http', 'https'}.contains(apiBaseUri.scheme)) {
      throw StateError('API_BASE_URL tidak valid: $apiUrl');
    }

    if (environment != AppEnvironment.development &&
        apiBaseUri.scheme != 'https') {
      throw StateError('API_BASE_URL staging dan production wajib HTTPS.');
    }

    return AppConfig(environment: environment, apiBaseUri: apiBaseUri);
  }

  static String _withTrailingSlash(String value) {
    return value.endsWith('/') ? value : '$value/';
  }
}

final appConfigProvider = Provider<AppConfig>((ref) {
  throw StateError('AppConfig belum diinisialisasi pada bootstrap aplikasi.');
});
