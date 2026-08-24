import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';

class NusaLogo extends StatelessWidget {
  const NusaLogo({this.size = 52, super.key});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Image.asset(
      'assets/images/logo-nusa.png',
      width: size,
      height: size,
      fit: BoxFit.contain,
      filterQuality: FilterQuality.high,
      semanticLabel: 'Logo NUSA',
    );
  }
}

class NusaBrand extends StatelessWidget {
  const NusaBrand({
    this.logoSize = 42,
    this.textColor = NusaColors.primary,
    this.compact = false,
    super.key,
  });

  final double logoSize;
  final Color textColor;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        NusaLogo(size: logoSize),
        SizedBox(width: compact ? 7 : 10),
        Text(
          'NUSA',
          style: TextStyle(
            color: textColor,
            fontSize: compact ? 22 : 30,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.4,
          ),
        ),
      ],
    );
  }
}
