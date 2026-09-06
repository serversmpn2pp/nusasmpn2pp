import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_label/application/inventory_label_document_service.dart';
import 'package:nusa/features/inventory_label/data/inventory_label_remote_data_source.dart';
import 'package:nusa/features/inventory_label/domain/inventory_label.dart';
import 'package:nusa/features/inventory_label/presentation/inventory_label_view.dart';

void main() {
  test('domain label mempertahankan aturan dan isi label desktop', () {
    final page = InventoryLabelPage.fromJson(_response());

    expect(page.rules.paperFormat, 'A4');
    expect(page.rules.marginMm, 8);
    expect(page.rules.gapMm, 3);
    expect(page.rules.maximumSelection, 500);
    expect(page.rules.maximumCopies, 20);
    expect(page.sizes.map((item) => item.label), [
      '50 x 30 mm',
      '65 x 35 mm',
      '80 x 45 mm',
    ]);
    expect(page.items.single.code, 'AST-2026-000001');
    expect(page.items.single.owner, 'SMPN 2 Padang Panjang');
    expect(page.items.single.sourceYear, 'Dana BOS 2026');
  });

  test('PDF menggunakan tiga ukuran desktop dan barcode Code 128', () async {
    final page = InventoryLabelPage.fromJson(_response());
    final builder = InventoryLabelPdfBuilder();

    for (final size in page.sizes) {
      final bytes = await builder.build(
        items: page.items,
        size: size,
        rules: page.rules,
        copies: 2,
      );
      expect(bytes.length, greaterThan(1000));
      expect(String.fromCharCodes(bytes.take(4)), '%PDF');
    }

    final stock = InventoryLabelPage.fromJson(_response(type: 'stok'));
    final bytes = await builder.build(
      items: stock.items,
      size: stock.sizes[1],
      rules: stock.rules,
      copies: 1,
    );
    expect(bytes.length, greaterThan(1000));
  });

  testWidgets(
    'label rapi di layar kecil dan mengirim ukuran serta salinan ke pencetak',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final document = _FakeInventoryLabelDocumentService();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            inventoryLabelRemoteDataSourceProvider.overrideWithValue(
              _FakeInventoryLabelRemoteDataSource(),
            ),
            inventoryLabelDocumentServiceProvider.overrideWithValue(document),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const InventoryLabelView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Label Inventaris'), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.ensureVisible(
        find.byKey(const Key('inventory-label-copy-plus')),
      );
      await tester.tap(find.byKey(const Key('inventory-label-copy-plus')));
      await tester.pumpAndSettle();
      await tester.drag(find.byType(CustomScrollView), const Offset(0, -620));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('inventory-label-preview')), findsOneWidget);
      expect(find.byKey(const Key('inventory-label-item-1')), findsOneWidget);
      expect(find.textContaining('AST-2026-000001'), findsWidgets);
      await tester.tap(find.byKey(const Key('print-inventory-label')));
      await tester.pumpAndSettle();

      expect(document.printed?.items.single.code, 'AST-2026-000001');
      expect(document.printed?.size.value, 'sedang');
      expect(document.printed?.copies, 2);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('pilihan dapat dikosongkan dan tombol cetak menjadi nonaktif', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          inventoryLabelRemoteDataSourceProvider.overrideWithValue(
            _FakeInventoryLabelRemoteDataSource(),
          ),
          inventoryLabelDocumentServiceProvider.overrideWithValue(
            _FakeInventoryLabelDocumentService(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const InventoryLabelView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Pilih nol'));
    await tester.pumpAndSettle();

    final button = tester.widget<FilledButton>(
      find.byKey(const Key('print-inventory-label')),
    );
    expect(button.onPressed, isNull);
    expect(find.byKey(const Key('inventory-label-preview')), findsNothing);
  });
}

class _FakeInventoryLabelRemoteDataSource
    implements InventoryLabelRemoteDataSource {
  @override
  Future<InventoryLabelPage> fetch(InventoryLabelFilters filters) async =>
      InventoryLabelPage.fromJson(_response(type: filters.type));
}

class _FakeInventoryLabelDocumentService
    implements InventoryLabelDocumentService {
  _PrintCall? printed;
  _PrintCall? shared;

  @override
  Future<bool> printLabels({
    required List<InventoryLabelItem> items,
    required InventoryLabelSize size,
    required InventoryLabelPrintRules rules,
    required int copies,
  }) async {
    printed = _PrintCall(items: items, size: size, copies: copies);
    return true;
  }

  @override
  Future<bool> shareLabels({
    required List<InventoryLabelItem> items,
    required InventoryLabelSize size,
    required InventoryLabelPrintRules rules,
    required int copies,
  }) async {
    shared = _PrintCall(items: items, size: size, copies: copies);
    return true;
  }
}

class _PrintCall {
  const _PrintCall({
    required this.items,
    required this.size,
    required this.copies,
  });

  final List<InventoryLabelItem> items;
  final InventoryLabelSize size;
  final int copies;
}

Map<String, dynamic> _response({String type = 'unit'}) => {
  'filter': {
    'jenis_label': type,
    'penerimaan_barang_id': null,
    'tahun_perolehan': null,
    'kategori_barang_id': null,
    'barang_id': null,
    'lokasi_barang_id': null,
  },
  'aturan_cetak': {
    'format_kertas': 'A4',
    'margin_mm': 8,
    'jarak_label_mm': 3,
    'maksimal_pilihan': 500,
    'maksimal_salinan': 20,
  },
  'ringkasan': {'jumlah_pilihan': 1, 'jenis_label': type},
  'pilihan': {
    'jenis_label': [
      {'nilai': 'unit', 'label': 'Barang tidak habis pakai'},
      {'nilai': 'stok', 'label': 'Barang habis pakai'},
    ],
    'ukuran': [
      {
        'nilai': 'kecil',
        'label': '50 x 30 mm',
        'lebar_mm': 50,
        'tinggi_mm': 30,
      },
      {
        'nilai': 'sedang',
        'label': '65 x 35 mm',
        'lebar_mm': 65,
        'tinggi_mm': 35,
      },
      {
        'nilai': 'besar',
        'label': '80 x 45 mm',
        'lebar_mm': 80,
        'tinggi_mm': 45,
      },
    ],
    'penerimaan': [
      {
        'id': 1,
        'nomor': 'BAST-001/2026',
        'tanggal': '2026-07-15',
        'label': 'BAST-001/2026 - 15-07-2026',
      },
    ],
    'kategori': [
      {'id': 1, 'nama': 'Elektronik', 'kode': 'ELEKTRONIK'},
    ],
    'barang': [
      {
        'id': 1,
        'nama': type == 'unit' ? 'Printer Epson' : 'Tinta Printer',
        'kode': type == 'unit' ? '02.06.01.05.40' : 'BHP-000001',
        'label': type == 'unit'
            ? 'Printer Epson - 02.06.01.05.40'
            : 'Tinta Printer - BHP-000001',
      },
    ],
    'lokasi': [
      {'id': 1, 'nama': 'Labor Komputer', 'kode': 'LAB'},
    ],
  },
  'items': type == 'unit'
      ? [
          {
            'id': 1,
            'jenis': 'unit',
            'kode': 'AST-2026-000001',
            'nama': 'Printer Epson',
            'nomor_aset_resmi': '12.03.15.08.10.2026.08',
            'kode_barang': '02.06.01.05.40.01',
            'sumber_tahun': 'Dana BOS 2026',
            'pemilik': 'SMPN 2 Padang Panjang',
            'lokasi': 'Labor Komputer',
            'ringkasan': 'Printer Epson - Labor Komputer',
          },
        ]
      : [
          {
            'id': 2,
            'jenis': 'stok',
            'judul': 'BARANG HABIS PAKAI',
            'kode': 'BHP-000001',
            'nama': 'Tinta Printer',
            'lokasi': 'Gudang Utama',
            'satuan': 'Botol',
            'ringkasan': 'BHP-000001 - Gudang Utama',
          },
        ],
};
