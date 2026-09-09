import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';

Color studentCaseColor(String color) => switch (color) {
  'success' => NusaColors.success,
  'danger' => const Color(0xFFC53A3A),
  'warning' => const Color(0xFF9A7100),
  'neutral' => NusaColors.textSecondary,
  _ => NusaColors.primaryLight,
};

Color studentCaseSurface(String color) => switch (color) {
  'success' => NusaColors.successSurface,
  'danger' => const Color(0xFFFFEEEE),
  'warning' => const Color(0xFFFFF8D8),
  'neutral' => const Color(0xFFF0F3F7),
  _ => NusaColors.surfaceBlue,
};

String studentCaseDate(String? value, {bool includeTime = false}) {
  if (value == null || value.isEmpty) return '-';
  final date = DateTime.tryParse(value)?.toLocal();
  if (date == null) return value;
  const months = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
  ];
  final day = '${date.day} ${months[date.month - 1]} ${date.year}';
  if (!includeTime || !value.contains('T')) return day;
  final hour = date.hour.toString().padLeft(2, '0');
  final minute = date.minute.toString().padLeft(2, '0');
  return '$day · $hour.$minute WIB';
}
