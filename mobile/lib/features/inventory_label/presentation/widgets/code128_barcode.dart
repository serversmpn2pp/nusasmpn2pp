import 'package:flutter/material.dart';

class Code128Barcode extends StatelessWidget {
  const Code128Barcode({required this.data, super.key});

  final String data;

  @override
  Widget build(BuildContext context) =>
      CustomPaint(painter: _Code128Painter(data), size: Size.infinite);
}

class _Code128Painter extends CustomPainter {
  _Code128Painter(this.data);

  final String data;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawRect(Offset.zero & size, Paint()..color = Colors.white);
    final values = _values(data);
    if (values.isEmpty) return;
    final patterns = values.map((value) => _patterns[value]).toList();
    final modules =
        20 +
        patterns.fold<int>(0, (sum, pattern) {
          return sum +
              pattern.codeUnits.fold<int>(
                0,
                (total, digit) => total + digit - 48,
              );
        });
    final moduleWidth = size.width / modules;
    final paint = Paint()
      ..color = const Color(0xFF111827)
      ..isAntiAlias = false;
    var x = 10 * moduleWidth;
    for (final pattern in patterns) {
      for (var index = 0; index < pattern.length; index++) {
        final width = (pattern.codeUnitAt(index) - 48) * moduleWidth;
        if (index.isEven) {
          canvas.drawRect(Rect.fromLTWH(x, 0, width, size.height), paint);
        }
        x += width;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _Code128Painter oldDelegate) =>
      data != oldDelegate.data;
}

List<int> _values(String data) {
  if (data.isEmpty || data.length > 80) return const [];
  final values = <int>[104];
  var checksum = 104;
  for (var index = 0; index < data.length; index++) {
    final character = data.codeUnitAt(index);
    if (character < 32 || character > 126) return const [];
    final value = character - 32;
    values.add(value);
    checksum += value * (index + 1);
  }
  values
    ..add(checksum % 103)
    ..add(106);
  return values;
}

const _patterns = <String>[
  '212222',
  '222122',
  '222221',
  '121223',
  '121322',
  '131222',
  '122213',
  '122312',
  '132212',
  '221213',
  '221312',
  '231212',
  '112232',
  '122132',
  '122231',
  '113222',
  '123122',
  '123221',
  '223211',
  '221132',
  '221231',
  '213212',
  '223112',
  '312131',
  '311222',
  '321122',
  '321221',
  '312212',
  '322112',
  '322211',
  '212123',
  '212321',
  '232121',
  '111323',
  '131123',
  '131321',
  '112313',
  '132113',
  '132311',
  '211313',
  '231113',
  '231311',
  '112133',
  '112331',
  '132131',
  '113123',
  '113321',
  '133121',
  '313121',
  '211331',
  '231131',
  '213113',
  '213311',
  '213131',
  '311123',
  '311321',
  '331121',
  '312113',
  '312311',
  '332111',
  '314111',
  '221411',
  '431111',
  '111224',
  '111422',
  '121124',
  '121421',
  '141122',
  '141221',
  '112214',
  '112412',
  '122114',
  '122411',
  '142112',
  '142211',
  '241211',
  '221114',
  '413111',
  '241112',
  '134111',
  '111242',
  '121142',
  '121241',
  '114212',
  '124112',
  '124211',
  '411212',
  '421112',
  '421211',
  '212141',
  '214121',
  '412121',
  '111143',
  '111341',
  '131141',
  '114113',
  '114311',
  '411113',
  '411311',
  '113141',
  '114131',
  '311141',
  '411131',
  '211412',
  '211214',
  '211232',
  '2331112',
];
