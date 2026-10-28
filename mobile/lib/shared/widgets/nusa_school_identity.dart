import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';

/// The school's photograph with daylight retouching, bundled for offline use.
class NusaSchoolPhoto extends StatelessWidget {
  const NusaSchoolPhoto({
    this.alignment = Alignment.center,
    this.asset = assetPath,
    super.key,
  });

  static const assetPath = 'assets/images/school-facade-daylight.webp';
  static const loginAssetPath = 'assets/images/school-login-header.webp';

  final Alignment alignment;
  final String asset;

  @override
  Widget build(BuildContext context) => Image.asset(
    asset,
    fit: BoxFit.cover,
    alignment: alignment,
    filterQuality: FilterQuality.high,
    excludeFromSemantics: true,
    errorBuilder: (context, error, stackTrace) =>
        const ColoredBox(color: NusaColors.background),
  );
}

class NusaTagline extends StatelessWidget {
  const NusaTagline({
    this.textAlign = TextAlign.center,
    this.fontSize = 18,
    super.key,
  });

  final TextAlign textAlign;
  final double fontSize;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    crossAxisAlignment: textAlign == TextAlign.center
        ? CrossAxisAlignment.center
        : CrossAxisAlignment.start,
    children: [
      Container(
        width: 32,
        height: 4,
        decoration: BoxDecoration(
          color: NusaColors.accent,
          borderRadius: BorderRadius.circular(4),
        ),
      ),
      const SizedBox(height: 8),
      Text(
        'Satu Nusa, Terhubung\nuntuk Bertumbuh',
        textAlign: textAlign,
        style: TextStyle(
          color: NusaColors.textPrimary,
          fontSize: fontSize,
          height: 1.25,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );
}
