import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../domain/student_card.dart';

enum StudentCardSide { front, back }

// The measurements and artwork come from resources/views/kartu-pelajar/index.blade.php.
const studentCardPixelsPerMm = 96.0 / 25.4;
const _mm = studentCardPixelsPerMm;
const _pt = 96.0 / 72;
const _blue = Color(0xFF15477A);
const _yellow = Color(0xFFF1C40F);

class StudentCardArtwork extends StatelessWidget {
  const StudentCardArtwork({
    required this.student,
    required this.side,
    required this.photo,
    super.key,
  });

  static const frontBackground = AssetImage(
    'assets/images/kartu-pelajar/front.jpg',
  );
  static const backBackground = AssetImage(
    'assets/images/kartu-pelajar/back.jpg',
  );
  static const defaultPhoto = AssetImage(
    'assets/images/kartu-pelajar/default-user.png',
  );
  static const schoolLogo = AssetImage('assets/images/logo-smpn2pp.png');
  static const nusaLogo = AssetImage('assets/images/logo-nusa.png');

  final StudentCardPerson student;
  final StudentCardSide side;
  final ImageProvider photo;

  @override
  Widget build(BuildContext context) {
    final front = side == StudentCardSide.front;
    return MediaQuery.withNoTextScaling(
      child: DefaultTextStyle(
        style: const TextStyle(
          fontFamily: 'InstrumentSansCard',
          fontWeight: FontWeight.w700,
          color: Colors.white,
          height: 1.55,
        ),
        textAlign: TextAlign.center,
        child: ColoredBox(
          color: Colors.white,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(3 * _mm),
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: _blue,
                image: DecorationImage(
                  image: front ? frontBackground : backBackground,
                  fit: BoxFit.cover,
                ),
              ),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  const DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [Color(0x1F071C36), Color(0x5C071C36)],
                      ),
                    ),
                  ),
                  DecoratedBox(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(3 * _mm),
                      border: Border.all(
                        color: const Color(0x57FFFFFF),
                        width: .35 * _mm,
                      ),
                    ),
                  ),
                  if (front) _front() else _back(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _front() => Padding(
    padding: const EdgeInsets.all(4.2 * _mm),
    child: Column(
      children: [
        _logo(schoolLogo, front: true),
        const SizedBox(height: 1.6 * _mm),
        _pill(
          'KARTU PELAJAR',
          fontSize: 5.6 * _pt,
          padding: const EdgeInsets.symmetric(
            vertical: 1.1 * _mm,
            horizontal: 3 * _mm,
          ),
          background: const Color(0xB815477A),
          foreground: Colors.white,
          border: Border.all(color: const Color(0xEBF1C40F), width: .35 * _mm),
        ),
        const SizedBox(height: 2 * _mm),
        SizedBox(
          width: 24.5 * _mm,
          height: 28.8 * _mm,
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: _yellow,
              borderRadius: BorderRadius.circular(4 * _mm),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x38041223),
                  blurRadius: 12 * _mm,
                  offset: Offset(0, 5 * _mm),
                ),
              ],
            ),
            child: Padding(
              padding: const EdgeInsets.all(.9 * _mm),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(3.1 * _mm),
                child: ColoredBox(
                  color: Colors.white,
                  child: Image(image: photo, fit: BoxFit.cover),
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 2 * _mm),
        Expanded(
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _name(front: true),
                const SizedBox(height: .8 * _mm),
                _nisnPill(student.nisnLabel),
                const SizedBox(height: .8 * _mm),
                Text(
                  student.birthLabel,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 6.2 * _pt,
                    height: 1.16,
                    color: Color(0xF0FFFFFF),
                    shadows: [
                      Shadow(
                        color: Color(0x57000000),
                        blurRadius: 1.8 * _mm,
                        offset: Offset(0, .8 * _mm),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 2 * _mm),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(
            vertical: 1.35 * _mm,
            horizontal: 2.2 * _mm,
          ),
          decoration: BoxDecoration(
            color: _yellow,
            borderRadius: BorderRadius.circular(2 * _mm),
            boxShadow: const [
              BoxShadow(
                color: Color(0x2E041223),
                blurRadius: 6 * _mm,
                offset: Offset(0, 2 * _mm),
              ),
            ],
          ),
          child: const Text(
            'SMP NEGERI 2 PADANG PANJANG',
            style: TextStyle(color: _blue, fontSize: 6.1 * _pt, height: 1.15),
          ),
        ),
      ],
    ),
  );

  Widget _back() {
    final data = student.qrData ?? '';
    final qr = student.canMakeQr && RegExp(r'^[0-9]{1,41}$').hasMatch(data)
        ? (QrCode(1, QrErrorCorrectLevel.L)..addNumeric(data))
        : null;
    return Padding(
      padding: const EdgeInsets.symmetric(
        vertical: 4.2 * _mm,
        horizontal: 4.4 * _mm,
      ),
      child: Column(
        children: [
          _logo(nusaLogo, front: false),
          const SizedBox(height: 1.6 * _mm),
          const Text(
            'PRESENSI DIGITAL NUSA',
            style: TextStyle(
              fontSize: 7.6 * _pt,
              height: 1.15,
              shadows: [
                Shadow(
                  color: Color(0x59000000),
                  blurRadius: 2.5 * _mm,
                  offset: Offset(0, 1 * _mm),
                ),
              ],
            ),
          ),
          const SizedBox(height: 2.2 * _mm),
          Expanded(
            child: Center(
              child: Container(
                width: 38 * _mm,
                height: 43.5 * _mm,
                padding: const EdgeInsets.all(2.2 * _mm),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(4 * _mm),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x3D041223),
                      blurRadius: 16 * _mm,
                      offset: Offset(0, 6 * _mm),
                    ),
                  ],
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (qr == null)
                      _nisnPill('QR belum tersedia')
                    else
                      QrImageView.withQr(
                        key: const Key('student-card-qr'),
                        qr: qr,
                        size: 29 * _mm,
                        padding: EdgeInsets.all(
                          29 * _mm * 4 / (qr.moduleCount + 8),
                        ),
                        backgroundColor: Colors.white,
                        semanticsLabel: 'QR presensi siswa',
                      ),
                    const SizedBox(height: 1.2 * _mm),
                    _name(front: false),
                    const SizedBox(height: .55 * _mm),
                    Text(
                      student.nisnLabel,
                      style: const TextStyle(
                        color: Color(0xFF385873),
                        fontSize: 5.7 * _pt,
                        height: 1.1,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 2.2 * _mm),
          const Text(
            'Scan QR ini untuk layanan presensi siswa SMP Negeri 2 Padang Panjang.',
            style: TextStyle(
              color: Color(0xEBFFFFFF),
              fontSize: 6.4 * _pt,
              height: 1.25,
              shadows: [
                Shadow(
                  color: Color(0x47000000),
                  blurRadius: 2 * _mm,
                  offset: Offset(0, 1 * _mm),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _logo(AssetImage image, {required bool front}) {
    final size = (front ? 10.5 : 16) * _mm;
    return Container(
      width: size,
      height: size,
      padding: EdgeInsets.all((front ? .9 : 1.5) * _mm),
      decoration: BoxDecoration(
        color: const Color(0xF5FFFFFF),
        shape: front ? BoxShape.circle : BoxShape.rectangle,
        borderRadius: front ? null : BorderRadius.circular(4 * _mm),
        border: front
            ? Border.all(color: const Color(0xE0F1C40F), width: .45 * _mm)
            : null,
        boxShadow: [
          BoxShadow(
            color: const Color(0x30041223),
            blurRadius: (front ? 5 : 8) * _mm,
            offset: Offset(0, (front ? 2 : 3) * _mm),
          ),
        ],
      ),
      child: Image(image: image, fit: BoxFit.contain),
    );
  }

  Widget _name({required bool front}) {
    final size = switch (student.name.trim().runes.length) {
      <= 16 => 11.0,
      <= 20 => 9.0,
      <= 24 => 7.5,
      <= 28 => 7.0,
      <= 34 => 6.1,
      <= 42 => 5.3,
      _ => 4.7,
    };
    final backSize = ((size * .72).clamp(0, 6.2) * 10).round() / 10;
    return SizedBox(
      width: double.infinity,
      child: FittedBox(
        fit: BoxFit.scaleDown,
        child: Text(
          student.name.toUpperCase(),
          maxLines: 1,
          style: TextStyle(
            color: front ? Colors.white : _blue,
            fontSize: (front ? size : backSize) * _pt,
            height: 1.05,
            shadows: front
                ? const [
                    Shadow(
                      color: Color(0x6B000000),
                      blurRadius: 2.5 * _mm,
                      offset: Offset(0, 1 * _mm),
                    ),
                  ]
                : null,
          ),
        ),
      ),
    );
  }

  Widget _nisnPill(String text) => _pill(
    text,
    fontSize: 6.8 * _pt,
    padding: const EdgeInsets.symmetric(
      vertical: .8 * _mm,
      horizontal: 2.6 * _mm,
    ),
    background: const Color(0xEBFFFFFF),
    foreground: _blue,
  );

  Widget _pill(
    String text, {
    required double fontSize,
    required EdgeInsets padding,
    required Color background,
    required Color foreground,
    BoxBorder? border,
  }) => Container(
    padding: padding,
    decoration: BoxDecoration(
      color: background,
      borderRadius: BorderRadius.circular(999),
      border: border,
    ),
    child: FittedBox(
      fit: BoxFit.scaleDown,
      child: Text(
        text,
        maxLines: 1,
        style: TextStyle(fontSize: fontSize, color: foreground),
      ),
    ),
  );
}
