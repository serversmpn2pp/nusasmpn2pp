import 'package:flutter/material.dart';
import 'package:flutter_math_fork/flutter_math.dart';

/// Renders the same delimited LaTeX stored by the web question editor.
class ExamMathText extends StatelessWidget {
  const ExamMathText(this.text, {this.style, this.textAlign, super.key});

  static final _formula = RegExp(r'\\\((.*?)\\\)|\\\[(.*?)\\\]', dotAll: true);

  final String text;
  final TextStyle? style;
  final TextAlign? textAlign;

  static bool containsFormula(String text) => _formula.hasMatch(text);

  @override
  Widget build(BuildContext context) {
    final spans = <InlineSpan>[];
    const lineBreak = TextSpan(text: '\n');
    var offset = 0;
    var afterDisplay = false;

    void addText(String part) {
      if (part.isEmpty) return;
      if (afterDisplay && !part.startsWith('\n')) spans.add(lineBreak);
      spans.add(TextSpan(text: part));
      afterDisplay = false;
    }

    for (final match in _formula.allMatches(text)) {
      final display = match[2] != null;
      final latex = (match[1] ?? match[2]!).trim();
      if (latex.isEmpty) continue;

      addText(text.substring(offset, match.start));
      if (afterDisplay ||
          (display && match.start > 0 && text[match.start - 1] != '\n')) {
        spans.add(lineBreak);
      }
      spans.add(
        WidgetSpan(
          alignment: PlaceholderAlignment.middle,
          child: SingleChildScrollView(
            primary: false,
            scrollDirection: Axis.horizontal,
            child: Math.tex(
              latex,
              mathStyle: display ? MathStyle.display : MathStyle.text,
              textStyle: style,
              // Text.rich scales the whole WidgetSpan with system text size.
              textScaleFactor: 1,
              onErrorFallback: (_) => Text(
                match[0]!,
                style: style,
                textScaler: TextScaler.noScaling,
              ),
            ),
          ),
        ),
      );
      afterDisplay = display;
      offset = match.end;
    }

    if (spans.isEmpty) return Text(text, style: style, textAlign: textAlign);
    addText(text.substring(offset));
    return Text.rich(
      TextSpan(children: spans),
      style: style,
      textAlign: textAlign,
    );
  }
}
