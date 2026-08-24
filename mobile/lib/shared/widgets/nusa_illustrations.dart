import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';

class NusaSchoolIllustration extends StatelessWidget {
  const NusaSchoolIllustration({this.opacity = 1, super.key});

  final double opacity;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: opacity,
      child: const CustomPaint(painter: _SchoolPainter(), size: Size.infinite),
    );
  }
}

class NusaEducationIllustration extends StatelessWidget {
  const NusaEducationIllustration({super.key});

  @override
  Widget build(BuildContext context) {
    return const CustomPaint(painter: _EducationPainter(), size: Size.infinite);
  }
}

class NusaSplashDecoration extends StatelessWidget {
  const NusaSplashDecoration({super.key});

  @override
  Widget build(BuildContext context) {
    return const CustomPaint(
      painter: _SplashDecorationPainter(),
      size: Size.infinite,
    );
  }
}

class _SchoolPainter extends CustomPainter {
  const _SchoolPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final scale = math.min(size.width / 240, size.height / 130);
    final center = Offset(size.width / 2, size.height);
    canvas.save();
    canvas.translate(center.dx - 120 * scale, center.dy - 125 * scale);
    canvas.scale(scale);

    final blue = Paint()..color = const Color(0xFFCFE3FA);
    final blueDark = Paint()..color = const Color(0xFF4F8DCF);
    final navy = Paint()..color = NusaColors.primary;
    final pale = Paint()..color = const Color(0xFFEAF3FE);
    final green = Paint()..color = const Color(0xFF74BC68);
    final greenDark = Paint()..color = const Color(0xFF4A9D52);
    final yellow = Paint()..color = const Color(0xFFFFD768);

    _cloud(canvas, const Offset(34, 24), pale);
    _cloud(canvas, const Offset(196, 16), pale);
    canvas.drawCircle(const Offset(205, 25), 13, yellow);

    canvas.drawRect(const Rect.fromLTWH(50, 60, 140, 58), blue);
    final roof = Path()
      ..moveTo(43, 63)
      ..lineTo(120, 32)
      ..lineTo(197, 63)
      ..close();
    canvas.drawPath(roof, blueDark);
    canvas.drawRect(const Rect.fromLTWH(104, 42, 32, 76), pale);
    canvas.drawRect(const Rect.fromLTWH(110, 85, 20, 33), navy);
    canvas.drawCircle(const Offset(120, 57), 10, pale);
    canvas.drawCircle(const Offset(120, 57), 7, blueDark);
    canvas.drawLine(
      const Offset(120, 57),
      const Offset(120, 51),
      Paint()
        ..color = Colors.white
        ..strokeWidth = 2,
    );
    canvas.drawLine(
      const Offset(120, 57),
      const Offset(125, 60),
      Paint()
        ..color = Colors.white
        ..strokeWidth = 2,
    );

    for (final x in [62.0, 82.0, 158.0, 178.0]) {
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x, 76, 12, 19),
          const Radius.circular(2),
        ),
        Colors.white.paint,
      );
      canvas.drawLine(
        Offset(x + 6, 76),
        Offset(x + 6, 95),
        blueDark..strokeWidth = 1.5,
      );
    }

    canvas.drawRect(const Rect.fromLTWH(119, 24, 3, 13), navy);
    final flag = Path()
      ..moveTo(122, 24)
      ..lineTo(140, 28)
      ..lineTo(122, 33)
      ..close();
    canvas.drawPath(flag, navy);

    _tree(canvas, const Offset(33, 104), green, greenDark);
    _tree(canvas, const Offset(205, 104), green, greenDark);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(38, 117, 164, 6),
        const Radius.circular(3),
      ),
      greenDark,
    );
    canvas.restore();
  }

  void _cloud(Canvas canvas, Offset center, Paint paint) {
    canvas.drawCircle(center, 8, paint);
    canvas.drawCircle(center.translate(10, -4), 11, paint);
    canvas.drawCircle(center.translate(21, 1), 7, paint);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(center.dx - 7, center.dy, 36, 8),
        const Radius.circular(4),
      ),
      paint,
    );
  }

  void _tree(Canvas canvas, Offset center, Paint green, Paint dark) {
    canvas.drawRect(Rect.fromLTWH(center.dx - 2, center.dy - 12, 4, 23), dark);
    canvas.drawCircle(center.translate(0, -18), 12, green);
    canvas.drawCircle(center.translate(-7, -10), 9, green);
    canvas.drawCircle(center.translate(7, -9), 10, dark);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _EducationPainter extends CustomPainter {
  const _EducationPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final pale = Paint()..color = const Color(0xFFE6F0FC);
    final blue = Paint()..color = const Color(0xFF8DB8EB);
    final navy = Paint()..color = NusaColors.primary;
    final yellow = Paint()..color = NusaColors.accent;

    final wave = Path()
      ..moveTo(0, size.height * 0.4)
      ..quadraticBezierTo(
        size.width * 0.25,
        size.height * 0.18,
        size.width * 0.5,
        size.height * 0.43,
      )
      ..quadraticBezierTo(
        size.width * 0.78,
        size.height * 0.72,
        size.width,
        size.height * 0.34,
      )
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(wave, pale);

    final bookY = size.height * 0.62;
    final book = Path()
      ..moveTo(size.width * 0.22, bookY)
      ..quadraticBezierTo(
        size.width * 0.37,
        bookY - 20,
        size.width * 0.5,
        bookY,
      )
      ..quadraticBezierTo(
        size.width * 0.65,
        bookY - 20,
        size.width * 0.8,
        bookY,
      )
      ..lineTo(size.width * 0.77, bookY + 38)
      ..quadraticBezierTo(
        size.width * 0.64,
        bookY + 18,
        size.width * 0.5,
        bookY + 37,
      )
      ..quadraticBezierTo(
        size.width * 0.36,
        bookY + 18,
        size.width * 0.25,
        bookY + 38,
      )
      ..close();
    canvas.drawPath(book, Colors.white.paint);
    canvas.drawPath(
      book,
      Paint()
        ..color = blue.color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2,
    );
    canvas.drawLine(
      Offset(size.width * 0.5, bookY),
      Offset(size.width * 0.5, bookY + 37),
      blue..strokeWidth = 1.5,
    );

    for (var index = 0; index < 3; index++) {
      final y = bookY + 9 + index * 7;
      canvas.drawLine(
        Offset(size.width * 0.29, y),
        Offset(size.width * 0.45, y - 2),
        pale..strokeWidth = 2,
      );
      canvas.drawLine(
        Offset(size.width * 0.55, y - 2),
        Offset(size.width * 0.71, y),
        pale,
      );
    }

    _leafStem(canvas, Offset(size.width * 0.12, bookY + 28), blue, false);
    _leafStem(canvas, Offset(size.width * 0.88, bookY + 28), blue, true);

    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(size.width * 0.64, bookY + 39, size.width * 0.2, 8),
        const Radius.circular(3),
      ),
      navy,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(size.width * 0.67, bookY + 49, size.width * 0.18, 7),
        const Radius.circular(3),
      ),
      yellow,
    );
  }

  void _leafStem(Canvas canvas, Offset base, Paint paint, bool mirror) {
    final direction = mirror ? -1.0 : 1.0;
    canvas.drawLine(
      base,
      base.translate(12 * direction, -58),
      paint..strokeWidth = 2,
    );
    for (var index = 0; index < 3; index++) {
      final stem = base.translate(
        direction * (4 + index * 4),
        -16.0 - index * 15,
      );
      canvas.drawOval(
        Rect.fromCenter(
          center: stem.translate(direction * 8, -3),
          width: 16,
          height: 8,
        ),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _SplashDecorationPainter extends CustomPainter {
  const _SplashDecorationPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final blue = Paint()..color = const Color(0xFF1B5C9B);
    final blueLight = Paint()..color = const Color(0xFF2676B9);
    final accent = Paint()
      ..color = NusaColors.accent.withValues(alpha: 0.9)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;

    canvas.drawCircle(Offset(-size.width * 0.08, 0), size.width * 0.52, blue);
    canvas.drawCircle(
      Offset(-size.width * 0.18, -size.height * 0.03),
      size.width * 0.42,
      blueLight,
    );
    canvas.drawArc(
      Rect.fromCircle(
        center: Offset(-size.width * 0.05, -size.height * 0.02),
        radius: size.width * 0.58,
      ),
      0.15,
      1.8,
      false,
      accent,
    );

    canvas.drawCircle(
      Offset(size.width * 0.08, size.height * 1.08),
      size.width * 0.5,
      blue,
    );
    canvas.drawArc(
      Rect.fromCircle(
        center: Offset(size.width * 0.55, size.height * 1.06),
        radius: size.width * 0.55,
      ),
      math.pi,
      1.6,
      false,
      accent,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

extension on Color {
  Paint get paint => Paint()..color = this;
}
