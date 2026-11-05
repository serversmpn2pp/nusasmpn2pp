import 'dart:math';

import 'package:nusa/core/errors/app_exception.dart';

typedef TfData = Map<String, dynamic>;
TfData tfMap(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : {};
List<TfData> tfItems(Object? value) =>
    value is List ? value.whereType<Map>().map(tfMap).toList() : [];
int tfInt(Object? value) => int.tryParse('$value') ?? 0;
String tfText(Object? value, [String fallback = '-']) =>
    value == null || '$value'.trim().isEmpty ? fallback : '$value';
String tfDate(Object? value) {
  final date = DateTime.tryParse('$value');
  return date == null
      ? '-'
      : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String tfError(Object error) =>
    error is ValidationException && error.errors.isNotEmpty
    ? error.errors.values.expand((v) => v).join('\n')
    : error is AppException
    ? error.message
    : 'Tahfidz belum dapat diproses. Silakan coba lagi.';
String tfToken() {
  final random = Random.secure();
  final bytes = List.generate(16, (_) => random.nextInt(256));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  final h = bytes.map((v) => v.toRadixString(16).padLeft(2, '0')).join();
  return '${h.substring(0, 8)}-${h.substring(8, 12)}-${h.substring(12, 16)}-${h.substring(16, 20)}-${h.substring(20)}';
}
