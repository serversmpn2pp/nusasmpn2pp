import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_label/domain/inventory_label.dart';
import 'package:nusa/features/inventory_label/presentation/widgets/code128_barcode.dart';

class InventoryLabelPreview extends StatelessWidget {
  const InventoryLabelPreview({
    required this.item,
    required this.size,
    this.selected = true,
    this.onTap,
    super.key,
  });

  final InventoryLabelItem item;
  final InventoryLabelSize size;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final width = constraints.maxWidth;
      final height = width * size.heightMm / size.widthMm;
      final scale = width / (size.widthMm * 3.78);
      return InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Opacity(
          opacity: selected ? 1 : 0.52,
          child: Container(
            height: height,
            padding: EdgeInsets.symmetric(
              horizontal: _horizontalPadding * scale,
              vertical: _verticalPadding * scale,
            ),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border.all(
                color: selected ? NusaColors.primary : NusaColors.outline,
                width: (1.3 * scale).clamp(0.8, 1.5),
              ),
              borderRadius: BorderRadius.circular((5.7 * scale).clamp(4, 8)),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x130D2D4B),
                  blurRadius: 8,
                  offset: Offset(0, 3),
                ),
              ],
            ),
            child: item.isAsset
                ? _AssetContent(item: item, size: size, scale: scale)
                : _StockContent(item: item, size: size, scale: scale),
          ),
        ),
      );
    },
  );

  double get _horizontalPadding => switch (size.value) {
    'kecil' => 6.05,
    'besar' => 9.83,
    _ => 7.56,
  };

  double get _verticalPadding => switch (size.value) {
    'kecil' => 4.54,
    'besar' => 7.56,
    _ => 5.67,
  };
}

class _AssetContent extends StatelessWidget {
  const _AssetContent({
    required this.item,
    required this.size,
    required this.scale,
  });

  final InventoryLabelItem item;
  final InventoryLabelSize size;
  final double scale;

  @override
  Widget build(BuildContext context) {
    final heading = _font(size, small: 5.2, medium: 6.2, large: 7.4) * scale;
    final identity = _font(size, small: 4.2, medium: 5.2, large: 6.3) * scale;
    final code = _font(size, small: 4.5, medium: 5.6, large: 6.8) * scale;
    final barcodeHeight =
        _font(size, small: 26.46, medium: 34.02, large: 49.13) * scale;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _Heading(left: item.name, right: 'ASET NUSA', fontSize: heading),
        SizedBox(height: 2.5 * scale),
        for (final text in [
          item.officialAssetNumber ?? '-',
          item.goodsCode ?? '-',
          item.sourceYear ?? '-',
          item.owner ?? '-',
        ])
          Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: const Color(0xFF0D2D4B),
              fontSize: identity,
              height: 1.05,
              fontWeight: FontWeight.w800,
            ),
          ),
        SizedBox(height: 2.8 * scale),
        SizedBox(
          height: barcodeHeight,
          child: Code128Barcode(data: item.code),
        ),
        SizedBox(height: 1.7 * scale),
        _Code(value: item.code, fontSize: code),
      ],
    );
  }
}

class _StockContent extends StatelessWidget {
  const _StockContent({
    required this.item,
    required this.size,
    required this.scale,
  });

  final InventoryLabelItem item;
  final InventoryLabelSize size;
  final double scale;

  @override
  Widget build(BuildContext context) {
    final heading = _font(size, small: 5.2, medium: 6.2, large: 7.4) * scale;
    final name = _font(size, small: 6, medium: 7.4, large: 9) * scale;
    final meta = _font(size, small: 4.2, medium: 5.2, large: 6.3) * scale;
    final code = _font(size, small: 4.5, medium: 5.6, large: 6.8) * scale;
    final barcodeHeight =
        _font(size, small: 26.46, medium: 34.02, large: 49.13) * scale;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _Heading(
          left: item.title ?? 'BARANG BERBASIS STOK',
          right: 'NUSA',
          fontSize: heading,
        ),
        SizedBox(height: 4.9 * scale),
        Text(
          item.name,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: const Color(0xFF0D2D4B),
            fontSize: name,
            height: 1.1,
            fontWeight: FontWeight.w900,
          ),
        ),
        SizedBox(height: 1.9 * scale),
        Row(
          children: [
            Expanded(child: _meta(item.location, meta, TextAlign.left)),
            Expanded(child: _meta(item.unit ?? '-', meta, TextAlign.right)),
          ],
        ),
        SizedBox(height: 2.8 * scale),
        SizedBox(
          height: barcodeHeight,
          child: Code128Barcode(data: item.code),
        ),
        SizedBox(height: 1.7 * scale),
        _Code(value: item.code, fontSize: code),
      ],
    );
  }
}

class _Heading extends StatelessWidget {
  const _Heading({
    required this.left,
    required this.right,
    required this.fontSize,
  });

  final String left;
  final String right;
  final double fontSize;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.only(bottom: 2),
    decoration: const BoxDecoration(
      border: Border(bottom: BorderSide(color: NusaColors.accent, width: 1)),
    ),
    child: Row(
      children: [
        Expanded(
          child: Text(
            left.toUpperCase(),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: NusaColors.primary,
              fontSize: fontSize,
              height: 1,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        const SizedBox(width: 5),
        Text(
          right,
          style: TextStyle(
            color: NusaColors.primary,
            fontSize: fontSize,
            height: 1,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    ),
  );
}

class _Code extends StatelessWidget {
  const _Code({required this.value, required this.fontSize});

  final String value;
  final double fontSize;

  @override
  Widget build(BuildContext context) => Text(
    value,
    maxLines: 1,
    overflow: TextOverflow.ellipsis,
    textAlign: TextAlign.center,
    style: TextStyle(
      color: const Color(0xFF0D2D4B),
      fontSize: fontSize,
      height: 1,
      fontWeight: FontWeight.w900,
    ),
  );
}

Widget _meta(String text, double size, TextAlign align) => Text(
  text,
  maxLines: 1,
  overflow: TextOverflow.ellipsis,
  textAlign: align,
  style: TextStyle(
    color: const Color(0xFF526B83),
    fontSize: size,
    height: 1,
    fontWeight: FontWeight.w700,
  ),
);

double _font(
  InventoryLabelSize size, {
  required double small,
  required double medium,
  required double large,
}) => switch (size.value) {
  'kecil' => small,
  'besar' => large,
  _ => medium,
};
