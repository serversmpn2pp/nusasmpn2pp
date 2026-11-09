import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/shared/widgets/exam_unlock_dialog.dart';

void main() {
  testWidgets(
    'form pembukaan dapat dibatalkan dan tetap terjangkau saat keyboard muncul',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      addTearDown(tester.view.resetViewInsets);
      String? result;
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light,
          home: Builder(
            builder: (context) => Scaffold(
              body: Center(
                child: FilledButton(
                  child: const Text('Tinjau'),
                  onPressed: () async {
                    result = await showExamUnlockDialog(
                      context,
                      participantName:
                          'Siswa dengan nama lengkap yang panjang sekali',
                    );
                  },
                ),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Tinjau'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('exam-unlock-reason')),
        'Alasan belum selesai diketik',
      );
      tester.view.viewInsets = const FakeViewPadding(bottom: 280);
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('exam-unlock-submit')));
      expect(tester.takeException(), isNull);
      await tester.tap(find.text('Batal'));
      await tester.pumpAndSettle();
      expect(result, isNull);
      expect(find.byType(AlertDialog), findsNothing);
    },
  );
}
