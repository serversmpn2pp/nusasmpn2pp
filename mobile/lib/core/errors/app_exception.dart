sealed class AppException implements Exception {
  const AppException(this.message, {this.cause});

  final String message;
  final Object? cause;

  @override
  String toString() => message;
}

final class NetworkException extends AppException {
  const NetworkException(super.message, {super.cause, this.statusCode});

  final int? statusCode;
}

final class UnauthorizedException extends AppException {
  const UnauthorizedException({super.cause})
    : super('Sesi Anda tidak valid. Silakan masuk kembali.');
}

final class ValidationException extends AppException {
  const ValidationException(
    super.message, {
    super.cause,
    this.errors = const {},
  });

  final Map<String, List<String>> errors;
}
