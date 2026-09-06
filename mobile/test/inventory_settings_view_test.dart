import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_settings/data/inventory_settings_remote_data_source.dart';
import 'package:nusa/features/inventory_settings/domain/inventory_settings.dart';
import 'package:nusa/features/inventory_settings/presentation/inventory_settings_view.dart';

void main() {
  test('domain pengaturan inventaris membaca identitas dan contoh kode', () {
    final settings = InventorySettings.fromJson(_response());

    expect(settings.assetNumberPrefix, '12.03.15.08.10');
    expect(settings.internalIdDigits, 6);
    expect(settings.assetNumberExample, '12.03.15.08.10.2026.08');
    expect(settings.consumableCodeExample, 'BHP-000001');
    expect(settings.assetUnitCodeExample, 'AST-2026-000001');
    expect(settings.canManage, isTrue);
  });

  testWidgets('pengaturan inventaris rapi di layar kecil dan dapat disimpan', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeInventorySettingsRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          inventorySettingsRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const InventorySettingsView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.widgetWithText(AppBar, 'Pengaturan Inventaris'),
      findsOneWidget,
    );
    expect(find.text('Pratinjau Identitas'), findsOneWidget);
    expect(find.text('12.03.15.08.10.2026.08'), findsOneWidget);
    expect(tester.takeException(), isNull);

    final suffix = find.byKey(const Key('inventory-settings-suffix'));
    await tester.ensureVisible(suffix);
    await tester.enterText(suffix, '09');
    final owner = find.byKey(const Key('inventory-settings-owner'));
    await tester.ensureVisible(owner);
    await tester.enterText(owner, 'SMP Negeri 2 Padang Panjang');
    final digits = find.byKey(const Key('inventory-settings-digits'));
    await tester.ensureVisible(digits);
    await tester.tap(digits);
    await tester.pumpAndSettle();
    await tester.tap(find.text('7 digit · 0000001').last);
    await tester.pumpAndSettle();

    final save = find.byKey(const Key('save-inventory-settings'));
    await tester.ensureVisible(save);
    await tester.tap(save);
    await tester.pumpAndSettle();

    expect(remote.updated?.assetNumberSuffix, '09');
    expect(remote.updated?.ownerName, 'SMP Negeri 2 Padang Panjang');
    expect(remote.updated?.internalIdDigits, 7);
    expect(
      find.text('Pengaturan inventaris berhasil disimpan.'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
}

class _FakeInventorySettingsRemoteDataSource
    implements InventorySettingsRemoteDataSource {
  InventorySettingsFormValue? updated;

  @override
  Future<InventorySettings> fetch() async =>
      InventorySettings.fromJson(_response());

  @override
  Future<InventorySettings> update(InventorySettingsFormValue value) async {
    updated = value;
    final sequence = '1'.padLeft(value.internalIdDigits, '0');
    return InventorySettings(
      id: 1,
      code: 'utama',
      assetNumberPrefix: value.assetNumberPrefix,
      assetNumberSuffix: value.assetNumberSuffix,
      ownerName: value.ownerName,
      internalIdDigits: value.internalIdDigits,
      exampleYear: 2026,
      assetNumberExample:
          '${value.assetNumberPrefix}.2026.${value.assetNumberSuffix}',
      consumableCodeExample: 'BHP-$sequence',
      assetUnitCodeExample: 'AST-2026-$sequence',
      updatedBy: 'Administrator',
      updatedAt: DateTime(2026, 9, 6, 14, 30),
      canManage: true,
    );
  }
}

Map<String, dynamic> _response() => {
  'id': 1,
  'kode': 'utama',
  'awalan_nomor_aset': '12.03.15.08.10',
  'akhiran_nomor_aset': '08',
  'nama_pemilik': 'SMPN 2 Padang Panjang',
  'jumlah_digit_id_internal': 6,
  'tahun_contoh': 2026,
  'contoh_nomor_aset': '12.03.15.08.10.2026.08',
  'contoh_kode_barang_habis_pakai': 'BHP-000001',
  'contoh_kode_unit_aset': 'AST-2026-000001',
  'diperbarui_oleh': null,
  'diperbarui_pada': '2026-09-06T12:00:00+07:00',
  'hak_akses': {'dapat_kelola': true},
};
