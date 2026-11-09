import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_violation_catalog/student_violation_catalog_view.dart';

void main() {
  testWidgets(
    'siswa membaca aturan dan poin, mencari, serta menyaring kategori pada layar kecil',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final calls = <(String, int?, int)>[];
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            studentViolationCatalogProvider.overrideWith((ref, filter) async {
              calls.add(filter);
              return {
                'items': [
                  {
                    'id': 1,
                    'kode': 'K001',
                    'nama': 'Tidak membawa kartu pelajar',
                    'poin': 10,
                    'kategori': 'Kehadiran',
                    'tingkat': 'ringan',
                  },
                ],
                'kategori': [
                  {'id': 2, 'nama': 'Kehadiran'},
                ],
                'halaman': 1,
                'halaman_terakhir': 1,
                'total': 1,
              };
            }),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const StudentViolationCatalogView(),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('10 poin'), findsOneWidget);
      expect(find.text('Tidak membawa kartu pelajar'), findsOneWidget);
      expect(find.byType(FloatingActionButton), findsNothing);
      expect(find.byIcon(Icons.edit), findsNothing);
      await tester.enterText(
        find.byKey(const Key('student-violation-search')),
        'kartu',
      );
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(calls.last.$1, 'kartu');
      await tester.tap(find.byKey(const Key('student-violation-category')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Kehadiran').last);
      await tester.pumpAndSettle();
      expect(calls.last.$2, 2);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
}
